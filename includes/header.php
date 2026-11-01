<?php
require_once __DIR__ . '/auth.php';
$user = isLoggedIn() ? currentUser() : null;
$badges = [];
$earnedBadgeCount = 0;
if ($user) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/code_game.php';

    $userId = (int)$user['id'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS challenge_total,
               COALESCE(SUM(COALESCE(up.solved, 0) = 1), 0) AS challenge_solved,
               COALESCE(SUM(COALESCE(ap.cleared, 0) = 1), 0) AS adventure_cleared
        FROM challenges c
        LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
        LEFT JOIN adventure_progress ap ON ap.challenge_id = c.id AND ap.user_id = ?
    ");
    $stmt->execute([$userId, $userId]);
    $missionProgress = $stmt->fetch();
    $challengeTotal = (int)$missionProgress['challenge_total'];
    $challengeSolved = (int)$missionProgress['challenge_solved'];
    $adventureCleared = (int)$missionProgress['adventure_cleared'];

    $codeProblems = codeGameProblems();
    $codeProblemIds = array_keys($codeProblems);
    $codeSolved = 0;
    if ($codeProblemIds) {
        $placeholders = implode(',', array_fill(0, count($codeProblemIds), '?'));
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM code_game_progress
            WHERE user_id = ? AND solved = 1 AND problem_id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$userId], $codeProblemIds));
        $codeSolved = (int)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT r.id)
        FROM coop_rooms r
        JOIN coop_room_members m ON m.room_id = r.id
        WHERE m.user_id = ?
          AND r.status = 'completed'
          AND (
              r.mode = 'team_answer'
              OR (r.mode = 'race' AND r.winner_id = m.user_id)
          )
    ");
    $stmt->execute([$userId]);
    $coopCompleted = (int)$stmt->fetchColumn();
    $streakDays = max(0, (int)($_SESSION['streak'] ?? 0));

    $badgeGroups = [
        [
            'key' => 'streak',
            'icon' => 'fa-fire',
            'current' => $streakDays,
            'unit' => 'days',
            'thresholds' => [3, 5, 7, 14, 21, 30, 50, 100],
            'titles' => ['Kindled Streak', 'Steady Flame', 'Week of Fire', 'Fortnight Flame', 'Three-Week Spark', 'Monthly Blaze', 'Golden Streak', 'Century Flame'],
            'description' => 'Maintain a daily play streak for %s consecutive days.',
        ],
        [
            'key' => 'challenges',
            'icon' => 'fa-puzzle-piece',
            'current' => $challengeSolved,
            'unit' => 'challenges',
            'thresholds' => array_values(array_unique([1, 5, 10, 20, 30, 40, 50, 60, 75, $challengeTotal])),
            'titles' => ['First Solve', 'Puzzle Scout', 'Challenge Seeker', 'Quest Solver', 'Mission Regular', 'Challenge Ace', 'Puzzle Expert', 'Quest Champion', 'Grand Challenger', 'Challenge Conqueror'],
            'description' => 'Clear %s C++ Challenge missions.',
        ],
        [
            'key' => 'adventure',
            'icon' => 'fa-map',
            'current' => $adventureCleared,
            'unit' => 'portals',
            'thresholds' => array_values(array_unique([1, 5, 10, 20, 30, 40, 50, 60, 75, $challengeTotal])),
            'titles' => ['First Portal', 'Trail Finder', 'Map Reader', 'Frontier Scout', 'Portal Runner', 'Pathfinder', 'World Explorer', 'Realm Champion', 'Legendary Voyager', 'Frontier Legend'],
            'description' => 'Clear %s Adventure portals.',
        ],
        [
            'key' => 'code_lab',
            'icon' => 'fa-terminal',
            'current' => $codeSolved,
            'unit' => 'missions',
            'thresholds' => array_values(array_unique([1, 2, count($codeProblemIds)])),
            'titles' => ['First Compile', 'Code Builder', 'Code Architect'],
            'description' => 'Complete %s Code Lab missions.',
        ],
        [
            'key' => 'xp',
            'icon' => 'fa-bolt',
            'current' => max(0, (int)$user['xp']),
            'unit' => 'XP',
            'thresholds' => [100, 250, 500, 1000, 2500, 5000, 10000, 25000],
            'titles' => ['XP Spark', 'XP Collector', 'XP Hunter', 'XP Veteran', 'XP Elite', 'XP Champion', 'XP Legend', 'XP Titan'],
            'description' => 'Earn %s total XP.',
        ],
        [
            'key' => 'levels',
            'icon' => 'fa-ranking-star',
            'current' => max(1, (int)$user['level']),
            'unit' => 'level',
            'thresholds' => [2, 3, 5, 8, 10, 15, 20, 30],
            'titles' => ['Debug Novice', 'Loop Ranger', 'Stack Climber', 'STL Master', 'C++ Wizard', 'Level Vanguard', 'Level Paragon', 'Level Mythic'],
            'description' => 'Reach Level %s.',
        ],
        [
            'key' => 'coop',
            'icon' => 'fa-people-group',
            'current' => $coopCompleted,
            'unit' => 'missions',
            'thresholds' => [1, 3, 5, 10, 15, 25],
            'titles' => ['Party Starter', 'Team Player', 'Co-op Regular', 'Party Leader', 'Co-op Ace', 'Co-op Champion'],
            'description' => 'Complete %s Co-op missions or win races.',
        ],
    ];

    foreach ($badgeGroups as $group) {
        foreach ($group['thresholds'] as $index => $threshold) {
            $threshold = (int)$threshold;
            if ($threshold < 1 || !isset($group['titles'][$index])) {
                continue;
            }

            $key = $group['key'] . '_' . $threshold;
            $badges[] = [
                'key' => $key,
                'title' => $group['titles'][$index],
                'icon' => $group['icon'],
                'description' => sprintf($group['description'], number_format($threshold)),
                'progress' => number_format(min((int)$group['current'], $threshold)) . ' / ' . number_format($threshold) . ' ' . $group['unit'],
                'unlocked' => (int)$group['current'] >= $threshold,
            ];
        }
    }

    $newBadgeKeys = array_column(
        array_filter($badges, static fn($badge) => $badge['unlocked']),
        'key'
    );
    if ($newBadgeKeys) {
        $badgeValues = implode(',', array_fill(0, count($newBadgeKeys), '(?, ?)'));
        $badgeParams = [];
        foreach ($newBadgeKeys as $badgeKey) {
            $badgeParams[] = $userId;
            $badgeParams[] = $badgeKey;
        }
        $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_key) VALUES $badgeValues")
            ->execute($badgeParams);
    }

    $stmt = $pdo->prepare("SELECT badge_key FROM user_badges WHERE user_id = ?");
    $stmt->execute([$userId]);
    $earnedBadgeKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($badges as &$badge) {
        $badge['unlocked'] = in_array($badge['key'], $earnedBadgeKeys, true);
    }
    unset($badge);
    $earnedBadgeCount = count(array_filter($badges, static fn($badge) => $badge['unlocked']));
    $badges = array_merge(
        array_values(array_filter($badges, static fn($badge) => $badge['unlocked'])),
        array_values(array_filter($badges, static fn($badge) => !$badge['unlocked']))
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CppQuest <?= isset($pageTitle) ? "— $pageTitle" : '' ?></title>
    <script>
        document.documentElement.dataset.theme = localStorage.getItem('cppq_theme') === 'dark' ? 'dark' : 'light';
    </script>
    <link rel="stylesheet" href="/cpp_learning/assets/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <style>
        .game-stars {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                repeating-linear-gradient(0deg, transparent 0 47px, rgba(6,14,9,.12) 48px 50px),
                repeating-linear-gradient(90deg, transparent 0 47px, rgba(6,14,9,.1) 48px 50px);
        }
        body > nav,
        body > main { position: relative; z-index: 1; }
        .pixel-avatar { display: block; flex: none; image-rendering: pixelated; }
        .glow { box-shadow: 0 0 20px rgba(99,102,241,0.4); }
        @keyframes pulse-xp { 0%,100%{opacity:1} 50%{opacity:.6} }
        .xp-pulse { animation: pulse-xp 1s ease-in-out; }
        @keyframes fall { to { transform: translateY(105vh) rotate(720deg); opacity:0; } }
        .game-nav .nav-game-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 38px;
            border: 1px solid #142117;
            border-radius: .55rem;
            padding: .45rem .75rem;
            color: #f3ead2;
            font-size: .82rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .activity-card {
            display: block;
            min-height: 130px;
            border-width: 2px !important;
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }

        /* Override Prism background to match the site's code panel */
        pre[class*="language-"],
        code[class*="language-"] { background: #1e1e1e !important; margin: 0; border-radius: 0; }
        pre[class*="language-"] { padding: 0 !important; }
    </style>
    <link rel="stylesheet" href="/cpp_learning/assets/theme.css?v=20261101-contrast">
</head>
<body class="text-white min-h-screen">
<div class="game-stars" aria-hidden="true"></div>

<?php if ($user): ?>
<nav class="game-nav px-4 sm:px-6 py-3 sticky top-0 z-50" aria-label="Main navigation">
    <a href="/cpp_learning/dashboard.php" class="nav-game-button nav-home" aria-label="Home - main lobby">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        <span>Home</span>
    </a>
    <div class="nav-logo-placeholder" aria-hidden="true"></div>
    <div class="nav-actions">
        <button id="navBadgesButton" type="button" class="nav-badges-button"
                aria-haspopup="dialog" aria-controls="playerBadgesDialog" aria-expanded="false">
            <i class="fa-solid fa-award" aria-hidden="true"></i>
            <span>Badges</span>
            <span class="nav-badge-count"><?= $earnedBadgeCount ?>/<?= count($badges) ?></span>
        </button>
        <button id="navXpTotal" type="button" class="bg-indigo-600 text-xs px-2 py-1 rounded-full font-bold"
                aria-haspopup="dialog" aria-controls="xpRewardsDialog" aria-expanded="false">
            XP Perks
        </button>
        <a href="/cpp_learning/profile.php" class="nav-profile-link" title="Player profile" aria-label="Player profile">
            <?= pixelAvatar((int)$user['id'], 36, (int)$user['avatar_id']) ?>
        </a>
        <a href="/cpp_learning/settings.php" class="settings-nav-button rounded-lg px-3 py-2 text-slate-300 transition hover:text-white" title="Game settings" aria-label="Game settings">
            <i class="fa-solid fa-gear"></i>
        </a>
    </div>
</nav>
<?php endif; ?>

<?php if ($user): ?>
<dialog id="xpRewardsDialog" class="xp-rewards-dialog" aria-labelledby="xpRewardsTitle">
    <div class="xp-rewards-content">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Player progression</p>
                <h2 id="xpRewardsTitle" class="mt-1 text-2xl font-black text-white">XP perks & rules</h2>
                <p class="mt-2 text-sm text-slate-300">Earn XP by learning, then spend it when you need a hand.</p>
            </div>
            <button id="xpRewardsClose" type="button" class="xp-rewards-close" aria-label="Close XP perks">×</button>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <section class="xp-rewards-card">
                <h3>Earn XP</h3>
                <ul>
                    <li>Complete Challenges and Code Lab missions.</li>
                    <li>Repair Adventure portals and build your streak.</li>
                    <li>Use hints to learn; solving correctly earns the game’s XP reward.</li>
                </ul>
            </section>
            <section class="xp-rewards-card">
                <h3>Spend XP</h3>
                <ul>
                    <li>Challenge hint: <strong>10 XP</strong> each.</li>
                    <li>Adventure hint: <strong>20 XP</strong> each round.</li>
                    <li>Reveal a challenge or Adventure answer: <strong>50 XP</strong>.</li>
                    <li>Each hint or answer unlock is purchased once and stays available.</li>
                </ul>
            </section>
        </div>

        <section class="xp-rewards-card mt-3">
            <h3>Campus rewards & quiz extra credit</h3>
            <p><strong>10 Scantron sheets</strong> are a proposed reward for first-year BSIT students at Pamantasan ng Lungsod ng Muntinlupa. The XP threshold and availability are still unconfirmed; ask your instructor. This app does not redeem or guarantee physical rewards.</p>
            <p class="mt-2">You may ask your professor whether XP can count toward quiz extra credit. Your professor decides; XP does not automatically convert to grades.</p>
        </section>

        <section class="xp-rewards-card mt-3">
            <h3>Do & don’t</h3>
            <ul>
                <li><strong>Do:</strong> practice, spend XP only when you want a hint or reveal, and check with your professor about class rewards.</li>
                <li><strong>Don’t:</strong> paste answers into marked game inputs. A paste attempt costs 10 XP; deductions stop at 0 XP.</li>
                <li><strong>Don’t:</strong> expect XP to guarantee school credit, grades, or Scantron rewards.</li>
            </ul>
        </section>
    </div>
</dialog>
<?php endif; ?>

<?php if ($user): ?>
<dialog id="playerBadgesDialog" class="xp-rewards-dialog player-badges-dialog" aria-labelledby="playerBadgesTitle">
    <div class="xp-rewards-content">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Achievements</p>
                <h2 id="playerBadgesTitle" class="mt-1 text-2xl font-black text-white">Player badges</h2>
                <p class="mt-2 text-sm text-slate-300"><?= $earnedBadgeCount ?> of <?= count($badges) ?> badges earned. Keep playing to unlock them all.</p>
            </div>
            <button id="playerBadgesClose" type="button" class="xp-rewards-close" aria-label="Close badges">×</button>
        </div>
        <div class="player-badge-list mt-5">
            <?php foreach ($badges as $badge): ?>
                <section class="player-badge-card <?= $badge['unlocked'] ? 'player-badge-earned' : 'player-badge-locked' ?>">
                    <span class="player-badge-icon" aria-hidden="true">
                        <i class="fa-solid <?= htmlspecialchars($badge['icon'], ENT_QUOTES) ?>"></i>
                    </span>
                    <span class="player-badge-info">
                        <strong><?= htmlspecialchars($badge['title']) ?></strong>
                        <span><?= htmlspecialchars($badge['description']) ?></span>
                        <small><?= htmlspecialchars($badge['progress']) ?></small>
                    </span>
                    <span class="player-badge-status"><?= $badge['unlocked'] ? 'Earned' : 'Locked' ?></span>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</dialog>
<?php endif; ?>

<script>
(() => {
    let musicContext = null;
    let musicTimer = null;
    let musicNote = 0;
    const notes = [220, 330, 440, 330, 196, 294, 392, 294];

    function updateSettingToggles(setting, enabled) {
        document.querySelectorAll('[data-setting-toggle="' + setting + '"]').forEach(button => {
            button.setAttribute('aria-checked', enabled ? 'true' : 'false');
            button.classList.toggle('setting-toggle-enabled', enabled);
            button.classList.toggle('setting-toggle-disabled', !enabled);
            const state = button.querySelector('[data-setting-state]');
            if (state) state.textContent = enabled ? 'On' : 'Off';
        });
    }

    window.cppqSetEffectsMuted = muted => {
        localStorage.setItem('cppq_muted', muted ? '1' : '0');
        updateSettingToggles('effects', !muted);
        const challengeButton = document.getElementById('muteBtn');
        if (challengeButton) challengeButton.textContent = muted ? '🔇' : '🔊';
        if (typeof window.cppqOnEffectsChanged === 'function') window.cppqOnEffectsChanged(muted);
    };

    function showPasteNotice(message, isError = false) {
        let notice = document.getElementById('cppqPasteNotice');
        if (!notice) {
            notice = document.createElement('div');
            notice.id = 'cppqPasteNotice';
            notice.setAttribute('role', 'alert');
            notice.setAttribute('aria-live', 'assertive');
            notice.className = 'cppq-paste-notice';
            document.body.appendChild(notice);
        }
        notice.textContent = message;
        notice.classList.toggle('cppq-paste-notice-error', isError);
        notice.classList.add('cppq-paste-notice-visible');
        window.clearTimeout(notice.dismissTimer);
        notice.dismissTimer = window.setTimeout(() => {
            notice.classList.remove('cppq-paste-notice-visible');
        }, 5000);
    }

    function updatePlayerXpDisplay(xp) {
        const xpDisplay = document.getElementById('navXpTotal');
        if (xpDisplay) xpDisplay.textContent = `⚡ ${xp} XP · Perks`;
    }
    window.cppqUpdatePlayerXp = updatePlayerXpDisplay;

    document.addEventListener('paste', event => {
        const target = event.target instanceof Element
            ? event.target.closest('[data-no-paste="true"]')
            : null;
        if (!target) return;

        event.preventDefault();
        showPasteNotice('Pasting answers is prohibited. Recording the 10 XP penalty…');

        fetch('/cpp_learning/paste_penalty.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                source: target.dataset.pasteSource,
                context_id: target.dataset.pasteContext,
                csrf_token: target.dataset.pasteCsrf
            })
        }).then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The XP penalty could not be recorded.');
            updatePlayerXpDisplay(result.new_xp, result.new_level);
            const penaltyMessage = result.xp_deducted > 0
                ? `${result.xp_deducted} XP deducted.`
                : 'Your XP is already at 0, so no additional XP was deducted.';
            showPasteNotice(`Pasting answers is prohibited. ${penaltyMessage}`);
        }).catch(error => {
            showPasteNotice(`The paste was blocked, but the penalty could not be confirmed: ${error.message}`, true);
        });
    }, true);

    window.toggleGameEffects = () => {
        window.cppqSetEffectsMuted(localStorage.getItem('cppq_muted') !== '1');
    };

    function setMusicEnabled(enabled) {
        if (!enabled) {
            localStorage.setItem('cppq_music', '0');
            if (musicTimer !== null) window.clearInterval(musicTimer);
            musicTimer = null;
            if (musicContext) {
                musicContext.close();
                musicContext = null;
            }
            updateSettingToggles('music', false);
            return true;
        }

        if (musicTimer !== null) {
            updateSettingToggles('music', true);
            return true;
        }

        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) {
            localStorage.setItem('cppq_music', '0');
            updateSettingToggles('music', false);
            return false;
        }

        musicContext = new AudioContext();
        musicContext.resume();
        localStorage.setItem('cppq_music', '1');
        updateSettingToggles('music', true);
        playNote();
        musicTimer = window.setInterval(playNote, 360);
        return true;
    }

    function playNote() {
        if (!musicContext) return;
        const oscillator = musicContext.createOscillator();
        const gain = musicContext.createGain();
        oscillator.type = 'square';
        oscillator.frequency.value = notes[musicNote++ % notes.length];
        gain.gain.setValueAtTime(0.018, musicContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, musicContext.currentTime + 0.22);
        oscillator.connect(gain);
        gain.connect(musicContext.destination);
        oscillator.start();
        oscillator.stop(musicContext.currentTime + 0.23);
    };

    window.toggleGameMusic = () => {
        return setMusicEnabled(musicTimer === null);
    };

    window.cppqSetMusicEnabled = setMusicEnabled;
    window.cppqSetReducedMotion = enabled => {
        localStorage.setItem('cppq_reduced_motion', enabled ? '1' : '0');
        document.documentElement.classList.toggle('cppq-reduced-motion', enabled);
        updateSettingToggles('motion', enabled);
    };
    window.cppqSetTheme = darkMode => {
        const theme = darkMode ? 'dark' : 'light';
        document.documentElement.dataset.theme = theme;
        localStorage.setItem('cppq_theme', theme);
        updateSettingToggles('theme', darkMode);
    };

    const xpButton = document.getElementById('navXpTotal');
    const xpDialog = document.getElementById('xpRewardsDialog');
    const xpClose = document.getElementById('xpRewardsClose');
    if (xpButton && xpDialog) {
        xpButton.addEventListener('click', () => {
            xpDialog.showModal();
            xpButton.setAttribute('aria-expanded', 'true');
        });
        xpClose.addEventListener('click', () => xpDialog.close());
        xpDialog.addEventListener('close', () => xpButton.setAttribute('aria-expanded', 'false'));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && xpDialog.open) {
                xpDialog.close();
            }
        });
        xpDialog.addEventListener('click', event => {
            if (event.target === xpDialog) xpDialog.close();
        });
    }

    const badgesButtons = document.querySelectorAll('[aria-controls="playerBadgesDialog"]');
    const badgesDialog = document.getElementById('playerBadgesDialog');
    const badgesClose = document.getElementById('playerBadgesClose');
    if (badgesButtons.length && badgesDialog && badgesClose) {
        badgesButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (!badgesDialog.open) badgesDialog.showModal();
                badgesButtons.forEach(trigger => trigger.setAttribute('aria-expanded', trigger === button ? 'true' : 'false'));
            });
        });
        badgesClose.addEventListener('click', () => badgesDialog.close());
        badgesDialog.addEventListener('close', () => {
            badgesButtons.forEach(button => button.setAttribute('aria-expanded', 'false'));
        });
        badgesDialog.addEventListener('click', event => {
            if (event.target === badgesDialog) badgesDialog.close();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && badgesDialog.open) {
                badgesDialog.close();
            }
        });
    }

    window.cppqSetEffectsMuted(localStorage.getItem('cppq_muted') === '1');
    updateSettingToggles('music', localStorage.getItem('cppq_music') === '1');
    updateSettingToggles('theme', document.documentElement.dataset.theme === 'dark');

    const reducedMotion = localStorage.getItem('cppq_reduced_motion') === '1';
    document.documentElement.classList.toggle('cppq-reduced-motion', reducedMotion);
    document.addEventListener('DOMContentLoaded', () => {
        window.cppqSetEffectsMuted(localStorage.getItem('cppq_muted') === '1');
        if (localStorage.getItem('cppq_music') === '1') {
            setMusicEnabled(true);
        } else {
            updateSettingToggles('music', false);
        }
        updateSettingToggles('motion', localStorage.getItem('cppq_reduced_motion') === '1');
    });
})();
</script>
