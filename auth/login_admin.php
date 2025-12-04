<?php
session_start();
include "../config/koneksi.php";
include "../config/helpers.php";

$error = "";

if(isset($_POST['login'])){
    verifyCsrfOrDie();
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Prepared statement untuk mencegah SQL Injection
    $stmt = mysqli_prepare($conn, "SELECT * FROM admin WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $d = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if($d && password_verify($password, $d['password'])){
        // Regenerate session ID untuk mencegah session fixation
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $d['id'];
        
        // Auto-link: cek apakah ada user dengan email sama dengan admin
        // Pertama, cek apakah kolom email ada di tabel admin
        $checkEmail = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'email'");
        if (mysqli_num_rows($checkEmail) == 0) {
            mysqli_query($conn, "ALTER TABLE admin ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER username");
        }
        
        // Ambil email admin (jika ada)
        $adminEmail = $d['email'] ?? null;
        
        // Jika admin punya email, cari user yang cocok
        if ($adminEmail) {
            $stmtUser = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmtUser, "s", $adminEmail);
            mysqli_stmt_execute($stmtUser);
            $userResult = mysqli_stmt_get_result($stmtUser);
            $linkedUser = mysqli_fetch_assoc($userResult);
            mysqli_stmt_close($stmtUser);
            
            if ($linkedUser) {
                $_SESSION['user_id'] = $linkedUser['id'];
            }
        }
        
        header("Location: ../admin/index.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Absensi HIMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900 flex items-center justify-center p-4">

    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Login Admin</h2>
            <p class="text-gray-500 text-sm mt-1">Panel Administrator</p>
        </div>

        <?php if($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="username" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 outline-none transition">
            </div>
            <button type="submit" name="login" 
                class="w-full py-3 bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-lg transition duration-200">
                Login
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="../index.php" class="text-gray-500 hover:text-gray-700 text-sm inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali ke Beranda
            </a>
        </div>
    </div>

</body>
</html>
