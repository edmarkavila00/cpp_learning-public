const crypto = require('node:crypto');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

const CONFIG_PATH = path.resolve(__dirname, '..', '..', '..', 'cpp_learning_runner.json');
const IMAGE = 'cppquest-cpp-runner:local';
const PORT = 8787;
const MAX_BODY_BYTES = 32_768;
const MAX_SOURCE_BYTES = 16_000;
const MAX_INPUT_BYTES = 512;
const MAX_TESTS = 8;
const JOB_TIMEOUT_MS = 25_000;
const OUTPUT_LIMIT = 12_000;
const MAX_CONCURRENT_RUNS = 2;
const recentRuns = [];
let activeRuns = 0;

function loadToken() {
    let config;
    try {
        config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
    } catch (error) {
        throw new Error(`Could not read ${CONFIG_PATH}: ${error.message}`);
    }
    if (typeof config.token !== 'string' || config.token.length < 32) {
        throw new Error('Runner token must be at least 32 characters long.');
    }
    return config.token;
}

const token = loadToken();

function sendJson(response, status, value) {
    const body = JSON.stringify(value);
    response.writeHead(status, {
        'content-type': 'application/json; charset=utf-8',
        'content-length': Buffer.byteLength(body),
        'cache-control': 'no-store',
    });
    response.end(body);
}

function readBody(request) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let length = 0;
        request.on('data', chunk => {
            length += chunk.length;
            if (length > MAX_BODY_BYTES) {
                reject(new Error('Request is too large.'));
                request.destroy();
                return;
            }
            chunks.push(chunk);
        });
        request.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
        request.on('error', reject);
    });
}

function validateRequest(value) {
    if (!value || typeof value.source !== 'string' || !Array.isArray(value.tests)) {
        throw new Error('Invalid compile request.');
    }
    if (!value.source.trim() || Buffer.byteLength(value.source, 'utf8') > MAX_SOURCE_BYTES) {
        throw new Error('Code must contain between 1 and 16,000 UTF-8 bytes.');
    }
    if (value.tests.length < 1 || value.tests.length > MAX_TESTS) {
        throw new Error('The test suite is invalid.');
    }
    for (const test of value.tests) {
        if (!test || typeof test.input !== 'string' || typeof test.expected !== 'string'
            || Buffer.byteLength(test.input, 'utf8') > MAX_INPUT_BYTES
            || Buffer.byteLength(test.expected, 'utf8') > 8192) {
            throw new Error('A test case is invalid.');
        }
    }
}

function authorize(request) {
    const header = request.headers.authorization || '';
    const supplied = header.startsWith('Bearer ') ? header.slice(7) : '';
    const suppliedBytes = Buffer.from(supplied);
    const expectedBytes = Buffer.from(token);
    return suppliedBytes.length === expectedBytes.length
        && crypto.timingSafeEqual(suppliedBytes, expectedBytes);
}

function takeRunSlot() {
    const now = Date.now();
    while (recentRuns.length && recentRuns[0] <= now - 60_000) recentRuns.shift();
    if (recentRuns.length >= 30) return false;
    recentRuns.push(now);
    return true;
}

function executeInContainer(payload) {
    return new Promise((resolve, reject) => {
        const name = `cppq-${crypto.randomUUID()}`;
        const args = [
            'run', '--rm', '--name', name,
            '--network=none',
            '--read-only',
            '--tmpfs', '/tmp:rw,exec,nosuid,nodev,size=64m,uid=10001,gid=10001,mode=1777',
            '--memory=256m', '--memory-swap=256m', '--cpus=1',
            '--pids-limit=32',
            '--security-opt=no-new-privileges:true',
            '--cap-drop=ALL',
            '--user=10001:10001',
            '--env=PYTHONDONTWRITEBYTECODE=1',
            '--init',
            IMAGE,
        ];
        const child = spawn('docker', args, { windowsHide: true, shell: false });
        let stdout = '';
        let stderr = '';
        let settled = false;
        let expired = false;

        const timer = setTimeout(() => {
            expired = true;
            child.kill();
            const cleanup = spawn('docker', ['rm', '--force', name], { windowsHide: true, shell: false });
            cleanup.on('error', () => {});
        }, JOB_TIMEOUT_MS);

        function finish(error, value) {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            if (error) reject(error);
            else resolve(value);
        }

        child.stdout.setEncoding('utf8');
        child.stderr.setEncoding('utf8');
        child.stdout.on('data', chunk => {
            stdout = (stdout + chunk).slice(-OUTPUT_LIMIT);
        });
        child.stderr.on('data', chunk => {
            stderr = (stderr + chunk).slice(-OUTPUT_LIMIT);
        });
        child.on('error', error => finish(new Error(`Could not start Docker: ${error.message}`)));
        child.on('close', code => {
            if (expired) {
                finish(new Error('The C++ runner took too long and was stopped.'));
                return;
            }
            if (code !== 0) {
                finish(new Error(stderr.trim() || 'The isolated C++ container failed. Check that Docker is running and the runner image is built.'));
                return;
            }
            try {
                finish(null, JSON.parse(stdout));
            } catch {
                finish(new Error('The C++ container returned an invalid result.'));
            }
        });
        child.stdin.on('error', () => {});
        child.stdin.end(JSON.stringify(payload));
    });
}

const server = http.createServer(async (request, response) => {
    if (request.socket.remoteAddress !== '127.0.0.1' && request.socket.remoteAddress !== '::ffff:127.0.0.1') {
        sendJson(response, 403, { error: 'The runner only accepts requests from this computer.' });
        return;
    }
    if (request.method === 'GET' && request.url === '/health') {
        sendJson(response, 200, { ready: true });
        return;
    }
    if (request.method !== 'POST' || request.url !== '/run') {
        sendJson(response, 404, { error: 'Route not found.' });
        return;
    }
    if (!authorize(request)) {
        sendJson(response, 401, { error: 'Runner authorization failed.' });
        return;
    }
    if (!takeRunSlot()) {
        sendJson(response, 429, { error: 'Too many code runs. Wait a minute and try again.' });
        return;
    }
    if (activeRuns >= MAX_CONCURRENT_RUNS) {
        sendJson(response, 429, { error: 'The compiler is busy. Wait for a current run to finish and try again.' });
        return;
    }

    activeRuns += 1;
    try {
        const value = JSON.parse(await readBody(request));
        validateRequest(value);
        const result = await executeInContainer(value);
        sendJson(response, 200, result);
    } catch (error) {
        sendJson(response, 503, { error: error.message || 'The C++ runner could not process this program.' });
    } finally {
        activeRuns -= 1;
    }
});

server.listen(PORT, '127.0.0.1', () => {
    console.log(`CppQuest C++ runner listening on http://127.0.0.1:${PORT}`);
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => server.close(() => process.exit(0)));
}
