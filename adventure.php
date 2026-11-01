<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.difficulty, c.xp_reward, c.category,
           GREATEST(COALESCE(up.solved, 0), COALESCE(ap.cleared, 0)) AS cleared
    FROM challenges c
    LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
    LEFT JOIN adventure_progress ap ON ap.challenge_id = c.id AND ap.user_id = ?
    ORDER BY FIELD(c.difficulty, 'beginner', 'intermediate', 'advanced'), c.order_num, c.id
");
$stmt->execute([$user['id'], $user['id']]);
$rows = $stmt->fetchAll();

$challenges = [];
$regionIndex = ['beginner' => 0, 'intermediate' => 0, 'advanced' => 0];
$sequenceOpen = true;
$solvedCount = 0;
foreach ($rows as $row) {
    $difficulty = $row['difficulty'];
    $index = $regionIndex[$difficulty] ?? 0;
    if (!isset($regionIndex[$difficulty])) {
        continue;
    }

    $cleared = (bool)$row['cleared'];
    $solvedCount += $cleared ? 1 : 0;
    $challenges[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'difficulty' => $difficulty,
        'xp' => (int)$row['xp_reward'],
        'category' => $row['category'],
        'cleared' => $cleared,
        'unlocked' => $sequenceOpen,
        'index' => $index,
    ];
    $regionIndex[$difficulty]++;
    $sequenceOpen = $sequenceOpen && $cleared;
}

