<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../Auth/login.php');
    exit();
}

$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($transaction_id <= 0) {
    $_SESSION['error'] = "Transaksi tidak ditemukan!";
    header('Location: riwayat_transaksi.php');
    exit();
}

// 🔒 Handle: User Selesaikan Pesanan
if(isset($_POST['complete_order'])) {
    $stmt = $conn->prepare("SELECT status FROM transactions WHERE id = :id AND user_id = :uid");
    $stmt->execute(['id' => $transaction_id, 'uid' => $_SESSION['user_id']]);
    $current_status = $stmt->fetchColumn();

    if($current_status === 'delivered') {
        try {
            $stmt = $conn->prepare("UPDATE transactions SET is_completed = 1, updated_at = NOW() WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $transaction_id, 'uid' => $_SESSION['user_id']]);
            $_SESSION['success'] = "✅ Pesanan telah diselesaikan. Terima kasih!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal menyelesaikan pesanan: " . $e->getMessage();
        }
        header('Location: detail_transaksi.php?id='.$transaction_id);
        exit();
    }
}

// 🔒 Handle: User Batalkan Pesanan (Sebelum dikirim/shipped)
if(isset($_POST['cancel_order'])) {
    $reason = trim($_POST['cancel_reason'] ?? '');
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT status FROM transactions WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $transaction_id, 'uid' => $_SESSION['user_id']]);
        $current_status = $stmt->fetchColumn();

        if($current_status === 'pending' || $current_status === 'paid') {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), '\n[Batal User: ', :reason, ']'), updated_at = NOW() WHERE id = :id AND user_id = :uid");
            $stmt->execute(['reason' => $reason, 'id' => $transaction_id, 'uid' => $_SESSION['user_id']]);
            
            // Jika status sebelumnya paid, kembalikan stok
            if($current_status === 'paid') {
                $stmt = $conn->prepare("SELECT td.product_id, td.quantity FROM transaction_details td WHERE td.transaction_id = :tid");
                $stmt->execute(['tid' => $transaction_id]);
                $items = $stmt->fetchAll();
                foreach($items as $item) {
                    $conn->prepare("UPDATE products SET stock = stock + :qty WHERE id = :pid")->execute(['qty' => $item['quantity'], 'pid' => $item['product_id']]);
                }
            }
            $conn->commit();
            $_SESSION['success'] = "✅ Pesanan berhasil dibatalkan.";
        } else {
            $_SESSION['error'] = "Pesanan tidak dapat dibatalkan karena status sudah dikirim.";
        }
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal membatalkan pesanan: " . $e->getMessage();
    }
    header('Location: detail_transaksi.php?id='.$transaction_id);
    exit();
}

