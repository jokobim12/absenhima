<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: iuran.php");
    exit;
}

// Get iuran data
$iuran = $conn->query("SELECT i.*, e.nama_event FROM iuran i LEFT JOIN events e ON i.event_id = e.id WHERE i.id = $id")->fetch_assoc();
if (!$iuran) {
    header("Location: iuran.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

// Handle toggle payment
if (isset($_POST['toggle_payment'])) {
    verifyCsrfOrDie();
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    if ($action == 'pay') {
        // Mark as paid
        $stmt = $conn->prepare("INSERT IGNORE INTO iuran_payments (iuran_id, user_id, verified_by) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $id, $user_id, $admin_id);
        $stmt->execute();
        
        // Add to kas
        $desc = "Iuran: " . $iuran['nama'] . " - User ID: " . $user_id;
        $stmt2 = $conn->prepare("INSERT INTO kas_transactions (type, amount, description, reference_type, reference_id, created_by) VALUES ('income', ?, ?, 'iuran_payment', ?, ?)");
        $stmt2->bind_param("isii", $iuran['nominal'], $desc, $id, $admin_id);
        $stmt2->execute();
    } else {
        // Get payment id first for kas reversal
        $payment = $conn->query("SELECT id FROM iuran_payments WHERE iuran_id = $id AND user_id = $user_id")->fetch_assoc();
        if ($payment) {
            // Remove payment
            $conn->query("DELETE FROM iuran_payments WHERE iuran_id = $id AND user_id = $user_id");
            // Remove from kas (or add negative)
            $desc = "Batal: Iuran " . $iuran['nama'] . " - User ID: " . $user_id;
            $stmt2 = $conn->prepare("INSERT INTO kas_transactions (type, amount, description, reference_type, reference_id, created_by) VALUES ('expense', ?, ?, 'iuran_cancel', ?, ?)");
            $stmt2->bind_param("isii", $iuran['nominal'], $desc, $id, $admin_id);
            $stmt2->execute();
        }
    }
    
    header("Location: iuran_detail.php?id=$id");
    exit;
}

// Handle bulk action
if (isset($_POST['bulk_action'])) {
    verifyCsrfOrDie();
    $user_ids = $_POST['user_ids'] ?? [];
    $action = $_POST['bulk_action'];
    
    foreach ($user_ids as $user_id) {
        $user_id = intval($user_id);
        if ($action == 'mark_paid') {
            $stmt = $conn->prepare("INSERT IGNORE INTO iuran_payments (iuran_id, user_id, verified_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $id, $user_id, $admin_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $desc = "Iuran: " . $iuran['nama'] . " - User ID: " . $user_id;
                $stmt2 = $conn->prepare("INSERT INTO kas_transactions (type, amount, description, reference_type, reference_id, created_by) VALUES ('income', ?, ?, 'iuran_payment', ?, ?)");
                $stmt2->bind_param("isii", $iuran['nominal'], $desc, $id, $admin_id);
                $stmt2->execute();
            }
        }
    }
    
    header("Location: iuran_detail.php?id=$id&msg=bulk");
    exit;
}

// Get all users with payment status
$users = $conn->query("
    SELECT u.id, u.nama, u.nim, u.picture,
           ip.id as payment_id, ip.paid_at, ip.verified_by,
           v.nama as verifier_name
    FROM users u
    LEFT JOIN iuran_payments ip ON ip.user_id = u.id AND ip.iuran_id = $id
    LEFT JOIN users v ON v.id = ip.verified_by
    ORDER BY ip.paid_at IS NULL DESC, u.nama ASC
")->fetch_all(MYSQLI_ASSOC);

$paid_count = 0;
$unpaid_count = 0;
foreach ($users as $u) {
    if ($u['payment_id']) $paid_count++;
    else $unpaid_count++;
}

$total_collected = $paid_count * $iuran['nominal'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Iuran - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto">
            
            <!-- Header -->
            <div class="flex items-center gap-4 mb-6">
                <a href="iuran.php" class="p-2 hover:bg-slate-200 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($iuran['nama']) ?></h1>
                    <p class="text-slate-500">Rp <?= number_format($iuran['nominal'], 0, ',', '.') ?> per orang</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Sudah Bayar</p>
                    <p class="text-2xl font-bold text-emerald-600"><?= $paid_count ?></p>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Belum Bayar</p>
                    <p class="text-2xl font-bold text-rose-600"><?= $unpaid_count ?></p>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Total Terkumpul</p>
                    <p class="text-2xl font-bold text-slate-800">Rp <?= number_format($total_collected, 0, ',', '.') ?></p>
                </div>
                <div class="bg-white rounded-xl border p-4">
                    <p class="text-sm text-slate-500">Target</p>
                    <p class="text-2xl font-bold text-slate-500">Rp <?= number_format(count($users) * $iuran['nominal'], 0, ',', '.') ?></p>
                </div>
            </div>

            <?php if(isset($_GET['msg'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4">
                Pembayaran berhasil diupdate!
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="flex gap-2 mb-4">
                <button onclick="filterUsers('all')" id="btn-all" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium">
                    Semua (<?= count($users) ?>)
                </button>
                <button onclick="filterUsers('unpaid')" id="btn-unpaid" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">
                    Belum Bayar (<?= $unpaid_count ?>)
                </button>
                <button onclick="filterUsers('paid')" id="btn-paid" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">
                    Sudah Bayar (<?= $paid_count ?>)
                </button>
            </div>

            <!-- User List -->
            <form method="POST" id="bulkForm">
                <?= csrfField() ?>
                
                <!-- Bulk Actions -->
                <div class="bg-white rounded-t-xl border border-b-0 p-3 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 rounded">
                        <span class="text-slate-600">Pilih Semua Belum Bayar</span>
                    </label>
                    <button type="submit" name="bulk_action" value="mark_paid" 
                        class="px-4 py-1.5 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700 disabled:opacity-50"
                        id="bulkPayBtn" disabled>
                        ✓ Tandai Sudah Bayar
                    </button>
                </div>
                
                <div class="bg-white rounded-b-xl border overflow-hidden">
                    <div class="divide-y max-h-[60vh] overflow-y-auto">
                        <?php foreach ($users as $u): ?>
                        <div class="flex items-center gap-3 p-3 hover:bg-slate-50 user-row <?= $u['payment_id'] ? 'paid' : 'unpaid' ?>">
                            <!-- Checkbox (only for unpaid) -->
                            <div class="w-6">
                                <?php if (!$u['payment_id']): ?>
                                <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" 
                                    class="w-4 h-4 rounded user-checkbox" onchange="updateBulkBtn()">
                                <?php endif; ?>
                            </div>
                            
                            <!-- Avatar -->
                            <?php if (!empty($u['picture'])): ?>
                            <img src="../<?= htmlspecialchars($u['picture']) ?>" class="w-10 h-10 rounded-full object-cover">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Name -->
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-slate-800 truncate"><?= htmlspecialchars($u['nama']) ?></p>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars($u['nim']) ?></p>
                            </div>
                            
                            <!-- Status & Action -->
                            <div class="flex items-center gap-2">
                                <?php if ($u['payment_id']): ?>
                                <div class="text-right mr-2">
                                    <span class="text-emerald-600 text-sm font-medium">✓ Lunas</span>
                                    <p class="text-xs text-slate-400"><?= date('d/m H:i', strtotime($u['paid_at'])) ?></p>
                                </div>
                                <form method="POST" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" name="toggle_payment" 
                                        class="px-3 py-1.5 bg-rose-100 text-rose-600 text-sm rounded-lg hover:bg-rose-200"
                                        onclick="return confirm('Batalkan pembayaran?')">
                                        Batal
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-rose-500 text-sm mr-2">Belum bayar</span>
                                <form method="POST" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="pay">
                                    <button type="submit" name="toggle_payment" 
                                        class="px-3 py-1.5 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700">
                                        ✓ Bayar
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

        </div>
    </main>

    <script>
    function filterUsers(filter) {
        const rows = document.querySelectorAll('.user-row');
        rows.forEach(row => {
            if (filter === 'all') {
                row.style.display = '';
            } else if (filter === 'paid') {
                row.style.display = row.classList.contains('paid') ? '' : 'none';
            } else {
                row.style.display = row.classList.contains('unpaid') ? '' : 'none';
            }
        });
        
        // Update button styles
        document.querySelectorAll('[id^="btn-"]').forEach(btn => {
            btn.className = 'px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200';
        });
        document.getElementById('btn-' + filter).className = 'px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium';
    }
    
    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            if (cb.closest('.user-row').style.display !== 'none') {
                cb.checked = checked;
            }
        });
        updateBulkBtn();
    }
    
    function updateBulkBtn() {
        const checked = document.querySelectorAll('.user-checkbox:checked').length;
        document.getElementById('bulkPayBtn').disabled = checked === 0;
        document.getElementById('bulkPayBtn').textContent = checked > 0 
            ? `✓ Tandai ${checked} Orang Sudah Bayar` 
            : '✓ Tandai Sudah Bayar';
    }
    </script>
</body>
</html>
