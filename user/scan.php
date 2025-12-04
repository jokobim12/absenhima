<?php include "auth.php"; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR - Absensi HIMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        .glass { backdrop-filter: blur(10px); }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800">

    <!-- Navbar -->
    <nav class="bg-white/10 glass border-b border-white/10 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
                <span class="text-white font-bold text-lg">Scan QR Code</span>
            </div>
            <a href="dashboard.php" class="text-white/60 hover:text-white flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-white/10 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali
            </a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid lg:grid-cols-2 gap-8 items-center min-h-[70vh]">
            
            <!-- Left: Instructions -->
            <div class="text-white order-2 lg:order-1">
                <h1 class="text-3xl lg:text-4xl font-bold mb-4">Arahkan Kamera ke QR Code</h1>
                <p class="text-white/60 text-lg mb-8">Pastikan QR code yang ditampilkan admin terlihat jelas di dalam kotak scanner.</p>
                
                <div class="space-y-4">
                    <div class="flex items-start gap-4 p-4 bg-white/5 rounded-xl">
                        <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-blue-400 font-bold">1</span>
                        </div>
                        <div>
                            <p class="font-medium">Izinkan akses kamera</p>
                            <p class="text-white/50 text-sm">Browser akan meminta izin untuk menggunakan kamera Anda</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-4 bg-white/5 rounded-xl">
                        <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-blue-400 font-bold">2</span>
                        </div>
                        <div>
                            <p class="font-medium">Arahkan ke QR Code</p>
                            <p class="text-white/50 text-sm">Posisikan QR code di dalam area kotak scanner</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-4 bg-white/5 rounded-xl">
                        <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-green-400 font-bold">3</span>
                        </div>
                        <div>
                            <p class="font-medium">Absensi otomatis</p>
                            <p class="text-white/50 text-sm">Sistem akan otomatis mencatat kehadiran Anda</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Scanner -->
            <div class="order-1 lg:order-2">
                <div class="bg-white rounded-3xl p-6 shadow-2xl">
                    <div id="reader" class="rounded-2xl overflow-hidden mb-4"></div>

                    <div id="status" class="hidden">
                        <div class="bg-blue-50 rounded-xl p-6 text-center">
                            <div class="flex items-center justify-center mb-3">
                                <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="text-blue-600 font-semibold text-lg">Memproses Absensi...</p>
                            <p class="text-blue-400 text-sm">Mohon tunggu sebentar</p>
                        </div>
                    </div>

                    <p class="text-center text-slate-400 text-sm">
                        QR code berubah setiap 5 detik, pastikan Anda scan dengan cepat
                    </p>
                </div>
            </div>

        </div>
    </div>

<script>
function onScanSuccess(decodedText) {
    document.getElementById('status').classList.remove('hidden');
    document.getElementById('reader').style.display = 'none';
    window.location = "submit_absen.php?token=" + decodedText;
}

var scanner = new Html5QrcodeScanner("reader", { 
    fps: 10, 
    qrbox: { width: 280, height: 280 },
    aspectRatio: 1.0
});
scanner.render(onScanSuccess);
</script>

<style>
#reader__scan_region {
    border-radius: 16px;
}
#reader__dashboard_section_csr button {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 24px !important;
    font-weight: 600 !important;
}
#reader__dashboard_section_csr button:hover {
    background: linear-gradient(135deg, #334155 0%, #1e293b 100%) !important;
}
</style>

</body>
</html>
