<?php
/**
 * Handle callback dari Google OAuth - Khusus Politala
 * Logika: Login dengan akun Politala → auto register → langsung masuk dashboard
 */
// Set session cookie parameters for better compatibility
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once "../config/google.php";
require_once "../config/koneksi.php";
require_once "../config/helpers.php";

// Function untuk tampilkan error
function showError($message) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Gagal</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Login Gagal</h2>
            <p class="text-gray-600 mb-6"><?= htmlspecialchars($message) ?></p>
            <a href="../index.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Kembali
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Extract NIM dan Nama dari data Google
 * Format nama Google biasanya: "2401301001 JOKO BIMANTARO"
 * Atau email: "2401301001@politala.ac.id"
 */
function extractNimDanNama($googleName, $email) {
    $nim = '';
    $nama = $googleName;
    
    // Coba extract dari nama Google (format: "NIM NAMA")
    // Contoh: "2401301001 JOKO BIMANTARO"
    if (preg_match('/^(\d{10})\s+(.+)$/i', $googleName, $matches)) {
        $nim = $matches[1];
        $nama = $matches[2];
    }
    // Coba format lain: "2401301001 - JOKO BIMANTARO"
    elseif (preg_match('/^(\d{10})\s*-\s*(.+)$/i', $googleName, $matches)) {
        $nim = $matches[1];
        $nama = $matches[2];
    }
    // Jika nama dimulai dengan angka (minimal 8 digit)
    elseif (preg_match('/^(\d{8,})\s*(.*)$/i', $googleName, $matches)) {
        $nim = $matches[1];
        $nama = !empty($matches[2]) ? $matches[2] : $googleName;
    }
    // Jika tidak ada di nama, coba dari email
    elseif (preg_match('/^(\d+)@/', $email, $matches)) {
        $nim = $matches[1];
    }
    // Fallback: gunakan prefix email
    else {
        $nim = strstr($email, '@', true);
    }
    
    // Bersihkan nama
    $nama = trim($nama);
    if (empty($nama) || $nama == $nim) {
        $nama = $googleName;
    }
    
    return [$nim, $nama];
}

// Validasi state CSRF
// Di localhost kadang session hilang, jadi kita toleransi jika oauth_state tidak ada
if (isset($_GET['state']) && isset($_SESSION['oauth_state'])) {
    if ($_GET['state'] !== $_SESSION['oauth_state']) {
        showError("Sesi tidak valid. Silakan coba lagi.");
    }
}

// Cek error dari Google
if (isset($_GET['error'])) {
    showError("Login dibatalkan.");
}

// Cek authorization code
if (!isset($_GET['code'])) {
    showError("Kode otorisasi tidak ditemukan.");
}

// Buat redirect_uri dinamis (harus sama dengan yang digunakan saat login)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirect_uri = $protocol . '://' . $host . '/absenhima/auth/google_callback.php';

// Exchange code untuk access token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code'
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true
]);
$tokenResult = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($tokenResult['access_token'])) {
    showError("Gagal mendapatkan token dari Google.");
}

// Ambil info user dari Google
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenResult['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true
]);
$userInfo = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($userInfo['email'])) {
    showError("Gagal mendapatkan email dari Google.");
}

// Data dari Google
$email = $userInfo['email'];
$google_name = $userInfo['name'] ?? 'User';
$google_id = $userInfo['id'];
$picture = $userInfo['picture'] ?? '';

// Cek domain email - HANYA Politala yang boleh masuk
$emailDomain = substr(strrchr($email, "@"), 1);
if (!empty(ALLOWED_DOMAINS) && !in_array($emailDomain, ALLOWED_DOMAINS)) {
    header("Location: error_account.php");
    exit;
}

// Extract NIM dan Nama dari data Google
// Format nama Google: "2401301001 JOKO BIMANTARO"
list($nim, $nama) = extractNimDanNama($google_name, $email);

// Hitung semester otomatis berdasarkan NIM
// NIM 24xxxxxx = masuk 2024 = semester 3 (jika sekarang Des 2025)
$semester = hitungSemester($nim);

// Cek apakah user sudah ada di database
$stmt = mysqli_prepare($conn, "SELECT id, picture FROM users WHERE email = ? OR google_id = ?");
mysqli_stmt_bind_param($stmt, "ss", $email, $google_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user) {
    // User sudah ada - update data terbaru TANPA menimpa foto lokal
    // Cek apakah user sudah punya foto yang diupload secara lokal
    $hasLocalPhoto = !empty($user['picture']) && strpos($user['picture'], 'uploads/profiles/') !== false;
    
    if ($hasLocalPhoto) {
        // Jangan update foto, pertahankan foto lokal
        $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, nim = ?, google_id = ?, semester = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssi", $nama, $nim, $google_id, $semester, $user['id']);
    } else {
        // Update dengan foto dari Google
        $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, nim = ?, google_id = ?, picture = ?, semester = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssssi", $nama, $nim, $google_id, $picture, $semester, $user['id']);
    }
    mysqli_stmt_execute($stmt);
    $user_id = $user['id'];
} else {
    // User baru - auto register dengan semester otomatis
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO users (nama, nim, email, kelas, semester, google_id, picture, username, password) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, '')"
    );
    $kelas = DEFAULT_KELAS;
    mysqli_stmt_bind_param($stmt, "ssssssss", $nama, $nim, $email, $kelas, $semester, $google_id, $picture, $nim);
    mysqli_stmt_execute($stmt);
    $user_id = mysqli_insert_id($conn);
}

// Set session & langsung ke dashboard
// Regenerate session ID untuk mencegah session fixation
session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;
$_SESSION['login_method'] = 'google';
unset($_SESSION['oauth_state']);

header("Location: ../user/dashboard.php");
exit;
