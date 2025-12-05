<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/ratelimit.php";

// Rate limit: max 10 submit attempts per menit
rateLimitPageOrDie('submit_absen', 10, 60);

$user_id = intval($_SESSION['user_id']);
$token = isset($_GET['token']) ? $_GET['token'] : '';

$success = false;
$message = "";

if(empty($token)){
    $message = "Token tidak ditemukan.";
} else {
    // Prepared statement untuk cek token
    $stmt = mysqli_prepare($conn, "SELECT * FROM tokens WHERE token = ? AND expired_at > NOW() ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tk = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if(!$tk){
        $message = "Token tidak valid atau sudah kadaluarsa.";
    } else {
        $event_id = intval($tk['event_id']);
        
        // Prepared statement untuk cek event
        $stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $ev = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if(!$ev || $ev['status'] != 'open'){
            $message = "Event sudah ditutup.";
        } else {
            // Prepared statement untuk cek absen existing
            $stmt = mysqli_prepare($conn, "SELECT * FROM absen WHERE user_id = ? AND event_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);

            if(mysqli_num_rows($result) > 0){
                $message = "Kamu sudah absen untuk event ini.";
            } else {
                // Prepared statement untuk insert absen
                $token_id = intval($tk['id']);
                $stmt = mysqli_prepare($conn, "INSERT INTO absen(user_id, event_id, token_id) VALUES(?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iii", $user_id, $event_id, $token_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                // Add points for attendance
                $is_big = isset($ev['is_big_event']) && $ev['is_big_event'] ? true : false;
                $points = $is_big ? 10 : 5;
                $activity_type = $is_big ? 'attendance_big' : 'attendance';
                $description = mysqli_real_escape_string($conn, 'Hadir di ' . ($is_big ? 'event besar: ' : 'event: ') . $ev['nama_event']);
                
                $check = $conn->query("SELECT id FROM point_history WHERE user_id = $user_id AND activity_type LIKE 'attendance%' AND reference_id = $event_id");
                if ($check && $check->num_rows == 0) {
                    $conn->query("INSERT INTO point_history (user_id, points, activity_type, description, reference_id) VALUES ($user_id, $points, '$activity_type', '$description', $event_id)");
                    $conn->query("UPDATE users SET total_points = total_points + $points WHERE id = $user_id");
                }
                
                $success = true;
                $message = "Absensi berhasil dicatat! +" . $points . " poin";
                $event_name = $ev['nama_event'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Absen - Absensi HIMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes checkmark {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-check { animation: checkmark 0.5s ease-out forwards; }
        .animate-fadeup { animation: fadeUp 0.5s ease-out forwards; }
        .animate-fadeup-delay { animation: fadeUp 0.5s ease-out 0.2s forwards; opacity: 0; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br <?= $success ? 'from-green-50 to-emerald-100' : 'from-red-50 to-rose-100' ?> flex items-center justify-center p-4">

    <div class="w-full max-w-lg text-center">
        
        <?php if($success): ?>
        <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
            <div class="animate-check">
                <div class="w-28 h-28 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-green-500/30">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>
            
            <div class="animate-fadeup">
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Absensi Berhasil!</h2>
                <p class="text-slate-500 text-lg">Kehadiran Anda telah tercatat</p>
            </div>

            <div class="animate-fadeup-delay mt-8 p-6 bg-gradient-to-br from-slate-50 to-slate-100 rounded-2xl">
                <p class="text-slate-500 text-sm mb-1">Event</p>
                <p class="text-slate-900 font-bold text-xl mb-4"><?= htmlspecialchars($event_name) ?></p>
                <div class="flex items-center justify-center gap-2 text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= date('d M Y, H:i:s') ?></span>
                </div>
            </div>

            <div class="animate-fadeup-delay mt-8">
                <a href="dashboard.php" class="block w-full py-4 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white font-semibold rounded-xl transition shadow-lg shadow-slate-900/20">
                    Kembali ke Dashboard
                </a>
            </div>
        </div>

        <?php else: ?>
        <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
            <div class="animate-check">
                <div class="w-28 h-28 bg-gradient-to-br from-red-400 to-rose-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-red-500/30">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
            </div>
            
            <div class="animate-fadeup">
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Gagal!</h2>
                <p class="text-slate-500 text-lg"><?= $message ?></p>
            </div>

            <div class="animate-fadeup-delay mt-8 p-6 bg-red-50 rounded-2xl">
                <p class="text-red-600">Silakan coba scan ulang atau hubungi admin jika masalah berlanjut.</p>
            </div>

            <div class="animate-fadeup-delay mt-8 space-y-3">
                <a href="scan.php" class="block w-full py-4 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white font-semibold rounded-xl transition shadow-lg shadow-slate-900/20">
                    Coba Scan Lagi
                </a>
                <a href="dashboard.php" class="block w-full py-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition">
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
