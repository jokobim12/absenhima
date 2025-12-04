<?php
/**
 * Simple Rate Limiting menggunakan Session
 * Mencegah spam dan abuse pada API endpoints
 */

/**
 * Check rate limit
 * @param string $key - Identifier untuk rate limit (misal: 'api_token', 'login')
 * @param int $maxRequests - Maksimal request dalam window
 * @param int $windowSeconds - Window waktu dalam detik
 * @return bool - true jika masih dalam limit, false jika exceeded
 */
function checkRateLimit($key, $maxRequests = 60, $windowSeconds = 60) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $now = time();
    $cacheKey = '_ratelimit_' . $key;
    
    // Initialize jika belum ada
    if (!isset($_SESSION[$cacheKey])) {
        $_SESSION[$cacheKey] = [
            'count' => 0,
            'window_start' => $now
        ];
    }
    
    $data = &$_SESSION[$cacheKey];
    
    // Reset window jika sudah expired
    if (($now - $data['window_start']) > $windowSeconds) {
        $data['count'] = 0;
        $data['window_start'] = $now;
    }
    
    // Increment counter
    $data['count']++;
    
    // Check limit
    return $data['count'] <= $maxRequests;
}

/**
 * Rate limit dengan response JSON untuk API
 */
function rateLimitOrDie($key, $maxRequests = 60, $windowSeconds = 60) {
    if (!checkRateLimit($key, $maxRequests, $windowSeconds)) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $windowSeconds);
        echo json_encode([
            'error' => 'Too many requests',
            'message' => 'Silakan tunggu beberapa saat sebelum mencoba lagi.',
            'retry_after' => $windowSeconds
        ]);
        exit;
    }
}

/**
 * Rate limit untuk halaman HTML
 */
function rateLimitPageOrDie($key, $maxRequests = 30, $windowSeconds = 60) {
    if (!checkRateLimit($key, $maxRequests, $windowSeconds)) {
        http_response_code(429);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Terlalu Banyak Request</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md text-center">
                <div class="text-6xl mb-4">⏳</div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Terlalu Banyak Request</h1>
                <p class="text-gray-600 mb-6">Silakan tunggu beberapa saat sebelum mencoba lagi.</p>
                <a href="javascript:location.reload()" class="inline-block px-6 py-3 bg-gray-800 text-white rounded-lg hover:bg-gray-700">
                    Coba Lagi
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
