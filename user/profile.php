<?php 
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";
include "../config/lang.php";

$user_id = $_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));

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
    mysqli_query($conn, "UPDATE users SET picture = NULL WHERE id = '$user_id'");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <style>
        .glass { backdrop-filter: blur(10px); }
        .cropper-view-box, .cropper-face { border-radius: 50%; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">

    <!-- Navbar -->
    <nav class="bg-white/80 glass border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <span class="text-slate-900 font-bold text-lg"><?= __('my_profile') ?></span>
            </div>
            <a href="dashboard.php" class="text-slate-500 hover:text-slate-900 flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-slate-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <?= __('back') ?>
            </a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Profile Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center sticky top-24">
                    <div class="relative inline-block mb-4">
                        <?php if($has_picture): ?>
                        <img src="<?= htmlspecialchars($picture_url) ?>" alt="Profile" 
                            class="w-32 h-32 rounded-full border-4 border-slate-100 object-cover shadow-lg">
                        <?php else: ?>
                        <div class="w-32 h-32 bg-slate-100 rounded-full flex items-center justify-center">
                            <svg class="w-16 h-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <?php endif; ?>
                        <button onclick="document.getElementById('fileInput').click()" class="absolute bottom-0 right-0 w-10 h-10 bg-slate-900 hover:bg-slate-800 text-white rounded-full shadow-lg flex items-center justify-center transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                    </div>
                    <input type="file" id="fileInput" accept="image/*" class="hidden" onchange="openCropperModal(this)">

                    <h2 class="text-xl font-bold text-slate-900"><?= htmlspecialchars($user['nama']) ?></h2>
                    <p class="text-slate-500 text-sm mb-4"><?= htmlspecialchars($user['email']) ?></p>
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 rounded-full">
                        <span class="text-slate-600 text-sm">NIM:</span>
                        <span class="font-mono font-medium text-slate-900"><?= htmlspecialchars($user['nim']) ?></span>
                    </div>
                    
                    <?php if($has_picture): ?>
                    <form method="POST" class="mt-4">
                        <button type="submit" name="remove_photo" onclick="return confirm('<?= __('delete_photo_confirm') ?>')" class="text-red-500 hover:text-red-600 text-sm font-medium">
                            <?= __('delete_photo') ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Section -->
            <div class="lg:col-span-2 space-y-6">
                
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
                    <div class="p-6">
                        <div class="grid sm:grid-cols-3 gap-6">
                            <div class="text-center p-4 bg-slate-50 rounded-xl">
                                <p class="text-slate-500 text-sm mb-1"><?= __('entry_year') ?></p>
                                <p class="text-3xl font-bold text-slate-900"><?= $tahun_masuk ?></p>
                            </div>
                            <div class="text-center p-4 bg-blue-50 rounded-xl">
                                <p class="text-blue-600 text-sm mb-1"><?= __('current_semester') ?></p>
                                <p class="text-3xl font-bold text-blue-600"><?= $semester ?></p>
                            </div>
                            <div class="text-center p-4 bg-slate-50 rounded-xl">
                                <p class="text-slate-500 text-sm mb-1"><?= __('class') ?></p>
                                <p class="text-3xl font-bold text-slate-900"><?= ($user['kelas'] && $user['kelas'] != '-') ? htmlspecialchars($user['kelas']) : '-' ?></p>
                            </div>
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
    </script>

</body>
</html>
