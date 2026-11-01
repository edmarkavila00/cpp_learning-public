<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/code_game.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();

$problems = codeGameProblems();
$stmt = $pdo->prepare("SELECT problem_id, attempts, solved FROM code_game_progress WHERE user_id = ?");
$stmt->execute([(int)$user['id']]);
$progress = [];
foreach ($stmt->fetchAll() as $row) {
    $progress[$row['problem_id']] = $row;
}

$firstAvailableId = array_key_first($problems);
foreach (array_keys($problems) as $index => $problemId) {
    if ($index === 0 || !empty($progress[array_keys($problems)[$index - 1]]['solved'])) {
        $firstAvailableId = $problemId;
    } else {
        break;
    }
}

$requestedId = $_GET['problem'] ?? '';
$selectedId = is_string($requestedId) && isset($problems[$requestedId])
    ? $requestedId
    : $firstAvailableId;
$selectedIndex = array_search($selectedId, array_keys($problems), true);
$selectedUnlocked = $selectedIndex === 0
    || !empty($progress[array_keys($problems)[$selectedIndex - 1]]['solved']);
if (!$selectedUnlocked) {
    $selectedId = $firstAvailableId;
}
$problem = $problems[$selectedId];
$solvedCount = count(array_filter($progress, static fn($row) => !empty($row['solved'])));
$pageTitle = 'Code Lab';
include __DIR__ . '/includes/header.php';
?>

