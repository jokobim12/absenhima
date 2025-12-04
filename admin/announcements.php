<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

$success = '';
$error = '';

// Handle delete
if (isset($_POST['delete'])) {
    verifyCsrfOrDie();
    $id = intval($_POST['delete']);
    mysqli_query($conn, "DELETE FROM announcements WHERE id = $id");
    $success = 'Pengumuman berhasil dihapus';
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    mysqli_query($conn, "UPDATE announcements SET is_active = NOT is_active WHERE id = $id");
    header("Location: announcements.php");
    exit;
}

// Handle toggle pin
if (isset($_GET['pin'])) {
    $id = intval($_GET['pin']);
    mysqli_query($conn, "UPDATE announcements SET is_pinned = NOT is_pinned WHERE id = $id");
    header("Location: announcements.php");
    exit;
}

// Handle create/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    verifyCsrfOrDie();
    
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'info';
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    if (empty($title) || empty($content)) {
        $error = 'Judul dan konten harus diisi';
    } else {
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE announcements SET title = ?, content = ?, type = ?, is_pinned = ?, expires_at = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssisi", $title, $content, $type, $is_pinned, $expires_at, $id);
        } else {
            $admin_id = $_SESSION['admin_id'];
            $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, type, is_pinned, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssisi", $title, $content, $type, $is_pinned, $expires_at, $admin_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $success = $id > 0 ? 'Pengumuman berhasil diupdate' : 'Pengumuman berhasil dibuat';
        } else {
            $error = 'Gagal menyimpan pengumuman';
        }
    }
}

// Get announcements
$announcements = mysqli_query($conn, "SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC");

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM announcements WHERE id = $edit_id"));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengumuman - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Kelola Pengumuman</h1>
                    <p class="text-gray-500">Buat dan kelola pengumuman untuk mahasiswa</p>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl"><?= $error ?></div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Form -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4"><?= $edit ? 'Edit Pengumuman' : 'Buat Pengumuman Baru' ?></h3>
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Judul</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Konten</label>
                                <textarea name="content" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipe</label>
                                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="info" <?= ($edit['type'] ?? '') == 'info' ? 'selected' : '' ?>>Info (Biru)</option>
                                    <option value="success" <?= ($edit['type'] ?? '') == 'success' ? 'selected' : '' ?>>Sukses (Hijau)</option>
                                    <option value="warning" <?= ($edit['type'] ?? '') == 'warning' ? 'selected' : '' ?>>Peringatan (Kuning)</option>
                                    <option value="danger" <?= ($edit['type'] ?? '') == 'danger' ? 'selected' : '' ?>>Penting (Merah)</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kadaluarsa (opsional)</label>
                                <input type="datetime-local" name="expires_at" value="<?= $edit['expires_at'] ? date('Y-m-d\TH:i', strtotime($edit['expires_at'])) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="is_pinned" value="1" <?= ($edit['is_pinned'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded">
                                    <span class="text-sm text-gray-700">Pin di atas</span>
                                </label>
                            </div>
                            
                            <div class="flex gap-2">
                                <?php if ($edit): ?>
                                <a href="announcements.php" class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium text-center hover:bg-gray-200 transition">Batal</a>
                                <?php endif; ?>
                                <button type="submit" name="save" class="flex-1 py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 transition">
                                    <?= $edit ? 'Update' : 'Buat' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <?php if (mysqli_num_rows($announcements) > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php while ($a = mysqli_fetch_assoc($announcements)): ?>
                            <div class="p-4 hover:bg-gray-50 <?= !$a['is_active'] ? 'opacity-50' : '' ?>">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <?php if ($a['is_pinned']): ?>
                                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                            <?php endif; ?>
                                            <span class="px-2 py-0.5 rounded text-xs font-medium <?php
                                                echo match($a['type']) {
                                                    'info' => 'bg-blue-100 text-blue-700',
                                                    'success' => 'bg-green-100 text-green-700',
                                                    'warning' => 'bg-yellow-100 text-yellow-700',
                                                    'danger' => 'bg-red-100 text-red-700',
                                                    default => 'bg-gray-100 text-gray-700'
                                                };
                                            ?>"><?= ucfirst($a['type']) ?></span>
                                            <?php if (!$a['is_active']): ?>
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Nonaktif</span>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($a['title']) ?></h4>
                                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?= htmlspecialchars($a['content']) ?></p>
                                        <p class="text-xs text-gray-400 mt-2">
                                            <?= date('d M Y H:i', strtotime($a['created_at'])) ?>
                                            <?= $a['expires_at'] ? ' • Kadaluarsa: ' . date('d M Y', strtotime($a['expires_at'])) : '' ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <a href="?pin=<?= $a['id'] ?>" class="p-2 text-gray-400 hover:text-yellow-600 rounded-lg hover:bg-gray-100" title="<?= $a['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                                            <svg class="w-4 h-4" fill="<?= $a['is_pinned'] ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                            </svg>
                                        </a>
                                        <a href="?toggle=<?= $a['id'] ?>" class="p-2 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-gray-100" title="<?= $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $a['is_active'] ? 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' : 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21' ?>"></path>
                                            </svg>
                                        </a>
                                        <a href="?edit=<?= $a['id'] ?>" class="p-2 text-gray-400 hover:text-green-600 rounded-lg hover:bg-gray-100" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Hapus pengumuman ini?')">
                                            <?= csrfField() ?>
                                            <button type="submit" name="delete" value="<?= $a['id'] ?>" class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100" title="Hapus">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="p-12 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                            </svg>
                            <p>Belum ada pengumuman</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
