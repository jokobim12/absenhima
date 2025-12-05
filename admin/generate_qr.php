<?php
include "auth.php";
include "../config/koneksi.php";

if(!isset($_GET['id'])){
    header("Location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

// Prepared statement untuk get event
$stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$event){
    die("Event tidak ditemukan.");
}

// Prepared statement untuk cek token yang masih valid
$stmt = mysqli_prepare($conn, "SELECT * FROM tokens WHERE event_id = ? AND expired_at > NOW() ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$token_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$token_row){
    $token = bin2hex(random_bytes(16));
    $expired_at = date('Y-m-d H:i:s', strtotime('+5 seconds'));
    
    $stmt = mysqli_prepare($conn, "INSERT INTO tokens(event_id, token, expired_at) VALUES(?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iss", $event_id, $token, $expired_at);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    $token_row = ['token' => $token, 'expired_at' => $expired_at];
}

$current_token = $token_row['token'];

// Prepared statement untuk count peserta
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM absen WHERE event_id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$peserta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR - <?= htmlspecialchars($event['nama_event']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); }
        .qr-container { 
            background: white;
            border-radius: 10px;
            padding: 20px;
        }
        #qrcode img { border-radius: 12px; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <!-- Back Button - Fixed Top Left -->
    <a href="events.php" class="fixed top-6 left-6 w-12 h-12 bg-white/10 hover:bg-white/20 rounded-xl flex items-center justify-center transition">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
    </a>

    <?php if($event['status'] == 'open'): ?>
    
    <div class="text-center">
        <!-- Event Name -->
        <div class="mb-6">
            <h1 class="text-white text-3xl md:text-4xl font-bold mb-2"><?= htmlspecialchars($event['nama_event']) ?></h1>
            <p class="text-white/50">Scan QR untuk absen</p>
        </div>

        <!-- QR Code -->
        <div class="qr-container inline-block mb-6">
            <div id="qrcode" class="flex justify-center"></div>
        </div>

        <!-- Stats & Controls -->
        <div class="flex items-center justify-center gap-6">
            <div class="text-center">
                <p class="text-5xl font-bold text-white" id="pesertaCount"><?= $peserta ?></p>
                <p class="text-white/50 text-sm">Hadir</p>
            </div>
            <div class="w-px h-12 bg-white/20"></div>
            <button onclick="showCloseModal()" class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl transition">
                Tutup Event
            </button>
        </div>
    </div>

    <!-- Close Event Confirmation Modal -->
    <div id="closeModal" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center" id="closeModalContent">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-2">Tutup Sesi Absensi?</h3>
            <p class="text-slate-500 mb-6">Setelah ditutup, peserta tidak bisa lagi melakukan absensi untuk event ini.</p>
            <div class="flex gap-3">
                <button onclick="hideCloseModal()" class="flex-1 py-3 bg-slate-100 text-slate-700 rounded-xl font-medium hover:bg-slate-200 transition">
                    Batal
                </button>
                <a href="stop_event.php?id=<?= $event_id ?>" class="flex-1 py-3 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition">
                    Ya, Tutup
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    
    <div class="text-center">
        <div class="w-24 h-24 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h2 class="text-white text-2xl font-bold mb-2"><?= htmlspecialchars($event['nama_event']) ?></h2>
        <p class="text-white/50 mb-8">Event belum dibuka</p>
        <a href="start_event.php?id=<?= $event_id ?>" class="inline-block px-8 py-4 bg-green-500 hover:bg-green-600 text-white font-bold rounded-xl transition">
            Mulai Event
        </a>
    </div>

    <?php endif; ?>

<?php if($event['status'] == 'open'): ?>
<script>
var eventId = <?= $event_id ?>;

function generateQR(text) {
    var qr = qrcode(0, 'H');
    qr.addData(text);
    qr.make();
    document.getElementById('qrcode').innerHTML = qr.createImgTag(10, 8);
}

generateQR("<?= $current_token ?>");

// QR berubah setiap 10 detik untuk mencegah share QR (1 token = 1 orang)
setInterval(function() {
    fetch('get_token.php?id=' + eventId)
        .then(response => response.json())
        .then(data => {
            if(data.token) {
                generateQR(data.token);
            }
            if(data.peserta !== undefined) {
                document.getElementById('pesertaCount').textContent = data.peserta;
            }
            if(data.status === 'closed') {
                location.reload();
            }
        })
        .catch(err => console.log(err));
}, 10000);

// Modal functions
function showCloseModal() {
    document.getElementById('closeModal').classList.remove('hidden');
    document.getElementById('closeModal').classList.add('flex');
}

function hideCloseModal() {
    document.getElementById('closeModal').classList.add('hidden');
    document.getElementById('closeModal').classList.remove('flex');
}

document.getElementById('closeModal').addEventListener('click', function(e) {
    if (e.target === this) hideCloseModal();
});
</script>
<?php endif; ?>

</body>
</html>
