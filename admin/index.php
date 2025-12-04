<?php
include "auth.php";
include "../config/koneksi.php";

// Basic stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_events = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM events"))['c'];
$total_absen = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM absen"))['c'];
$event_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE status='open' LIMIT 1"));

// Pending permissions count
$pending_permissions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM permissions WHERE status='pending'"))['c'] ?? 0;

// Attendance data for last 7 days
$attendance_7days = mysqli_query($conn, "
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM absen 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    $chart_data[$date] = 0;
}
while ($row = mysqli_fetch_assoc($attendance_7days)) {
    if (isset($chart_data[$row['date']])) {
        $chart_data[$row['date']] = (int)$row['count'];
    }
}
$chart_data = array_values($chart_data);

// Attendance by event (top 5)
$event_stats = mysqli_query($conn, "
    SELECT e.nama_event, COUNT(a.id) as count 
    FROM events e 
    LEFT JOIN absen a ON e.id = a.event_id 
    GROUP BY e.id 
    ORDER BY count DESC 
    LIMIT 5
");
$event_labels = [];
$event_data = [];
while ($row = mysqli_fetch_assoc($event_stats)) {
    $event_labels[] = strlen($row['nama_event']) > 20 ? substr($row['nama_event'], 0, 20) . '...' : $row['nama_event'];
    $event_data[] = (int)$row['count'];
}

// Attendance by class
$class_stats = mysqli_query($conn, "
    SELECT u.kelas, COUNT(a.id) as count 
    FROM users u 
    LEFT JOIN absen a ON u.id = a.user_id 
    WHERE u.kelas != '-' AND u.kelas != ''
    GROUP BY u.kelas 
    ORDER BY count DESC
    LIMIT 6
");
$class_labels = [];
$class_data = [];
while ($row = mysqli_fetch_assoc($class_stats)) {
    $class_labels[] = $row['kelas'];
    $class_data[] = (int)$row['count'];
}

// Recent attendance
$recent_absen = mysqli_query($conn, "
    SELECT a.created_at, u.nama, u.nim, e.nama_event 
    FROM absen a 
    JOIN users u ON a.user_id = u.id 
    JOIN events e ON a.event_id = e.id 
    ORDER BY a.id DESC 
    LIMIT 5
");

// Attendance rate (this month)
$this_month = date('Y-m');
$month_attendance = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT user_id) as attended FROM absen WHERE DATE_FORMAT(created_at, '%Y-%m') = '$this_month'
"))['attended'];
$attendance_rate = $total_users > 0 ? round(($month_attendance / $total_users) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">

    <?php include "sidebar.php"; ?>

    <main class="lg:ml-64 min-h-screen pt-14 lg:pt-0">
        <div class="p-6">
            
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-500">Selamat datang di panel admin</p>
                </div>
                <div class="flex gap-2">
                    <?php if ($pending_permissions > 0): ?>
                    <a href="permissions.php" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-100 text-orange-700 rounded-lg font-medium hover:bg-orange-200 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?= $pending_permissions ?> Izin Pending
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alert Event Aktif -->
            <?php if($event_aktif): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                    </div>
                    <div>
                        <p class="font-medium text-green-800">Event Aktif</p>
                        <p class="text-green-600"><?= htmlspecialchars($event_aktif['nama_event']) ?></p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="generate_qr.php?id=<?= $event_aktif['id'] ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition">Lihat QR</a>
                    <a href="stop_event.php?id=<?= $event_aktif['id'] ?>" class="px-4 py-2 bg-white border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition">Tutup</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total User</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_users ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Event</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_events ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Absensi</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_absen ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Tingkat Kehadiran</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1"><?= $attendance_rate ?>%</p>
                            <p class="text-xs text-gray-400">Bulan ini</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                <a href="create_event.php" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition text-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-gray-900 text-sm">Buat Event</p>
                </a>
                <a href="events.php" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition text-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-gray-900 text-sm">Kelola Event</p>
                </a>
                <a href="users.php" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition text-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-gray-900 text-sm">Kelola User</p>
                </a>
                <a href="permissions.php" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition text-center relative">
                    <?php if ($pending_permissions > 0): ?>
                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"><?= $pending_permissions ?></span>
                    <?php endif; ?>
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-gray-900 text-sm">Izin/Sakit</p>
                </a>
                <a href="announcements.php" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition text-center">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-gray-900 text-sm">Pengumuman</p>
                </a>
                <a href="absen.php" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 hover:shadow-sm transition text-center">
                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </div>
                    <p class="font-medium text-gray-900 text-sm">Data Absensi</p>
                </a>
            </div>

        </div>
    </main>

    <script>
    // Line Chart - 7 Days
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Jumlah Absensi',
                data: <?= json_encode($chart_data) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // Bar Chart - By Event
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($event_labels) ?>,
            datasets: [{
                label: 'Peserta',
                data: <?= json_encode($event_data) ?>,
                backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // Doughnut Chart - By Class
    new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($class_labels) ?>,
            datasets: [{
                data: <?= json_encode($class_data) ?>,
                backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#6366f1']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12 } }
            }
        }
    });
    </script>

</body>
</html>
