<?php
include "auth.php";
include "../config/koneksi.php";

if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM absen WHERE user_id='$id'");
    mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    header("Location: users.php?msg=deleted");
    exit;
}

if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $kelas = mysqli_real_escape_string($conn, $_POST['kelas']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    mysqli_query($conn, "UPDATE users SET kelas='$kelas', semester='$semester' WHERE id='$id'");
    header("Location: users.php?msg=updated");
    exit;
}

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
$total = mysqli_num_rows($users);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Kelola User</h1>
                <p class="text-gray-500"><?= $total ?> user terdaftar</p>
            </div>

            <?php if(isset($_GET['msg'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <?= $_GET['msg'] == 'deleted' ? 'User berhasil dihapus.' : 'Data user berhasil diupdate.' ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">NIM</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Kelas</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Semester</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if($total > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($users)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <?php 
                                        $pic_url = $row['picture'];
                                        if(!empty($pic_url) && strpos($pic_url, 'http') !== 0){
                                            $pic_url = '../' . $pic_url;
                                        }
                                        ?>
                                        <?php if(!empty($row['picture'])): ?>
                                        <img src="<?= htmlspecialchars($pic_url) ?>" class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($row['nama']) ?></p>
                                            <p class="text-gray-500 text-sm"><?= htmlspecialchars($row['email'] ?? '-') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600"><?= htmlspecialchars($row['nim']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= $row['kelas'] == '-' ? '<span class="text-yellow-600">Belum diisi</span>' : htmlspecialchars($row['kelas']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($row['semester']) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="openEdit(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama']) ?>', '<?= htmlspecialchars($row['kelas']) ?>', '<?= htmlspecialchars($row['semester']) ?>')" 
                                            class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 transition">Edit</button>
                                        <a href="users.php?delete=<?= $row['id'] ?>" 
                                           onclick="return confirm('Hapus user ini?')"
                                           class="px-3 py-1.5 bg-red-100 text-red-600 text-sm rounded-lg hover:bg-red-200 transition">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    Belum ada user terdaftar.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Edit -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Edit User</h3>
            <p id="editName" class="text-gray-500 mb-4"></p>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
                    <input type="text" name="kelas" id="editKelas" placeholder="Contoh: TI-2A"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                    <select name="semester" id="editSemester"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                        <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeEdit()" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Batal</button>
                    <button type="submit" name="update" class="flex-1 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEdit(id, nama, kelas, semester) {
        document.getElementById('editId').value = id;
        document.getElementById('editName').textContent = nama;
        document.getElementById('editKelas').value = kelas == '-' ? '' : kelas;
        document.getElementById('editSemester').value = semester;
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
    }
    function closeEdit() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }
    </script>

</body>
</html>
