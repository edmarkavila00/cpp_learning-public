<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/adventure_game.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();

$challengeId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->execute([$challengeId ?: 0]);
$challenge = $stmt->fetch();
if (!$challenge) {
    header('Location: /cpp_learning/adventure.php');
    exit;
}
if (!isAdventurePortalUnlocked($pdo, (int)$user['id'], $challenge)) {
    header('Location: /cpp_learning/adventure.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT round_number, score, cleared
    FROM adventure_progress
    WHERE user_id = ? AND challenge_id = ?
");
$stmt->execute([$user['id'], $challengeId]);
$progress = $stmt->fetch() ?: ['round_number' => 0, 'score' => 0, 'cleared' => 0];
$roundNumber = min(2, (int)$progress['round_number']);
$question = (bool)$progress['cleared'] ? null : publicAdventureQuestion($challenge, $roundNumber);
$questionJson = json_encode($question, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$regionConfig = [
    'beginner' => ['region' => 'Syntax Grove', 'theme' => 'grove'],
    'intermediate' => ['region' => 'Looping Dunes', 'theme' => 'dunes'],
    'advanced' => ['region' => 'Pointer Peaks', 'theme' => 'peaks'],
];
$portalRegion = $regionConfig[$challenge['difficulty']] ?? $regionConfig['beginner'];
$pageTitle = 'Portal Trial';
include __DIR__ . '/includes/header.php';
?>

<style>
    .portal-stage {
        --portal-glow: 52 211 153;
        --portal-accent: #6ee7b7;
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background:
            radial-gradient(ellipse at 18% 55%, rgb(var(--portal-glow) / .16), transparent 40%),
            radial-gradient(ellipse at 86% 12%, rgb(99 102 241 / .16), transparent 35%),
            linear-gradient(135deg, #0b1224 5%, #101a31 55%, #101326);
    }
    .portal-stage.theme-dunes { --portal-glow: 251 191 36; --portal-accent: #fbbf24; }
    .portal-stage.theme-peaks { --portal-glow: 56 189 248; --portal-accent: #7dd3fc; }
    .portal-stars {
        position: absolute;
        inset: 0;
        z-index: -1;
        opacity: .48;
        background-image:
            radial-gradient(2px 2px at 22px 32px, #fff 98%, transparent),
            radial-gradient(1px 1px at 90px 70px, var(--portal-accent) 98%, transparent),
            radial-gradient(2px 2px at 145px 24px, #c4b5fd 98%, transparent);
        background-size: 180px 120px;
        mask-image: linear-gradient(90deg, #000, transparent 90%);
        animation: portal-star-drift 24s linear infinite;
    }
    .portal-art {
        position: relative;
        display: grid;
        min-height: 220px;
        place-items: center;
        overflow: hidden;
    }
    .portal-floor {
        position: absolute;
        bottom: 12%;
        width: min(90%, 340px);
        height: 34px;
        border: 1px solid rgb(var(--portal-glow) / .24);
        border-radius: 50%;
        background: radial-gradient(ellipse, rgb(var(--portal-glow) / .22), transparent 68%);
        box-shadow: 0 0 38px rgb(var(--portal-glow) / .19);
    }
    .portal-ring {
        position: absolute;
        width: 144px;
        height: 178px;
        border: 2px solid rgb(var(--portal-glow) / .85);
        border-radius: 50%;
        background: radial-gradient(ellipse, rgb(var(--portal-glow) / .22), rgb(15 23 42 / .72) 58%, transparent 72%);
        box-shadow: 0 0 18px rgb(var(--portal-glow) / .52), inset 0 0 22px rgb(var(--portal-glow) / .24);
        transform: perspective(420px) rotateY(-12deg);
        animation: portal-breathe 2.8s ease-in-out infinite;
    }
    .portal-ring::before,
    .portal-ring::after {
        position: absolute;
        content: "";
        inset: 10px -11px;
        border: 1px solid rgb(var(--portal-glow) / .5);
        border-radius: 50%;
        transform: rotate(28deg);
    }
    .portal-ring::after {
        inset: 20px -20px;
        border-style: dashed;
        opacity: .75;
        transform: rotate(-35deg);
        animation: portal-spin 18s linear infinite;
    }
    .portal-core {
        position: absolute;
        display: grid;
        width: 94px;
        height: 130px;
        place-items: center;
        border-radius: 50%;
        color: var(--portal-accent);
        background: radial-gradient(ellipse, rgb(var(--portal-glow) / .2), rgb(8 11 27 / .9) 68%);
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 25px;
        font-weight: 900;
        text-shadow: 0 0 14px rgb(var(--portal-glow) / .95);
        animation: portal-flicker 3.2s ease-in-out infinite;
    }
    .portal-rune {
        position: absolute;
        z-index: 1;
        color: var(--portal-accent);
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 800;
        text-shadow: 0 0 12px rgb(var(--portal-glow) / .8);
        animation: rune-drift 3.5s ease-in-out infinite;
    }
    .portal-rune.rune-one { top: 16%; left: 18%; font-size: 20px; }
    .portal-rune.rune-two { top: 28%; right: 16%; font-size: 24px; animation-delay: -.8s; }
    .portal-rune.rune-three { bottom: 22%; left: 24%; font-size: 17px; animation-delay: -1.6s; }
    .portal-avatar {
        position: absolute;
        z-index: 2;
        bottom: 14%;
        left: calc(50% - 112px);
        filter: drop-shadow(0 0 12px rgb(var(--portal-glow) / .5));
        animation: avatar-hover 2s ease-in-out infinite;
    }
    .core-meter {
        height: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: rgb(51 65 85 / .78);
    }
    .core-meter-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--portal-accent), #a5f3fc);
        box-shadow: 0 0 12px rgb(var(--portal-glow) / .7);
        transition: width .45s ease;
    }
    .portal-shake { animation: portal-hit .38s ease; }
    .portal-surge { animation: portal-surge .7s ease; }
    @keyframes portal-star-drift { to { background-position: 180px 120px; } }
    @keyframes portal-spin { to { transform: rotate(325deg); } }
    @keyframes portal-breathe { 50% { filter: brightness(1.35); transform: perspective(420px) rotateY(-12deg) scale(1.045); } }
    @keyframes portal-flicker { 50% { opacity: .68; filter: blur(1px); } }
    @keyframes rune-drift { 50% { transform: translateY(-9px) rotate(8deg); } }
    @keyframes avatar-hover { 50% { transform: translateY(-5px); } }
    @keyframes portal-hit { 25% { transform: translateX(-6px); } 50% { transform: translateX(6px); } 75% { transform: translateX(-3px); } }
    @keyframes portal-surge { 50% { box-shadow: 0 0 70px rgb(var(--portal-glow) / .48); } }
    @media (prefers-reduced-motion: reduce) {
        .portal-stars, .portal-ring, .portal-ring::after, .portal-core, .portal-rune, .portal-avatar { animation: none !important; }
    }
</style>

<main class="mx-auto max-w-3xl px-4 py-8">
    <a href="/cpp_learning/adventure.php" class="text-sm text-slate-400 transition hover:text-cyan-300">← Escape to the frontier</a>

    <section class="game-panel mt-4 overflow-hidden rounded-2xl border border-cyan-500/30 shadow-2xl">
        <div class="portal-stage theme-<?= htmlspecialchars($portalRegion['theme']) ?> grid md:grid-cols-[.8fr_1.2fr]">
            <div class="portal-art" aria-hidden="true">
                <div class="portal-stars"></div>
                <div class="portal-floor"></div>
                <div class="portal-ring"></div>
                <div class="portal-core">&lt;/&gt;</div>
                <span class="portal-rune rune-one">{ }</span>
                <span class="portal-rune rune-two">0x01</span>
                <span class="portal-rune rune-three">++</span>
                <div class="portal-avatar"><?= pixelAvatar((int)$user['id'], 48, (int)$user['avatar_id']) ?></div>
            </div>
            <div class="relative flex flex-col justify-center p-5 sm:p-7">
                <div class="portal-stars" aria-hidden="true"></div>
                <div class="relative">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[.2em] text-cyan-200">
                        <span class="rounded-full border border-white/10 bg-slate-950/40 px-3 py-1"><?= htmlspecialchars($portalRegion['region']) ?></span>
                        <span><?= htmlspecialchars(ucfirst($challenge['difficulty'])) ?> portal</span>
                    </div>
                    <h1 class="mt-3 text-2xl font-black text-white sm:text-3xl"><?= htmlspecialchars($challenge['title']) ?></h1>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300">
                        The portal core is unstable. Repair three C++ fragments to restore its power and earn <strong class="text-cyan-200">35 XP</strong>.
                    </p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-white/10 bg-slate-950/45 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Core integrity</div>
                            <div id="integrityLabel" class="mt-1 text-sm font-bold text-white"><?= (int)$progress['round_number'] ?> / 3 fragments</div>
                            <div class="core-meter mt-2"><div id="integrityMeter" class="core-meter-fill" style="width:<?= (int)$progress['round_number'] * 33 ?>%"></div></div>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/45 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Portal reward</div>
                            <div class="mt-1 text-sm font-bold text-amber-200">⚡ 35 XP</div>
                            <div class="mt-1 text-xs text-slate-400">Clear all 3 repair rounds</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($progress['cleared']): ?>
            <div class="portal-clear p-6 text-center sm:p-10">
                <div class="text-5xl" aria-hidden="true">✨</div>
                <h2 class="mt-3 text-2xl font-black text-emerald-300">Portal already cleared</h2>
                <p class="mt-2 text-slate-300">You repaired this portal's code and earned its XP reward.</p>
                <a href="/cpp_learning/adventure.php" class="mt-6 inline-flex rounded-lg bg-cyan-700 px-5 py-3 font-bold text-white transition hover:bg-cyan-600">Back to the frontier</a>
            </div>
        <?php else: ?>
            <div class="p-5 sm:p-7">
                <div class="mb-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[.2em] text-amber-300">Compiler repair mini-game</div>
                            <div id="roundLabel" class="mt-1 text-sm font-semibold text-slate-300">Fragment <?= $roundNumber + 1 ?> of 3</div>
                        </div>
                        <div id="roundBadge" class="rounded-lg border border-cyan-500/20 bg-cyan-950/40 px-3 py-2 text-xs font-bold text-cyan-200">ROUND <?= $roundNumber + 1 ?></div>
                    </div>
                    <div class="flex gap-2" aria-label="Portal repair progress">
                        <?php for ($step = 0; $step < 3; $step++): ?>
                            <span id="step-<?= $step ?>" class="h-2 flex-1 rounded-full <?= $step < $roundNumber ? 'bg-emerald-400' : 'bg-slate-700' ?>"></span>
                        <?php endfor; ?>
                    </div>
                </div>

                <div id="gameCard" class="rounded-2xl border border-slate-700 bg-slate-950/70 p-5 shadow-inner sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div id="gameTask" class="text-xs font-bold uppercase tracking-wider text-amber-300"></div>
                            <h2 id="gamePrompt" class="mt-2 text-lg font-semibold leading-relaxed text-white"></h2>
                        </div>
                        <div class="hidden shrink-0 rounded-lg border border-cyan-500/20 bg-cyan-950/40 px-3 py-2 text-xs font-bold text-cyan-200 sm:block">35 XP</div>
                    </div>
                    <div class="mt-5 overflow-hidden rounded-xl border border-slate-700 bg-slate-900">
                        <div class="flex items-center justify-between border-b border-slate-700 bg-slate-800/80 px-4 py-2">
                            <span class="flex gap-1.5" aria-hidden="true"><i class="h-2.5 w-2.5 rounded-full bg-red-400"></i><i class="h-2.5 w-2.5 rounded-full bg-amber-300"></i><i class="h-2.5 w-2.5 rounded-full bg-emerald-400"></i></span>
                            <span class="font-mono text-[10px] uppercase tracking-wider text-slate-500">portal_core.cpp</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-300">Repair</span>
                        </div>
                        <pre class="overflow-x-auto p-4 text-sm leading-relaxed text-cyan-100"><code id="gameCode" class="language-cpp"></code></pre>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <span>Select the missing token</span><span>Keys <kbd class="rounded border border-slate-700 px-1">1–4</kbd> · <kbd class="rounded border border-slate-700 px-1">Enter</kbd></span>
                    </div>
                    <div id="choiceList" class="mt-5 grid gap-2 sm:grid-cols-2" role="group" aria-label="Choose a C++ token"></div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button id="portalHintButton" type="button" class="game-button rounded-lg border border-emerald-500/30 bg-emerald-950/40 px-4 py-2 text-sm font-bold text-emerald-100 transition hover:bg-emerald-900/60">Buy hint · 20 XP</button>
                        <button id="portalAnswerButton" type="button" class="game-button rounded-lg border border-emerald-500/30 bg-emerald-950/40 px-4 py-2 text-sm font-bold text-emerald-100 transition hover:bg-emerald-900/60">Reveal answer · 50 XP</button>
                    </div>
                    <div id="portalHelpText" class="mt-3 hidden rounded-lg border border-emerald-700/50 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100" role="status" aria-live="polite"></div>
                    <div id="gameFeedback" class="mt-4 hidden rounded-lg border px-4 py-3 text-sm" role="status" aria-live="polite"></div>
                    <button id="submitAnswer" type="button" disabled class="game-button mt-5 w-full rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">Install token →</button>
                </div>

                <div id="completeCard" class="hidden rounded-2xl border border-emerald-500/40 bg-emerald-950/20 p-6 text-center sm:p-8">
                    <div class="text-5xl" aria-hidden="true">🌀</div>
                    <h2 class="mt-3 text-2xl font-black text-emerald-300">Portal repaired!</h2>
                    <p id="completeMessage" class="mt-2 text-slate-200"></p>
                    <p id="levelMessage" class="mt-2 hidden font-bold text-purple-300"></p>
                    <a href="/cpp_learning/adventure.php" class="mt-6 inline-flex rounded-lg bg-cyan-700 px-5 py-3 font-bold text-white transition hover:bg-cyan-600">Continue exploring →</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if (!$progress['cleared']): ?>
<script>
const challengeId = <?= (int)$challengeId ?>;
const csrfToken = <?= json_encode(csrfToken()) ?>;
let roundNumber = <?= (int)$roundNumber ?>;
let currentQuestion = <?= $questionJson ?: 'null' ?>;
let selectedAnswer = null;
const choiceList = document.getElementById('choiceList');
const feedback = document.getElementById('gameFeedback');
const submitButton = document.getElementById('submitAnswer');
const gameCard = document.getElementById('gameCard');
const portalStage = document.querySelector('.portal-stage');
const hintButton = document.getElementById('portalHintButton');
const revealButton = document.getElementById('portalAnswerButton');
const helpText = document.getElementById('portalHelpText');

function renderQuestion(question) {
    currentQuestion = question;
    selectedAnswer = null;
    document.getElementById('gameTask').textContent = question.task;
    document.getElementById('gamePrompt').textContent = question.prompt;
    document.getElementById('gameCode').textContent = question.code;
    document.getElementById('roundLabel').textContent = `Fragment ${roundNumber + 1} of 3`;
    document.getElementById('roundBadge').textContent = `ROUND ${roundNumber + 1}`;
    choiceList.replaceChildren();
    helpText.textContent = '';
    helpText.classList.add('hidden');
    helpText.classList.remove('border-red-700', 'bg-red-950/40', 'text-red-200');
    hintButton.disabled = false;
    hintButton.textContent = 'Buy hint · 20 XP';
    revealButton.disabled = false;
    revealButton.textContent = 'Reveal answer · 50 XP';

    question.choices.forEach((choice, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'flex min-h-12 items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-left font-mono text-sm text-cyan-100 transition hover:-translate-y-0.5 hover:border-cyan-400 hover:bg-cyan-950/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300';
        const keyBadge = document.createElement('span');
        keyBadge.textContent = String(index + 1);
        keyBadge.className = 'grid h-6 w-6 shrink-0 place-items-center rounded-md border border-slate-600 bg-slate-800 text-[11px] font-bold text-slate-400';
        const token = document.createElement('span');
        token.textContent = choice;
        button.append(keyBadge, token);
        button.addEventListener('click', () => {
            selectedAnswer = choice;
            choiceList.querySelectorAll('button').forEach(item => {
                item.classList.remove('border-cyan-400', 'bg-cyan-950/60', 'ring-1', 'ring-cyan-300/50');
                item.setAttribute('aria-pressed', 'false');
            });
            button.classList.add('border-cyan-400', 'bg-cyan-950/60', 'ring-1', 'ring-cyan-300/50');
            button.setAttribute('aria-pressed', 'true');
            submitButton.disabled = false;
        });
        button.setAttribute('aria-pressed', 'false');
        choiceList.appendChild(button);
    });

    feedback.classList.add('hidden');
    submitButton.disabled = true;
    if (window.Prism) Prism.highlightElement(document.getElementById('gameCode'));
}

async function purchasePortalUnlock(item, button) {
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = 'Unlocking...';
    try {
        const response = await fetch('/cpp_learning/xp_purchase.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                game: 'adventure',
                item,
                challenge_id: challengeId,
                round_number: roundNumber,
                csrf_token: csrfToken
            })
        });
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Could not spend XP.');
        if (typeof window.cppqUpdatePlayerXp === 'function') {
            window.cppqUpdatePlayerXp(result.new_xp, result.new_level);
        }

        helpText.textContent = item === 'hint'
            ? `Hint: ${result.content}`
            : `Answer: ${result.content}`;
        helpText.classList.remove('hidden');

        if (item === 'answer') {
            const answerButton = [...choiceList.querySelectorAll('button')]
                .find(button => button.lastElementChild.textContent === result.content);
            if (answerButton) answerButton.click();
            button.textContent = 'Answer unlocked';
        } else {
            button.textContent = 'Hint unlocked';
        }
        if (result.already_owned) {
            helpText.textContent += ' (Already unlocked; no XP charged again.)';
        }
    } catch (error) {
        button.textContent = originalText;
        button.disabled = false;
        helpText.textContent = error.message || 'Could not unlock this item.';
        helpText.classList.remove('hidden');
        helpText.classList.add('border-red-700', 'bg-red-950/40', 'text-red-200');
    }
}

hintButton.addEventListener('click', () => purchasePortalUnlock('hint', hintButton));
revealButton.addEventListener('click', () => purchasePortalUnlock('answer', revealButton));

submitButton.addEventListener('click', async () => {
    if (selectedAnswer === null) return;
    submitButton.disabled = true;
    submitButton.textContent = 'Checking code...';

    try {
        const response = await fetch('/cpp_learning/adventure_run.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                challenge_id: challengeId,
                round_number: roundNumber,
                answer: selectedAnswer,
                csrf_token: csrfToken
            })
        });
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Could not check the code.');
        if (typeof window.cppqUpdatePlayerXp === 'function' && Number.isInteger(result.new_xp)) {
            window.cppqUpdatePlayerXp(result.new_xp, result.new_level);
        }

        feedback.textContent = result.correct
            ? result.feedback
            : result.feedback;
        feedback.className = `mt-4 rounded-lg border px-4 py-3 text-sm ${result.correct ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200' : 'border-amber-700 bg-amber-950/40 text-amber-200'}`;
        feedback.classList.remove('hidden');

        if (!result.correct) {
            gameCard.classList.remove('portal-shake');
            void gameCard.offsetWidth;
            gameCard.classList.add('portal-shake');
            submitButton.textContent = 'Try another token';
            submitButton.disabled = false;
            return;
        }

        portalStage.classList.remove('portal-surge');
        void portalStage.offsetWidth;
        portalStage.classList.add('portal-surge');
        document.getElementById(`step-${roundNumber}`).classList.replace('bg-slate-700', 'bg-emerald-400');
        const completedFragments = roundNumber + 1;
        document.getElementById('integrityLabel').textContent = `${completedFragments} / 3 fragments`;
        document.getElementById('integrityMeter').style.width = `${completedFragments * 33.333}%`;
        if (result.finished) {
            document.getElementById('gameCard').classList.add('hidden');
            document.getElementById('completeCard').classList.remove('hidden');
            document.getElementById('completeMessage').textContent = `You repaired all three fragments and earned ${result.xp_earned} XP.`;
            if (result.level_up) {
                const levelMessage = document.getElementById('levelMessage');
                levelMessage.textContent = `Level up! You are now level ${result.new_level}.`;
                levelMessage.classList.remove('hidden');
            }
            return;
        }

        roundNumber = result.round_number;
        submitButton.textContent = 'Install token →';
        submitButton.disabled = true;
        window.setTimeout(() => renderQuestion(result.next_question), 450);
    } catch (error) {
        feedback.textContent = error.message || 'Could not submit your answer. Please try again.';
        feedback.className = 'mt-4 rounded-lg border border-red-700 bg-red-950/40 px-4 py-3 text-sm text-red-200';
        feedback.classList.remove('hidden');
        submitButton.textContent = 'Try again';
        submitButton.disabled = selectedAnswer === null;
    }
});

document.addEventListener('keydown', event => {
    if (event.altKey || event.ctrlKey || event.metaKey || event.target.matches('input, textarea, select')) return;
    if (/^[1-4]$/.test(event.key)) {
        choiceList.querySelectorAll('button')[Number(event.key) - 1]?.click();
    } else if (event.key === 'Enter' && !submitButton.disabled) {
        submitButton.click();
    }
});

renderQuestion(currentQuestion);
</script>
<?php endif; ?>
</body>
</html>
