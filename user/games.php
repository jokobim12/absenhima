<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/settings.php";

$user_id = intval($_SESSION['user_id']);
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
$today = date('Y-m-d');

// Cek apakah sudah spin hari ini
$already_spin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'spin_wheel' AND DATE(created_at) = '$today'"));
$can_spin = !$already_spin;

// Get user points
$user_points = intval($user['total_points']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - <?= htmlspecialchars($s['site_name'] ?? 'AbsenHIMA') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { darkMode: 'class' }
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark');
    }
    </script>
    <style>
        .dark body { background: #0a0a0a !important; }
        .dark .bg-white { background: #1a1a1a !important; }
        .dark .bg-slate-50 { background: #0f0f0f !important; }
        .dark .border-slate-200 { border-color: #333 !important; }
        .dark .text-slate-900 { color: #f5f5f5 !important; }
        .dark .text-slate-600 { color: #a0a0a0 !important; }
        .dark .text-slate-500 { color: #888 !important; }
        
        .wheel-container {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 0 auto;
        }
        
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: relative;
            background: conic-gradient(
                #ef4444 0deg 60deg,
                #f97316 60deg 120deg,
                #eab308 120deg 180deg,
                #22c55e 180deg 240deg,
                #3b82f6 240deg 300deg,
                #8b5cf6 300deg 360deg
            );
            box-shadow: 0 0 20px rgba(0,0,0,0.3), inset 0 0 10px rgba(0,0,0,0.2);
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
        }
        
        .wheel-label {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }
        
        .wheel-label span {
            position: absolute;
            left: 50%;
            top: 15%;
            transform-origin: 0 110px;
            font-weight: bold;
            font-size: 18px;
            color: white;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }
        
        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .wheel-pointer {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 35px solid #ef4444;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            z-index: 20;
        }
        
        .wheel-pointer::after {
            content: '';
            position: absolute;
            top: -35px;
            left: -10px;
            width: 20px;
            height: 20px;
            background: #ef4444;
            border-radius: 50%;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .bounce {
            animation: bounce 0.5s ease-in-out;
        }
        
        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            top: -10px;
            animation: confetti 3s ease-out forwards;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-purple-500 to-pink-500 text-white">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="p-2 hover:bg-white/20 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div class="flex-1">
                    <h1 class="text-xl font-bold">Mini Games</h1>
                    <p class="text-sm text-white/80">Main game, dapat poin!</p>
                </div>
                <div class="bg-white/20 rounded-xl px-4 py-2">
                    <p class="text-xs text-white/70">Poin Kamu</p>
                    <p class="text-xl font-bold" id="userPoints"><?= number_format($user_points) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">
        
        <!-- Spin Wheel Section -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-lg">🎡 Spin Wheel</h2>
                    <p class="text-sm text-slate-500">Putar roda keberuntungan!</p>
                </div>
                <div class="text-right">
                    <?php if($can_spin): ?>
                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                        1x Tersedia
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-sm font-medium">
                        Besok lagi ya!
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Wheel -->
                <div class="wheel-container mb-6">
                    <div class="wheel-pointer"></div>
                    <div class="wheel" id="wheel">
                        <div class="wheel-label">
                            <span style="transform: rotate(30deg) translateX(-50%);">+1</span>
                            <span style="transform: rotate(90deg) translateX(-50%);">+2</span>
                            <span style="transform: rotate(150deg) translateX(-50%);">+3</span>
                            <span style="transform: rotate(210deg) translateX(-50%);">+5</span>
                            <span style="transform: rotate(270deg) translateX(-50%);">+7</span>
                            <span style="transform: rotate(330deg) translateX(-50%);">+10</span>
                        </div>
                    </div>
                    <div class="wheel-center">
                        <span class="text-white text-2xl">🎯</span>
                    </div>
                </div>
                
                <!-- Spin Button -->
                <div class="text-center">
                    <?php if($can_spin): ?>
                    <button id="spinBtn" onclick="spinWheel()" class="px-8 py-4 bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-bold text-lg rounded-2xl shadow-lg transform hover:scale-105 transition-all">
                        🎰 PUTAR SEKARANG!
                    </button>
                    <?php else: ?>
                    <button disabled class="px-8 py-4 bg-slate-300 text-slate-500 font-bold text-lg rounded-2xl cursor-not-allowed">
                        Sudah Spin Hari Ini
                    </button>
                    <p class="text-sm text-slate-500 mt-3">Kembali lagi besok untuk spin gratis!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Snake Game (Coming Soon) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 opacity-60">
            <div class="flex items-center gap-4">
                <span class="text-4xl">🐍</span>
                <div>
                    <h3 class="font-bold text-slate-900">Snake Game</h3>
                    <p class="text-sm text-slate-500">Segera hadir</p>
                </div>
            </div>
        </div>
        
        <!-- Rules -->
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
            <h3 class="font-semibold text-purple-900 mb-2">📋 Cara Main</h3>
            <ul class="text-sm text-purple-700 space-y-1">
                <li>• Kamu dapat 1x spin gratis setiap hari</li>
                <li>• Hadiah: 1, 2, 3, 5, 7, atau 10 poin</li>
                <li>• Poin langsung masuk ke akun kamu</li>
                <li>• Reset setiap jam 00:00 WITA</li>
            </ul>
        </div>
    </div>
    
    <!-- Spin Result Modal -->
    <div id="resultModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-3xl p-8 text-center max-w-sm mx-4 transform scale-95 transition-transform" id="resultContent">
            <div class="text-6xl mb-4" id="resultEmoji">🎉</div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Selamat!</h2>
            <p class="text-slate-600 mb-4">Kamu mendapatkan</p>
            <div class="text-5xl font-bold text-purple-600 mb-6">+<span id="resultPoints">0</span> Poin</div>
            <button onclick="closeModal()" class="w-full py-3 bg-purple-500 hover:bg-purple-600 text-white font-bold rounded-xl transition">
                Mantap! 🎯
            </button>
        </div>
    </div>

    <script>
    // Segment order: 1, 2, 3, 5, 7, 10 (each 60 degrees)
    // Position 0deg = +1 (red), 60deg = +2 (orange), etc.
    const prizeAngles = {
        1: 30,    // center of segment 0-60
        2: 90,    // center of segment 60-120
        3: 150,   // center of segment 120-180
        5: 210,   // center of segment 180-240
        7: 270,   // center of segment 240-300
        10: 330   // center of segment 300-360
    };
    
    let isSpinning = false;
    const wheel = document.getElementById('wheel');
    
    async function spinWheel() {
        if (isSpinning) return;
        isSpinning = true;
        
        const btn = document.getElementById('spinBtn');
        btn.disabled = true;
        btn.innerHTML = '🎰 Memutar...';
        
        try {
            const res = await fetch('../api/spin_wheel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            });
            const data = await res.json();
            
            if (!data.success) {
                alert(data.error || 'Gagal spin');
                btn.disabled = false;
                btn.innerHTML = '🎰 PUTAR SEKARANG!';
                isSpinning = false;
                return;
            }
            
            const prize = data.prize;
            const targetAngle = prizeAngles[prize];
            
            // Spin: 5 full rotations + stop at prize position
            // Pointer is at top (0deg), so we need to rotate wheel so prize is at top
            const extraSpins = 5;
            const finalRotation = (360 * extraSpins) + (360 - targetAngle);
            
            wheel.style.transform = `rotate(${finalRotation}deg)`;
            
            setTimeout(() => {
                showResult(prize, data.total_points);
                btn.innerHTML = 'Sudah Spin Hari Ini';
                btn.classList.remove('from-purple-500', 'to-pink-500', 'hover:from-purple-600', 'hover:to-pink-600');
                btn.classList.add('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
            }, 4200);
            
        } catch (err) {
            console.error(err);
            alert('Terjadi kesalahan');
            btn.disabled = false;
            btn.innerHTML = '🎰 PUTAR SEKARANG!';
            isSpinning = false;
        }
    }
    
    function showResult(points, totalPoints) {
        document.getElementById('resultPoints').textContent = points;
        document.getElementById('userPoints').textContent = totalPoints.toLocaleString();
        
        // Different emoji based on prize
        const emoji = points >= 7 ? '🎊' : points >= 5 ? '🎉' : '✨';
        document.getElementById('resultEmoji').textContent = emoji;
        
        const modal = document.getElementById('resultModal');
        const content = document.getElementById('resultContent');
        modal.classList.remove('hidden');
        setTimeout(() => content.style.transform = 'scale(1)', 10);
        
        // Confetti
        createConfetti();
    }
    
    function closeModal() {
        const modal = document.getElementById('resultModal');
        const content = document.getElementById('resultContent');
        content.style.transform = 'scale(0.95)';
        setTimeout(() => modal.classList.add('hidden'), 200);
    }
    
    function createConfetti() {
        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6'];
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 2 + 's';
            document.body.appendChild(confetti);
            setTimeout(() => confetti.remove(), 5000);
        }
    }
    </script>
</body>
</html>