// 🔒 Handle: User Ajukan Pengembalian Barang (Setelah delivered)
if(isset($_POST['request_return'])) {
    $reason = trim($_POST['return_reason'] ?? '');
    if(empty($reason)) {
        $_SESSION['error'] = "Alasan pengembalian wajib diisi!";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE transactions SET return_status = 'requested', return_reason = :reason, updated_at = NOW() WHERE id = :id AND user_id = :uid AND (return_status IS NULL OR return_status = 'none') AND status = 'delivered'");
            $stmt->execute(['reason' => $reason, 'id' => $transaction_id, 'uid' => $_SESSION['user_id']]);
            $_SESSION['success'] = "Pengajuan pengembalian berhasil dikirim! Menunggu konfirmasi admin.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Gagal mengajukan: " . $e->getMessage();
        }
    }
    header('Location: detail_transaksi.php?id='.$transaction_id);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT t.*, u.full_name as user_name, u.email, u.phone FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = :id AND t.user_id = :user_id");
    $stmt->execute(['id' => $transaction_id, 'user_id' => $_SESSION['user_id']]);
    $transaction = $stmt->fetch();
    
    if(!$transaction) {
        $_SESSION['error'] = "Transaksi tidak ditemukan atau tidak sesuai dengan akun Anda!";
        header('Location: riwayat_transaksi.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT td.*, p.name as product_name, p.image, p.price as product_price FROM transaction_details td JOIN products p ON td.product_id = p.id WHERE td.transaction_id = :transaction_id");
    $stmt->execute(['transaction_id' => $transaction_id]);
    $transaction_items = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_user = $stmt->fetch();

    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $cart_result = $stmt->fetch();
    $cart_count = $cart_result['total'] ?? 0;
} catch(PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: riwayat_transaksi.php');
    exit();
}

// 🔒 LOGIC STATUS UNTUK UI & INVOICE
$inv_rs = $transaction['return_status'] ?? 'none';
$is_completed = $transaction['is_completed'] ?? 0;

$inv_status_class = '';
$inv_status_text = '';

if($inv_rs === 'approved') {
    $inv_status_text = 'Pembatalan/PG Disetujui';
    $inv_status_class = 'return_approved';
} elseif($inv_rs === 'requested') {
    $inv_status_text = 'Pengembalian Diajukan';
    $inv_status_class = 'return_requested';
} elseif($inv_rs === 'rejected') {
    $inv_status_text = 'Pengembalian Ditolak';
    $inv_status_class = 'return_rejected';
} elseif($transaction['status'] === 'cancelled') {
    $inv_status_text = 'Dibatalkan';
    $inv_status_class = 'cancelled';
} else {
    $inv_status_text = ucfirst($transaction['status']);
    $inv_status_class = $transaction['status'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi #<?php echo htmlspecialchars($transaction['transaction_code']); ?> - E-Commerce Platform</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #3751fe; --primary-dark: #2d43d9; --secondary: #667eea; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --dark: #1f2937; --gray: #6b7280; --light-gray: #f3f4f6; --white: #ffffff; --sidebar-bg: #1e293b; --sidebar-hover: #334155; --card-bg: #ffffff; --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fb; display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, var(--sidebar-bg) 0%, #1a2234 100%); color: var(--white); position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: center; }
        .logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 15px; }
        .logo-icon { font-size: 32px; color: var(--primary); }
        .logo-text { font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo-subtext { font-size: 14px; color: rgba(255, 255, 255, 0.6); margin-top: 5px; }
        .sidebar-menu { padding: 20px 0; }
        .menu-section { margin-bottom: 20px; }
        .menu-title { padding: 15px 25px 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255, 255, 255, 0.5); font-weight: 600; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255, 255, 255, 0.85); text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover { background: var(--sidebar-hover); color: var(--white); border-left-color: var(--primary); }
        .menu-item.active { background: rgba(55, 81, 254, 0.2); color: var(--white); border-left-color: var(--primary); }
        .menu-icon { width: 24px; margin-right: 15px; font-size: 18px; }
        .menu-text { flex: 1; font-size: 15px; font-weight: 500; }
        .menu-badge { background: var(--primary); color: var(--white); padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .main-content { flex: 1; margin-left: 280px; transition: all 0.3s; }
        .header { background: var(--white); padding: 20px 40px; box-shadow: var(--shadow); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .search-bar { position: relative; }
        .search-bar input { padding: 12px 20px 12px 45px; border: 2px solid var(--light-gray); border-radius: 10px; width: 300px; font-size: 14px; transition: all 0.3s; }
        .search-bar input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--gray); font-size: 16px; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .cart-icon { position: relative; cursor: pointer; }
        .cart-icon i { font-size: 20px; color: var(--gray); }
        .cart-badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: var(--white); width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; }
        .user-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: var(--white); font-weight: 600; font-size: 16px; overflow: hidden; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: var(--dark); }
        .user-role { font-size: 12px; color: var(--gray); }
        .dashboard-content { padding: 30px 40px; }
        .page-title { font-size: 28px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .page-subtitle { color: var(--gray); font-size: 16px; margin-bottom: 30px; }
        .transaction-detail { background: white; border-radius: 15px; box-shadow: var(--shadow); padding: 30px; margin-bottom: 30px; }
        .transaction-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid var(--light-gray); }
        .transaction-info { flex: 1; }
        .transaction-code { font-size: 24px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .transaction-status { display: inline-block; padding: 6px 15px; border-radius: 8px; font-weight: 600; margin-bottom: 10px; }
        .transaction-status.pending { background: #fef3c7; color: #92400e; }
        .transaction-status.paid { background: #d1fae5; color: #065f46; }
        .transaction-status.shipped { background: #dbeafe; color: #1e40af; }
        .transaction-status.delivered { background: #dcfce7; color: #166534; }
        .transaction-status.cancelled { background: #fee2e2; color: #991b1b; }
        .transaction-status.return_requested { background: #fef3c7; color: #92400e; border: 2px solid #f59e0b; }
        .transaction-status.return_approved { background: #dcfce7; color: #166534; border: 2px solid #10b981; }
        .transaction-status.return_rejected { background: #fee2e2; color: #991b1b; border: 2px solid #ef4444; }
        .transaction-date { color: var(--gray); font-size: 14px; margin-bottom: 15px; }
        .transaction-total { font-size: 28px; font-weight: 700; color: var(--success); }
        .transaction-actions { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 12px 20px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2d43d9; transform: translateY(-2px); }
        .btn-secondary { background: #6b7280; } .btn-secondary:hover { background: #4b5563; }
        .btn-success { background: #10b981; } .btn-success:hover { background: #0da271; }
        .btn-contact { background: #f59e0b; } .btn-contact:hover { background: #d97706; }
        .btn-warning { background: #f59e0b; } .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; } .btn-danger:hover { background: #dc2626; }
        .btn:disabled { background: #9ca3af; cursor: not-allowed; transform: none; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 20px; font-weight: 600; color: var(--dark); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--light-gray); }
        .shipping-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-card { background: var(--light-gray); border-radius: 10px; padding: 20px; }
        .info-label { font-size: 13px; color: var(--gray); margin-bottom: 8px; font-weight: 500; }
        .info-value { font-size: 16px; font-weight: 600; color: var(--dark); }
        .products-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .product-item { display: flex; gap: 15px; padding: 20px; background: var(--light-gray); border-radius: 10px; }
        .product-image { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 24px; }
        .product-details { flex: 1; }
        .product-name { font-size: 16px; font-weight: 600; color: var(--dark); margin-bottom: 8px; }
        .product-price { font-size: 14px; color: var(--gray); margin-bottom: 10px; }
        .product-quantity { display: flex; align-items: center; gap: 15px; }
        .quantity-label { color: var(--gray); font-size: 13px; }
        .quantity-value { font-weight: 600; color: var(--dark); }
        .product-subtotal { font-weight: 600; color: var(--success); font-size: 16px; }
        .payment-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: var(--light-gray); }
        .timeline-item { position: relative; margin-bottom: 25px; }
        .timeline-item::before { content: ''; position: absolute; left: -28px; top: 5px; width: 20px; height: 20px; border-radius: 50%; background: var(--primary); border: 3px solid var(--white); z-index: 1; }
        .timeline-content { padding: 15px; background: var(--light-gray); border-radius: 10px; }
        .timeline-title { font-weight: 600; color: var(--dark); margin-bottom: 8px; }
        .timeline-date { font-size: 12px; color: var(--gray); }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--white); border-radius: 15px; width: 90%; max-width: 500px; box-shadow: var(--shadow-lg); animation: modalSlideIn 0.3s ease-out; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-50px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 25px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: var(--white); border-radius: 15px 15px 0 0; text-align: center; position: relative; }
        .modal-title { font-size: 24px; font-weight: 700; }
        .modal-close { position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.2); border: none; width: 30px; height: 30px; border-radius: 50%; color: var(--white); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .modal-close:hover { background: rgba(255, 255, 255, 0.3); transform: rotate(90deg); }
        .modal-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; color: #374151; margin-bottom: 8px; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; }
        .return-badge { display: inline-block; padding: 5px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-top: 10px; }
        .return-badge.requested { background: #fef3c7; color: #92400e; }
        .return-badge.approved { background: #dcfce7; color: #166534; }
        .return-badge.rejected { background: #fee2e2; color: #991b1b; }
        @media print {
            .sidebar, .header, .transaction-actions, .btn, .menu-section, .search-bar, .header-right, .cart-icon, .user-profile { display: none !important; }
            body { background: white; min-height: auto; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .dashboard-content { padding: 20px !important; }
            .transaction-detail { box-shadow: none !important; border: 1px solid #e5e7eb; }
            .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 20px; }
            .invoice-title { font-size: 28px; font-weight: 700; color: var(--primary); margin-bottom: 10px; }
            .invoice-subtitle { font-size: 16px; color: var(--gray); }
            .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
            .invoice-section { margin-bottom: 25px; }
            .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 12px; text-align: left; }
            .invoice-table th { background: #f9fafb; font-weight: 600; }
            .total-row { font-weight: 700; font-size: 18px; }
            .footer-note { text-align: center; margin-top: 40px; font-style: italic; color: var(--gray); }
            .page-break { page-break-after: always; }
        }
        @media (max-width: 1024px) { .sidebar { width: 240px; } .main-content { margin-left: 240px; } .search-bar input { width: 200px; } }
        @media (max-width: 768px) {
            .sidebar { width: 70px; overflow: hidden; }
            .sidebar:hover { width: 280px; overflow: visible; }
            .logo-text, .logo-subtext, .menu-text, .menu-title, .menu-badge { display: none; }
            .sidebar:hover .logo-text, .sidebar:hover .logo-subtext, .sidebar:hover .menu-text, .sidebar:hover .menu-title, .sidebar:hover .menu-badge { display: block; }
            .menu-icon { margin-right: 0; }
            .main-content { margin-left: 70px; }
            .search-bar { display: none; }
            .transaction-header { flex-direction: column; gap: 20px; text-align: center; }
            .transaction-actions { flex-direction: column; }
            .shipping-info, .products-list, .payment-info { grid-template-columns: 1fr; }
            .product-item { flex-direction: column; }
            .dashboard-content { padding: 20px; }
            .btn-group { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><div class="logo"><i class="fas fa-shopping-cart logo-icon"></i><div><div class="logo-text">E-Commerce</div><div class="logo-subtext">Platform</div></div></div></div>
        <div class="sidebar-menu">
            <div class="menu-section"><div class="menu-title">Main Menu</div><a href="user_dashboard.php" class="menu-item"><i class="fas fa-home menu-icon"></i><span class="menu-text">Dashboard</span></a></div>
            <div class="menu-section"><div class="menu-title">Shopping</div><a href="user_dashboard.php" class="menu-item"><i class="fas fa-shopping-bag menu-icon"></i><span class="menu-text">Produk</span></a><a href="keranjang.php" class="menu-item"><i class="fas fa-shopping-cart menu-icon"></i><span class="menu-text">Keranjang</span><?php if($cart_count > 0): ?><span class="menu-badge"><?php echo $cart_count; ?></span><?php endif; ?></a><a href="riwayat_transaksi.php" class="menu-item active"><i class="fas fa-history menu-icon"></i><span class="menu-text">Riwayat Transaksi</span></a></div>
            <div class="menu-section"><div class="menu-title">Settings</div><a href="profile_user.php" class="menu-item"><i class="fas fa-user menu-icon"></i><span class="menu-text">Profile</span></a><a href="../Auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt menu-icon"></i><span class="menu-text">Logout</span></a></div>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="header-left"><a href="riwayat_transaksi.php" class="btn btn-secondary" style="padding: 8px 15px; font-size: 14px;"><i class="fas fa-arrow-left"></i> Kembali ke Riwayat</a></div>
            <div class="header-right">
                <div class="cart-icon" onclick="window.location.href='keranjang.php'"><i class="fas fa-shopping-cart"></i><?php if($cart_count > 0): ?><span class="cart-badge"><?php echo $cart_count; ?></span><?php endif; ?></div>
                <div class="user-profile" onclick="window.location.href='profile_user.php'"><div class="avatar"><?php if(isset($current_user['profile_picture']) && $current_user['profile_picture'] && file_exists('../uploads/profiles/' . $current_user['profile_picture'])): ?><img src="../uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" alt="Profile"><?php else: ?><?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?><?php endif; ?></div><div class="user-info"><div class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div><div class="user-role"><?php echo ucfirst($current_user['role']); ?></div></div></div>
            </div>
        </div>

        <div class="dashboard-content">
            <h1 class="page-title">Detail Transaksi</h1>
            <p class="page-subtitle">Informasi lengkap tentang transaksi Anda</p>
            
            <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success" style="padding:15px;border-radius:8px;margin-bottom:20px;background:#d1fae5;color:#065f46;"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?><div class="alert alert-error" style="padding:15px;border-radius:8px;margin-bottom:20px;background:#fee2e2;color:#991b1b;"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

            <div class="transaction-detail">
                <div class="transaction-header">
                    <div class="transaction-info">
                        <div class="transaction-code">#<?php echo htmlspecialchars($transaction['transaction_code']); ?></div>
                        <div class="transaction-status <?php echo $inv_status_class; ?>"><?php echo $inv_status_text; ?></div>
                        <div class="transaction-date"><?php echo date('d M Y, H:i', strtotime($transaction['created_at'])); ?></div>
                        <div class="transaction-total">Total: Rp<?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?></div>
                        
                        <?php if(!empty($inv_rs) && $inv_rs !== 'none'): ?>
                            <div class="return-badge <?php echo htmlspecialchars($inv_rs); ?>">
                                <i class="fas fa-exchange-alt"></i>
                                <?php
                                if($inv_rs === 'requested') echo 'Menunggu Persetujuan Admin';
                                elseif($inv_rs === 'approved') echo 'Pengembalian Disetujui';
                                elseif($inv_rs === 'rejected') echo 'Pengembalian Ditolak';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="transaction-actions">
                        <button class="btn btn-success" onclick="printInvoice()"><i class="fas fa-print"></i> Cetak Invoice</button>
                        <button class="btn btn-contact" onclick="openModal('contactModal')"><i class="fas fa-envelope"></i> Hubungi Penjual</button>
                        
                        <!-- 🔒 Tombol Selesaikan Pesanan (hanya jika delivered & belum selesai) -->
                        <?php if($transaction['status'] === 'delivered' && $is_completed == 0): ?>
                            <button class="btn btn-success" onclick="confirmComplete()"><i class="fas fa-check-circle"></i> Selesaikan Pesanan</button>
                        <?php endif; ?>

                        <!-- 🔒 Tombol Ajukan Pengembalian (hanya jika delivered, belum selesai, & belum request) -->
                        <?php if($transaction['status'] === 'delivered' && $is_completed == 0 && ($inv_rs === 'none' || empty($inv_rs))): ?>
                            <button class="btn btn-warning" onclick="openModal('returnModal')"><i class="fas fa-undo"></i> Ajukan Pengembalian</button>
                        <?php endif; ?>
                        
                        <!-- 🔒 Tombol Batalkan Pesanan (hanya jika pending/paid, BELUM shipped) -->
                        <?php if($transaction['status'] === 'pending' || $transaction['status'] === 'paid'): ?>
                            <button class="btn btn-danger" onclick="openModal('cancelModal')"><i class="fas fa-times-circle"></i> Batalkan Pesanan</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Informasi Pengiriman</h2>
                    <div class="shipping-info">
                        <div class="info-card"><div class="info-label">Penerima</div><div class="info-value"><?php echo htmlspecialchars($transaction['user_name'] ?? '-'); ?></div></div>
                        <div class="info-card"><div class="info-label">Nomor Telepon</div><div class="info-value"><?php echo htmlspecialchars($transaction['phone'] ?? '-'); ?></div></div>
                        <div class="info-card"><div class="info-label">Email</div><div class="info-value"><?php echo htmlspecialchars($transaction['email'] ?? '-'); ?></div></div>
                    </div>
                    <div class="info-card" style="margin-top: 20px;"><div class="info-label">Alamat Pengiriman</div><div class="info-value"><?php echo nl2br(htmlspecialchars($transaction['shipping_address'] ?? '-')); ?></div></div>
                    
                    <?php if(!empty($transaction['courier']) || !empty($transaction['service_type'])): ?>
                        <div class="info-card" style="margin-top: 20px; border-left: 4px solid var(--primary);">
                            <div class="info-label"><i class="fas fa-shipping-fast" style="color: var(--primary); margin-right: 5px;"></i>Opsi Pengiriman</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                                <div><div style="font-size: 12px; color: var(--gray); margin-bottom: 3px;">Jasa Kurir</div><div class="info-value" style="font-size: 15px;"><?php echo htmlspecialchars(ucfirst($transaction['courier'] ?? '-')); ?></div></div>
                                <div><div style="font-size: 12px; color: var(--gray); margin-bottom: 3px;">Tipe Pengiriman</div><div class="info-value" style="font-size: 15px;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $transaction['service_type'] ?? '-'))); ?></div></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($transaction['return_reason'])): ?>
                        <div class="info-card" style="margin-top: 20px; border-left: 4px solid var(--danger); background: #fef2f2;">
                            <div class="info-label"><i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-right: 5px;"></i>Alasan Pengembalian</div>
                            <div class="info-value" style="font-style: italic; color: #991b1b;"><?php echo nl2br(htmlspecialchars($transaction['return_reason'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($transaction['notes'])): ?>
                        <div class="info-card" style="margin-top: 20px; border-left: 4px solid var(--warning);">
                            <div class="info-label"><i class="fas fa-sticky-note" style="color: var(--warning); margin-right: 5px;"></i>Catatan Pesanan</div>
                            <div class="info-value" style="font-style: italic; color: #4b5563;"><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2 class="section-title">Daftar Produk</h2>
                    <div class="products-list">
                        <?php foreach($transaction_items as $item): ?>
                            <div class="product-item">
                                <div class="product-image"><?php if($item['image'] && file_exists('../uploads/products/' . $item['image'])): ?><img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;"><?php else: ?><i class="fas fa-box"></i><?php endif; ?></div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="product-price">Rp<?php echo number_format($item['product_price'], 0, ',', '.'); ?> / pcs</div>
                                    <div class="product-quantity"><div><div class="quantity-label">Jumlah</div><div class="quantity-value"><?php echo $item['quantity']; ?> pcs</div></div><div><div class="quantity-label">Subtotal</div><div class="product-subtotal">Rp<?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div></div></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: right; margin-top: 20px;"><div style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 10px;">Total Keseluruhan: <span style="color: var(--success);">Rp<?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?></span></div></div>
                </div>

                <div class="section">
                    <h2 class="section-title">Informasi Pembayaran</h2>
                    <div class="payment-info">
                        <div class="info-card"><div class="info-label">Metode Pembayaran</div><div class="info-value"><?php echo htmlspecialchars($transaction['payment_method'] ?? 'Tidak ditentukan'); ?></div></div>
                        <div class="info-card"><div class="info-label">Status Paket</div><div class="info-value"><?php echo $inv_status_text; ?></div></div>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Status Pengiriman</h2>
                    <div class="timeline">
                        <div class="timeline-item"><div class="timeline-content"><div class="timeline-title">Order Diterima</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['created_at'])); ?></div></div></div>
                        <?php if($transaction['status'] === 'paid' || $transaction['status'] === 'shipped' || $transaction['status'] === 'delivered'): ?>
                            <div class="timeline-item"><div class="timeline-content"><div class="timeline-title">Pembayaran Dikonfirmasi</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at'])); ?></div></div></div>
                        <?php endif; ?>
                        <?php if($transaction['status'] === 'shipped' || $transaction['status'] === 'delivered'): ?>
                            <div class="timeline-item"><div class="timeline-content"><div class="timeline-title">Dikirim</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at'])); ?></div></div></div>
                        <?php endif; ?>
                        <?php if($transaction['status'] === 'delivered'): ?>
                            <div class="timeline-item"><div class="timeline-content"><div class="timeline-title">Diterima</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at'])); ?></div></div></div>
                        <?php endif; ?>
                        <?php if($inv_rs === 'requested'): ?>
                            <div class="timeline-item"><div class="timeline-content" style="background:#fef3c7;border-color:#f59e0b;"><div class="timeline-title" style="color:#92400e;">⏳ Pengembalian Diajukan</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at'])); ?></div></div></div>
                        <?php elseif($inv_rs === 'approved'): ?>
                            <div class="timeline-item"><div class="timeline-content" style="background:#dcfce7;border-color:#10b981;"><div class="timeline-title" style="color:#166534;">↩️ Pengembalian Disetujui</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at'])); ?></div></div></div>
                        <?php elseif($inv_rs === 'rejected'): ?>
                            <div class="timeline-item"><div class="timeline-content" style="background:#fee2e2;border-color:#ef4444;"><div class="timeline-title" style="color:#991b1b;">❌ Pengembalian Ditolak</div><div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($transaction['updated_at'] ?? $transaction['created_at'])); ?></div></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Order Confirmation Modal -->
    <div class="modal-overlay" id="completeModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('completeModal')"><i class="fas fa-times"></i></button>
                <div class="modal-title">Selesaikan Pesanan</div>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="alert alert-warning" style="background:#fffbeb;color:#92400e;padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                        <i class="fas fa-info-circle"></i> Apakah Anda yakin ingin menyelesaikan pesanan ini? Setelah diselesaikan, Anda tidak dapat mengajukan pengembalian.
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('completeModal')">Batal</button>
                        <button type="submit" name="complete_order" class="btn btn-success"><i class="fas fa-check"></i> Ya, Selesaikan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal (Untuk Pending/Paid) -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('cancelModal')"><i class="fas fa-times"></i></button>
                <div class="modal-title">Batalkan Pesanan</div>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="alert alert-warning" style="background:#fffbeb;color:#92400e;padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                        <i class="fas fa-info-circle"></i> Apakah Anda yakin ingin membatalkan pesanan ini?
                    </div>
                    <div class="form-group"><label for="cancel_reason">Alasan Pembatalan <span style="color:red">*</span></label><textarea id="cancel_reason" name="cancel_reason" required placeholder="Misal: Salah pilih produk, tidak jadi beli..." minlength="3"></textarea></div>
                    <div class="btn-group"><button type="submit" name="cancel_order" class="btn btn-danger"><i class="fas fa-times"></i> Ya, Batalkan</button><button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">Tidak, Kembali</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Return Modal (Untuk Delivered) -->
    <div class="modal-overlay" id="returnModal">
        <div class="modal">
            <div class="modal-header"><button class="modal-close" onclick="closeModal('returnModal')"><i class="fas fa-times"></i></button><div class="modal-title">Ajukan Pengembalian Barang</div></div>
            <div class="modal-body">
                <form method="POST">
                    <div class="alert alert-warning" style="background:#fffbeb;color:#92400e;padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                        <i class="fas fa-info-circle"></i> Pastikan barang masih dalam kondisi asli dan lengkap.
                    </div>
                    <div class="form-group"><label for="return_reason">Alasan Pengembalian <span style="color:red">*</span></label><textarea id="return_reason" name="return_reason" required placeholder="Jelaskan alasan pengembalian (misal: barang rusak, tidak sesuai, dll)" minlength="10"></textarea></div>
                    <div class="btn-group"><button type="submit" name="request_return" class="btn btn-danger"><i class="fas fa-undo"></i> Kirim Pengajuan</button><button type="button" class="btn btn-secondary" onclick="closeModal('returnModal')">Batal</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Contact Seller Modal -->
    <div class="modal-overlay" id="contactModal">
        <div class="modal">
            <div class="modal-header"><button class="modal-close" onclick="closeModal('contactModal')"><i class="fas fa-times"></i></button><div class="modal-title">Hubungi Penjual</div></div>
            <div class="modal-body">
                <form id="contactForm">
                    <div class="form-group"><label for="subject">Subjek Pesan</label><input type="text" id="subject" name="subject" placeholder="Tentang transaksi #<?php echo htmlspecialchars($transaction['transaction_code']); ?>" value="Pertanyaan tentang transaksi #<?php echo htmlspecialchars($transaction['transaction_code']); ?>" required></div>
                    <div class="form-group"><label for="message">Pesan Anda</label><textarea id="message" name="message" placeholder="Tulis pesan Anda kepada penjual..." required></textarea></div>
                    <div class="form-group"><label for="contact_method">Preferensi Kontak</label><select id="contact_method" name="contact_method" required><option value="email">Email (<?php echo htmlspecialchars($current_user['email']); ?>)</option><option value="phone">Telepon (<?php echo htmlspecialchars($transaction['phone'] ?? 'Belum ada nomor'); ?>)</option><option value="system">Sistem Pesan (Kami akan sampaikan ke penjual)</option></select></div>
                    <div class="btn-group"><button type="submit" class="btn btn-contact"><i class="fas fa-paper-plane"></i> Kirim Pesan</button><button type="button" class="btn btn-secondary" onclick="closeModal('contactModal')"><i class="fas fa-times"></i> Batal</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Invoice Template for Printing -->
    <div id="invoiceTemplate" style="display: none;">
        <div class="invoice-header">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-subtitle">E-Commerce Platform</div>
            <div>Kode Transaksi: #<?php echo htmlspecialchars($transaction['transaction_code']); ?></div>
            <div>Tanggal: <?php echo date('d M Y, H:i', strtotime($transaction['created_at'])); ?></div>
        </div>
        <div class="invoice-info">
            <div>
                <div style="font-weight: 600; margin-bottom: 5px;">Kepada Yth:</div>
                <div><?php echo htmlspecialchars($transaction['user_name'] ?? '-'); ?></div>
                <div><?php echo htmlspecialchars($transaction['email'] ?? '-'); ?></div>
                <div><?php echo htmlspecialchars($transaction['phone'] ?? '-'); ?></div>
                <div style="margin-top: 10px;"><?php echo nl2br(htmlspecialchars($transaction['shipping_address'] ?? '-')); ?></div>
                <?php if(!empty($transaction['courier']) || !empty($transaction['service_type'])): ?>
                    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #cbd5e1;"><div style="font-weight: 600; margin-bottom: 5px; color: var(--primary);"><i class="fas fa-shipping-fast"></i> Opsi Pengiriman</div><div style="font-size: 14px;"><?php if(!empty($transaction['courier'])): ?><div><strong>Kurir:</strong> <?php echo htmlspecialchars(ucfirst($transaction['courier'])); ?></div><?php endif; ?><?php if(!empty($transaction['service_type'])): ?><div><strong>Tipe:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $transaction['service_type']))); ?></div><?php endif; ?></div></div>
                <?php endif; ?>
                <?php if(!empty($transaction['notes'])): ?><div style="margin-top: 10px; font-style: italic; color: #6b7280;"><strong>Catatan:</strong> <?php echo htmlspecialchars($transaction['notes']); ?></div><?php endif; ?>
            </div>
            <div><div style="font-weight: 600; margin-bottom: 5px;">Dari:</div><div>E-Commerce Platform</div><div>Jl. Sudirman No. 123</div><div>Jakarta, Indonesia</div><div>support@ecommerce.com</div><div>+62 21 1234 5678</div></div>
        </div>
        <div class="invoice-section">
            <table class="invoice-table">
                <thead><tr><th>No</th><th>Produk</th><th>Harga Satuan</th><th>Jumlah</th><th>Subtotal</th></tr></thead>
                <tbody>
                    <?php $no = 1; foreach($transaction_items as $item): ?>
                    <tr><td><?php echo $no++; ?></td><td><?php echo htmlspecialchars($item['product_name']); ?></td><td>Rp<?php echo number_format($item['product_price'], 0, ',', '.'); ?></td><td><?php echo $item['quantity']; ?></td><td>Rp<?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="4" style="text-align: right;">Total:</td><td>Rp<?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="invoice-section">
            <div style="font-weight: 600; margin-bottom: 10px;">Metode Pembayaran:</div>
            <div><?php echo htmlspecialchars($transaction['payment_method'] ?? 'Tidak ditentukan'); ?></div>
            <div style="font-weight: 600; margin-top: 15px; margin-bottom: 10px;">Status:</div>
            <!-- ✅ FIX: Status di invoice sinkron dengan return_status & is_completed -->
            <div class="transaction-status <?php echo $inv_status_class; ?>" style="display: inline-block;"><?php echo $inv_status_text; ?></div>
        </div>
        <div class="footer-note"><p>Terima kasih telah berbelanja di E-Commerce Platform!</p><p>Simpan invoice ini sebagai bukti transaksi Anda.</p><p style="margin-top: 10px;">Jika ada pertanyaan, hubungi kami di support@ecommerce.com atau +62 21 1234 5678</p></div>
    </div>

    <script>
        function confirmComplete() {
            if(confirm("Apakah Anda yakin ingin menyelesaikan pesanan ini? Tombol pengembalian akan dinonaktifkan.")) {
                openModal('completeModal');
            }
        }

        function openModal(modalId) { document.getElementById(modalId).classList.add('show'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
        document.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); });
        
        function printInvoice() {
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            const invoiceContent = document.getElementById('invoiceTemplate').innerHTML;
            printWindow.document.write(`<!DOCTYPE html><html><head><title>Invoice #<?php echo htmlspecialchars($transaction['transaction_code']); ?></title><style>body{font-family:'Inter',sans-serif;padding:20px;background:white;color:#1f2937}.invoice-header{text-align:center;margin-bottom:30px;border-bottom:2px solid #3751fe;padding-bottom:20px}.invoice-title{font-size:28px;font-weight:700;color:#3751fe;margin-bottom:10px}.invoice-subtitle{font-size:16px;color:#6b7280}.invoice-info{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px}.invoice-table{width:100%;border-collapse:collapse;margin-bottom:20px}.invoice-table th,.invoice-table td{border:1px solid #e5e7eb;padding:12px;text-align:left}.invoice-table th{background:#f9fafb;font-weight:600}.total-row td{font-weight:700;font-size:18px}.transaction-status{display:inline-block;padding:6px 15px;border-radius:8px;font-weight:600;margin-bottom:10px}.transaction-status.pending{background:#fef3c7;color:#92400e}.transaction-status.paid{background:#d1fae5;color:#065f46}.transaction-status.shipped{background:#dbeafe;color:#1e40af}.transaction-status.delivered{background:#dcfce7;color:#166534}.transaction-status.cancelled{background:#fee2e2;color:#991b1b}.transaction-status.return_requested{background:#fef3c7;color:#92400e;border:2px solid #f59e0b}.transaction-status.return_approved{background:#dcfce7;color:#166534;border:2px solid #10b981}.transaction-status.return_rejected{background:#fee2e2;color:#991b1b;border:2px solid #ef4444}.footer-note{text-align:center;margin-top:40px;font-style:italic;color:#6b7280}@media print{body{padding:0}.no-print{display:none}}</style></head><body>${invoiceContent}<div class="no-print" style="text-align:center;margin-top:20px;"><button onclick="window.print()" style="padding:10px 20px;background:#3751fe;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:500">Cetak Invoice</button><button onclick="window.close()" style="padding:10px 20px;background:#6b7280;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:500;margin-left:10px">Tutup</button></div></body></html>`);
            printWindow.document.close();
            printWindow.onload = function() { /* printWindow.print(); */ };
        }
        
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert(`Pesan berhasil dikirim!\nSubjek: ${document.getElementById('subject').value}\nMetode Kontak: ${document.getElementById('contact_method').value}\nPesan Anda akan disampaikan ke tim penjual. Terima kasih!`);
            closeModal('contactModal');
            this.reset();
        });
    </script>
</body>
</html>