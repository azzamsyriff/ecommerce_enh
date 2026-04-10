<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = $data['id'];
    
    // Update notification status to read
    // (Implementasi sesuai dengan database Anda)
    
    echo json_encode(['success' => true]);
}
?>