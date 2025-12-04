<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login_user.php");
    exit;
}
?>
