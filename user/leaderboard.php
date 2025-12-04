<?php 
include "auth.php";
include "../config/koneksi.php";
include "../config/settings.php";
include "../config/gamification.php";

$user_id = intval($_SESSION['user_id']);
$s = getAllSettings();

// Get leaderboard
$leaderboard = getLeaderboard($conn, 20);

// Get current user's rank
$my_rank = getUserRank($conn, $user_id);

// Get user stats
$stmt = mysqli_prepare($conn, "SELECT current_streak, longest_streak FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$my_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$my_attendance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM absen WHERE user_id = $user_id"))['c'];

// Colors
$color_primary = $s['color_primary'] ?? '#1e293b';
$color_secondary = $s['color_secondary'] ?? '#3b82f6';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?= htmlspecialchars($s['site_name'] ?? 'Absensi') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '<?= $color_primary ?>',
                    secondary: '<?= $color_secondary ?>',
                }
            }
        }
    }
    </script>
</head>
<body class="bg-slate-50 min-h-screen">

    <!-- Header -->
    <div class="bg-primary text-white">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <div class="flex items-center gap-4 mb-6">
                <a href="dashboard.php" class="p-2 hover:bg-white/10 rounded-lg transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-xl font-bold">Leaderboard</h1>
            </div>
            
            <!-- My Stats -->
            <div class="bg-white/10 rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl font-bold">
                            #<?= $my_rank ?>
                        </div>
                        <div>
                            <p class="text-white/60 text-sm">Peringkat Kamu</p>
                            <p class="font-bold text-lg"><?= $my_attendance ?> kehadiran</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-white/60 text-sm">Streak</p>
                        <p class="font-bold text-lg"><?= $my_stats['current_streak'] ?? 0 ?> hari 🔥</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard List -->
    <div class="max-w-2xl mx-auto px-4 py-6">
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <?php foreach ($leaderboard as $i => $user): ?>
            <?php 
                $isMe = $user['id'] == $user_id;
                $picture = $user['picture'] ? (strpos($user['picture'], 'http') === 0 ? $user['picture'] : '../' . $user['picture']) : '';
            ?>
            <div class="flex items-center gap-4 px-4 py-3 <?= $isMe ? 'bg-secondary/5' : '' ?> <?= $i > 0 ? 'border-t border-slate-100' : '' ?>">
                <!-- Rank -->
                <div class="w-8 text-center flex-shrink-0">
                    <?php if ($user['rank'] == 1): ?>
                        <span class="text-2xl">🥇</span>
                    <?php elseif ($user['rank'] == 2): ?>
                        <span class="text-2xl">🥈</span>
                    <?php elseif ($user['rank'] == 3): ?>
                        <span class="text-2xl">🥉</span>
                    <?php else: ?>
                        <span class="text-slate-400 font-bold"><?= $user['rank'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Avatar -->
                <?php if ($picture): ?>
                <img src="<?= htmlspecialchars($picture) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <?php endif; ?>
                
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-900 truncate <?= $isMe ? 'text-secondary' : '' ?>">
                        <?= htmlspecialchars($user['nama']) ?>
                        <?= $isMe ? '(Kamu)' : '' ?>
                    </p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($user['kelas'] ?: '-') ?></p>
                </div>
                
                <!-- Stats -->
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-slate-900"><?= $user['total_attendance'] ?></p>
                    <div class="flex items-center justify-end gap-1 text-xs text-slate-400">
                        <?php if ($user['current_streak'] > 0): ?>
                        <span>🔥<?= $user['current_streak'] ?></span>
                        <?php endif; ?>
                        <?php if ($user['badge_count'] > 0): ?>
                        <span>🏆<?= $user['badge_count'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>
