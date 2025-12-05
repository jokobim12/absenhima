<?php 
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";
include "../config/lang.php";

$user_id = intval($_SESSION['user_id']);
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$semester = hitungSemester($user['nim']);
$tahun_masuk = 2000 + intval(substr($user['nim'], 0, 2));

$success = "";
$error = "";

// Handle cropped photo upload (base64)
if(isset($_POST['cropped_image']) && !empty($_POST['cropped_image'])){
    $data = $_POST['cropped_image'];
    
    // Extract base64 data
    if(preg_match('/^data:image\/(\w+);base64,/', $data, $type)){
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]);
        
        if(!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])){
            $error = "Format file tidak didukung.";
        } else {
            $data = base64_decode($data);
            
            if($data === false){
                $error = "Gagal memproses gambar.";
            } else {
                $newname = 'profile_' . $user_id . '_' . time() . '.' . $type;
                $upload_dir = dirname(__FILE__) . '/../uploads/profiles/';
                $upload_path = $upload_dir . $newname;
                
                if(!is_dir($upload_dir)){
                    mkdir($upload_dir, 0777, true);
                }
                
                // Delete old photo
                if(!empty($user['picture']) && strpos($user['picture'], 'uploads/profiles/') !== false){
                    $old_file = dirname(__FILE__) . '/../' . $user['picture'];
                    if(file_exists($old_file)){
                        unlink($old_file);
                    }
                }
                
                if(file_put_contents($upload_path, $data)){
                    $picture_url = 'uploads/profiles/' . $newname;
                    $stmt = mysqli_prepare($conn, "UPDATE users SET picture = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $picture_url, $user_id);
                    mysqli_stmt_execute($stmt);
                    $user['picture'] = $picture_url;
                    $success = "Foto profil berhasil diupdate!";
                } else {
                    $error = "Gagal menyimpan foto.";
                }
            }
        }
    } else {
        $error = "Format gambar tidak valid.";
    }
}

// Handle remove photo
if(isset($_POST['remove_photo'])){
    if(!empty($user['picture']) && strpos($user['picture'], 'uploads/profiles/') !== false){
        $old_file = dirname(__FILE__) . '/../' . $user['picture'];
        if(file_exists($old_file)){
            unlink($old_file);
        }
    }
    $stmt = mysqli_prepare($conn, "UPDATE users SET picture = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $user['picture'] = '';
    $success = "Foto profil berhasil dihapus.";
}

// Handle update kelas
if(isset($_POST['update'])){
    $kelas = trim($_POST['kelas']);
    
    if(empty($kelas)){
        $error = "Kelas tidak boleh kosong!";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET kelas = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $kelas, $user_id);
        
        if(mysqli_stmt_execute($stmt)){
            $success = "Profil berhasil diupdate!";
            $user['kelas'] = $kelas;
        } else {
            $error = "Gagal mengupdate profil.";
        }
    }
}

