<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

// Handle delete dengan CSRF protection
if(isset($_POST['delete'])){
    verifyCsrfOrDie();
    $id = intval($_POST['delete']);
    
    // Prepared statement untuk delete absen
    $stmt = mysqli_prepare($conn, "DELETE FROM absen WHERE event_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Prepared statement untuk delete tokens
    $stmt = mysqli_prepare($conn, "DELETE FROM tokens WHERE event_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Prepared statement untuk delete event
    $stmt = mysqli_prepare($conn, "DELETE FROM events WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header("Location: events.php?msg=deleted");
    exit;
}

// Optimized query dengan LEFT JOIN (lebih efisien dari subquery)
$events = mysqli_query($conn, "
    SELECT e.*, COUNT(a.id) as peserta_count 
    FROM events e 
    LEFT JOIN absen a ON a.event_id = e.id 
    GROUP BY e.id 
    ORDER BY e.id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Event - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Kelola Event</h1>
                    <p class="text-gray-500">Daftar semua event</p>
                </div>
                <a href="create_event.php" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Buat Event
                </a>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                Event berhasil dihapus.
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Nama Event</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Peserta</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if(mysqli_num_rows($events) > 0): ?>
                            <?php while($d = mysqli_fetch_assoc($events)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-600"><?= $d['id'] ?></td>
                                <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($d['nama_event']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        <?= $d['peserta_count'] ?> orang
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($d['status'] == 'open'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        Open
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        Closed
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <?php if($d['status'] == 'closed'): ?>
                                        <a href="start_event.php?id=<?= $d['id'] ?>" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition">Mulai</a>
                                        <?php else: ?>
                                        <a href="stop_event.php?id=<?= $d['id'] ?>" class="px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">Tutup</a>
                                        <a href="generate_qr.php?id=<?= $d['id'] ?>" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">QR</a>
                                        <?php endif; ?>
                                        <a href="participants.php?event_id=<?= $d['id'] ?>" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 transition">Peserta</a>
                                        <button onclick="showDeleteModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['nama_event'])) ?>', <?= $d['peserta_count'] ?>)" 
                                            class="px-3 py-1.5 bg-red-100 text-red-600 text-sm rounded-lg hover:bg-red-200 transition">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    Belum ada event. <a href="create_event.php" class="text-blue-600 hover:underline">Buat event baru</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md transform transition-all scale-95 opacity-0" id="deleteModalContent">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Hapus Event</h3>
                <p class="text-gray-500 mb-2">Apakah Anda yakin ingin menghapus event:</p>
                <p class="font-semibold text-gray-900 mb-2" id="deleteEventName"></p>
                <p class="text-red-600 text-sm mb-6" id="deleteWarning"></p>
            </div>
            <form method="POST" class="flex gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="delete" id="deleteId" value="">
                <button type="button" onclick="hideDeleteModal()" class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition text-center">
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>

    <script>
    function showDeleteModal(id, name, pesertaCount) {
        document.getElementById('deleteEventName').textContent = name;
        document.getElementById('deleteId').value = id;
        
        if(pesertaCount > 0) {
            document.getElementById('deleteWarning').textContent = '⚠️ Event ini memiliki ' + pesertaCount + ' data peserta yang juga akan dihapus!';
        } else {
            document.getElementById('deleteWarning').textContent = '';
        }
        
        const modal = document.getElementById('deleteModal');
        const content = document.getElementById('deleteModalContent');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function hideDeleteModal() {
        const modal = document.getElementById('deleteModal');
        const content = document.getElementById('deleteModalContent');
        
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 200);
    }

    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideDeleteModal();
        }
    });
    </script>

</body>
</html>
