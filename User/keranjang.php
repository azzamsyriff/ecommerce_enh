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
        SELECT c.*, p.name, p.price, p.image, p.stock, p.status, cat.name as category_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN categories cat ON p.category_id = cat.id
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
    
    // Hitung jumlah item di keranjang untuk badge
    $cart_count = count($cart_items);
    
} catch(PDOException $e) {
    $cart_items = [];
    $total_price = 0;
    $cart_count = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - E-Commerce Platform</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #3751fe;
            --primary-dark: #2d43d9;
            --secondary: #667eea;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --gray: #6b7280;
            --light-gray: #f3f4f6;
            --white: #ffffff;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fb;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #1a2234 100%);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .logo-icon {
            font-size: 32px;
            color: var(--primary);
        }

        .logo-text {
            font-family: 'Inter', sans-serif;
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-subtext {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            margin-bottom: 20px;
        }

        .menu-title {
            padding: 15px 25px 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background: var(--sidebar-hover);
            color: var(--white);
            border-left-color: var(--primary);
        }

        .menu-item.active {
            background: rgba(55, 81, 254, 0.2);
            color: var(--white);
            border-left-color: var(--primary);
        }

        .menu-icon {
            width: 24px;
            margin-right: 15px;
            font-size: 18px;
        }

        .menu-text {
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .menu-badge {
            background: var(--primary);
            color: var(--white);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s;
        }

        /* Header */
        .header {
            background: var(--white);
            padding: 20px 40px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-bar {
            position: relative;
        }

        .search-bar input {
            padding: 12px 20px 12px 45px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            width: 300px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
        }

        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 16px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .cart-icon {
            position: relative;
            cursor: pointer;
        }

        .cart-icon i {
            font-size: 20px;
            color: var(--gray);
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: var(--white);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            font-size: 16px;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark);
        }

        .user-role {
            font-size: 12px;
            color: var(--gray);
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 30px 40px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 30px;
        }

        /* Cart Container */
        .cart-container {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .cart-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }

        .cart-count {
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Cart Items */
        .cart-items {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            padding: 20px;
            border-radius: 10px;
            background: var(--light-gray);
            transition: all 0.3s;
        }

        .cart-item:hover {
            background: #e5e7eb;
            transform: translateX(5px);
        }

        .cart-image {
            width: 100%;
            height: 100%;
            border-radius: 8px;
            object-fit: cover;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 32px;
        }

        .cart-item-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cart-item-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .cart-item-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 10px;
        }

        .cart-item-stock {
            font-size: 13px;
            color: var(--gray);
        }

        .cart-item-stock.low {
            color: var(--warning);
        }

        .cart-item-stock.out {
            color: var(--danger);
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: var(--white);
            border: 1px solid var(--gray);
            color: var(--dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .quantity-input {
            width: 40px;
            text-align: center;
            padding: 5px;
            border: 1px solid var(--gray);
            border-radius: 6px;
            font-size: 14px;
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--danger);
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.3s;
        }

        .remove-btn:hover {
            opacity: 0.7;
        }

        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 30px;
            max-width: 400px;
            margin-left: auto;
        }

        .summary-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--light-gray);
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }

        .checkout-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .checkout-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(55, 81, 254, 0.3);
        }

        .checkout-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .continue-shopping {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--light-gray);
            color: var(--dark);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            text-decoration: none;
            text-align: center;
        }

        .continue-shopping:hover {
            background: #e5e7eb;
        }

        /* Empty State */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-cart h3 {
            color: #374151;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: #6b7280;
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #3751fe;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #2d43d9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
            
            .search-bar input {
                width: 200px;
            }
            
            .cart-summary {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar:hover {
                width: 280px;
                overflow: visible;
            }
            
            .logo-text, .logo-subtext, .menu-text, .menu-title, .menu-badge {
                display: none;
            }
            
            .sidebar:hover .logo-text,
            .sidebar:hover .logo-subtext,
            .sidebar:hover .menu-text,
            .sidebar:hover .menu-title,
            .sidebar:hover .menu-badge {
                display: block;
            }
            
            .menu-icon {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .search-bar {
                display: none;
            }
            
            .cart-item {
                grid-template-columns: 80px 1fr;
            }
            
            .cart-item-actions {
                grid-column: span 2;
                flex-direction: row;
                justify-content: space-between;
            }
            
            .dashboard-content {
                padding: 20px;
            }
            
            .cart-container,
            .cart-summary {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-shopping-cart logo-icon"></i>
                <div>
                    <div class="logo-text">E-Commerce</div>
                    <div class="logo-subtext">Platform</div>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <!-- Dashboard Section -->
            <div class="menu-section">
                <div class="menu-title">Main Menu</div>
                <a href="user_dashboard.php" class="menu-item">
                    <i class="fas fa-home menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </div>
            
            <!-- Shopping Section -->
            <div class="menu-section">
                <div class="menu-title">Shopping</div>
                <a href="user_dashboard.php" class="menu-item">
                    <i class="fas fa-shopping-bag menu-icon"></i>
                    <span class="menu-text">Produk</span>
                </a>
                <a href="keranjang.php" class="menu-item active">
                    <i class="fas fa-shopping-cart menu-icon"></i>
                    <span class="menu-text">Keranjang</span>
                    <?php if(count($cart_items) > 0): ?>
                        <span class="menu-badge"><?php echo count($cart_items); ?></span>
                    <?php endif; ?>
                </a>
                <a href="riwayat_transaksi.php" class="menu-item">
                    <i class="fas fa-history menu-icon"></i>
                    <span class="menu-text">Riwayat Transaksi</span>
                </a>
            </div>
            
            <!-- Settings Section -->
            <div class="menu-section">
                <div class="menu-title">Settings</div>
                <a href="profile_user.php" class="menu-item">
                    <i class="fas fa-user menu-icon"></i>
                    <span class="menu-text">Profile</span>
                </a>
                <a href="../Auth/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt menu-icon"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Cari produk...">
                </div>
            </div>
            
            <div class="header-right">
                <div class="cart-icon" onclick="window.location.href='keranjang.php'">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if(count($cart_items) > 0): ?>
                        <span class="cart-badge"><?php echo count($cart_items); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="user-profile" onclick="window.location.href='profile_user.php'">
                    <div class="avatar">
                        <?php if($current_user['profile_picture'] && file_exists('../uploads/profiles/' . $current_user['profile_picture'])): ?>
                            <img src="../uploads/profiles/<?php echo $current_user['profile_picture']; ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $current_user['full_name']; ?></div>
                        <div class="user-role"><?php echo ucfirst($current_user['role']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <h1 class="page-title"><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h1>
            <p class="page-subtitle">Kelola produk yang ingin Anda beli</p>
            
            <?php if(count($cart_items) > 0): ?>
                <!-- Cart Items -->
                <div class="cart-container">
                    <div class="cart-header">
                        <div class="cart-title">Produk di Keranjang</div>
                        <div class="cart-count"><?php echo count($cart_items); ?> produk</div>
                    </div>
                    
                    <div class="cart-items">
<?php foreach($cart_items as $item): ?>
    <div class="cart-item">
        <div class="cart-image">
            <?php if($item['image'] && file_exists('../uploads/products/' . $item['image'])): ?>
                <img src="../uploads/products/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
            <?php else: ?>
                <i class="fas fa-box"></i>
            <?php endif; ?>
        </div>
        
        <div class="cart-item-info">
            <div class="cart-item-title"><?php echo $item['name']; ?></div>
            <div class="cart-item-price">Rp<?php echo number_format($item['price'], 0, ',', '.'); ?></div>
            <div class="cart-item-stock <?php echo $item['stock'] < 5 ? ($item['stock'] == 0 ? 'out' : 'low') : ''; ?>">
                <?php echo $item['stock']; ?> pcs tersedia
            </div>
        </div>
        
        <div class="cart-item-actions">
            <div class="quantity-control">
                <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)">-</button>
                <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" onchange="updateQuantityManual(<?php echo $item['product_id']; ?>, this.value)">
                <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)">+</button>
            </div>
            <button class="remove-btn" onclick="removeFromCart(<?php echo $item['product_id']; ?>)">
                <i class="fas fa-trash"></i> Hapus
            </button>
        </div>
    </div>
<?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Cart Summary -->
                <div class="cart-summary">
                    <div class="summary-title">Ringkasan Pesanan</div>
                    
                    <div class="summary-row">
                        <span>Total Produk</span>
                        <span><?php echo count($cart_items); ?> item</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>Rp<?php echo number_format($total_price, 0, ',', '.'); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Ongkos Kirim</span>
                        <span>Rp15.000</span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total</span>
                        <span>Rp<?php echo number_format($total_price + 15000, 0, ',', '.'); ?></span>
                    </div>
                    
                    <button class="checkout-btn" onclick="checkout()">
                        <i class="fas fa-shopping-checkout"></i> Lanjut ke Checkout
                    </button>
                    
                    <a href="user_dashboard.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Lanjut Belanja
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Empty Cart -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Keranjang Belanja Kosong</h3>
                    <p>Keranjang belanja Anda saat ini kosong. Temukan produk menarik di halaman dashboard dan tambahkan ke keranjang untuk memulai belanja!</p>
                    <a href="user_dashboard.php" class="btn">
                        <i class="fas fa-shopping-bag"></i> Jelajahi Produk
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Update quantity
        function updateQuantity(productId, change) {
            const currentQty = parseInt(document.querySelector(`input[onchange*="updateQuantityManual(${productId}"]`).value);
            const newQty = currentQty + change;
            
            if(newQty >= 1) {
                updateQuantityManual(productId, newQty);
            }
        }
        
        // Update quantity manual
        function updateQuantityManual(productId, quantity) {
            fetch('update_keranjang.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    action: 'update', 
                    product_id: productId, 
                    quantity: parseInt(quantity) 
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Gagal memperbarui keranjang');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memperbarui keranjang');
            });
        }
        
        // Remove from cart
        function removeFromCart(productId) {
            if(confirm('Yakin ingin menghapus produk ini dari keranjang?')) {
                fetch('update_keranjang.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ 
                        action: 'remove', 
                        product_id: productId 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Gagal menghapus dari keranjang');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus dari keranjang');
                });
            }
        }
        
        // Checkout
        function checkout() {
            if(confirm('Yakin ingin melanjutkan ke checkout?')) {
                window.location.href = 'checkout.php';
            }
        }
    </script>
</body>
</html>