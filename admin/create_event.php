<?php
include "auth.php";
include "../config/koneksi.php";

if(isset($_POST['buat'])){
    $nama = mysqli_real_escape_string($conn, $_POST['nama_event']);
    mysqli_query($conn, "INSERT INTO events(nama_event, status) VALUES('$nama','closed')");
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

            <div class="max-w-lg">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Event</label>
                            <input type="text" name="nama_event" required placeholder="Contoh: Rapat Anggota 2025"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                        </div>
                        <div class="flex gap-3 pt-2">
                            <a href="events.php" class="flex-1 py-3 bg-gray-100 text-gray-700 text-center rounded-lg font-medium hover:bg-gray-200 transition">Batal</a>
                            <button type="submit" name="buat" class="flex-1 py-3 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 transition">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
