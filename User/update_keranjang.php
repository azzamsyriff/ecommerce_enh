<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$product_id = (int)($data['product_id'] ?? 0);
$quantity = (int)($data['quantity'] ?? 1);

$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch($action) {
        case 'add':
            // Cek stok produk
            $stmt = $conn->prepare("SELECT stock FROM products WHERE id = :id AND status = 'active'");
            $stmt->execute(['id' => $product_id]);
            $product = $stmt->fetch();
            
            if(!$product) {
                $response = ['success' => false, 'message' => 'Produk tidak ditemukan'];
                break;
            }
            
            if($product['stock'] < $quantity) {
                $response = ['success' => false, 'message' => 'Stok tidak mencukupi'];
                break;
            }
            
            // Cek apakah produk sudah ada di keranjang
            $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id");
            $stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);
            $cart_item = $stmt->fetch();
            
            if($cart_item) {
                // Update quantity jika sudah ada
                $new_quantity = $cart_item['quantity'] + $quantity;
                if($new_quantity > $product['stock']) {
                    $response = ['success' => false, 'message' => 'Stok tidak mencukupi untuk jumlah yang diminta'];
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE cart SET quantity = :quantity, updated_at = NOW() WHERE id = :id");
                $stmt->execute(['quantity' => $new_quantity, 'id' => $cart_item['id']]);
            } else {
                // Insert baru jika belum ada
                $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)");
                $stmt->execute([
                    'user_id' => $user_id,
                    'product_id' => $product_id,
                    'quantity' => $quantity
                ]);
            }
            
            $response = ['success' => true, 'message' => 'Produk berhasil ditambahkan ke keranjang'];
            break;
            
        case 'update':
            // Cek stok
            $stmt = $conn->prepare("SELECT stock FROM products WHERE id = :id AND status = 'active'");
            $stmt->execute(['id' => $product_id]);
            $product = $stmt->fetch();
            
            if(!$product || $quantity > $product['stock'] || $quantity <= 0) {
                $response = ['success' => false, 'message' => 'Jumlah tidak valid atau melebihi stok'];
                break;
            }
            
            // Update keranjang
            $stmt = $conn->prepare("UPDATE cart SET quantity = :quantity, updated_at = NOW() WHERE user_id = :user_id AND product_id = :product_id");
            $stmt->execute([
                'quantity' => $quantity,
                'user_id' => $user_id,
                'product_id' => $product_id
            ]);
            
            $response = ['success' => true, 'message' => 'Keranjang berhasil diperbarui'];
            break;
            
        case 'remove':
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id AND product_id = :product_id");
            $stmt->execute([
                'user_id' => $user_id,
                'product_id' => $product_id
            ]);
            
            $response = ['success' => true, 'message' => 'Produk berhasil dihapus dari keranjang'];
            break;
            
        case 'clear':
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            $response = ['success' => true, 'message' => 'Keranjang berhasil dikosongkan'];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Aksi tidak valid'];
    }
    
} catch(PDOException $e) {
    $response = ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
}

echo json_encode($response);
?>