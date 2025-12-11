<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";

$user_id = intval($_SESSION['user_id']);

// Handle delete
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM absen WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $delete_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: riwayat.php?deleted=1");
    exit;
}

// Handle delete all
if (isset($_POST['delete_all'])) {
    $stmt = mysqli_prepare($conn, "DELETE FROM absen WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: riwayat.php?deleted=all");
    exit;
}

// Get all attendance history
$stmt = mysqli_prepare($conn, "
    SELECT a.id, a.created_at as waktu_absen, e.nama_event, e.is_big_event
    FROM absen a 
    JOIN events e ON a.event_id = e.id 
    WHERE a.user_id = ? 
    ORDER BY a.id DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$riwayat = mysqli_stmt_get_result($stmt);
$total = mysqli_num_rows($riwayat);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kehadiran - <?= htmlspecialchars($s['site_name'] ?? 'AbsenHIMA') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { darkMode: 'class' }
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark');
    }
    </script>
    <style>
        .dark body { background: #0a0a0a !important; }
        .dark .bg-white { background: #1a1a1a !important; }
        .dark .bg-slate-50 { background: #0f0f0f !important; }
        .dark .bg-slate-100 { background: #252525 !important; }
        .dark .border-slate-200 { border-color: #3a3a3a !important; }
        .dark .border-slate-100 { border-color: #2d2d2d !important; }
        .dark .text-slate-900 { color: #f5f5f5 !important; }
        .dark .text-slate-700 { color: #e5e5e5 !important; }
        .dark .text-slate-600 { color: #d0d0d0 !important; }
        .dark .text-slate-500 { color: #a8a8a8 !important; }
        .dark .text-slate-400 { color: #888888 !important; }
        .dark .hover\:bg-slate-50:hover { background: #252525 !important; }
        .dark .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: #2d2d2d !important; }
        .dark .bg-emerald-50 { background: rgba(16, 185, 129, 0.15) !important; }
        .dark .border-emerald-200 { border-color: rgba(16, 185, 129, 0.3) !important; }
        .dark .text-emerald-700 { color: #6ee7b7 !important; }
        .dark .bg-emerald-100 { background: rgba(16, 185, 129, 0.2) !important; }
        .dark .text-emerald-600 { color: #34d399 !important; }
        .dark .bg-orange-100 { background: rgba(251, 146, 60, 0.2) !important; }
        .dark .text-orange-600 { color: #fb923c !important; }
        .dark .hover\:bg-red-50:hover { background: rgba(239, 68, 68, 0.15) !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div class="flex-1">
                    <h1 class="text-xl font-bold">Riwayat Kehadiran</h1>
                    <p class="text-sm text-white/80"><?= $total ?> kehadiran tercatat</p>
                </div>
                <?php if($total > 0): ?>
                <button onclick="confirmDeleteAll()" class="p-2 hover:bg-white/20 rounded-lg transition" title="Hapus Semua">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if(isset($_GET['deleted'])): ?>
    <div class="max-w-4xl mx-auto px-4 mt-4">
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
            <?= $_GET['deleted'] == 'all' ? 'Semua riwayat berhasil dihapus' : 'Riwayat berhasil dihapus' ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="max-w-4xl mx-auto px-4 py-6">
        <?php if($total > 0): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="divide-y divide-slate-100">
                <?php while($r = mysqli_fetch_assoc($riwayat)): ?>
                <div class="p-4 flex items-center gap-4 hover:bg-slate-50 transition group">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <?php if($r['is_big_event']): ?>
                            <span class="text-xl">⭐</span>
                        <?php else: ?>
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900 truncate"><?= htmlspecialchars($r['nama_event']) ?></p>
                        <p class="text-sm text-slate-500"><?= date('l, d F Y', strtotime($r['waktu_absen'])) ?></p>
                        <p class="text-xs text-slate-400"><?= date('H:i', strtotime($r['waktu_absen'])) ?> WITA</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if($r['is_big_event']): ?>
                            <span class="px-2 py-1 bg-orange-100 text-orange-600 rounded-lg text-xs font-medium">Event Besar</span>
                        <?php endif; ?>
                        <span class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold">HADIR</span>
                        <button onclick="confirmDelete(<?= $r['id'] ?>)" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition opacity-0 group-hover:opacity-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">Belum Ada Riwayat</h3>
            <p class="text-slate-500 mb-6">Riwayat kehadiran kamu akan muncul di sini</p>
            <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Kembali ke Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" class="hidden">
        <input type="hidden" name="delete_id" id="deleteId">
    </form>
    <form id="deleteAllForm" method="POST" class="hidden">
        <input type="hidden" name="delete_all" value="1">
    </form>

    <script>
    function confirmDelete(id) {
        if (confirm('Hapus riwayat kehadiran ini?')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
    
    function confirmDeleteAll() {
        if (confirm('Hapus SEMUA riwayat kehadiran? Tindakan ini tidak dapat dibatalkan.')) {
            if (confirm('Yakin ingin menghapus semua data?')) {
                document.getElementById('deleteAllForm').submit();
            }
        }
    }
    </script>
</body>
</html>
