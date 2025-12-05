<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/helpers.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#111827',
                        secondary: '#6366f1',
                        accent: '#10b981'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <?php include "sidebar.php"; ?>
        
        <main class="flex-1 lg:ml-64">
            <div class="p-4 sm:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
                    <!-- Header -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Forum Diskusi</h1>
                        <p class="text-gray-600 mt-1">Moderasi forum diskusi mahasiswa</p>
                    </div>

                    <!-- Forum Container -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <h3 class="font-bold text-slate-900">Forum Diskusi</h3>
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Mode Moderator</span>
                            </div>
                            <span class="text-xs text-green-500 flex items-center gap-1" id="onlineStatus">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                Live
                            </span>
                        </div>
                        
                        <!-- Pinned Messages Banner -->
                        <div id="pinnedBanner" class="hidden border-b border-amber-200 bg-amber-50 max-h-32 overflow-y-auto">
                        </div>
                        
                        <!-- Chat Messages -->
                        <div id="chatMessages" class="h-[500px] overflow-y-auto p-4 space-y-3 bg-slate-50">
                            <div class="text-center text-slate-400 text-sm py-8">Memuat pesan...</div>
                        </div>
                        
                        <!-- Info Bar -->
                        <div class="p-4 border-t border-slate-100 bg-amber-50">
                            <div class="flex items-center gap-2 text-amber-700 text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Mode moderator: Anda dapat pin dan hapus pesan. Klik pesan untuk melihat aksi.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    const BASE_URL = '../api';
    let lastMessageId = 0;
    let isPolling = true;

    function formatTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(datetime) {
        const date = new Date(datetime);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        if (date.toDateString() === today.toDateString()) return 'Hari ini';
        if (date.toDateString() === yesterday.toDateString()) return 'Kemarin';
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function createMessageEl(msg, showDate = false) {
        const isDeleted = msg.is_deleted;
        const isPinned = msg.is_pinned;
        const picture = msg.picture ? (msg.picture.startsWith('http') ? msg.picture : '../' + msg.picture) : null;
        
        // Check if message is a sticker (emoji or image)
        const emojiStickerMatch = msg.message ? msg.message.match(/^\[sticker\](.+)\[\/sticker\]$/) : null;
        const imageStickerMatch = msg.message ? msg.message.match(/^\[sticker:(.+)\]$/) : null;
        const isEmojiSticker = emojiStickerMatch !== null;
        const isImageSticker = imageStickerMatch !== null;
        
        let messageContent;
        if (isDeleted) {
            messageContent = `<p class="italic text-slate-400">${msg.message}</p>`;
        } else if (isEmojiSticker) {
            messageContent = `<div class="text-6xl leading-none py-1">${emojiStickerMatch[1]}</div>`;
        } else if (isImageSticker) {
            const stickerUrl = imageStickerMatch[1];
            messageContent = `<img src="../${stickerUrl}" class="w-32 h-32 object-contain" alt="sticker">`;
        } else {
            // Parse URLs
            let msgText = msg.message || '';
            msgText = msgText.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" class="text-blue-500 underline hover:text-blue-700">$1</a>');
            messageContent = `<p class="text-sm whitespace-pre-wrap break-words">${msgText}</p>`;
            if (msg.image_url) {
                const imgSrc = msg.image_url.startsWith('http') ? msg.image_url : '../' + msg.image_url;
                messageContent += `<img src="${imgSrc}" class="mt-2 max-w-full max-h-60 rounded-lg cursor-pointer hover:opacity-90 object-contain" onclick="openImageModal('${imgSrc}')" alt="Image">`;
            }
            if (msg.file_url) {
                const fileExt = msg.file_name ? msg.file_name.split('.').pop().toLowerCase() : '';
                const icons = { 'pdf': '📕', 'doc': '📘', 'docx': '📘', 'xls': '📗', 'xlsx': '📗', 'ppt': '📙', 'pptx': '📙' };
                messageContent += `
                    <div class="mt-2">
                        <a href="../${msg.file_url}" download="${msg.file_name || 'file'}" class="flex items-center gap-2 px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                            <span class="text-2xl">${icons[fileExt] || '📎'}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate text-slate-700">${msg.file_name || 'File'}</p>
                                <p class="text-xs text-slate-500">${fileExt.toUpperCase()}</p>
                            </div>
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </a>
                    </div>`;
            }
            // Voice message
            if (msg.voice_url) {
                const formatDuration = (s) => `${Math.floor(s/60)}:${(s%60).toString().padStart(2,'0')}`;
                messageContent += `
                    <div class="mt-2">
                        <div class="voice-message flex items-center gap-3 px-3 py-2 bg-slate-100 rounded-xl min-w-[200px]">
                            <button type="button" onclick="toggleVoicePlay(this, '../${msg.voice_url}')" class="voice-play-btn w-10 h-10 flex items-center justify-center bg-secondary hover:bg-secondary/90 rounded-full transition flex-shrink-0">
                                <svg class="play-icon w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <svg class="pause-icon w-5 h-5 text-white hidden" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>
                            </button>
                            <div class="flex-1">
                                <div class="voice-progress-container h-1.5 bg-slate-300 rounded-full overflow-hidden cursor-pointer" onclick="seekVoice(event, this)">
                                    <div class="voice-progress h-full bg-secondary rounded-full transition-all" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between mt-1">
                                    <span class="voice-current-time text-xs text-slate-500">0:00</span>
                                    <span class="voice-duration text-xs text-slate-500">${formatDuration(msg.voice_duration || 0)}</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg>
                        </div>
                    </div>`;
            }
        }

        const editedLabel = msg.is_edited ? ' <span class="text-xs opacity-60">(diedit)</span>' : '';
        
        const dateHeader = showDate ? `
            <div class="flex justify-center my-4">
                <span class="text-xs text-slate-400 bg-white px-3 py-1 rounded-full border border-slate-200">${formatDate(msg.created_at)}</span>
            </div>
        ` : '';

        const replyHtml = msg.reply_info ? `
            <div class="text-xs bg-black/5 rounded px-2 py-1 mb-1 border-l-2 border-slate-400 cursor-pointer hover:bg-black/10" onclick="scrollToMessage(${msg.reply_to})">
                <span class="font-semibold">${msg.reply_info.nama}</span>
                <p class="truncate opacity-70">${msg.reply_info.message}</p>
            </div>
        ` : '';

        const pinIndicator = isPinned ? '📌 ' : '';

        const actionButtons = !isDeleted ? `
            <div class="flex gap-1 mt-2 pt-2 border-t border-slate-100">
                <button onclick="togglePin(${msg.id}, ${isPinned})" 
                    class="flex items-center gap-1 px-2 py-1 text-xs rounded ${isPinned ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'} hover:opacity-80">
                    <svg class="w-3 h-3" fill="${isPinned ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                    </svg>
                    ${isPinned ? 'Unpin' : 'Pin'}
                </button>
                <button onclick="deleteMessage(${msg.id})" 
                    class="flex items-center gap-1 px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Hapus
                </button>
            </div>
        ` : `
            <div class="flex gap-1 mt-2 pt-2 border-t border-slate-100">
                <button onclick="deleteMessage(${msg.id}, true)" 
                    class="flex items-center gap-1 px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Hapus Permanen
                </button>
            </div>
        `;
        
        return `
            ${dateHeader}
            <div class="flex gap-3" id="msg-${msg.id}">
                ${picture ? 
                    `<img src="${picture}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">` :
                    `<div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>`
                }
                <div class="flex-1 min-w-0">
                    <div class="bg-white border border-slate-200 px-4 py-3 rounded-xl ${isDeleted ? 'opacity-70' : ''}">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-sm font-semibold text-secondary">${pinIndicator}${msg.nama}</p>
                            <span class="text-xs text-slate-400">${formatTime(msg.created_at)}${editedLabel}</span>
                        </div>
                        ${replyHtml}
                        ${messageContent}
                        ${actionButtons}
                    </div>
                </div>
            </div>
        `;
    }

    function scrollToMessage(id) {
        const el = document.getElementById('msg-' + id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('ring-2', 'ring-secondary');
            setTimeout(() => el.classList.remove('ring-2', 'ring-secondary'), 2000);
        }
    }

    async function deleteMessage(id, permanent = false) {
        const confirmMsg = permanent ? 'Hapus permanen pesan ini? Pesan tidak dapat dikembalikan.' : 'Hapus pesan ini?';
        if (!confirm(confirmMsg)) return;
        
        try {
            const res = await fetch(`${BASE_URL}/forum_delete.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, permanent: permanent })
            });
            
            const data = await res.json();
            if (data.success) {
                lastMessageId = 0;
                await loadMessages();
            } else {
                alert(data.error || 'Gagal menghapus pesan');
            }
        } catch (err) {
            console.error('Error deleting message:', err);
        }
    }

    async function togglePin(id, currentlyPinned) {
        try {
            const res = await fetch(`${BASE_URL}/forum_pin.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, action: currentlyPinned ? 'unpin' : 'pin' })
            });
            
            const data = await res.json();
            if (data.success) {
                lastMessageId = 0;
                await loadMessages();
            } else {
                alert(data.error || 'Gagal mengubah status pin');
            }
        } catch (err) {
            console.error('Error toggling pin:', err);
        }
    }

    function openImageModal(src) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4';
        modal.onclick = () => modal.remove();
        modal.innerHTML = `
            <img src="${src}" class="max-w-full max-h-full rounded-lg">
            <button class="absolute top-4 right-4 text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        `;
        document.body.appendChild(modal);
    }

    let lastDate = '';
    let pinnedMessages = [];
    
    function renderPinnedBanner() {
        const banner = document.getElementById('pinnedBanner');
        if (pinnedMessages.length === 0) {
            banner.classList.add('hidden');
            banner.innerHTML = '';
            return;
        }
        
        banner.classList.remove('hidden');
        let html = '';
        pinnedMessages.forEach((msg, index) => {
            const picture = msg.picture ? (msg.picture.startsWith('http') ? msg.picture : '../' + msg.picture) : '';
            const emojiStickerPreview = msg.message ? msg.message.match(/^\[sticker\](.+)\[\/sticker\]$/) : null;
            const imageStickerPreview = msg.message ? msg.message.match(/^\[sticker:(.+)\]$/) : null;
            const previewText = emojiStickerPreview ? `🎭 ${emojiStickerPreview[1]}` : (imageStickerPreview ? '🎭 Stiker' : (msg.message ? (msg.message.length > 50 ? msg.message.substring(0, 50) + '...' : msg.message) : (msg.image_url ? '📷 Gambar' : (msg.file_url ? '📎 File' : ''))));
            html += `
                <div class="flex items-center gap-3 px-4 py-2 ${index > 0 ? 'border-t border-amber-200' : ''} cursor-pointer hover:bg-amber-100 transition" onclick="scrollToMessage(${msg.id})">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L10 6.477V16h2a1 1 0 110 2H8a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/>
                        </svg>
                        ${picture ? `<img src="${picture}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">` : ''}
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-semibold text-amber-800">${msg.nama}</span>
                            <p class="text-xs text-amber-700 truncate">${previewText}</p>
                        </div>
                    </div>
                    <button onclick="event.stopPropagation(); togglePin(${msg.id}, true)" class="p-1 text-amber-600 hover:text-red-600 flex-shrink-0" title="Lepas Pin">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
        });
        banner.innerHTML = html;
    }
    
    async function loadMessages() {
        try {
            const res = await fetch(`${BASE_URL}/forum_messages.php?last_id=${lastMessageId}`);
            const data = await res.json();
            
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('chatMessages');
                
                // Handle pinned messages
                if (lastMessageId === 0) {
                    pinnedMessages = data.messages.filter(m => m.is_pinned && !m.is_deleted);
                    renderPinnedBanner();
                }
                
                if (lastMessageId === 0) {
                    lastDate = '';
                    let html = '';
                    data.messages.forEach(msg => {
                        const msgDate = new Date(msg.created_at).toDateString();
                        const showDate = msgDate !== lastDate;
                        if (showDate) lastDate = msgDate;
                        html += createMessageEl(msg, showDate);
                    });
                    container.innerHTML = html || '<div class="text-center text-slate-400 text-sm py-8">Belum ada pesan di forum.</div>';
                } else {
                    const newPinned = data.messages.filter(m => m.is_pinned && !m.is_deleted);
                    if (newPinned.length > 0) {
                        newPinned.forEach(np => {
                            if (!pinnedMessages.find(p => p.id === np.id)) {
                                pinnedMessages.push(np);
                            }
                        });
                        renderPinnedBanner();
                    }
                    
                    data.messages.forEach(msg => {
                        const msgDate = new Date(msg.created_at).toDateString();
                        const showDate = msgDate !== lastDate;
                        if (showDate) lastDate = msgDate;
                        container.insertAdjacentHTML('beforeend', createMessageEl(msg, showDate));
                    });
                }
                
                if (data.messages.length > 0) {
                    lastMessageId = Math.max(...data.messages.map(m => m.id));
                }
                
                container.scrollTop = container.scrollHeight;
            } else if (lastMessageId === 0) {
                document.getElementById('chatMessages').innerHTML = '<div class="text-center text-slate-400 text-sm py-8">Belum ada pesan di forum.</div>';
                pinnedMessages = [];
                renderPinnedBanner();
            }
        } catch (err) {
            console.error('Error loading messages:', err);
        }
    }

    loadMessages();
    setInterval(() => { if (isPolling) loadMessages(); }, 3000);

    // Voice playback
    let currentAudio = null;
    let currentPlayBtn = null;

    function toggleVoicePlay(btn, audioUrl) {
        const container = btn.closest('.voice-message');
        const playIcon = btn.querySelector('.play-icon');
        const pauseIcon = btn.querySelector('.pause-icon');
        const progressBar = container.querySelector('.voice-progress');
        const currentTimeEl = container.querySelector('.voice-current-time');
        
        if (currentAudio && currentPlayBtn === btn) {
            if (currentAudio.paused) {
                currentAudio.play();
                playIcon.classList.add('hidden');
                pauseIcon.classList.remove('hidden');
            } else {
                currentAudio.pause();
                playIcon.classList.remove('hidden');
                pauseIcon.classList.add('hidden');
            }
            return;
        }
        
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
            if (currentPlayBtn) {
                currentPlayBtn.querySelector('.play-icon').classList.remove('hidden');
                currentPlayBtn.querySelector('.pause-icon').classList.add('hidden');
                currentPlayBtn.closest('.voice-message').querySelector('.voice-progress').style.width = '0%';
            }
        }
        
        currentAudio = new Audio(audioUrl);
        currentPlayBtn = btn;
        
        currentAudio.addEventListener('timeupdate', () => {
            const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
            progressBar.style.width = `${progress}%`;
            const mins = Math.floor(currentAudio.currentTime / 60);
            const secs = Math.floor(currentAudio.currentTime % 60);
            currentTimeEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        });
        
        currentAudio.addEventListener('ended', () => {
            playIcon.classList.remove('hidden');
            pauseIcon.classList.add('hidden');
            progressBar.style.width = '0%';
            currentTimeEl.textContent = '0:00';
            currentAudio = null;
            currentPlayBtn = null;
        });
        
        currentAudio.play();
        playIcon.classList.add('hidden');
        pauseIcon.classList.remove('hidden');
    }

    function seekVoice(event, container) {
        if (!currentAudio || !currentPlayBtn) return;
        const voiceMessage = container.closest('.voice-message');
        if (!voiceMessage.contains(currentPlayBtn)) return;
        const rect = container.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const percentage = x / rect.width;
        currentAudio.currentTime = percentage * currentAudio.duration;
    }
    </script>
</body>
</html>
