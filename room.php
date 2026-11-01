<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coop.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();
$code = strtoupper(trim($_GET['code'] ?? ''));
$room = findCoopRoom($pdo, $code, (int)$user['id']);
if (!$room) {
    http_response_code(404);
    $pageTitle = 'Room not found';
    include __DIR__ . '/includes/header.php';
    echo '<main class="max-w-3xl mx-auto px-4 py-12"><h1 class="text-2xl font-bold">Room not found</h1><p class="text-slate-400 mt-2">Check the invite code or join this room first.</p><a class="text-indigo-300 mt-4 inline-block" href="/cpp_learning/coop.php">Back to co-op rooms →</a></main></body></html>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.user_id, m.joined_at,
           COALESCE(p.display_name, u.username) AS display_name,
           COALESCE(p.avatar_id, MOD(u.id - 1, 12)) AS avatar_id,
           COALESCE(up.solved, 0) AS solved,
           (m.user_id = ?) AS is_self,
           (m.user_id = ?) AS is_host
    FROM coop_room_members m
    JOIN users u ON u.id = m.user_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN user_progress up ON up.user_id = u.id AND up.challenge_id = ?
    WHERE m.room_id = ?
    ORDER BY m.joined_at, m.user_id
");
$stmt->execute([$user['id'], $room['host_id'], $room['challenge_id'], $room['id']]);
$members = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT solved FROM user_progress WHERE user_id = ? AND challenge_id = ?");
$stmt->execute([$user['id'], $room['challenge_id']]);
$userSolved = (bool)$stmt->fetchColumn();
$canSubmit = $room['status'] === 'open' && !($userSolved && in_array($room['mode'], ['chat_room', 'race'], true));
$modeDescriptions = [
    'team_answer' => 'Work as a party: the first correct answer marks the mission complete and awards XP to every member who has not solved it yet.',
    'chat_room' => 'Study together in chat. Everyone submits their own answer and earns their own XP.',
    'race' => 'Race your friends. The first correct answer claims the XP and ends the round.',
];

$pageTitle = 'Co-op Mission';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <a href="/cpp_learning/coop.php" class="text-sm text-slate-400 hover:text-indigo-300">← Multiplayer hub</a>
            <div class="text-xs uppercase tracking-[.2em] text-cyan-300 font-bold mt-4"><?= htmlspecialchars(coopModeLabel($room['mode'])) ?> · <?= htmlspecialchars(ucfirst($room['difficulty'])) ?></div>
            <h1 class="text-3xl font-black mt-1"><?= htmlspecialchars($room['title']) ?></h1>
            <p class="text-sm text-slate-400 mt-2 max-w-2xl"><?= htmlspecialchars($modeDescriptions[$room['mode']]) ?></p>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-3 min-w-48">
            <div class="text-xs text-slate-400">Invite code</div>
            <div class="flex items-center gap-2 mt-1">
                <code id="inviteCode" class="text-xl font-black tracking-[.2em] text-cyan-300"><?= htmlspecialchars($room['invite_code']) ?></code>
                <button type="button" id="copyInvite" class="rounded bg-slate-700 px-2 py-1 text-xs hover:bg-slate-600">Copy</button>
            </div>
            <div id="copyStatus" class="text-xs text-slate-500 mt-1">Share this code with friends.</div>
        </div>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_320px] gap-5">
        <div class="space-y-5">
            <section class="game-panel rounded-2xl border border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between bg-slate-950/60 border-b border-slate-700 px-4 py-3">
                    <div class="text-sm font-bold">Mission briefing · <?= (int)$room['xp_reward'] ?> XP</div>
                    <span id="roomStatus" class="text-xs rounded-full px-3 py-1 <?= $room['status'] === 'open' ? 'bg-green-900/50 text-green-300' : 'bg-slate-700 text-slate-300' ?>"><?= $room['status'] === 'open' ? 'In progress' : 'Mission complete' ?></span>
                </div>
                <pre class="p-5 overflow-x-auto text-sm leading-relaxed"><code class="language-cpp"><?= htmlspecialchars($room['challenge_code']) ?></code></pre>
            </section>

            <section class="game-panel rounded-2xl border border-slate-700 p-5">
                <h2 class="font-bold text-lg">Submit your output</h2>
                <p class="text-sm text-slate-400 mt-1">Predict exactly what the program prints.</p>
                <?php if ($canSubmit): ?>
                    <form id="answerForm" class="mt-4">
                        <textarea id="roomAnswer" rows="3" maxlength="2000" required placeholder="Your predicted output..."
                                  data-no-paste="true" data-paste-source="coop"
                                  data-paste-context="<?= htmlspecialchars($room['invite_code'], ENT_QUOTES) ?>"
                                  data-paste-csrf="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>"
                                  class="w-full rounded-lg border border-slate-600 bg-slate-950 px-4 py-3 font-mono text-sm text-white focus:border-indigo-400 focus:outline-none"></textarea>
                        <button id="answerButton" class="game-button mt-3 w-full rounded-lg bg-indigo-600 px-4 py-3 font-bold hover:bg-indigo-500">Submit answer</button>
                    </form>
                <?php elseif ($room['status'] !== 'open'): ?>
                    <div class="mt-4 rounded-lg border border-slate-700 bg-slate-950/50 p-3 text-sm text-slate-300">This mission has ended. Check the party results.</div>
                <?php else: ?>
                    <div class="mt-4 rounded-lg border border-green-700 bg-green-900/20 p-3 text-sm text-green-200">You have already cleared this mission.</div>
                <?php endif; ?>
                <div id="answerFeedback" class="hidden mt-3 rounded-lg p-3 text-sm" role="status"></div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="game-panel rounded-2xl border border-slate-700 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-bold">Party</h2>
                    <span id="memberCount" class="text-xs text-slate-400"><?= count($members) ?>/8</span>
                </div>
                <div id="partyList" class="space-y-2">
                    <?php foreach ($members as $member): ?>
                        <div class="flex items-center gap-3 rounded-lg bg-slate-950/50 p-2">
                            <?= pixelAvatar((int)$member['user_id'], 36, (int)$member['avatar_id']) ?>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium"><?= htmlspecialchars($member['display_name']) ?><?= $member['is_self'] ? ' (you)' : '' ?></div>
                                <div class="text-xs text-slate-500"><?= $member['is_host'] ? 'Room host' : ($member['solved'] ? 'Mission cleared' : 'Ready') ?></div>
                            </div>
                            <?php if ($member['solved']): ?><span class="text-green-400">✓</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="game-panel rounded-2xl border border-slate-700 p-4">
                <h2 class="font-bold mb-1">Party chat</h2>
                <div id="chatMessages" class="h-64 overflow-y-auto rounded-lg bg-slate-950/60 p-3 space-y-3" aria-live="polite"></div>
                <form id="chatForm" class="flex gap-2 mt-3">
                    <input id="chatInput" maxlength="500" required placeholder="Message your party..."
                           class="min-w-0 flex-1 rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white focus:border-cyan-400 focus:outline-none">
                    <button class="rounded-lg bg-cyan-700 px-3 py-2 text-sm font-bold hover:bg-cyan-600">Send</button>
                </form>
                <div id="chatError" class="hidden text-xs text-red-300 mt-2"></div>
            </section>
        </aside>
    </div>
