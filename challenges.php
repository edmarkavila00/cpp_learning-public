<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();

$stmt = $pdo->prepare("
    SELECT c.*,
           COALESCE(up.solved, 0) AS solved,
           COALESCE(up.attempts, 0) AS attempts
    FROM challenges c
    LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
    ORDER BY FIELD(c.difficulty, 'beginner', 'intermediate', 'advanced'), c.order_num, c.id
");
$stmt->execute([$user['id']]);
$challenges = $stmt->fetchAll();

$grouped = ['beginner' => [], 'intermediate' => [], 'advanced' => []];
$categories = [];
foreach ($challenges as $challenge) {
    if (isset($grouped[$challenge['difficulty']])) {
        $grouped[$challenge['difficulty']][] = $challenge;
    }
    $categories[$challenge['category']] = $challenge['category'];
}
natcasesort($categories);

$config = [
    'beginner' => [
        'label' => 'Beginner',
        'icon' => 'fa-seedling',
        'text' => 'text-green-300',
        'border' => 'border-green-700',
        'badge' => 'text-green-400 bg-green-900/30 border-green-700',
        'lock' => null,
    ],
    'intermediate' => [
        'label' => 'Intermediate',
        'icon' => 'fa-layer-group',
        'text' => 'text-yellow-300',
        'border' => 'border-yellow-700',
        'badge' => 'text-yellow-400 bg-yellow-900/30 border-yellow-700',
        'lock' => 'Complete all Beginner challenges to unlock',
    ],
    'advanced' => [
        'label' => 'Advanced',
        'icon' => 'fa-rocket',
        'text' => 'text-red-300',
        'border' => 'border-red-700',
        'badge' => 'text-red-400 bg-red-900/30 border-red-700',
        'lock' => 'Complete all Intermediate challenges to unlock',
    ],
];

$stats = [];
foreach ($config as $difficulty => $settings) {
    $list = $grouped[$difficulty];
    $solved = count(array_filter($list, fn($challenge) => $challenge['solved']));
    $total = count($list);
    $stats[$difficulty] = [
        'solved' => $solved,
        'total' => $total,
        'complete' => $total > 0 && $solved === $total,
    ];
}

$defaultTab = 'beginner';
if ($stats['beginner']['complete'] && !$stats['intermediate']['complete']) {
    $defaultTab = 'intermediate';
} elseif ($stats['intermediate']['complete']) {
    $defaultTab = 'advanced';
}

$allSolved = count(array_filter($challenges, fn($challenge) => $challenge['solved']));
$pageTitle = 'Challenges';
include __DIR__ . '/includes/header.php';
?>

<main class="challenge-board mx-auto max-w-5xl px-4 py-8">
    <div class="challenge-hero mb-7 flex flex-wrap items-center justify-between gap-5 rounded-2xl border p-5 sm:p-7">
        <div class="flex items-center gap-4">
            <div class="challenge-hero-icon flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl" aria-hidden="true">
                <i class="fa-solid fa-code"></i>
            </div>
            <div>
                <p class="mb-1 text-xs font-black uppercase tracking-[.22em] text-emerald-300">Mission board · C++ training</p>
                <h1 class="text-3xl font-black text-white sm:text-4xl">C++ Challenges</h1>
                <p class="mt-1 max-w-xl text-sm text-slate-300">Predict the output, earn XP, and clear each mission to open the next stage.</p>
            </div>
        </div>
        <div class="challenge-total rounded-2xl border px-5 py-3 text-right">
            <div class="text-xs font-bold uppercase tracking-[.15em] text-slate-400">Missions cleared</div>
            <div class="mt-1 text-2xl font-black text-white">
                <?= $allSolved ?><span class="text-base font-semibold text-slate-400"> / <?= count($challenges) ?></span>
            </div>
            <div class="challenge-total-track mt-2 h-1.5 w-36 overflow-hidden rounded-full">
                <div class="h-full rounded-full bg-emerald-400" style="width:<?= count($challenges) > 0 ? (int)round($allSolved / count($challenges) * 100) : 0 ?>%"></div>
            </div>
        </div>
    </div>

    <section class="difficulty-stages mb-7 grid gap-3 sm:grid-cols-3" aria-label="Difficulty progress">
        <?php foreach ($config as $difficulty => $settings): ?>
            <?php
                $difficultyStats = $stats[$difficulty];
                $sectionLocked = ($difficulty === 'intermediate' && !$stats['beginner']['complete'])
                    || ($difficulty === 'advanced' && !$stats['intermediate']['complete']);
                $percent = $difficultyStats['total'] > 0
                    ? (int)round($difficultyStats['solved'] / $difficultyStats['total'] * 100)
                    : 0;
                $stageState = $sectionLocked ? 'stage-locked' : ($difficultyStats['complete'] ? 'stage-cleared' : 'stage-open');
            ?>
            <button type="button" onclick="showTab('<?= $difficulty ?>')"
                    class="difficulty-stage <?= $stageState ?> rounded-xl border p-4 text-left transition"
                    aria-label="<?= htmlspecialchars($settings['label']) ?> stage: <?= $difficultyStats['solved'] ?> of <?= $difficultyStats['total'] ?> cleared<?= $sectionLocked ? ', locked' : '' ?>">
                <span class="flex items-center gap-3">
                    <span class="stage-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg">
                        <i class="fa-solid <?= $sectionLocked ? 'fa-lock' : $settings['icon'] ?>"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[.65rem] font-black uppercase tracking-[.16em] text-slate-400">Stage <?= array_search($difficulty, array_keys($config), true) + 1 ?></span>
                        <span class="mt-0.5 block font-black text-white"><?= $settings['label'] ?></span>
                    </span>
                    <span class="text-right">
                        <span class="block text-sm font-black text-white"><?= $difficultyStats['solved'] ?>/<?= $difficultyStats['total'] ?></span>
                        <span class="text-[.65rem] font-bold uppercase tracking-wider text-slate-400"><?= $sectionLocked ? 'Locked' : ($difficultyStats['complete'] ? 'Clear' : 'Missions') ?></span>
                    </span>
                </span>
                <span class="stage-progress mt-4 block h-2 overflow-hidden rounded-full">
                    <span class="stage-progress-fill block h-full rounded-full" style="width:<?= $percent ?>%"></span>
                </span>
                <span class="mt-2 flex items-center justify-between text-xs">
                    <span class="text-slate-400"><?= $sectionLocked ? 'Clear the previous stage to enter' : ($difficultyStats['complete'] ? 'Stage complete!' : $percent . '% complete') ?></span>
                    <span class="stage-arrow font-bold" aria-hidden="true"><?= $sectionLocked ? '🔒' : '→' ?></span>
                </span>
            </button>
        <?php endforeach; ?>
    </section>

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="challenge-tabs flex w-fit max-w-full gap-2 overflow-x-auto rounded-xl border p-1.5" role="tablist" aria-label="Challenge difficulty">
            <?php foreach ($config as $difficulty => $settings): ?>
            <?php
                $sectionLocked = ($difficulty === 'intermediate' && !$stats['beginner']['complete'])
                    || ($difficulty === 'advanced' && !$stats['intermediate']['complete']);
            ?>
            <button id="tab-<?= $difficulty ?>" type="button" role="tab" aria-controls="panel-<?= $difficulty ?>"
                    onclick="showTab('<?= $difficulty ?>')"
                    class="tab-btn flex shrink-0 items-center gap-2 rounded-lg border border-transparent px-3 py-2.5 text-sm text-slate-300 transition hover:text-white sm:px-4">
                <i class="fa-solid <?= $settings['icon'] ?> text-xs"></i>
                <span><?= $settings['label'] ?></span>
                <span class="rounded-full bg-slate-700 px-1.5 py-0.5 text-xs"><?= $stats[$difficulty]['solved'] ?>/<?= $stats[$difficulty]['total'] ?></span>
                <?php if ($sectionLocked): ?><i class="fa-solid fa-lock text-xs text-slate-600"></i><?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <label for="topicFilter" class="text-sm font-bold text-slate-300">Practice topic
            <select id="topicFilter" class="ml-2 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white">
                <option value="all">All topics</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category, ENT_QUOTES) ?>"><?= htmlspecialchars(ucfirst($category)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <?php foreach ($config as $difficulty => $settings): ?>
        <?php
            $list = $grouped[$difficulty];
            $sectionLocked = ($difficulty === 'intermediate' && !$stats['beginner']['complete'])
                || ($difficulty === 'advanced' && !$stats['intermediate']['complete']);
        ?>
        <section id="panel-<?= $difficulty ?>" class="tab-panel hidden" role="tabpanel" aria-labelledby="tab-<?= $difficulty ?>">
            <?php if ($sectionLocked): ?>
                <div class="stage-locked-notice mb-4 flex items-center gap-4 rounded-xl border p-5 text-slate-300">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg"><i class="fa-solid fa-lock"></i></span>
                    <div>
                        <div class="font-black text-white"><?= $settings['label'] ?> stage locked</div>
                        <div class="text-sm"><?= $settings['lock'] ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid gap-3">
                <?php foreach ($list as $index => $challenge): ?>
                    <?php
                        $solved = (bool)$challenge['solved'];
                        $locked = $sectionLocked || ($index > 0 && !(bool)$list[$index - 1]['solved']);
                        $missionState = $solved ? 'mission-cleared' : ($locked ? 'mission-locked' : 'mission-ready');
                    ?>
                    <article data-category="<?= htmlspecialchars($challenge['category'], ENT_QUOTES) ?>" class="challenge-mission <?= $missionState ?> flex items-center justify-between gap-3 rounded-xl border p-4 transition">
                        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                            <span class="mission-node flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-black">
                                <?php if ($solved): ?>✓<?php elseif ($locked): ?><i class="fa-solid fa-lock text-xs"></i><?php else: ?><?= (int)$challenge['order_num'] ?><?php endif; ?>
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold <?= $locked ? 'text-slate-500' : 'text-white' ?>"><?= htmlspecialchars($challenge['title']) ?></h2>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span class="rounded border px-2 py-0.5 text-xs <?= $settings['badge'] ?>"><?= htmlspecialchars(ucfirst($challenge['category'])) ?></span>
                                    <?php if ($challenge['attempts'] > 0 && !$solved): ?>
                                        <span class="text-xs text-yellow-500"><?= (int)$challenge['attempts'] ?> attempt<?= (int)$challenge['attempts'] === 1 ? '' : 's' ?></span>
                                    <?php endif; ?>
                                    <?php if ($solved): ?><span class="mission-state-label text-xs font-bold uppercase tracking-wider">Cleared</span>
                                    <?php elseif (!$locked): ?><span class="mission-state-label text-xs font-bold uppercase tracking-wider">Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="mission-reward hidden rounded-lg px-2.5 py-1 text-xs font-black sm:inline">+<?= (int)$challenge['xp_reward'] ?> XP</span>
                            <?php if (!$locked): ?>
                                <a href="/cpp_learning/challenge.php?id=<?= (int)$challenge['id'] ?>"
                                   class="rounded-lg px-4 py-1.5 text-sm font-medium transition <?= $solved ? 'bg-slate-700 text-slate-300 hover:bg-slate-600' : 'bg-indigo-600 text-white hover:bg-indigo-500' ?>">
                                    <?= $solved ? 'Replay' : 'Play' ?> →
                                </a>
                            <?php else: ?>
                                <span class="px-4 py-1.5 text-sm text-slate-600">Locked</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="topic-empty-state mt-4 hidden text-sm text-slate-400">No challenges match this topic at this difficulty.</p>
        </section>
    <?php endforeach; ?>
