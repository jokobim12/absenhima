<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";

$user_id = intval($_SESSION['user_id']);
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
$today = date('Y-m-d');

// Get activities
$daily_claimed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'daily_login' AND DATE(created_at) = '$today'"));
$chat_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM point_history WHERE user_id = $user_id AND activity_type = 'chat' AND DATE(created_at) = '$today'"))['c'];
$attendance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM absen WHERE user_id = $user_id AND DATE(created_at) = '$today'"));

$streak = intval($user['daily_streak']);
$points = intval($user['total_points']);
$rank = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*)+1 as r FROM users WHERE total_points > $points"))['r'];

// Streak milestones yang bisa diklaim
$milestones = [];
foreach ([7, 14, 30, 60, 90] as $m) {
    $claimed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'streak_milestone' AND description LIKE '%$m hari%'"));
    if ($streak >= $m && !$claimed) {
        $milestones[] = ['days' => $m, 'points' => $m <= 7 ? 5 : ($m <= 14 ? 10 : 20)];
    }
}

$can_claim_daily = !$daily_claimed;
$total_claimable = ($can_claim_daily ? 1 : 0) + count($milestones);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Misi Harian - <?= htmlspecialchars($s['site_name'] ?? 'AbsenHIMA') ?></title>
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
        .dark .hover\:bg-slate-50:hover { background: #1f1f1f !important; }
        .dark .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: #2a2a2a !important; }
        .dark .bg-emerald-50 { background: #052e16 !important; }
        .dark .bg-orange-50 { background: #2a1a00 !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold">Misi & Reward</h1>
                    <p class="text-sm text-white/80">Selesaikan misi untuk dapat poin</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Card -->
    <div class="max-w-4xl mx-auto px-4 -mt-4">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="grid grid-cols-3 divide-x divide-slate-100">
                <div class="p-4 text-center">
                    <p class="text-3xl font-bold text-orange-500"><?= $streak ?></p>
                    <p class="text-xs text-slate-500 mt-1">Hari Streak</p>
                </div>
                <div class="p-4 text-center">
                    <p class="text-3xl font-bold text-emerald-500"><?= number_format($points) ?></p>
                    <p class="text-xs text-slate-500 mt-1">Total Poin</p>
                </div>
                <div class="p-4 text-center">
                    <p class="text-3xl font-bold text-blue-500">#<?= $rank ?></p>
                    <p class="text-xs text-slate-500 mt-1">Peringkat</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

        <?php if ($total_claimable > 0): ?>
        <!-- Claimable Rewards -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 bg-emerald-50 flex items-center gap-2">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                <h2 class="font-semibold text-slate-900">Hadiah Tersedia</h2>
                <span class="ml-auto bg-emerald-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $total_claimable ?></span>
            </div>
            <div class="divide-y divide-slate-100">
                <?php if ($can_claim_daily): ?>
                <div class="p-4 flex items-center gap-4" id="card-daily">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-xl flex-shrink-0">
                        ☀️
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900">Login Harian</p>
                        <p class="text-sm text-slate-500">Klaim hadiah login hari ini</p>
                    </div>
                    <button onclick="claimReward('daily_login', 1, 0, this)" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-2 rounded-xl text-sm transition flex-shrink-0">
                        Klaim +1
                    </button>
                </div>
                <?php endif; ?>

                <?php foreach ($milestones as $m): ?>
                <div class="p-4 flex items-center gap-4" id="card-streak-<?= $m['days'] ?>">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-orange-500 flex items-center justify-center text-xl flex-shrink-0">
                        🔥
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900">Streak <?= $m['days'] ?> Hari</p>
                        <p class="text-sm text-slate-500">Bonus milestone tercapai!</p>
                    </div>
                    <button onclick="claimReward('streak_<?= $m['days'] ?>', <?= $m['points'] ?>, <?= $m['days'] ?>, this)" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-2 rounded-xl text-sm transition flex-shrink-0">
                        Klaim +<?= $m['points'] ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Daily Missions -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h2 class="font-semibold text-slate-900">Misi Hari Ini</h2>
            </div>
            <div class="divide-y divide-slate-100">
                <!-- Login Harian -->
                <div class="p-4 flex items-center gap-4 <?= $daily_claimed ? 'bg-slate-50' : '' ?>">
                    <div class="w-10 h-10 rounded-lg <?= $daily_claimed ? 'bg-emerald-100' : 'bg-slate-100' ?> flex items-center justify-center flex-shrink-0">
                        <?php if ($daily_claimed): ?>
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <span class="text-lg">☀️</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 <?= $daily_claimed ? 'line-through opacity-60' : '' ?>">Login Harian</p>
                        <p class="text-sm text-slate-500">Klaim reward login</p>
                    </div>
                    <span class="text-sm <?= $daily_claimed ? 'text-emerald-500 font-semibold' : 'text-slate-400' ?> flex-shrink-0">
                        <?= $daily_claimed ? 'Selesai' : '+1 poin' ?>
                    </span>
                </div>

                <!-- Kirim Pesan -->
                <div class="p-4 flex items-center gap-4 <?= $chat_today >= 1 ? 'bg-slate-50' : '' ?>">
                    <div class="w-10 h-10 rounded-lg <?= $chat_today >= 1 ? 'bg-emerald-100' : 'bg-slate-100' ?> flex items-center justify-center flex-shrink-0">
                        <?php if ($chat_today >= 1): ?>
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <span class="text-lg">💬</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 <?= $chat_today >= 1 ? 'line-through opacity-60' : '' ?>">Kirim Pesan</p>
                        <p class="text-sm text-slate-500">Chat di forum diskusi</p>
                    </div>
                    <span class="text-sm <?= $chat_today >= 1 ? 'text-emerald-500 font-semibold' : 'text-slate-400' ?> flex-shrink-0">
                        <?= $chat_today >= 1 ? 'Selesai' : '+1 poin' ?>
                    </span>
                </div>

                <!-- Aktif Diskusi -->
                <div class="p-4 <?= $chat_today >= 5 ? 'bg-slate-50' : '' ?>">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg <?= $chat_today >= 5 ? 'bg-emerald-100' : 'bg-slate-100' ?> flex items-center justify-center flex-shrink-0">
                            <?php if ($chat_today >= 5): ?>
                                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <?php else: ?>
                                <span class="text-lg">🗣️</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-slate-900 <?= $chat_today >= 5 ? 'line-through opacity-60' : '' ?>">Aktif Berdiskusi</p>
                            <p class="text-sm text-slate-500">Kirim 5 pesan hari ini</p>
                        </div>
                        <span class="text-sm <?= $chat_today >= 5 ? 'text-emerald-500 font-semibold' : 'text-slate-400' ?> flex-shrink-0">
                            <?= $chat_today >= 5 ? 'Selesai' : '+3 poin' ?>
                        </span>
                    </div>
                    <?php if ($chat_today < 5): ?>
                    <div class="mt-3 ml-14">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full" style="width: <?= ($chat_today/5)*100 ?>%"></div>
                            </div>
                            <span class="text-xs text-slate-500"><?= $chat_today ?>/5</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Hadir Event -->
                <div class="p-4 flex items-center gap-4 <?= $attendance ? 'bg-slate-50' : '' ?>">
                    <div class="w-10 h-10 rounded-lg <?= $attendance ? 'bg-emerald-100' : 'bg-slate-100' ?> flex items-center justify-center flex-shrink-0">
                        <?php if ($attendance): ?>
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <span class="text-lg">📍</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 <?= $attendance ? 'line-through opacity-60' : '' ?>">Hadir di Event</p>
                        <p class="text-sm text-slate-500">Absen di event hari ini</p>
                    </div>
                    <span class="text-sm <?= $attendance ? 'text-emerald-500 font-semibold' : 'text-slate-400' ?> flex-shrink-0">
                        <?= $attendance ? 'Selesai' : '+5 poin' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Milestone Tracker -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h2 class="font-semibold text-slate-900">Milestone Streak</h2>
            </div>
            <div class="p-4">
                <div class="flex justify-between mb-4">
                    <?php foreach ([7, 14, 30, 60, 90] as $m): 
                        $reached = $streak >= $m;
                        $claimed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'streak_milestone' AND description LIKE '%$m hari%'"));
                    ?>
                    <div class="text-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mx-auto <?= $reached ? ($claimed ? 'bg-emerald-100 text-emerald-600' : 'bg-orange-100 text-orange-600') : 'bg-slate-100 text-slate-400' ?>">
                            <?php if ($reached && $claimed): ?>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <?php elseif ($reached): ?>
                                🔥
                            <?php else: ?>
                                🔒
                            <?php endif; ?>
                        </div>
                        <p class="text-xs mt-1 <?= $reached ? 'text-slate-700 font-medium' : 'text-slate-400' ?>"><?= $m ?>d</p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-orange-400 to-red-500 rounded-full transition-all" style="width: <?= min(100, ($streak/90)*100) ?>%"></div>
                </div>
                <p class="text-center text-sm text-slate-500 mt-2"><?= $streak ?> dari 90 hari</p>
            </div>
        </div>

        <!-- Info -->
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
            <p class="text-sm text-orange-800">
                <span class="font-semibold">Tips:</span> Login setiap hari untuk menjaga streak. Bonus poin tersedia di milestone 7, 14, 30, 60, dan 90 hari!
            </p>
        </div>
    </div>

    <script>
    async function claimReward(type, pts, milestone, btn) {
        const card = btn.closest('[id^="card-"]');
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
        
        try {
            const res = await fetch('../api/claim_daily.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'include',
                body: JSON.stringify({ mission_id: type, milestone: milestone })
            });
            const data = await res.json();
            
            if (data.success) {
                card.innerHTML = `
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-xl flex-shrink-0">
                        <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-emerald-700">+${pts} Poin Diterima!</p>
                        <p class="text-sm text-emerald-600">Total poin: ${data.total_points}</p>
                    </div>
                `;
                card.classList.add('bg-emerald-50');
            } else {
                btn.disabled = false;
                btn.textContent = 'Klaim +' + pts;
                alert(data.message || 'Gagal klaim');
            }
        } catch(e) {
            btn.disabled = false;
            btn.textContent = 'Klaim +' + pts;
            alert('Terjadi kesalahan');
        }
    }
    </script>
</body>
</html>