$avatarSvg = pixelAvatar((int)$user['id'], 64, (int)$user['avatar_id']);
$avatarData = 'data:image/svg+xml;base64,' . base64_encode($avatarSvg);
$challengeJson = json_encode(
    $challenges,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$pageTitle = 'Adventure';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-6xl mx-auto px-4 py-6 sm:py-8">
    <div class="flex flex-wrap items-end gap-4 mb-5">
        <div>
            <div class="text-xs uppercase tracking-[.25em] text-cyan-300 font-bold">CppQuest · Open world</div>
            <h1 class="text-3xl sm:text-4xl font-black text-white mt-1">The Compiler's Frontier</h1>
            <p class="text-slate-400 mt-2">Explore the world, meet the guildkeeper, and find the C++ mission portals.</p>
        </div>
    </div>

    <section class="game-panel rounded-2xl border border-indigo-500/30 p-3 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 px-1 pb-3">
            <div class="flex items-center gap-3">
                <?= pixelAvatar((int)$user['id'], 42, (int)$user['avatar_id']) ?>
                <div>
                    <div class="font-bold text-white"><?= htmlspecialchars($user['display_name']) ?></div>
                    <div class="text-xs text-slate-400">Level <?= (int)$user['level'] ?> explorer</div>
                </div>
            </div>
            <div class="text-sm text-slate-300"><span id="solvedCount"><?= $solvedCount ?></span> / <?= count($challenges) ?> portals cleared</div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-slate-600 bg-slate-950">
            <div id="adventureWorld" role="application" tabindex="0"
                 aria-label="Pixel adventure world. Move with WASD or arrow keys, click to walk, and press E near a character or portal."></div>
            <div class="pointer-events-none absolute left-3 top-3 rounded-lg border border-white/10 bg-slate-950/75 px-3 py-2 text-xs text-slate-200 backdrop-blur">
                <span class="font-bold text-cyan-300">MOVE</span> WASD / arrows
                <span class="mx-1 text-slate-500">·</span>
                <span class="font-bold text-amber-300">INTERACT</span> E
            </div>
            <div id="regionBadge" class="pointer-events-none absolute right-3 top-3 rounded-lg border border-white/10 bg-slate-950/75 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white backdrop-blur">Syntax Grove</div>
            <div id="mapNotice" class="pointer-events-none absolute bottom-3 left-1/2 w-[min(90%,32rem)] -translate-x-1/2 rounded-lg border border-cyan-400/30 bg-slate-950/90 px-4 py-2 text-center text-sm text-cyan-100 opacity-0 transition-opacity" aria-live="polite"></div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div id="interactionTitle" class="font-bold text-white">Explore the frontier</div>
                        <p id="interactionText" class="mt-1 text-sm text-slate-400">Walk around with WASD or the arrow keys. Click anywhere on the map to travel there.</p>
                    </div>
                    <a id="missionButton" href="#" class="hidden shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-500">Enter mission →</a>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-700 bg-slate-950/60 p-3">
                <div class="grid grid-cols-3 gap-1">
                    <span></span><button class="move-key" data-move="up" aria-label="Move up">▲</button><span></span>
                    <button class="move-key" data-move="left" aria-label="Move left">◀</button>
                    <button class="move-key" data-move="down" aria-label="Move down">▼</button>
                    <button class="move-key" data-move="right" aria-label="Move right">▶</button>
                </div>
                <button id="interactButton" type="button" class="rounded-lg border border-amber-500/50 bg-amber-950/50 px-4 py-3 text-sm font-bold text-amber-200 hover:bg-amber-900/60">Interact · E</button>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-400">
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-emerald-400"></i> Cleared portal</span>
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-amber-300"></i> Next mission</span>
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-slate-600"></i> Locked portal</span>
            <span class="ml-auto">Tip: clear missions in order to unlock the next trail.</span>
        </div>
    </section>
</main>

<style>
    #adventureWorld { width:100%; aspect-ratio:16/9; overflow:hidden; touch-action:none; background:#172033; }
    #adventureWorld canvas { display:block; width:100% !important; height:100% !important; image-rendering:pixelated; }
    .move-key { width:2.5rem; height:2.25rem; border:1px solid rgb(71 85 105); border-radius:.5rem; background:rgb(30 41 59); color:white; touch-action:none; }
    .move-key:active { background:rgb(79 70 229); }
    @media (max-width: 640px) {
        #adventureWorld { aspect-ratio:4/3; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/phaser@3.90.0/dist/phaser.min.js"></script>
<script>
(() => {
    const challenges = <?= $challengeJson ?: '[]' ?>;
    const playerName = <?= json_encode($user['display_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const playerAvatar = <?= json_encode($avatarData) ?>;
    const worldWidth = 1800;
    const worldHeight = 1620;
    const tileSize = 24;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const zones = [
        { key:'beginner', name:'Syntax Grove', top:0, base:'#286348', light:'#367953', path:'#a68755', accent:'#72d69a' },
        { key:'intermediate', name:'Looping Dunes', top:540, base:'#8c6437', light:'#a87941', path:'#d7b86f', accent:'#f7d37c' },
        { key:'advanced', name:'Pointer Peaks', top:1080, base:'#384967', light:'#4a5e7d', path:'#8593a6', accent:'#a8d7f0' }
    ];
    const locations = [];
    const activeDirections = new Set();
    let activeScene = null;
    let noticeTimeout;

    challenges.forEach(challenge => {
        const row = Math.floor(challenge.index / 10);
        const column = challenge.index % 10;
        const zone = zones.find(item => item.key === challenge.difficulty);
        const globalRow = zones.indexOf(zone) * 3 + row;
        const location = {
            x:190 + (globalRow % 2 ? 9 - column : column) * 155,
            y:zone.top + 135 + row * 135,
            challenge
        };
        locations.push(location);
    });

    const keeper = { x:105, y:245, keeper:true };
    const npcs = [
        {
            x:900, y:202, name:'Sage Syntax', role:'Syntax mentor', color:0x68d391,
            lines:[
                'Quick tip: = assigns a value; == checks whether two values are equal.',
                'A semicolon ends most C++ statements. Braces { } group a block of code.',
                'Use std::cout << value << "\\n"; to print a value followed by a new line.'
            ]
        },
        {
            x:900, y:337, name:'Bugsy the Rival', role:'Friendly rival', color:0xfb7185,
            lines:[
                'Heh, that semicolon tried to escape you. Check the compiler line number and catch it!',
                'Your code almost confused me. Remember: a loop needs a condition that eventually becomes false!',
                'I would brag, but your debugging is getting better. Read the error message from the first line.'
            ]
        },
        {
            x:900, y:742, name:'Loopa Trailguide', role:'Loop mentor', color:0xfbbf24,
            lines:[
                'A for loop is great when you know the count: setup, condition, then update.',
                'Watch your loop bounds: an array of size n has valid indexes from 0 to n - 1.',
                'If a loop never ends, check that its update moves the condition toward false.'
            ]
        },
        {
            x:900, y:877, name:'Count von Bug', role:'Friendly rival', color:0xfb7185,
            lines:[
                'I bet you forgot the update! A loop counter has to change or we will be here all day.',
                'Nice try, coder. Trace one iteration at a time; the bug usually reveals itself.',
                'Your array loop is close! Start at index 0, not 1. I learned that the embarrassing way.'
            ]
        },
        {
            x:900, y:1282, name:'Pointer Sage', role:'Pointer mentor', color:0x7dd3fc,
            lines:[
                'A pointer stores an address. Use &value to get an address and *pointer to access its value.',
                'Before dereferencing a pointer, make sure it points to a valid object.',
                'nullptr means a pointer intentionally points to nothing. Check before using *pointer.'
            ]
        },
        {
            x:900, y:1417, name:'Segfault Sam', role:'Friendly rival', color:0xfb7185,
            lines:[
                'Your pointer is wandering off! Check for nullptr before dereferencing it.',
                'I once chased a bad address for an hour. Follow the pointer one step at a time!',
                'That code nearly segfaulted. Keep the pointed-to object alive as long as you use it!'
            ]
        }
    ];

    function showText(title, text, href = null) {
        document.getElementById('interactionTitle').textContent = title;
        document.getElementById('interactionText').textContent = text;
        const button = document.getElementById('missionButton');
        if (href) {
            button.href = href;
            button.classList.remove('hidden');
        } else {
            button.classList.add('hidden');
        }
    }
    function showNotice(text) {
        const element = document.getElementById('mapNotice');
        element.textContent = text;
        element.classList.remove('opacity-0');
        clearTimeout(noticeTimeout);
        noticeTimeout = setTimeout(() => element.classList.add('opacity-0'), 2200);
    }
    document.querySelectorAll('.move-key').forEach(button => {
        const direction = button.dataset.move;
        button.addEventListener('pointerdown', event => {
            event.preventDefault();
            button.setPointerCapture(event.pointerId);
            activeDirections.add(direction);
            if (activeScene) activeScene.walkingTarget = null;
        });
        const release = () => activeDirections.delete(direction);
        button.addEventListener('pointerup', release);
        button.addEventListener('pointercancel', release);
        button.addEventListener('lostpointercapture', release);
    });
    class FrontierScene extends Phaser.Scene {
        constructor() {
            super('frontier');
            this.playerSpeed = 205;
            this.walkingTarget = null;
            this.lastNearby = null;
            this.portals = [];
            this.npcActors = [];
        }

        preload() {
            this.load.image('player-avatar', playerAvatar);
        }

        create() {
            activeScene = this;
            this.physics.world.setBounds(0, 0, worldWidth, worldHeight);
            this.cameras.main.setBounds(0, 0, worldWidth, worldHeight);
            this.drawWorld();
            this.createKeeper();
            this.createNpcs();
            this.createPortals();
            this.createPlayer();

            this.keys = this.input.keyboard.addKeys({
                up:'W', left:'A', down:'S', right:'D',
                arrowUp:'UP', arrowLeft:'LEFT', arrowDown:'DOWN', arrowRight:'RIGHT',
                interact:'E', enter:'ENTER'
            });
            this.input.keyboard.addCapture(['UP', 'DOWN', 'LEFT', 'RIGHT', 'SPACE']);
            this.keys.interact.on('down', () => this.interact());
            this.keys.enter.on('down', () => this.interact());
            this.input.on('pointerdown', pointer => {
                const point = this.cameras.main.getWorldPoint(pointer.x, pointer.y);
                this.walkingTarget = {
                    x:Phaser.Math.Clamp(point.x, 30, worldWidth - 30),
                    y:Phaser.Math.Clamp(point.y, 30, worldHeight - 30)
                };
                this.game.canvas.focus();
            });

            const activeMission = challenges.find(item => item.unlocked && !item.cleared);
            if (activeMission) {
                showText('Your next mission', `${activeMission.title} is waiting in ${activeMission.difficulty}. Explore to its glowing portal and interact to enter.`);
            } else if (challenges.length) {
                showText('Frontier complete!', 'Every mission portal has been cleared. Celebrate your progress or explore the world again.');
            } else {
                showText('No missions yet', 'The world is ready, but it needs challenges. Ask an administrator to seed the challenge database.');
            }
        }

        drawWorld() {
            const ground = this.add.graphics().setDepth(0);
            ground.fillStyle(0x172033, 1).fillRect(0, 0, worldWidth, worldHeight);
            for (const zone of zones) {
                ground.fillStyle(Phaser.Display.Color.HexStringToColor(zone.base).color, 1);
                ground.fillRect(0, zone.top, worldWidth, 540);
                const baseColor = Phaser.Display.Color.HexStringToColor(zone.base).color;
                const lightColor = Phaser.Display.Color.HexStringToColor(zone.light).color;
                for (let y = zone.top; y < zone.top + 540; y += tileSize) {
                    for (let x = 0; x < worldWidth; x += tileSize) {
                        const cellX = Math.floor(x / tileSize);
                        const cellY = Math.floor(y / tileSize);
                        const variation = (cellX * 13 + cellY * 7 + zone.top) % 7;
                        ground.fillStyle(variation < 2 ? lightColor : baseColor, variation < 2 ? .14 : .055);
                        ground.fillRect(x, y, tileSize, tileSize);
                        if (variation === 1 || variation === 4) {
                            ground.fillStyle(lightColor, .24);
                            ground.fillRect(x + 5 + (cellX % 9), y + 5 + (cellY % 7), 2, 2);
                        }
                    }
                }

                const pathColor = Phaser.Display.Color.HexStringToColor(zone.path).color;
                for (let row = 0; row < 3; row++) {
                    const roadY = zone.top + 135 + row * 135;
                    const laneStartX = 190;
                    const laneWidth = 1395;
                    ground.fillStyle(0x101827, .42);
                    ground.fillRoundedRect(laneStartX - 28, roadY - 24, laneWidth + 56, 54, 18);
                    ground.fillStyle(zone.key === 'beginner' ? 0x614d39 : zone.key === 'intermediate' ? 0x795b36 : 0x59677d, 1);
                    ground.fillRoundedRect(laneStartX - 24, roadY - 21, laneWidth + 48, 48, 17);
                    ground.fillStyle(pathColor, 1);
                    ground.fillRoundedRect(laneStartX - 20, roadY - 18, laneWidth + 40, 36, 14);
                    ground.lineStyle(2, 0xfff3ce, .2);
                    ground.strokeRoundedRect(laneStartX - 14, roadY - 14, laneWidth + 28, 28, 11);
                    ground.fillStyle(0x574737, .28);
                    ground.fillRoundedRect(laneStartX - 12, roadY - 3, laneWidth + 24, 6, 3);
                    ground.fillStyle(0xfff3ce, .34);
                    for (let x = laneStartX + 8; x < laneStartX + laneWidth - 20; x += 92) {
                        ground.fillRoundedRect(x, roadY - 2, 30, 4, 2);
                    }
                }

                this.drawRegionLandmarks(zone, ground);
                const sign = this.add.graphics().setDepth(2);
                sign.fillStyle(0x0b1220, .74).fillRoundedRect(620, zone.top + 15, 270, 56, 10);
                sign.lineStyle(1, Phaser.Display.Color.HexStringToColor(zone.accent).color, .7);
                sign.strokeRoundedRect(620, zone.top + 15, 270, 56, 10);
                sign.fillStyle(Phaser.Display.Color.HexStringToColor(zone.accent).color, 1);
                sign.fillRoundedRect(630, zone.top + 26, 4, 34, 2);
                this.add.text(644, zone.top + 23, zone.name.toUpperCase(), {
                    fontFamily:'system-ui, sans-serif', fontSize:'16px', fontStyle:'bold',
                    color:'#ffffff', stroke:'#101827', strokeThickness:4
                }).setDepth(3);
                this.add.text(645, zone.top + 45, 'REGION  ·  MISSION DISTRICT', {
                    fontFamily:'system-ui, sans-serif', fontSize:'9px', fontStyle:'bold',
                    color:'#dbeafe', stroke:'#101827', strokeThickness:3
                }).setDepth(3);
                this.drawDecorations(zone);
            }
            this.drawTrailConnectors(ground);
        }

        drawTrailConnectors(ground) {
            const trailColor = 0xcbb17a;
            const transitionCount = zones.length * 3 - 1;
            for (let row = 0; row < transitionCount; row++) {
                const currentZone = zones[Math.floor(row / 3)];
                const nextZone = zones[Math.floor((row + 1) / 3)];
                const currentLane = row % 3;
                const nextLane = (row + 1) % 3;
                const fromY = currentZone.top + 135 + currentLane * 135;
                const toY = nextZone.top + 135 + nextLane * 135;
                const connectorX = row % 2 === 0 ? 1585 : 190;
                const bend = row % 2 === 0 ? 1710 : 65;
                const controlY = (fromY + toY) / 2;
                const points = [];
                const segments = 36;
                for (let step = 0; step <= segments; step++) {
                    const t = step / segments;
                    const inverse = 1 - t;
                    points.push({
                        x: inverse * inverse * connectorX + 2 * inverse * t * bend + t * t * connectorX,
                        y: inverse * inverse * fromY + 2 * inverse * t * controlY + t * t * toY
                    });
                }

                const drawStroke = (width, color, alpha) => {
                    ground.lineStyle(width, color, alpha);
                    ground.beginPath();
                    ground.moveTo(points[0].x, points[0].y);
                    for (let i = 1; i < points.length; i++) ground.lineTo(points[i].x, points[i].y);
                    ground.strokePath();
                };
                const borderColor = currentZone.key === 'beginner' ? 0x614d39
                    : currentZone.key === 'intermediate' ? 0x795b36 : 0x59677d;
                drawStroke(56, 0x101827, .52);
                drawStroke(48, borderColor, 1);
                drawStroke(36, trailColor, 1);
                drawStroke(28, currentZone.key === 'beginner' ? 0xb99a67 : currentZone.key === 'intermediate' ? 0xe7ca82 : 0xaab7c8, 1);
                drawStroke(2, 0xfff3ce, .36);

                ground.fillStyle(0xfff3ce, .64);
                for (let i = 3; i < points.length - 2; i += 5) {
                    ground.fillCircle(points[i].x, points[i].y, 2);
                }
            }
        }

        drawRegionLandmarks(zone, ground) {
            const accent = Phaser.Display.Color.HexStringToColor(zone.accent).color;
            const art = this.add.graphics().setDepth(1);
            if (zone.key === 'beginner') {
                art.fillStyle(0x163d31, .56)
                    .fillTriangle(1440, zone.top + 82, 1532, zone.top + 6, 1624, zone.top + 82)
                    .fillTriangle(1510, zone.top + 82, 1612, zone.top - 2, 1714, zone.top + 82);
                art.fillStyle(0x527b5f, .58)
                    .fillTriangle(1496, zone.top + 54, 1532, zone.top + 6, 1568, zone.top + 54)
                    .fillTriangle(1577, zone.top + 51, 1612, zone.top - 2, 1647, zone.top + 51);
                art.fillStyle(0x20533d, .72)
                    .fillEllipse(120, zone.top + 385, 180, 34)
                    .fillEllipse(1700, zone.top + 385, 180, 34);
                ground.fillStyle(accent, .3);
                for (let x = 118; x < 1800; x += 182) {
                    ground.fillCircle(x, zone.top + 388, 2);
                }
            } else if (zone.key === 'intermediate') {
                art.fillStyle(0x725335, .34)
                    .fillEllipse(1520, zone.top + 32, 500, 94)
                    .fillEllipse(1660, zone.top + 370, 560, 110)
                    .fillEllipse(120, zone.top + 372, 350, 78);
                art.fillStyle(0xf0cd7a, .34)
                    .fillEllipse(1515, zone.top + 21, 440, 46)
                    .fillEllipse(1650, zone.top + 357, 490, 44);
                art.fillStyle(0x304f43, .76)
                    .fillEllipse(1540, zone.top + 350, 84, 22)
                    .fillEllipse(1540, zone.top + 350, 47, 13);
                art.fillStyle(0x4a8a68, .9)
                    .fillRect(1537, zone.top + 319, 6, 33)
                    .fillRect(1524, zone.top + 326, 7, 5)
                    .fillRect(1549, zone.top + 334, 7, 5);
            } else {
                art.fillStyle(0x293950, .82)
                    .fillTriangle(1280, zone.top + 84, 1400, zone.top - 10, 1520, zone.top + 84)
                    .fillTriangle(1430, zone.top + 84, 1570, zone.top - 20, 1710, zone.top + 84)
                    .fillTriangle(1570, zone.top + 84, 1688, zone.top - 4, 1800, zone.top + 84);
                art.fillStyle(0xb9d8e8, .86)
                    .fillTriangle(1368, zone.top + 16, 1400, zone.top - 10, 1432, zone.top + 16)
                    .fillTriangle(1532, zone.top + 9, 1570, zone.top - 20, 1608, zone.top + 9)
                    .fillTriangle(1658, zone.top + 17, 1688, zone.top - 4, 1718, zone.top + 17);
                art.fillStyle(0x1d2b40, .72)
                    .fillTriangle(1260, zone.top + 382, 1370, zone.top + 292, 1480, zone.top + 382)
                    .fillTriangle(1500, zone.top + 382, 1625, zone.top + 286, 1750, zone.top + 382);
                art.fillStyle(accent, .48);
                for (let i = 0; i < 7; i++) {
                    const x = 1240 + i * 75;
                    const y = zone.top + 346 - (i % 3) * 9;
                    art.fillPoints([
                        { x, y:y - 6 }, { x:x + 4, y }, { x, y:y + 6 }, { x:x - 4, y }
                    ], true);
                }
            }
        }

        drawDecorations(zone) {
            const art = this.add.graphics().setDepth(3);
            for (let i = 0; i < 44; i++) {
                const seed = i * 127 + zone.top * 3;
                const x = 64 + (seed * 37 % 1650);
                const y = zone.top + 76 + (seed * 19 % 290);
                const nearRoad = [135, 270, 405].some(road => Math.abs(y - (zone.top + road)) < 34);
                if (nearRoad || x < 235 || x > 1660) continue;

                if (zone.key === 'beginner') {
                    const canopy = i % 2 ? 0x245d43 : 0x1c503b;
                    art.fillStyle(0x153b2d, .52).fillEllipse(x, y + 15, 48, 13);
                    art.fillStyle(0x62442f, 1).fillRect(x - 5, y - 4, 10, 22);
                    art.fillStyle(0x183f31, 1).fillCircle(x, y - 12, 22);
                    art.fillStyle(canopy, 1).fillCircle(x - 9, y - 17, 14).fillCircle(x + 10, y - 16, 13);
                    art.fillStyle(0x4d9b66, .95).fillCircle(x - 7, y - 22, 8).fillCircle(x + 7, y - 11, 7);
                    art.fillStyle(0x9be276, .7).fillCircle(x - 9, y - 24, 3);
                    if (i % 3 === 0) {
                        art.fillStyle(0xafffca, .8).fillCircle(x + 23, y - 8, 2);
                        art.fillStyle(0xafffca, .24).fillCircle(x + 23, y - 8, 7);
                    }
                } else if (zone.key === 'intermediate') {
                    art.fillStyle(0x60462d, .45).fillEllipse(x, y + 14, 40, 10);
                    art.fillStyle(0x7d5732, 1).fillRect(x - 2, y - 9, 5, 22);
                    art.fillStyle(0xb98545, 1).fillRect(x - 10, y - 10, 21, 14);
                    art.fillStyle(0xe8c472, 1).fillRect(x - 7, y - 17, 14, 9);
                    art.fillStyle(0xffe6a3, .92).fillRect(x - 3, y - 15, 5, 5);
                    if (i % 3 === 0) {
                        art.fillStyle(0x316c55, 1).fillRect(x + 20, y - 15, 7, 28);
                        art.fillStyle(0x316c55, 1).fillRect(x + 13, y - 8, 7, 5).fillRect(x + 27, y - 2, 7, 5);
                        art.fillStyle(0x59a676, 1).fillRect(x + 22, y - 16, 3, 9);
                    } else {
                        art.fillStyle(0xf6d889, .8).fillEllipse(x - 24, y + 5, 15, 5);
                        art.fillStyle(0xf5e7bb, .65).fillCircle(x + 25, y + 4, 3);
                    }
                } else {
                    art.fillStyle(0x172033, .45).fillEllipse(x, y + 14, 44, 11);
                    art.fillStyle(0x586982, 1).fillTriangle(x - 22, y + 7, x - 6, y - 22, x + 12, y + 7);
                    art.fillStyle(0x8093aa, 1).fillTriangle(x - 12, y + 7, x + 6, y - 27, x + 23, y + 7);
                    art.fillStyle(0xd7efff, 1).fillTriangle(x - 2, y - 12, x + 6, y - 27, x + 13, y - 12);
                    art.fillStyle(0x9bdcf5, .82).fillPoints([
                        { x:x - 19, y:y - 5 }, { x:x - 15, y:y - 1 },
                        { x:x - 19, y:y + 3 }, { x:x - 23, y:y - 1 }
                    ], true);
                    if (i % 3 === 0) {
                        art.fillStyle(0x607a99, 1).fillRect(x - 24, y + 7, 48, 5);
                        art.fillStyle(0xb9e8f7, .8).fillRect(x - 17, y + 8, 8, 2).fillRect(x + 3, y + 8, 12, 2);
                    }
                }
            }
        }

        createKeeper() {
            const shadow = this.add.ellipse(keeper.x, keeper.y + 14, 38, 14, 0x080b1b, .45).setDepth(8);
            const sprite = this.add.container(keeper.x, keeper.y).setDepth(9);
            const art = this.add.graphics();
            art.fillStyle(0x382518, 1).fillRect(-12, -32, 24, 7);
            art.fillStyle(0x68411f, 1).fillRect(-9, -27, 18, 5);
            art.fillStyle(0xf5c27a, 1).fillRoundedRect(-8, -24, 16, 14, 3);
            art.fillStyle(0xb77935, 1).fillRoundedRect(-11, -9, 22, 22, 4);
            art.fillStyle(0x17202f, 1).fillRect(-5, -19, 3, 3).fillRect(3, -19, 3, 3);
            art.fillStyle(0x62d7c7, 1).fillRect(-14, 1, 28, 4);
            sprite.add(art);
            const label = this.add.text(keeper.x, keeper.y + 31, 'BYTE · GUILDKEEPER', {
                fontFamily:'system-ui, sans-serif', fontSize:'11px', fontStyle:'bold',
                color:'#ffffff', stroke:'#080b1b', strokeThickness:4
            }).setOrigin(.5).setDepth(10);
            this.keeperVisual = { sprite, shadow, label };
            this.tweens.add({ targets:sprite, y:keeper.y - 2, duration:800, yoyo:true, repeat:-1, ease:'Sine.easeInOut' });
        }

        createNpcs() {
            for (const npc of npcs) {
                npc.lineIndex = 0;
                const shadow = this.add.ellipse(npc.x, npc.y + 13, 30, 10, 0x080b1b, .45).setDepth(8);
                const sprite = this.add.container(npc.x, npc.y).setDepth(9);
                const art = this.add.graphics();
                art.fillStyle(0x172033, .7).fillRoundedRect(-13, -12, 26, 27, 4);
                art.fillStyle(npc.color, 1).fillRoundedRect(-10, -11, 20, 20, 3);
                art.fillStyle(0xf3c98b, 1).fillRoundedRect(-7, -25, 14, 14, 3);
                art.fillStyle(0x352a37, 1).fillRect(-8, -27, 16, 5).fillRect(-10, -23, 4, 7);
                art.fillStyle(0x172033, 1).fillRect(-4, -20, 2, 2).fillRect(3, -20, 2, 2);
                art.fillStyle(0xe2e8f0, .95).fillRoundedRect(-10, 13, 8, 5, 2).fillRoundedRect(2, 13, 8, 5, 2);
                art.fillStyle(0xffffff, .52).fillRect(-2, -8, 4, 13);
                sprite.add(art);
                const marker = this.add.circle(npc.x, npc.y - 38, 4, npc.color, 1)
                    .setStrokeStyle(2, 0x0b1220, 1).setDepth(11);
                const label = this.add.text(npc.x, npc.y + 23, npc.name, {
                    fontFamily:'system-ui, sans-serif', fontSize:'10px', fontStyle:'bold',
                    color:'#ffffff', stroke:'#080b1b', strokeThickness:4
                }).setOrigin(.5).setDepth(10);
                const role = this.add.text(npc.x, npc.y + 35, npc.role.toUpperCase(), {
                    fontFamily:'system-ui, sans-serif', fontSize:'8px', fontStyle:'bold',
                    color:npc.role === 'Friendly rival' ? '#fda4af' : '#bae6fd',
                    stroke:'#080b1b', strokeThickness:3
                }).setOrigin(.5).setDepth(10);
                npc.visual = { sprite, shadow, marker, label, role };
                this.npcActors.push(npc);
                if (!reducedMotion) {
                    this.tweens.add({ targets:sprite, y:npc.y - 2, duration:850 + (npc.y % 4) * 90, yoyo:true, repeat:-1, ease:'Sine.easeInOut' });
                    this.tweens.add({ targets:marker, y:npc.y - 43, alpha:.45, duration:650, yoyo:true, repeat:-1, ease:'Sine.easeInOut' });
                }
            }
        }

        createPortals() {
            for (const location of locations) {
                const { challenge, x, y } = location;
                const color = challenge.cleared ? 0x86efac : challenge.unlocked ? 0xffe08a : 0x94a3b8;
                const glowColor = challenge.cleared ? 0x34d399 : challenge.unlocked ? 0xfbbf24 : 0x64748b;
                const group = this.add.container(x, y).setDepth(12);
                const lit = challenge.unlocked || challenge.cleared;
                const portalColor = lit ? glowColor : 0x334155;
                const shadow = this.add.ellipse(0, 17, 58, 18, 0x080b1b, .48);
                const halo = this.add.ellipse(0, -10, 78, 88, glowColor, lit ? .12 : .035);
                const outerFrame = this.add.ellipse(0, -10, 54, 76, portalColor, lit ? .32 : .18)
                    .setStrokeStyle(3, color, lit ? .98 : .55);
                const innerFrame = this.add.ellipse(0, -10, 44, 66, 0x101827, .96)
                    .setStrokeStyle(2, color, lit ? .72 : .38);
                const aperture = this.add.ellipse(0, -10, 34, 56, lit ? 0x071827 : 0x111827, 1);
                const vortex = this.add.ellipse(0, -10, 25, 45, lit ? glowColor : 0x475569, lit ? .34 : .12)
                    .setStrokeStyle(2, color, lit ? .92 : .4);
                const vortexCore = this.add.ellipse(0, -10, 15, 34, lit ? 0x081321 : 0x182233, .94)
                    .setStrokeStyle(1, color, lit ? .72 : .3);
                const rift = this.add.ellipse(0, -10, 5, 28, color, lit ? .9 : .3);
                const leftShard = this.add.polygon(-24, -10, [0,-28,8,-17,5,8,0,27,-4,9,-3,-12], color, lit ? .94 : .45);
                const rightShard = this.add.polygon(24, -10, [0,-27,4,-10,3,11,-1,28,-7,11,-5,-12], color, lit ? .78 : .35);
                const pad = this.add.ellipse(0, 13, 54, 20, 0x172033, 1).setStrokeStyle(2, color, .86);
                const padLight = this.add.ellipse(0, 13, 38, 9, glowColor, lit ? .6 : .12);
                const diamond = this.add.polygon(0, -10, [0,-7,6,0,0,7,-6,0], color, 1);
                const icon = this.add.text(0, 0, challenge.cleared ? '✓' : challenge.unlocked ? '✦' : '×', {
                    fontFamily:'system-ui, sans-serif', fontSize:'11px', fontStyle:'bold',
                    color:challenge.unlocked || challenge.cleared ? '#0f172a' : '#e2e8f0'
                }).setOrigin(.5);
                const number = this.add.text(-56, 38, String(challenge.index + 1).padStart(2, '0'), {
                    fontFamily:'ui-monospace, monospace', fontSize:'8px', fontStyle:'bold', color:'#cbd5e1'
                }).setOrigin(.5);
                const labelBg = this.add.rectangle(0, 38, 146, 21, 0x0b1220, .9)
                    .setStrokeStyle(1, challenge.cleared ? 0x34d399 : challenge.unlocked ? 0xfbbf24 : 0x64748b, .8);
                const title = this.add.text(8, 38,
                    challenge.title.length > 17 ? challenge.title.slice(0, 15) + '…' : challenge.title,
                    { fontFamily:'system-ui, sans-serif', fontSize:'12px', fontStyle:'bold',
                        color:'#ffffff', stroke:'#0b1220', strokeThickness:3 }
                ).setOrigin(.5);
                const nextQuest = challenge.unlocked && !challenge.cleared
                    ? this.add.text(0, -40, 'NEXT QUEST ↓', {
                        fontFamily:'system-ui, sans-serif', fontSize:'10px', fontStyle:'bold',
                        color:'#fef3c7', backgroundColor:'#78350f', padding:{ x:6, y:3 }
                    }).setOrigin(.5)
                    : null;
                group.add([
                    shadow, halo, outerFrame, innerFrame, aperture, vortex, vortexCore, rift,
                    leftShard, rightShard, pad, padLight, diamond, icon, labelBg, number, title
                ]);
                if (nextQuest) group.add(nextQuest);
                if (!reducedMotion) {
                    this.tweens.add({
                        targets:halo, scaleX:1.16, scaleY:1.2, alpha:lit ? .045 : .015,
                        duration:1100 + (challenge.index % 5) * 100, yoyo:true, repeat:-1, ease:'Sine.easeInOut'
                    });
                    this.tweens.add({
                        targets:vortex, angle:360, duration:lit ? 5200 : 11000, repeat:-1, ease:'Linear'
                    });
                    this.tweens.add({
                        targets:vortexCore, angle:-360, duration:lit ? 7600 : 15000, repeat:-1, ease:'Linear'
                    });
                    this.tweens.add({
                        targets:diamond, scale:lit ? 1.3 : 1.08, alpha:lit ? .5 : .25,
                        duration:750, yoyo:true, repeat:-1, ease:'Sine.easeInOut'
                    });
                    if (lit) {
                        const motes = this.add.container(0, -10);
                        motes.add([
                            this.add.circle(0, -35, 2, 0xffffff, .95),
                            this.add.circle(18, 2, 2, color, .9),
                            this.add.circle(-15, 14, 1.5, 0xffffff, .8)
                        ]);
                        group.add(motes);
                        this.tweens.add({
                            targets:motes, angle:360, duration:3600 + (challenge.index % 4) * 300,
                            repeat:-1, ease:'Linear'
                        });
                    }
                }
                location.gameObject = group;
                this.portals.push(location);
            }
        }

        createPlayer() {
            this.player = this.physics.add.sprite(130, 105, 'player-avatar');
            this.player.setDisplaySize(54, 54).setDepth(30).setCollideWorldBounds(true);
            this.player.body.setSize(25, 30).setOffset(19, 25);
            this.playerShadow = this.add.ellipse(this.player.x, this.player.y + 17, 34, 12, 0x080b1b, .45).setDepth(29);
            this.playerLabel = this.add.text(this.player.x, this.player.y - 37, playerName, {
                fontFamily:'system-ui, sans-serif', fontSize:'11px', fontStyle:'bold',
                color:'#ffffff', stroke:'#080b1b', strokeThickness:4
            }).setOrigin(.5).setDepth(31);
            this.prompt = this.add.container(this.player.x, this.player.y - 60).setDepth(32);
            this.prompt.add([
                this.add.rectangle(0, 0, 26, 21, 0x080b1b, .92).setStrokeStyle(1, 0xfde68a, .8),
                this.add.text(0, 0, 'E', { fontFamily:'system-ui, sans-serif', fontSize:'12px', fontStyle:'bold', color:'#fde68a' }).setOrigin(.5)
            ]);
            this.prompt.setVisible(false);
            this.cameras.main.startFollow(this.player, true, .12, .12);
            this.game.canvas.setAttribute('tabindex', '0');
            this.game.canvas.setAttribute('aria-label', 'Pixel adventure world. Move with WASD or arrow keys, click to walk, and press E to interact.');
            this.game.canvas.focus();
        }

        nearestInteraction() {
            let nearest = null;
            let bestDistance = 72;
            const keeperDistance = Phaser.Math.Distance.Between(this.player.x, this.player.y, keeper.x, keeper.y);
            if (keeperDistance < bestDistance) {
                nearest = keeper;
                bestDistance = keeperDistance;
            }
            for (const npc of this.npcActors) {
                const currentDistance = Phaser.Math.Distance.Between(this.player.x, this.player.y, npc.x, npc.y);
                if (currentDistance < bestDistance) {
                    nearest = npc;
                    bestDistance = currentDistance;
                }
            }
            for (const location of this.portals) {
                const currentDistance = Phaser.Math.Distance.Between(this.player.x, this.player.y, location.x, location.y);
                if (currentDistance < bestDistance) {
                    nearest = location;
                    bestDistance = currentDistance;
                }
            }
            return nearest;
        }

        interact() {
            const target = this.nearestInteraction();
            if (!target) {
                showNotice('Move closer to a character or mission portal first.');
                return;
            }
            if (target.keeper) {
                const next = challenges.find(item => item.unlocked && !item.cleared);
                showText('Guildkeeper Byte', next
                    ? `Welcome, ${playerName}! The next mission is "${next.title}". Find its glowing portal and interact.`
                    : 'Every portal is cleared! You have mastered the whole frontier. Visit the leaderboard to see your rank.');
                showNotice(next ? 'Byte: The next mission awaits!' : 'Byte: The frontier is yours!');
                return;
            }
            if (target.lines) {
                const line = target.lines[target.lineIndex];
                target.lineIndex = (target.lineIndex + 1) % target.lines.length;
                showText(`${target.name} · ${target.role}`, line);
                showNotice(`${target.name}: ${line}`);
                return;
            }
            const mission = target.challenge;
            if (!mission.unlocked) {
                showText('Trail blocked', 'Clear the earlier missions to open this portal.');
                showNotice('This portal is sealed. Follow the trail in order.');
                return;
            }
            const label = mission.cleared ? 'Visit cleared portal' : 'Enter portal trial';
            showText(mission.title, `${mission.difficulty[0].toUpperCase() + mission.difficulty.slice(1)} · 3 C++ code-repair puzzles · 35 XP`, `/cpp_learning/adventure_portal.php?id=${mission.id}`);
            showNotice(`${mission.cleared ? 'Portal cleared' : 'Portal trial opened'}: ${mission.title}`);
            document.getElementById('missionButton').textContent = `${label} →`;
        }

        update(time, deltaMs) {
            const delta = Math.min(deltaMs / 1000, .04);
            let dx = 0;
            let dy = 0;
            if (this.keys.left.isDown || this.keys.arrowLeft.isDown || activeDirections.has('left')) dx--;
            if (this.keys.right.isDown || this.keys.arrowRight.isDown || activeDirections.has('right')) dx++;
            if (this.keys.up.isDown || this.keys.arrowUp.isDown || activeDirections.has('up')) dy--;
            if (this.keys.down.isDown || this.keys.arrowDown.isDown || activeDirections.has('down')) dy++;

            if (dx || dy) {
                const length = Math.hypot(dx, dy);
                this.player.body.setVelocity(dx / length * this.playerSpeed, dy / length * this.playerSpeed);
                this.walkingTarget = null;
            } else if (this.walkingTarget) {
                const distance = Phaser.Math.Distance.Between(this.player.x, this.player.y, this.walkingTarget.x, this.walkingTarget.y);
                if (distance < this.playerSpeed * delta) {
                    this.player.setPosition(this.walkingTarget.x, this.walkingTarget.y);
                    this.walkingTarget = null;
                    this.player.body.setVelocity(0, 0);
                } else {
                    const angle = Phaser.Math.Angle.Between(this.player.x, this.player.y, this.walkingTarget.x, this.walkingTarget.y);
                    this.player.body.setVelocity(Math.cos(angle) * this.playerSpeed, Math.sin(angle) * this.playerSpeed);
                }
            } else {
                this.player.body.setVelocity(0, 0);
            }

            const moving = this.player.body.speed > 0;
            const bob = moving ? Math.sin(time / 65) * 2 : Math.sin(time / 420) * 1.1;
            this.playerShadow.setPosition(this.player.x, this.player.y + 17);
            this.player.setAngle(moving ? Math.sin(time / 75) * 2 : 0);
            this.playerLabel.setPosition(this.player.x, this.player.y - 37 + bob);
            this.prompt.setPosition(this.player.x, this.player.y - 60 + bob);
            const nearby = this.nearestInteraction();
            this.prompt.setVisible(Boolean(nearby));
            if (nearby !== this.lastNearby) {
                this.lastNearby = nearby;
                if (nearby && nearby.challenge) {
                    showText(nearby.challenge.title, nearby.challenge.unlocked
                        ? 'Press E or tap Interact to enter this portal and repair its C++ code.'
                        : 'This portal is sealed. Clear earlier missions first.');
                } else if (nearby && nearby.keeper) {
                    showText('Guildkeeper Byte', 'Press E or tap Interact to hear your next quest.');
                } else if (nearby && nearby.lines) {
                    showText(`${nearby.name} · ${nearby.role}`, 'Press E or tap Interact for a C++ tip.');
                }
            }
            const zone = zones.find(item => this.player.y >= item.top && this.player.y < item.top + 540) || zones[2];
            document.getElementById('regionBadge').textContent = zone.name;
        }
    }

    if (!window.Phaser) {
        showText('Game engine unavailable', 'Could not load Phaser. Check your internet connection and reload the adventure.');
        return;
    }

    const activeMission = challenges.find(item => item.unlocked && !item.cleared);
    if (activeMission) {
        showText('Your next mission', `${activeMission.title} is waiting in ${activeMission.difficulty}. Explore to its glowing portal and interact to enter.`);
    } else if (challenges.length) {
        showText('Frontier complete!', 'Every mission portal has been cleared. Celebrate your progress or explore the world again.');
    } else {
        showText('No missions yet', 'The world is ready, but it needs challenges. Ask an administrator to seed the challenge database.');
    }

    new Phaser.Game({
        type:Phaser.AUTO,
        parent:'adventureWorld',
        width:960,
        height:540,
        backgroundColor:'#172033',
        pixelArt:true,
        roundPixels:true,
        antialias:false,
        physics:{ default:'arcade', arcade:{ debug:false } },
        scale:{ mode:Phaser.Scale.RESIZE, width:960, height:540 },
        scene:[FrontierScene]
    });
    document.getElementById('interactButton').addEventListener('click', () => {
        if (activeScene) activeScene.interact();
    });
    window.addEventListener('blur', () => activeDirections.clear());
})();
</script>
