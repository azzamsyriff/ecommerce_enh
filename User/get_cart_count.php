<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['count' => 0]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $result = $stmt->fetch();
    $count = $result['total'] ?? 0;
    
    echo json_encode(['count' => (int)$count]);
} catch(PDOException $e) {
    echo json_encode(['count' => 0]);
}
?>