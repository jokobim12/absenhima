<?php 
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";
include "../config/lang.php";

$user_id = $_SESSION['user_id'];

// Handle language switch
if (isset($_GET['lang'])) {
    setCurrentLang($_GET['lang']);
    header("Location: dashboard.php");
    exit;
}

// Get settings
$s = getAllSettings();

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));
$event = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE status='open' LIMIT 1"));

$semester_sekarang = hitungSemester($user['nim']);
if ($user['semester'] != $semester_sekarang) {
    mysqli_query($conn, "UPDATE users SET semester='$semester_sekarang' WHERE id='$user_id'");
    $user['semester'] = $semester_sekarang;
}

$riwayat = mysqli_query($conn, "
    SELECT COALESCE(a.waktu, a.created_at) as waktu_absen, e.nama_event 
    FROM absen a 
    JOIN events e ON a.event_id = e.id 
    WHERE a.user_id = '$user_id' 
    ORDER BY a.id DESC 
    LIMIT 10
");
$total_hadir = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM absen WHERE user_id='$user_id'"))['c'];

$has_picture = !empty($user['picture']);
$picture_url = $has_picture ? (strpos($user['picture'], 'http') === 0 ? $user['picture'] : '../' . $user['picture']) : '';

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
    <style>
        .glass { backdrop-filter: blur(10px); }
        body { background: linear-gradient(135deg, <?= $color_bg ?> 0%, #e2e8f0 100%); }
        .gradient-primary { background: linear-gradient(135deg, <?= $color_primary ?> 0%, <?= $color_primary ?>dd 100%); }
    </style>
</head>
<body class="min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white/80 glass border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <?php if (!empty($s['site_logo'])): ?>
                <img src="../<?= htmlspecialchars($s['site_logo']) ?>" alt="Logo" class="w-10 h-10 object-contain">
                <?php else: ?>
                <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <?php endif; ?>
                <div>
                    <span class="text-slate-900 font-bold text-lg"><?= htmlspecialchars($s['site_name'] ?? 'SADHATI') ?></span>
                    <p class="text-slate-500 text-xs"><?= htmlspecialchars($s['site_tagline'] ?? 'Sistem Absensi') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-4">
                <!-- Language Switcher -->
                <div class="relative">
                    <select onchange="window.location='?lang='+this.value" class="appearance-none bg-slate-100 text-slate-700 text-xs sm:text-sm px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg cursor-pointer pr-6 sm:pr-8 focus:outline-none">
                        <?php foreach ($languages as $code => $name): ?>
                        <option value="<?= $code ?>" <?= $current_lang == $code ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
                <a href="profile.php" class="text-slate-500 hover:text-slate-900 p-2 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
                <button onclick="showLogoutModal()" class="text-slate-500 hover:text-red-600 p-2 hover:bg-red-50 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <div class="flex items-start justify-between mb-6">
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
                        <a href="scan.php" class="flex items-center justify-center gap-3 w-full py-4 gradient-primary hover:opacity-90 text-white font-semibold rounded-xl transition shadow-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            <?= __('scan_now') ?>
                        </a>
                    </div>
                </div>
                <?php else: ?>
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
                    <div class="px-6 lg:px-8 py-5 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900 text-lg"><?= __('attendance_history') ?></h3>
                        <span class="text-slate-400 text-sm"><?= $total_hadir ?> <?= __('total') ?></span>
                    </div>
                    <?php if($riwayat && mysqli_num_rows($riwayat) > 0): ?>
                    <div class="divide-y divide-slate-100">
                        <?php while($r = mysqli_fetch_assoc($riwayat)): ?>
                        <div class="px-6 lg:px-8 py-4 flex items-center justify-between hover:bg-slate-50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900"><?= htmlspecialchars($r['nama_event']) ?></p>
                                    <p class="text-slate-400 text-sm"><?= date('d M Y, H:i', strtotime($r['waktu_absen'])) ?></p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-accent/10 text-accent rounded-full text-xs font-medium"><?= __('present') ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="px-6 lg:px-8 py-12 text-center">
                        <p class="text-slate-400"><?= __('no_history') ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                
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
    </script>

</body>
</html>
