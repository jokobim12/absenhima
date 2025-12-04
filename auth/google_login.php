<?php
/**
 * Redirect user ke Google OAuth
 */
// Set session cookie parameters for better compatibility
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once "../config/google.php";

// Generate state untuk keamanan CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Buat redirect_uri dinamis berdasarkan URL akses saat ini
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirect_uri = $protocol . '://' . $host . '/absenhima/auth/google_callback.php';

// Build Google OAuth URL
$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account' // Selalu tampilkan pilihan akun
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $authUrl);
exit;
