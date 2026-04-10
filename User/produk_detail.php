<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role user
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../Auth/login.php');
    exit();
}

// Ambil ID produk dari URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($product_id <= 0) {
    $_SESSION['error'] = "Produk tidak ditemukan!";
    header('Location: user_dashboard.php');
    exit();
}

// Ambil data produk
try {
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.id = :id AND p.status = 'active' AND p.stock > 0");
    $stmt->execute(['id' => $product_id]);
    $product = $stmt->fetch();
    
    if(!$product) {
        $_SESSION['error'] = "Produk tidak ditemukan atau tidak tersedia!";
        header('Location: user_dashboard.php');
        exit();
    }
    
    // Ambil produk terkait (kategori yang sama)
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = :category_id AND id != :id AND status = 'active' AND stock > 0 ORDER BY created_at DESC LIMIT 4");
    $stmt->execute(['category_id' => $product['category_id'], 'id' => $product_id]);
    $related_products = $stmt->fetchAll();
    
    // Ambil data user untuk avatar
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    // Ambil jumlah item di keranjang
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $cart_result = $stmt->fetch();
    $cart_count = $cart_result['total'] ?? 0;
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: user_dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> - Detail Produk</title>
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

        .breadcrumb {
            display: flex;
            gap: 10px;
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb i {
            color: var(--gray);
        }

        /* Product Detail */
        .product-detail-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }

        .product-images {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
            background: #f3f4f6;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 64px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-category {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            width: fit-content;
        }

        .product-title {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.3;
        }

        .product-description {
            font-size: 16px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .product-price {
            font-size: 36px;
            font-weight: 700;
            color: #10b981;
            margin: 15px 0;
        }

        .product-stock {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .product-stock.in-stock {
            background: #d1fae5;
            color: #065f46;
        }

        .product-stock.low-stock {
            background: #fef3c7;
            color: #92400e;
        }

        .product-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 400px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--light-gray);
            border: none;
            color: var(--dark);
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            background: var(--primary);
            color: white;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 15px 25px;
            background: #3751fe;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(55, 81, 254, 0.4);
        }

        .btn-cart {
            background: #10b981;
        }

        .btn-cart:hover {
            background: #0da271;
        }

        .btn-buy {
            background: #f59e0b;
            font-size: 18px;
        }

        .btn-buy:hover {
            background: #d97706;
        }

        /* Related Products */
        .related-products {
            margin-top: 60px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 15px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 48px;
        }

        .product-content {
            padding: 20px;
        }

        .product-card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            cursor: pointer;
        }

        .product-card-title:hover {
            color: var(--primary);
        }

        .product-card-price {
            font-size: 20px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 10px;
        }

        .product-card-stock {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .product-card-stock.in-stock {
            background: #d1fae5;
            color: #065f46;
        }

        .product-card-stock.low-stock {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-secondary {
            background: #6b7280;
            width: 100%;
            margin-top: 10px;
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
            
            .product-detail-container {
                grid-template-columns: 1fr;
            }
            
            .main-image {
                height: 300px;
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
            
            .dashboard-content {
                padding: 20px;
            }
            
            .product-detail-container {
                gap: 20px;
            }
            
            .main-image {
                height: 250px;
            }
            
            .product-title {
                font-size: 24px;
            }
            
            .product-price {
                font-size: 28px;
            }
            
            .quantity-control {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .quantity-input {
                width: 100%;
            }
            
            .btn {
                width: 100%;
            }
            
            .btn-buy {
                font-size: 16px;
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
                <a href="keranjang.php" class="menu-item">
                    <i class="fas fa-shopping-cart menu-icon"></i>
                    <span class="menu-text">Keranjang</span>
                    <?php if($cart_count > 0): ?>
                        <span class="menu-badge"><?php echo $cart_count; ?></span>
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
                <a href="user_dashboard.php" class="btn btn-secondary" style="padding: 8px 15px; font-size: 14px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Produk
                </a>
            </div>
            
            <div class="header-right">
                <div class="cart-icon" onclick="window.location.href='keranjang.php'">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if($cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
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
            <div class="breadcrumb">
                <a href="user_dashboard.php">Produk</a>
                <i class="fas fa-chevron-right"></i>
                <span><?php echo $product['name']; ?></span>
            </div>
            
            <h1 class="page-title"><?php echo $product['name']; ?></h1>
            
            <!-- Product Detail -->
            <div class="product-detail-container">
                <div class="product-images">
                    <div class="main-image">
                        <?php if($product['image'] && file_exists('../uploads/products/' . $product['image'])): ?>
                            <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" style="width: 100%; height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <i class="fas fa-box-open"></i>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="product-info">
                    <span class="product-category"><?php echo $product['category_name'] ?? 'Uncategorized'; ?></span>
                    <h2 class="product-title"><?php echo $product['name']; ?></h2>
                    <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <div class="product-price">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                    
                    <?php
                    $stockClass = 'in-stock';
                    if($product['stock'] < 10) $stockClass = 'low-stock';
                    ?>
                    <div class="product-stock <?php echo $stockClass; ?>">
                        <?php echo $product['stock']; ?> pcs tersedia
                    </div>
                    
                    <div class="product-actions">
                        <div class="quantity-control">
                            <button class="quantity-btn" id="decreaseBtn">-</button>
                            <input type="number" id="quantityInput" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button class="quantity-btn" id="increaseBtn">+</button>
                        </div>
                        
                        <button class="btn btn-cart" id="addToCartBtn">
                            <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                        </button>
                        
                        <button class="btn btn-buy" id="buyNowBtn">
                            <i class="fas fa-shopping-cart"></i> Beli Sekarang
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if(count($related_products) > 0): ?>
                <div class="related-products">
                    <h2 class="section-title">Produk Terkait</h2>
                    
                    <div class="products-grid">
                        <?php foreach($related_products as $related): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if($related['image'] && file_exists('../uploads/products/' . $related['image'])): ?>
                                        <img src="../uploads/products/<?php echo $related['image']; ?>" alt="<?php echo $related['name']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-content">
                                    <h3 class="product-card-title" onclick="viewProduct(<?php echo $related['id']; ?>)">
                                        <?php echo $related['name']; ?>
                                    </h3>
                                    <div class="product-card-price">Rp<?php echo number_format($related['price'], 0, ',', '.'); ?></div>
                                    
                                    <?php
                                    $relatedStockClass = 'in-stock';
                                    if($related['stock'] < 10) $relatedStockClass = 'low-stock';
                                    ?>
                                    <div class="product-card-stock <?php echo $relatedStockClass; ?>">
                                        <?php echo $related['stock']; ?> pcs
                                    </div>
                                    
                                    <button class="btn btn-secondary" onclick="viewProduct(<?php echo $related['id']; ?>)">
                                        <i class="fas fa-eye"></i> Lihat Detail
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Quantity control
        const decreaseBtn = document.getElementById('decreaseBtn');
        const increaseBtn = document.getElementById('increaseBtn');
        const quantityInput = document.getElementById('quantityInput');
        const maxQuantity = <?php echo $product['stock']; ?>;
        
        decreaseBtn.addEventListener('click', function() {
            let current = parseInt(quantityInput.value);
            if(current > 1) {
                quantityInput.value = current - 1;
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            let current = parseInt(quantityInput.value);
            if(current < maxQuantity) {
                quantityInput.value = current + 1;
            }
        });
        
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if(value < 1) this.value = 1;
            if(value > maxQuantity) this.value = maxQuantity;
        });
        
        // Add to cart
        document.getElementById('addToCartBtn').addEventListener('click', function() {
            const quantity = parseInt(quantityInput.value);
            
            fetch('update_keranjang.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    action: 'add', 
                    product_id: <?php echo $product['id']; ?>, 
                    quantity: quantity 
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Produk "<?php echo $product['name']; ?>" berhasil ditambahkan ke keranjang!');
                    updateCartBadge();
                } else {
                    alert('Gagal menambahkan ke keranjang: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan ke keranjang');
            });
        });
        
        // Buy now
        document.getElementById('buyNowBtn').addEventListener('click', function() {
            const quantity = parseInt(quantityInput.value);
            
            if(quantity > <?php echo $product['stock']; ?>) {
                alert('Stok tidak mencukupi!');
                return;
            }
            
            fetch('prepare_checkout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    product_id: <?php echo $product['id']; ?>, 
                    quantity: quantity 
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'checkout.php';
                } else {
                    alert('Gagal mempersiapkan checkout: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mempersiapkan checkout');
            });
        });
        
        // View product
        function viewProduct(productId) {
            window.location.href = 'produk_detail.php?id=' + productId;
        }
        
        // Update cart badge
        function updateCartBadge() {
            fetch('get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.cart-badge');
                if(badge) {
                    badge.textContent = data.count;
                    if(data.count === 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.style.display = 'flex';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge();
        });
    </script>
</body>
</html>