<?php
/**
 * Helper Functions
 */

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
