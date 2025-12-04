<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oops! Akun Salah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px) rotate(-5deg); }
            75% { transform: translateX(10px) rotate(5deg); }
        }
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes wiggle {
            0%, 100% { transform: rotate(-3deg); }
            50% { transform: rotate(3deg); }
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .float { animation: float 3s ease-in-out infinite; }
        .shake { animation: shake 0.5s ease-in-out; }
        .bounce-slow { animation: bounce-slow 2s ease-in-out infinite; }
        .wiggle { animation: wiggle 1s ease-in-out infinite; }
        .slide-up { animation: slide-up 0.6s ease-out forwards; }
        .slide-up-delay-1 { animation-delay: 0.1s; opacity: 0; }
        .slide-up-delay-2 { animation-delay: 0.2s; opacity: 0; }
        .slide-up-delay-3 { animation-delay: 0.3s; opacity: 0; }
        .pulse-ring {
            animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4 overflow-hidden">
    
    <!-- Background decorations -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-10 left-10 text-6xl float" style="animation-delay: 0s;">🎓</div>
        <div class="absolute top-20 right-20 text-5xl float" style="animation-delay: 0.5s;">📚</div>
        <div class="absolute bottom-20 left-20 text-5xl float" style="animation-delay: 1s;">✨</div>
        <div class="absolute bottom-10 right-10 text-6xl float" style="animation-delay: 1.5s;">🏫</div>
        <div class="absolute top-1/2 left-5 text-4xl bounce-slow" style="animation-delay: 0.3s;">💫</div>
        <div class="absolute top-1/3 right-10 text-4xl bounce-slow" style="animation-delay: 0.8s;">⭐</div>
    </div>

    <div class="bg-white rounded-3xl card-shadow p-8 md:p-12 max-w-lg text-center relative z-10">
        
        <!-- Animated character -->
        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-32 h-32 bg-red-100 rounded-full pulse-ring"></div>
            </div>
            <div class="relative text-8xl wiggle">
                🙈
            </div>
        </div>
        
        <!-- Content -->
        <div class="slide-up">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3">
                Waduh, Salah Akun!
            </h1>
        </div>
        
        <div class="slide-up slide-up-delay-1">
            <p class="text-gray-600 text-lg mb-6">
                Kamu login pakai akun yang bukan dari <span class="font-semibold text-purple-600">Politala</span> nih...
            </p>
        </div>
        
        <div class="slide-up slide-up-delay-2">
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl p-6 mb-6 border-2 border-dashed border-purple-200">
                <div class="text-4xl mb-3">📧</div>
                <p class="text-gray-700 font-medium mb-2">Gunakan email Politala:</p>
                <div class="space-y-1">
                    <p class="text-purple-600 font-mono text-sm bg-white px-3 py-1 rounded-lg inline-block">nim@politala.ac.id</p>
                    <p class="text-gray-400 text-sm">atau</p>
                    <p class="text-purple-600 font-mono text-sm bg-white px-3 py-1 rounded-lg inline-block">nim@mhs.politala.ac.id</p>
                </div>
            </div>
        </div>
        
        <div class="slide-up slide-up-delay-3">
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="../index.php" 
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-all hover:scale-105">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali
                </a>
                <a href="google_login.php" 
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-medium hover:from-purple-700 hover:to-pink-700 transition-all hover:scale-105 shadow-lg">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#fff"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#fff"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff"/>
                    </svg>
                    Coba Lagi
                </a>
            </div>
        </div>
        
        <!-- Fun footer -->
        <div class="mt-8 pt-6 border-t border-gray-100">
            <p class="text-gray-400 text-sm flex items-center justify-center gap-2">
                <span class="text-lg">🎯</span>
                Tips: Pastikan sudah login ke akun Google Politala dulu ya!
            </p>
        </div>
    </div>

    <!-- Easter egg - click the monkey -->
    <script>
        let clickCount = 0;
        const emojis = ['🙈', '🙉', '🙊', '🐵', '🤭', '😅', '🫣'];
        document.querySelector('.wiggle').addEventListener('click', function() {
            clickCount++;
            this.textContent = emojis[clickCount % emojis.length];
            this.classList.add('shake');
            setTimeout(() => this.classList.remove('shake'), 500);
        });
    </script>
</body>
</html>
