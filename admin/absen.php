<?php
include "auth.php";
include "../config/koneksi.php";

$filter_event = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

$where = "";
if($filter_event > 0){
    $where = "WHERE a.event_id = '$filter_event'";
}

$query = "
    SELECT a.id, COALESCE(a.waktu, a.created_at) as waktu_absen, u.nama, u.nim, u.kelas, u.picture, e.nama_event 
    FROM absen a
    JOIN users u ON a.user_id = u.id
    JOIN events e ON a.event_id = e.id
    $where
    ORDER BY a.id DESC
";

$result = mysqli_query($conn, $query);
$events = mysqli_query($conn, "SELECT * FROM events ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Absensi - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Data Absensi</h1>
                <p class="text-gray-500">Riwayat kehadiran semua event</p>
            </div>

            <!-- Filter -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                <form method="GET" class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-gray-700 font-medium text-sm">Filter:</label>
                        <select name="event_id" onchange="this.form.submit()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                            <option value="0">Semua Event</option>
                            <?php while($ev = mysqli_fetch_assoc($events)): ?>
                            <option value="<?= $ev['id'] ?>" <?= $filter_event == $ev['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['nama_event']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if($filter_event > 0): ?>
                    <a href="export_excel.php?event_id=<?= $filter_event ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export Excel
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">NIM</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Event</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if($result && mysqli_num_rows($result) > 0): ?>
                            <?php $no = 1; while($row = mysqli_fetch_assoc($result)): 
                                $pic_url = $row['picture'];
                                if(!empty($pic_url) && strpos($pic_url, 'http') !== 0){
                                    $pic_url = '../' . $pic_url;
                                }
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-600"><?= $no++ ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <?php if(!empty($row['picture'])): ?>
                                        <img src="<?= htmlspecialchars($pic_url) ?>" class="w-8 h-8 rounded-full object-cover">
                                        <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($row['nama']) ?></p>
                                            <p class="text-gray-500 text-sm"><?= htmlspecialchars($row['kelas']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600"><?= htmlspecialchars($row['nim']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                                        <?= htmlspecialchars($row['nama_event']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500 text-sm"><?= $row['waktu_absen'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    Belum ada data absensi.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
