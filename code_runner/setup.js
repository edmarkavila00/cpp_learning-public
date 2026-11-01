const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const configPath = path.resolve(__dirname, '..', '..', '..', 'cpp_learning_runner.json');

if (fs.existsSync(configPath)) {
    console.log(`Runner configuration already exists: ${configPath}`);
    console.log('Keep the token private. Delete this file and rerun setup only if you need to rotate it.');
    process.exit(0);
}

const config = { token: crypto.randomBytes(32).toString('hex') };
fs.writeFileSync(configPath, `${JSON.stringify(config, null, 2)}\n`, { flag: 'wx', mode: 0o600 });
console.log(`Created private runner configuration: ${configPath}`);
console.log('Keep this file outside the web root and do not share its token.');