<main class="code-game-page mx-auto max-w-6xl px-4 py-8">
    <header class="code-game-hero mb-6 flex flex-wrap items-center justify-between gap-5 rounded-2xl border p-5 sm:p-7">
        <div class="flex items-center gap-4">
            <div class="code-game-hero-icon flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl" aria-hidden="true">
                <i class="fa-solid fa-terminal"></i>
            </div>
            <div>
                <p class="mb-1 text-xs font-black uppercase tracking-[.22em] text-emerald-300">Build · Compile · Clear</p>
                <h1 class="text-3xl font-black text-white sm:text-4xl">C++ Code Lab</h1>
                <p class="mt-1 text-sm text-slate-300">Solve the problem with code, then run it against the test suite.</p>
            </div>
        </div>
        <div class="code-game-score rounded-xl border px-4 py-3 text-right">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Programs accepted</div>
            <div class="mt-1 text-xl font-black text-white"><?= $solvedCount ?> <span class="text-sm text-slate-400">/ <?= count($problems) ?></span></div>
        </div>
    </header>

    <div class="grid items-start gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
        <nav class="code-game-panel rounded-2xl border p-4" aria-label="Coding missions">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-black text-white">Missions</h2>
                <span class="text-xs font-bold text-emerald-300"><?= $solvedCount ?>/<?= count($problems) ?></span>
            </div>
            <ol class="grid gap-2">
                <?php foreach ($problems as $problemId => $mission): ?>
                    <?php
                        $missionIndex = array_search($problemId, array_keys($problems), true);
                        $unlocked = $missionIndex === 0
                            || !empty($progress[array_keys($problems)[$missionIndex - 1]]['solved']);
                        $cleared = !empty($progress[$problemId]['solved']);
                        $selected = $problemId === $selectedId;
                    ?>
                    <li>
                        <?php if ($unlocked): ?>
                            <a href="/cpp_learning/code_game.php?problem=<?= rawurlencode($problemId) ?>"
                               class="code-game-mission <?= $selected ? 'code-game-mission-selected' : '' ?> <?= $cleared ? 'code-game-mission-cleared' : '' ?> flex items-center gap-3 rounded-xl border p-3">
                                <span class="code-game-mission-icon" aria-hidden="true">
                                    <i class="fa-solid <?= $cleared ? 'fa-circle-check' : 'fa-code' ?>"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400"><?= htmlspecialchars($mission['difficulty']) ?></span>
                                    <span class="mt-0.5 block truncate text-sm font-bold text-white"><?= htmlspecialchars($mission['title']) ?></span>
                                </span>
                                <span class="text-xs font-black text-emerald-300"><?= $cleared ? 'CLEAR' : '+20' ?></span>
                            </a>
                        <?php else: ?>
                            <div class="code-game-mission code-game-mission-locked flex items-center gap-3 rounded-xl border p-3" aria-label="<?= htmlspecialchars($mission['title']) ?> — locked">
                                <span class="code-game-mission-icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-500"><?= htmlspecialchars($mission['difficulty']) ?></span>
                                    <span class="mt-0.5 block truncate text-sm font-bold text-slate-400"><?= htmlspecialchars($mission['title']) ?></span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="mt-4 rounded-lg border border-emerald-900/70 bg-emerald-950/30 p-3 text-xs leading-relaxed text-slate-400">
                Clear a mission to unlock the next one. Accepted solutions earn 20 XP.
            </p>
        </nav>

        <section class="code-game-panel min-w-0 rounded-2xl border p-4 sm:p-5" aria-labelledby="problem-title">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.18em] text-emerald-300"><?= htmlspecialchars($problem['difficulty']) ?> mission</p>
                    <h2 id="problem-title" class="mt-1 text-2xl font-black text-white"><?= htmlspecialchars($problem['title']) ?></h2>
                </div>
                <span class="code-game-xp rounded-full border px-3 py-1 text-xs font-black">⚡ +20 XP</span>
            </div>

            <div class="code-game-problem mb-4 rounded-xl border p-4">
                <p class="font-bold text-white"><?= htmlspecialchars($problem['description']) ?></p>
                <p class="mt-2 text-sm leading-relaxed text-slate-300"><?= htmlspecialchars($problem['objective']) ?></p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <h3 class="mb-1 text-xs font-black uppercase tracking-wider text-slate-400">Example input</h3>
                        <pre class="code-game-sample rounded-lg p-3 text-sm text-emerald-100"><code><?= htmlspecialchars($problem['sample_input']) ?></code></pre>
                    </div>
                    <div>
                        <h3 class="mb-1 text-xs font-black uppercase tracking-wider text-slate-400">Expected output</h3>
                        <pre class="code-game-sample rounded-lg p-3 text-sm text-emerald-100"><code><?= htmlspecialchars($problem['sample_output']) ?></code></pre>
                    </div>
                </div>
            </div>

            <label for="codeEditor" class="mb-2 block text-sm font-bold text-white">Your C++ program</label>
            <div class="code-game-editor-shell rounded-xl border">
                <pre id="codeHighlight" class="code-game-highlight font-mono text-sm leading-6" aria-hidden="true"><code></code></pre>
                <textarea id="codeEditor" class="code-game-editor w-full p-4 font-mono text-sm leading-6"
                          data-no-paste="true" data-paste-source="code_lab"
                          data-paste-context="<?= htmlspecialchars($selectedId, ENT_QUOTES) ?>"
                          data-paste-csrf="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
                          spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off"
                          wrap="off" aria-describedby="editorHelp"><?= htmlspecialchars($problem['starter_code']) ?></textarea>
            </div>
            <p id="editorHelp" class="mt-2 text-xs text-slate-400">C++17 · Read input from <code>cin</code> and print the answer with <code>cout</code>. Ctrl + Enter to run.</p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button id="runCodeButton" type="button" class="code-game-run-button rounded-lg border px-5 py-3 font-black text-white">
                    <i class="fa-solid fa-play mr-2" aria-hidden="true"></i>Compile &amp; Run
                </button>
                <a id="nextCodeMission" class="hidden rounded-lg border px-4 py-3 text-sm font-bold text-white"></a>
                <span id="runStatus" class="text-sm text-slate-400" role="status" aria-live="polite"></span>
            </div>
            <section class="code-game-console mt-4 overflow-hidden rounded-xl border" aria-labelledby="console-title">
                <div class="code-game-console-head flex items-center justify-between px-4 py-2.5">
                    <h3 id="console-title" class="text-xs font-black uppercase tracking-wider text-slate-300">Compiler output</h3>
                    <span class="font-mono text-[.65rem] text-slate-500">sandbox · C++17</span>
                </div>
                <pre id="compilerOutput" class="min-h-24 whitespace-pre-wrap break-words p-4 font-mono text-sm text-slate-300" aria-live="polite">Your compiler output and test results will appear here.</pre>
            </section>
        </section>
    </div>
