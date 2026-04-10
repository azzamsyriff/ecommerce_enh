<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Password tidak cocok!";
        header('Location: register.php');
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter!";
        header('Location: register.php');
        exit();
    }
    
    try {
        // Cek email sudah terdaftar
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email sudah terdaftar!";
            header('Location: register.php');
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user baru (default role: user)
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (:full_name, :email, :phone, :password, 'user')");
        $stmt->execute([
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'password' => $hashed_password
        ]);
        
        $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
        header('Location: login.php');
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: register.php');
        exit();
    }
} else {
    header('Location: register.php');
    exit();
}
?>