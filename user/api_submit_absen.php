<?php
/**
 * API endpoint untuk submit absensi via AJAX
 * Dengan dukungan verifikasi GPS dan Gamifikasi
 */
include "auth.php";
include "../config/koneksi.php";
include "../config/ratelimit.php";
include "../config/gamification.php";

header('Content-Type: application/json');

// Rate limit: max 10 submit attempts per menit
if (!checkRateLimit('submit_absen', 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Terlalu banyak percobaan. Tunggu sebentar.'
    ]);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');

// GPS data from user
$user_lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$user_lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Token tidak ditemukan.'
    ]);
    exit;
}

// Cek token valid
$stmt = mysqli_prepare($conn, "SELECT * FROM tokens WHERE token = ? AND expired_at > NOW() ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tk = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$tk) {
    echo json_encode([
        'success' => false,
        'message' => 'QR Code sudah tidak valid atau kadaluarsa. Scan ulang QR yang baru.'
    ]);
    exit;
}

$event_id = intval($tk['event_id']);

// Cek event status
$stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ev = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$ev || $ev['status'] != 'open') {
    echo json_encode([
        'success' => false,
        'message' => 'Event sudah ditutup.'
    ]);
    exit;
}

// GPS Verification
$location_verified = null;
if ($ev['require_location'] && $ev['latitude'] && $ev['longitude']) {
    if (!$user_lat || !$user_lng) {
        echo json_encode([
            'success' => false,
            'require_location' => true,
            'message' => 'Event ini memerlukan verifikasi lokasi. Aktifkan GPS dan izinkan akses lokasi.'
        ]);
        exit;
    }
    
    // Calculate distance using Haversine formula
    $distance = calculateDistance($ev['latitude'], $ev['longitude'], $user_lat, $user_lng);
    $radius = intval($ev['radius']) ?: 100;
    
    if ($distance > $radius) {
        echo json_encode([
            'success' => false,
            'location_failed' => true,
            'message' => "Anda berada di luar area absensi. Jarak Anda: " . round($distance) . "m, maksimal: {$radius}m.",
            'distance' => round($distance),
            'max_radius' => $radius
        ]);
        exit;
    }
    
    $location_verified = 1;
}

// Cek sudah absen
$stmt = mysqli_prepare($conn, "SELECT * FROM absen WHERE user_id = ? AND event_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

if (mysqli_num_rows($result) > 0) {
    echo json_encode([
        'success' => false,
        'already_attended' => true,
        'message' => 'Kamu sudah absen untuk event ini.'
    ]);
    exit;
}

// Insert absen dengan data lokasi
$token_id = intval($tk['id']);
$stmt = mysqli_prepare($conn, "INSERT INTO absen(user_id, event_id, token_id, latitude, longitude, location_verified) VALUES(?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iiiddi", $user_id, $event_id, $token_id, $user_lat, $user_lng, $location_verified);
$success = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($success) {
    // Update streak
    $streak = updateStreak($conn, $user_id);
    
    // Check early bird
    checkEarlyBird($conn, $user_id, $event_id);
    
    // Check and award badges
    $new_badges = checkAndAwardBadges($conn, $user_id);
    
    // Add points for attendance
    $is_big = isset($ev['is_big_event']) && $ev['is_big_event'] ? true : false;
    $points = $is_big ? 10 : 5;
    $activity_type = $is_big ? 'attendance_big' : 'attendance';
    $description = 'Hadir di ' . ($is_big ? 'event besar: ' : 'event: ') . $ev['nama_event'];
    
    // Check if points already given for this event
    $check = $conn->query("SELECT id FROM point_history WHERE user_id = $user_id AND activity_type LIKE 'attendance%' AND reference_id = $event_id");
    if ($check && $check->num_rows == 0) {
        $conn->query("INSERT INTO point_history (user_id, points, activity_type, description, reference_id) VALUES ($user_id, $points, '$activity_type', '$description', $event_id)");
        $conn->query("UPDATE users SET total_points = total_points + $points WHERE id = $user_id");
    }
    
    $response = [
        'success' => true,
        'message' => 'Absensi berhasil dicatat!',
        'event_name' => $ev['nama_event'],
        'timestamp' => date('d M Y, H:i:s'),
        'streak' => $streak,
        'points_earned' => $points
    ];
    
    if ($location_verified) {
        $response['location_verified'] = true;
    }
    
    if (!empty($new_badges)) {
        $response['new_badges'] = array_map(function($b) {
            return ['name' => $b['name'], 'icon' => $b['icon'], 'description' => $b['description']];
        }, $new_badges);
    }
    
    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan absensi. Silakan coba lagi.'
    ]);
}

/**
 * Calculate distance between two GPS coordinates using Haversine formula
 * Returns distance in meters
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}
