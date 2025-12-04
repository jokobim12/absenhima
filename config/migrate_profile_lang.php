<?php
/**
 * Migration script untuk menambahkan terjemahan halaman profil
 */

require_once "koneksi.php";

echo "=== Migrasi Terjemahan Halaman Profil ===\n\n";

// Default translations for profile page
$translations = [
    'my_profile' => [
        'id' => 'Profil Saya',
        'bjn' => 'Profil Ulun',
        'jv' => 'Profilku'
    ],
    'back' => [
        'id' => 'Kembali',
        'bjn' => 'Bulik',
        'jv' => 'Bali'
    ],
    'delete_photo' => [
        'id' => 'Hapus Foto',
        'bjn' => 'Hapus Gambar',
        'jv' => 'Busak Foto'
    ],
    'delete_photo_confirm' => [
        'id' => 'Hapus foto profil?',
        'bjn' => 'Hapus gambar profil?',
        'jv' => 'Busak foto profil?'
    ],
    'academic_info' => [
        'id' => 'Informasi Akademik',
        'bjn' => 'Informasi Akademik',
        'jv' => 'Informasi Akademik'
    ],
    'auto_calculated' => [
        'id' => 'Data dihitung otomatis berdasarkan NIM',
        'bjn' => 'Data dihitung otomatis badasarkan NIM',
        'jv' => 'Data dietung otomatis adhedhasar NIM'
    ],
    'entry_year' => [
        'id' => 'Tahun Masuk',
        'bjn' => 'Tahun Masuk',
        'jv' => 'Taun Mlebu'
    ],
    'current_semester' => [
        'id' => 'Semester Saat Ini',
        'bjn' => 'Semester Wayahini',
        'jv' => 'Semester Saiki'
    ],
    'class' => [
        'id' => 'Kelas',
        'bjn' => 'Kelas',
        'jv' => 'Kelas'
    ],
    'edit_data' => [
        'id' => 'Edit Data',
        'bjn' => 'Edit Data',
        'jv' => 'Edit Data'
    ],
    'class_example' => [
        'id' => 'Contoh: TI-2A',
        'bjn' => 'Contoh: TI-2A',
        'jv' => 'Tuladha: TI-2A'
    ],
    'class_hint' => [
        'id' => 'Masukkan kelas Anda sesuai dengan data kampus (contoh: TI-2A, SI-1B)',
        'bjn' => 'Masuakan kelas Pian sasui lawan data kampus (contoh: TI-2A, SI-1B)',
        'jv' => 'Lebokna kelas sampeyan cocog karo data kampus (tuladha: TI-2A, SI-1B)'
    ],
    'save_changes' => [
        'id' => 'Simpan Perubahan',
        'bjn' => 'Simpan Parubahan',
        'jv' => 'Simpen Owah-owahan'
    ],
    'adjust_photo' => [
        'id' => 'Atur Posisi Foto',
        'bjn' => 'Atur Posisi Gambar',
        'jv' => 'Atur Posisi Foto'
    ],
    'save' => [
        'id' => 'Simpan',
        'bjn' => 'Simpan',
        'jv' => 'Simpen'
    ],
];

$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO languages (lang_key, lang_code, lang_value) VALUES (?, ?, ?)");

$count = 0;
foreach ($translations as $key => $langs) {
    foreach ($langs as $code => $value) {
        mysqli_stmt_bind_param($stmt, "sss", $key, $code, $value);
        mysqli_stmt_execute($stmt);
        $count++;
    }
}

echo "✓ $count terjemahan profil ditambahkan\n";

echo "\n=== Migrasi Selesai ===\n";
?>
