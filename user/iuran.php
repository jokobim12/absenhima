<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
include "../config/settings.php";

$user_id = intval($_SESSION['user_id']);

// Get user's iuran status
$iuran_list = $conn->query("
    SELECT i.*, 
           ip.paid_at, ip.id as payment_id,
           e.nama_event
    FROM iuran i
    LEFT JOIN iuran_payments ip ON ip.iuran_id = i.id AND ip.user_id = $user_id
    LEFT JOIN events e ON i.event_id = e.id
    WHERE i.status = 'active'
    ORDER BY ip.paid_at IS NULL DESC, i.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$unpaid_total = 0;
$unpaid_count = 0;
foreach ($iuran_list as $i) {
    if (!$i['payment_id']) {
        $unpaid_total += $i['nominal'];
        $unpaid_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iuran Saya - <?= htmlspecialchars($s['site_name'] ?? 'HIMA') ?></title>
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
        .dark .border-slate-200 { border-color: #333 !important; }
        .dark .text-slate-900 { color: #fff !important; }
        .dark .text-slate-800 { color: #f0f0f0 !important; }
        .dark .text-slate-700 { color: #e0e0e0 !important; }
        .dark .text-slate-600 { color: #b0b0b0 !important; }
        .dark .text-slate-500 { color: #909090 !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white">
        <div class="max-w-2xl mx-auto px-4 py-4">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-xl font-bold">💰 Iuran Saya</h1>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <?php if ($unpaid_count > 0): ?>
    <div class="max-w-2xl mx-auto px-4 -mt-4">
        <div class="bg-rose-500 text-white rounded-2xl p-4 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-rose-100 text-sm">Belum Dibayar</p>
                    <p class="text-2xl font-bold"><?= $unpaid_count ?> iuran</p>
                </div>
                <div class="text-right">
                    <p class="text-rose-100 text-sm">Total</p>
                    <p class="text-2xl font-bold">Rp <?= number_format($unpaid_total, 0, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="max-w-2xl mx-auto px-4 -mt-4">
        <div class="bg-emerald-500 text-white rounded-2xl p-4 shadow-lg text-center">
            <p class="text-lg font-bold">✓ Semua iuran sudah lunas!</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Iuran List -->
    <div class="max-w-2xl mx-auto px-4 py-4">
        <?php if (empty($iuran_list)): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center">
            <p class="text-slate-500">Tidak ada iuran aktif saat ini.</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($iuran_list as $i): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-4 <?= $i['payment_id'] ? 'opacity-75' : '' ?>">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-slate-800"><?= htmlspecialchars($i['nama']) ?></h3>
                            <?php if ($i['event_id']): ?>
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full"><?= htmlspecialchars($i['nama_event']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($i['deskripsi']): ?>
                        <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($i['deskripsi']) ?></p>
                        <?php endif; ?>
                        <p class="text-lg font-bold text-slate-800 mt-2">Rp <?= number_format($i['nominal'], 0, ',', '.') ?></p>
                        <?php if ($i['deadline']): ?>
                        <p class="text-xs text-slate-400 mt-1">Deadline: <?= date('d M Y', strtotime($i['deadline'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <?php if ($i['payment_id']): ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-100 text-emerald-700 text-sm font-medium rounded-full">
                            ✓ Lunas
                        </span>
                        <p class="text-xs text-slate-400 mt-1"><?= date('d/m/Y', strtotime($i['paid_at'])) ?></p>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-rose-100 text-rose-600 text-sm font-medium rounded-full">
                            Belum Bayar
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p class="text-center text-sm text-slate-400 mt-6">
            Bayar langsung ke bendahara saat kegiatan
        </p>
    </div>
</body>
</html>