</main>

<script id="codeGameConfig" type="application/json"><?= json_encode([
    'problemId' => $selectedId,
    'csrfToken' => csrfToken(),
    'starterCode' => $problem['starter_code'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script>
(() => {
    const config = JSON.parse(document.getElementById('codeGameConfig').textContent);
    const editor = document.getElementById('codeEditor');
    const highlightedCode = document.querySelector('#codeHighlight code');
    const highlightedPre = document.getElementById('codeHighlight');
    const output = document.getElementById('compilerOutput');
    const status = document.getElementById('runStatus');
    const runButton = document.getElementById('runCodeButton');
    const storageKey = `cppq_code_${config.problemId}`;
    const tokenPattern = /(^[ \t]*#\s*[A-Za-z_]\w*|\b(?:alignas|alignof|and|asm|auto|bitand|bitor|bool|break|case|catch|char|class|compl|concept|const|consteval|constexpr|constinit|continue|co_await|co_return|co_yield|decltype|default|delete|do|double|else|enum|explicit|export|extern|false|float|for|friend|goto|if|inline|int|long|mutable|namespace|new|noexcept|not|nullptr|operator|or|override|private|protected|public|register|reinterpret_cast|requires|return|short|signed|sizeof|static|static_assert|struct|switch|template|this|thread_local|throw|true|try|typedef|typeid|typename|union|unsigned|using|virtual|void|volatile|while|xor)\b|\b(?:string|vector|array|map|unordered_map|set|unordered_set|pair|tuple|size_t|int[0-9]+_t|uint[0-9]+_t|std::\w+)\b|\/\/[^\n]*|\/\*[\s\S]*?\*\/|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?[uUlLfF]*\b|[A-Za-z_]\w*(?=\s*\()|(?:<<|>>|->|::|==|!=|<=|>=|&&|\|\||\+\+|--|\+=|-=|\*=|\/=|%=|[+\-*\/%=&|!<>^~?:.,()\[\]{}]))/gm;

    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[character]);
    }

    function highlightCpp(source) {
        let highlighted = '';
        let previousIndex = 0;
        tokenPattern.lastIndex = 0;
        for (const match of source.matchAll(tokenPattern)) {
            const token = match[0];
            const index = match.index;
            highlighted += escapeHtml(source.slice(previousIndex, index));
            let tokenClass = '';
            if (token.startsWith('//') || token.startsWith('/*')) tokenClass = 'cpp-comment';
            else if (token.startsWith('"') || token.startsWith("'")) tokenClass = 'cpp-string';
            else if (token.startsWith('#')) tokenClass = 'cpp-preprocessor';
            else if (/^(?:alignas|alignof|and|asm|auto|bitand|bitor|bool|break|case|catch|char|class|compl|concept|const|consteval|constexpr|constinit|continue|co_await|co_return|co_yield|decltype|default|delete|do|double|else|enum|explicit|export|extern|false|float|for|friend|goto|if|inline|int|long|mutable|namespace|new|noexcept|not|nullptr|operator|or|override|private|protected|public|register|reinterpret_cast|requires|return|short|signed|sizeof|static|static_assert|struct|switch|template|this|thread_local|throw|true|try|typedef|typeid|typename|union|unsigned|using|virtual|void|volatile|while|xor)$/.test(token)) tokenClass = 'cpp-keyword';
            else if (/^(?:string|vector|array|map|unordered_map|set|unordered_set|pair|tuple|size_t|int[0-9]+_t|uint[0-9]+_t|std::\w+)$/.test(token)) tokenClass = 'cpp-type';
            else if (/^\d/.test(token)) tokenClass = 'cpp-number';
            else if (/^[A-Za-z_]/.test(token)) tokenClass = 'cpp-function';
            else tokenClass = 'cpp-operator';
            highlighted += `<span class="${tokenClass}">${escapeHtml(token)}</span>`;
            previousIndex = index + token.length;
        }
        highlighted += escapeHtml(source.slice(previousIndex));
        if (source.endsWith('\n')) highlighted += ' ';
        highlightedCode.innerHTML = highlighted;
    }

    function syncHighlightScroll() {
        highlightedPre.style.transform = `translate(${-editor.scrollLeft}px, ${-editor.scrollTop}px)`;
    }

    try {
        const savedCode = localStorage.getItem(storageKey);
        if (savedCode !== null) editor.value = savedCode;
    } catch {
        status.textContent = 'Local draft storage is unavailable; your code will stay in this page.';
    }
    highlightCpp(editor.value);
    syncHighlightScroll();

    editor.addEventListener('input', () => {
        highlightCpp(editor.value);
        try {
            localStorage.setItem(storageKey, editor.value);
        } catch {
            status.textContent = 'Could not save your draft in this browser.';
        }
    });
    editor.addEventListener('scroll', syncHighlightScroll);

    editor.addEventListener('keydown', event => {
        if (event.key === 'Tab') {
            event.preventDefault();
            editor.setRangeText('    ', editor.selectionStart, editor.selectionEnd, 'end');
            editor.dispatchEvent(new Event('input'));
        }
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            runButton.click();
        }
    });

    runButton.addEventListener('click', async () => {
        runButton.disabled = true;
        status.textContent = 'Compiling and running in the isolated C++ sandbox...';
        output.textContent = 'Starting compiler...';

        try {
            const response = await fetch('/cpp_learning/code_run.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    problem_id: config.problemId,
                    source: editor.value,
                    csrf_token: config.csrfToken
                })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The code runner could not process this submission.');

            if (result.kind && result.kind !== 'results') {
                output.textContent = result.error || 'The program could not be run.';
                status.textContent = 'Fix the issue and try again.';
                return;
            }

            const lines = result.results.map(test => test.passed
                ? `Test ${test.test}: passed`
                : `Test ${test.test}: failed\nYour output: ${test.actual.trim() || '(no output)'}`);
            output.textContent = lines.join('\n\n');

            if (result.passed) {
                const reward = result.xp_earned > 0 ? ` +${result.xp_earned} XP earned.` : ' Mission already cleared.';
                status.textContent = `All ${result.total_tests} tests passed!${reward}`;
                if (result.xp_earned > 0) {
                    const xpDisplay = document.getElementById('navXpTotal');
                    const levelDisplay = document.getElementById('navLevelLabel');
                    const levelProgress = document.getElementById('navLevelProgress');
                    if (xpDisplay) xpDisplay.textContent = `⚡ ${result.new_xp} XP`;
                    if (levelDisplay) levelDisplay.textContent = `Level ${result.new_level}`;
                    if (levelProgress) {
                        const levelStart = (result.new_level - 1) * 100;
                        levelProgress.style.width = `${Math.max(0, Math.min(100, result.new_xp - levelStart))}%`;
                    }
                }
                if (result.next_problem_id) {
                    const nextMission = document.getElementById('nextCodeMission');
                    nextMission.href = `/cpp_learning/code_game.php?problem=${encodeURIComponent(result.next_problem_id)}`;
                    nextMission.textContent = `Next mission: ${result.next_problem_title} →`;
                    nextMission.classList.remove('hidden');
                    status.textContent += ' Next mission unlocked.';
                }
            } else {
                status.textContent = `${result.passed_count} of ${result.total_tests} tests passed. Keep debugging!`;
            }
        } catch (error) {
            output.textContent = error.message;
            status.textContent = 'Run failed.';
        } finally {
            runButton.disabled = false;
        }
    });
})();
</script>
</body>
</html>
