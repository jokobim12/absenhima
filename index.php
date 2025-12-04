<?php
session_start();
require_once "config/koneksi.php";
require_once "config/settings.php";
require_once "config/lang.php";

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit;
}
if (isset($_SESSION['admin_id'])) {
    header("Location: admin/index.php");
    exit;
}

// Handle language switch
if (isset($_GET['lang'])) {
    setCurrentLang($_GET['lang']);
    header("Location: index.php");
    exit;
}

// Get settings
$s = getAllSettings();
$current_lang = getCurrentLang();
$languages = getAvailableLanguages();
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($s['site_name'] ?? 'Absensi HIMA') ?> - <?= htmlspecialchars($s['site_tagline'] ?? '') ?></title>
    <?php if (!empty($s['site_favicon'])): ?>
    <link rel="icon" href="<?= htmlspecialchars($s['site_favicon']) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '<?= $s['color_primary'] ?? '#1e293b' ?>',
                    secondary: '<?= $s['color_secondary'] ?? '#3b82f6' ?>',
                    accent: '<?= $s['color_accent'] ?? '#10b981' ?>',
                }
            }
        }
    }
    </script>
    <style>
        body { background-color: <?= $s['color_background'] ?? '#f8fafc' ?>; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="border-b border-gray-100 bg-white/95 backdrop-blur-sm sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <?php if (!empty($s['site_logo'])): ?>
                <img src="<?= htmlspecialchars($s['site_logo']) ?>" alt="Logo" class="w-8 h-8 sm:w-10 sm:h-10 object-contain">
                <?php else: ?>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-primary rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <?php endif; ?>
                <span class="text-gray-900 font-bold text-base sm:text-lg"><?= htmlspecialchars($s['site_name'] ?? 'SADHATI') ?></span>
            </div>
            <div class="flex items-center gap-2 sm:gap-4">
                <!-- Language Switcher -->
                <div class="relative">
                    <select onchange="window.location='?lang='+this.value" class="appearance-none bg-gray-100 text-gray-700 text-xs px-2 py-1.5 rounded-lg cursor-pointer pr-6 focus:outline-none">
                        <?php foreach ($languages as $code => $name): ?>
                        <option value="<?= $code ?>" <?= $current_lang == $code ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
                <a href="auth/login_admin.php" class="text-gray-500 hover:text-gray-900 text-xs sm:text-sm">
                    Admin
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="flex-1 flex items-center">
        <div class="max-w-6xl mx-auto px-4 py-8 sm:py-12 md:py-16 w-full">
            <div class="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
                
                <!-- Left Content -->
                <div class="text-center md:text-left order-2 md:order-1">
                    <!-- Title -->
                    <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight mb-4 sm:mb-6">
                        <?= htmlspecialchars(__('hero_title') ?: 'Sistem Absensi Digital') ?>
                        <span class="block text-secondary mt-1"><?= htmlspecialchars(__('hero_subtitle') ?: 'HIMA Politala') ?></span>
                    </h1>
                    
                    <!-- Description -->
                    <p class="text-gray-500 text-sm sm:text-base md:text-lg mb-6 sm:mb-8 leading-relaxed max-w-md mx-auto md:mx-0">
                        <?= htmlspecialchars(__('hero_description') ?: 'Absensi modern dengan QR Code dinamis. Cepat, aman, dan efisien.') ?>
                    </p>
                    
                    <!-- Features - Grid on mobile -->
                    <div class="grid grid-cols-1 gap-3 mb-6 sm:mb-8 max-w-sm mx-auto md:mx-0">
                        <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700 text-sm font-medium"><?= htmlspecialchars(__('feature_1_title') ?: 'QR Code Dinamis') ?></span>
                        </div>
                        <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                            <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700 text-sm font-medium"><?= htmlspecialchars(__('feature_2_title') ?: 'Login dengan Google') ?></span>
                        </div>
                        <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700 text-sm font-medium"><?= htmlspecialchars(__('feature_3_title') ?: 'Realtime & Akurat') ?></span>
                        </div>
                    </div>

                    <!-- CTA Button -->
                    <div class="max-w-sm mx-auto md:mx-0">
                        <a href="auth/google_login.php" class="flex items-center justify-center gap-3 bg-primary hover:bg-gray-800 text-white w-full px-6 py-4 rounded-xl font-semibold transition shadow-lg shadow-gray-900/10">
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            <?= __('login_with_google') ?>
                        </a>
                        <p class="text-gray-400 text-xs sm:text-sm mt-3 text-center"><?= __('use_politala_email') ?></p>
                    </div>
                </div>

                <!-- Right Content - Illustration -->
                <div class="order-1 md:order-2">
                    <?php if (!empty($s['hero_image'])): 
                        $hero_size = $s['hero_image_size'] ?? '100';
                        $hero_fit = $s['hero_image_fit'] ?? 'contain';
                        $hero_position = $s['hero_image_position'] ?? 'center';
                    ?>
                    <div class="flex justify-center md:block">
                        <div class="w-full max-w-xs md:max-w-none rounded-2xl overflow-hidden bg-gray-100" 
                             style="max-width: <?= min(intval($hero_size), 200) ?>%;">
                            <img src="<?= htmlspecialchars($s['hero_image']) ?>" alt="Hero" 
                                 class="w-full h-auto rounded-2xl"
                                 style="object-fit: <?= htmlspecialchars($hero_fit) ?>; object-position: <?= htmlspecialchars($hero_position) ?>;">
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Mobile: Compact QR illustration -->
                    <div class="md:hidden flex justify-center mb-2">
                        <div class="w-24 h-24 bg-primary rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                        </div>
                    </div>
                    <!-- Desktop: Full illustration -->
                    <div class="hidden md:block bg-gray-50 rounded-2xl p-8">
                        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                            <div class="bg-gray-50 rounded-lg p-6 mb-4">
                                <div class="flex items-center justify-center">
                                    <div class="w-32 h-32 bg-primary rounded-lg flex items-center justify-center">
                                        <svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mb-4">
                                <p class="text-gray-900 font-medium"><?= __('scan_qr') ?></p>
                                <p class="text-gray-400 text-sm"><?= __('attend_now') ?></p>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <span class="text-gray-600 text-sm"><?= __('present') ?></span>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-gray-600 text-sm"><?= __('statistics') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-gray-100 bg-white mt-auto">
        <div class="max-w-6xl mx-auto px-4 py-4 sm:py-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <p class="text-gray-400 text-xs sm:text-sm text-center sm:text-left">
                    <?= htmlspecialchars($s['footer_text'] ?? '© 2025 HIMA Politala') ?>
                </p>
                <div class="flex flex-wrap justify-center gap-4 sm:gap-6">
                    <?php if (!empty($s['contact_email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($s['contact_email']) ?>" class="text-gray-400 hover:text-gray-600 text-xs sm:text-sm"><?= htmlspecialchars($s['contact_email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($s['contact_instagram'])): ?>
                    <a href="https://instagram.com/<?= htmlspecialchars(ltrim($s['contact_instagram'], '@')) ?>" target="_blank" class="text-gray-400 hover:text-gray-600 text-xs sm:text-sm"><?= htmlspecialchars($s['contact_instagram']) ?></a>
                    <?php endif; ?>
                    <a href="auth/login_admin.php" class="text-gray-400 hover:text-gray-600 text-xs sm:text-sm">Admin</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
