<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /cpp_learning/dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->execute([$id]);
$challenge = $stmt->fetch();
if (!$challenge) { header('Location: /cpp_learning/dashboard.php'); exit; }
if (!isChallengeUnlocked($pdo, (int)$user['id'], $challenge)
    && !isAssignedChallenge($pdo, (int)$user['id'], $id)) {
    header('Location: /cpp_learning/dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM user_progress WHERE user_id = ? AND challenge_id = ?");
$stmt->execute([$user['id'], $id]);
$progress = $stmt->fetch();

$rawHint = $challenge['hint'] ?? '';
$hints   = [];
if ($rawHint) {
    $decoded = json_decode($rawHint, true);
    $hints   = is_array($decoded) ? $decoded : [$rawHint];
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM user_xp_purchases
    WHERE user_id = ? AND item_type = 'challenge_hint' AND context_id = ?
");
$stmt->execute([$user['id'], $id]);
$ownedHintCount = (int)$stmt->fetchColumn();

$combo      = $_SESSION['combo'] ?? 0;
$multiplier = match(true) { $combo >= 5 => 2.0, $combo >= 3 => 1.5, default => 1.0 };

$difficultyOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
$challengeDifficultyOrder = $difficultyOrder[$challenge['difficulty']] ?? 99;
$stmt = $pdo->prepare("
    SELECT id
    FROM challenges
    WHERE
        CASE difficulty
            WHEN 'beginner' THEN 1
            WHEN 'intermediate' THEN 2
            WHEN 'advanced' THEN 3
            ELSE 99
        END > ?
        OR (
            CASE difficulty
                WHEN 'beginner' THEN 1
                WHEN 'intermediate' THEN 2
                WHEN 'advanced' THEN 3
                ELSE 99
            END = ?
            AND (order_num > ? OR (order_num = ? AND id > ?))
        )
    ORDER BY FIELD(difficulty, 'beginner', 'intermediate', 'advanced'), order_num, id
    LIMIT 1
");
$stmt->execute([
    $challengeDifficultyOrder,
    $challengeDifficultyOrder,
    $challenge['order_num'],
    $challenge['order_num'],
    $id,
]);
$nextChallenge = $stmt->fetch();

$nextId = $nextChallenge ? (int)$nextChallenge['id'] : 0;

$pageTitle = $challenge['title'];
include __DIR__ . '/includes/header.php';
?>

<style>
/* ── Animations ─────────────────────────────────────── */
@keyframes floatUp {
    0%   { opacity:1; transform:translateY(0) scale(1); }
    100% { opacity:0; transform:translateY(-70px) scale(1.5); }
}
.xp-float { animation: floatUp 1.3s ease-out forwards; }

@keyframes shake {
    0%,100%{ transform:translateX(0); }
    20%    { transform:translateX(-7px); }
    40%    { transform:translateX(7px); }
    60%    { transform:translateX(-4px); }
    80%    { transform:translateX(4px); }
}
.shake { animation: shake 0.4s ease; }

@keyframes popIn {
    0%   { opacity:0; transform:scale(0.4) translateY(30px); }
    65%  { transform:scale(1.08) translateY(-4px); }
    100% { opacity:1; transform:scale(1) translateY(0); }
}
@keyframes popOut {
    0%   { opacity:1; transform:scale(1); }
    100% { opacity:0; transform:scale(0.8) translateY(-20px); }
}
.pop-in  { animation: popIn  0.35s cubic-bezier(.34,1.56,.64,1) forwards; }
.pop-out { animation: popOut 0.25s ease-in forwards; }

@keyframes pulseGlow {
    0%,100% { box-shadow: 0 0 0px rgba(251,146,60,0); }
    50%     { box-shadow: 0 0 24px rgba(251,146,60,0.5); }
}
.combo-glow { animation: pulseGlow 1.2s ease-in-out infinite; }

@keyframes slideUp {
    from { opacity:0; transform:translateX(-50%) translateY(20px); }
    to   { opacity:1; transform:translateX(-50%) translateY(0); }
}
@keyframes slideDown {
    from { opacity:1; transform:translateX(-50%) translateY(0); }
    to   { opacity:0; transform:translateX(-50%) translateY(20px); }
}
</style>

<main class="max-w-5xl mx-auto px-4 py-8">

    <!-- Top bar: back + mute -->
    <div class="flex items-center justify-between mb-6">
        <a href="/cpp_learning/challenges.php" class="text-slate-400 hover:text-indigo-400 text-sm inline-flex items-center gap-1 transition">
            ← Back to Challenges
        </a>
        <button id="muteBtn" onclick="toggleMute()" title="Toggle sounds"
            class="text-slate-500 hover:text-slate-300 transition text-lg px-2">
            🔊
        </button>
    </div>

    <!-- Persistent combo bar -->
    <div id="comboBar" class="mb-5 <?= $combo <= 0 ? 'hidden' : '' ?>">
        <div id="comboInner" class="rounded-xl p-3 flex items-center justify-between border
            <?= $combo >= 5 ? 'border-red-500/70 bg-red-900/25 combo-glow' : ($combo >= 3 ? 'border-orange-500/60 bg-orange-900/20' : 'border-yellow-700/40 bg-yellow-900/10') ?>">
            <div class="flex items-center gap-3">
                <div id="comboFlame" class="text-2xl leading-none select-none"><?= str_repeat('🔥', min($combo, 5)) ?></div>
                <div>
                    <div class="font-bold <?= $combo >= 5 ? 'text-red-300' : ($combo >= 3 ? 'text-orange-300' : 'text-yellow-400') ?>" id="comboCount">
                        <?= $combo ?>× Combo!
                    </div>
                    <div class="text-xs text-slate-400" id="comboSub">
                        <?= $multiplier > 1.0 ? number_format($multiplier,1).'× XP active — keep it going!' : 'Get to 3 in a row for 1.5× XP!' ?>
                    </div>
                </div>
            </div>
            <div id="comboMultDisplay" class="text-right">
                <?php if ($multiplier > 1.0): ?>
                    <div class="text-2xl font-black text-yellow-400"><?= number_format($multiplier,1) ?>×</div>
                    <div class="text-xs text-slate-500">XP multiplier</div>
                <?php else: ?>
                    <div class="text-slate-500 text-sm">1× XP</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Challenge header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
            <h1 class="text-2xl font-bold"><?= htmlspecialchars($challenge['title']) ?></h1>
            <?php if ($progress && $progress['solved']): ?>
                <span class="bg-green-600 text-xs px-2 py-1 rounded-full">✓ Solved</span>
            <?php endif; ?>
        </div>
        <div class="flex gap-3 text-sm text-slate-400 flex-wrap">
            <span>Category: <span class="text-indigo-300"><?= ucfirst($challenge['category']) ?></span></span>
            <span>•</span>
            <span>Difficulty: <span class="text-indigo-300"><?= ucfirst($challenge['difficulty']) ?></span></span>
            <span>•</span>
            <?php if (!$progress || !$progress['solved']): ?>
            <span>Reward: <span class="text-yellow-400" id="xpLabel">
                +<?= $challenge['xp_reward'] ?> XP<?= $multiplier > 1.0 ? ' × '.number_format($multiplier,1) : '' ?>
                <?php if ($ownedHintCount >= 2): ?>
                    <span class="text-slate-500 text-xs">(no bonus)</span>
                <?php elseif ($ownedHintCount === 1): ?>
                    <span class="text-yellow-500 text-xs">+3 bonus</span>
                <?php else: ?>
                    <span class="text-green-400 text-xs">+5 bonus</span>
                <?php endif; ?>
            </span></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two-column content area -->
    <div class="lg:flex lg:gap-6 lg:items-start">

        <!-- Left: code + answer -->
        <div class="lg:flex-1 min-w-0">

            <!-- Code display -->
            <div class="bg-slate-900 rounded-xl border border-slate-700 mb-6 overflow-hidden" id="codePanel">
                <div class="flex items-center justify-between px-4 py-2 bg-slate-800 border-b border-slate-700">
                    <div class="flex gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                    </div>
                    <span class="text-xs text-slate-400">main.cpp</span>
                </div>
                <pre class="p-5 text-sm overflow-x-auto leading-relaxed"><code class="language-cpp"><?= htmlspecialchars($challenge['code']) ?></code></pre>
            </div>

            <!-- Answer area -->
            <div class="bg-slate-800 rounded-xl border border-slate-700 p-6">
                <h2 class="text-lg font-semibold mb-1">What is the output of this program?</h2>
                <p class="text-slate-400 text-sm mb-4">Type exactly what you think will be printed to the console.</p>

                <div id="answerSection">
                    <label class="sr-only" for="userAnswer">Your predicted program output</label>
                    <textarea id="userAnswer" rows="3"
                        data-no-paste="true" data-paste-source="challenge"
                        data-paste-context="<?= (int)$id ?>" data-paste-csrf="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
                        placeholder="Type your predicted output here..."
                        class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-3 text-white font-mono text-sm focus:border-indigo-500 focus:outline-none transition resize-none"></textarea>

                    <div class="flex gap-3 mt-3 relative">
                        <button id="submitBtn" onclick="submitAnswer(this)"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-500 font-semibold py-2.5 rounded-lg transition">
                            Submit Answer
                        </button>
                        <div id="xpAnchor" class="absolute right-0 bottom-12 pointer-events-none"></div>
                    </div>
                </div>

                <div id="resultArea" class="hidden mt-4"></div>
            </div>

            <details class="mt-4 rounded-xl border border-slate-700 bg-slate-900/60 p-4">
                <summary class="cursor-pointer font-semibold text-slate-200">Report an issue with this challenge</summary>
                <form id="challengeReportForm" class="mt-3 space-y-3">
                    <label for="reportType" class="block text-sm text-slate-300">What needs attention?</label>
                    <select id="reportType" name="issue_type" required class="w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                        <option value="incorrect_answer">Expected answer may be incorrect</option>
                        <option value="unclear_wording">Instructions or wording are unclear</option>
                        <option value="broken_code">Code appears broken or misleading</option>
                        <option value="other">Other issue</option>
                    </select>
                    <label for="reportDetails" class="block text-sm text-slate-300">Details (optional)</label>
                    <textarea id="reportDetails" name="details" maxlength="1000" rows="3" class="w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-white"></textarea>
                    <button type="submit" class="game-button rounded-lg px-4 py-2 text-sm font-bold">Send report</button>
                    <p id="challengeReportStatus" class="text-sm text-slate-300" role="status" aria-live="polite"></p>
                </form>
            </details>

        </div>

        <!-- Right: hint sidebar -->
        <div class="lg:w-72 flex-shrink-0 mt-4 lg:mt-0">
            <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-lightbulb text-yellow-400 text-sm"></i>
                        <span class="text-sm font-semibold text-slate-300">Hints</span>
                    </div>
                    <button id="hintBtn" onclick="showNextHint()" <?= empty($hints) ? 'disabled' : '' ?>
                        class="bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium transition whitespace-nowrap">
                        <?= empty($hints) ? 'No hints' : '💡 Hint 1/'.count($hints).' · 10 XP' ?>
                    </button>
                </div>
                <div class="p-3">
                    <p class="mb-3 text-xs text-slate-400">Hints stay unlocked after you buy them.</p>
                    <div id="hintBox" class="hidden rounded-xl border border-yellow-700/60 bg-yellow-900/25 p-3" role="status" aria-live="polite">
                        <div id="hintLabel" class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-yellow-400"></div>
                        <p id="hintText" class="text-xs leading-relaxed text-yellow-100"></p>
                    </div>
                    <div id="answerRevealPanel" class="hidden mt-3 rounded-xl border border-emerald-700/60 bg-emerald-900/25 p-3" role="status" aria-live="polite">
                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-emerald-300">Answer revealed</div>
                        <p id="answerRevealText" class="whitespace-pre-wrap font-mono text-sm text-white"></p>
                    </div>
                    <button id="revealAnswerBtn" type="button" onclick="revealChallengeAnswer()"
                        class="mt-3 w-full rounded-lg border border-emerald-700/50 bg-emerald-950/40 px-3 py-2 text-xs font-bold text-emerald-100 transition hover:bg-emerald-900/60">
                        Show answer · 50 XP
                    </button>
                </div>
            </div>
        </div>

    </div>

</main>

<script>
const challengeId  = <?= $id ?>;
const totalHints   = <?= count($hints) ?>;
const currentCombo = <?= $combo ?>;
const nextId       = <?= $nextId ?>;
const nextUrl      = nextId ? `/cpp_learning/challenge.php?id=${nextId}` : '/cpp_learning/challenges.php';
const nextLabel    = nextId ? 'Next Challenge →' : '← Back to Dashboard';
const purchaseCsrf = <?= json_encode(csrfToken()) ?>;
const answerCsrf = purchaseCsrf;
const reportCsrf = purchaseCsrf;
const answerDraftKey = `cppq_answer_${challengeId}`;
let hintLevel  = 0;
let hintUsed   = <?= $ownedHintCount > 0 ? 'true' : 'false' ?>;
let hintsUsed  = <?= min(2, $ownedHintCount) ?>;
let muted      = localStorage.getItem('cppq_muted') === '1';
let answered   = <?= ($progress && $progress['solved']) ? 'true' : 'false' ?>; // true if already solved

// ── Mute ────────────────────────────────────────────────────────────────────
function toggleMute() {
    window.cppqSetEffectsMuted(!muted);
}
document.getElementById('muteBtn').textContent = muted ? '🔇' : '🔊';
window.cppqOnEffectsChanged = value => { muted = value; };

// ── Web Audio ────────────────────────────────────────────────────────────────
let _ac = null;
function ac() {
    if (!_ac) _ac = new (window.AudioContext || window.webkitAudioContext)();
    return _ac;
}
function tone(freq, dur, type = 'sine', vol = 0.28, delay = 0) {
    if (muted) return;
    const ctx = ac(), osc = ctx.createOscillator(), g = ctx.createGain();
    osc.connect(g); g.connect(ctx.destination);
    osc.type = type; osc.frequency.value = freq;
    const t = ctx.currentTime + delay;
    g.gain.setValueAtTime(vol, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + dur);
    osc.start(t); osc.stop(t + dur);
}
function playCorrect() {
    tone(523, 0.12, 'sine', 0.25);
    tone(659, 0.12, 'sine', 0.25, 0.10);
    tone(784, 0.25, 'sine', 0.25, 0.20);
}
function playWrong() {
    tone(220, 0.15, 'sawtooth', 0.18);
    tone(160, 0.25, 'sawtooth', 0.14, 0.15);
}
function playComboUp(combo) {
    const notes = [392, 494, 587, 698, 784];
    const n = Math.min(combo, notes.length);
    for (let i = 0; i < n; i++) tone(notes[i], 0.14, 'sine', 0.22, i * 0.07);
}
function playComboMax() {
    [523,659,784,1047,1319].forEach((f, i) => tone(f, 0.18, 'sine', 0.28, i * 0.08));
}
function playComboBreak() {
    tone(400, 0.12, 'square', 0.2);
    tone(280, 0.12, 'square', 0.15, 0.12);
    tone(180, 0.22, 'square', 0.12, 0.24);
}
function playHint() {
    tone(440, 0.18, 'sine', 0.18);
    tone(370, 0.28, 'sine', 0.12, 0.16);
}
function playHintWarning() {
    tone(330, 0.15, 'triangle', 0.2);
    tone(277, 0.25, 'triangle', 0.15, 0.15);
}
function playLevelUp() {
    [392,523,659,784,1047,1319].forEach((f, i) => tone(f, 0.2, 'sine', 0.3, i * 0.1));
}

// ── Toast ────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'info', duration = 2800) {
    const palette = {
        info:    'bg-slate-800    border-slate-600  text-slate-200',
        warning: 'bg-orange-950  border-orange-600 text-orange-200',
        error:   'bg-red-950     border-red-600    text-red-200',
        success: 'bg-green-950   border-green-600  text-green-200',
    };
    const el = document.createElement('div');
    el.className = `fixed bottom-6 left-1/2 border rounded-xl px-5 py-3 text-sm font-medium z-50 shadow-xl max-w-xs text-center ${palette[type]}`;
    el.style.cssText = 'transform:translateX(-50%) translateY(20px); opacity:0; transition:all 0.25s ease';
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => {
        el.style.transform = 'translateX(-50%) translateY(0)';
        el.style.opacity   = '1';
    });
    setTimeout(() => {
        el.style.transform = 'translateX(-50%) translateY(20px)';
        el.style.opacity   = '0';
        setTimeout(() => el.remove(), 260);
    }, duration);
}

// ── Big combo popup ───────────────────────────────────────────────────────────
function showComboPopup(combo, multiplier) {
    const isMax = combo >= 5;
    const isMid = combo >= 3;
    const flames = '🔥'.repeat(Math.min(combo, 5));

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 flex items-center justify-center pointer-events-none z-50';

    const box = document.createElement('div');
    box.className = 'text-center px-10 py-8 rounded-2xl border-2 shadow-2xl pop-in ' +
        (isMax ? 'bg-red-950/95 border-red-500' : isMid ? 'bg-orange-950/95 border-orange-500' : 'bg-yellow-950/95 border-yellow-600');

    box.innerHTML = `
        <div class="text-5xl mb-2 leading-none">${flames}</div>
        <div class="text-4xl font-black mt-1 ${isMax ? 'text-red-300' : isMid ? 'text-orange-300' : 'text-yellow-300'}">
            ${combo}× COMBO!
        </div>
        ${multiplier > 1 ? `<div class="text-2xl font-bold text-yellow-400 mt-1">${multiplier.toFixed(1)}× XP!</div>` : ''}
        ${isMax ? '<div class="text-sm text-red-400 mt-2 font-semibold">MAX MULTIPLIER REACHED</div>' : ''}
    `;

    overlay.appendChild(box);
    document.body.appendChild(overlay);

    // Pop out after 1.5s
    setTimeout(() => {
        box.classList.replace('pop-in', 'pop-out');
        setTimeout(() => overlay.remove(), 280);
    }, 1500);
}

// ── Floating XP label ────────────────────────────────────────────────────────
function spawnXpFloat(text) {
    const anchor = document.getElementById('xpAnchor');
    const el = document.createElement('div');
    el.textContent = text;
    el.className = 'xp-float absolute text-yellow-300 font-black text-2xl whitespace-nowrap drop-shadow-lg';
    el.style.cssText = 'right:0;bottom:0;';
    anchor.appendChild(el);
    setTimeout(() => el.remove(), 1400);
}

// ── Hint system ───────────────────────────────────────────────────────────────
function showNextHint() {
    if (hintLevel >= totalHints) return;

    const comboBar    = document.getElementById('comboBar');
    const comboActive = comboBar && !comboBar.classList.contains('hidden');

    // First hint + active combo + not yet answered → ask for confirmation first
    if (!hintUsed && comboActive && !answered) {
        showHintConfirm();
        return;
    }

    revealHint();
}

function showHintConfirm() {
    playHintWarning();

    const overlay = document.createElement('div');
    overlay.id = 'hintConfirmOverlay';
    overlay.className = 'fixed inset-0 bg-black/60 flex items-center justify-center z-50 backdrop-blur-sm';

    overlay.innerHTML = `
        <div class="bg-slate-800 border border-orange-600 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl pop-in text-center">
            <div class="text-4xl mb-3">🔥</div>
            <h3 class="text-lg font-bold text-white mb-1">Break your combo?</h3>
            <p class="text-slate-400 text-sm mb-1">
                You have a <span class="text-orange-300 font-semibold" id="confirmComboCount"></span> active.
            </p>
            <p class="text-slate-500 text-xs mb-1">Using a hint will reset your combo to zero.</p>
            <p class="text-yellow-600 text-xs mb-5">You'll lose the +5 bonus XP. Using both hints loses all bonus XP.</p>
            <div class="flex gap-3">
                <button onclick="dismissHintConfirm()"
                    class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2.5 rounded-lg transition text-sm">
                    Keep my combo
                </button>
                <button onclick="confirmUseHint()"
                    class="flex-1 bg-orange-700 hover:bg-orange-600 text-white font-semibold py-2.5 rounded-lg transition text-sm">
                    Use hint anyway
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Fill in current combo count
    const comboCount = document.getElementById('comboCount');
    const confirmEl  = document.getElementById('confirmComboCount');
    if (comboCount && confirmEl) confirmEl.textContent = comboCount.textContent;

    // Click outside to cancel
    overlay.addEventListener('click', e => { if (e.target === overlay) dismissHintConfirm(); });
}

function dismissHintConfirm() {
    const el = document.getElementById('hintConfirmOverlay');
    if (!el) return;
    el.querySelector('div').classList.replace('pop-in', 'pop-out');
    setTimeout(() => el.remove(), 280);
}

function confirmUseHint() {
    dismissHintConfirm();
    revealHint();
}

async function purchaseChallengeUnlock(item, details = {}) {
    const response = await fetch('/cpp_learning/xp_purchase.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            game: 'challenge',
            item,
            challenge_id: challengeId,
            csrf_token: purchaseCsrf,
            ...details
        })
    });
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || 'Could not spend XP.');
    if (typeof window.cppqUpdatePlayerXp === 'function') {
        window.cppqUpdatePlayerXp(result.new_xp, result.new_level);
    }
    return result;
}

async function revealHint() {
    const btn = document.getElementById('hintBtn');
    btn.disabled = true;
    try {
        const result = await purchaseChallengeUnlock('hint', { hint_index: hintLevel });
        document.getElementById('hintLabel').textContent = `Hint ${hintLevel + 1} of ${totalHints}`;
        document.getElementById('hintText').textContent = result.content;
        document.getElementById('hintBox').classList.remove('hidden');
        hintLevel++;
        hintsUsed = Math.min(2, result.owned_hint_count);
        hintUsed = result.owned_hint_count > 0;
        playHint();
        if (result.already_owned) {
            showToast('This hint was already unlocked. No XP was charged again.', 'info');
        } else {
            showToast('Hint unlocked for 10 XP. Use it to reason through the code.', 'info');
        }

        const sub = document.getElementById('comboSub');
        const inner = document.getElementById('comboInner');
        const comboActive = document.getElementById('comboBar') && !document.getElementById('comboBar').classList.contains('hidden');
        if (comboActive && !answered) {
            if (sub) sub.textContent = '⚠️ Combo breaks on next submission';
            if (inner) {
                inner.classList.add('shake');
                setTimeout(() => inner.classList.remove('shake'), 450);
            }
        }
        updateXpLabel();

        if (hintLevel >= totalHints) {
            btn.textContent = '✓ All hints unlocked';
            btn.classList.add('opacity-40', 'cursor-not-allowed');
        } else {
            btn.textContent = `💡 Hint ${hintLevel + 1}/${totalHints} · 10 XP`;
            btn.disabled = false;
        }
    } catch (error) {
        showToast(error.message || 'Could not unlock this hint.', 'error');
        btn.disabled = false;
    }
}

async function revealChallengeAnswer() {
    const btn = document.getElementById('revealAnswerBtn');
    btn.disabled = true;
    try {
        const result = await purchaseChallengeUnlock('answer');
        document.getElementById('answerRevealText').textContent = result.content;
        document.getElementById('answerRevealPanel').classList.remove('hidden');
        btn.textContent = result.already_owned ? 'Answer already unlocked' : 'Answer unlocked';
        showToast(result.already_owned ? 'This answer was already unlocked; no XP charged again.' : 'Answer revealed for 50 XP.', 'info');
    } catch (error) {
        showToast(error.message || 'Could not reveal the answer.', 'error');
        btn.disabled = false;
    }
}

function updateXpLabel() {
    const base = <?= $challenge['xp_reward'] ?>;
    const el   = document.getElementById('xpLabel');
    if (!el) return;
    if (hintsUsed >= 2) {
        el.innerHTML = '+' + base + ' XP <span class="text-slate-500 text-xs">(no bonus)</span>';
    } else if (hintsUsed === 1) {
        el.innerHTML = '+' + base + ' XP <span class="text-yellow-500 text-xs">+3 bonus</span>';
    } else {
        el.innerHTML = '+' + base + ' XP <span class="text-green-400 text-xs">+5 bonus</span>';
    }
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submitAnswer(btn) {
    const answer = document.getElementById('userAnswer').value;
    if (!answer.trim()) return;
    btn.textContent = 'Checking...';
    btn.disabled = true;

    fetch('/cpp_learning/run.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ challenge_id: challengeId, answer, csrf_token: answerCsrf })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) throw new Error(data.error);
        showResult(data);
        btn.textContent = 'Submit Answer';
        btn.disabled = false;
    })
    .catch(error => {
        showToast(error.message || 'Could not submit your answer. Please try again.', 'error');
        btn.textContent = 'Submit Answer';
        btn.disabled = false;
    });
}

const answerInput = document.getElementById('userAnswer');
try {
    answerInput.value = localStorage.getItem(answerDraftKey) || '';
} catch {
    showToast('Local answer drafts are unavailable in this browser.', 'info');
}
answerInput.addEventListener('input', () => {
    try {
        localStorage.setItem(answerDraftKey, answerInput.value);
    } catch {
        showToast('Could not save your answer draft in this browser.', 'error');
    }
});

document.getElementById('challengeReportForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const status = document.getElementById('challengeReportStatus');
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    status.textContent = 'Sending report...';
    try {
        const response = await fetch('/cpp_learning/report_challenge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                challenge_id: challengeId,
                issue_type: form.elements.issue_type.value,
                details: form.elements.details.value,
                csrf_token: reportCsrf
            })
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Could not send this report.');
        status.textContent = 'Thanks. Your report was recorded for review.';
        form.reset();
    } catch (error) {
        status.textContent = error.message || 'Could not send this report. Please try again.';
    } finally {
        submitButton.disabled = false;
    }
});

// ── Result ────────────────────────────────────────────────────────────────────
function showResult(data) {
    updateComboUI(data);
    if (typeof window.cppqUpdatePlayerXp === 'function' && Number.isInteger(data.new_xp)) {
        window.cppqUpdatePlayerXp(data.new_xp, data.new_level);
    }

    if (data.correct) {
        answered = true;
        const xpLbl = document.getElementById('xpLabel');
        if (xpLbl) xpLbl.closest('span').remove();
        if (data.level_up) playLevelUp();
        else playCorrect();

        if (data.xp_earned) spawnXpFloat('+' + data.xp_earned + ' XP');

        if (data.difficulty_complete && data.completed_difficulty) {
            setTimeout(() => showDifficultyComplete(data.completed_difficulty), 900);
        }

        if (data.all_complete) {
            showAllCompleteModal(data);
        } else {
            showCorrectModal(data);
        }

    } else {
        playWrong();
        document.getElementById('codePanel').classList.add('shake');
        setTimeout(() => document.getElementById('codePanel').classList.remove('shake'), 450);

        const area = document.getElementById('resultArea');
        area.classList.remove('hidden');

        const nudge = totalHints > 0 && hintLevel < totalHints && data.attempts >= 2
            ? `<div class="mt-2 text-yellow-500 text-xs">💡 Try the hint on the right — it won't give the answer, just a nudge.</div>` : '';

        area.innerHTML = `
            <div class="bg-red-900/25 border border-red-700/60 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-red-400 text-lg">✗</span>
                    <span class="text-red-300 font-bold">Wrong answer</span>
                    <span class="ml-auto text-xs text-slate-500">Attempt ${data.attempts}</span>
                </div>
                <div class="text-sm text-slate-400">Read the code carefully and try once more.</div>
                ${nudge}
            </div>`;
        if (data.output_feedback) {
            const feedback = document.createElement('p');
            feedback.className = 'mt-2 text-sm text-amber-200';
            feedback.textContent = `First difference at character ${data.output_feedback.position}: expected ${data.output_feedback.expected}, submitted ${data.output_feedback.submitted}. Expected length: ${data.output_feedback.expected_length}; your length: ${data.output_feedback.submitted_length}.`;
            area.querySelector('div').appendChild(feedback);
        }
    }
}

function showCorrectModal(data) {
    const multBadge = data.multiplier > 1
        ? `<span class="ml-1 text-xs bg-orange-900/60 text-orange-300 px-1.5 py-0.5 rounded-full">${data.multiplier.toFixed(1)}× combo</span>` : '';

    const overlay = document.createElement('div');
    overlay.id = 'correctModal';
    overlay.className = 'fixed inset-0 z-40 flex items-center justify-center bg-black/60 backdrop-blur-sm';
    overlay.innerHTML = `
        <div class="bg-slate-800 border border-green-500/60 rounded-2xl p-8 max-w-sm w-full mx-4 shadow-2xl text-center pop-in">
            <div class="text-5xl mb-3">🎉</div>
            <div class="text-green-300 font-bold text-2xl mb-1">Correct!</div>
            ${data.xp_earned ? `<div class="text-yellow-400 mt-2 font-semibold text-lg">+${data.xp_earned} XP ${multBadge}</div>` : ''}
            ${data.level_up  ? `<div class="text-purple-300 mt-2 font-bold text-sm">🎊 Level Up! Now Level ${data.new_level}!</div>` : ''}
            <a href="${nextUrl}"
               class="inline-block mt-5 bg-indigo-600 hover:bg-indigo-500 px-8 py-3 rounded-xl font-semibold transition text-sm w-full">
                ${nextLabel}
            </a>
        </div>`;
    document.body.appendChild(overlay);
}

function showAllCompleteModal(data) {
    const multBadge = data.multiplier > 1
        ? `<span class="ml-1 text-xs bg-orange-900/60 text-orange-300 px-1.5 py-0.5 rounded-full">${data.multiplier.toFixed(1)}× combo</span>` : '';

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-40 flex items-center justify-center bg-black/60 backdrop-blur-sm';
    overlay.innerHTML = `
        <div class="bg-gradient-to-br from-yellow-900/60 to-indigo-900/60 border border-yellow-500 rounded-2xl p-8 max-w-sm w-full mx-4 shadow-2xl text-center pop-in">
            <div class="text-5xl mb-3">🏆</div>
            <div class="text-yellow-300 font-bold text-xl mb-1">All Challenges Complete!</div>
            <div class="text-slate-300 text-sm mb-4">You're a C++ master. Check the leaderboard!</div>
            ${data.xp_earned ? `<div class="text-yellow-400 mb-2 font-semibold">+${data.xp_earned} XP ${multBadge}</div>` : ''}
            ${data.level_up  ? `<div class="text-purple-300 mb-3 font-bold text-sm">🎊 Level Up! Now Level ${data.new_level}!</div>` : ''}
            <div class="flex justify-center gap-3 mt-5 flex-wrap">
                <a href="/cpp_learning/leaderboard.php" class="bg-yellow-500 hover:bg-yellow-400 text-black font-bold px-5 py-2 rounded-lg text-sm">🏆 Leaderboard</a>
                <a href="/cpp_learning/challenges.php"   class="bg-indigo-600 hover:bg-indigo-500 px-5 py-2 rounded-lg text-sm font-semibold">Challenges</a>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    launchConfetti();
}

// ── Combo UI update ───────────────────────────────────────────────────────────
function updateComboUI(data) {
    const bar   = document.getElementById('comboBar');
    const inner = document.getElementById('comboInner');

    if (data.combo_broken) {
        playComboBreak();
        showToast('💔 Combo broken!', 'error', 2000);
        if (inner) inner.classList.add('shake');
        setTimeout(() => {
            inner && inner.classList.remove('shake');
            bar  && bar.classList.add('hidden');
        }, 500);
        return;
    }

    if (!data.correct || data.combo <= 0) return;

    // Milestone popups
    if (data.combo === 3 || data.combo === 5) {
        showComboPopup(data.combo, data.multiplier);
    }
    if (data.combo >= 5) playComboMax();
    else if (data.combo >= 2) playComboUp(data.combo);

    // Update persistent bar
    bar && bar.classList.remove('hidden');

    const flames = '🔥'.repeat(Math.min(data.combo, 5));
    const flameEl = document.getElementById('comboFlame');
    const countEl = document.getElementById('comboCount');
    const subEl   = document.getElementById('comboSub');
    const multEl  = document.getElementById('comboMultDisplay');

    if (flameEl) { flameEl.textContent = flames; flameEl.classList.add('pop-in'); setTimeout(() => flameEl.classList.remove('pop-in'), 400); }
    if (countEl) countEl.textContent = data.combo + '× Combo!';

    if (data.combo >= 5) {
        if (inner) inner.className = 'rounded-xl p-3 flex items-center justify-between border border-red-500/70 bg-red-900/25 combo-glow';
        if (subEl) subEl.textContent = '🔥 MAX COMBO — 2× XP!';
        if (multEl) multEl.innerHTML = '<div class="text-2xl font-black text-yellow-400">2×</div><div class="text-xs text-slate-500">XP multiplier</div>';
    } else if (data.combo >= 3) {
        if (inner) inner.className = 'rounded-xl p-3 flex items-center justify-between border border-orange-500/60 bg-orange-900/20';
        if (subEl) subEl.textContent = data.multiplier.toFixed(1) + '× XP active — keep going!';
        if (multEl) multEl.innerHTML = `<div class="text-2xl font-black text-yellow-400">${data.multiplier.toFixed(1)}×</div><div class="text-xs text-slate-500">XP multiplier</div>`;
    } else {
        if (inner) inner.className = 'rounded-xl p-3 flex items-center justify-between border border-yellow-700/40 bg-yellow-900/10';
        if (subEl) subEl.textContent = 'Get to 3 in a row for 1.5× XP!';
        if (multEl) multEl.innerHTML = '<div class="text-slate-500 text-sm">1× XP</div>';
    }
}

// ── Difficulty complete overlay ───────────────────────────────────────────────
function showDifficultyComplete(diff) {
    const cfg = {
        beginner:     { label:'Beginner',     emoji:'🌱', next:'Intermediate', color:'green',  border:'border-green-500',  bg:'from-green-950  to-emerald-950', text:'text-green-300',  sub:'text-green-400'  },
        intermediate: { label:'Intermediate', emoji:'⚡', next:'Advanced',     color:'yellow', border:'border-yellow-500', bg:'from-yellow-950 to-amber-950',   text:'text-yellow-300', sub:'text-yellow-400' },
        advanced:     { label:'Advanced',     emoji:'🚀', next:null,           color:'red',    border:'border-red-500',    bg:'from-red-950    to-rose-950',     text:'text-red-300',    sub:'text-red-400'    },
    }[diff] || {};

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black/70 flex items-center justify-center z-50 backdrop-blur-sm';

    overlay.innerHTML = `
        <div class="bg-gradient-to-b ${cfg.bg} border-2 ${cfg.border} rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl pop-in text-center">
            <div class="text-6xl mb-3">${cfg.emoji}</div>
            <div class="text-xs font-bold uppercase tracking-widest ${cfg.sub} mb-1">Level Complete</div>
            <h2 class="text-3xl font-black text-white mb-2">${cfg.label} Cleared!</h2>
            <p class="text-slate-400 text-sm mb-6">
                You've finished every <span class="${cfg.text} font-semibold">${cfg.label}</span> challenge.
                ${cfg.next ? `Get ready for <span class="text-white font-semibold">${cfg.next}</span> — it gets harder from here.` : ''}
            </p>
            <div class="flex gap-3 justify-center flex-wrap">
                ${cfg.next
                    ? `<a href="${nextUrl}" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-2.5 rounded-xl transition text-sm">
                           Continue to ${cfg.next} →
                       </a>`
                    : ''}
                <a href="/cpp_learning/challenges.php" class="bg-slate-700 hover:bg-slate-600 text-slate-300 font-semibold px-6 py-2.5 rounded-xl transition text-sm">
                    Challenges
                </a>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
    launchConfetti();
}

// ── Confetti ──────────────────────────────────────────────────────────────────
function launchConfetti() {
    const colors = ['#fbbf24','#818cf8','#34d399','#f472b6','#60a5fa'];
    for (let i = 0; i < 130; i++) {
        const el = document.createElement('div');
        el.style.cssText = `position:fixed;top:-10px;left:${Math.random()*100}vw;
            width:${5+Math.random()*7}px;height:${5+Math.random()*7}px;
            background:${colors[~~(Math.random()*colors.length)]};
            border-radius:${Math.random()>.5?'50%':'2px'};
            opacity:1;pointer-events:none;z-index:9999;
            animation:fall ${1.5+Math.random()*2}s linear forwards;
            transform:rotate(${Math.random()*360}deg)`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
}

document.getElementById('userAnswer').addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'Enter') submitAnswer(document.getElementById('submitBtn'));
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-c.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-cpp.min.js"></script>
</body>
</html>
