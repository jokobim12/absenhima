<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";

if(isset($_POST['buat'])){
    verifyCsrfOrDie();
    $nama = trim($_POST['nama_event']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $waktu_mulai = !empty($_POST['waktu_mulai']) ? $_POST['waktu_mulai'] : null;
    
    // GPS fields
    $require_location = isset($_POST['require_location']) ? 1 : 0;
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $radius = !empty($_POST['radius']) ? intval($_POST['radius']) : 100;
    
    $stmt = mysqli_prepare($conn, "INSERT INTO events(nama_event, deskripsi, lokasi, waktu_mulai, require_location, latitude, longitude, radius, status) VALUES(?, ?, ?, ?, ?, ?, ?, ?, 'closed')");
    mysqli_stmt_bind_param($stmt, "ssssiidi", $nama, $deskripsi, $lokasi, $waktu_mulai, $require_location, $latitude, $longitude, $radius);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header("Location: events.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Event - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Buat Event Baru</h1>
                <p class="text-gray-500">Tambahkan event untuk absensi</p>
            </div>

            <div class="max-w-2xl">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <form method="POST" class="space-y-4">
                        <?= csrfField() ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Event <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_event" required placeholder="Contoh: Rapat Anggota 2025"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                            <textarea name="deskripsi" rows="3" placeholder="Deskripsi singkat tentang event ini..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                                <input type="text" name="lokasi" placeholder="Contoh: Aula Kampus Lt. 2"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Waktu Mulai</label>
                                <input type="datetime-local" name="waktu_mulai"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                            </div>
                        </div>

                        <!-- GPS Section -->
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="font-medium text-gray-900">Verifikasi Lokasi GPS</h3>
                                    <p class="text-sm text-gray-500">Wajibkan peserta berada di lokasi saat absen</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="require_location" id="requireLocation" class="sr-only peer" onchange="toggleGpsFields()">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            
                            <div id="gpsFields" class="hidden space-y-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-sm text-blue-700 mb-3">Klik tombol di bawah untuk mengambil koordinat lokasi Anda saat ini, atau masukkan manual.</p>
                                    <button type="button" onclick="getCurrentLocation()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                                        📍 Ambil Lokasi Saat Ini
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                        <input type="text" name="latitude" id="latitude" placeholder="-6.200000"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                        <input type="text" name="longitude" id="longitude" placeholder="106.816666"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Radius (meter)</label>
                                        <input type="number" name="radius" value="100" min="10" max="1000"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                                    </div>
                                </div>
                                
                                <div id="locationPreview" class="hidden bg-green-50 border border-green-200 rounded-lg p-4">
                                    <p class="text-sm text-green-700">✓ Lokasi berhasil diambil</p>
                                    <p class="text-xs text-green-600 mt-1" id="locationText"></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <a href="events.php" class="flex-1 py-3 bg-gray-100 text-gray-700 text-center rounded-lg font-medium hover:bg-gray-200 transition">Batal</a>
                            <button type="submit" name="buat" class="flex-1 py-3 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 transition">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
    function toggleGpsFields() {
        const gpsFields = document.getElementById('gpsFields');
        const checkbox = document.getElementById('requireLocation');
        gpsFields.classList.toggle('hidden', !checkbox.checked);
    }

    function getCurrentLocation() {
        if (!navigator.geolocation) {
            alert('Browser tidak mendukung geolocation');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(8);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
                
                document.getElementById('locationPreview').classList.remove('hidden');
                document.getElementById('locationText').textContent = 
                    `Lat: ${position.coords.latitude.toFixed(6)}, Lng: ${position.coords.longitude.toFixed(6)} (Akurasi: ${Math.round(position.coords.accuracy)}m)`;
            },
            function(error) {
                let msg = 'Gagal mengambil lokasi: ';
                switch(error.code) {
                    case error.PERMISSION_DENIED: msg += 'Izin lokasi ditolak'; break;
                    case error.POSITION_UNAVAILABLE: msg += 'Lokasi tidak tersedia'; break;
                    case error.TIMEOUT: msg += 'Timeout'; break;
                    default: msg += 'Error tidak diketahui';
                }
                alert(msg);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
    </script>

</body>
</html>
