<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();
$userId = (int)$user['id'];
$avatars = avatarOptions();
$error = '';
$saved = isset($_GET['saved']);
$editMode = ($_GET['edit'] ?? '') === '1';

$stmt = $pdo->prepare("
    SELECT u.username, u.created_at, u.xp, u.level, u.streak,
           p.display_name, p.avatar_id, p.bio, p.photo_path, p.cover_path
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    exit('Player profile not found.');
}
$profile['display_name'] = $profile['display_name'] ?: $user['username'];
$profile['avatar_id'] = isset($profile['avatar_id']) ? (int)$profile['avatar_id'] : (int)$user['avatar_id'];
$profile['bio'] = $profile['bio'] ?? '';
$profile['photo_path'] = $profile['photo_path'] ?? null;
$profile['cover_path'] = $profile['cover_path'] ?? null;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND solved = 1");
$stmt->execute([$userId]);
$solvedCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM adventure_progress WHERE user_id = ? AND cleared = 1");
$stmt->execute([$userId]);
$portalCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM coop_room_members WHERE user_id = ?");
$stmt->execute([$userId]);
$coopCount = (int)$stmt->fetchColumn();
$totalChallenges = (int)$pdo->query("SELECT COUNT(*) FROM challenges")->fetchColumn();
$totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users u
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS solved
        FROM user_progress
        WHERE solved = 1
        GROUP BY user_id
    ) progress ON progress.user_id = u.id
    WHERE u.xp > ?
       OR (u.xp = ? AND COALESCE(progress.solved, 0) > ?)
       OR (u.xp = ? AND COALESCE(progress.solved, 0) = ? AND u.id < ?)