</main>

<script>
const challengeDifficulties = new Set(['beginner', 'intermediate', 'advanced']);

function showTab(difficulty) {
    if (!challengeDifficulties.has(difficulty)) return;

    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.classList.remove('challenge-tab-active');
        button.setAttribute('aria-selected', 'false');
    });

    document.getElementById('panel-' + difficulty).classList.remove('hidden');
    const activeTab = document.getElementById('tab-' + difficulty);
    activeTab.classList.add('challenge-tab-active');
    activeTab.setAttribute('aria-selected', 'true');
    localStorage.setItem('cppq_challenge_tab', difficulty);
}

const topicFilter = document.getElementById('topicFilter');
function applyTopicFilter(category) {
    const selectedCategory = [...topicFilter.options].some(option => option.value === category) ? category : 'all';
    topicFilter.value = selectedCategory;
    document.querySelectorAll('.tab-panel').forEach(panel => {
        let visibleCount = 0;
        panel.querySelectorAll('.challenge-mission').forEach(mission => {
            const visible = selectedCategory === 'all' || mission.dataset.category === selectedCategory;
            mission.classList.toggle('hidden', !visible);
            if (visible) visibleCount++;
        });
        panel.querySelector('.topic-empty-state').classList.toggle('hidden', visibleCount > 0);
    });
    localStorage.setItem('cppq_challenge_category', selectedCategory);
}

topicFilter.addEventListener('change', () => applyTopicFilter(topicFilter.value));

const savedTab = localStorage.getItem('cppq_challenge_tab');
const defaultTab = <?= json_encode($defaultTab) ?>;
showTab(challengeDifficulties.has(savedTab) ? savedTab : defaultTab);
const requestedCategory = new URLSearchParams(window.location.search).get('category');
const savedCategory = localStorage.getItem('cppq_challenge_category');
applyTopicFilter(requestedCategory || savedCategory || 'all');
</script>
</body>
</html>
