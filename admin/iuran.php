<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

// Handle create iuran
if (isset($_POST['create'])) {
    verifyCsrfOrDie();
    $nama = trim($_POST['nama']);
    $nominal = intval($_POST['nominal']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : null;
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    
    $stmt = $conn->prepare("INSERT INTO iuran (nama, nominal, deskripsi, event_id, deadline) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisis", $nama, $nominal, $deskripsi, $event_id, $deadline);
    $stmt->execute();
    
    header("Location: iuran.php?msg=created");
    exit;
}

// Handle delete
if (isset($_POST['delete'])) {
    verifyCsrfOrDie();
    $id = intval($_POST['delete']);
    $conn->query("DELETE FROM iuran WHERE id = $id");
    header("Location: iuran.php?msg=deleted");
    exit;
}

// Handle close/reopen
if (isset($_GET['close'])) {
    $id = intval($_GET['close']);
    $conn->query("UPDATE iuran SET status = 'closed' WHERE id = $id");
    header("Location: iuran.php");
    exit;
}
if (isset($_GET['reopen'])) {
    $id = intval($_GET['reopen']);
    $conn->query("UPDATE iuran SET status = 'active' WHERE id = $id");
    header("Location: iuran.php");
    exit;
}

// Get iuran list
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$iuran_list = $conn->query("
    SELECT i.*, e.nama_event,
           (SELECT COUNT(*) FROM iuran_payments WHERE iuran_id = i.id) as paid_count
    FROM iuran i
    LEFT JOIN events e ON i.event_id = e.id
    ORDER BY i.status = 'active' DESC, i.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
foreach ($iuran_list as &$item) {
    $item['total_users'] = $total_users;
}

// Get events for dropdown
$events = $conn->query("SELECT id, nama_event FROM events ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Get kas summary
$kas_income = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM kas_transactions WHERE type = 'income'")->fetch_assoc()['total'];
$kas_expense = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM kas_transactions WHERE type = 'expense'")->fetch_assoc()['total'];
$kas_balance = $kas_income - $kas_expense;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Iuran - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
            
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">💰 Iuran & Kas</h1>
                    <p class="text-slate-500">Kelola iuran anggota</p>
                </div>
                <button onclick="document.getElementById('createModal').classList.remove('hidden')" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 text-white rounded-xl font-medium hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Buat Iuran
                </button>
            </div>

            <!-- Kas Summary -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Total Pemasukan</p>
                    <p class="text-xl font-bold text-emerald-600">Rp <?= number_format($kas_income, 0, ',', '.') ?></p>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Total Pengeluaran</p>
                    <p class="text-xl font-bold text-rose-600">Rp <?= number_format($kas_expense, 0, ',', '.') ?></p>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Saldo Kas</p>
                    <p class="text-xl font-bold text-slate-800">Rp <?= number_format($kas_balance, 0, ',', '.') ?></p>
                </div>
            </div>

            <?php if(isset($_GET['msg'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4">
                <?= $_GET['msg'] == 'created' ? 'Iuran berhasil dibuat!' : 'Iuran berhasil dihapus!' ?>
            </div>
            <?php endif; ?>

            <!-- Iuran List -->
            <div class="bg-white rounded-xl border overflow-hidden">
                <div class="p-4 border-b">
                    <h3 class="font-bold text-slate-800">Daftar Iuran</h3>
                </div>
                
                <?php if (empty($iuran_list)): ?>
                <div class="p-8 text-center text-slate-500">
                    Belum ada iuran. Klik "Buat Iuran" untuk membuat baru.
                </div>
                <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($iuran_list as $i): ?>
                    <div class="p-4 hover:bg-slate-50">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-semibold text-slate-800"><?= htmlspecialchars($i['nama']) ?></h4>
                                    <?php if ($i['status'] == 'active'): ?>
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded-full">Aktif</span>
                                    <?php else: ?>
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-xs rounded-full">Ditutup</span>
                                    <?php endif; ?>
                                    <?php if ($i['event_id']): ?>
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full"><?= htmlspecialchars($i['nama_event']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-lg font-bold text-emerald-600 mt-1">Rp <?= number_format($i['nominal'], 0, ',', '.') ?></p>
                                <div class="flex items-center gap-4 mt-2 text-sm text-slate-500">
                                    <span>✅ <?= $i['paid_count'] ?>/<?= $i['total_users'] ?> sudah bayar</span>
                                    <?php if ($i['deadline']): ?>
                                    <span>📅 Deadline: <?= date('d M Y', strtotime($i['deadline'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="iuran_detail.php?id=<?= $i['id'] ?>" 
                                    class="px-4 py-2 bg-slate-900 text-white text-sm rounded-lg hover:bg-slate-800 transition">
                                    Kelola
                                </a>
                                <?php if ($i['status'] == 'active'): ?>
                                <a href="?close=<?= $i['id'] ?>" class="px-3 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg hover:bg-slate-200">
                                    Tutup
                                </a>
                                <?php else: ?>
                                <a href="?reopen=<?= $i['id'] ?>" class="px-3 py-2 bg-emerald-100 text-emerald-600 text-sm rounded-lg hover:bg-emerald-200">
                                    Buka
                                </a>
                                <?php endif; ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Hapus iuran ini?')">
                                    <?= csrfField() ?>
                                    <button name="delete" value="<?= $i['id'] ?>" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Create Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-2xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Buat Iuran Baru</h3>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Iuran *</label>
                    <input type="text" name="nama" required placeholder="Contoh: Iuran Rapat Bulanan"
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-slate-900 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nominal (Rp) *</label>
                    <input type="number" name="nominal" required placeholder="10000" min="0"
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-slate-900 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                    <textarea name="deskripsi" rows="2" placeholder="Keterangan tambahan..."
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-slate-900 outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Terkait Event (Opsional)</label>
                    <select name="event_id" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-slate-900 outline-none">
                        <option value="">-- Tidak terkait event --</option>
                        <?php foreach ($events as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nama_event']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Deadline (Opsional)</label>
                    <input type="date" name="deadline"
                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-slate-900 outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit" name="create" class="flex-1 py-2.5 bg-slate-900 text-white rounded-lg font-medium hover:bg-slate-800">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('createModal').addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
    </script>
</body>
</html>