");
$stmt->execute([
    (int)$profile['xp'],
    (int)$profile['xp'],
    $solvedCount,
    (int)$profile['xp'],
    $solvedCount,
    $userId,
]);
$rank = (int)$stmt->fetchColumn() + 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editMode = true;
    $displayNameInput = $_POST['display_name'] ?? '';
    $displayName = trim(is_string($displayNameInput) ? $displayNameInput : '');
    $bioInput = $_POST['bio'] ?? '';
    $bio = trim(is_string($bioInput) ? $bioInput : '');
    $avatarId = filter_var($_POST['avatar_id'] ?? null, FILTER_VALIDATE_INT);
    $displayNameLength = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);
    $bioLength = function_exists('mb_strlen') ? mb_strlen($bio) : strlen($bio);
    $photoPath = $profile['photo_path'];
    $coverPath = $profile['cover_path'];
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';
    $removeCover = ($_POST['remove_cover'] ?? '') === '1';
    $photoFile = $_FILES['display_photo'] ?? null;
    $coverFile = $_FILES['cover_photo'] ?? null;

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload this page and try again.';
    } elseif ($displayNameLength < 2 || $displayNameLength > 32) {
        $error = 'Display name must be between 2 and 32 characters.';
    } elseif ($bioLength > 160) {
        $error = 'Bio must be 160 characters or fewer.';
    } elseif ($avatarId === false || !array_key_exists($avatarId, $avatars)) {
        $error = 'Choose one of the available pixel avatars.';
    } elseif ($photoFile && $photoFile['error'] !== UPLOAD_ERR_NO_FILE && $photoFile['error'] !== UPLOAD_ERR_OK) {
        $error = match ($photoFile['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Display photos must be 2 MB or smaller.',
            UPLOAD_ERR_PARTIAL => 'The display photo upload was incomplete. Please try again.',
            default => 'The display photo could not be uploaded. Please try again.',
        };
    } elseif ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
        if ($photoFile['size'] > 2 * 1024 * 1024 || !is_uploaded_file($photoFile['tmp_name'])) {
            $error = 'Choose a valid display photo no larger than 2 MB.';
        } else {
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($photoFile['tmp_name']);
            $imageInfo = getimagesize($photoFile['tmp_name']);
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($allowedTypes[$mimeType]) || !$imageInfo || $imageInfo['mime'] !== $mimeType) {
                $error = 'Use a valid JPG, PNG, or WebP image for your display photo.';
            } elseif ($imageInfo[0] > 3000 || $imageInfo[1] > 3000) {
                $error = 'Display photos must be 3000 pixels or smaller in each dimension.';
            }
        }
    }
    if ($error === '' && $coverFile && $coverFile['error'] !== UPLOAD_ERR_NO_FILE && $coverFile['error'] !== UPLOAD_ERR_OK) {
        $error = match ($coverFile['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Cover photos must be 5 MB or smaller.',
            UPLOAD_ERR_PARTIAL => 'The cover photo upload was incomplete. Please try again.',
            default => 'The cover photo could not be uploaded. Please try again.',
        };
    } elseif ($error === '' && $coverFile && $coverFile['error'] === UPLOAD_ERR_OK) {
        if ($coverFile['size'] > 5 * 1024 * 1024 || !is_uploaded_file($coverFile['tmp_name'])) {
            $error = 'Choose a valid cover photo no larger than 5 MB.';
        } else {
            $coverMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($coverFile['tmp_name']);
            $coverImageInfo = getimagesize($coverFile['tmp_name']);
            $coverAllowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($coverAllowedTypes[$coverMimeType]) || !$coverImageInfo || $coverImageInfo['mime'] !== $coverMimeType) {
                $error = 'Use a valid JPG, PNG, or WebP image for your cover photo.';
            } elseif ($coverImageInfo[0] > 5000 || $coverImageInfo[1] > 5000) {
                $error = 'Cover photos must be 5000 pixels or smaller in each dimension.';
            }
        }
    }

    if ($error === '') {
        if ($removePhoto) {
            $photoPath = null;
        }
        if ($removeCover) {
            $coverPath = null;
        }
        $newUploadFiles = [];
        if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
            $uploadDirectory = __DIR__ . '/uploads/profile-photos';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                $error = 'The photo upload folder could not be created. Please contact the site administrator.';
            } else {
                $extension = $allowedTypes[$mimeType];
                $filename = $userId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($photoFile['tmp_name'], $uploadDirectory . '/' . $filename)) {
                    $error = 'The display photo could not be saved. Please try again.';
                } else {
                    $photoPath = 'uploads/profile-photos/' . $filename;
                    $newUploadFiles[] = $uploadDirectory . '/' . $filename;
                }
            }
        }
        if ($error === '' && $coverFile && $coverFile['error'] === UPLOAD_ERR_OK) {
            $uploadDirectory = __DIR__ . '/uploads/profile-covers';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                $error = 'The cover upload folder could not be created. Please contact the site administrator.';
            } else {
                $extension = $coverAllowedTypes[$coverMimeType];
                $filename = $userId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($coverFile['tmp_name'], $uploadDirectory . '/' . $filename)) {
                    $error = 'The cover photo could not be saved. Please try again.';
                } else {
                    $coverPath = 'uploads/profile-covers/' . $filename;
                    $newUploadFiles[] = $uploadDirectory . '/' . $filename;
                }
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, display_name, avatar_id, bio, photo_path, cover_path)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE display_name = VALUES(display_name),
                                        avatar_id = VALUES(avatar_id),
                                        bio = VALUES(bio),
                                        photo_path = VALUES(photo_path),
                                        cover_path = VALUES(cover_path)
            ");
            $stmt->execute([$userId, $displayName, $avatarId, $bio, $photoPath, $coverPath]);
            refreshSession($pdo, $userId);

            if ($profile['photo_path'] && $profile['photo_path'] !== $photoPath
                && preg_match('/^uploads\\/profile-photos\\/' . $userId . '_[a-f0-9]{32}\\.(jpg|png|webp)$/', $profile['photo_path'])) {
                $oldPhoto = __DIR__ . '/' . $profile['photo_path'];
                if (is_file($oldPhoto)) {
                    unlink($oldPhoto);
                }
            }
            if ($profile['cover_path'] && $profile['cover_path'] !== $coverPath
                && preg_match('/^uploads\\/profile-covers\\/' . $userId . '_[a-f0-9]{32}\\.(jpg|png|webp)$/', $profile['cover_path'])) {
                $oldCover = __DIR__ . '/' . $profile['cover_path'];
                if (is_file($oldCover)) {
                    unlink($oldCover);
                }
            }
            header('Location: /cpp_learning/profile.php?saved=1');
            exit;
        }
        foreach ($newUploadFiles as $newUploadFile) {
            if (is_file($newUploadFile)) {
                unlink($newUploadFile);
            }
        }
    }

    $profile['display_name'] = $displayName;
    $profile['bio'] = $bio;
    $profile['avatar_id'] = $avatarId === false ? $profile['avatar_id'] : $avatarId;
    $profile['photo_path'] = $error === '' ? $photoPath : $profile['photo_path'];
    $profile['cover_path'] = $error === '' ? $coverPath : $profile['cover_path'];
}

