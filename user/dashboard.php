<?php 
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";
include "../config/lang.php";
include "../config/gamification.php";

$user_id = intval($_SESSION['user_id']);

// Handle language switch
if (isset($_GET['lang'])) {
    setCurrentLang($_GET['lang']);
    header("Location: dashboard.php");
    exit;
}

// Get settings
$s = getAllSettings();

// Prepared statement untuk get user
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Query tanpa input user - aman
$event = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE status='open' LIMIT 1"));

$semester_sekarang = hitungSemester($user['nim']);
if ($user['semester'] != $semester_sekarang) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET semester = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $semester_sekarang, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $user['semester'] = $semester_sekarang;
}

// Prepared statement untuk get riwayat
$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(a.waktu, a.created_at) as waktu_absen, e.nama_event 
    FROM absen a 
    JOIN events e ON a.event_id = e.id 
    WHERE a.user_id = ? 
    ORDER BY a.id DESC 
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$riwayat = mysqli_stmt_get_result($stmt);

// Prepared statement untuk count total hadir
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM absen WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$total_hadir = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);

$has_picture = !empty($user['picture']);
$picture_url = $has_picture ? (strpos($user['picture'], 'http') === 0 ? $user['picture'] : '../' . $user['picture']) : '';

// Get active announcements
$announcements = mysqli_query($conn, "
    SELECT * FROM announcements 
    WHERE is_active = 1 
    AND (expires_at IS NULL OR expires_at > NOW())
    ORDER BY is_pinned DESC, created_at DESC 
    LIMIT 5
");

// Get user's pending permissions count
$pending_perms = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as c FROM permissions WHERE user_id = $user_id AND status = 'pending'
"))['c'] ?? 0;

// Gamification - Auto update streak (tanpa poin), poin harus claim manual
$stmt_daily = mysqli_prepare($conn, "SELECT last_active_date, daily_streak, total_points, longest_streak FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_daily, "i", $user_id);
mysqli_stmt_execute($stmt_daily);
$user_daily = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_daily));
mysqli_stmt_close($stmt_daily);

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$last_active = $user_daily['last_active_date'];
$user_streak = intval($user_daily['daily_streak']);
$longest_streak = intval($user_daily['longest_streak']);

// Auto update streak jika belum login hari ini
if ($last_active != $today) {
    if ($last_active == $yesterday) {
        $user_streak++;
    } else {
        $user_streak = 1;
    }
    if ($user_streak > $longest_streak) {
        $longest_streak = $user_streak;
    }
    // Update streak only, not points
    $stmt_upd = mysqli_prepare($conn, "UPDATE users SET last_active_date = ?, daily_streak = ?, longest_streak = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_upd, "siii", $today, $user_streak, $longest_streak, $user_id);
    mysqli_stmt_execute($stmt_upd);
    mysqli_stmt_close($stmt_upd);
}

// Cek misi yang bisa di-claim
$can_claim_daily = !mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'daily_login' AND DATE(created_at) = '$today'"));
$pending_claims = $can_claim_daily ? 1 : 0;

$user_badges = getUserBadges($conn, $user_id);
$user_rank = getUserRank($conn, $user_id);
$user_points = $user_daily['total_points'] ?? 0;

// Unpaid iuran
$unpaid_iuran = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(i.nominal), 0) as total
    FROM iuran i
    LEFT JOIN iuran_payments ip ON ip.iuran_id = i.id AND ip.user_id = $user_id
    WHERE i.status = 'active' AND ip.id IS NULL
")->fetch_assoc();

// Colors from settings
$color_primary = $s['color_primary'] ?? '#1e293b';
$color_secondary = $s['color_secondary'] ?? '#3b82f6';
$color_accent = $s['color_accent'] ?? '#10b981';
$color_bg = $s['color_background'] ?? '#f8fafc';

