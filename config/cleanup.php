<?php
/**
 * Cleanup Functions
 * Untuk membersihkan data expired dan menjaga performa database
 */

/**
 * Hapus tokens yang sudah expired
 * Dipanggil secara periodik atau saat generate token baru
 */
function cleanupExpiredTokens($conn, $olderThanMinutes = 60) {
    $stmt = mysqli_prepare($conn, 
        "DELETE FROM tokens WHERE expired_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    mysqli_stmt_bind_param($stmt, "i", $olderThanMinutes);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmt);
    
    return $deleted;
}

/**
 * Cleanup dengan probability (tidak setiap request)
 * Probability 1% = cleanup dijalankan 1 dari 100 request
 */
function maybeCleanup($conn, $probability = 1) {
    if (rand(1, 100) <= $probability) {
        cleanupExpiredTokens($conn);
    }
}

/**
 * Get statistics untuk monitoring
 */
function getCleanupStats($conn) {
    $stats = [];
    
    // Total tokens
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM tokens");
    $stats['total_tokens'] = mysqli_fetch_assoc($result)['total'];
    
    // Expired tokens
    $result = mysqli_query($conn, "SELECT COUNT(*) as expired FROM tokens WHERE expired_at < NOW()");
    $stats['expired_tokens'] = mysqli_fetch_assoc($result)['expired'];
    
    // Active tokens
    $stats['active_tokens'] = $stats['total_tokens'] - $stats['expired_tokens'];
    
    return $stats;
}
?>
