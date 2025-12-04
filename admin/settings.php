<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/settings.php";
include "../config/lang.php";
include "../config/push_helper.php";

$success = '';
$error = '';

// Handle VAPID key generation
if (isset($_POST['generate_vapid'])) {
    $keys = generateVapidKeys();
    if ($keys) {
        if (saveVapidKeys($conn, $keys['publicKey'], $keys['privateKeyPem'])) {
            $success = 'VAPID keys berhasil dibuat!';
        } else {
            $error = 'Gagal menyimpan VAPID keys';
        }
    } else {
        $error = 'Gagal membuat VAPID keys. Pastikan OpenSSL mendukung EC keys.';
    }
}

// Handle delete image
if (isset($_GET['delete_image']) && !empty($_GET['delete_image'])) {
    $key = mysqli_real_escape_string($conn, $_GET['delete_image']);
    $old_value = getSetting($key);
    
    if ($old_value && file_exists('../' . $old_value)) {
        @unlink('../' . $old_value);
    }
    
    updateSetting($key, '');
    
    $tab = $_GET['tab'] ?? 'branding';
    header("Location: settings.php?tab=$tab&deleted=1");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle image uploads
    if (!empty($_FILES)) {
        $upload_dir = '../uploads/settings/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES as $key => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/svg+xml'];
                if (in_array($file['type'], $allowed)) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $key . '_' . time() . '.' . $ext;
                    
                    $old_value = getSetting($key);
                    if ($old_value && file_exists('../' . $old_value)) {
                        @unlink('../' . $old_value);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        updateSetting($key, 'uploads/settings/' . $filename);
                    }
                }
            }
        }
    }
    
    // Handle text/color settings
    if (isset($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            updateSetting($key, $value);
        }
    }
    
    // Handle translations
    if (isset($_POST['translations'])) {
        foreach ($_POST['translations'] as $key => $langs) {
            foreach ($langs as $lang => $value) {
                updateTranslation($key, $lang, $value);
            }
        }
    }
    
    $success = 'Pengaturan berhasil disimpan';
}

if (isset($_GET['deleted'])) {
    $success = 'Gambar berhasil dihapus';
}

// Get settings by group
$branding = getSettingsByGroup('branding');
$colors = getSettingsByGroup('colors');
$homepage = getSettingsByGroup('homepage');
$footer = getSettingsByGroup('footer');
$google = getSettingsByGroup('google');

// Get translations
$translations = getTranslationsGrouped();
$labels = getTranslationLabels();
$languages = getAvailableLanguages();
$homepage_labels = getHomepageTranslationLabels();
$homepage_translations = getHomepageTranslations();