// Current language
$current_lang = getCurrentLang();
$languages = getAvailableLanguages();
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($s['site_name'] ?? 'Absensi HIMA') ?></title>
    <?php if (!empty($s['site_favicon'])): ?>
    <link rel="icon" href="../<?= htmlspecialchars($s['site_favicon']) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    primary: '<?= $color_primary ?>',
                    secondary: '<?= $color_secondary ?>',
                    accent: '<?= $color_accent ?>',
                }
            }
        }
    }
    </script>
    <script>
    // Dark mode initialization
    if (localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
    </script>
    <style>
        .glass { backdrop-filter: blur(10px); }
        body { background: linear-gradient(135deg, <?= $color_bg ?> 0%, #e2e8f0 100%); }
        .break-words { word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
        .dark body { background: #0a0a0a !important; }
        html.dark { background: #0a0a0a; }
        .gradient-primary { background: linear-gradient(135deg, <?= $color_primary ?> 0%, <?= $color_primary ?>dd 100%); }
        .reaction-btn { transition: transform 0.1s; }
        .reaction-btn:hover { transform: scale(1.2); }
        .reaction-btn.active { background: rgba(59, 130, 246, 0.2); }
        /* Dark mode overrides for better contrast */
        .dark .bg-white { background-color: #1a1a1a !important; }
        .dark .bg-slate-50 { background-color: #111111 !important; }
        .dark .bg-slate-100 { background-color: #222222 !important; }
        .dark .bg-slate-200 { background-color: #2a2a2a !important; }
        .dark .border-slate-200 { border-color: #333333 !important; }
        .dark .border-slate-100 { border-color: #2a2a2a !important; }
        .dark .text-slate-900 { color: #f5f5f5 !important; }
        .dark .text-slate-800 { color: #e5e5e5 !important; }
        .dark .text-slate-700 { color: #d4d4d4 !important; }
        .dark .text-slate-600 { color: #b3b3b3 !important; }
        .dark .text-slate-500 { color: #999999 !important; }
        .dark .text-slate-400 { color: #888888 !important; }
        .dark .bg-white\/80 { background-color: rgba(26, 26, 26, 0.95) !important; }
        .dark .hover\:bg-slate-100:hover { background-color: #333333 !important; }
        .dark .hover\:bg-slate-50:hover { background-color: #2a2a2a !important; }
        .dark .hover\:bg-red-50:hover { background-color: rgba(239, 68, 68, 0.15) !important; }
        .dark .hover\:bg-yellow-50:hover { background-color: rgba(234, 179, 8, 0.15) !important; }
        .dark .hover\:bg-amber-50:hover { background-color: rgba(245, 158, 11, 0.15) !important; }
        .dark .hover\:bg-blue-50:hover { background-color: rgba(59, 130, 246, 0.15) !important; }
        .dark .shadow-xl { box-shadow: 0 20px 25px -5px rgba(0,0,0,0.6) !important; }
        .dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5) !important; }
        .dark .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0,0,0,0.4) !important; }
        .dark textarea, .dark input[type="text"], .dark input[type="search"] { 
            background-color: #222222 !important; 
            border-color: #444444 !important; 
            color: #e5e5e5 !important; 
        }
        .dark textarea::placeholder, .dark input::placeholder { color: #777777 !important; }
        .dark select { background-color: #222222 !important; color: #e5e5e5 !important; }
        /* Dark mode for chat bubbles */
        .dark .bg-white.border.border-slate-200 { background-color: #2a2a2a !important; border-color: #404040 !important; }
        .dark .bg-white.border.border-slate-200 p { color: #e5e5e5 !important; }
        .dark .bg-white.border.border-slate-200 .text-slate-400 { color: #888888 !important; }
        .dark .bg-white.border.border-slate-200 .text-secondary { color: #60a5fa !important; }
        /* Dark mode for file attachment in chat */
        .dark .bg-slate-100 { background-color: #333333 !important; }
        .dark .bg-slate-100 .text-slate-700 { color: #e5e5e5 !important; }
        .dark .bg-slate-100 .text-slate-500 { color: #999999 !important; }
        /* Dark mode for action buttons popup */
        .dark .bg-white.rounded-lg.shadow-lg { background-color: #252525 !important; border-color: #3a3a3a !important; }
        /* Dark mode for emoji/sticker picker */
        .dark #emojiPicker > div, .dark #stickerPicker > div:last-child { background-color: #1e1e1e !important; border-color: #3a3a3a !important; }
        .dark .emoji-btn:hover, .dark .sticker-tab:hover { background-color: #333333 !important; }
        .dark .sticker-tab.text-secondary { background-color: transparent !important; }
        /* Dark mode for dropdowns */
        .dark #mentionDropdown, .dark #searchResults, .dark #notifDropdown { background-color: #1e1e1e !important; border-color: #3a3a3a !important; }
        /* Dark mode for pinned banner */
        .dark .bg-amber-50 { background-color: rgba(245, 158, 11, 0.1) !important; }
        .dark .border-amber-200 { border-color: rgba(245, 158, 11, 0.3) !important; }
        .dark .text-amber-700, .dark .text-amber-800 { color: #fbbf24 !important; }
        /* Dark mode for reply preview */
        .dark .bg-blue-50 { background-color: rgba(59, 130, 246, 0.15) !important; }
        .dark .text-blue-700, .dark .text-blue-600 { color: #60a5fa !important; }
        /* Dark mode for green elements */
        .dark .bg-green-50 { background-color: rgba(34, 197, 94, 0.15) !important; }
        .dark .text-green-600, .dark .text-green-700 { color: #4ade80 !important; }
        /* Dark mode for riwayat kehadiran */
        .dark .bg-emerald-100 { background-color: rgba(16, 185, 129, 0.25) !important; }
        .dark .text-emerald-600 { color: #34d399 !important; }
        .dark .from-emerald-50 { --tw-gradient-from: rgba(16, 185, 129, 0.12) !important; }
        .dark .to-teal-50 { --tw-gradient-to: rgba(20, 184, 166, 0.12) !important; }
        .dark .bg-gradient-to-r.from-emerald-50.to-teal-50 { background: linear-gradient(to right, rgba(16, 185, 129, 0.12), rgba(20, 184, 166, 0.12)) !important; border: 1px solid rgba(16, 185, 129, 0.2) !important; }
        .dark .bg-gradient-to-r.from-emerald-50.to-teal-50 .text-slate-900 { color: #f0f0f0 !important; }
        .dark .bg-gradient-to-r.from-emerald-50.to-teal-50 .text-slate-500 { color: #a0a0a0 !important; }
        /* Dark mode for typing indicator */
        .dark .bg-slate-50\/80 { background-color: rgba(17, 17, 17, 0.9) !important; }
        /* Scrollbar styling - konsisten untuk light & dark */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }
        .dark ::-webkit-scrollbar-track { background: #1a1a1a; }
        .dark ::-webkit-scrollbar-thumb { background: #444; }
        .dark ::-webkit-scrollbar-thumb:hover { background: #555; }
        /* Dark mode for file/voice elements */
        .dark .voice-message { background-color: #2a2a2a !important; }
        .dark .bg-red-50 { background-color: rgba(239, 68, 68, 0.15) !important; }
        /* Dark mode for polls */
        .dark .poll-option { background-color: #252525 !important; border-color: #3a3a3a !important; }
        .dark .poll-option:hover { background-color: #333333 !important; }
        /* Dark mode for reactions */
        .dark .reactions-display button { background-color: #2a2a2a !important; }
        .dark .bg-blue-100 { background-color: rgba(59, 130, 246, 0.2) !important; }
        /* Dark mode for attachment menu */
        .dark #attachMenu { background-color: #1e1e1e !important; }
        /* Dark mode for image preview */
        .dark #imagePreview { background-color: #1e1e1e !important; border-color: #3a3a3a !important; }
        /* Dark mode mobile overlay */
        .dark #emojiPicker > div:first-child, .dark #stickerPicker > div:first-child { background-color: rgba(0,0,0,0.7) !important; }
        /* Date separator */
        .dark .bg-slate-200.text-slate-600 { background-color: #333 !important; color: #aaa !important; }
        #chatInput { min-height: 40px; line-height: 1.4; }
        /* Toast notification animation */
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
        @keyframes pulseOnce { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        .animate-slide-out { animation: slideOut 0.3s ease-in; }
        .animate-pulse-once { animation: pulseOnce 1s ease-in-out 3; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        /* Dark mode toast */
        .dark #notifToast { background-color: #1e1e1e !important; border-color: #3a3a3a !important; }
    </style>
</head>
<body class="min-h-screen transition-colors duration-300">

    <!-- Navbar -->
    <nav class="bg-white/80 glass border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 sm:py-4 flex justify-between items-center">
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if (!empty($s['site_logo'])): ?>
                <img src="../<?= htmlspecialchars($s['site_logo']) ?>" alt="Logo" class="w-8 h-8 sm:w-10 sm:h-10 object-contain">
                <?php else: ?>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-primary rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <span class="text-slate-900 font-bold text-sm sm:text-lg truncate block"><?= htmlspecialchars($s['site_name'] ?? 'SADHATI') ?></span>
                    <p class="text-slate-500 text-xs hidden sm:block"><?= htmlspecialchars($s['site_tagline'] ?? 'Sistem Absensi') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-1 sm:gap-4">
                <!-- Dark Mode Toggle -->
                <button onclick="toggleDarkMode()" id="darkModeBtn" class="text-slate-500 hover:text-slate-900 p-1.5 sm:p-2 hover:bg-slate-100 rounded-lg transition">
                    <svg id="sunIcon" class="w-4 h-4 sm:w-5 sm:h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <svg id="moonIcon" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </button>
                <!-- Language Switcher -->
                <div class="relative">
                    <select onchange="window.location='?lang='+this.value" class="appearance-none bg-slate-100 text-slate-700 text-xs px-2 py-1.5 sm:py-2 rounded-lg cursor-pointer pr-6 focus:outline-none">
                        <?php foreach ($languages as $code => $name): ?>
                        <option value="<?= $code ?>" <?= $current_lang == $code ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 absolute right-1.5 sm:right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
                <!-- Notification Bell -->
                <div class="relative">
                    <button onclick="toggleNotifications()" id="notifBtn" class="text-slate-500 hover:text-slate-900 p-1.5 sm:p-2 hover:bg-slate-100 rounded-lg transition relative">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span id="notifBadge" class="hidden absolute -top-1 -right-1 w-4 h-4 sm:w-5 sm:h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-medium">0</span>
                    </button>
                    <!-- Notification Dropdown -->
                    <div id="notifDropdown" class="hidden fixed sm:absolute left-2 right-2 sm:left-auto sm:right-0 top-14 sm:top-full sm:mt-2 w-auto sm:w-96 bg-white rounded-xl shadow-xl border border-slate-200 z-50 max-h-[70vh] overflow-hidden">
                        <div class="p-3 border-b border-slate-100 flex justify-between items-center">
                            <h3 class="font-semibold text-slate-900">Notifikasi</h3>
                            <div class="flex gap-2">
                                <button onclick="markAllRead()" class="text-xs text-secondary hover:underline">Tandai dibaca</button>
                                <span class="text-slate-300">|</span>
                                <button onclick="deleteAllNotifs()" class="text-xs text-red-500 hover:underline">Hapus semua</button>
                            </div>
                        </div>
                        <div id="notifList" class="overflow-y-auto max-h-80">
                            <div class="p-4 text-center text-slate-400 text-sm">Memuat...</div>
                        </div>
                        <div class="p-2 border-t border-slate-100 text-center">
                            <a href="notifications.php" class="text-sm text-secondary hover:underline">Lihat semua notifikasi</a>
                        </div>
                    </div>
                </div>
                <a href="profile.php" class="text-slate-500 hover:text-slate-900 p-1.5 sm:p-2 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
                <button onclick="showLogoutModal()" class="text-slate-500 hover:text-red-600 p-1.5 sm:p-2 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Welcome Section -->
        <div class="gradient-primary rounded-lg p-4 sm:p-6 lg:p-8 mb-8 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
            <div class="relative flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 sm:gap-5 min-w-0 flex-1">
                    <?php if($has_picture): ?>
                    <img src="<?= htmlspecialchars($picture_url) ?>" alt="Profile" 
                        class="w-14 h-14 sm:w-20 sm:h-20 lg:w-24 lg:h-24 rounded-full object-cover border-2 border-white flex-shrink-0">
                    <?php else: ?>
                    <div class="w-14 h-14 sm:w-20 sm:h-20 lg:w-24 lg:h-24 bg-white/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7 sm:w-10 sm:h-10 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <p class="text-white/60 text-xs sm:text-sm"><?= __('welcome') ?></p>
                        <h2 class="text-base sm:text-2xl lg:text-3xl font-bold truncate"><?= htmlspecialchars($user['nama']) ?></h2>
                        <p class="text-white/60 text-xs sm:text-base mt-0.5 sm:mt-1"><?= htmlspecialchars($user['nim']) ?> <?php if($user['kelas'] && $user['kelas'] != '-'): ?>• <?= htmlspecialchars($user['kelas']) ?><?php endif; ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:gap-6 flex-shrink-0">
                    <div class="text-center">
                        <p class="text-white/60 text-xs sm:text-sm"><?= __('semester') ?></p>
                        <p class="text-2xl sm:text-4xl lg:text-5xl font-bold"><?= $user['semester'] ?></p>
                    </div>
                    <div class="h-10 sm:h-16 w-px bg-white/20"></div>
                    <div class="text-center">
                        <p class="text-white/60 text-xs sm:text-sm"><?= __('attendance') ?></p>
                        <p class="text-2xl sm:text-4xl lg:text-5xl font-bold"><?= $total_hadir ?></p>
                    </div>
                </div>
            </div>
            <?php if(!$user['kelas'] || $user['kelas'] == '-'): ?>
            <div class="relative mt-6 pt-6 border-t border-white/10">
                <a href="profile.php" class="inline-flex items-center gap-2 text-yellow-400 hover:text-yellow-300 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <?= __('complete_class') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Active Event -->
                <?php if($event): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-6 lg:p-8">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 bg-accent rounded-full animate-pulse"></span>
                                    <span class="text-accent font-medium text-sm"><?= __('active_event') ?></span>
                                </div>
                                <h3 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($event['nama_event']) ?></h3>
                            </div>
                            <div class="w-14 h-14 bg-accent/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <?php if(!empty($event['deskripsi']) || !empty($event['lokasi']) || !empty($event['waktu_mulai'])): ?>
                        <div class="mb-4 p-4 bg-slate-50 rounded-xl space-y-2">
                            <?php if(!empty($event['deskripsi'])): ?>
                            <p class="text-slate-600 text-sm"><?= nl2br(htmlspecialchars($event['deskripsi'])) ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap gap-4 text-sm text-slate-500">
                                <?php if(!empty($event['lokasi'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <?= htmlspecialchars($event['lokasi']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($event['waktu_mulai'])): ?>
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?= date('d M Y, H:i', strtotime($event['waktu_mulai'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <a href="scan.php" class="flex items-center justify-center gap-3 w-full py-4 gradient-primary hover:opacity-90 text-white font-semibold rounded-xl transition shadow-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            <?= __('scan_now') ?>
                        </a>
                    </div>
                </div>

                <?php endif; ?>

                <!-- Forum Diskusi (Selalu Tampil) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm" id="forumSection">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-100 bg-white rounded-t-2xl">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <h3 class="font-bold text-slate-900">Forum Diskusi</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Sound Toggle -->
                                <button onclick="toggleSound()" class="text-slate-400 hover:text-slate-600 p-1 hover:bg-slate-100 rounded transition" title="Toggle Sound">
                                    <svg id="soundIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                                    </svg>
                                </button>
                                <!-- Online Count -->
                                <span class="text-xs text-green-500 flex items-center gap-1 cursor-pointer" id="onlineStatus" onclick="showOnlineUsers()" title="Lihat yang online">
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                    <span id="onlineCount">0</span> Online
                                </span>
                            </div>
                        </div>
                        <!-- Search Bar -->
                        <div class="relative" id="searchContainer">
                            <input type="text" id="forumSearch" placeholder="Cari pesan..." 
                                class="w-full pl-9 pr-4 py-2 bg-slate-100 border-0 rounded-lg text-sm text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-secondary outline-none">
                            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <div id="searchResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-64 overflow-y-auto z-20"></div>
                        </div>
                    </div>
                    
                    <!-- Pinned Messages Banner -->
                    <div id="pinnedBanner" class="hidden border-b border-amber-200 bg-amber-50">
                    </div>
                    
                    <!-- Chat Messages -->
                    <div id="chatMessagesWrapper" class="relative h-80 overflow-hidden z-0">
                        <div id="forumWallpaperBg" class="absolute inset-0 bg-cover bg-center"></div>
                        <div id="forumWallpaperOverlay" class="absolute inset-0 bg-black" style="opacity: 0;"></div>
                        <div id="chatMessages" class="absolute inset-0 overflow-y-auto overflow-x-hidden p-4 space-y-3">
                            <div class="text-center text-slate-400 text-sm py-8">Memuat pesan...</div>
                        </div>
                    </div>
                    
                    <!-- Typing Indicator -->
                    <div id="typingIndicator" class="hidden px-4 py-1 text-xs text-slate-500 italic bg-slate-50/80">
                        <span id="typingText"></span>
                        <span class="typing-dots">
                            <span class="animate-bounce inline-block" style="animation-delay: 0ms">.</span>
                            <span class="animate-bounce inline-block" style="animation-delay: 150ms">.</span>
                            <span class="animate-bounce inline-block" style="animation-delay: 300ms">.</span>
                        </span>
                    </div>
                    
                    <!-- Chat Input -->
                    <div class="p-3 border-t border-slate-100 relative">
                        <div id="replyPreview"></div>
                        <div id="imagePreview" class="hidden mb-2"></div>
                        <div id="mentionDropdown" class="hidden absolute bg-white border border-slate-200 rounded-lg shadow-lg max-h-40 overflow-y-auto z-50"></div>
                        <form id="chatForm" class="flex flex-col gap-2 relative">
                            <!-- Input Row -->
                            <div class="flex gap-2 items-center">
                                <!-- Attachment Menu Button (Mobile) -->
                                <button type="button" id="attachMenuBtn" class="md:hidden w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition flex-shrink-0" title="Lampiran">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </button>
                                <!-- Icons (Desktop Only) -->
                                <div class="hidden md:flex items-center flex-shrink-0">
                                    <button type="button" id="emojiBtn" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition" title="Emoji">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" id="stickerBtn" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition" title="Stiker">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2" stroke-width="2" stroke-linecap="round"/>
                                            <circle cx="9" cy="10" r="1" fill="currentColor"/>
                                            <circle cx="15" cy="10" r="1" fill="currentColor"/>
                                        </svg>
                                    </button>
                                    <label class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition cursor-pointer" title="Kirim Gambar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <input type="file" id="imageInput" accept="image/*" class="hidden">
                                    </label>
                                    <label class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition cursor-pointer" title="Kirim File">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                        <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="hidden">
                                    </label>
                                    <!-- Poll Button -->
                                    <button type="button" onclick="showPollModal()" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition" title="Buat Polling">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </button>
                                </div>
                                <!-- Input Field -->
                                <div class="flex-1 min-w-0">
                                    <textarea id="chatInput" placeholder="Tulis pesan..." maxlength="2000" rows="1"
                                        class="w-full px-4 py-2.5 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-secondary focus:border-secondary outline-none transition text-sm resize-none overflow-hidden leading-5"
                                        style="max-height: 120px;"></textarea>
                                </div>
                                <!-- Voice & Send -->
                                <button type="button" id="voiceBtn" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-full transition flex-shrink-0" title="Rekam Suara">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                                    </svg>
                                </button>
                                <button type="submit" id="sendBtn"
                                    class="w-10 h-10 flex items-center justify-center bg-secondary text-white rounded-full hover:bg-secondary/90 transition flex-shrink-0">
                                    <svg class="w-5 h-5 transform rotate-90 -translate-x-[-1px]" 
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Mobile Attachment Menu -->
                            <div id="attachMenu" class="hidden md:hidden flex items-center justify-center gap-2 py-2 bg-slate-50 rounded-xl">
                                <button type="button" id="emojiBtnMobile" class="w-12 h-12 flex flex-col items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition" title="Emoji">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-xs mt-0.5">Emoji</span>
                                </button>
                                <button type="button" id="stickerBtnMobile" class="w-12 h-12 flex flex-col items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition" title="Stiker">
                                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/>
                                        <path d="M8 14s1.5 2 4 2 4-2 4-2" stroke-width="2" stroke-linecap="round"/>
                                        <circle cx="9" cy="10" r="1" fill="currentColor"/>
                                        <circle cx="15" cy="10" r="1" fill="currentColor"/>
                                    </svg>
                                    <span class="text-xs mt-0.5">Stiker</span>
                                </button>
                                <label class="w-12 h-12 flex flex-col items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition cursor-pointer" title="Gambar">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-xs mt-0.5">Gambar</span>
                                    <input type="file" id="imageInputMobile" accept="image/*" class="hidden">
                                </label>
                                <label class="w-12 h-12 flex flex-col items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition cursor-pointer" title="File">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                    </svg>
                                    <span class="text-xs mt-0.5">File</span>
                                    <input type="file" id="fileInputMobile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="hidden">
                                </label>
                                <button type="button" onclick="attachMenu.classList.add('hidden'); showPollModal()" class="w-12 h-12 flex flex-col items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition" title="Polling">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <span class="text-xs mt-0.5">Poll</span>
                                </button>
                            </div>
                        </form>
                        <!-- Voice Recording UI -->
                        <div id="voiceRecordingUI" class="hidden">
                            <div class="flex items-center gap-2">
                                <button type="button" id="cancelVoice" class="w-9 h-9 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-full transition flex-shrink-0" title="Batal">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                <div class="flex-1 flex items-center gap-2 px-3 py-2 bg-red-50 rounded-full">
                                    <div id="voiceWaveform" class="flex items-center gap-0.5">
                                        <div class="w-1 bg-red-500 rounded-full animate-pulse" style="height: 8px;"></div>
                                        <div class="w-1 bg-red-500 rounded-full animate-pulse" style="height: 14px; animation-delay: 0.1s;"></div>
                                        <div class="w-1 bg-red-500 rounded-full animate-pulse" style="height: 18px; animation-delay: 0.2s;"></div>
                                        <div class="w-1 bg-red-500 rounded-full animate-pulse" style="height: 12px; animation-delay: 0.3s;"></div>
                                        <div class="w-1 bg-red-500 rounded-full animate-pulse" style="height: 16px; animation-delay: 0.4s;"></div>
                                    </div>
                                    <span id="voiceTimer" class="text-sm font-mono text-red-600">00:00</span>
                                </div>
                                <button type="button" id="sendVoice" class="w-10 h-10 flex items-center justify-center bg-green-500 text-white rounded-full hover:bg-green-600 transition flex-shrink-0" title="Kirim">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- Emoji Picker Dropdown -->
                        <div id="emojiPicker" class="hidden">
                            <div class="fixed inset-0 bg-black/50 sm:hidden" onclick="document.getElementById('emojiPicker').classList.add('hidden')"></div>
                            <div class="fixed bottom-0 left-0 right-0 sm:absolute sm:bottom-full sm:left-4 sm:mb-2 z-[9999] w-full sm:w-auto bg-white sm:border sm:border-slate-200 rounded-t-2xl sm:rounded-xl shadow-lg p-3">
                            <div class="grid grid-cols-8 gap-1 max-h-48 overflow-y-auto">
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😀</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😂</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😍</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🥰</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😊</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😎</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😢</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😭</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😤</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😡</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤔</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤗</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">👍</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">👎</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">👏</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🙏</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">💪</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">❤️</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">💔</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">💯</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🔥</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">✨</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🎉</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🎊</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🥳</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😴</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤣</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😇</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🙄</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😏</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤩</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😋</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤤</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😱</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🥺</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😳</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤪</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">😜</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🤭</button>
                                <button type="button" class="emoji-btn text-xl p-1 hover:bg-slate-100 rounded">🥶</button>
                            </div>
                            </div>
                        </div>
                        <!-- Sticker Picker Dropdown -->
                        <div id="stickerPicker" class="hidden">
                            <div class="fixed inset-0 bg-black/50 sm:hidden" onclick="document.getElementById('stickerPicker').classList.add('hidden')"></div>
                            <div class="fixed bottom-0 left-0 right-0 sm:absolute sm:bottom-full sm:left-4 sm:mb-2 z-[9999] w-full sm:w-80 bg-white sm:border sm:border-slate-200 rounded-t-2xl sm:rounded-xl shadow-lg">
                            <!-- Tabs -->
                            <div class="flex border-b border-slate-200">
                                <button type="button" class="sticker-tab flex-1 px-3 py-2 text-xs font-medium text-secondary border-b-2 border-secondary" data-tab="my">Koleksi Saya</button>
                                <button type="button" class="sticker-tab flex-1 px-3 py-2 text-xs font-medium text-slate-500 hover:text-slate-700" data-tab="recent">Terbaru</button>
                                <button type="button" class="sticker-tab flex-1 px-3 py-2 text-xs font-medium text-slate-500 hover:text-slate-700" data-tab="emoji">Emoji</button>
                            </div>
                            <!-- Upload Button -->
                            <div class="p-2 border-b border-slate-100">
                                <label class="flex items-center justify-center gap-2 px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg cursor-pointer transition text-xs text-slate-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Upload Stiker (PNG/WEBP/GIF/JPG, max 1MB)
                                    <input type="file" id="stickerUpload" accept="image/png,image/webp,image/gif,image/jpeg" class="hidden">
                                </label>
                            </div>
                            <!-- Sticker Content -->
                            <div id="stickerContent" class="p-2 h-48 overflow-y-auto">
                                <div class="text-center text-slate-400 text-sm py-8">Memuat stiker...</div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pengumuman -->
                <?php if ($announcements && mysqli_num_rows($announcements) > 0): ?>
                <?php mysqli_data_seek($announcements, 0); ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">Pengumuman</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php while ($ann = mysqli_fetch_assoc($announcements)): ?>
                        <div class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0 <?= $ann['type'] == 'danger' ? 'bg-red-500' : ($ann['type'] == 'warning' ? 'bg-yellow-500' : ($ann['type'] == 'success' ? 'bg-green-500' : 'bg-blue-500')) ?>"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-slate-900"><?= htmlspecialchars($ann['title']) ?></p>
                                    <p class="text-sm text-slate-600 mt-1"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
                                    <p class="text-xs text-slate-400 mt-2"><?= date('d M Y, H:i', strtotime($ann['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if(!$event): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 lg:p-12 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2"><?= __('no_active_event') ?></h3>
                    <p class="text-slate-500"><?= __('wait_admin') ?></p>
                </div>
                <?php endif; ?>

                <!-- Riwayat Kehadiran -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900"><?= __('attendance_history') ?></h3>
                                <p class="text-sm text-slate-500"><?= $total_hadir ?> kehadiran tercatat</p>
                            </div>
                        </div>
                        <?php if($total_hadir > 0): ?>
                        <a href="riwayat.php" class="text-sm text-secondary hover:text-secondary/80 font-medium flex items-center gap-1">
                            Lihat Semua
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php if($riwayat && mysqli_num_rows($riwayat) > 0): 
                        $r = mysqli_fetch_assoc($riwayat);
                    ?>
                    <div class="px-6 pb-4">
                        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900"><?= htmlspecialchars($r['nama_event']) ?></p>
                                    <p class="text-sm text-slate-500"><?= date('d M Y, H:i', strtotime($r['waktu_absen'])) ?> WITA</p>
                                </div>
                            </div>
                            <span class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold">HADIR</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="px-6 pb-6">
                        <div class="bg-slate-50 rounded-xl p-6 text-center">
                            <div class="w-16 h-16 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-slate-500">Belum ada riwayat kehadiran</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">

                <!-- Streak & Missions -->
                <a href="missions.php" class="block bg-white rounded-2xl border border-slate-200 shadow-sm p-4 hover:shadow-md transition group">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-2xl">
                                🔥
                            </div>
                            <div>
                                <p class="text-sm text-slate-500">Login Streak</p>
                                <p class="text-xl font-bold text-slate-900"><?= $user_streak ?> Hari</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($pending_claims > 0): ?>
                                <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full"><?= $pending_claims ?></span>
                            <?php endif; ?>
                            <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                    <?php if ($pending_claims > 0): ?>
                        <p class="text-xs text-emerald-600 mt-2 font-medium">Ada reward yang bisa diklaim!</p>
                    <?php endif; ?>
                </a>

                <!-- Leaderboard & Poin -->
                <a href="leaderboard.php" class="block bg-gradient-to-r from-yellow-400 to-orange-500 rounded-2xl p-4 text-white hover:shadow-lg transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Peringkat Kamu</p>
                            <p class="text-3xl font-bold">#<?= $user_rank ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-white/80 text-sm">Poin</p>
                            <p class="text-2xl font-bold" id="userPointsDisplay"><?= $user_points ?></p>
                        </div>
                    </div>
                    <p class="text-white/60 text-xs mt-2"><span id="streakSmall"><?= $user_streak ?></span> hari streak 🔥 · Tap untuk detail →</p>
                </a>

                <!-- Badges -->
                <?php if (!empty($user_badges)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <h4 class="font-bold text-slate-900 mb-3">Badge Kamu</h4>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (array_slice($user_badges, 0, 6) as $badge): ?>
                        <div class="group relative">
                            <span class="text-2xl cursor-help"><?= $badge['icon'] ?></span>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none z-10">
                                <?= htmlspecialchars($badge['name']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($user_badges) > 6): ?>
                        <span class="text-sm text-slate-400 self-center">+<?= count($user_badges) - 6 ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <h4 class="font-bold text-slate-900 mb-2">Badge Kamu</h4>
                    <p class="text-slate-400 text-sm">Belum ada badge. Hadir di event untuk mendapatkan badge!</p>
                </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <h4 class="font-bold text-slate-900 mb-4"><?= __('statistics') ?></h4>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-secondary/10 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <span class="text-slate-600"><?= __('total_present') ?></span>
                            </div>
                            <span class="text-2xl font-bold text-slate-900"><?= $total_hadir ?></span>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                </div>
                                <span class="text-slate-600"><?= __('semester') ?></span>
                            </div>
                            <span class="text-2xl font-bold text-slate-900"><?= $user['semester'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <h4 class="font-bold text-slate-900 mb-4"><?= __('menu') ?></h4>
                    <div class="space-y-2">
                        <a href="profile.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                            <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900"><?= __('edit_profile') ?></p>
                                <p class="text-slate-400 text-sm"><?= __('change_class') ?></p>
                            </div>
                        </a>
                        <?php if($event): ?>
                        <a href="scan.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                            <div class="w-10 h-10 bg-accent/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900"><?= __('scan_qr') ?></p>
                                <p class="text-slate-400 text-sm"><?= __('attend_now') ?></p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <button onclick="showPermissionModal()" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition text-left">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900">Ajukan Izin/Sakit</p>
                                <p class="text-slate-400 text-sm"><?= $pending_perms > 0 ? $pending_perms . ' pengajuan pending' : 'Tidak bisa hadir?' ?></p>
                            </div>
                        </button>
                        <a href="iuran.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900">Iuran</p>
                                <p class="text-slate-400 text-sm"><?= $unpaid_iuran['count'] > 0 ? $unpaid_iuran['count'] . ' belum bayar' : 'Semua lunas' ?></p>
                            </div>
                        </a>
                        <a href="leaderboard.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900">Leaderboard</p>
                                <p class="text-slate-400 text-sm">Peringkat #<?= $user_rank ?> · <?= $user_points ?> poin</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-200 mt-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <p class="text-slate-400 text-sm text-center">
                <?= htmlspecialchars($s['footer_text'] ?? '© 2025 HIMA Politala') ?>
            </p>
        </div>
    </footer>

    <!-- Poll Create Modal -->
    <div id="pollModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-900">Buat Polling</h3>
                <button onclick="hidePollModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-4 overflow-y-auto max-h-[60vh]">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pertanyaan</label>
                    <input type="text" id="pollQuestion" placeholder="Apa pendapat kalian?" maxlength="500"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pilihan</label>
                    <div id="pollOptions" class="space-y-2">
                        <input type="text" class="poll-option w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm" placeholder="Pilihan 1" maxlength="100">
                        <input type="text" class="poll-option w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm" placeholder="Pilihan 2" maxlength="100">
                    </div>
                    <button type="button" onclick="addPollOption()" class="mt-2 text-sm text-secondary hover:underline">+ Tambah pilihan</button>
                </div>
                <div class="mb-4 flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input type="checkbox" id="pollMultiple" class="rounded border-slate-300 text-secondary focus:ring-secondary">
                        <span>Boleh pilih lebih dari satu</span>
                    </label>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Durasi</label>
                    <select id="pollDuration" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm">
                        <option value="0">Tanpa batas waktu</option>
                        <option value="1">1 jam</option>
                        <option value="6">6 jam</option>
                        <option value="12">12 jam</option>
                        <option value="24">1 hari</option>
                        <option value="72">3 hari</option>
                        <option value="168">1 minggu</option>
                    </select>
                </div>
            </div>
            <div class="p-4 border-t border-slate-100 flex gap-2">
                <button onclick="hidePollModal()" class="flex-1 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition">Batal</button>
                <button onclick="createPoll()" class="flex-1 py-2 bg-secondary text-white rounded-lg hover:bg-secondary/90 transition">Buat Poll</button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2"><?= __('logout_confirm') ?></h3>
            <p class="text-slate-500 mb-6"><?= __('logout_message') ?></p>
            <div class="flex gap-3">
                <button onclick="hideLogoutModal()" class="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-medium hover:bg-slate-200 transition">
                    <?= __('cancel') ?>
                </button>
                <a href="../auth/logout.php" class="flex-1 py-2.5 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition inline-block">
                    <?= __('yes_logout') ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Permission Request Modal -->
    <div id="permissionModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-slate-900 mb-4">Ajukan Izin/Sakit</h3>
            <form id="permissionForm" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Tipe</label>
                    <div class="flex gap-3">
                        <label class="flex-1 flex items-center justify-center gap-2 p-3 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-blue-500 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="radio" name="type" value="izin" class="sr-only" checked>
                            <span>📋 Izin</span>
                        </label>
                        <label class="flex-1 flex items-center justify-center gap-2 p-3 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-purple-500 transition has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50">
                            <input type="radio" name="type" value="sakit" class="sr-only">
                            <span>🏥 Sakit</span>
                        </label>
                    </div>
                </div>
                <?php if($event): ?>
                <div class="mb-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="for_event" id="forEventCheck" class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm text-slate-700">Untuk event: <?= htmlspecialchars($event['nama_event']) ?></span>
                    </label>
                    <input type="hidden" name="event_id" id="eventIdInput" value="">
                </div>
                <?php endif; ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Alasan <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition" placeholder="Jelaskan alasan Anda..."></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Lampiran (opsional)</label>
                    <input type="file" name="attachment" accept="image/*,.pdf" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                    <p class="text-xs text-slate-400 mt-1">JPG, PNG, GIF, PDF. Maks 5MB.</p>
                </div>
                <div id="permissionError" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm"></div>
                <div class="flex gap-3">
                    <button type="button" onclick="hidePermissionModal()" class="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-medium hover:bg-slate-200 transition">Batal</button>
                    <button type="submit" id="permissionSubmitBtn" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">Kirim</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showLogoutModal() {
        document.getElementById('logoutModal').classList.remove('hidden');
        document.getElementById('logoutModal').classList.add('flex');
    }
    function hideLogoutModal() {
        document.getElementById('logoutModal').classList.add('hidden');
        document.getElementById('logoutModal').classList.remove('flex');
    }
    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) hideLogoutModal();
    });

    // Permission Modal
    function showPermissionModal() {
        document.getElementById('permissionModal').classList.remove('hidden');
        document.getElementById('permissionModal').classList.add('flex');
    }
    function hidePermissionModal() {
        document.getElementById('permissionModal').classList.add('hidden');
        document.getElementById('permissionModal').classList.remove('flex');
        document.getElementById('permissionForm').reset();
        document.getElementById('permissionError').classList.add('hidden');
    }
    document.getElementById('permissionModal').addEventListener('click', function(e) {
        if (e.target === this) hidePermissionModal();
    });

    <?php if($event): ?>
    document.getElementById('forEventCheck').addEventListener('change', function() {
        document.getElementById('eventIdInput').value = this.checked ? '<?= $event['id'] ?>' : '';
    });
    <?php endif; ?>

    document.getElementById('permissionForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('permissionSubmitBtn');
        const errorDiv = document.getElementById('permissionError');
        
        btn.disabled = true;
        btn.textContent = 'Mengirim...';
        errorDiv.classList.add('hidden');
        
        const formData = new FormData(this);
        
        try {
            const res = await fetch('../api/permission_submit.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                hidePermissionModal();
                alert('Pengajuan berhasil dikirim! Tunggu konfirmasi dari admin.');
                location.reload();
            } else {
                errorDiv.textContent = data.error || 'Gagal mengirim pengajuan';
                errorDiv.classList.remove('hidden');
            }
        } catch (err) {
            errorDiv.textContent = 'Terjadi kesalahan. Coba lagi.';
            errorDiv.classList.remove('hidden');
        }
        
        btn.disabled = false;
        btn.textContent = 'Kirim';
    });

    // Push Notification Setup
    async function initPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push notifications not supported');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('../sw.js');
            console.log('Service Worker registered');

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('Notification permission denied');
                return;
            }

            // Get VAPID public key
            const response = await fetch('../api/vapid_keys.php');
            if (!response.ok) {
                console.log('VAPID keys not configured');
                return;
            }
            const { publicKey } = await response.json();

            // Subscribe to push
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });

            // Send subscription to server
            await fetch('../api/push_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });

            console.log('Push subscription successful');
        } catch (error) {
            console.error('Push subscription failed:', error);
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Initialize push notifications when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPushNotifications);
    } else {
        initPushNotifications();
    }

    // Forum Chat Realtime
    const CURRENT_USER_ID = <?= $user_id ?>;
    const BASE_URL = '<?= rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"])), "/") ?>/api';
    let lastMessageId = 0;
    let isPolling = true;
    let replyTo = null;
    let editingId = null;
    let isAdmin = <?= isset($_SESSION['admin_id']) ? 'true' : 'false' ?>;

    const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function formatTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }
    
    // Mark messages as read
    async function markMessagesAsRead(messageIds) {
        if (!messageIds || messageIds.length === 0) return;
        try {
            await fetch(`${BASE_URL}/message_read.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_ids: messageIds })
            });
        } catch (err) {
            console.error('Error marking messages as read:', err);
        }
    }
    
    // Read status icon (checkmarks like WhatsApp)
    function getReadStatusIcon(msg) {
        const readCount = msg.read_count || 0;
        const onlineCount = msg.online_count || 1;
        const allRead = msg.all_read || false;
        
        // SVG for single check (sent)
        const singleCheck = `<svg class="w-4 h-4 inline-block" viewBox="0 0 16 16" fill="currentColor"><path d="M12.354 4.354a.5.5 0 0 0-.708-.708L5 10.293 2.354 7.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0l7-7z"/></svg>`;
        
        // SVG for double check (read)
        const doubleCheck = `<svg class="w-4 h-4 inline-block" viewBox="0 0 16 16" fill="currentColor"><path d="M12.354 4.354a.5.5 0 0 0-.708-.708L5 10.293 2.354 7.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0l7-7z"/><path d="M15.354 4.354a.5.5 0 0 0-.708-.708L8 10.293l-.146-.147a.5.5 0 1 0-.708.708l.5.5a.5.5 0 0 0 .708 0l7-7z"/></svg>`;
        
        if (readCount === 0) {
            // No one has read yet - single gray check
            return `<span class="opacity-60" title="Terkirim">${singleCheck}</span>`;
        } else if (allRead) {
            // Everyone has read - double blue check
            return `<span class="text-blue-400" title="Dibaca semua">${doubleCheck}</span>`;
        } else {
            // Some have read - double gray check
            return `<span class="opacity-60" title="Dibaca ${readCount} orang">${doubleCheck}</span>`;
        }
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        if (date.toDateString() === today.toDateString()) {
            return 'Hari ini';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return 'Kemarin';
        }
        return `${DAYS[date.getDay()]}, ${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`;
    }

    function setReply(id, nama, message) {
        replyTo = { id, nama, message };
        const preview = document.getElementById('replyPreview');
        const text = message.length > 50 ? message.substring(0, 50) + '...' : message;
        preview.innerHTML = `
            <div class="flex items-center justify-between bg-slate-100 px-3 py-2 rounded-lg mb-2">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-secondary">${nama}</p>
                    <p class="text-xs text-slate-500 truncate">${text}</p>
                </div>
                <button type="button" onclick="cancelReply()" class="ml-2 text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        document.getElementById('chatInput').focus();
    }

    function cancelReply() {
        replyTo = null;
        document.getElementById('replyPreview').innerHTML = '';
    }

    function createMessageEl(msg, showDate = false) {
        const isMe = msg.user_id == CURRENT_USER_ID;
        const picture = msg.picture ? (msg.picture.startsWith('http') ? msg.picture : '../' + msg.picture) : '';
        const isDeleted = msg.is_deleted;
        const isEdited = msg.is_edited;
        const isPinned = msg.is_pinned;
        
        let dateHeader = '';
        if (showDate) {
            dateHeader = `<div class="text-center my-3"><span class="bg-slate-200 text-slate-600 text-xs px-3 py-1 rounded-full">${formatDate(msg.created_at)}</span></div>`;
        }
        
        // Pin indicator kecil di dalam pesan (opsional, karena sudah ada di banner)
        const pinIndicator = isPinned ? `<span class="text-amber-500 ml-1" title="Pesan disematkan">📌</span>` : '';
        
        const replyHtml = (!isDeleted && msg.reply_info) ? `
            <div class="bg-slate-100/50 border-l-2 border-secondary px-2 py-1 rounded mb-1 cursor-pointer" onclick="scrollToMessage(${msg.reply_to})">
                <p class="text-xs font-medium text-secondary">${msg.reply_info.nama}</p>
                <p class="text-xs text-slate-500 truncate">${msg.reply_info.message.substring(0, 40)}${msg.reply_info.message.length > 40 ? '...' : ''}</p>
            </div>
        ` : '';

        // Parse @mentions and URLs in message
        const parseMentions = (text) => {
            // Parse URLs first
            const urlRegex = /(https?:\/\/[^\s<]+)/g;
            text = text.replace(urlRegex, '<a href="$1" target="_blank" class="text-blue-500 underline hover:text-blue-700">$1</a>');
            // Parse mentions
            text = text.replace(/@(\w+(?:\s\w+)*)/g, '<span class="text-blue-500 font-medium">@$1</span>');
            return text;
        };
        
        // Check if message is a sticker (emoji or image) or poll
        const emojiStickerMatch = msg.message ? msg.message.match(/^\[sticker\](.+)\[\/sticker\]$/) : null;
        const imageStickerMatch = msg.message ? msg.message.match(/^\[sticker:(.+)\]$/) : null;
        const pollMatch = msg.message ? msg.message.match(/^\[poll:(\d+)\]$/) : null;
        const isEmojiSticker = emojiStickerMatch !== null;
        const isImageSticker = imageStickerMatch !== null;
        const isPoll = pollMatch !== null;
        
        // Image HTML - max-w-full agar tidak overflow
        const imageHtml = (!isDeleted && msg.image_url) ? `
            <div class="mt-2 mb-1">
                <img src="../${msg.image_url}" alt="Image" class="max-w-full max-h-60 rounded-lg cursor-pointer hover:opacity-90 transition object-contain" onclick="openImageModal('../${msg.image_url}')">
            </div>
        ` : '';
        
        // File HTML
        const getFileIcon = (ext) => {
            const icons = {
                'pdf': '📕',
                'doc': '📘', 'docx': '📘',
                'xls': '📗', 'xlsx': '📗',
                'ppt': '📙', 'pptx': '📙'
            };
            return icons[ext] || '📎';
        };
        const fileExt = msg.file_name ? msg.file_name.split('.').pop().toLowerCase() : '';
        const fileHtml = (!isDeleted && msg.file_url) ? `
            <div class="mt-2 mb-1">
                <a href="../${msg.file_url}" download="${msg.file_name || 'file'}" class="flex items-center gap-2 px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg transition ${isMe ? 'bg-white/20 hover:bg-white/30' : ''}">
                    <span class="text-2xl">${getFileIcon(fileExt)}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate ${isMe ? 'text-white' : 'text-slate-700'}">${msg.file_name || 'File'}</p>
                        <p class="text-xs ${isMe ? 'text-white/70' : 'text-slate-500'}">${fileExt.toUpperCase()}</p>
                    </div>
                    <svg class="w-5 h-5 ${isMe ? 'text-white/70' : 'text-slate-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                </a>
            </div>
        ` : '';
        
        // Voice HTML
        const formatDuration = (seconds) => {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        };
        const voiceHtml = (!isDeleted && msg.voice_url) ? `
            <div class="mt-2 mb-1">
                <div class="voice-message flex items-center gap-3 px-3 py-2 ${isMe ? 'bg-white/20' : 'bg-slate-100'} rounded-xl min-w-[200px]">
                    <button type="button" onclick="toggleVoicePlay(this, '../${msg.voice_url}')" class="voice-play-btn w-10 h-10 flex items-center justify-center ${isMe ? 'bg-white/30 hover:bg-white/40' : 'bg-secondary hover:bg-secondary/90'} rounded-full transition flex-shrink-0">
                        <svg class="play-icon w-5 h-5 ${isMe ? 'text-white' : 'text-white'}" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <svg class="pause-icon w-5 h-5 ${isMe ? 'text-white' : 'text-white'} hidden" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                        </svg>
                    </button>
                    <div class="flex-1">
                        <div class="voice-progress-container h-1.5 ${isMe ? 'bg-white/30' : 'bg-slate-300'} rounded-full overflow-hidden cursor-pointer" onclick="seekVoice(event, this)">
                            <div class="voice-progress h-full ${isMe ? 'bg-white' : 'bg-secondary'} rounded-full transition-all" style="width: 0%"></div>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="voice-current-time text-xs ${isMe ? 'text-white/70' : 'text-slate-500'}">0:00</span>
                            <span class="voice-duration text-xs ${isMe ? 'text-white/70' : 'text-slate-500'}">${formatDuration(msg.voice_duration || 0)}</span>
                        </div>
                    </div>
                    <svg class="w-5 h-5 ${isMe ? 'text-white/50' : 'text-slate-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                    </svg>
                </div>
            </div>
        ` : '';
        
        let messageContent;
        if (isDeleted) {
            messageContent = `<p class="text-sm italic ${isMe ? 'text-white/70' : 'text-slate-400'}">Pesan telah dihapus</p>`;
        } else if (isEmojiSticker) {
            messageContent = `<div class="text-6xl leading-none py-1">${emojiStickerMatch[1]}</div>`;
        } else if (isImageSticker) {
            const stickerUrl = imageStickerMatch[1];
            messageContent = `
                <div class="relative group/sticker">
                    <img src="../${stickerUrl}" class="w-32 h-32 object-contain" alt="sticker">
                    ${!isMe ? `<button onclick="saveSticker('${stickerUrl}')" class="absolute top-0 right-0 p-1 bg-black/50 text-white rounded-bl-lg opacity-0 group-hover/sticker:opacity-100 transition text-xs" title="Simpan Stiker">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </button>` : ''}
                </div>`;
        } else if (isPoll) {
            const pollId = pollMatch[1];
            messageContent = `<div class="poll-container" data-poll-id="${pollId}"><p class="text-sm text-slate-400">Memuat polling...</p></div>`;
        } else {
            messageContent = `${msg.message ? `<p class="text-sm break-words whitespace-pre-wrap" id="msg-text-${msg.id}">${parseMentions(msg.message)}</p>` : ''}${imageHtml}${fileHtml}${voiceHtml}`;
        }

        const editedLabel = (isEdited && !isDeleted) ? `<span class="text-xs ${isMe ? 'text-white/50' : 'text-slate-400'} ml-1">· diedit</span>` : '';
        
        // Tombol-tombol aksi
        const reactionButton = `
            <button onclick="showReactionPicker(event, ${msg.id})" class="p-1.5 text-slate-400 hover:text-yellow-500 hover:bg-yellow-50 rounded" title="Tambah Reaksi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        `;
        
        const pinButton = `
            <button onclick="togglePin(${msg.id}, ${isPinned})" 
                class="p-1.5 ${isPinned ? 'text-amber-500 bg-amber-50' : 'text-slate-400'} hover:text-amber-600 hover:bg-amber-50 rounded" title="${isPinned ? 'Lepas Pin' : 'Pin Pesan'}">
                <svg class="w-4 h-4" fill="${isPinned ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L10 6.477V16h2a1 1 0 110 2H8a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/>
                </svg>
            </button>
        `;
        
        const replyButton = `
            <button onclick="setReply(${msg.id}, '${msg.nama.replace(/'/g, "\\'")}', '${(msg.message || '').replace(/'/g, "\\'").replace(/\n/g, ' ')}')" 
                class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded" title="Balas">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                </svg>
            </button>
        `;

        const actionButtons = (isMe && !isDeleted) ? `
            <div class="absolute ${isMe ? 'right-0' : 'left-0'} bottom-full mb-1 opacity-0 group-hover:opacity-100 flex gap-0.5 bg-white rounded-lg shadow-lg border border-slate-200 p-0.5 transition z-10">
                ${reactionButton}
                ${replyButton}
                ${pinButton}
                <button onclick="startEdit(${msg.id}, '${(msg.message || '').replace(/'/g, "\\'").replace(/\n/g, '\\n')}')" 
                    class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-slate-100 rounded" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </button>
                <button onclick="deleteMessage(${msg.id}, false)" 
                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        ` : (isMe && isDeleted) ? `
            <button onclick="deleteMessage(${msg.id}, true)" 
                class="absolute ${isMe ? 'right-0' : 'left-0'} bottom-full mb-1 opacity-0 group-hover:opacity-100 p-1.5 text-slate-400 hover:text-red-600 bg-white rounded-lg shadow-lg border transition z-10" title="Hapus Permanen">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        ` : (!isDeleted ? `
            <div class="absolute ${isMe ? 'right-0' : 'left-0'} bottom-full mb-1 opacity-0 group-hover:opacity-100 flex gap-0.5 bg-white rounded-lg shadow-lg border border-slate-200 p-0.5 transition z-10">
                ${reactionButton}
                ${replyButton}
                ${pinButton}
            </div>
        ` : '');
        
        return `
            ${dateHeader}
            <div class="flex gap-2 ${isMe ? 'flex-row-reverse' : ''} group" id="msg-${msg.id}">
                ${!isMe && picture ? 
                    `<img src="${picture}" class="w-8 h-8 rounded-full object-cover flex-shrink-0 mt-auto">` :
                    !isMe ? `<div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center flex-shrink-0 mt-auto">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>` : ''
                }
                <div class="max-w-[75%] relative">
                    <div class="${isMe ? 'bg-secondary text-white' : 'bg-white border border-slate-200'} px-3 py-2 rounded-xl ${isMe ? 'rounded-tr-sm' : 'rounded-tl-sm'} ${isDeleted ? 'opacity-70' : ''} break-words overflow-hidden">
                        ${!isMe ? `<p class="text-xs font-semibold ${isMe ? 'text-white/80' : 'text-secondary'} mb-1">${msg.nama}${pinIndicator}</p>` : ''}
                        ${replyHtml}
                        ${messageContent}
                        <p class="text-xs ${isMe ? 'text-white/60' : 'text-slate-400'} mt-1 text-right flex items-center justify-end gap-1">${isMe && isPinned ? '📌 ' : ''}${formatTime(msg.created_at)}${editedLabel}${isMe ? getReadStatusIcon(msg) : ''}</p>
                    </div>
                    <!-- Reactions Display -->
                    <div class="reactions-display flex flex-wrap gap-1 mt-1 ${isMe ? 'justify-end' : ''}" id="reactions-${msg.id}"></div>
                    ${actionButtons}
                </div>
            </div>
        `;
    }

    function scrollToMessage(id) {
        const el = document.getElementById('msg-' + id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('bg-yellow-50');
            setTimeout(() => el.classList.remove('bg-yellow-50'), 2000);
        }
    }

    function startEdit(id, message) {
        editingId = id;
        const input = document.getElementById('chatInput');
        input.value = message.replace(/\\n/g, '\n');
        input.focus();
        
        const preview = document.getElementById('replyPreview');
        preview.innerHTML = `
            <div class="flex items-center justify-between bg-blue-50 px-3 py-2 rounded-lg mb-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span class="text-sm text-blue-700">Mengedit pesan</span>
                </div>
                <button type="button" onclick="cancelEdit()" class="text-blue-400 hover:text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
    }

    function cancelEdit() {
        editingId = null;
        document.getElementById('chatInput').value = '';
        document.getElementById('replyPreview').innerHTML = '';
    }

    async function deleteMessage(id, permanent = false) {
        const confirmMsg = permanent ? 'Hapus permanen pesan ini? Pesan tidak dapat dikembalikan.' : 'Hapus pesan ini?';
        if (!confirm(confirmMsg)) return;
        
        try {
            const res = await fetch(`${BASE_URL}/forum_delete.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, permanent: permanent })
            });
            
            const data = await res.json();
            if (data.success) {
                lastMessageId = 0;
                await loadMessages();
            } else {
                alert(data.error || 'Gagal menghapus pesan');
            }
        } catch (err) {
            console.error('Error deleting message:', err);
        }
    }

    async function togglePin(id, currentlyPinned) {
        try {
            const res = await fetch(`${BASE_URL}/forum_pin.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, action: currentlyPinned ? 'unpin' : 'pin' })
            });
            
            const data = await res.json();
            if (data.success) {
                lastMessageId = 0;
                await loadMessages();
            } else {
                alert(data.error || 'Gagal mengubah status pin');
            }
        } catch (err) {
            console.error('Error toggling pin:', err);
        }
    }

    async function editMessage(id, message) {
        try {
            const res = await fetch(`${BASE_URL}/forum_edit.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, message: message })
            });
            
            const data = await res.json();
            if (data.success) {
                cancelEdit();
                lastMessageId = 0;
                await loadMessages();
                return true;
            } else {
                alert(data.error || 'Gagal mengedit pesan');
                return false;
            }
        } catch (err) {
            console.error('Error editing message:', err);
            return false;
        }
    }

    let lastDate = '';
    let pinnedMessages = [];
    
    function renderPinnedBanner() {
        const banner = document.getElementById('pinnedBanner');
        if (pinnedMessages.length === 0) {
            banner.classList.add('hidden');
            banner.innerHTML = '';
            return;
        }
        
        banner.classList.remove('hidden');
        let html = '';
        pinnedMessages.forEach((msg, index) => {
            const picture = msg.picture ? (msg.picture.startsWith('http') ? msg.picture : '../' + msg.picture) : '';
            const emojiStickerPreview = msg.message ? msg.message.match(/^\[sticker\](.+)\[\/sticker\]$/) : null;
            const imageStickerPreview = msg.message ? msg.message.match(/^\[sticker:(.+)\]$/) : null;
            const previewText = emojiStickerPreview ? `🎭 ${emojiStickerPreview[1]}` : (imageStickerPreview ? '🎭 Stiker' : (msg.message ? (msg.message.length > 50 ? msg.message.substring(0, 50) + '...' : msg.message) : (msg.image_url ? '📷 Gambar' : (msg.file_url ? '📎 File' : ''))));
            html += `
                <div class="flex items-center gap-3 px-4 py-2 ${index > 0 ? 'border-t border-amber-200' : ''} cursor-pointer hover:bg-amber-100 transition" onclick="scrollToMessage(${msg.id})">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L10 6.477V16h2a1 1 0 110 2H8a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/>
                        </svg>
                        ${picture ? `<img src="${picture}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">` : ''}
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-semibold text-amber-800">${msg.nama}</span>
                            <p class="text-xs text-amber-700 truncate">${previewText}</p>
                        </div>
                    </div>
                    <button onclick="event.stopPropagation(); togglePin(${msg.id}, true)" class="p-1 text-amber-600 hover:text-red-600 flex-shrink-0" title="Lepas Pin">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
        });
        banner.innerHTML = html;
    }
    
    let loadingMessages = false;
    async function loadMessages() {
        // Prevent concurrent calls
        if (loadingMessages) return;
        loadingMessages = true;
        
        try {
            const res = await fetch(`${BASE_URL}/forum_messages.php?last_id=${lastMessageId}`);
            const data = await res.json();
            
            // Update isAdmin dari response API
            if (typeof data.is_admin !== 'undefined') {
                isAdmin = data.is_admin;
            }
            
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('chatMessages');
                
                // Pisahkan pesan yang di-pin dan yang biasa
                if (lastMessageId === 0) {
                    pinnedMessages = data.messages.filter(m => m.is_pinned && !m.is_deleted);
                    renderPinnedBanner();
                }
                
                if (lastMessageId === 0) {
                    lastDate = '';
                    let html = '';
                    data.messages.forEach(msg => {
                        const msgDate = new Date(msg.created_at).toDateString();
                        const showDate = msgDate !== lastDate;
                        if (showDate) lastDate = msgDate;
                        html += createMessageEl(msg, showDate);
                    });
                    container.innerHTML = html || '<div class="text-center text-slate-400 text-sm py-8">Belum ada pesan. Mulai diskusi!</div>';
                } else {
                    // Check for new pinned messages
                    const newPinned = data.messages.filter(m => m.is_pinned && !m.is_deleted);
                    if (newPinned.length > 0) {
                        newPinned.forEach(np => {
                            if (!pinnedMessages.find(p => p.id === np.id)) {
                                pinnedMessages.push(np);
                            }
                        });
                        renderPinnedBanner();
                    }
                    
                    data.messages.forEach(msg => {
                        // Skip jika pesan sudah ada di DOM
                        if (document.getElementById('msg-' + msg.id)) return;
                        
                        const msgDate = new Date(msg.created_at).toDateString();
                        const showDate = msgDate !== lastDate;
                        if (showDate) lastDate = msgDate;
                        container.insertAdjacentHTML('beforeend', createMessageEl(msg, showDate));
                    });
                }
                
                if (data.messages.length > 0) {
                    lastMessageId = Math.max(...data.messages.map(m => m.id));
                    
                    // Mark messages as read (only messages from other users)
                    const unreadMsgIds = data.messages
                        .filter(m => m.user_id != CURRENT_USER_ID && !m.is_deleted)
                        .map(m => m.id);
                    if (unreadMsgIds.length > 0) {
                        markMessagesAsRead(unreadMsgIds);
                    }
                }
                
                container.scrollTop = container.scrollHeight;
            } else if (lastMessageId === 0) {
                document.getElementById('chatMessages').innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Belum ada pesan. Mulai diskusi!</div>';
                pinnedMessages = [];
                renderPinnedBanner();
            }
        } catch (err) {
            console.error('Error loading messages:', err);
        } finally {
            loadingMessages = false;
        }
    }

    let sendLock = false;
    async function sendMessage(message, imageUrl = null, fileUrl = null, fileName = null) {
        // Prevent double send
        if (sendLock) return false;
        sendLock = true;
        
        try {
            const body = { message: message };
            if (replyTo) body.reply_to = replyTo.id;
            if (imageUrl) body.image_url = imageUrl;
            if (fileUrl) {
                body.file_url = fileUrl;
                body.file_name = fileName;
            }
            
            const res = await fetch(`${BASE_URL}/forum_send.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            
            const data = await res.json();
            sendLock = false;
            if (data.success) {
                cancelReply();
                return true;
            } else {
                alert(data.error || 'Gagal mengirim pesan');
                return false;
            }
        } catch (err) {
            console.error('Error sending message:', err);
            sendLock = false;
            return false;
        }
    }

    let isSending = false;
    document.getElementById('chatForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Prevent double submit
        if (isSending) return;
        
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        
        if (message || pendingImage || pendingFile) {
            isSending = true;
            input.disabled = true;
            document.getElementById('sendBtn').disabled = true;
            let success;
            
            if (editingId) {
                success = await editMessage(editingId, message);
            } else {
                success = await sendMessage(message, pendingImage, pendingFile, pendingFileName);
            }
            
            if (success) {
                input.value = '';
                input.style.height = 'auto'; // Reset textarea height
                cancelImage();
                cancelFile();
                await loadMessages();
            }
            input.disabled = false;
            document.getElementById('sendBtn').disabled = false;
            isSending = false;
            input.focus();
        }
    });

    // Image upload handling
    let pendingImage = null;
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    
    imageInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB');
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.innerHTML = `
                <div class="relative inline-block">
                    <img src="${e.target.result}" class="h-20 rounded-lg border border-slate-200">
                    <button type="button" onclick="cancelImage()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            imagePreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
        
        // Upload image
        const formData = new FormData();
        formData.append('image', file);
        
        try {
            const res = await fetch(`${BASE_URL}/forum_upload.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                pendingImage = data.image_url;
            } else {
                alert(data.error || 'Gagal upload gambar');
                cancelImage();
            }
        } catch (err) {
            console.error('Upload error:', err);
            alert('Gagal upload gambar');
            cancelImage();
        }
        
        imageInput.value = '';
    });
    
    function cancelImage() {
        pendingImage = null;
        imagePreview.innerHTML = '';
        imagePreview.classList.add('hidden');
    }
    
    // File upload handling
    let pendingFile = null;
    let pendingFileName = null;
    const fileInput = document.getElementById('fileInput');
    
    fileInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB');
            return;
        }
        
        // Show preview
        const ext = file.name.split('.').pop().toLowerCase();
        const icons = { 'pdf': '📕', 'doc': '📘', 'docx': '📘', 'xls': '📗', 'xlsx': '📗', 'ppt': '📙', 'pptx': '📙' };
        imagePreview.innerHTML = `
            <div class="relative inline-flex items-center gap-2 px-3 py-2 bg-slate-100 rounded-lg">
                <span class="text-2xl">${icons[ext] || '📎'}</span>
                <span class="text-sm text-slate-700">${file.name}</span>
                <button type="button" onclick="cancelFile()" class="ml-2 text-red-500 hover:text-red-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        imagePreview.classList.remove('hidden');
        
        // Upload file
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const res = await fetch(`${BASE_URL}/forum_upload.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                pendingFile = data.file_url;
                pendingFileName = data.file_name || file.name;
            } else {
                alert(data.error || 'Gagal upload file');
                cancelFile();
            }
        } catch (err) {
            console.error('Upload error:', err);
            alert('Gagal upload file');
            cancelFile();
        }
        
        fileInput.value = '';
    });
    
    function cancelFile() {
        pendingFile = null;
        pendingFileName = null;
        imagePreview.innerHTML = '';
        imagePreview.classList.add('hidden');
    }
    window.cancelFile = cancelFile;
    
    // Emoji picker
    const emojiBtn = document.getElementById('emojiBtn');
    const emojiPicker = document.getElementById('emojiPicker');
    
    emojiBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        emojiPicker.classList.toggle('hidden');
    });
    
    document.querySelectorAll('.emoji-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = document.getElementById('chatInput');
            const emoji = this.textContent;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.substring(0, start) + emoji + input.value.substring(end);
            input.focus();
            input.selectionStart = input.selectionEnd = start + emoji.length;
            emojiPicker.classList.add('hidden');
        });
    });
    
    // Sticker picker
    const stickerBtn = document.getElementById('stickerBtn');
    const stickerPicker = document.getElementById('stickerPicker');
    const stickerContent = document.getElementById('stickerContent');
    let stickerData = null;
    let currentStickerTab = 'my';
    
    const defaultEmojis = ['👍','👎','❤️','🔥','😂','😭','😍','🥺','🤣','😎','🥳','🎉','💯','✨','💪','🙏','👏','🤝','👀','💀','🤡','😈','👻','💩','🐶','🐱','🦊','🐸','🌈','☀️','🌙','⭐'];
    
    async function loadStickers() {
        try {
            const res = await fetch(`${BASE_URL}/sticker_list.php`);
            stickerData = await res.json();
            renderStickers();
        } catch (err) {
            console.error('Error loading stickers:', err);
            stickerContent.innerHTML = '<div class="text-center text-red-400 text-sm py-4">Gagal memuat stiker</div>';
        }
    }
    
    function renderStickers() {
        let html = '';
        
        if (currentStickerTab === 'my') {
            if (stickerData?.my_stickers?.length > 0) {
                html = '<div class="grid grid-cols-4 gap-2">';
                stickerData.my_stickers.forEach(s => {
                    html += `<button type="button" class="sticker-item p-1 hover:bg-slate-100 rounded-lg transition" data-url="${s.file_url}">
                        <img src="../${s.file_url}" class="w-14 h-14 object-contain" alt="sticker">
                    </button>`;
                });
                html += '</div>';
            } else {
                html = '<div class="text-center text-slate-400 text-xs py-6">Belum ada stiker. Upload atau curi stiker dari chat!</div>';
            }
        } else if (currentStickerTab === 'recent') {
            if (stickerData?.recent?.length > 0) {
                html = '<div class="grid grid-cols-4 gap-2">';
                stickerData.recent.forEach(s => {
                    html += `<button type="button" class="sticker-item p-1 hover:bg-slate-100 rounded-lg transition relative group" data-url="${s.file_url}" data-id="${s.id}">
                        <img src="../${s.file_url}" class="w-14 h-14 object-contain" alt="sticker">
                        <span class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </span>
                    </button>`;
                });
                html += '</div>';
            } else {
                html = '<div class="text-center text-slate-400 text-xs py-6">Belum ada stiker terbaru</div>';
            }
        } else if (currentStickerTab === 'emoji') {
            html = '<div class="grid grid-cols-5 gap-1">';
            defaultEmojis.forEach(e => {
                html += `<button type="button" class="emoji-sticker text-3xl p-2 hover:bg-slate-100 rounded-lg transition">${e}</button>`;
            });
            html += '</div>';
        }
        
        stickerContent.innerHTML = html;
        bindStickerEvents();
    }
    
    function bindStickerEvents() {
        document.querySelectorAll('.sticker-item').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                const url = this.dataset.url;
                const id = this.dataset.id;
                
                // If on recent tab and not in my collection, save first
                if (currentStickerTab === 'recent' && id) {
                    await fetch(`${BASE_URL}/sticker_save.php`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({action: 'save', sticker_id: parseInt(id)})
                    });
                }
                
                stickerPicker.classList.add('hidden');
                await sendMessage(`[sticker:${url}]`);
                lastMessageId = 0;
                await loadMessages();
            });
        });
        
        document.querySelectorAll('.emoji-sticker').forEach(btn => {
            btn.addEventListener('click', async function() {
                const emoji = this.textContent;
                stickerPicker.classList.add('hidden');
                await sendMessage(`[sticker]${emoji}[/sticker]`);
                lastMessageId = 0;
                await loadMessages();
            });
        });
    }
    
    // Tab switching
    document.querySelectorAll('.sticker-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sticker-tab').forEach(t => {
                t.classList.remove('text-secondary', 'border-b-2', 'border-secondary');
                t.classList.add('text-slate-500');
            });
            this.classList.remove('text-slate-500');
            this.classList.add('text-secondary', 'border-b-2', 'border-secondary');
            currentStickerTab = this.dataset.tab;
            renderStickers();
        });
    });
    
    // Upload sticker
    document.getElementById('stickerUpload').addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('sticker', file);
        
        stickerContent.innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Mengupload stiker...</div>';
        
        try {
            const res = await fetch(`${BASE_URL}/sticker_upload.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                await loadStickers();
                currentStickerTab = 'my';
                document.querySelectorAll('.sticker-tab').forEach(t => {
                    t.classList.remove('text-secondary', 'border-b-2', 'border-secondary');
                    t.classList.add('text-slate-500');
                });
                document.querySelector('.sticker-tab[data-tab="my"]').classList.remove('text-slate-500');
                document.querySelector('.sticker-tab[data-tab="my"]').classList.add('text-secondary', 'border-b-2', 'border-secondary');
                renderStickers();
            } else {
                alert(data.error || 'Gagal upload stiker');
                renderStickers();
            }
        } catch (err) {
            console.error('Upload error:', err);
            alert('Gagal upload stiker');
            renderStickers();
        }
        
        this.value = '';
    });
    
    stickerBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        emojiPicker.classList.add('hidden');
        if (stickerPicker.classList.contains('hidden')) {
            stickerPicker.classList.remove('hidden');
            if (!stickerData) loadStickers();
        } else {
            stickerPicker.classList.add('hidden');
        }
    });
    
    emojiBtn.addEventListener('click', function() {
        stickerPicker.classList.add('hidden');
    });
    
    document.addEventListener('click', function(e) {
        if (!emojiPicker.contains(e.target) && e.target !== emojiBtn) {
            emojiPicker.classList.add('hidden');
        }
        if (!stickerPicker.contains(e.target) && e.target !== stickerBtn) {
            stickerPicker.classList.add('hidden');
        }
        if (!mentionDropdown.contains(e.target)) {
            mentionDropdown.classList.add('hidden');
        }
    });
    
    // Mention autocomplete
    const mentionDropdown = document.getElementById('mentionDropdown');
    let mentionSearch = '';
    let mentionStart = -1;
    
    document.getElementById('chatInput').addEventListener('input', async function(e) {
        const value = this.value;
        const cursorPos = this.selectionStart;
        
        // Find @ before cursor
        let atPos = -1;
        for (let i = cursorPos - 1; i >= 0; i--) {
            if (value[i] === '@') {
                atPos = i;
                break;
            }
            if (value[i] === ' ' && i < cursorPos - 1) break;
        }
        
        if (atPos >= 0) {
            mentionSearch = value.substring(atPos + 1, cursorPos);
            mentionStart = atPos;
            
            if (mentionSearch.length >= 1) {
                try {
                    const res = await fetch(`${BASE_URL}/forum_users.php?q=${encodeURIComponent(mentionSearch)}`);
                    const data = await res.json();
                    
                    if (data.users && data.users.length > 0) {
                        mentionDropdown.innerHTML = data.users.map(u => `
                            <div class="flex items-center gap-2 px-3 py-2 hover:bg-slate-100 cursor-pointer mention-item" data-nama="${u.nama}">
                                <img src="${u.picture ? (u.picture.startsWith('http') ? u.picture : '../' + u.picture) : '../uploads/profiles/default.png'}" class="w-6 h-6 rounded-full">
                                <span class="text-sm">${u.nama}</span>
                            </div>
                        `).join('');
                        
                        const inputRect = this.getBoundingClientRect();
                        mentionDropdown.style.left = inputRect.left + 'px';
                        mentionDropdown.style.bottom = (window.innerHeight - inputRect.top + 5) + 'px';
                        mentionDropdown.style.width = inputRect.width + 'px';
                        mentionDropdown.classList.remove('hidden');
                        
                        document.querySelectorAll('.mention-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const nama = this.dataset.nama;
                                const input = document.getElementById('chatInput');
                                const before = input.value.substring(0, mentionStart);
                                const after = input.value.substring(input.selectionStart);
                                input.value = before + '@' + nama + ' ' + after;
                                input.focus();
                                mentionDropdown.classList.add('hidden');
                            });
                        });
                    } else {
                        mentionDropdown.classList.add('hidden');
                    }
                } catch (err) {
                    console.error('Mention search error:', err);
                }
            } else {
                mentionDropdown.classList.add('hidden');
            }
        } else {
            mentionDropdown.classList.add('hidden');
        }
    });
    
    // Image modal
    function openImageModal(src) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4';
        modal.onclick = () => modal.remove();
        modal.innerHTML = `
            <img src="${src}" class="max-w-full max-h-full rounded-lg">
            <button class="absolute top-4 right-4 text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        `;
        document.body.appendChild(modal);
    }
    async function saveSticker(url) {
        try {
            const res = await fetch(`${BASE_URL}/sticker_save.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'save', file_url: url})
            });
            const data = await res.json();
            if (data.success) {
                alert('Stiker berhasil disimpan ke koleksi!');
                stickerData = null; // Reset to reload next time
            } else {
                alert(data.error || 'Gagal menyimpan stiker');
            }
        } catch (err) {
            console.error('Error saving sticker:', err);
            alert('Gagal menyimpan stiker');
        }
    }
    
    window.openImageModal = openImageModal;
    window.cancelImage = cancelImage;
    window.saveSticker = saveSticker;

    // Load forum wallpaper settings
    async function loadForumWallpaper() {
        try {
            const res = await fetch(`${BASE_URL}/forum_wallpaper.php`);
            const data = await res.json();
            if (data.success) {
                const wallpaperBg = document.getElementById('forumWallpaperBg');
                const wallpaperOverlay = document.getElementById('forumWallpaperOverlay');
                
                if (data.wallpaper) {
                    const wpUrl = data.wallpaper.startsWith('http') ? data.wallpaper : '../' + data.wallpaper;
                    wallpaperBg.style.backgroundImage = `url('${wpUrl}')`;
                    wallpaperOverlay.style.opacity = data.opacity || 0.5;
                } else {
                    wallpaperBg.style.backgroundImage = '';
                    wallpaperOverlay.style.opacity = 0;
                    document.getElementById('chatMessagesWrapper').classList.add('bg-slate-50');
                }
            }
        } catch (err) {
            console.error('Error loading wallpaper:', err);
            document.getElementById('chatMessagesWrapper').classList.add('bg-slate-50');
        }
    }

    loadForumWallpaper();

    // ==================== MOBILE ATTACHMENT MENU ====================
    const attachMenuBtn = document.getElementById('attachMenuBtn');
    const attachMenu = document.getElementById('attachMenu');
    
    attachMenuBtn?.addEventListener('click', () => {
        attachMenu.classList.toggle('hidden');
    });
    
    // Mobile buttons handlers
    document.getElementById('emojiBtnMobile')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        attachMenu.classList.add('hidden');
        const picker = document.getElementById('emojiPicker');
        const stickerPicker = document.getElementById('stickerPicker');
        if (picker) {
            picker.classList.toggle('hidden');
            stickerPicker?.classList.add('hidden');
        }
    });
    
    document.getElementById('stickerBtnMobile')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        attachMenu.classList.add('hidden');
        const picker = document.getElementById('stickerPicker');
        const emojiPicker = document.getElementById('emojiPicker');
        if (picker) {
            picker.classList.toggle('hidden');
            emojiPicker?.classList.add('hidden');
            loadStickers();
        }
    });
    
    // Close picker when clicking overlay (mobile)
    document.getElementById('emojiPicker')?.addEventListener('click', (e) => {
        if (e.target.id === 'emojiPicker') {
            e.target.classList.add('hidden');
        }
    });
    document.getElementById('stickerPicker')?.addEventListener('click', (e) => {
        if (e.target.id === 'stickerPicker') {
            e.target.classList.add('hidden');
        }
    });
    
    // Mobile image input
    document.getElementById('imageInputMobile')?.addEventListener('change', async function(e) {
        attachMenu.classList.add('hidden');
        const file = e.target.files[0];
        if (!file) return;
        
        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 10MB');
            return;
        }
        
        // Use same handler as desktop
        const formData = new FormData();
        formData.append('image', file);
        
        try {
            const res = await fetch(`${BASE_URL}/forum_upload.php`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                pendingImage = data.image_url;
                imagePreview.innerHTML = `
                    <div class="relative inline-block">
                        <img src="../${data.image_url}" class="h-20 rounded-lg">
                        <button type="button" onclick="cancelImage()" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center">×</button>
                    </div>
                `;
                imagePreview.classList.remove('hidden');
            }
        } catch (err) {
            console.error('Upload error:', err);
        }
        this.value = '';
    });
    
    // Mobile file input
    document.getElementById('fileInputMobile')?.addEventListener('change', async function(e) {
        attachMenu.classList.add('hidden');
        const file = e.target.files[0];
        if (!file) return;
        
        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB');
            return;
        }
        
        const ext = file.name.split('.').pop().toLowerCase();
        const icons = { 'pdf': '📕', 'doc': '📘', 'docx': '📘', 'xls': '📗', 'xlsx': '📗', 'ppt': '📙', 'pptx': '📙' };
        imagePreview.innerHTML = `
            <div class="relative inline-flex items-center gap-2 px-3 py-2 bg-slate-100 rounded-lg">
                <span class="text-2xl">${icons[ext] || '📎'}</span>
                <span class="text-sm text-slate-700">${file.name}</span>
                <button type="button" onclick="cancelFile()" class="ml-2 text-red-500 hover:text-red-700">×</button>
            </div>
        `;
        imagePreview.classList.remove('hidden');
        
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const res = await fetch(`${BASE_URL}/forum_upload.php`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                pendingFile = data.file_url;
                pendingFileName = data.file_name;
            }
        } catch (err) {
            console.error('Upload error:', err);
        }
        this.value = '';
    });
    
    // ==================== DARK MODE ====================
    function toggleDarkMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', isDark);
        document.body.style.background = isDark ? '#0a0a0a' : '';
        
        // Toggle icons
        document.getElementById('sunIcon').classList.toggle('hidden', !isDark);
        document.getElementById('moonIcon').classList.toggle('hidden', isDark);
    }
    
    // Apply dark mode on load
    if (document.documentElement.classList.contains('dark')) {
        document.body.style.background = '#0a0a0a';
        document.getElementById('sunIcon').classList.remove('hidden');
        document.getElementById('moonIcon').classList.add('hidden');
    }

    // ==================== SEARCH MESSAGES ====================
    const forumSearch = document.getElementById('forumSearch');
    const searchResults = document.getElementById('searchResults');
    let searchDebounce = null;

    forumSearch.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchDebounce);
        
        if (query.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            return;
        }
        
        searchDebounce = setTimeout(async () => {
            try {
                const res = await fetch(`${BASE_URL}/forum_search.php?q=${encodeURIComponent(query)}`);
                const data = await res.json();
                
                if (data.success && data.messages.length > 0) {
                    let html = `<div class="p-2 text-xs text-slate-500 dark:text-slate-400 border-b dark:border-slate-600">${data.count} hasil ditemukan</div>`;
                    data.messages.forEach(msg => {
                        const pic = msg.picture ? (msg.picture.startsWith('http') ? msg.picture : '../' + msg.picture) : '';
                        const preview = msg.message.length > 60 ? msg.message.substring(0, 60) + '...' : msg.message;
                        const highlighted = preview.replace(new RegExp(query, 'gi'), '<mark class="bg-yellow-200 dark:bg-yellow-700 rounded px-0.5">$&</mark>');
                        html += `
                            <div class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer border-b dark:border-slate-600 last:border-0" onclick="scrollToMessage(${msg.id}); searchResults.classList.add('hidden'); forumSearch.value = '';">
                                <div class="flex items-center gap-2 mb-1">
                                    ${pic ? `<img src="${pic}" class="w-5 h-5 rounded-full object-cover">` : ''}
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">${msg.nama}</span>
                                    <span class="text-xs text-slate-400">${formatTime(msg.created_at)}</span>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-slate-300">${highlighted}</p>
                            </div>
                        `;
                    });
                    searchResults.innerHTML = html;
                    searchResults.classList.remove('hidden');
                } else {
                    searchResults.innerHTML = `<div class="p-4 text-center text-slate-400">Tidak ada hasil</div>`;
                    searchResults.classList.remove('hidden');
                }
            } catch (err) {
                console.error('Search error:', err);
            }
        }, 300);
    });

    forumSearch.addEventListener('blur', () => {
        setTimeout(() => searchResults.classList.add('hidden'), 200);
    });

    // ==================== REACTIONS ====================
    const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡', '🎉', '🔥', '👏', '💯'];
    let reactionPickerMsgId = null;

    function showReactionPicker(event, msgId) {
        event.stopPropagation();
        reactionPickerMsgId = msgId;
        closeReactionDetail();
        
        // Remove existing picker
        const existing = document.getElementById('reactionPicker');
        if (existing) existing.remove();
        
        // Create picker
        const picker = document.createElement('div');
        picker.id = 'reactionPicker';
        picker.className = 'fixed z-50 bg-white rounded-2xl shadow-xl border p-2 grid grid-cols-5 gap-1';
        picker.innerHTML = REACTION_EMOJIS.map(e => 
            `<button onclick="addReaction('${e}')" class="reaction-btn text-xl hover:scale-110 hover:bg-slate-100 transition p-2 rounded-lg">${e}</button>`
        ).join('');
        
        // Position - center on mobile, near button on desktop
        const btn = event.target.closest('button');
        const rect = btn.getBoundingClientRect();
        const isMobile = window.innerWidth < 640;
        
        if (isMobile) {
            picker.style.left = '50%';
            picker.style.transform = 'translateX(-50%)';
            picker.style.bottom = '80px';
        } else {
            picker.style.left = `${Math.max(10, rect.left - 100)}px`;
            picker.style.top = `${rect.top - 60}px`;
        }
        
        document.body.appendChild(picker);
        
        // Close on outside click
        setTimeout(() => {
            document.addEventListener('click', closeReactionPicker);
        }, 10);
    }

    function closeReactionPicker() {
        const picker = document.getElementById('reactionPicker');
        if (picker) picker.remove();
        document.removeEventListener('click', closeReactionPicker);
    }

    async function addReaction(emoji) {
        closeReactionPicker();
        if (!reactionPickerMsgId) return;
        
        try {
            const res = await fetch(`${BASE_URL}/reactions.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: reactionPickerMsgId, emoji: emoji })
            });
            const data = await res.json();
            if (data.success) {
                loadReactionsForMessage(reactionPickerMsgId);
            }
        } catch (err) {
            console.error('Reaction error:', err);
        }
    }

    // Show who reacted (like WhatsApp)
    async function showReactionDetail(event, msgId, emoji) {
        event.stopPropagation();
        closeReactionPicker();
        
        try {
            const res = await fetch(`${BASE_URL}/reactions.php?detail=${msgId}&emoji=${encodeURIComponent(emoji)}`);
            const data = await res.json();
            
            if (data.success) {
                // Remove existing modal
                closeReactionDetail();
                
                // Create modal
                const modal = document.createElement('div');
                modal.id = 'reactionDetailModal';
                modal.className = 'fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center';
                modal.onclick = (e) => { if (e.target === modal) closeReactionDetail(); };
                
                const usersList = data.users.map(u => `
                    <div class="flex items-center justify-between py-2 px-4 ${u.is_me ? 'bg-blue-50' : ''}">
                        <span class="text-slate-700">${u.name}</span>
                        ${u.is_me ? `
                            <button onclick="removeMyReaction(${msgId}, '${emoji}')" class="text-red-500 text-sm hover:text-red-700">
                                Hapus
                            </button>
                        ` : ''}
                    </div>
                `).join('');
                
                modal.innerHTML = `
                    <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-sm max-h-[60vh] overflow-hidden shadow-xl">
                        <div class="flex items-center justify-between px-4 py-3 border-b">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">${emoji}</span>
                                <span class="text-slate-600 font-medium">${data.users.length} orang</span>
                            </div>
                            <button onclick="closeReactionDetail()" class="p-1 hover:bg-slate-100 rounded-full">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="overflow-y-auto max-h-[50vh] divide-y">
                            ${usersList}
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            }
        } catch (err) {
            console.error('Load reaction detail error:', err);
        }
    }

    function closeReactionDetail() {
        const modal = document.getElementById('reactionDetailModal');
        if (modal) modal.remove();
    }

    async function removeMyReaction(msgId, emoji) {
        try {
            const res = await fetch(`${BASE_URL}/reactions.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: msgId, emoji: emoji, remove: true })
            });
            const data = await res.json();
            if (data.success) {
                closeReactionDetail();
                loadReactionsForMessage(msgId);
            }
        } catch (err) {
            console.error('Remove reaction error:', err);
        }
    }

    async function loadReactionsForMessage(msgId) {
        try {
            const res = await fetch(`${BASE_URL}/reactions.php?ids=${msgId}`);
            const data = await res.json();
            if (data.success) {
                renderReactions(msgId, data.reactions[msgId] || []);
            }
        } catch (err) {
            console.error('Load reactions error:', err);
        }
    }

    function renderReactions(msgId, reactions) {
        const container = document.getElementById(`reactions-${msgId}`);
        if (!container) return;
        
        if (reactions.length === 0) {
            container.innerHTML = '';
            return;
        }
        
        container.innerHTML = reactions.map(r => `
            <button onclick="showReactionDetail(event, ${msgId}, '${r.emoji}')" 
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs ${r.reacted ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-300' : 'bg-slate-100 text-slate-600'} hover:scale-105 transition cursor-pointer">
                <span>${r.emoji}</span>
                <span>${r.count}</span>
            </button>
        `).join('');
    }

    // Load reactions when messages load + play sound on new messages
    const originalLoadMessages = loadMessages;
    let previousMaxMsgId = 0;
    let isFirstLoad = true;
    
    loadMessages = async function() {
        const beforeCount = document.querySelectorAll('[id^="msg-"]').length;
        await originalLoadMessages();
        
        // Check for new messages and play sound
        const msgElements = document.querySelectorAll('[id^="msg-"]');
        const msgIds = Array.from(msgElements).map(el => parseInt(el.id.replace('msg-', ''))).filter(id => !isNaN(id));
        const currentMaxId = msgIds.length > 0 ? Math.max(...msgIds) : 0;
        
        if (!isFirstLoad && currentMaxId > previousMaxMsgId && beforeCount < msgElements.length) {
            // New message arrived - play sound if not from self
            const newestMsg = document.getElementById(`msg-${currentMaxId}`);
            if (newestMsg && !newestMsg.querySelector('.flex-row-reverse')) {
                playNotifSound();
            }
        }
        previousMaxMsgId = currentMaxId;
        isFirstLoad = false;
        
        // Load reactions for visible messages
        if (msgIds.length > 0) {
            try {
                const res = await fetch(`${BASE_URL}/reactions.php?ids=${msgIds.join(',')}`);
                const data = await res.json();
                if (data.success) {
                    Object.keys(data.reactions).forEach(msgId => {
                        renderReactions(msgId, data.reactions[msgId]);
                    });
                }
            } catch (err) {
                console.error('Load reactions error:', err);
            }
        }
        
        // Load polls
        document.querySelectorAll('[data-poll-id]').forEach(el => {
            if (!el.querySelector('.poll-widget')) {
                loadPoll(el.dataset.pollId, el);
            }
        });
    };

    // Start loading messages AFTER wrapper is set up
    loadMessages();
    setInterval(() => { if (isPolling) loadMessages(); }, 2000);

    // ==================== VOICE MESSAGE ====================
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingStartTime = null;
    let voiceTimerInterval = null;
    let currentAudio = null;
    let currentPlayBtn = null;

    const voiceBtn = document.getElementById('voiceBtn');
    const voiceRecordingUI = document.getElementById('voiceRecordingUI');
    const voiceTimer = document.getElementById('voiceTimer');
    const cancelVoiceBtn = document.getElementById('cancelVoice');
    const sendVoiceBtn = document.getElementById('sendVoice');

    // Start recording
    voiceBtn?.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
            audioChunks = [];
            
            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) audioChunks.push(e.data);
            };
            
            mediaRecorder.onstop = () => {
                stream.getTracks().forEach(track => track.stop());
            };
            
            mediaRecorder.start();
            recordingStartTime = Date.now();
            voiceRecordingUI.classList.remove('hidden');
            document.getElementById('chatForm').querySelector('.flex.gap-2.items-center').classList.add('hidden');
            document.getElementById('attachMenu')?.classList.add('hidden');
            
            // Start timer
            voiceTimerInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
                const mins = Math.floor(elapsed / 60);
                const secs = elapsed % 60;
                voiceTimer.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }, 1000);
            
        } catch (err) {
            console.error('Microphone error:', err);
            alert('Tidak dapat mengakses mikrofon. Pastikan izin mikrofon sudah diberikan.');
        }
    });

    // Cancel recording
    cancelVoiceBtn?.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        clearInterval(voiceTimerInterval);
        voiceRecordingUI.classList.add('hidden');
        document.getElementById('chatForm').querySelector('.flex.gap-2.items-center').classList.remove('hidden');
        voiceTimer.textContent = '00:00';
        audioChunks = [];
    });

    // Send voice message
    sendVoiceBtn?.addEventListener('click', async () => {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        clearInterval(voiceTimerInterval);
        
        const duration = Math.floor((Date.now() - recordingStartTime) / 1000);
        
        // Wait for all chunks
        await new Promise(resolve => setTimeout(resolve, 100));
        
        if (audioChunks.length === 0) {
            voiceRecordingUI.classList.add('hidden');
            document.getElementById('chatForm').querySelector('.flex.gap-2.items-center').classList.remove('hidden');
            return;
        }
        
        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
        
        // Upload voice
        const formData = new FormData();
        formData.append('voice', audioBlob, 'voice.webm');
        formData.append('duration', duration);
        
        try {
            sendVoiceBtn.disabled = true;
            sendVoiceBtn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            
            const uploadRes = await fetch(`${BASE_URL}/voice_upload.php`, {
                method: 'POST',
                body: formData
            });
            const uploadData = await uploadRes.json();
            
            if (uploadData.success) {
                // Send message with voice
                const sendRes = await fetch(`${BASE_URL}/forum_send.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: '',
                        voice_url: uploadData.voice_url,
                        voice_duration: duration
                    })
                });
                const sendData = await sendRes.json();
                
                if (sendData.success) {
                    lastMessageId = 0;
                    await loadMessages();
                }
            } else {
                alert(uploadData.error || 'Gagal upload voice');
            }
        } catch (err) {
            console.error('Voice upload error:', err);
            alert('Gagal mengirim voice message');
        } finally {
            sendVoiceBtn.disabled = false;
            sendVoiceBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>';
            voiceRecordingUI.classList.add('hidden');
            document.getElementById('chatForm').querySelector('.flex.gap-2.items-center').classList.remove('hidden');
            voiceTimer.textContent = '00:00';
            audioChunks = [];
        }
    });

    // Voice playback functions
    function toggleVoicePlay(btn, audioUrl) {
        const container = btn.closest('.voice-message');
        const playIcon = btn.querySelector('.play-icon');
        const pauseIcon = btn.querySelector('.pause-icon');
        const progressBar = container.querySelector('.voice-progress');
        const currentTimeEl = container.querySelector('.voice-current-time');
        
        // If already playing this audio, pause it
        if (currentAudio && currentPlayBtn === btn) {
            if (currentAudio.paused) {
                currentAudio.play();
                playIcon.classList.add('hidden');
                pauseIcon.classList.remove('hidden');
            } else {
                currentAudio.pause();
                playIcon.classList.remove('hidden');
                pauseIcon.classList.add('hidden');
            }
            return;
        }
        
        // Stop any other playing audio
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
            if (currentPlayBtn) {
                currentPlayBtn.querySelector('.play-icon').classList.remove('hidden');
                currentPlayBtn.querySelector('.pause-icon').classList.add('hidden');
                currentPlayBtn.closest('.voice-message').querySelector('.voice-progress').style.width = '0%';
            }
        }
        
        // Create new audio
        currentAudio = new Audio(audioUrl);
        currentPlayBtn = btn;
        
        currentAudio.addEventListener('timeupdate', () => {
            const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
            progressBar.style.width = `${progress}%`;
            const mins = Math.floor(currentAudio.currentTime / 60);
            const secs = Math.floor(currentAudio.currentTime % 60);
            currentTimeEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        });
        
        currentAudio.addEventListener('ended', () => {
            playIcon.classList.remove('hidden');
            pauseIcon.classList.add('hidden');
            progressBar.style.width = '0%';
            currentTimeEl.textContent = '0:00';
            currentAudio = null;
            currentPlayBtn = null;
        });
        
        currentAudio.play();
        playIcon.classList.add('hidden');
        pauseIcon.classList.remove('hidden');
    }
    window.toggleVoicePlay = toggleVoicePlay;

    function seekVoice(event, container) {
        if (!currentAudio || !currentPlayBtn) return;
        
        const voiceMessage = container.closest('.voice-message');
        if (!voiceMessage.contains(currentPlayBtn)) return;
        
        const rect = container.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const percentage = x / rect.width;
        currentAudio.currentTime = percentage * currentAudio.duration;
    }
    window.seekVoice = seekVoice;

    // ==================== NOTIFICATIONS ====================
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const notifList = document.getElementById('notifList');
    let notifOpen = false;

    function toggleNotifications() {
        notifOpen = !notifOpen;
        if (notifOpen) {
            notifDropdown.classList.remove('hidden');
            loadNotifications();
        } else {
            notifDropdown.classList.add('hidden');
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (notifOpen && !e.target.closest('#notifDropdown') && !e.target.closest('#notifBtn')) {
            notifDropdown.classList.add('hidden');
            notifOpen = false;
        }
    });

    async function loadNotifications() {
        try {
            const res = await fetch(`${BASE_URL}/notifications.php?limit=10`, {
                credentials: 'include'
            });
            const data = await res.json();
            if (data.success) {
                renderNotifications(data.notifications);
                updateNotifBadge(data.unread_count);
            }
        } catch (err) {
            console.error('Load notifications error:', err);
        }
    }

    async function loadNotifCount() {
        try {
            const res = await fetch(`${BASE_URL}/notifications.php?count=1`, {
                credentials: 'include'
            });
            const data = await res.json();
            if (data.success) {
                updateNotifBadge(data.count);
            }
        } catch (err) {}
    }

    function updateNotifBadge(count) {
        if (count > 0) {
            notifBadge.textContent = count > 99 ? '99+' : count;
            notifBadge.classList.remove('hidden');
            notifBadge.classList.add('flex');
        } else {
            notifBadge.classList.add('hidden');
            notifBadge.classList.remove('flex');
        }
    }

    function renderNotifications(notifications) {
        if (notifications.length === 0) {
            notifList.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Tidak ada notifikasi</div>';
            return;
        }

        const icons = {
            'mention': '💬',
            'reply': '↩️',
            'event': '🎯',
            'badge': '🏆',
            'announcement': '📢',
            'iuran': '💰',
            'payment': '✅',
            'system': '🔔'
        };

        notifList.innerHTML = notifications.map(n => `
            <div class="notif-item p-3 hover:bg-slate-50 border-b border-slate-100 last:border-0 ${n.is_read ? 'opacity-60' : ''} group">
                <div class="flex gap-3">
                    <span class="text-xl cursor-pointer" onclick="handleNotifClick('${n.id}', '${n.link || ''}', ${!n.is_read})">${icons[n.type] || '🔔'}</span>
                    <div class="flex-1 min-w-0 cursor-pointer" onclick="handleNotifClick('${n.id}', '${n.link || ''}', ${!n.is_read})">
                        <p class="text-sm font-medium text-slate-900 ${n.is_read ? '' : 'font-semibold'}">${escapeHtml(n.title)}</p>
                        ${n.message ? `<p class="text-xs text-slate-500 truncate">${escapeHtml(n.message)}</p>` : ''}
                        <p class="text-xs text-slate-400 mt-1">${timeAgo(n.created_at)}</p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        ${!n.is_read ? '<span class="w-2 h-2 bg-secondary rounded-full"></span>' : ''}
                        ${n.id !== 'iuran' ? `
                            <button onclick="event.stopPropagation(); deleteNotif('${n.id}')" 
                                class="p-1 text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition" title="Hapus">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function timeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`;
        if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
        if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`;
        return date.toLocaleDateString('id-ID');
    }

    async function handleNotifClick(id, link, markRead) {
        if (markRead && id !== 'iuran') {
            try {
                await fetch(`${BASE_URL}/notifications.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ mark_read: true, id: parseInt(id) })
                });
                loadNotifCount();
            } catch (err) {}
        }
        
        if (link) {
            window.location.href = link;
        } else {
            // Refresh list to show read state
            loadNotifications();
        }
    }

    async function markAllRead() {
        try {
            const res = await fetch(`${BASE_URL}/notifications.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ mark_all_read: true })
            });
            if (!res.ok) {
                console.error('markAllRead HTTP error:', res.status, await res.text());
                return;
            }
            loadNotifications();
            updateNotifBadge(0);
        } catch (err) {
            console.error('Mark all read error:', err);
        }
    }
    
    async function deleteNotif(id) {
        if (id === 'iuran') return; // Virtual notification
        try {
            const res = await fetch(`${BASE_URL}/notifications.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ delete: true, id: parseInt(id) })
            });
            const data = await res.json();
            if (data.success) {
                loadNotifications();
                loadNotifCount();
            } else {
                console.error('Delete failed:', data);
            }
        } catch (err) {
            console.error('Delete notification error:', err);
        }
    }
    
    async function deleteAllNotifs() {
        if (!confirm('Hapus semua notifikasi?')) return;
        try {
            const res = await fetch(`${BASE_URL}/notifications.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ delete_all: true })
            });
            const data = await res.json();
            if (data.success) {
                loadNotifications();
                updateNotifBadge(0);
            }
        } catch (err) {
            console.error('Delete all notifications error:', err);
        }
    }

    // Simple polling for real-time updates (lebih ringan dari SSE)
    let lastNotifId = 0;
    let lastEventId = 0;
    let lastAnnId = 0;
    let currentEventStatus = '<?= $event ? 'open' : 'closed' ?>';
    
    async function checkRealtimeUpdates() {
        try {
            const res = await fetch(`${BASE_URL}/realtime_check.php?last_notif=${lastNotifId}&last_event=${lastEventId}&last_ann=${lastAnnId}&event_status=${currentEventStatus}`, {
                credentials: 'include'
            });
            const data = await res.json();
            
            if (data.new_notification) {
                showNotificationToast(data.new_notification);
                playNotifSound();
                lastNotifId = data.new_notification.id;
                if (notifOpen) loadNotifications();
            }
            
            if (data.event_started) {
                showEventStartedToast(data.event_started);
                playNotifSound();
                setTimeout(() => location.reload(), 2000);
            }
            
            if (data.event_closed) {
                showToast('info', 'Event Selesai', 'Event telah ditutup oleh admin');
                setTimeout(() => location.reload(), 2000);
            }
            
            if (data.new_announcement) {
                showAnnouncementToast(data.new_announcement);
                playNotifSound();
                addAnnouncementToPage(data.new_announcement);
                lastAnnId = data.new_announcement.id;
            }
            
            if (data.unread_count !== undefined) {
                updateNotifBadge(data.unread_count);
            }
            
            // Update trackers
            if (data.last_notif_id) lastNotifId = data.last_notif_id;
            if (data.last_event_id) lastEventId = data.last_event_id;
            if (data.event_status) currentEventStatus = data.event_status;
        } catch (err) {
            // Silent fail
        }
    }
    
    function showNotificationToast(notif) {
        // Remove existing toast
        const existingToast = document.getElementById('notifToast');
        if (existingToast) existingToast.remove();
        
        const icons = {
            'announcement': '📢',
            'event': '🎯',
            'iuran': '💰',
            'mention': '💬',
            'reply': '↩️',
            'badge': '🏆',
            'payment': '✅',
            'system': '🔔'
        };
        
        const toast = document.createElement('div');
        toast.id = 'notifToast';
        toast.className = 'fixed top-4 right-4 z-[9999] bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700 p-4 max-w-sm animate-slide-in cursor-pointer';
        toast.innerHTML = `
            <div class="flex gap-3 items-start">
                <span class="text-2xl">${icons[notif.type] || '🔔'}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-900 dark:text-white text-sm">${escapeHtml(notif.title)}</p>
                    ${notif.message ? `<p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">${escapeHtml(notif.message)}</p>` : ''}
                </div>
                <button onclick="event.stopPropagation(); this.parentElement.parentElement.remove();" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        toast.onclick = () => {
            toast.remove();
            if (notif.link) {
                window.location.href = notif.link;
            } else {
                toggleNotifications();
            }
        };
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }
    
    // Toast helper for different types
    function showToast(type, title, message) {
        const icons = { 'info': 'ℹ️', 'success': '✅', 'warning': '⚠️', 'error': '❌' };
        showNotificationToast({ type: 'system', title: `${icons[type] || '🔔'} ${title}`, message });
    }
    
    // Show event started toast
    function showEventStartedToast(event) {
        const existingToast = document.getElementById('notifToast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.id = 'notifToast';
        toast.className = 'fixed top-4 right-4 z-[9999] bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl shadow-2xl p-4 max-w-sm animate-slide-in cursor-pointer';
        toast.innerHTML = `
            <div class="flex gap-3 items-start">
                <span class="text-3xl">🎯</span>
                <div class="flex-1">
                    <p class="font-bold text-lg">Event Dimulai!</p>
                    <p class="text-sm opacity-90">${escapeHtml(event.nama_event)}</p>
                    <p class="text-xs opacity-75 mt-1">Klik untuk scan QR sekarang</p>
                </div>
            </div>
        `;
        toast.onclick = () => { toast.remove(); location.reload(); };
        document.body.appendChild(toast);
        setTimeout(() => { if (toast.parentElement) toast.remove(); }, 5000);
    }
    
    // Show announcement toast
    function showAnnouncementToast(ann) {
        const typeColors = {
            'info': 'from-blue-500 to-blue-600',
            'warning': 'from-yellow-500 to-orange-500',
            'danger': 'from-red-500 to-red-600',
            'success': 'from-green-500 to-green-600'
        };
        const icons = { 'info': '📢', 'warning': '⚠️', 'danger': '🚨', 'success': '✅' };
        
        const existingToast = document.getElementById('notifToast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.id = 'notifToast';
        toast.className = `fixed top-4 right-4 z-[9999] bg-gradient-to-r ${typeColors[ann.type] || typeColors.info} text-white rounded-xl shadow-2xl p-4 max-w-sm animate-slide-in cursor-pointer`;
        toast.innerHTML = `
            <div class="flex gap-3 items-start">
                <span class="text-2xl">${icons[ann.type] || '📢'}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-bold">Pengumuman Baru</p>
                    <p class="text-sm font-medium">${escapeHtml(ann.title)}</p>
                    <p class="text-xs opacity-90 mt-1 line-clamp-2">${escapeHtml(ann.content.substring(0, 100))}${ann.content.length > 100 ? '...' : ''}</p>
                </div>
                <button onclick="event.stopPropagation(); this.parentElement.parentElement.remove();" class="text-white/70 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        `;
        toast.onclick = () => toast.remove();
        document.body.appendChild(toast);
        setTimeout(() => { if (toast.parentElement) { toast.classList.add('animate-slide-out'); setTimeout(() => toast.remove(), 300); } }, 6000);
    }
    
    // Add announcement to page dynamically
    function addAnnouncementToPage(ann) {
        const container = document.querySelector('.announcements-container');
        if (!container) {
            // Create announcements section if not exists
            const forumSection = document.getElementById('forumSection');
            if (forumSection) {
                const annSection = document.createElement('div');
                annSection.className = 'announcements-container bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-6';
                annSection.innerHTML = `
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h3 class="font-bold text-slate-900 dark:text-white">Pengumuman</h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700 announcements-list"></div>
                `;
                forumSection.parentElement.insertBefore(annSection, forumSection);
            }
        }
        
        const list = document.querySelector('.announcements-list') || document.querySelector('.announcements-container .divide-y');
        if (list) {
            const typeColors = { 'danger': 'bg-red-500', 'warning': 'bg-yellow-500', 'success': 'bg-green-500', 'info': 'bg-blue-500' };
            const annEl = document.createElement('div');
            annEl.className = 'px-6 py-4 bg-yellow-50 dark:bg-yellow-900/20 animate-pulse-once';
            annEl.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0 ${typeColors[ann.type] || 'bg-blue-500'}"></div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 dark:text-white">${escapeHtml(ann.title)}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">${escapeHtml(ann.content).replace(/\n/g, '<br>')}</p>
                        <p class="text-xs text-slate-400 mt-2">Baru saja</p>
                    </div>
                </div>
            `;
            list.insertBefore(annEl, list.firstChild);
            // Remove highlight after 3 seconds
            setTimeout(() => annEl.classList.remove('bg-yellow-50', 'dark:bg-yellow-900/20', 'animate-pulse-once'), 3000);
        }
    }
    
    // Initialize polling (setiap 5 detik)
    loadNotifCount();
    setInterval(checkRealtimeUpdates, 5000);

    // ==================== TYPING INDICATOR ====================
    const typingIndicator = document.getElementById('typingIndicator');
    const typingText = document.getElementById('typingText');
    const chatInput = document.getElementById('chatInput');
    let typingTimeout = null;
    let lastTypingSent = 0;

    // Auto-resize textarea
    function autoResizeTextarea() {
        const textarea = chatInput;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        // Change overflow when reaching max height
        textarea.style.overflowY = textarea.scrollHeight > 120 ? 'auto' : 'hidden';
    }

    // Handle Enter key (send) and Shift+Enter (new line)
    chatInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }
    });

    // Auto-resize on input
    chatInput?.addEventListener('input', autoResizeTextarea);

    // Send typing status when user types
    chatInput?.addEventListener('input', () => {
        const now = Date.now();
        if (now - lastTypingSent > 2000) { // Send every 2 seconds max
            lastTypingSent = now;
            fetch(`${BASE_URL}/typing.php`, { method: 'POST' }).catch(() => {});
        }
    });

    // Check who is typing
    async function checkTyping() {
        try {
            const res = await fetch(`${BASE_URL}/typing.php`);
            const data = await res.json();
            if (data.success && data.typing.length > 0) {
                let text = '';
                if (data.typing.length === 1) {
                    text = `${data.typing[0]} sedang mengetik`;
                } else if (data.typing.length === 2) {
                    text = `${data.typing[0]} dan ${data.typing[1]} sedang mengetik`;
                } else {
                    text = `${data.typing[0]} dan ${data.typing.length - 1} lainnya sedang mengetik`;
                }
                typingText.textContent = text;
                typingIndicator.classList.remove('hidden');
            } else {
                typingIndicator.classList.add('hidden');
            }
        } catch (err) {}
    }
    setInterval(checkTyping, 2000);

    // ==================== SOUND NOTIFICATION ====================
    let soundEnabled = localStorage.getItem('soundEnabled') !== 'false';
    let lastMessageCount = 0;
    
    // Create audio element
    const notifSound = new Audio('data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAABhgC7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7//////////////////////////////////////////////////////////////////8AAAAATGF2YzU4LjEzAAAAAAAAAAAAAAAAJAAAAAAAAAAAAYYNBrv+AAAAAAAAAAAAAAAAAAAAAP/7UMQAA8AAADSAAAAAAAAANIAAAAATEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVX/+1DEAYPAAADSAAAAAAAAANIAAAAASqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqo=');
    
    // Toggle sound function
    window.toggleSound = function() {
        soundEnabled = !soundEnabled;
        localStorage.setItem('soundEnabled', soundEnabled);
        updateSoundIcon();
        if (soundEnabled) {
            notifSound.play().catch(() => {});
        }
    };

    function updateSoundIcon() {
        const icon = document.getElementById('soundIcon');
        if (icon) {
            icon.innerHTML = soundEnabled 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"></path>';
        }
    }

    function playNotifSound() {
        if (soundEnabled) {
            notifSound.currentTime = 0;
            notifSound.play().catch(() => {});
        }
    }

    // ==================== ONLINE STATUS ====================
    async function updateOnlineStatus() {
        try {
            await fetch(`${BASE_URL}/online_status.php`, { method: 'POST' });
        } catch (err) {}
    }
    
    async function loadOnlineCount() {
        try {
            const res = await fetch(`${BASE_URL}/online_status.php`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('onlineCount').textContent = data.count;
                window.onlineUsers = data.users;
            }
        } catch (err) {}
    }
    
    function showOnlineUsers() {
        const users = window.onlineUsers || [];
        if (users.length === 0) {
            alert('Tidak ada user online');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
        modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
        
        const userList = users.map(u => {
            const pic = u.picture ? (u.picture.startsWith('http') ? u.picture : '../' + u.picture) : '';
            return `
                <div class="flex items-center gap-3 p-2">
                    ${pic ? `<img src="${pic}" class="w-8 h-8 rounded-full object-cover">` : 
                    `<div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>`}
                    <span class="text-sm text-slate-700">${u.nama}</span>
                    <span class="w-2 h-2 bg-green-500 rounded-full ml-auto"></span>
                </div>
            `;
        }).join('');
        
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full max-h-[60vh] overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-semibold text-slate-900">User Online (${users.length})</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-2 max-h-80 overflow-y-auto">${userList}</div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    updateOnlineStatus();
    loadOnlineCount();
    setInterval(updateOnlineStatus, 30000);
    setInterval(loadOnlineCount, 30000);
    
    // Initialize sound icon
    updateSoundIcon();

    // ==================== POLLING ====================
    function showPollModal() {
        document.getElementById('pollModal').classList.remove('hidden');
        document.getElementById('pollModal').classList.add('flex');
        document.getElementById('pollQuestion').value = '';
        document.getElementById('pollOptions').innerHTML = `
            <input type="text" class="poll-option w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm" placeholder="Pilihan 1" maxlength="100">
            <input type="text" class="poll-option w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm" placeholder="Pilihan 2" maxlength="100">
        `;
        document.getElementById('pollMultiple').checked = false;
        document.getElementById('pollDuration').value = '24';
    }
    
    function hidePollModal() {
        document.getElementById('pollModal').classList.add('hidden');
        document.getElementById('pollModal').classList.remove('flex');
    }
    
    function addPollOption() {
        const container = document.getElementById('pollOptions');
        const count = container.querySelectorAll('.poll-option').length;
        if (count >= 10) {
            alert('Maksimal 10 pilihan');
            return;
        }
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'poll-option w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm';
        input.placeholder = `Pilihan ${count + 1}`;
        input.maxLength = 100;
        container.appendChild(input);
    }
    
    async function createPoll() {
        const question = document.getElementById('pollQuestion').value.trim();
        const optionInputs = document.querySelectorAll('.poll-option');
        const options = Array.from(optionInputs).map(i => i.value.trim()).filter(v => v);
        const isMultiple = document.getElementById('pollMultiple').checked;
        const duration = parseInt(document.getElementById('pollDuration').value);
        
        if (!question) {
            alert('Masukkan pertanyaan');
            return;
        }
        if (options.length < 2) {
            alert('Minimal 2 pilihan');
            return;
        }
        
        try {
            const res = await fetch(`${BASE_URL}/poll.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ create: true, question, options, is_multiple: isMultiple, duration })
            });
            const text = await res.text();
            console.log('Poll API response:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                alert('Error parsing response: ' + text);
                return;
            }
            
            if (data.success && data.poll_id) {
                // Send poll as special message
                const sent = await sendMessage(`[poll:${data.poll_id}]`);
                if (sent) {
                    hidePollModal();
                    lastMessageId = 0;
                    await loadMessages();
                } else {
                    alert('Poll dibuat tapi gagal mengirim ke chat');
                }
            } else {
                alert(data.error || 'Gagal membuat poll');
            }
        } catch (err) {
            console.error('Create poll error:', err);
            alert('Gagal membuat poll: ' + err.message);
        }
    }
    
    async function loadPoll(pollId, container) {
        try {
            const res = await fetch(`${BASE_URL}/poll.php?id=${pollId}`);
            const data = await res.json();
            
            if (data.success) {
                renderPoll(data.poll, container);
            }
        } catch (err) {
            container.innerHTML = '<p class="text-sm text-red-500">Gagal memuat poll</p>';
        }
    }
    
    function renderPoll(poll, container) {
        const totalVotes = poll.total_votes;
        const hasVoted = poll.has_voted;
        const isEnded = poll.is_ended;
        const canRevote = hasVoted && !isEnded && !poll.is_multiple; // Single choice bisa ganti pilihan
        
        let optionsHtml = poll.options.map((opt, idx) => {
            const count = poll.vote_counts[idx] || 0;
            const percent = totalVotes > 0 ? Math.round((count / totalVotes) * 100) : 0;
            const isSelected = poll.user_votes.includes(idx);
            
            if (isEnded) {
                // Poll sudah berakhir - tampilkan hasil saja
                return `
                    <div class="relative mb-2">
                        <div class="absolute inset-0 bg-secondary/20 rounded-lg" style="width: ${percent}%"></div>
                        <div class="relative flex justify-between items-center px-3 py-2 border border-slate-200 rounded-lg bg-white/50">
                            <span class="text-sm text-slate-700 ${isSelected ? 'font-semibold' : ''}">${isSelected ? '✓ ' : ''}${opt}</span>
                            <span class="text-xs text-slate-600">${percent}% (${count})</span>
                        </div>
                    </div>
                `;
            } else if (hasVoted) {
                // Sudah vote tapi masih bisa ganti (single choice)
                return `
                    <button onclick="votePoll(${poll.id}, ${idx})" class="relative w-full mb-2 ${!poll.is_multiple ? 'cursor-pointer hover:opacity-80' : ''}" ${poll.is_multiple ? 'disabled' : ''}>
                        <div class="absolute inset-0 bg-secondary/20 rounded-lg" style="width: ${percent}%"></div>
                        <div class="relative flex justify-between items-center px-3 py-2 border ${isSelected ? 'border-secondary bg-secondary/10' : 'border-slate-200 bg-white/50'} rounded-lg">
                            <span class="text-sm text-slate-700 ${isSelected ? 'font-semibold text-secondary' : ''}">${isSelected ? '✓ ' : ''}${opt}</span>
                            <span class="text-xs text-slate-600">${percent}% (${count})</span>
                        </div>
                    </button>
                `;
            } else {
                // Belum vote
                return `
                    <button onclick="votePoll(${poll.id}, ${idx})" class="w-full text-left px-3 py-2 border border-slate-200 rounded-lg bg-white hover:bg-slate-100 hover:border-secondary transition mb-2">
                        <span class="text-sm text-slate-700">${opt}</span>
                    </button>
                `;
            }
        }).join('');
        
        const revoteHint = canRevote ? '<span class="text-xs text-slate-500"> · Tap untuk ganti</span>' : '';
        
        container.innerHTML = `
            <div class="poll-widget bg-white border border-slate-200 rounded-xl p-3 max-w-xs shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="text-xs font-medium text-secondary">POLLING</span>
                    ${isEnded ? '<span class="text-xs text-red-500 ml-auto">Berakhir</span>' : ''}
                </div>
                <p class="font-medium text-slate-800 text-sm mb-3">${poll.question}</p>
                ${optionsHtml}
                <p class="text-xs text-slate-500 mt-2">${totalVotes} suara${poll.is_multiple ? ' · Pilihan ganda' : ''}${revoteHint}</p>
            </div>
        `;
    }
    
    async function votePoll(pollId, optionIdx) {
        try {
            const res = await fetch(`${BASE_URL}/poll.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vote: true, poll_id: pollId, options: [optionIdx] })
            });
            const data = await res.json();
            
            if (data.success) {
                // Reload all polls
                document.querySelectorAll('[data-poll-id]').forEach(el => {
                    loadPoll(el.dataset.pollId, el);
                });
            } else {
                alert(data.error || 'Gagal vote');
            }
        } catch (err) {
            console.error('Vote error:', err);
        }
    }
    window.votePoll = votePoll;
    </script>

</body>
</html>
