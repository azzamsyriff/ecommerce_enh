<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false, 'message'=>'Invalid request']); exit(); }
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit(); }

$user_id = $_SESSION['user_id'];
$address = trim($data['address'] ?? '');
$notes = trim($data['notes'] ?? ''); // <--- AMBIL DATA NOTES
$payment_method = $data['payment_method'] ?? 'bank_transfer';
$total_amount = $data['total_amount'] ?? 0;

if (empty($address)) { echo json_encode(['success'=>false, 'message'=>'Alamat kosong!']); exit(); }

try {
    $conn->beginTransaction();
    
    // 1. Ambil Keranjang
    $stmt = $conn->prepare("SELECT c.*, p.price, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = :uid");
    $stmt->execute(['uid'=>$user_id]); $cart = $stmt->fetchAll();
    if(empty($cart)) throw new Exception("Keranjang kosong.");

    // 2. Validasi Stok & Hitung Ulang
    $calc = 0;
    foreach($cart as $i) {
        if($i['stock'] < $i['quantity']) throw new Exception("Stok {$i['name']} tidak cukup.");
        $calc += $i['price'] * $i['quantity'];
    }
    if(abs($calc + 15000 - $total_amount) > 1) throw new Exception("Total tidak valid.");

    // 3. Insert Transaksi (Termasuk NOTES) <--- TAMBAHKAN KOLOM notes
    $code = 'TRX-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6));
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, transaction_code, total_amount, status, payment_method, shipping_address, phone, notes) VALUES (:uid, :code, :total, 'pending', :method, :addr, :phone, :notes)");
    $stmt->execute([
        'uid'=>$user_id, 'code'=>$code, 'total'=>$total_amount, 'method'=>$payment_method, 
        'addr'=>$address, 'phone'=>$_SESSION['phone'] ?? '', 'notes'=>$notes // <--- SIMPAN NOTES
    ]);
    $trans_id = $conn->lastInsertId();

    // 4. Insert Details & Kurangi Stok
    foreach($cart as $i) {
        $sub = $i['price'] * $i['quantity'];
        $stmt = $conn->prepare("INSERT INTO transaction_details (transaction_id, product_id, quantity, price, subtotal) VALUES (:tid, :pid, :q, :p, :s)");
        $stmt->execute(['tid'=>$trans_id, 'pid'=>$i['product_id'], 'q'=>$i['quantity'], 'p'=>$i['price'], 's'=>$sub]);
        $stmt = $conn->prepare("UPDATE products SET stock = stock - :q WHERE id = :pid");
        $stmt->execute(['q'=>$i['quantity'], 'pid'=>$i['product_id']]);
    }

    // 5. Kosongkan Keranjang
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :uid");
    $stmt->execute(['uid'=>$user_id]);

    $conn->commit();
    echo json_encode(['success'=>true, 'transaction_code'=>$code]);
} catch(Exception $e) {
    $conn->rollBack();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>