<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

// Handle tambah poin
if (isset($_POST['add_points'])) {
    verifyCsrfOrDie();
    $user_id = intval($_POST['user_id']);
    $points = intval($_POST['points']);
    $description = trim($_POST['description']);
    
    if ($points != 0 && !empty($description)) {
        // Insert ke point_history
        $stmt = mysqli_prepare($conn, "INSERT INTO point_history (user_id, points, activity_type, description) VALUES (?, ?, 'admin_adjust', ?)");
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $points, $description);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Update total_points di users
        $stmt = mysqli_prepare($conn, "UPDATE users SET total_points = total_points + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $points, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("Location: manage_points.php?msg=success&action=" . ($points > 0 ? 'add' : 'reduce'));
        exit;
    } else {
        header("Location: manage_points.php?msg=error");
        exit;
    }
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query users dengan poin
if (!empty($search)) {
    $stmt = mysqli_prepare($conn, "SELECT id, nama, nim, kelas, total_points FROM users WHERE nama LIKE ? OR nim LIKE ? ORDER BY total_points DESC, nama ASC");
    $search_param = "%{$search}%";
    mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    $users = mysqli_stmt_get_result($stmt);
} else {
    $users = mysqli_query($conn, "SELECT id, nama, nim, kelas, total_points FROM users ORDER BY total_points DESC, nama ASC");
}
$total = mysqli_num_rows($users);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Poin - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Kelola Poin User</h1>
                <p class="text-gray-500">Tambah atau kurangi poin peserta</p>
            </div>

            <?php if(isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'success'): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                Poin berhasil <?= $_GET['action'] == 'add' ? 'ditambahkan' : 'dikurangi' ?>!
            </div>
            <?php elseif($_GET['msg'] == 'error'): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                Gagal mengubah poin. Pastikan semua field terisi dengan benar.
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Search -->
            <div class="mb-6">
                <form method="GET" class="flex gap-2">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                        placeholder="Cari nama atau NIM..." 
                        class="flex-1 max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition">Cari</button>
                    <?php if(!empty($search)): ?>
                    <a href="manage_points.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">NIM</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Kelas</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase">Total Poin</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if($total > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($users)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($row['nama']) ?></p>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600"><?= htmlspecialchars($row['nim']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= $row['kelas'] == '-' ? '<span class="text-yellow-600">-</span>' : htmlspecialchars($row['kelas']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-full font-bold">
                                        <?= number_format($row['total_points']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="openAddModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>', <?= $row['total_points'] ?>)" 
                                            class="px-3 py-1.5 bg-green-100 text-green-700 text-sm rounded-lg hover:bg-green-200 transition">
                                            + Tambah
                                        </button>
                                        <button onclick="openReduceModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>', <?= $row['total_points'] ?>)" 
                                            class="px-3 py-1.5 bg-red-100 text-red-600 text-sm rounded-lg hover:bg-red-200 transition">
                                            - Kurangi
                                        </button>
                                        <button onclick="openHistoryModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama'])) ?>')" 
                                            class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 transition">
                                            Riwayat
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <?= !empty($search) ? 'User tidak ditemukan.' : 'Belum ada user terdaftar.' ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Tambah Poin -->
    <div id="addModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Tambah Poin</h3>
                    <p id="addUserName" class="text-gray-500 text-sm"></p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Poin saat ini: <span id="addCurrentPoints" class="font-bold text-purple-600"></span></p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" id="addUserId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Poin</label>
                    <input type="number" name="points" min="1" required placeholder="Contoh: 10"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <input type="text" name="description" required placeholder="Contoh: Bonus aktif forum"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('addModal')" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Batal</button>
                    <button type="submit" name="add_points" class="flex-1 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">Tambah Poin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kurangi Poin -->
    <div id="reduceModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Kurangi Poin</h3>
                    <p id="reduceUserName" class="text-gray-500 text-sm"></p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Poin saat ini: <span id="reduceCurrentPoints" class="font-bold text-purple-600"></span></p>
            <form method="POST" id="reduceForm">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" id="reduceUserId">
                <input type="hidden" name="points" id="reducePointsHidden">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Poin yang Dikurangi</label>
                    <input type="number" id="reducePointsInput" min="1" required placeholder="Contoh: 5"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <input type="text" name="description" required placeholder="Contoh: Penalti tidak hadir"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('reduceModal')" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Batal</button>
                    <button type="submit" name="add_points" class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Kurangi Poin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Riwayat Poin -->
    <div id="historyModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[80vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Riwayat Poin</h3>
                    <p id="historyUserName" class="text-gray-500 text-sm"></p>
                </div>
                <button onclick="closeModal('historyModal')" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="historyContent" class="flex-1 overflow-y-auto">
                <div class="text-center py-8 text-gray-500">Memuat...</div>
            </div>
        </div>
    </div>

    <script>
    function openAddModal(id, nama, currentPoints) {
        document.getElementById('addUserId').value = id;
        document.getElementById('addUserName').textContent = nama;
        document.getElementById('addCurrentPoints').textContent = currentPoints.toLocaleString();
        document.getElementById('addModal').classList.remove('hidden');
        document.getElementById('addModal').classList.add('flex');
    }

    function openReduceModal(id, nama, currentPoints) {
        document.getElementById('reduceUserId').value = id;
        document.getElementById('reduceUserName').textContent = nama;
        document.getElementById('reduceCurrentPoints').textContent = currentPoints.toLocaleString();
        document.getElementById('reduceModal').classList.remove('hidden');
        document.getElementById('reduceModal').classList.add('flex');
    }

    // Convert to negative before submit
    document.getElementById('reduceForm').addEventListener('submit', function(e) {
        const input = document.getElementById('reducePointsInput');
        const hidden = document.getElementById('reducePointsHidden');
        hidden.value = -Math.abs(parseInt(input.value));
    });

    async function openHistoryModal(id, nama) {
        document.getElementById('historyUserName').textContent = nama;
        document.getElementById('historyContent').innerHTML = '<div class="text-center py-8 text-gray-500">Memuat...</div>';
        document.getElementById('historyModal').classList.remove('hidden');
        document.getElementById('historyModal').classList.add('flex');

        try {
            const res = await fetch('../api/get_point_history.php?user_id=' + id);
            const data = await res.json();
            
            if (data.success && data.history.length > 0) {
                let html = '<div class="space-y-3">';
                data.history.forEach(item => {
                    const isPositive = item.points > 0;
                    html += `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">${item.description}</p>
                                <p class="text-sm text-gray-500">${item.created_at}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full font-bold ${isPositive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                                ${isPositive ? '+' : ''}${item.points}
                            </span>
                        </div>
                    `;
                });
                html += '</div>';
                document.getElementById('historyContent').innerHTML = html;
            } else {
                document.getElementById('historyContent').innerHTML = '<div class="text-center py-8 text-gray-500">Belum ada riwayat poin.</div>';
            }
        } catch (err) {
            document.getElementById('historyContent').innerHTML = '<div class="text-center py-8 text-red-500">Gagal memuat data.</div>';
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
        document.getElementById(modalId).classList.remove('flex');
    }
    </script>

</body>
</html>
