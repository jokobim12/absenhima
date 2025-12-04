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
    LIMIT 10
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

// Gamification data
$user_badges = getUserBadges($conn, $user_id);
$user_rank = getUserRank($conn, $user_id);
$user_streak = $user['current_streak'] ?? 0;
$user_longest_streak = $user['longest_streak'] ?? 0;

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
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" id="forumSection">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <h3 class="font-bold text-slate-900">Forum Diskusi</h3>
                        </div>
                        <span class="text-xs text-green-500 flex items-center gap-1" id="onlineStatus">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            Live
                        </span>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div id="chatMessages" class="h-80 overflow-y-auto p-4 space-y-3 bg-slate-50">
                        <div class="text-center text-slate-400 text-sm py-8">Memuat pesan...</div>
                    </div>
                    
                    <!-- Chat Input -->
                    <div class="p-4 border-t border-slate-100">
                        <div id="replyPreview"></div>
                        <div id="imagePreview" class="hidden mb-2"></div>
                        <div id="mentionDropdown" class="hidden absolute bg-white border border-slate-200 rounded-lg shadow-lg max-h-40 overflow-y-auto z-50"></div>
                        <form id="chatForm" class="flex gap-2 items-end">
                            <div class="flex gap-1">
                                <!-- Emoji Picker Button -->
                                <button type="button" id="emojiBtn" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition" title="Emoji">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                <!-- Image Upload Button -->
                                <label class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition cursor-pointer" title="Kirim Gambar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <input type="file" id="imageInput" accept="image/*" class="hidden">
                                </label>
                            </div>
                            <div class="flex-1 relative">
                                <input type="text" id="chatInput" placeholder="Tulis pesan... (@ untuk tag)" maxlength="2000"
                                    class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-secondary focus:border-secondary outline-none transition text-sm">
                            </div>
                            <button type="submit" id="sendBtn" class="px-4 py-2 bg-secondary text-white rounded-xl hover:bg-secondary/90 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </form>
                        <!-- Emoji Picker Dropdown -->
                        <div id="emojiPicker" class="hidden absolute bottom-20 left-4 bg-white border border-slate-200 rounded-xl shadow-lg p-3 z-50">
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

                <!-- Leaderboard & Streak -->
                <a href="leaderboard.php" class="block bg-gradient-to-r from-yellow-400 to-orange-500 rounded-2xl p-4 text-white hover:shadow-lg transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Peringkat Kamu</p>
                            <p class="text-3xl font-bold">#<?= $user_rank ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-white/80 text-sm">Streak</p>
                            <p class="text-2xl font-bold"><?= $user_streak ?> 🔥</p>
                        </div>
                    </div>
                    <p class="text-white/60 text-xs mt-2">Tap untuk lihat leaderboard →</p>
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
    const BASE_URL = '<?= dirname($_SERVER['PHP_SELF']) ?>/../api';
    let lastMessageId = 0;
    let isPolling = true;
    let replyTo = null;
    let editingId = null;

    const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function formatTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
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
        
        let dateHeader = '';
        if (showDate) {
            dateHeader = `<div class="text-center my-3"><span class="bg-slate-200 text-slate-600 text-xs px-3 py-1 rounded-full">${formatDate(msg.created_at)}</span></div>`;
        }
        
        const replyHtml = (!isDeleted && msg.reply_info) ? `
            <div class="bg-slate-100/50 border-l-2 border-secondary px-2 py-1 rounded mb-1 cursor-pointer" onclick="scrollToMessage(${msg.reply_to})">
                <p class="text-xs font-medium text-secondary">${msg.reply_info.nama}</p>
                <p class="text-xs text-slate-500 truncate">${msg.reply_info.message.substring(0, 40)}${msg.reply_info.message.length > 40 ? '...' : ''}</p>
            </div>
        ` : '';

        // Parse @mentions in message
        const parseMentions = (text) => {
            return text.replace(/@(\w+(?:\s\w+)*)/g, '<span class="text-blue-500 font-medium">@$1</span>');
        };
        
        // Image HTML
        const imageHtml = (!isDeleted && msg.image_url) ? `
            <div class="mt-2 mb-1">
                <img src="../${msg.image_url}" alt="Image" class="max-w-xs rounded-lg cursor-pointer hover:opacity-90 transition" onclick="openImageModal('../${msg.image_url}')">
            </div>
        ` : '';
        
        const messageContent = isDeleted 
            ? `<p class="text-sm italic ${isMe ? 'text-white/70' : 'text-slate-400'}">Pesan telah dihapus</p>`
            : `${msg.message ? `<p class="text-sm break-words whitespace-pre-wrap" id="msg-text-${msg.id}">${parseMentions(msg.message)}</p>` : ''}${imageHtml}`;

        const editedLabel = (isEdited && !isDeleted) ? `<span class="text-xs ${isMe ? 'text-white/50' : 'text-slate-400'} ml-1">· diedit</span>` : '';
        
        const actionButtons = (isMe && !isDeleted) ? `
            <div class="absolute ${isMe ? 'left-0 -translate-x-full' : 'right-0 translate-x-full'} top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 flex gap-1 transition">
                <button onclick="setReply(${msg.id}, '${msg.nama.replace(/'/g, "\\'")}', '${msg.message.replace(/'/g, "\\'").replace(/\n/g, ' ')}')" 
                    class="p-1 text-slate-400 hover:text-slate-600" title="Balas">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                </button>
                <button onclick="startEdit(${msg.id}, '${msg.message.replace(/'/g, "\\'").replace(/\n/g, '\\n')}')" 
                    class="p-1 text-slate-400 hover:text-blue-600" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </button>
                <button onclick="deleteMessage(${msg.id}, false)" 
                    class="p-1 text-slate-400 hover:text-red-600" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        ` : (isMe && isDeleted) ? `
            <button onclick="deleteMessage(${msg.id}, true)" 
                class="absolute ${isMe ? 'left-0 -translate-x-full' : 'right-0 translate-x-full'} top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-red-600 transition" title="Hapus Permanen">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        ` : (!isDeleted ? `
            <button onclick="setReply(${msg.id}, '${msg.nama.replace(/'/g, "\\'")}', '${msg.message.replace(/'/g, "\\'").replace(/\n/g, ' ')}')" 
                class="absolute ${isMe ? 'left-0 -translate-x-full' : 'right-0 translate-x-full'} top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-slate-600 transition" title="Balas">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                </svg>
            </button>
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
                    <div class="${isMe ? 'bg-secondary text-white' : 'bg-white border border-slate-200'} px-3 py-2 rounded-xl ${isMe ? 'rounded-tr-sm' : 'rounded-tl-sm'} ${isDeleted ? 'opacity-70' : ''}">
                        ${!isMe ? `<p class="text-xs font-semibold ${isMe ? 'text-white/80' : 'text-secondary'} mb-1">${msg.nama}</p>` : ''}
                        ${replyHtml}
                        ${messageContent}
                        <p class="text-xs ${isMe ? 'text-white/60' : 'text-slate-400'} mt-1 text-right">${formatTime(msg.created_at)}${editedLabel}</p>
                    </div>
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
    async function loadMessages() {
        try {
            const res = await fetch(`${BASE_URL}/forum_messages.php?last_id=${lastMessageId}`);
            const data = await res.json();
            
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('chatMessages');
                
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
                    data.messages.forEach(msg => {
                        const msgDate = new Date(msg.created_at).toDateString();
                        const showDate = msgDate !== lastDate;
                        if (showDate) lastDate = msgDate;
                        container.insertAdjacentHTML('beforeend', createMessageEl(msg, showDate));
                    });
                }
                
                if (data.messages.length > 0) {
                    lastMessageId = Math.max(...data.messages.map(m => m.id));
                }
                
                container.scrollTop = container.scrollHeight;
            } else if (lastMessageId === 0) {
                document.getElementById('chatMessages').innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Belum ada pesan. Mulai diskusi!</div>';
            }
        } catch (err) {
            console.error('Error loading messages:', err);
        }
    }

    async function sendMessage(message, imageUrl = null) {
        try {
            const body = { message: message };
            if (replyTo) body.reply_to = replyTo.id;
            if (imageUrl) body.image_url = imageUrl;
            
            const res = await fetch(`${BASE_URL}/forum_send.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            
            const data = await res.json();
            if (data.success) {
                cancelReply();
                return true;
            } else {
                alert(data.error || 'Gagal mengirim pesan');
                return false;
            }
        } catch (err) {
            console.error('Error sending message:', err);
            return false;
        }
    }

    document.getElementById('chatForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        
        if (message || pendingImage) {
            input.disabled = true;
            document.getElementById('sendBtn').disabled = true;
            let success;
            
            if (editingId) {
                success = await editMessage(editingId, message);
            } else {
                success = await sendMessage(message, pendingImage);
            }
            
            if (success) {
                input.value = '';
                cancelImage();
                await loadMessages();
            }
            input.disabled = false;
            document.getElementById('sendBtn').disabled = false;
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
        
        if (!file.type.startsWith('image/')) {
            alert('Hanya file gambar yang diperbolehkan');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 5MB');
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
    
    document.addEventListener('click', function(e) {
        if (!emojiPicker.contains(e.target) && e.target !== emojiBtn) {
            emojiPicker.classList.add('hidden');
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
    window.openImageModal = openImageModal;
    window.cancelImage = cancelImage;

    loadMessages();
    setInterval(() => { if (isPolling) loadMessages(); }, 2000);
    </script>

</body>
</html>
