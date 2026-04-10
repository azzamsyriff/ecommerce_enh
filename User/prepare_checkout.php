<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = (int)($data['product_id'] ?? 0);
$quantity = (int)($data['quantity'] ?? 1);

if($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit();
}

try {
    // Clear cart dulu
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    
    // Tambah produk ke keranjang
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'product_id' => $product_id,
        'quantity' => $quantity
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Checkout dipersiapkan']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>