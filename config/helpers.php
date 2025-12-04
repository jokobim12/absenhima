<?php
/**
 * Helper Functions
 */

// ==================== CSRF Protection ====================

/**
 * Generate CSRF token dan simpan di session
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate hidden input field untuk CSRF
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Verify CSRF atau die
 */
function verifyCsrfOrDie() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        die('CSRF token invalid. <a href="javascript:history.back()">Kembali</a>');
    }
}

// ==================== Session Security ====================

/**
 * Secure session start dengan regenerate ID
 */
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Regenerate session ID (panggil setelah login)
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Hitung semester otomatis berdasarkan NIM
 * 
 * Format NIM: AABBCCDDDD
 * - AA = Tahun masuk (22 = 2022, 23 = 2023, 24 = 2024, 25 = 2025)
 * 
 * Contoh (jika sekarang Desember 2025):
 * - NIM 22xxxxxxxx → masuk 2022 → semester 7
 * - NIM 23xxxxxxxx → masuk 2023 → semester 5
 * - NIM 24xxxxxxxx → masuk 2024 → semester 3
 * - NIM 25xxxxxxxx → masuk 2025 → semester 1
 */
function hitungSemester($nim) {
    // Ambil 2 digit pertama NIM sebagai tahun masuk
    $tahun_masuk_2digit = substr($nim, 0, 2);
    
    // Validasi apakah angka
    if (!is_numeric($tahun_masuk_2digit)) {
        return 1; // Default semester 1 jika tidak valid
    }
    
    $tahun_masuk = 2000 + intval($tahun_masuk_2digit);
    $tahun_sekarang = intval(date('Y'));
    $bulan_sekarang = intval(date('n'));
    
    // Hitung semester
    // Semester ganjil: Agustus - Januari (bulan >= 8)
    // Semester genap: Februari - Juli (bulan < 8)
    
    if ($bulan_sekarang >= 8) {
        // Semester ganjil (1, 3, 5, 7)
        $semester = (($tahun_sekarang - $tahun_masuk) * 2) + 1;
    } else {
        // Semester genap (2, 4, 6, 8)
        $semester = ($tahun_sekarang - $tahun_masuk) * 2;
    }
    
    // Pastikan minimal semester 1 dan maksimal 8
    if ($semester < 1) $semester = 1;
    if ($semester > 8) $semester = 8;
    
    return $semester;
}

/**
 * Format tampilan semester
 */
function formatSemester($semester) {
    $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];
    $idx = intval($semester) - 1;
    if ($idx >= 0 && $idx < count($romawi)) {
        return $romawi[$idx];
    }
    return $semester;
}
