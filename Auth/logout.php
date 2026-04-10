<?php
session_start();

// Hapus semua session data
session_unset();
session_destroy();

// Redirect ke login page
header('Location: login.php');
exit();
?>