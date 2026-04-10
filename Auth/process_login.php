<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            
            // 🔒 TAMBAHKAN INI: Cek status akun sebelum mengizinkan login
            if ($user['status'] !== 'active') {
                $_SESSION['error'] = "Akun Anda telah dinonaktifkan. Hubungi administrator untuk informasi lebih lanjut.";
                header('Location: login.php');
                exit();
            }
            
            // Set session variables (hanya jika status active)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['phone'] = $user['phone'];
            
            // Redirect berdasarkan role
            switch($user['role']) {
                case 'admin':
                    header('Location: ../Admin/admin_dashboard.php');
                    break;
                case 'petugas':
                    header('Location: ../Petugas/petugas_dashboard.php');
                    break;
                default:
                    header('Location: ../User/user_dashboard.php');
            }
            exit();
            
        } else {
            // Password salah atau user tidak ditemukan
            $_SESSION['error'] = "Email atau password salah!";
            header('Location: login.php');
            exit();
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: login.php');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>