$has_picture = !empty($user['picture']);
$picture_url = $has_picture ? (strpos($user['picture'], 'http') === 0 ? $user['picture'] : '../' . $user['picture']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Absensi HIMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    primary: '#6366f1',
                    secondary: '#8b5cf6',
                }
            }
        }
    }
    </script>
    <script>
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark');
    }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <style>
        .glass { backdrop-filter: blur(10px); }
        .cropper-view-box, .cropper-face { border-radius: 50%; }
        /* Dark mode overrides */
        .dark body { background: #0a0a0a !important; }
        .dark .bg-gradient-to-br { background: #0a0a0a !important; }
        .dark .bg-white { background: #1a1a1a !important; }
        .dark .bg-white\/80 { background: rgba(26, 26, 26, 0.9) !important; }
        .dark .bg-slate-50 { background: #111 !important; }
        .dark .bg-slate-100 { background: #1a1a1a !important; }
        .dark .border-slate-200 { border-color: #333 !important; }
        .dark .border-slate-100 { border-color: #222 !important; }
        .dark .text-slate-900 { color: #f1f1f1 !important; }
        .dark .text-slate-800 { color: #e5e5e5 !important; }
        .dark .text-slate-700 { color: #d4d4d4 !important; }
        .dark .text-slate-600 { color: #a3a3a3 !important; }
        .dark .text-slate-500 { color: #737373 !important; }
        .dark .hover\:bg-slate-50:hover { background: #222 !important; }
        .dark .hover\:bg-slate-100:hover { background: #333 !important; }
        .dark input, .dark select, .dark textarea { background: #1a1a1a !important; border-color: #333 !important; color: #e5e5e5 !important; }
        .dark .bg-green-50 { background: rgba(34, 197, 94, 0.1) !important; }
        .dark .bg-orange-50 { background: rgba(249, 115, 22, 0.1) !important; }
        .dark .bg-blue-50 { background: rgba(59, 130, 246, 0.1) !important; }
        .dark .bg-purple-50 { background: rgba(168, 85, 247, 0.1) !important; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">

    <!-- Navbar -->
    <nav class="bg-white/80 glass border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <a href="dashboard.php" class="p-2 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <span class="text-slate-900 font-bold"><?= __('my_profile') ?></span>
            </div>
            <button onclick="toggleDarkMode()" id="darkModeBtn" class="text-slate-500 hover:text-slate-900 p-2 hover:bg-slate-100 rounded-lg transition" title="Dark Mode">
                <svg id="sunIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <svg id="moonIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
            </button>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
        
        <!-- Profile Card - Compact for Mobile -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
            <div class="flex items-center gap-4">
                <div class="relative flex-shrink-0">
                    <?php if($has_picture): ?>
                    <img src="<?= htmlspecialchars($picture_url) ?>" alt="Profile" 
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-slate-100 object-cover shadow-lg">
                    <?php else: ?>
                    <div class="w-20 h-20 sm:w-24 sm:h-24 bg-slate-100 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 sm:w-12 sm:h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <?php endif; ?>
                    <button onclick="document.getElementById('fileInput').click()" class="absolute bottom-0 right-0 w-8 h-8 sm:w-9 sm:h-9 bg-slate-900 hover:bg-slate-800 text-white rounded-full shadow-lg flex items-center justify-center transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </button>
                </div>
                <input type="file" id="fileInput" accept="image/*" class="hidden" onchange="openCropperModal(this)">
                
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900 truncate"><?= htmlspecialchars($user['nama']) ?></h2>
                    <p class="text-slate-500 text-sm truncate"><?= htmlspecialchars($user['email']) ?></p>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 rounded-full mt-2 text-sm">
                        <span class="text-slate-500">NIM:</span>
                        <span class="font-mono font-medium text-slate-900"><?= htmlspecialchars($user['nim']) ?></span>
                    </div>
                    <?php if($has_picture): ?>
                    <form method="POST" class="mt-2">
                        <button type="submit" name="remove_photo" onclick="return confirm('<?= __('delete_photo_confirm') ?>')" class="text-red-500 hover:text-red-600 text-xs font-medium">
                            <?= __('delete_photo') ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-4 sm:space-y-6">

                <?php if($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <?= $success ?>
                </div>
                <?php endif; ?>

                <?php if($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-5 py-4 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <?= $error ?>
                </div>
                <?php endif; ?>

                <!-- Info Otomatis -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900"><?= __('academic_info') ?></h3>
                        <p class="text-slate-500 text-sm"><?= __('auto_calculated') ?></p>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="grid grid-cols-3 gap-2 sm:gap-4">
                            <div class="text-center p-3 sm:p-4 bg-slate-50 rounded-xl">
                                <p class="text-slate-500 text-xs sm:text-sm mb-1"><?= __('entry_year') ?></p>
                                <p class="text-xl sm:text-2xl font-bold text-slate-900"><?= $tahun_masuk ?></p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-blue-50 rounded-xl">
                                <p class="text-blue-600 text-xs sm:text-sm mb-1"><?= __('current_semester') ?></p>
                                <p class="text-xl sm:text-2xl font-bold text-blue-600"><?= $semester ?></p>
                            </div>
                            <div class="text-center p-3 sm:p-4 bg-slate-50 rounded-xl">
                                <p class="text-slate-500 text-xs sm:text-sm mb-1"><?= __('class') ?></p>
                                <p class="text-xl sm:text-2xl font-bold text-slate-900"><?= ($user['kelas'] && $user['kelas'] != '-') ? htmlspecialchars($user['kelas']) : '-' ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Heatmap -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900">Riwayat Kehadiran</h3>
                            <p class="text-slate-500 text-sm">Visualisasi kehadiran tahun <span id="heatmapYear"><?= date('Y') ?></span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="../api/export_attendance.php?year=<?= date('Y') ?>" target="_blank" id="exportPdfLink" class="p-1.5 hover:bg-slate-100 rounded text-slate-400 hover:text-secondary" title="Export PDF">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </a>
                            <button onclick="changeHeatmapYear(-1)" class="p-1 hover:bg-slate-100 rounded">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <button onclick="changeHeatmapYear(1)" class="p-1 hover:bg-slate-100 rounded">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="text-center p-3 bg-green-50 rounded-xl">
                                <p class="text-green-600 text-sm mb-1">Total Hadir</p>
                                <p class="text-2xl font-bold text-green-600" id="heatmapTotal">0</p>
                            </div>
                            <div class="text-center p-3 bg-orange-50 rounded-xl">
                                <p class="text-orange-600 text-sm mb-1">Streak Saat Ini</p>
                                <p class="text-2xl font-bold text-orange-600" id="heatmapStreak">0</p>
                            </div>
                            <div class="text-center p-3 bg-purple-50 rounded-xl">
                                <p class="text-purple-600 text-sm mb-1">Streak Terpanjang</p>
                                <p class="text-2xl font-bold text-purple-600" id="heatmapLongest">0</p>
                            </div>
                        </div>
                        <!-- Heatmap Container -->
                        <div class="overflow-x-auto">
                            <div id="heatmapContainer" class="min-w-[700px]">
                                <div class="flex gap-1 mb-2 text-xs text-slate-400 pl-8">
                                    <span class="w-[54px] text-center">Jan</span>
                                    <span class="w-[54px] text-center">Feb</span>
                                    <span class="w-[54px] text-center">Mar</span>
                                    <span class="w-[54px] text-center">Apr</span>
                                    <span class="w-[42px] text-center">Mei</span>
                                    <span class="w-[54px] text-center">Jun</span>
                                    <span class="w-[54px] text-center">Jul</span>
                                    <span class="w-[54px] text-center">Agu</span>
                                    <span class="w-[54px] text-center">Sep</span>
                                    <span class="w-[54px] text-center">Okt</span>
                                    <span class="w-[54px] text-center">Nov</span>
                                    <span class="w-[54px] text-center">Des</span>
                                </div>
                                <div class="flex">
                                    <div class="flex flex-col gap-1 text-xs text-slate-400 pr-2 justify-around">
                                        <span>Sen</span>
                                        <span>Rab</span>
                                        <span>Jum</span>
                                    </div>
                                    <div id="heatmapGrid" class="flex gap-[3px]">
                                        <!-- Grid will be generated by JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Legend -->
                        <div class="flex items-center justify-end gap-2 mt-4 text-xs text-slate-500">
                            <span>Sedikit</span>
                            <div class="w-3 h-3 rounded-sm bg-slate-200"></div>
                            <div class="w-3 h-3 rounded-sm bg-green-200"></div>
                            <div class="w-3 h-3 rounded-sm bg-green-400"></div>
                            <div class="w-3 h-3 rounded-sm bg-green-600"></div>
                            <div class="w-3 h-3 rounded-sm bg-green-800"></div>
                            <span>Banyak</span>
                        </div>
                    </div>
                </div>

                <!-- Edit Kelas -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900"><?= __('edit_data') ?></h3>
                        <p class="text-slate-500 text-sm"><?= __('complete_class') ?></p>
                    </div>
                    <form method="POST" class="p-6">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-700 mb-2"><?= __('class') ?> <span class="text-red-500">*</span></label>
                            <input type="text" name="kelas" value="<?= htmlspecialchars($user['kelas'] == '-' ? '' : $user['kelas']) ?>" required
                                placeholder="<?= __('class_example') ?>"
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white outline-none transition text-lg">
                            <p class="text-slate-400 text-sm mt-2"><?= __('class_hint') ?></p>
                        </div>
                        <button type="submit" name="update" 
                            class="w-full py-4 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white font-semibold rounded-xl transition shadow-lg shadow-slate-900/20">
                            <?= __('save_changes') ?>
                        </button>
                    </form>
                </div>

                <!-- Wallpaper Forum -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">Wallpaper Forum Diskusi</h3>
                        <p class="text-slate-500 text-sm">Kustomisasi tampilan latar belakang forum</p>
                    </div>
                    <div class="p-6">
                        <!-- Preview -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Preview</label>
                            <div id="wallpaperPreview" class="relative h-40 rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                                <div id="wallpaperBg" class="absolute inset-0 bg-cover bg-center"></div>
                                <div id="wallpaperOverlay" class="absolute inset-0 bg-black" style="opacity: 0.5;"></div>
                                <div class="absolute inset-0 flex items-center justify-center p-4">
                                    <div class="bg-white rounded-lg px-4 py-2 text-sm text-slate-600 shadow">
                                        Contoh tampilan chat forum
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upload -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Gambar Wallpaper</label>
                            <div class="flex gap-2">
                                <label class="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-slate-100 hover:bg-slate-200 rounded-xl cursor-pointer transition text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span id="uploadLabel">Pilih Gambar</span>
                                    <input type="file" id="wallpaperInput" accept="image/jpeg,image/png,image/webp" class="hidden">
                                </label>
                                <button type="button" id="removeWallpaperBtn" class="px-4 py-3 bg-red-100 hover:bg-red-200 text-red-600 rounded-xl transition hidden">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-slate-400 text-xs mt-1">Format: JPG, PNG, WEBP. Maksimal 5MB.</p>
                        </div>
                        
                        <!-- Opacity Slider -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Tingkat Gelap: <span id="opacityValue">50%</span>
                            </label>
                            <input type="range" id="opacitySlider" min="0" max="100" value="50" 
                                class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-800">
                            <div class="flex justify-between text-xs text-slate-400 mt-1">
                                <span>Terang</span>
                                <span>Gelap</span>
                            </div>
                        </div>
                        
                        <div id="wallpaperStatus" class="text-sm text-green-600 hidden"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Cropper Modal -->
    <div id="cropperModal" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden transform transition-all scale-95 opacity-0" id="cropperModalContent">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-xl font-bold text-slate-900"><?= __('adjust_photo') ?></h3>
                <button onclick="closeCropperModal()" class="p-2 hover:bg-slate-100 rounded-lg transition">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="p-4 bg-slate-900">
                <div class="max-h-[400px] overflow-hidden">
                    <img id="cropperImage" src="" class="max-w-full">
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button onclick="cropper.zoom(0.1)" class="p-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 transition" title="Zoom In">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.zoom(-0.1)" class="p-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 transition" title="Zoom Out">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.rotate(-90)" class="p-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 transition" title="Rotate Left">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.rotate(90)" class="p-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-100 transition" title="Rotate Right">
                        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex gap-3">
                    <button onclick="closeCropperModal()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-100 transition">
                        <?= __('cancel') ?>
                    </button>
                    <button onclick="saveCroppedImage()" class="px-6 py-2 bg-slate-900 text-white rounded-lg font-medium hover:bg-slate-800 transition">
                        <?= __('save') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for cropped image -->
    <form id="croppedForm" method="POST" class="hidden">
        <input type="hidden" name="cropped_image" id="croppedImageInput">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
    let cropper = null;

    function openCropperModal(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const modal = document.getElementById('cropperModal');
                const content = document.getElementById('cropperModalContent');
                const image = document.getElementById('cropperImage');
                
                image.src = e.target.result;
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                    
                    // Initialize cropper
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    cropper = new Cropper(image, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        cropBoxResizable: true,
                        cropBoxMovable: true,
                        guides: true,
                        center: true,
                        highlight: false,
                        background: false,
                    });
                }, 100);
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function closeCropperModal() {
        const modal = document.getElementById('cropperModal');
        const content = document.getElementById('cropperModalContent');
        
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            
            // Reset file input
            document.getElementById('fileInput').value = '';
        }, 200);
    }

    function saveCroppedImage() {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            const croppedData = canvas.toDataURL('image/jpeg', 0.9);
            document.getElementById('croppedImageInput').value = croppedData;
            document.getElementById('croppedForm').submit();
        }
    }

    // Close modal when clicking outside
    document.getElementById('cropperModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCropperModal();
        }
    });

    // Wallpaper Settings
    const wallpaperBg = document.getElementById('wallpaperBg');
    const wallpaperOverlay = document.getElementById('wallpaperOverlay');
    const wallpaperInput = document.getElementById('wallpaperInput');
    const opacitySlider = document.getElementById('opacitySlider');
    const opacityValue = document.getElementById('opacityValue');
    const removeWallpaperBtn = document.getElementById('removeWallpaperBtn');
    const wallpaperStatus = document.getElementById('wallpaperStatus');
    const uploadLabel = document.getElementById('uploadLabel');

    let currentWallpaper = null;
    let debounceTimer = null;

    // Load current settings
    async function loadWallpaperSettings() {
        try {
            const res = await fetch('../api/forum_wallpaper.php');
            const data = await res.json();
            if (data.success) {
                if (data.wallpaper) {
                    currentWallpaper = data.wallpaper;
                    wallpaperBg.style.backgroundImage = `url('../${data.wallpaper}')`;
                    removeWallpaperBtn.classList.remove('hidden');
                    uploadLabel.textContent = 'Ganti Gambar';
                }
                const opacityPercent = Math.round(data.opacity * 100);
                opacitySlider.value = opacityPercent;
                opacityValue.textContent = opacityPercent + '%';
                wallpaperOverlay.style.opacity = data.opacity;
            }
        } catch (err) {
            console.error('Error loading wallpaper settings:', err);
        }
    }

    // Upload wallpaper
    wallpaperInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            showStatus('Ukuran maksimal 5MB', 'red');
            return;
        }

        const formData = new FormData();
        formData.append('wallpaper', file);

        try {
            uploadLabel.textContent = 'Mengupload...';
            const res = await fetch('../api/forum_wallpaper.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                currentWallpaper = data.wallpaper;
                wallpaperBg.style.backgroundImage = `url('../${data.wallpaper}')`;
                removeWallpaperBtn.classList.remove('hidden');
                uploadLabel.textContent = 'Ganti Gambar';
                showStatus('Wallpaper berhasil diupload', 'green');
            } else {
                showStatus(data.error || 'Gagal upload', 'red');
                uploadLabel.textContent = currentWallpaper ? 'Ganti Gambar' : 'Pilih Gambar';
            }
        } catch (err) {
            console.error('Upload error:', err);
            showStatus('Gagal upload wallpaper', 'red');
            uploadLabel.textContent = currentWallpaper ? 'Ganti Gambar' : 'Pilih Gambar';
        }
        
        wallpaperInput.value = '';
    });

    // Opacity slider
    opacitySlider.addEventListener('input', function() {
        const opacity = this.value / 100;
        opacityValue.textContent = this.value + '%';
        wallpaperOverlay.style.opacity = opacity;
        
        // Debounce save
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            saveOpacity(opacity);
        }, 500);
    });

    async function saveOpacity(opacity) {
        try {
            const formData = new FormData();
            formData.append('opacity', opacity);
            
            await fetch('../api/forum_wallpaper.php', {
                method: 'POST',
                body: formData
            });
        } catch (err) {
            console.error('Error saving opacity:', err);
        }
    }

    // Remove wallpaper
    removeWallpaperBtn.addEventListener('click', async function() {
        if (!confirm('Hapus wallpaper?')) return;
        
        try {
            const formData = new FormData();
            formData.append('remove', '1');
            
            const res = await fetch('../api/forum_wallpaper.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                currentWallpaper = null;
                wallpaperBg.style.backgroundImage = '';
                removeWallpaperBtn.classList.add('hidden');
                uploadLabel.textContent = 'Pilih Gambar';
                showStatus('Wallpaper dihapus', 'green');
            }
        } catch (err) {
            console.error('Error removing wallpaper:', err);
            showStatus('Gagal menghapus', 'red');
        }
    });

    function showStatus(message, color) {
        wallpaperStatus.textContent = message;
        wallpaperStatus.className = `text-sm text-${color}-600`;
        wallpaperStatus.classList.remove('hidden');
        setTimeout(() => {
            wallpaperStatus.classList.add('hidden');
        }, 3000);
    }

    // Load on page load
    loadWallpaperSettings();

    // ==================== ATTENDANCE HEATMAP ====================
    let currentHeatmapYear = new Date().getFullYear();
    
    async function loadHeatmap(year) {
        try {
            const res = await fetch(`../api/attendance_heatmap.php?year=${year}`);
            const data = await res.json();
            
            if (data.success) {
                document.getElementById('heatmapYear').textContent = year;
                document.getElementById('heatmapTotal').textContent = data.total;
                document.getElementById('heatmapStreak').textContent = data.current_streak;
                document.getElementById('heatmapLongest').textContent = data.longest_streak;
                
                renderHeatmap(year, data.attendance);
            }
        } catch (err) {
            console.error('Error loading heatmap:', err);
        }
    }
    
    function renderHeatmap(year, attendance) {
        const grid = document.getElementById('heatmapGrid');
        grid.innerHTML = '';
        
        // Get first day of year
        const startDate = new Date(year, 0, 1);
        const endDate = new Date(year, 11, 31);
        
        // Adjust to start from Monday
        const startDay = startDate.getDay();
        const adjustedStart = new Date(startDate);
        adjustedStart.setDate(adjustedStart.getDate() - (startDay === 0 ? 6 : startDay - 1));
        
        // Create weeks
        let currentDate = new Date(adjustedStart);
        let weekHtml = '';
        let weeksHtml = '';
        
        while (currentDate <= endDate || currentDate.getDay() !== 1) {
            const dateStr = currentDate.toISOString().split('T')[0];
            const count = attendance[dateStr] || 0;
            const isCurrentYear = currentDate.getFullYear() === year;
            
            let colorClass = 'bg-slate-100';
            if (isCurrentYear && count > 0) {
                if (count === 1) colorClass = 'bg-green-200';
                else if (count === 2) colorClass = 'bg-green-400';
                else if (count === 3) colorClass = 'bg-green-600';
                else colorClass = 'bg-green-800';
            } else if (!isCurrentYear) {
                colorClass = 'bg-slate-50';
            }
            
            const tooltip = isCurrentYear ? `${count} kehadiran pada ${dateStr}` : '';
            weekHtml += `<div class="w-3 h-3 rounded-sm ${colorClass} ${isCurrentYear ? 'cursor-pointer hover:ring-2 hover:ring-slate-400' : 'opacity-30'}" title="${tooltip}"></div>`;
            
            // If Sunday (end of week)
            if (currentDate.getDay() === 0) {
                weeksHtml += `<div class="flex flex-col gap-[3px]">${weekHtml}</div>`;
                weekHtml = '';
            }
            
            currentDate.setDate(currentDate.getDate() + 1);
            
            // Safety break
            if (currentDate.getFullYear() > year + 1) break;
        }
        
        // Add remaining days
        if (weekHtml) {
            weeksHtml += `<div class="flex flex-col gap-[3px]">${weekHtml}</div>`;
        }
        
        grid.innerHTML = weeksHtml;
    }
    
    function changeHeatmapYear(delta) {
        const newYear = currentHeatmapYear + delta;
        if (newYear >= 2020 && newYear <= new Date().getFullYear()) {
            currentHeatmapYear = newYear;
            loadHeatmap(currentHeatmapYear);
            document.getElementById('exportPdfLink').href = `../api/export_attendance.php?year=${newYear}`;
        }
    }
    
    // Load heatmap on page load
    loadHeatmap(currentHeatmapYear);

    // ==================== DARK MODE ====================
    function toggleDarkMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', isDark);
        document.body.style.background = isDark ? '#0a0a0a' : '';
        
        // Toggle icons
        document.getElementById('sunIcon').classList.toggle('hidden', !isDark);
        document.getElementById('moonIcon').classList.toggle('hidden', isDark);
    }
    
    // Initialize dark mode icons
    function initDarkModeIcons() {
        const isDark = document.documentElement.classList.contains('dark');
        document.getElementById('sunIcon').classList.toggle('hidden', !isDark);
        document.getElementById('moonIcon').classList.toggle('hidden', isDark);
        if (isDark) {
            document.body.style.background = '#0a0a0a';
        }
    }
    initDarkModeIcons();
    </script>

</body>
</html>
