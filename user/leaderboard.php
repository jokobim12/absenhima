<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";
include "../config/gamification.php";

$user_id = intval($_SESSION['user_id']);

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get leaderboard top 10
$leaderboard = $conn->query("
    SELECT u.id, u.nama, u.picture, u.total_points, u.daily_streak,
           (SELECT COUNT(*) FROM absen WHERE user_id = u.id) as attendance_count
    FROM users u 
    ORDER BY u.total_points DESC, u.daily_streak DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Split top 3 and rest
$top3 = array_slice($leaderboard, 0, 3);
$rest = array_slice($leaderboard, 3);

// Get my rank and stats
$my_rank = getUserRank($conn, $user_id);
$my_points = $user['total_points'] ?? 0;
$my_streak = $user['daily_streak'] ?? 0;

// Point breakdown
$breakdown = $conn->query("
    SELECT activity_type, SUM(points) as total, COUNT(*) as count
    FROM point_history 
    WHERE user_id = $user_id
    GROUP BY activity_type
")->fetch_all(MYSQLI_ASSOC);

$activity_labels = [
    'daily_login' => 'Login Harian',
    'chat' => 'Kirim Pesan',
    'attendance' => 'Kehadiran Event',
    'attendance_big' => 'Kehadiran Event Besar',
    'streak_bonus' => 'Bonus Streak'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?= htmlspecialchars($s['site_name'] ?? 'SADHATI') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { darkMode: 'class' }
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark');
    }
    </script>
    <style>
        .dark body { background: #0a0a0a !important; }
        .dark .bg-white { background: #1a1a1a !important; }
        .dark .bg-slate-50 { background: #0f0f0f !important; }
        .dark .bg-slate-100 { background: #1a1a1a !important; }
        .dark .border-slate-200 { border-color: #333 !important; }
        .dark .border-slate-100 { border-color: #2a2a2a !important; }
        .dark .text-slate-900 { color: #ffffff !important; }
        .dark .text-slate-800 { color: #f0f0f0 !important; }
        .dark .text-slate-700 { color: #e0e0e0 !important; }
        .dark .text-slate-600 { color: #b0b0b0 !important; }
        .dark .text-slate-500 { color: #909090 !important; }
        .dark .bg-yellow-50 { background: #2a2500 !important; }
        .dark .hover\:bg-slate-50:hover { background: #1f1f1f !important; }
        .dark .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: #2a2a2a !important; }
        
        .podium-1 { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .podium-2 { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .podium-3 { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-xl font-bold">Leaderboard</h1>
            </div>
        </div>
    </div>

    <!-- My Stats Card -->
    <div class="max-w-4xl mx-auto px-4 -mt-4">
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-slate-200">
            <?php 
            $myPic = $user['picture'] ?? '';
            $myPicUrl = (strpos($myPic, 'http') === 0) ? $myPic : '../' . $myPic;
            ?>
            <div class="flex items-center gap-4">
                <?php if (!empty($myPic)): ?>
                    <img src="<?= htmlspecialchars($myPicUrl) ?>" class="w-14 h-14 rounded-full object-cover border-2 border-yellow-400">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-full bg-slate-200 flex items-center justify-center">
                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <p class="font-bold text-slate-900"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="text-sm text-slate-500">Peringkat #<?= $my_rank ?></p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-orange-500"><?= number_format($my_points) ?></p>
                    <p class="text-xs text-slate-500">Poin</p>
                </div>
                <div class="text-right pl-4 border-l border-slate-200">
                    <p class="text-2xl font-bold text-orange-500"><?= $my_streak ?> 🔥</p>
                    <p class="text-xs text-slate-500">Streak</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Podium Top 3 -->
    <?php if (count($top3) >= 1): ?>
    <div class="max-w-4xl mx-auto px-4 mt-6">
        <div class="flex items-end justify-center gap-2 px-4">
            <!-- 2nd Place -->
            <div class="flex flex-col items-center w-1/3">
                <?php if (isset($top3[1])): 
                    $pic2 = $top3[1]['picture'] ?? '';
                    $pic2Url = (strpos($pic2, 'http') === 0) ? $pic2 : '../' . $pic2;
                ?>
                <p class="text-xs font-bold text-yellow-600 dark:text-yellow-400 mb-1 text-center truncate w-full px-1"><?= strtoupper(htmlspecialchars($top3[1]['nama'])) ?></p>
                <?php if (!empty($pic2)): ?>
                    <img src="<?= htmlspecialchars($pic2Url) ?>" class="w-16 h-16 rounded-full object-cover border-4 border-gray-300 shadow-lg mb-2">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full bg-gray-300 flex items-center justify-center border-4 border-gray-400 shadow-lg mb-2">
                        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <span class="bg-gray-400 text-white text-xs px-2 py-0.5 rounded-full mb-2 font-bold"><?= number_format($top3[1]['total_points']) ?> Poin</span>
                <?php else: ?>
                <div class="w-16 h-16 rounded-full bg-gray-600 flex items-center justify-center border-4 border-gray-500 shadow-lg mb-2 opacity-30">
                    <span class="text-gray-400 text-2xl">?</span>
                </div>
                <span class="bg-gray-600 text-gray-400 text-xs px-2 py-0.5 rounded-full mb-2 font-bold">- Poin</span>
                <?php endif; ?>
                <div class="podium-2 w-full h-24 rounded-t-xl flex items-center justify-center shadow-lg <?= !isset($top3[1]) ? 'opacity-50' : '' ?>">
                    <span class="text-white text-4xl font-bold">2</span>
                </div>
            </div>
            
            <!-- 1st Place -->
            <div class="flex flex-col items-center w-1/3">
                <?php if (isset($top3[0])): 
                    $pic1 = $top3[0]['picture'] ?? '';
                    $pic1Url = (strpos($pic1, 'http') === 0) ? $pic1 : '../' . $pic1;
                ?>
                <p class="text-xs font-bold text-yellow-600 dark:text-yellow-400 mb-1 text-center truncate w-full px-1"><?= strtoupper(htmlspecialchars($top3[0]['nama'])) ?></p>
                <?php if (!empty($pic1)): ?>
                    <img src="<?= htmlspecialchars($pic1Url) ?>" class="w-20 h-20 rounded-full object-cover border-4 border-yellow-400 shadow-lg mb-2">
                <?php else: ?>
                    <div class="w-20 h-20 rounded-full bg-yellow-100 flex items-center justify-center border-4 border-yellow-400 shadow-lg mb-2">
                        <svg class="w-10 h-10 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <span class="bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full mb-2 font-bold"><?= number_format($top3[0]['total_points']) ?> Poin</span>
                <?php endif; ?>
                <div class="podium-1 w-full h-32 rounded-t-xl flex items-center justify-center shadow-lg">
                    <span class="text-white text-5xl font-bold">1</span>
                </div>
            </div>
            
            <!-- 3rd Place -->
            <div class="flex flex-col items-center w-1/3">
                <?php if (isset($top3[2])): 
                    $pic3 = $top3[2]['picture'] ?? '';
                    $pic3Url = (strpos($pic3, 'http') === 0) ? $pic3 : '../' . $pic3;
                ?>
                <p class="text-xs font-bold text-yellow-600 dark:text-yellow-400 mb-1 text-center truncate w-full px-1"><?= strtoupper(htmlspecialchars($top3[2]['nama'])) ?></p>
                <?php if (!empty($pic3)): ?>
                    <img src="<?= htmlspecialchars($pic3Url) ?>" class="w-14 h-14 rounded-full object-cover border-4 border-orange-300 shadow-lg mb-2">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-full bg-orange-100 flex items-center justify-center border-4 border-orange-300 shadow-lg mb-2">
                        <svg class="w-7 h-7 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                <span class="bg-orange-400 text-white text-xs px-2 py-0.5 rounded-full mb-2 font-bold"><?= number_format($top3[2]['total_points']) ?> Poin</span>
                <?php else: ?>
                <div class="w-14 h-14 rounded-full bg-gray-600 flex items-center justify-center border-4 border-gray-500 shadow-lg mb-2 opacity-30">
                    <span class="text-gray-400 text-xl">?</span>
                </div>
                <span class="bg-gray-600 text-gray-400 text-xs px-2 py-0.5 rounded-full mb-2 font-bold">- Poin</span>
                <?php endif; ?>
                <div class="podium-3 w-full h-16 rounded-t-xl flex items-center justify-center shadow-lg <?= !isset($top3[2]) ? 'opacity-50' : '' ?>">
                    <span class="text-white text-3xl font-bold">3</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leaderboard List (4-10) -->
    <?php if (!empty($rest)): ?>
    <div class="max-w-4xl mx-auto px-4 py-4">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900">Top 10</h3>
            </div>
            <div class="divide-y divide-slate-100">
                <?php $rank = 4; foreach ($rest as $u): ?>
                <div class="flex items-center gap-3 p-3 <?= $u['id'] == $user_id ? 'bg-yellow-50' : 'hover:bg-slate-50' ?>">
                    <!-- Rank -->
                    <div class="w-8 text-center">
                        <span class="text-slate-500 font-bold"><?= $rank ?></span>
                    </div>
                    
                    <!-- Avatar -->
                    <?php 
                    $pic = $u['picture'] ?? '';
                    $picUrl = (strpos($pic, 'http') === 0) ? $pic : '../' . $pic;
                    ?>
                    <?php if (!empty($pic)): ?>
                        <img src="<?= htmlspecialchars($picUrl) ?>" class="w-10 h-10 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Name & Stats -->
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 truncate">
                            <?= htmlspecialchars($u['nama']) ?>
                            <?= $u['id'] == $user_id ? '<span class="text-xs text-orange-500">(Kamu)</span>' : '' ?>
                        </p>
                        <p class="text-xs text-slate-500"><?= $u['attendance_count'] ?> kehadiran · <?= $u['daily_streak'] ?> hari streak</p>
                    </div>
                    
                    <!-- Points -->
                    <div class="text-right">
                        <p class="font-bold text-slate-900"><?= number_format($u['total_points']) ?></p>
                        <p class="text-xs text-slate-500">poin</p>
                    </div>
                </div>
                <?php $rank++; endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Point Info -->
    <div class="max-w-4xl mx-auto px-4 mt-2">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <h3 class="font-bold text-slate-900 mb-3">Cara Dapat Poin</h3>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                    <span class="text-slate-600">Login Harian</span>
                    <span class="font-bold text-slate-900">+1</span>
                </div>
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                    <span class="text-slate-600">Kirim Pesan (1x/hari)</span>
                    <span class="font-bold text-slate-900">+1</span>
                </div>
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                    <span class="text-slate-600">Hadir Event</span>
                    <span class="font-bold text-slate-900">+5</span>
                </div>
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                    <span class="text-slate-600">Hadir Event Besar</span>
                    <span class="font-bold text-slate-900">+10</span>
                </div>
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                    <span class="text-slate-600">Streak 7 Hari</span>
                    <span class="font-bold text-slate-900">+5</span>
                </div>
                <div class="flex justify-between p-2 bg-slate-50 rounded">
                    <span class="text-slate-600">Streak 30 Hari</span>
                    <span class="font-bold text-slate-900">+20</span>
                </div>
            </div>
        </div>
    </div>

    <!-- My Breakdown -->
    <?php if (!empty($breakdown)): ?>
    <div class="max-w-4xl mx-auto px-4 py-4 pb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <h3 class="font-bold text-slate-900 mb-3">Rincian Poin Kamu</h3>
            <div class="space-y-2">
                <?php foreach ($breakdown as $b): ?>
                <div class="flex justify-between items-center p-2 bg-slate-50 rounded">
                    <span class="text-sm text-slate-600"><?= $activity_labels[$b['activity_type']] ?? $b['activity_type'] ?></span>
                    <span class="text-sm">
                        <span class="text-slate-500"><?= $b['count'] ?>x</span>
                        <span class="font-bold text-slate-900 ml-2">+<?= $b['total'] ?></span>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
