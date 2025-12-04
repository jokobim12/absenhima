<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    
    if ($id > 0 && in_array($action, ['approved', 'rejected'])) {
        $admin_id = $_SESSION['admin_id'];
        $stmt = mysqli_prepare($conn, "UPDATE permissions SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssii", $action, $note, $admin_id, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("Location: permissions.php?msg=" . ($action == 'approved' ? 'approved' : 'rejected'));
        exit;
    }
}

// Filter
$status_filter = $_GET['status'] ?? 'all';
$where = "";
if ($status_filter != 'all') {
    $status_filter = mysqli_real_escape_string($conn, $status_filter);
    $where = "WHERE p.status = '$status_filter'";
}

// Get permissions
$permissions = mysqli_query($conn, "
    SELECT p.*, u.nama, u.nim, u.kelas, e.nama_event 
    FROM permissions p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN events e ON p.event_id = e.id 
    $where
    ORDER BY 
        CASE p.status WHEN 'pending' THEN 0 ELSE 1 END,
        p.created_at DESC
");

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        SUM(status = 'pending') as pending,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected
    FROM permissions
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Izin/Sakit - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Izin/Sakit</h1>
                <p class="text-gray-500 text-sm">Kelola pengajuan izin dan sakit</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
            <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $_GET['msg'] == 'approved' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700' ?>">
                <?= $_GET['msg'] == 'approved' ? 'Pengajuan disetujui' : 'Pengajuan ditolak' ?>
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-lg w-fit">
                <a href="?status=all" class="px-4 py-2 rounded-md text-sm font-medium transition <?= $status_filter == 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' ?>">
                    Semua
                </a>
                <a href="?status=pending" class="px-4 py-2 rounded-md text-sm font-medium transition <?= $status_filter == 'pending' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' ?>">
                    Pending <?php if(($stats['pending'] ?? 0) > 0): ?><span class="ml-1 px-1.5 py-0.5 bg-orange-100 text-orange-600 rounded text-xs"><?= $stats['pending'] ?></span><?php endif; ?>
                </a>
                <a href="?status=approved" class="px-4 py-2 rounded-md text-sm font-medium transition <?= $status_filter == 'approved' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' ?>">
                    Disetujui
                </a>
                <a href="?status=rejected" class="px-4 py-2 rounded-md text-sm font-medium transition <?= $status_filter == 'rejected' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' ?>">
                    Ditolak
                </a>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mahasiswa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alasan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (mysqli_num_rows($permissions) > 0): ?>
                        <?php while ($p = mysqli_fetch_assoc($permissions)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($p['nama']) ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($p['nim']) ?> · <?= htmlspecialchars($p['kelas']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm <?= $p['type'] == 'sakit' ? 'text-purple-600' : 'text-blue-600' ?>">
                                    <?= ucfirst($p['type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-600 max-w-xs truncate"><?= htmlspecialchars($p['reason']) ?></p>
                                <?php if ($p['attachment']): ?>
                                <a href="../<?= htmlspecialchars($p['attachment']) ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Lampiran</a>
                                <?php endif; ?>
                                <?php if ($p['admin_note']): ?>
                                <p class="text-xs text-gray-400 mt-1">Admin: <?= htmlspecialchars($p['admin_note']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?= date('d M Y', strtotime($p['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($p['status'] == 'pending'): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-orange-50 text-orange-600">Pending</span>
                                <?php elseif ($p['status'] == 'approved'): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-50 text-green-600">Disetujui</span>
                                <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($p['status'] == 'pending'): ?>
                                <div class="flex gap-2">
                                    <button onclick="approve(<?= $p['id'] ?>)" class="text-green-600 hover:text-green-700 text-sm font-medium">Setujui</button>
                                    <button onclick="reject(<?= $p['id'] ?>)" class="text-gray-400 hover:text-red-600 text-sm">Tolak</button>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400 text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                Tidak ada data
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <!-- Modal -->
    <div id="modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-sm">
            <h3 class="font-semibold text-gray-900 mb-4" id="modalTitle">Konfirmasi</h3>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="modalId">
                <input type="hidden" name="action" id="modalAction">
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-1">Catatan (opsional)</label>
                    <input type="text" name="note" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-gray-400" placeholder="Tulis catatan...">
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="hideModal()" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Batal</button>
                    <button type="submit" id="modalBtn" class="flex-1 py-2 rounded-lg text-sm font-medium text-white">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function approve(id) {
        document.getElementById('modalId').value = id;
        document.getElementById('modalAction').value = 'approved';
        document.getElementById('modalTitle').textContent = 'Setujui Pengajuan';
        document.getElementById('modalBtn').className = 'flex-1 py-2 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700';
        document.getElementById('modal').classList.remove('hidden');
        document.getElementById('modal').classList.add('flex');
    }
    
    function reject(id) {
        document.getElementById('modalId').value = id;
        document.getElementById('modalAction').value = 'rejected';
        document.getElementById('modalTitle').textContent = 'Tolak Pengajuan';
        document.getElementById('modalBtn').className = 'flex-1 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700';
        document.getElementById('modal').classList.remove('hidden');
        document.getElementById('modal').classList.add('flex');
    }
    
    function hideModal() {
        document.getElementById('modal').classList.add('hidden');
        document.getElementById('modal').classList.remove('flex');
    }
    
    document.getElementById('modal').addEventListener('click', function(e) {
        if (e.target === this) hideModal();
    });
    </script>

</body>
</html>
