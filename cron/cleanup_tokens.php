#!/usr/bin/env php
<?php
/**
 * Cron Job untuk cleanup expired tokens
 * Jalankan setiap jam: 0 * * * * php /path/to/absenhima/cron/cleanup_tokens.php
 * 
 * Atau manual: php cron/cleanup_tokens.php
 */

// Hanya bisa dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    die("Script ini hanya bisa dijalankan dari command line.\n");
}

require_once dirname(__FILE__) . '/../config/koneksi.php';
require_once dirname(__FILE__) . '/../config/cleanup.php';

echo "=== Token Cleanup Script ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Get stats sebelum cleanup
$before = getCleanupStats($conn);
echo "Before cleanup:\n";
echo "  Total tokens: {$before['total_tokens']}\n";
echo "  Expired: {$before['expired_tokens']}\n";
echo "  Active: {$before['active_tokens']}\n\n";

// Jalankan cleanup (hapus tokens yang expired > 1 jam)
$deleted = cleanupExpiredTokens($conn, 60);
echo "Deleted: $deleted expired tokens\n\n";

// Get stats sesudah cleanup
$after = getCleanupStats($conn);
echo "After cleanup:\n";
echo "  Total tokens: {$after['total_tokens']}\n";
echo "  Expired: {$after['expired_tokens']}\n";
echo "  Active: {$after['active_tokens']}\n\n";

echo "=== Cleanup Complete ===\n";
?>
