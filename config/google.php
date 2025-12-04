<?php
/**
 * Konfigurasi Google OAuth 2.0 - Dari Database Settings
 */

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

define('GOOGLE_CLIENT_ID', getSetting('google_client_id', ''));
define('GOOGLE_CLIENT_SECRET', getSetting('google_client_secret', ''));
define('GOOGLE_REDIRECT_URI', getSetting('google_redirect_uri', ''));

// Domain email Politala yang diizinkan
define('ALLOWED_DOMAINS', ['politala.ac.id', 'mhs.politala.ac.id']);

// Pattern untuk extract NIM dari email
define('NIM_PATTERN', '/^(\d+)@/');

// Default values
define('DEFAULT_KELAS', '-');
define('DEFAULT_SEMESTER', '1');