// Active tab
$active_tab = $_GET['tab'] ?? 'branding';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Pengaturan Website</h1>
                <p class="text-gray-500">Kustomisasi tampilan dan konten website</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl">
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="border-b border-gray-200">
                    <nav class="flex overflow-x-auto">
                        <a href="?tab=branding" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 <?= $active_tab == 'branding' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                            Branding
                        </a>
                        <a href="?tab=colors" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 <?= $active_tab == 'colors' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                            Warna
                        </a>
                        <a href="?tab=homepage" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 <?= $active_tab == 'homepage' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                            Halaman Beranda
                        </a>
                        <a href="?tab=footer" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 <?= $active_tab == 'footer' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                            Footer
                        </a>
                        <a href="?tab=language" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 <?= $active_tab == 'language' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                            Bahasa
                        </a>
                        <a href="?tab=notifications" class="px-6 py-4 text-sm font-medium whitespace-nowrap border-b-2 <?= $active_tab == 'notifications' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                            Notifikasi
                        </a>
                    </nav>
                </div>

                <form method="POST" enctype="multipart/form-data" class="p-6">
                    
                    <!-- Branding Tab -->
                    <?php if ($active_tab == 'branding'): ?>
                    <div class="space-y-6">
                        <h2 class="text-lg font-semibold text-gray-900">Branding</h2>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php foreach ($branding as $key => $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($setting['setting_label']) ?></label>
                                
                                <?php if ($setting['setting_type'] == 'image'): ?>
                                <div class="flex items-center gap-3">
                                    <?php if ($setting['setting_value']): ?>
                                    <img src="../<?= htmlspecialchars($setting['setting_value']) ?>" class="w-16 h-16 object-contain bg-gray-100 rounded-lg border">
                                    <?php else: ?>
                                    <div class="w-16 h-16 bg-gray-100 rounded-lg border flex items-center justify-center text-gray-400">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex flex-col gap-2">
                                        <label class="cursor-pointer">
                                            <span class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition inline-block text-sm">
                                                Pilih File
                                            </span>
                                            <input type="file" name="<?= $key ?>" accept="image/*" class="hidden">
                                        </label>
                                        <?php if ($setting['setting_value']): ?>
                                        <a href="?tab=<?= $active_tab ?>&delete_image=<?= $key ?>" 
                                           onclick="return confirm('Hapus gambar ini?')"
                                           class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition inline-block text-sm text-center">
                                            Hapus
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <input type="text" name="settings[<?= $key ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Colors Tab -->
                    <?php if ($active_tab == 'colors'): ?>
                    <div class="space-y-6">
                        <h2 class="text-lg font-semibold text-gray-900">Warna</h2>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php foreach ($colors as $key => $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($setting['setting_label']) ?></label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="settings[<?= $key ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                           class="w-12 h-12 rounded-lg cursor-pointer border-2 border-gray-200" id="color_<?= $key ?>">
                                    <input type="text" value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                           class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl font-mono text-sm"
                                           onchange="document.getElementById('color_<?= $key ?>').value = this.value;"
                                           id="text_<?= $key ?>">
                                </div>
                                <script>
                                document.getElementById('color_<?= $key ?>').addEventListener('input', function() {
                                    document.getElementById('text_<?= $key ?>').value = this.value;
                                });
                                </script>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-8 p-6 rounded-xl border border-gray-200">
                            <h3 class="text-sm font-medium text-gray-700 mb-4">Preview Warna</h3>
                            <div class="flex flex-wrap gap-4">
                                <?php foreach ($colors as $key => $setting): ?>
                                <div class="text-center">
                                    <div class="w-20 h-20 rounded-xl shadow-sm border" style="background-color: <?= htmlspecialchars($setting['setting_value']) ?>"></div>
                                    <p class="text-xs text-gray-500 mt-2"><?= str_replace('Warna ', '', $setting['setting_label']) ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Homepage Tab -->
                    <?php if ($active_tab == 'homepage'): ?>
                    <div class="space-y-6">
                        <h2 class="text-lg font-semibold text-gray-900">Halaman Beranda</h2>
                        <p class="text-sm text-gray-500">Atur konten dan gambar halaman beranda</p>
                        
                        <!-- Image Settings -->
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h3 class="text-sm font-medium text-gray-900 mb-4">Gambar Hero</h3>
                            <?php foreach ($homepage as $key => $setting): ?>
                                <?php if ($setting['setting_type'] == 'image'): ?>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($setting['setting_label']) ?></label>
                                    <div class="flex items-start gap-4">
                                        <?php if ($setting['setting_value']): ?>
                                        <img src="../<?= htmlspecialchars($setting['setting_value']) ?>" class="w-32 h-32 object-cover bg-gray-100 rounded-lg border">
                                        <?php else: ?>
                                        <div class="w-32 h-32 bg-gray-100 rounded-lg border flex items-center justify-center text-gray-400">
                                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex flex-col gap-2">
                                            <label class="cursor-pointer">
                                                <span class="px-4 py-2 bg-white hover:bg-gray-100 text-gray-700 rounded-lg font-medium transition inline-block text-sm border">
                                                    Pilih Gambar
                                                </span>
                                                <input type="file" name="<?= $key ?>" accept="image/*" class="hidden">
                                            </label>
                                            <?php if ($setting['setting_value']): ?>
                                            <a href="?tab=<?= $active_tab ?>&delete_image=<?= $key ?>" 
                                               onclick="return confirm('Hapus gambar ini?')"
                                               class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition inline-block text-sm text-center">
                                                Hapus
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Hero Image Display Settings -->
                            <div class="mt-6 pt-4 border-t border-gray-200">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Pengaturan Tampilan</h4>
                                <div class="grid sm:grid-cols-3 gap-4">
                                    <!-- Size -->
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Ukuran (%)</label>
                                        <input type="number" name="settings[hero_image_size]" 
                                               value="<?= htmlspecialchars(getSetting('hero_image_size') ?: '100') ?>" 
                                               min="10" max="200" step="5"
                                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none">
                                        <p class="text-xs text-gray-400 mt-1">10-200%</p>
                                    </div>
                                    <!-- Fit Mode -->
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Mode Tampilan</label>
                                        <select name="settings[hero_image_fit]" 
                                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none">
                                            <option value="contain" <?= getSetting('hero_image_fit') == 'contain' ? 'selected' : '' ?>>Contain (Utuh)</option>
                                            <option value="cover" <?= getSetting('hero_image_fit') == 'cover' ? 'selected' : '' ?>>Cover (Penuh)</option>
                                            <option value="fill" <?= getSetting('hero_image_fit') == 'fill' ? 'selected' : '' ?>>Fill (Stretch)</option>
                                            <option value="none" <?= getSetting('hero_image_fit') == 'none' ? 'selected' : '' ?>>None (Asli)</option>
                                        </select>
                                    </div>
                                    <!-- Position -->
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Posisi</label>
                                        <select name="settings[hero_image_position]" 
                                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none">
                                            <option value="center" <?= getSetting('hero_image_position') == 'center' ? 'selected' : '' ?>>Tengah</option>
                                            <option value="top" <?= getSetting('hero_image_position') == 'top' ? 'selected' : '' ?>>Atas</option>
                                            <option value="bottom" <?= getSetting('hero_image_position') == 'bottom' ? 'selected' : '' ?>>Bawah</option>
                                            <option value="left" <?= getSetting('hero_image_position') == 'left' ? 'selected' : '' ?>>Kiri</option>
                                            <option value="right" <?= getSetting('hero_image_position') == 'right' ? 'selected' : '' ?>>Kanan</option>
                                            <option value="top left" <?= getSetting('hero_image_position') == 'top left' ? 'selected' : '' ?>>Atas Kiri</option>
                                            <option value="top right" <?= getSetting('hero_image_position') == 'top right' ? 'selected' : '' ?>>Atas Kanan</option>
                                            <option value="bottom left" <?= getSetting('hero_image_position') == 'bottom left' ? 'selected' : '' ?>>Bawah Kiri</option>
                                            <option value="bottom right" <?= getSetting('hero_image_position') == 'bottom right' ? 'selected' : '' ?>>Bawah Kanan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Multi-language Content -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Konten Multi Bahasa</h3>
                            <p class="text-xs text-gray-500 mb-4">Atur teks dalam 3 bahasa: Indonesia, Banjar, Jawa</p>
                            
                            <div class="space-y-4">
                                <?php foreach ($homepage_labels as $key => $label): ?>
                                <div class="p-4 bg-gray-50 rounded-xl">
                                    <label class="block text-sm font-medium text-gray-900 mb-3"><?= htmlspecialchars($label) ?></label>
                                    <div class="grid md:grid-cols-3 gap-3">
                                        <?php foreach ($languages as $code => $name): ?>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1"><?= $name ?></label>
                                            <?php if (strpos($key, 'desc') !== false): ?>
                                            <textarea name="translations[<?= $key ?>][<?= $code ?>]" rows="2"
                                                      placeholder="<?= $name ?>"
                                                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition resize-none"><?= htmlspecialchars($homepage_translations[$key][$code] ?? '') ?></textarea>
                                            <?php else: ?>
                                            <input type="text" 
                                                   name="translations[<?= $key ?>][<?= $code ?>]" 
                                                   value="<?= htmlspecialchars($homepage_translations[$key][$code] ?? '') ?>" 
                                                   placeholder="<?= $name ?>"
                                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition">
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Footer Tab -->
                    <?php if ($active_tab == 'footer'): ?>
                    <div class="space-y-6">
                        <h2 class="text-lg font-semibold text-gray-900">Footer</h2>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php foreach ($footer as $key => $setting): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($setting['setting_label']) ?></label>
                                <input type="text" name="settings[<?= $key ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Language Tab -->
                    <?php if ($active_tab == 'language'): ?>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Multi Bahasa</h2>
                                <p class="text-sm text-gray-500">Atur teks dalam berbagai bahasa (Indonesia, Banjar, Jawa)</p>
                            </div>
                        </div>

                        <!-- Default Language -->
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bahasa Default</label>
                            <select name="settings[default_language]" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition">
                                <?php foreach ($languages as $code => $name): ?>
                                <option value="<?= $code ?>" <?= getSetting('default_language') == $code ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Translations -->
                        <div class="space-y-6">
                            <?php foreach ($labels as $key => $label): ?>
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <label class="block text-sm font-medium text-gray-900 mb-3"><?= htmlspecialchars($label) ?></label>
                                <div class="grid md:grid-cols-3 gap-3">
                                    <?php foreach ($languages as $code => $name): ?>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1"><?= $name ?></label>
                                        <input type="text" 
                                               name="translations[<?= $key ?>][<?= $code ?>]" 
                                               value="<?= htmlspecialchars($translations[$key][$code] ?? '') ?>" 
                                               placeholder="<?= $name ?>"
                                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Notifications Tab -->
                    <?php if ($active_tab == 'notifications'): 
                        ensurePushTables($conn);
                        $vapid = getVapidKeys($conn);
                        $subCount = countPushSubscriptions($conn);
                        $pushSupported = isPushSupported();
                    ?>
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Push Notifications</h2>
                            <p class="text-sm text-gray-500">Kirim notifikasi ke pengguna saat event absen dimulai</p>
                        </div>

                        <?php if (!$pushSupported): ?>
                        <div class="p-4 bg-red-50 rounded-xl border border-red-200">
                            <div class="flex items-center gap-2 text-red-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="font-medium">Push Notifications Tidak Didukung</span>
                            </div>
                            <p class="text-sm text-red-600 mt-2">Server hosting tidak mendukung OpenSSL EC (prime256v1). Fitur push notification tidak dapat digunakan. Hubungi provider hosting untuk mengaktifkan OpenSSL EC support.</p>
                        </div>
                        <?php else: ?>

                        <!-- VAPID Keys -->
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">VAPID Keys</h3>
                            <?php if ($vapid): ?>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Public Key</label>
                                    <div class="flex gap-2">
                                        <input type="text" value="<?= htmlspecialchars($vapid['public_key']) ?>" readonly 
                                               class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-mono" id="vapidPublicKey">
                                        <button type="button" onclick="copyToClipboard('vapidPublicKey')" 
                                                class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm">
                                            Copy
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-green-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    VAPID Keys sudah dikonfigurasi
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-sm text-yellow-600 mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                VAPID Keys belum dikonfigurasi
                            </div>
                            <?php endif; ?>
                            <button type="submit" name="generate_vapid" value="1" 
                                    class="mt-3 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-lg text-sm font-medium">
                                <?= $vapid ? 'Generate Ulang VAPID Keys' : 'Generate VAPID Keys' ?>
                            </button>
                            <?php if ($vapid): ?>
                            <p class="text-xs text-gray-400 mt-2">Perhatian: Generate ulang akan membuat semua subscriber harus subscribe ulang</p>
                            <?php endif; ?>
                        </div>

                        <!-- Subscription Stats -->
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">Statistik Subscriber</h3>
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold text-gray-900"><?= $subCount ?></p>
                                    <p class="text-sm text-gray-500">Perangkat terdaftar</p>
                                </div>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="p-4 bg-blue-50 rounded-xl border border-blue-100">
                            <h3 class="text-sm font-medium text-blue-900 mb-2">Cara Kerja</h3>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>1. Generate VAPID Keys (sekali saja)</li>
                                <li>2. User yang login akan otomatis diminta izin notifikasi</li>
                                <li>3. Saat admin memulai event absen, semua user yang subscribe akan menerima notifikasi</li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <script>
                    function copyToClipboard(id) {
                        var input = document.getElementById(id);
                        input.select();
                        document.execCommand('copy');
                        alert('Copied!');
                    }
                    </script>

                    <!-- Submit Button -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-3 bg-gray-900 hover:bg-gray-800 text-white font-semibold rounded-xl transition shadow-lg">
                                <span class="flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Simpan Perubahan
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