$pageTitle = 'Player Profile';
include __DIR__ . '/includes/header.php';
?>

<main class="mx-auto max-w-4xl px-4 py-8">
    <?php if ($editMode): ?>
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Player settings</p>
                <h1 class="mt-1 text-3xl font-black">Edit profile</h1>
            </div>
            <a href="/cpp_learning/profile.php" class="rounded-lg border border-slate-500 bg-slate-800 px-4 py-2 text-sm font-bold text-slate-100 hover:bg-slate-700">Cancel</a>
        </div>

        <form method="POST" enctype="multipart/form-data" class="game-panel space-y-7 rounded-2xl border border-emerald-700 p-5 sm:p-7">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <?php if ($error): ?>
                <div role="alert" class="rounded-xl border border-red-700 bg-red-950 px-4 py-3 text-sm text-red-200"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($saved): ?>
                <div role="status" class="rounded-xl border border-green-700 bg-green-950 px-4 py-3 text-sm text-green-200">Profile saved. Your player card is updated.</div>
            <?php endif; ?>

            <div class="grid gap-6 sm:grid-cols-[1fr_auto]">
                <label class="block">
                    <span class="mb-2 block text-sm font-bold text-slate-200">Display photo</span>
                    <input id="displayPhotoInput" type="file" name="display_photo" accept="image/jpeg,image/png,image/webp"
                           class="block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-3 text-sm text-slate-200 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-700 file:px-3 file:py-2 file:font-bold file:text-white">
                    <span class="mt-2 block text-xs text-slate-400">JPG, PNG, or WebP · max 2 MB · max 3000 × 3000</span>
                    <?php if ($profile['photo_path']): ?>
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-500 bg-slate-800">
                            Remove current display photo
                        </label>
                    <?php endif; ?>
                </label>
                <div class="flex items-center justify-center">
                    <img id="displayPhotoPreview"
                         src="<?= htmlspecialchars($profile['photo_path'] ?: 'data:image/svg+xml;base64,' . base64_encode(pixelAvatar($userId, 120, $profile['avatar_id']))) ?>"
                         alt="Display photo preview" class="h-28 w-28 rounded-2xl border-4 border-emerald-600 bg-slate-950 object-cover">
                </div>
            </div>

            <div class="space-y-3">
                <label class="block">
                    <span class="mb-2 block text-sm font-bold text-slate-200">Player-card cover photo or flag</span>
                    <input id="coverPhotoInput" type="file" name="cover_photo" accept="image/jpeg,image/png,image/webp"
                           class="block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-3 text-sm text-slate-200 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-700 file:px-3 file:py-2 file:font-bold file:text-white">
                    <span class="mt-2 block text-xs text-slate-400">JPG, PNG, or WebP · max 5 MB · max 5000 × 5000. Wide images work best.</span>
                    <?php if ($profile['cover_path']): ?>
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-500 bg-slate-800">
                            Remove current cover photo
                        </label>
                    <?php endif; ?>
                </label>
                <img id="coverPhotoPreview"
                     src="<?= htmlspecialchars($profile['cover_path'] ?: 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 360"><defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="#d1fae5"/><stop offset="1" stop-color="#dbeafe"/></linearGradient></defs><rect width="1200" height="360" fill="url(#g)"/><path d="M0 280 250 120l210 150 220-190 240 200 150-110 130 90v100H0Z" fill="#a7f3d0" opacity=".7"/></svg>')) ?>"
                     alt="<?= $profile['cover_path'] ? 'Player cover photo preview' : 'Default player cover preview' ?>"
                     class="h-36 w-full rounded-xl border border-emerald-700 bg-emerald-50 object-cover sm:h-48">
            </div>

            <label class="block">
                <span class="mb-2 block text-sm font-bold text-slate-200">In-game display name</span>
                <input name="display_name" maxlength="32" minlength="2" required value="<?= htmlspecialchars($profile['display_name']) ?>"
                       class="w-full rounded-lg border border-slate-600 bg-slate-950 px-4 py-3 text-white focus:border-emerald-400 focus:outline-none">
                <span class="mt-2 block text-xs text-slate-400">Your sign-in username stays <?= htmlspecialchars($profile['username']) ?>.</span>
            </label>

            <fieldset>
                <legend class="mb-3 text-sm font-bold text-slate-200">Choose your in-game avatar</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <?php foreach ($avatars as $avatarId => $avatarName): ?>
                        <label class="cursor-pointer">
                            <input class="peer sr-only" type="radio" name="avatar_id" value="<?= $avatarId ?>"
                                   <?= (int)$profile['avatar_id'] === $avatarId ? 'checked' : '' ?>>
                            <span class="flex items-center gap-3 rounded-xl border border-slate-600 bg-slate-900 p-3 transition hover:border-emerald-500 peer-checked:border-emerald-400 peer-checked:bg-emerald-950 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-400">
                                <?= pixelAvatar($userId, 44, $avatarId) ?>
                                <span class="text-xs font-semibold text-slate-200"><?= htmlspecialchars($avatarName) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <label class="block">
                <span class="mb-2 block text-sm font-bold text-slate-200">Player bio <span class="font-normal text-slate-400">· optional</span></span>
                <textarea name="bio" maxlength="160" rows="3" placeholder="Tell other players what you're learning..."
                          class="w-full rounded-lg border border-slate-600 bg-slate-950 px-4 py-3 text-white focus:border-emerald-400 focus:outline-none"><?= htmlspecialchars($profile['bio']) ?></textarea>
                <span class="mt-2 block text-xs text-slate-400">Up to 160 characters.</span>
            </label>

            <div class="flex justify-end">
                <button type="submit" class="game-button rounded-lg border border-emerald-400 bg-emerald-700 px-6 py-3 text-sm font-black text-white hover:bg-emerald-600">Save profile</button>
            </div>
        </form>
    <?php else: ?>
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Adventurer card</p>
                <h1 class="mt-1 text-3xl font-black">Player profile</h1>
            </div>
            <a href="/cpp_learning/profile.php?edit=1" class="rounded-lg border border-amber-400 bg-amber-700 px-4 py-2.5 text-sm font-black text-white shadow-[0_3px_0_#422f13] transition hover:bg-amber-600 active:translate-y-0.5">✎ Edit profile</a>
        </div>

        <?php if ($saved): ?>
            <div role="status" class="mb-5 rounded-xl border border-green-700 bg-green-950 px-4 py-3 text-sm text-green-200">Profile saved. Your player card is updated.</div>
        <?php endif; ?>

        <section class="profile-card game-panel overflow-hidden rounded-2xl border-2 border-slate-700">
            <div class="profile-banner relative overflow-hidden px-5 py-7 sm:px-8 sm:py-10">
                <?php if ($profile['cover_path']): ?>
                    <img class="profile-banner-cover" src="<?= htmlspecialchars($profile['cover_path']) ?>" alt="" aria-hidden="true">
                    <div class="profile-banner-cover-overlay" aria-hidden="true"></div>
                <?php endif; ?>
                <div class="relative flex flex-col items-center gap-5 text-center sm:flex-row sm:items-end sm:text-left">
                    <div class="relative shrink-0">
                        <img src="<?= htmlspecialchars($profile['photo_path'] ?: 'data:image/svg+xml;base64,' . base64_encode(pixelAvatar($userId, 144, $profile['avatar_id']))) ?>"
                             alt="<?= htmlspecialchars($profile['display_name']) ?> display photo"
                             class="profile-photo-frame h-32 w-32 rounded-2xl border-4 border-amber-300 bg-slate-950 object-cover sm:h-36 sm:w-36">
                        <div class="profile-photo-frame absolute -bottom-3 -right-3 rounded-xl border-2 border-amber-200 bg-slate-950 p-1.5">
                            <?= pixelAvatar($userId, 42, $profile['avatar_id']) ?>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 pb-1">
                        <div class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-800 px-3 py-1 text-xs font-black uppercase tracking-wider text-amber-100">
                            <span>Level <?= (int)$profile['level'] ?></span><span aria-hidden="true">·</span><span><?= htmlspecialchars(levelTitle((int)$profile['level'])) ?></span>
                        </div>
                        <h2 class="mt-3 break-words text-3xl font-black text-white sm:text-4xl"><?= htmlspecialchars($profile['display_name']) ?></h2>
                        <p class="mt-1 text-sm font-semibold text-emerald-100">@<?= htmlspecialchars($profile['username']) ?></p>
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <span class="rounded-md border border-slate-600 bg-slate-900 px-3 py-1.5 font-mono text-sm font-bold text-cyan-200">UID · <?= htmlspecialchars(playerUid($userId)) ?></span>
                            <span class="rounded-md border border-amber-300 bg-amber-800 px-3 py-1.5 text-sm font-black text-amber-100">🏆 Rank #<?= $rank ?> <span class="font-medium text-amber-200">/ <?= $totalPlayers ?></span></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4 sm:p-6">
                <div class="rounded-xl border border-indigo-700 bg-slate-900 p-4 text-center">
                    <div class="text-2xl font-black text-indigo-300"><?= number_format((int)$profile['xp']) ?></div>
                    <div class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">Total XP</div>
                </div>
                <div class="rounded-xl border border-emerald-700 bg-slate-900 p-4 text-center">
                    <div class="text-2xl font-black text-emerald-300"><?= $solvedCount ?><span class="text-sm text-slate-400"> / <?= $totalChallenges ?></span></div>
                    <div class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">Challenges</div>
                </div>
                <div class="rounded-xl border border-cyan-700 bg-slate-900 p-4 text-center">
                    <div class="text-2xl font-black text-cyan-300"><?= $portalCount ?></div>
                    <div class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">Portals cleared</div>
                </div>
                <div class="rounded-xl border border-orange-700 bg-slate-900 p-4 text-center">
                    <div class="text-2xl font-black text-orange-300"><?= (int)$profile['streak'] ?> 🔥</div>
                    <div class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">Day streak</div>
                </div>
            </div>

            <div class="grid gap-4 px-4 pb-5 sm:grid-cols-2 sm:px-6 sm:pb-6">
                <div class="rounded-xl border border-slate-600 bg-slate-900 p-4">
                    <div class="text-xs font-black uppercase tracking-wider text-amber-300">Player title</div>
                    <div class="mt-2 text-lg font-black text-white"><?= htmlspecialchars(levelTitle((int)$profile['level'])) ?></div>
                    <div class="mt-1 text-sm text-slate-400">Earned at Level <?= (int)$profile['level'] ?></div>
                </div>
                <div class="rounded-xl border border-slate-600 bg-slate-900 p-4">
                    <div class="text-xs font-black uppercase tracking-wider text-cyan-300">Guild record</div>
                    <div class="mt-2 text-lg font-black text-white"><?= $coopCount ?> co-op <?= $coopCount === 1 ? 'room' : 'rooms' ?></div>
                    <div class="mt-1 text-sm text-slate-400">Adventurer since <?= htmlspecialchars(date('M Y', strtotime($profile['created_at']))) ?></div>
                </div>
            </div>

            <div class="mx-4 mb-5 rounded-xl border border-slate-600 bg-slate-900 px-4 py-3 sm:mx-6 sm:mb-6">
                <div class="text-xs font-black uppercase tracking-wider text-emerald-300">About this player</div>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-200"><?= $profile['bio'] !== '' ? htmlspecialchars($profile['bio']) : 'This adventurer has not added a bio yet.' ?></p>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php if ($editMode): ?>
<script>
const photoInput = document.getElementById('displayPhotoInput');
const photoPreview = document.getElementById('displayPhotoPreview');
const coverInput = document.getElementById('coverPhotoInput');
const coverPreview = document.getElementById('coverPhotoPreview');
if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
        const [file] = photoInput.files;
        if (file) photoPreview.src = URL.createObjectURL(file);
    });
}
if (coverInput && coverPreview) {
    coverInput.addEventListener('change', () => {
        const [file] = coverInput.files;
        if (file) coverPreview.src = URL.createObjectURL(file);
    });
}
</script>
<?php endif; ?>
</body>
</html>
