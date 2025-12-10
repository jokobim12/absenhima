<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

// Cek dan tambah kolom is_deleted jika belum ada
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'is_deleted'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
}

// Handle delete dengan CSRF protection (soft delete)
if(isset($_POST['delete'])){
    verifyCsrfOrDie();
    $id = intval($_POST['delete']);
    
    // Soft delete - tandai sebagai dihapus, data tetap ada untuk history user
    $stmt = mysqli_prepare($conn, "UPDATE events SET is_deleted = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header("Location: events.php?msg=deleted");
    exit;
}

$events = mysqli_query($conn, "
    SELECT e.*, COUNT(a.id) as peserta_count 
    FROM events e 
    LEFT JOIN absen a ON a.event_id = e.id 
    WHERE e.is_deleted = 0
    GROUP BY e.id 
    ORDER BY e.id DESC
");
$total_events = mysqli_num_rows($events);
$open_count = 0;
$closed_count = 0;
$events_data = [];
while($row = mysqli_fetch_assoc($events)) {
    $events_data[] = $row;
    if($row['status'] == 'open') $open_count++;
    else $closed_count++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Event - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgb(0 0 0 / 0.1); }
        .btn-icon { transition: all 0.15s ease; }
        .btn-icon:hover { transform: scale(1.05); }
        .btn-icon:active { transform: scale(0.95); }
    </style>
</head>
<body class="bg-slate-50/50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
            
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Event</h1>
                    <p class="text-slate-500 mt-1">Kelola semua event kegiatan</p>
                </div>
                <a href="create_event.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-slate-900 text-white rounded-xl font-medium hover:bg-slate-800 transition shadow-lg shadow-slate-900/10 btn-icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Buat Event
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-8">
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-slate-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xl sm:text-2xl font-bold text-slate-800"><?= $total_events ?></p>
                            <p class="text-xs sm:text-sm text-slate-500">Total Event</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xl sm:text-2xl font-bold text-emerald-600"><?= $open_count ?></p>
                            <p class="text-xs sm:text-sm text-slate-500">Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-slate-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xl sm:text-2xl font-bold text-slate-400"><?= $closed_count ?></p>
                            <p class="text-xs sm:text-sm text-slate-500">Selesai</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Event berhasil dihapus.
            </div>
            <?php endif; ?>

            <!-- Event Cards -->
            <?php if(count($events_data) > 0): ?>
            <div class="grid gap-4">
                <?php foreach($events_data as $d): ?>
                <div class="bg-white rounded-2xl border border-slate-100 card-hover overflow-hidden">
                    <div class="p-4 sm:p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <!-- Event Info -->
                            <div class="flex items-start gap-4 flex-1 min-w-0">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center flex-shrink-0 <?= $d['status'] == 'open' ? 'bg-emerald-50' : 'bg-slate-100' ?>">
                                    <?php if($d['status'] == 'open'): ?>
                                    <svg class="w-6 h-6 sm:w-7 sm:h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?php else: ?>
                                    <svg class="w-6 h-6 sm:w-7 sm:h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                    </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-slate-800 text-lg truncate"><?= htmlspecialchars($d['nama_event']) ?></h3>
                                        <?php if(!empty($d['is_big_event'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                            ⭐ +10 Poin
                                        </span>
                                        <?php endif; ?>
                                        <?php if($d['status'] == 'open'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                            Live
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                                            Selesai
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-4 mt-2 text-sm text-slate-500">
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            <?= $d['peserta_count'] ?> peserta
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                            </svg>
                                            ID: <?= $d['id'] ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                                <?php if($d['status'] == 'closed'): ?>
                                <a href="start_event.php?id=<?= $d['id'] ?>" class="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition btn-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Mulai
                                </a>
                                <?php else: ?>
                                <a href="stop_event.php?id=<?= $d['id'] ?>" class="flex items-center gap-1.5 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-xl hover:bg-rose-700 transition btn-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"></path>
                                    </svg>
                                    Stop
                                </a>
                                <a href="generate_qr.php?id=<?= $d['id'] ?>" class="flex items-center gap-1.5 px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition btn-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                    </svg>
                                    QR
                                </a>
                                <?php endif; ?>
                                <a href="participants.php?event_id=<?= $d['id'] ?>" class="flex items-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded-xl hover:bg-slate-200 transition btn-icon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                    Peserta
                                </a>
                                <button onclick="showDeleteModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['nama_event'])) ?>', <?= $d['peserta_count'] ?>)" 
                                    class="flex items-center justify-center w-10 h-10 bg-rose-50 text-rose-600 rounded-xl hover:bg-rose-100 transition btn-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center">
                <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-2">Belum ada event</h3>
                <p class="text-slate-500 mb-6">Mulai buat event pertama Anda</p>
                <a href="create_event.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 text-white rounded-xl font-medium hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Buat Event Baru
                </a>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm transform transition-all scale-95 opacity-0" id="deleteModalContent">
            <div class="text-center">
                <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Hapus Event?</h3>
                <p class="text-slate-500 text-sm mb-1">Event yang akan dihapus:</p>
                <p class="font-semibold text-slate-800 mb-2" id="deleteEventName"></p>
                <p class="text-rose-600 text-sm mb-6" id="deleteWarning"></p>
            </div>
            <form method="POST" class="flex gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="delete" id="deleteId" value="">
                <button type="button" onclick="hideDeleteModal()" class="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-medium hover:bg-slate-200 transition">
                    Batal
                </button>
                <button type="submit" class="flex-1 py-2.5 bg-rose-600 text-white rounded-xl font-medium hover:bg-rose-700 transition">
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <script>
    function showDeleteModal(id, name, pesertaCount) {
        document.getElementById('deleteEventName').textContent = name;
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteWarning').textContent = pesertaCount > 0 
            ? '⚠️ ' + pesertaCount + ' data peserta akan ikut terhapus!' 
            : '';
        
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

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) hideDeleteModal();
    });
    </script>

</body>
</html>