</main>

<script>
const roomCode = <?= json_encode($room['invite_code']) ?>;
const csrfToken = <?= json_encode(csrfToken()) ?>;
const roomMode = <?= json_encode($room['mode']) ?>;
const currentUserId = <?= (int)$user['id'] ?>;
let lastMessageId = 0;
let polling = false;
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

document.getElementById('copyInvite').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(document.getElementById('inviteCode').textContent);
        document.getElementById('copyStatus').textContent = 'Invite code copied.';
    } catch (error) {
        document.getElementById('copyStatus').textContent = 'Copy failed; select the code to share it.';
    }
});

function addMessage(message) {
    const row = document.createElement('div');
    row.className = 'text-sm';
    const author = document.createElement('div');
    author.className = 'text-xs font-bold ' + (Number(message.user_id) === currentUserId ? 'text-cyan-300' : 'text-indigo-300');
    author.textContent = message.display_name;
    const body = document.createElement('div');
    body.className = 'text-slate-200 break-words';
    body.textContent = message.message;
    row.append(author, body);
    document.getElementById('chatMessages').append(row);
    document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
}

async function pollRoom() {
    if (polling) return;
    polling = true;
    try {
        const response = await fetch(`/cpp_learning/coop_api.php?code=${encodeURIComponent(roomCode)}&after=${lastMessageId}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not refresh the room.');
        data.messages.forEach(message => {
            addMessage(message);
            lastMessageId = Math.max(lastMessageId, Number(message.id));
        });
        document.getElementById('partyList').innerHTML = data.members.map(member => `
            <div class="flex items-center gap-3 rounded-lg bg-slate-950/50 p-2">
                ${member.avatar_html}
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">${escapeHtml(member.display_name)}${Number(member.user_id) === currentUserId ? ' (you)' : ''}</div>
                    <div class="text-xs text-slate-500">${member.is_host ? 'Room host' : (Number(member.solved) ? 'Mission cleared' : 'Ready')}</div>
                </div>
                ${Number(member.solved) ? '<span class="text-green-400">✓</span>' : ''}
            </div>`).join('');
        const status = document.getElementById('roomStatus');
        if (data.room.status === 'completed') {
            status.textContent = data.room.mode === 'race' ? 'Race finished' : 'Mission complete';
            status.className = 'text-xs rounded-full px-3 py-1 bg-slate-700 text-slate-300';
            if (data.room.mode === 'race' && data.room.winner_name) status.textContent += ' · ' + data.room.winner_name + ' won';
            const button = document.getElementById('answerButton');
            if (button) {
                button.disabled = true;
                button.textContent = 'Mission complete';
            }
        }
        document.getElementById('memberCount').textContent = `${data.members.length}/8`;
    } catch (error) {
        document.getElementById('chatError').textContent = error.message;
        document.getElementById('chatError').classList.remove('hidden');
    } finally {
        polling = false;
    }
}

document.getElementById('chatForm').addEventListener('submit', async event => {
    event.preventDefault();
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;
    try {
        const response = await fetch('/cpp_learning/coop_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({code: roomCode, action: 'message', message, csrf_token: csrfToken})
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Message could not be sent.');
        input.value = '';
        document.getElementById('chatError').classList.add('hidden');
        await pollRoom();
    } catch (error) {
        document.getElementById('chatError').textContent = error.message;
        document.getElementById('chatError').classList.remove('hidden');
    }
});

const answerForm = document.getElementById('answerForm');
if (answerForm) {
    answerForm.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('answerButton');
        const feedback = document.getElementById('answerFeedback');
        button.disabled = true;
        button.textContent = 'Checking...';
        try {
            const response = await fetch('/cpp_learning/coop_submit.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    code: roomCode,
                    answer: document.getElementById('roomAnswer').value,
                    csrf_token: csrfToken
                })
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Answer could not be checked.');
            feedback.className = 'mt-3 rounded-lg border p-3 text-sm ' + (data.correct ? 'border-green-700 bg-green-900/30 text-green-200' : 'border-red-700 bg-red-900/30 text-red-200');
            feedback.textContent = data.message;
            feedback.classList.remove('hidden');
            if (data.room_completed || roomMode === 'chat_room' && data.correct || roomMode === 'race' && data.already_solved) {
                button.disabled = true;
                button.textContent = 'Mission complete';
            } else {
                button.disabled = false;
                button.textContent = 'Submit answer';
            }
            await pollRoom();
        } catch (error) {
            feedback.className = 'mt-3 rounded-lg border border-red-700 bg-red-900/30 p-3 text-sm text-red-200';
            feedback.textContent = error.message;
            feedback.classList.remove('hidden');
            button.disabled = false;
            button.textContent = 'Submit answer';
        }
    });
}

pollRoom();
window.setInterval(pollRoom, 2500);
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-c.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-cpp.min.js"></script>
</body>
</html>
