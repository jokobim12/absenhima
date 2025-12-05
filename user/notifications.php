<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

include "../config/koneksi.php";

$user_id = intval($_SESSION['user_id']);

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get site settings
$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
$s = $settings ?: [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - <?= htmlspecialchars($s['site_name'] ?? 'SADHATI') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#f97316',
                        secondary: '#0ea5e9'
                    }
                }
            }
        }
    </script>
    <script>
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark');
    }
    </script>
    <style>
        .glass { backdrop-filter: blur(10px); }
        .dark body { background: #0a0a0a !important; }
        .dark .bg-white { background-color: #1a1a1a !important; }
        .dark .bg-white\/80 { background: rgba(26, 26, 26, 0.9) !important; }
        .dark .bg-slate-50 { background-color: #0a0a0a !important; }
        .dark .border-slate-200 { border-color: #333 !important; }
        .dark .border-slate-100 { border-color: #222 !important; }
        .dark .text-slate-900 { color: #f1f1f1 !important; }
        .dark .text-slate-800 { color: #e5e5e5 !important; }
        .dark .text-slate-700 { color: #d4d4d4 !important; }
        .dark .text-slate-600 { color: #a3a3a3 !important; }
        .dark .text-slate-500 { color: #737373 !important; }
        .dark .text-slate-400 { color: #525252 !important; }
        .dark .hover\:bg-slate-50:hover { background-color: #222 !important; }
        .dark .hover\:bg-slate-100:hover { background-color: #333 !important; }
        .dark .bg-blue-50 { background: rgba(59, 130, 246, 0.1) !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white/80 glass border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-4">
            <a href="dashboard.php" class="text-slate-500 hover:text-slate-900 p-2 hover:bg-slate-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-lg font-bold text-slate-900">Notifikasi</h1>
            <div class="flex-1"></div>
            <button onclick="toggleDarkMode()" class="text-slate-500 hover:text-slate-900 p-2 hover:bg-slate-100 rounded-lg transition mr-2" title="Dark Mode">
                <svg id="sunIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <svg id="moonIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
            </button>
            <button onclick="markAllRead()" class="text-sm text-secondary hover:underline">Tandai semua dibaca</button>
        </div>
    </nav>

    <!-- Content -->
    <div class="max-w-4xl mx-auto p-4">
        <div id="notifContainer" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="p-8 text-center text-slate-400">
                <svg class="w-8 h-8 mx-auto mb-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memuat notifikasi...
            </div>
        </div>
        
        <!-- Load More -->
        <div id="loadMoreContainer" class="hidden text-center mt-4">
            <button onclick="loadMore()" id="loadMoreBtn" class="px-6 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition">
                Muat lebih banyak
            </button>
        </div>
    </div>

    <script>
    const BASE_URL = '../api';
    let offset = 0;
    const limit = 20;
    let allLoaded = false;

    const icons = {
        'mention': '💬',
        'reply': '↩️',
        'event': '📅',
        'badge': '🏆',
        'announcement': '📢',
        'iuran': '💰',
        'system': '🔔'
    };

    async function loadNotifications(append = false) {
        try {
            const res = await fetch(`${BASE_URL}/notifications.php?limit=${limit}&offset=${offset}`);
            const data = await res.json();
            
            if (data.success) {
                if (!append) {
                    renderNotifications(data.notifications);
                } else {
                    appendNotifications(data.notifications);
                }
                
                if (data.notifications.length < limit) {
                    allLoaded = true;
                    document.getElementById('loadMoreContainer').classList.add('hidden');
                } else {
                    document.getElementById('loadMoreContainer').classList.remove('hidden');
                }
            }
        } catch (err) {
            console.error('Load error:', err);
            document.getElementById('notifContainer').innerHTML = `
                <div class="p-8 text-center text-red-500">Gagal memuat notifikasi</div>
            `;
        }
    }

    function renderNotifications(notifications) {
        const container = document.getElementById('notifContainer');
        
        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <p class="text-slate-400">Belum ada notifikasi</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = notifications.map(n => createNotifHtml(n)).join('');
    }

    function appendNotifications(notifications) {
        const container = document.getElementById('notifContainer');
        notifications.forEach(n => {
            container.insertAdjacentHTML('beforeend', createNotifHtml(n));
        });
    }

    function createNotifHtml(n) {
        const isVirtual = n.is_virtual || typeof n.id === 'string';
        return `
            <div class="notif-item p-4 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0 ${n.is_read ? 'opacity-60' : ''}" 
                 onclick="handleClick('${n.id}', '${n.link || ''}', ${!n.is_read && !isVirtual})" id="notif-${n.id}">
                <div class="flex gap-4">
                    <span class="text-2xl">${icons[n.type] || '🔔'}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-900 ${n.is_read ? '' : 'font-semibold'}">${escapeHtml(n.title)}</p>
                        ${n.message ? `<p class="text-sm text-slate-500 mt-1">${escapeHtml(n.message)}</p>` : ''}
                        <p class="text-xs text-slate-400 mt-2">${formatDate(n.created_at)}</p>
                    </div>
                    ${!n.is_read ? '<span class="w-2 h-2 bg-secondary rounded-full flex-shrink-0 mt-2"></span>' : ''}
                </div>
            </div>
        `;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`;
        if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
        if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`;
        
        return date.toLocaleDateString('id-ID', { 
            day: 'numeric', 
            month: 'long', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    async function handleClick(id, link, markRead) {
        if (markRead) {
            try {
                await fetch(`${BASE_URL}/notifications.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mark_read: true, id: id })
                });
                
                // Update UI
                const el = document.getElementById(`notif-${id}`);
                if (el) {
                    el.classList.add('opacity-60');
                    el.querySelector('.bg-secondary')?.remove();
                    el.querySelector('.font-semibold')?.classList.remove('font-semibold');
                }
            } catch (err) {}
        }
        
        if (link) {
            window.location.href = link;
        }
    }

    async function markAllRead() {
        try {
            await fetch(`${BASE_URL}/notifications.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_all_read: true })
            });
            
            // Update UI
            document.querySelectorAll('.notif-item').forEach(el => {
                el.classList.add('opacity-60');
                el.querySelector('.bg-secondary')?.remove();
                el.querySelector('.font-semibold')?.classList.remove('font-semibold');
            });
        } catch (err) {
            console.error('Error:', err);
        }
    }

    function loadMore() {
        if (allLoaded) return;
        offset += limit;
        loadNotifications(true);
    }

    // ==================== DARK MODE ====================
    function toggleDarkMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', isDark);
        document.body.style.background = isDark ? '#0a0a0a' : '';
        document.getElementById('sunIcon').classList.toggle('hidden', !isDark);
        document.getElementById('moonIcon').classList.toggle('hidden', isDark);
    }
    
    // Initialize dark mode
    (function() {
        const isDark = document.documentElement.classList.contains('dark');
        document.getElementById('sunIcon').classList.toggle('hidden', !isDark);
        document.getElementById('moonIcon').classList.toggle('hidden', isDark);
        if (isDark) document.body.style.background = '#0a0a0a';
    })();

    // Initial load
    loadNotifications();
    </script>
</body>
</html>
