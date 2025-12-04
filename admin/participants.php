<?php
include "auth.php";
include "../config/koneksi.php";

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

$events = mysqli_query($conn, "SELECT * FROM events ORDER BY id DESC");

$participants = null;
$event_info = null;
$total = 0;

if($event_id > 0){
    // Prepared statement untuk get event info
    $stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $event_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    // Prepared statement untuk get participants
    $stmt = mysqli_prepare($conn, "
        SELECT u.nama, u.nim, u.kelas, u.semester, u.picture, COALESCE(a.waktu, a.created_at) as waktu_absen
        FROM absen a
        JOIN users u ON a.user_id = u.id
        WHERE a.event_id = ?
        ORDER BY a.id ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $participants = mysqli_stmt_get_result($stmt);
    $total = mysqli_num_rows($participants);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peserta Event - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Peserta Event</h1>
                <p class="text-gray-500">Daftar hadir per event</p>
            </div>

            <!-- Filter -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                <form method="GET" class="flex items-center gap-2">
                    <label class="text-gray-700 font-medium text-sm">Pilih Event:</label>
                    <select name="event_id" onchange="this.form.submit()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        <option value="0">-- Pilih Event --</option>
                        <?php while($ev = mysqli_fetch_assoc($events)): ?>
                        <option value="<?= $ev['id'] ?>" <?= $event_id == $ev['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['nama_event']) ?> (<?= $ev['status'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>

            <?php if($event_info): ?>
            <!-- Event Info -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($event_info['nama_event']) ?></h2>
                        <p class="text-gray-500">
                            Status: 
                            <?php if($event_info['status'] == 'open'): ?>
                            <span class="text-green-600 font-medium">Open</span>
                            <?php else: ?>
                            <span class="text-gray-600">Closed</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-900"><?= $total ?></p>
                            <p class="text-gray-500 text-sm">Peserta</p>
                        </div>
                        <?php if($total > 0): ?>
                        <a href="export_excel.php?event_id=<?= $event_id ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">NIM</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Kelas</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if($total > 0): ?>
                            <?php $no = 1; while($row = mysqli_fetch_assoc($participants)): 
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
                                        <span class="font-medium text-gray-900"><?= htmlspecialchars($row['nama']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600"><?= htmlspecialchars($row['nim']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($row['kelas']) ?></td>
                                <td class="px-6 py-4 text-gray-500 text-sm"><?= $row['waktu_absen'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    Belum ada peserta.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="text-gray-500">Pilih event terlebih dahulu untuk melihat peserta.</p>
            </div>
            <?php endif; ?>

        </div>
    </main>

</body>
</html>
