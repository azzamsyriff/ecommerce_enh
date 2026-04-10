<?php
session_start();
require_once '../config.php';
// Cek apakah user sudah login dan role user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../Auth/login.php');
    exit();
}
// Ambil data user
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    if(!$current_user) {
        header('Location: ../Auth/logout.php');
        exit();
    }
    // Ambil keranjang dari database
    $stmt = $conn->prepare("
        SELECT c.*, p.name, p.price, p.image, p.stock, p.description as product_description
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = :user_id AND p.status = 'active' AND p.stock > 0
        ORDER BY c.created_at DESC
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
    // Hitung total harga
    $total_price = 0;
    foreach($cart_items as $item) {
        $total_price += $item['price'] * $item['quantity'];
    }
    // Hitung ongkos kirim default (flat rate Rp15.000)
    $shipping_cost = 15000;
    $grand_total = $total_price + $shipping_cost;
    // Ambil jumlah item di keranjang untuk badge
    $cart_count = count($cart_items);
} catch(PDOException $e) {
    $cart_items = [];
    $total_price = 0;
    $shipping_cost = 15000;
    $grand_total = $shipping_cost;
    $cart_count = 0;
    error_log("Error in checkout.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - E-Commerce Platform</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.0/dist/qrious.min.js"></script>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root { --primary: #3751fe; --primary-dark: #2d43d9; --secondary: #667eea; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --dark: #1f2937; --gray: #6b7280; --light-gray: #f3f4f6; --white: #ffffff; --sidebar-bg: #1e293b; --sidebar-hover: #334155; --card-bg: #ffffff; --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); }
body { font-family: 'Inter', sans-serif; background-color: #f5f7fb; display: flex; min-height: 100vh; }
/* Sidebar */
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
/* Main Content */
.main-content { flex: 1; margin-left: 280px; transition: all 0.3s; }
/* Header */
.header { background: var(--white); padding: 20px 40px; box-shadow: var(--shadow); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; }
.header-left { display: flex; align-items: center; gap: 20px; }
.btn-secondary { display: inline-block; padding: 8px 15px; font-size: 14px; text-decoration: none; color: var(--dark); font-weight: 500; background: #f3f4f6; border-radius: 8px; transition: all 0.2s; }
.btn-secondary:hover { background: #e5e7eb; }
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
/* Dashboard Content */
.dashboard-content { padding: 30px 40px; }
.page-title { font-size: 28px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
.breadcrumb { display: flex; gap: 10px; color: var(--gray); font-size: 14px; margin-bottom: 30px; }
.breadcrumb a { color: var(--primary); text-decoration: none; }
.breadcrumb i { color: var(--gray); }
/* Checkout Container */
.checkout-container { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
/* Shipping Form */
.shipping-form { background: white; border-radius: 15px; box-shadow: var(--shadow); padding: 30px; }
.form-section { margin-bottom: 30px; }
.form-title { font-size: 20px; font-weight: 600; color: var(--dark); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--light-gray); display: flex; align-items: center; gap: 10px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-weight: 500; color: var(--dark); margin-bottom: 8px; font-size: 14px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px; border: 2px solid var(--light-gray); border-radius: 10px; font-size: 16px; transition: all 0.3s; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
.form-group textarea { min-height: 120px; resize: vertical; }
/* 🔒 Courier Selection Styles */
.courier-section { background: #f8fafc; border-radius: 12px; padding: 20px; margin: 20px 0; border: 2px solid var(--light-gray); transition: all 0.3s; }
.courier-section.active { border-color: var(--primary); background: rgba(55, 81, 254, 0.03); }
.courier-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
.courier-header i { color: var(--primary); font-size: 20px; }
.courier-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.courier-info { font-size: 13px; color: var(--gray); margin-top: 5px; }
.courier-eta { display: inline-block; background: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-left: 8px; }
/* Payment Methods */
.payment-section { background: white; border-radius: 15px; box-shadow: var(--shadow); padding: 30px; margin-bottom: 30px; }
.payment-options { display: flex; flex-direction: column; gap: 15px; }
.payment-option { display: flex; align-items: center; padding: 15px; border: 2px solid var(--light-gray); border-radius: 12px; cursor: pointer; transition: all 0.2s ease-in-out; position: relative; user-select: none; }
.payment-option:hover { border-color: var(--primary); background: rgba(55, 81, 254, 0.03); transform: translateY(-2px); }
.payment-option.active { border-color: var(--primary); background: rgba(55, 81, 254, 0.05); border-width: 2px; box-shadow: 0 0 0 4px rgba(55, 81, 254, 0.1); }
.payment-option.active::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: -10px; right: -10px; width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); z-index: 2; }
.payment-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 24px; flex-shrink: 0; }
.bank-bca { background: #eb1a23; color: white; } .bank-bni { background: #b71d1f; color: white; } .bank-mandiri { background: #003366; color: white; } .ewallet-gopay { background: #00a86b; color: white; } .ewallet-ovo { background: #f8592e; color: white; } .ewallet-dana { background: #ff5c00; color: white; } .qris { background: #2ecc71; color: white; } .cod { background: #6366f1; color: white; }
.payment-label { flex: 1; font-weight: 600; color: var(--dark); font-size: 16px; pointer-events: none; }
.payment-desc { font-size: 13px; color: var(--gray); margin-top: 3px; pointer-events: none; }
/* Cart Items Preview */
.cart-items-preview { margin-bottom: 30px; }
.cart-item { display: grid; grid-template-columns: 80px 1fr auto; gap: 20px; padding: 15px 0; border-bottom: 1px solid var(--light-gray); }
.cart-item:last-child { border-bottom: none; }
.cart-image { width: 80px; height: 80px; border-radius: 10px; object-fit: cover; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 32px; }
.cart-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.cart-item-info h4 { font-size: 16px; font-weight: 600; color: var(--dark); margin-bottom: 5px; }
.cart-item-info .quantity { color: var(--gray); font-size: 14px; }
.cart-item-price { font-weight: 600; color: var(--success); font-size: 16px; text-align: right; }
/* Order Summary */
.order-summary { background: white; border-radius: 15px; box-shadow: var(--shadow); padding: 30px; position: sticky; top: 100px; }
.summary-title { font-size: 20px; font-weight: 600; color: var(--dark); margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--light-gray); }
.summary-item { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 16px; }
.summary-label { color: var(--gray); }
.summary-value { font-weight: 600; color: var(--dark); }
.summary-total { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--light-gray); font-size: 24px; font-weight: 700; color: var(--dark); }
.checkout-btn { display: block; width: 100%; padding: 18px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 25px; position: relative; overflow: hidden; z-index: 10; }
.checkout-btn:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(55, 81, 254, 0.4); }
.checkout-btn:disabled { background: #9ca3af; cursor: not-allowed; transform: none; box-shadow: none; }
.checkout-btn::after { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 70%); transform: scale(0); transition: transform 0.5s ease-out; z-index: 0; }
.checkout-btn:hover::after { transform: scale(1); }
.checkout-btn span { position: relative; z-index: 1; }
.empty-cart { text-align: center; padding: 60px 20px; }
.empty-cart i { font-size: 64px; color: #d1d5db; margin-bottom: 20px; }
.empty-cart h3 { color: #374151; font-size: 24px; margin-bottom: 15px; }
.empty-cart p { color: #6b7280; margin-bottom: 25px; max-width: 500px; margin-left: auto; margin-right: auto; }
.alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
.alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #f59e0b; }
.alert-warning i { color: #f59e0b; font-size: 20px; }
.input-error { border-color: var(--danger) !important; background-color: #fef2f2; }
/* MODAL STYLES */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 2000; display: none; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
.modal-overlay.show { display: flex; opacity: 1; }
.modal-content { background: white; width: 90%; max-width: 500px; border-radius: 16px; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); transform: scale(0.9); transition: transform 0.3s ease; text-align: center; }
.modal-overlay.show .modal-content { transform: scale(1); }
.modal-header { margin-bottom: 20px; }
.modal-title { font-size: 22px; font-weight: 700; color: var(--dark); }
.modal-body { margin-bottom: 25px; }
.payment-instruction { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 20px; margin: 15px 0; }
.account-number { font-size: 24px; font-weight: 800; color: var(--primary); letter-spacing: 1px; margin: 10px 0; font-family: monospace; }
.copy-btn { background: var(--light-gray); border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; color: var(--gray); transition: all 0.2s; }
.copy-btn:hover { background: #e2e8f0; color: var(--dark); }
.qris-container { display: flex; justify-content: center; margin: 15px 0; }
.qris-canvas { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
.modal-footer { display: flex; gap: 15px; }
.btn-modal { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; }
.btn-cancel { background: var(--light-gray); color: var(--gray); } .btn-cancel:hover { background: #e2e8f0; color: var(--dark); }
.btn-confirm { background: var(--primary); color: white; } .btn-confirm:hover { background: var(--primary-dark); } .btn-confirm:disabled { background: #9ca3af; cursor: not-allowed; }
/* Responsive */
@media (max-width: 1024px) { .sidebar { width: 240px; } .main-content { margin-left: 240px; } .checkout-container { grid-template-columns: 1fr; } .order-summary { position: static; } }
@media (max-width: 768px) { .sidebar { width: 70px; overflow: hidden; } .sidebar:hover { width: 280px; overflow: visible; } .logo-text, .logo-subtext, .menu-text, .menu-title, .menu-badge { display: none; } .sidebar:hover .logo-text, .sidebar:hover .logo-subtext, .sidebar:hover .menu-text, .sidebar:hover .menu-title, .sidebar:hover .menu-badge { display: block; } .menu-icon { margin-right: 0; } .main-content { margin-left: 70px; } .dashboard-content { padding: 20px; } .form-row { grid-template-columns: 1fr; } .courier-row { grid-template-columns: 1fr; } .cart-item { grid-template-columns: 70px 1fr; } .cart-item-price { grid-column: span 2; text-align: left; margin-top: 10px; } }
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo"><i class="fas fa-shopping-cart logo-icon"></i><div><div class="logo-text">E-Commerce</div><div class="logo-subtext">Platform</div></div></div>
    </div>
    <div class="sidebar-menu">
        <div class="menu-section"><div class="menu-title">Main Menu</div><a href="user_dashboard.php" class="menu-item"><i class="fas fa-home menu-icon"></i><span class="menu-text">Dashboard</span></a></div>
        <div class="menu-section"><div class="menu-title">Shopping</div>
            <a href="user_dashboard.php" class="menu-item"><i class="fas fa-shopping-bag menu-icon"></i><span class="menu-text">Produk</span></a>
            <a href="keranjang.php" class="menu-item"><i class="fas fa-shopping-cart menu-icon"></i><span class="menu-text">Keranjang</span><?php if($cart_count > 0): ?><span class="menu-badge"><?php echo $cart_count; ?></span><?php endif; ?></a>
            <a href="riwayat_transaksi.php" class="menu-item active"><i class="fas fa-history menu-icon"></i><span class="menu-text">Riwayat Transaksi</span></a>
        </div>
        <div class="menu-section"><div class="menu-title">Settings</div>
            <a href="profile_user.php" class="menu-item"><i class="fas fa-user menu-icon"></i><span class="menu-text">Profile</span></a>
            <a href="../Auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt menu-icon"></i><span class="menu-text">Logout</span></a>
        </div>
    </div>
</div>
<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <div class="header-left"><a href="keranjang.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Keranjang</a></div>
        <div class="header-right">
            <div class="cart-icon" onclick="window.location.href='keranjang.php'"><i class="fas fa-shopping-cart"></i><?php if($cart_count > 0): ?><span class="cart-badge"><?php echo $cart_count; ?></span><?php endif; ?></div>
            <div class="user-profile" onclick="window.location.href='profile_user.php'">
                <div class="avatar">
                    <?php if($current_user['profile_picture'] && file_exists('../uploads/profiles/' . $current_user['profile_picture'])): ?>
                    <img src="../uploads/profiles/<?php echo $current_user['profile_picture']; ?>" alt="Profile">
                    <?php else: ?>
                    <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info"><div class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div><div class="user-role"><?php echo ucfirst($current_user['role']); ?></div></div>
            </div>
        </div>
    </div>
    <div class="dashboard-content">
        <div class="breadcrumb"><a href="user_dashboard.php">Produk</a><i class="fas fa-chevron-right"></i><a href="keranjang.php">Keranjang</a><i class="fas fa-chevron-right"></i><span>Checkout</span></div>
        <h1 class="page-title"><i class="fas fa-shopping-cart"></i> Checkout</h1>
        <div class="alert alert-warning"><i class="fas fa-exclamation-circle"></i><span>Pastikan data pengiriman dan metode pembayaran sudah benar sebelum mengkonfirmasi pesanan.</span></div>
        <?php if(count($cart_items) > 0): ?>
        <div class="checkout-container">
            <div>
                <div class="shipping-form">
                    <div class="form-section">
                        <h2 class="form-title"><i class="fas fa-truck"></i> Informasi Pengiriman</h2>
                        <div class="form-row">
                            <div class="form-group"><label for="full_name">Nama Lengkap <span style="color: red;">*</span></label><input type="text" id="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" readonly required></div>
                            <div class="form-group"><label for="email">Email <span style="color: red;">*</span></label><input type="email" id="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" readonly required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="phone">Nomor Telepon <span style="color: red;">*</span></label><input type="tel" id="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" placeholder="Contoh: 081234567890" readonly required></div>
                            <div class="form-group"><label for="address">Alamat Lengkap <span style="color: red;">*</span></label><textarea id="address" readonly required placeholder="Masukkan alamat lengkap untuk pengiriman"><?php echo htmlspecialchars($current_user['address'] ?? ''); ?></textarea></div>
                        </div>
                    </div>
                    
                    <!-- 🔒 COURIER & SERVICE TYPE SECTION -->
                    <div class="form-section">
                        <h2 class="form-title"><i class="fas fa-shipping-fast"></i> Opsi Pengiriman</h2>
                        <div class="courier-section active">
                            <div class="courier-header">
                                <i class="fas fa-truck-moving"></i>
                                <strong>Pilih Jasa Kurir & Tipe Pengiriman</strong>
                            </div>
                            <div class="courier-row">
                                <div class="form-group">
                                    <label for="courier">Jasa Kurir <span style="color: red;">*</span></label>
                                    <select id="courier" name="courier" required onchange="updateShippingCost()">
                                        <option value="">-- Pilih Kurir --</option>
                                        <option value="jne">JNE</option>
                                        <option value="jnt">J&T Express</option>
                                        <option value="sicepat">SiCepat</option>
                                        <option value="ninja">Ninja Express</option>
                                        <option value="pos">POS Indonesia</option>
                                    </select>
                                    <div class="courier-info">Pilih jasa pengiriman yang tersedia di wilayah Anda</div>
                                </div>
                                <div class="form-group">
                                    <label for="service_type">Tipe Pengiriman <span style="color: red;">*</span></label>
                                    <select id="service_type" name="service_type" required onchange="updateShippingCost()">
                                        <option value="">-- Pilih Tipe --</option>
                                        <option value="kilat">Kilat (1 Hari) <span class="courier-eta">🚀</span></option>
                                        <option value="same_day">Same Day (Hari Ini) <span class="courier-eta">⚡</span></option>
                                        <option value="next_day">Next Day (Besok) <span class="courier-eta">📦</span></option>
                                        <option value="reguler" selected>Reguler (2-3 Hari) <span class="courier-eta">🚚</span></option>
                                        <option value="hemat">Hemat (3-5 Hari) <span class="courier-eta">🐌</span></option>
                                    </select>
                                    <div class="courier-info">Estimasi waktu pengiriman dapat berubah tergantung lokasi</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-section">
                        <h2 class="form-title"><i class="fas fa-credit-card"></i> Pilih Metode Pembayaran</h2>
                        <div class="payment-options">
                            <div class="payment-option active" data-method="bank_transfer_bca"><div class="payment-icon bank-bca"><i class="fas fa-university"></i></div><div class="payment-label">Transfer Bank (BCA)<div class="payment-desc">Bayar melalui ATM/M-Banking/Internet Banking BCA</div></div></div>
                            <div class="payment-option" data-method="bank_transfer_bni"><div class="payment-icon bank-bni"><i class="fas fa-university"></i></div><div class="payment-label">Transfer Bank (BNI)<div class="payment-desc">Bayar melalui ATM/M-Banking/Internet Banking BNI</div></div></div>
                            <div class="payment-option" data-method="bank_transfer_mandiri"><div class="payment-icon bank-mandiri"><i class="fas fa-university"></i></div><div class="payment-label">Transfer Bank (Mandiri)<div class="payment-desc">Bayar melalui ATM/M-Banking/Internet Banking Mandiri</div></div></div>
                            <div class="payment-option" data-method="qris"><div class="payment-icon qris"><i class="fas fa-qrcode"></i></div><div class="payment-label">QRIS<div class="payment-desc">Scan QR Code dengan aplikasi pembayaran apa pun</div></div></div>
                            <div class="payment-option" data-method="cod"><div class="payment-icon cod"><i class="fas fa-truck"></i></div><div class="payment-label">Cash on Delivery (COD)<div class="payment-desc">Bayar tunai saat barang diterima</div></div></div>
                        </div>
                    </div>
                    <div class="form-section">
                        <h2 class="form-title"><i class="fas fa-sticky-note"></i> Catatan Pesanan (Opsional)</h2>
                        <div class="form-group"><textarea id="notes" placeholder="Contoh: Kirim hari Sabtu, warna biru, dll."></textarea></div>
                    </div>
                    <button class="checkout-btn" id="placeOrderBtn"><span><i class="fas fa-check-circle"></i> Bayar Sekarang - Rp<span id="grandTotalDisplay"><?php echo number_format($grand_total, 0, ',', '.'); ?></span></span></button>
                </div>
            </div>
            <div class="order-summary">
                <h2 class="summary-title">Ringkasan Pesanan</h2>
                <div class="cart-items-preview">
                    <?php foreach($cart_items as $item): ?>
                    <div class="cart-item">
                        <div class="cart-image"><?php if($item['image'] && file_exists('../uploads/products/' . $item['image'])): ?><img src="../uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"><?php else: ?><i class="fas fa-box"></i><?php endif; ?></div>
                        <div class="cart-item-info"><h4><?php echo htmlspecialchars($item['name']); ?></h4><div class="quantity">x <?php echo $item['quantity']; ?></div></div>
                        <div class="cart-item-price">Rp<?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-item"><span class="summary-label">Subtotal</span><span class="summary-value">Rp<span id="subtotalDisplay"><?php echo number_format($total_price, 0, ',', '.'); ?></span></span></div>
                <div class="summary-item"><span class="summary-label">Ongkos Kirim</span><span class="summary-value">Rp<span id="shippingDisplay"><?php echo number_format($shipping_cost, 0, ',', '.'); ?></span></span></div>
                <div class="summary-total"><span>Total Pembayaran</span><span>Rp<span id="grandTotalDisplay"><?php echo number_format($grand_total, 0, ',', '.'); ?></span></span></div>
                <div style="background: #dbeafe; border-radius: 10px; padding: 15px; margin-top: 20px; font-size: 14px; color: #1e40af;"><i class="fas fa-shield-alt" style="margin-right: 8px;"></i><strong>Garansi Uang Kembali</strong> jika pesanan tidak sampai dalam 7 hari</div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-cart"><i class="fas fa-shopping-cart"></i><h3>Keranjang Belanja Kosong</h3><p>Tidak ada produk dalam keranjang Anda. Silakan tambahkan produk terlebih dahulu sebelum checkout.</p><a href="user_dashboard.php" class="btn" style="display: inline-block; padding: 12px 24px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; margin-top: 20px;"><i class="fas fa-shopping-bag"></i> Jelajahi Produk</a></div>
        <?php endif; ?>
    </div>
</div>
<!-- PAYMENT MODAL -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title" id="modalTitle">Instruksi Pembayaran</h3></div>
        <div class="modal-body">
            <p style="color: var(--gray); margin-bottom: 15px;">Silakan lakukan pembayaran sesuai instruksi di bawah ini:</p>
            <div id="paymentInstructionContent" class="payment-instruction"></div>
            <p style="font-size: 14px; color: var(--danger); margin-top: 15px;"><i class="fas fa-info-circle"></i> Pastikan nominal transfer sesuai dengan total pembayaran.</p>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-cancel" id="cancelPaymentBtn">Batal</button>
            <button class="btn-modal btn-confirm" id="confirmOrderBtn"><i class="fas fa-check"></i> Konfirmasi Pesanan</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentOptions = document.querySelectorAll('.payment-option');
    paymentOptions.forEach(option => { option.addEventListener('click', function(e) { e.preventDefault(); paymentOptions.forEach(opt => opt.classList.remove('active')); this.classList.add('active'); }); });

    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const modal = document.getElementById('paymentModal');
    const cancelPaymentBtn = document.getElementById('cancelPaymentBtn');
    const confirmOrderBtn = document.getElementById('confirmOrderBtn');
    const instructionContent = document.getElementById('paymentInstructionContent');
    const modalTitle = document.getElementById('modalTitle');
    const bankAccounts = { 'bank_transfer_bca': { name: 'BCA', number: '123-456-7890', holder: 'PT Toko Online' }, 'bank_transfer_bni': { name: 'BNI', number: '098-765-4321', holder: 'PT Toko Online' }, 'bank_transfer_mandiri': { name: 'Mandiri', number: '112-233-4455', holder: 'PT Toko Online' } };
    
    // 🔒 DATA ONGKIR (Simulasi - Bisa diganti API RajaOngkir nanti)
    const shippingRates = {
        'jne': { 'kilat': 35000, 'same_day': 25000, 'next_day': 18000, 'reguler': 15000, 'hemat': 12000 },
        'jnt': { 'kilat': 32000, 'same_day': 23000, 'next_day': 17000, 'reguler': 14000, 'hemat': 11000 },
        'sicepat': { 'kilat': 33000, 'same_day': 24000, 'next_day': 17500, 'reguler': 15000, 'hemat': 12000 },
        'ninja': { 'kilat': 30000, 'same_day': 22000, 'next_day': 16000, 'reguler': 13000, 'hemat': 10000 },
        'pos': { 'kilat': 28000, 'same_day': 20000, 'next_day': 15000, 'reguler': 12000, 'hemat': 9000 }
    };
    
    let currentPaymentMethod = ''; 
    let formData = {};
    let baseTotal = <?php echo $total_price; ?>;
    let baseShipping = <?php echo $shipping_cost; ?>;

    // 🔒 FUNGSI UPDATE ONGKIR & TOTAL
    function updateShippingCost() {
        const courier = document.getElementById('courier').value;
        const service = document.getElementById('service_type').value;
        
        if(courier && service && shippingRates[courier] && shippingRates[courier][service]) {
            const newShipping = shippingRates[courier][service];
            const newGrandTotal = baseTotal + newShipping;
            
            // Update display dengan animasi
            document.getElementById('shippingDisplay').textContent = newShipping.toLocaleString('id-ID');
            document.getElementById('grandTotalDisplay').textContent = newGrandTotal.toLocaleString('id-ID');
            
            // Highlight section
            document.querySelector('.courier-section').classList.add('active');
        } else {
            // Reset ke default jika belum pilih
            document.getElementById('shippingDisplay').textContent = baseShipping.toLocaleString('id-ID');
            document.getElementById('grandTotalDisplay').textContent = (baseTotal + baseShipping).toLocaleString('id-ID');
            document.querySelector('.courier-section').classList.remove('active');
        }
    }

    function validateForm() {
        const fullName = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const address = document.getElementById('address').value.trim();
        const courier = document.getElementById('courier').value;
        const serviceType = document.getElementById('service_type').value;
        
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        if (!fullName) { document.getElementById('full_name').classList.add('input-error'); alert('Nama Lengkap wajib diisi.'); return false; }
        if (!email || !validateEmail(email)) { document.getElementById('email').classList.add('input-error'); alert('Email wajib diisi dan format harus valid.'); return false; }
        if (!phone || !validatePhone(phone)) { document.getElementById('phone').classList.add('input-error'); alert('Nomor telepon wajib diisi.'); return false; }
        if (!address) { document.getElementById('address').classList.add('input-error'); alert('Alamat lengkap wajib diisi.'); return false; }
        if (!courier) { alert('Silakan pilih jasa kurir!'); return false; }
        if (!serviceType) { alert('Silakan pilih tipe pengiriman!'); return false; }
        
        const selectedOption = document.querySelector('.payment-option.active');
        if (!selectedOption) { alert('Silakan pilih metode pembayaran!'); return false; }
        
        currentPaymentMethod = selectedOption.getAttribute('data-method');
        
        // 🔒 TAMBAHKAN DATA KURIR KE FORM DATA
        formData = { 
            full_name: fullName, 
            email: email, 
            phone: phone, 
            address: address, 
            notes: document.getElementById('notes').value.trim(), 
            payment_method: currentPaymentMethod, 
            courier: courier,           // <--- BARU
            service_type: serviceType,  // <--- BARU
            total_amount: parseInt(document.getElementById('grandTotalDisplay').textContent.replace(/\./g, '').replace(',', '.'))
        };
        return true;
    }

    if (placeOrderBtn) {
        placeOrderBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!validateForm()) return;
            if (currentPaymentMethod === 'cod') {
                const conf = confirm(`Metode: COD (Bayar di Tempat)\nKurir: ${formData.courier.toUpperCase()} - ${formData.service_type}\nTotal: Rp${formData.total_amount.toLocaleString('id-ID')}\nLanjutkan pesanan?`);
                if (conf) submitOrderToServer();
            } else if (currentPaymentMethod.startsWith('bank_transfer')) {
                const bank = bankAccounts[currentPaymentMethod];
                modalTitle.innerText = `Transfer ke ${bank.name}`;
                instructionContent.innerHTML = `<p>Nomor Rekening:</p><div class="account-number">${bank.number}</div><p>A.n. <strong>${bank.holder}</strong></p><button class="copy-btn" onclick="navigator.clipboard.writeText('${bank.number}')"><i class="fas fa-copy"></i> Salin Nomor</button><hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;"><p>Total Tagihan:</p><h3 style="color: var(--primary);">Rp${formData.total_amount.toLocaleString('id-ID')}</h3><p style="font-size:12px;color:#6b7280;margin-top:10px;">Kurir: ${formData.courier.toUpperCase()} - ${formData.service_type}</p>`;
                showModal();
            } else if (currentPaymentMethod === 'qris') {
                modalTitle.innerText = "Scan QRIS";
                instructionContent.innerHTML = `<div class="qris-container"><canvas id="qrisCanvas"></canvas></div><p>Scan menggunakan aplikasi e-wallet apapun</p><h3 style="color: var(--primary); margin-top:10px;">Rp${formData.total_amount.toLocaleString('id-ID')}</h3><p style="font-size:12px;color:#6b7280;margin-top:10px;">Kurir: ${formData.courier.toUpperCase()} - ${formData.service_type}</p>`;
                showModal();
                setTimeout(() => { new QRious({ element: document.getElementById('qrisCanvas'), value: `QRIS_PAYLOAD_SIMULASI_${Date.now()}`, size: 200 }); }, 100);
            }
        });
    }

    function showModal() { modal.classList.add('show'); }
    function hideModal() { modal.classList.remove('show'); }
    cancelPaymentBtn.addEventListener('click', hideModal);
    confirmOrderBtn.addEventListener('click', function() { this.disabled = true; this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...'; submitOrderToServer(); });

    function submitOrderToServer() {
        if(placeOrderBtn) { placeOrderBtn.disabled = true; placeOrderBtn.innerHTML = '<span><i class="fas fa-spinner fa-spin"></i> Memproses...</span>'; }
        fetch('process_checkout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData) })
        .then(response => response.json())
        .then(data => {
            hideModal();
            if (data.success) {
                alert(`✅ Pesanan berhasil dibuat!\nKode Transaksi: ${data.transaction_code}\nTotal: Rp${formData.total_amount.toLocaleString('id-ID')}\nKurir: ${formData.courier.toUpperCase()} - ${formData.service_type}\nMetode: ${getPaymentMethodName(formData.payment_method)}\nSilakan selesaikan pembayaran jika belum.`);
                window.location.href = 'riwayat_transaksi.php';
            } else {
                alert('Gagal membuat pesanan: ' + data.message);
                if(placeOrderBtn) { placeOrderBtn.disabled = false; placeOrderBtn.innerHTML = '<span><i class="fas fa-check-circle"></i> Bayar Sekarang - Rp'+formData.total_amount.toLocaleString('id-ID')+'</span>'; }
                confirmOrderBtn.disabled = false; confirmOrderBtn.innerHTML = '<i class="fas fa-check"></i> Saya Sudah Bayar / Konfirmasi Pesanan';
            }
        }).catch(error => {
            console.error('Error:', error); hideModal(); alert('Terjadi kesalahan jaringan. Silakan coba lagi.');
            if(placeOrderBtn) { placeOrderBtn.disabled = false; placeOrderBtn.innerHTML = '<span><i class="fas fa-check-circle"></i> Bayar Sekarang - Rp'+formData.total_amount.toLocaleString('id-ID')+'</span>'; }
            confirmOrderBtn.disabled = false; confirmOrderBtn.innerHTML = '<i class="fas fa-check"></i> Saya Sudah Bayar / Konfirmasi Pesanan';
        });
    }
    function validateEmail(email) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).toLowerCase()); }
    function validatePhone(phone) { return /^08\d{8,11}$/.test(phone.replace(/\D/g, '')); }
    function getPaymentMethodName(method) { const names = {'bank_transfer_bca': 'Transfer Bank BCA', 'bank_transfer_bni': 'Transfer Bank BNI', 'bank_transfer_mandiri': 'Transfer Bank Mandiri', 'qris': 'QRIS', 'cod': 'Cash on Delivery'}; return names[method] || method; }
    function updateCartBadge() { fetch('get_cart_count.php').then(r => r.json()).then(data => { const badge = document.querySelector('.cart-badge'); if (badge) { badge.textContent = data.count; badge.style.display = data.count === 0 ? 'none' : 'flex'; }}).catch(e => console.error(e)); }
    updateCartBadge();
});
</script>
</body>
</html>