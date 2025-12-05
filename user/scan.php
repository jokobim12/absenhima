<?php include "auth.php"; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR - Absensi HIMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
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
                    <div class="relative">
                        <div id="reader" class="rounded-2xl overflow-hidden mb-4"></div>
                        <button id="switch-camera" onclick="switchCamera()" class="absolute bottom-6 right-3 z-10 w-10 h-10 bg-slate-800/80 hover:bg-slate-700 rounded-full flex items-center justify-center transition shadow-lg" title="Ganti Kamera">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Status: Processing -->
                    <div id="status-processing" class="hidden">
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

                    <!-- Status: Success -->
                    <div id="status-success" class="hidden">
                        <div class="bg-green-50 rounded-xl p-6 text-center">
                            <div class="flex items-center justify-center mb-3">
                                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-green-600 font-semibold text-lg">Absensi Berhasil!</p>
                            <p id="success-event" class="text-green-500 text-sm"></p>
                            <a href="dashboard.php" class="inline-block mt-4 px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                                Kembali ke Dashboard
                            </a>
                        </div>
                    </div>

                    <!-- Status: Error -->
                    <div id="status-error" class="hidden">
                        <div class="bg-red-50 rounded-xl p-6 text-center">
                            <div class="flex items-center justify-center mb-3">
                                <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-red-600 font-semibold text-lg">Gagal!</p>
                            <p id="error-message" class="text-red-500 text-sm"></p>
                            <button onclick="resetScanner()" class="inline-block mt-4 px-6 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700">
                                Coba Lagi
                            </button>
                        </div>
                    </div>

                    <p id="scan-hint" class="text-center text-slate-400 text-sm">
                        QR code berubah setiap 5 detik, pastikan Anda scan dengan cepat
                    </p>
                </div>
            </div>

        </div>
    </div>

<script>
var html5QrCode = null;
var isProcessing = false;
var userLocation = null;
var useBackCamera = true;

function switchCamera() {
    if (isProcessing || !html5QrCode) return;
    
    useBackCamera = !useBackCamera;
    
    html5QrCode.stop().then(() => {
        startCamera();
    }).catch(err => {
        startCamera();
    });
}

function startCamera() {
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    const facingMode = useBackCamera ? "environment" : "user";
    
    html5QrCode.start(
        { facingMode: facingMode },
        config,
        onScanSuccess,
        () => {} // Ignore no QR errors
    ).catch(err => {
        console.log('Camera error:', err);
        document.getElementById('reader').innerHTML = `
            <div class="text-center p-8">
                <p class="text-red-500 mb-4">Gagal mengakses kamera</p>
                <p class="text-slate-500 text-sm mb-4">${err}</p>
                <button onclick="location.reload()" class="px-4 py-2 bg-blue-500 text-white rounded-lg">Refresh</button>
            </div>`;
    });
}

function initScanner() {
    try {
        html5QrCode = new Html5Qrcode("reader");
        startCamera();
    } catch(e) {
        document.getElementById('reader').innerHTML = `
            <div class="text-center p-8">
                <p class="text-red-500 mb-4">Error inisialisasi scanner</p>
                <p class="text-slate-500 text-sm mb-4">${e.message}</p>
                <button onclick="location.reload()" class="px-4 py-2 bg-blue-500 text-white rounded-lg">Refresh</button>
            </div>`;
    }
}

// Try to get user location on page load
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        function(position) {
            userLocation = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };
            console.log('Location acquired:', userLocation);
        },
        function(error) {
            console.log('Location error:', error.message);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function onScanSuccess(decodedText) {
    if (isProcessing) return;
    isProcessing = true;
    
    // Hide scanner, show processing
    document.getElementById('reader').style.display = 'none';
    document.getElementById('scan-hint').style.display = 'none';
    document.getElementById('status-processing').classList.remove('hidden');
    
    // Stop scanner
    if (html5QrCode) {
        html5QrCode.stop().catch(err => console.log('Stop error:', err));
    }
    
    // Build form data with location
    const formData = new FormData();
    formData.append('token', decodedText);
    if (userLocation) {
        formData.append('latitude', userLocation.latitude);
        formData.append('longitude', userLocation.longitude);
    }
    
    // Submit via AJAX with POST (to send location data)
    fetch('api_submit_absen.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            document.getElementById('status-processing').classList.add('hidden');
            
            if (data.success) {
                let msg = data.event_name + ' - ' + data.timestamp;
                if (data.location_verified) {
                    msg += ' ✓ GPS';
                }
                document.getElementById('success-event').textContent = msg;
                document.getElementById('status-success').classList.remove('hidden');
            } else if (data.require_location) {
                // Event requires location but we don't have it
                document.getElementById('error-message').textContent = data.message;
                document.getElementById('status-error').classList.remove('hidden');
                // Try to get location again
                requestLocation();
            } else {
                document.getElementById('error-message').textContent = data.message;
                document.getElementById('status-error').classList.remove('hidden');
            }
        })
        .catch(error => {
            document.getElementById('status-processing').classList.add('hidden');
            document.getElementById('error-message').textContent = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
            document.getElementById('status-error').classList.remove('hidden');
        });
}

function requestLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                userLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                };
                alert('Lokasi berhasil diambil. Silakan scan ulang QR code.');
            },
            function(error) {
                alert('Gagal mengambil lokasi. Pastikan GPS aktif dan izinkan akses lokasi.');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
}

function resetScanner() {
    isProcessing = false;
    document.getElementById('status-error').classList.add('hidden');
    document.getElementById('status-success').classList.add('hidden');
    document.getElementById('reader').style.display = 'block';
    document.getElementById('reader').innerHTML = '';
    document.getElementById('scan-hint').style.display = 'block';
    
    // Reinitialize scanner
    initScanner();
}

// Initialize scanner
initScanner();
</script>

<style>
#reader__scan_region {
    border-radius: 16px;
}
#reader__dashboard_section_csr button {
    background: #dc2626 !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 24px !important;
    font-weight: 600 !important;
    color: white !important;
}
#reader__dashboard_section_csr button:hover {
    background: #b91c1c !important;
}
/* Hide dropdown & switch button on mobile, show on desktop */
@media (max-width: 768px) {
    #reader__dashboard_section_csr select,
    #reader__dashboard_section_swaplink,
    #reader select {
        display: none !important;
    }
}
@media (min-width: 769px) {
    #switch-camera {
        display: none !important;
    }
}
</style>

</body>
</html>
