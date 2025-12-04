<?php
include "auth.php";
include "../config/koneksi.php";

// Get admin data
$admin_id = $_SESSION['admin_id'];

// Cek apakah kolom email ada di tabel admin, tambahkan jika belum
$checkEmail = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'email'");
if (mysqli_num_rows($checkEmail) == 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER username");
}

$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admin WHERE id='$admin_id'"));

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile Info
    if (isset($_POST['update_profile'])) {
        $username = mysqli_real_escape_string($conn, trim($_POST['username']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        
        $canUpdate = true;
        
        // Hanya cek username jika berubah
        if ($username !== $admin['username']) {
            $stmtCheck = mysqli_prepare($conn, "SELECT id FROM admin WHERE username = ?");
            mysqli_stmt_bind_param($stmtCheck, "s", $username);
            mysqli_stmt_execute($stmtCheck);
            $checkResult = mysqli_stmt_get_result($stmtCheck);
            $check = mysqli_fetch_assoc($checkResult);
            mysqli_stmt_close($stmtCheck);
            
            if ($check) {
                $error = 'Username sudah digunakan';
                $canUpdate = false;
            }
        }
        
        if ($canUpdate) {
            $emailValue = $email ?: null;
            $stmtUpdate = mysqli_prepare($conn, "UPDATE admin SET username = ?, email = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmtUpdate, "ssi", $username, $emailValue, $admin_id);
            mysqli_stmt_execute($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);
            
            $success = 'Profil berhasil diperbarui';
            $admin['username'] = $username;
            $admin['email'] = $email;
        }
    }
    
    // Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $admin['password'])) {
            $error = 'Password saat ini salah';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Konfirmasi password tidak cocok';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE admin SET password='$hashed' WHERE id='$admin_id'");
            $success = 'Password berhasil diperbarui';
        }
    }
    
    // Save Cropped Photo
    if (isset($_POST['save_cropped_photo']) && !empty($_POST['cropped_image'])) {
        $cropped_image = $_POST['cropped_image'];
        
        // Decode base64 image
        $image_parts = explode(";base64,", $cropped_image);
        $image_base64 = base64_decode($image_parts[1]);
        
        $filename = 'admin_' . $admin_id . '_' . time() . '.png';
        $upload_dir = '../uploads/profiles/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old photo
        if (!empty($admin['picture']) && file_exists('../' . $admin['picture'])) {
            @unlink('../' . $admin['picture']);
        }
        
        if (file_put_contents($upload_dir . $filename, $image_base64)) {
            $picture_path = 'uploads/profiles/' . $filename;
            mysqli_query($conn, "UPDATE admin SET picture='$picture_path' WHERE id='$admin_id'");
            $admin['picture'] = $picture_path;
            $success = 'Foto profil berhasil diperbarui';
        } else {
            $error = 'Gagal menyimpan foto';
        }
    }
    
    // Refresh admin data
    $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admin WHERE id='$admin_id'"));
}

$picture_url = !empty($admin['picture']) ? '../' . $admin['picture'] : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - HIMA Politala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Profil Admin</h1>
                <p class="text-gray-500">Kelola informasi akun Anda</p>
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

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Profile Photo -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Foto Profil</h2>
                    
                    <div class="text-center">
                        <div class="w-32 h-32 mx-auto mb-4 rounded-full overflow-hidden bg-gray-100 border-4 border-gray-200">
                            <?php if ($picture_url): ?>
                                <img src="<?= htmlspecialchars($picture_url) ?>?t=<?= time() ?>" alt="Admin" class="w-full h-full object-cover" id="currentPhoto">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400" id="defaultAvatar">
                                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <label class="cursor-pointer">
                            <span class="inline-block px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition">
                                Pilih Foto
                            </span>
                            <input type="file" id="photoInput" accept="image/*" class="hidden">
                        </label>
                        
                        <p class="text-xs text-gray-400 mt-3">JPG, PNG, GIF, WebP. Max 2MB</p>
                    </div>
                </div>

                <!-- Profile Info -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Akun</h2>
                    
                    <form method="POST">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition"
                                       placeholder="Isi dengan email akun user Anda">
                                <p class="text-xs text-gray-400 mt-1">Untuk mengakses forum dengan akun user terkait</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ID Admin</label>
                                <input type="text" value="#<?= $admin['id'] ?>" 
                                       class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-500" readonly>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" 
                                class="mt-6 w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-medium rounded-xl transition">
                            Simpan Perubahan
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <!-- <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Ubah Password</h2>
                    
                    <form method="POST">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password Saat Ini</label>
                                <input type="password" name="current_password" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                                <input type="password" name="new_password" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                                <input type="password" name="confirm_password" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_password" 
                                class="mt-6 w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-medium rounded-xl transition">
                            Ubah Password
                        </button>
                    </form>
                </div> -->
            </div>
        </div>
    </main>

    <!-- Crop Modal -->
    <div id="cropModal" class="fixed inset-0 bg-black/70 z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Atur Foto Profil</h3>
                <button onclick="closeCropModal()" class="p-2 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="p-4">
                <div class="bg-gray-100 rounded-xl overflow-hidden" style="height: 300px;">
                    <img id="cropImage" src="" class="max-w-full">
                </div>
                
                <!-- Controls -->
                <div class="flex items-center justify-center gap-2 mt-4">
                    <button onclick="cropper.zoom(0.1)" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="Zoom In">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.zoom(-0.1)" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="Zoom Out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.rotate(-90)" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="Rotate Left">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.rotate(90)" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="Rotate Right">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"></path>
                        </svg>
                    </button>
                    <button onclick="cropper.reset()" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="Reset">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeCropModal()" class="flex-1 py-2.5 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition">
                    Batal
                </button>
                <button onclick="saveCroppedPhoto()" class="flex-1 py-2.5 bg-gray-900 text-white font-medium rounded-xl hover:bg-gray-800 transition">
                    Simpan Foto
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden form for saving cropped photo -->
    <form id="cropForm" method="POST" class="hidden">
        <input type="hidden" name="save_cropped_photo" value="1">
        <input type="hidden" name="cropped_image" id="croppedImageInput">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
    let cropper = null;
    const photoInput = document.getElementById('photoInput');
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');

    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file maksimal 2MB');
            return;
        }
        
        // Validate file type
        if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
            alert('Format file tidak didukung');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            cropImage.src = e.target.result;
            openCropModal();
        };
        reader.readAsDataURL(file);
    });

    function openCropModal() {
        cropModal.classList.remove('hidden');
        cropModal.classList.add('flex');
        
        if (cropper) {
            cropper.destroy();
        }
        
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            cropBoxResizable: true,
            cropBoxMovable: true,
            background: false
        });
    }

    function closeCropModal() {
        cropModal.classList.add('hidden');
        cropModal.classList.remove('flex');
        
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        
        photoInput.value = '';
    }

    function saveCroppedPhoto() {
        if (!cropper) return;
        
        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        const croppedImage = canvas.toDataURL('image/png');
        document.getElementById('croppedImageInput').value = croppedImage;
        document.getElementById('cropForm').submit();
    }

    // Close modal on outside click
    cropModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeCropModal();
        }
    });
    </script>

</body>
</html>
