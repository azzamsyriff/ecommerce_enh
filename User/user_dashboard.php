<?php
session_start();
require_once '../config.php';

// ✅ MODIFIKASI: Tidak redirect ke login, tapi cek apakah user sudah login
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';

// Ambil data user hanya jika sudah login
$current_user = null;
$cart_count = 0;

if($is_logged_in) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $current_user = $stmt->fetch();
        
        // Ambil jumlah item di keranjang
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $cart_result = $stmt->fetch();
        $cart_count = $cart_result['total'] ?? 0;
    } catch(PDOException $e) {
        // Abaikan error, tetap tampilkan produk untuk guest
    }
}

// Ambil produk dengan pagination (bisa diakses guest)
try {
    $limit = 12;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    
    // Search & filter
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    
    $query = "SELECT p.*, c.name as category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.status = 'active' AND p.stock > 0";
    $params = [];
    
    if($search) {
        $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if($category_filter) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $category_filter;
    }
    
    $query .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    
    $stmt = $conn->prepare($query);
    foreach($params as $key => $value) {
        if($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Total produk untuk pagination
    $count_query = "SELECT COUNT(*) as total FROM products p WHERE p.status = 'active' AND p.stock > 0";
    $count_params = [];
    
    if($search) {
        $count_query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
        $count_params[':search'] = "%$search%";
    }
    
    if($category_filter) {
        $count_query .= " AND p.category_id = :category_id";
        $count_params[':category_id'] = $category_filter;
    }
    
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $total_products = $stmt->fetch()['total'];
    $total_pages = ceil($total_products / $limit);
    
    // Ambil kategori untuk filter
    $stmt = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $products = [];
    $categories = [];
    $total_products = 0;
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - E-Commerce Platform</title>
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
        .menu-item.guest { opacity: 0.6; cursor: not-allowed; }
        .menu-item.guest:hover { background: var(--sidebar-hover); opacity: 0.8; }
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
        .cart-icon.disabled { cursor: not-allowed; opacity: 0.6; }
        .cart-badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: var(--white); width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; }
        .user-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .user-profile.guest { cursor: default; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: var(--white); font-weight: 600; font-size: 16px; overflow: hidden; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: var(--dark); }
        .user-role { font-size: 12px; color: var(--gray); }
        .dashboard-content { padding: 30px 40px; }
        .page-title { font-size: 28px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .page-subtitle { color: var(--gray); font-size: 16px; margin-bottom: 30px; }
        .filters { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .filters-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 15px; }
        .filters-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 13px; font-weight: 500; color: #374151; }
        .filter-group input, .filter-group select { padding: 10px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .product-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: var(--shadow); transition: all 0.3s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .product-image { width: 100%; height: 200px; object-fit: cover; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 48px; }
        .product-content { padding: 20px; }
        .product-category { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }
        .product-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 8px; display: -webkit-box; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; }
        .product-title:hover { color: var(--primary); }
        .product-description { font-size: 14px; color: #6b7280; margin-bottom: 15px; display: -webkit-box; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-price { font-size: 20px; font-weight: 700; color: #10b981; margin-bottom: 15px; }
        .product-stock { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 15px; }
        .product-stock.in-stock { background: #d1fae5; color: #065f46; }
        .product-stock.low-stock { background: #fef3c7; color: #92400e; }
        .product-actions { display: flex; gap: 10px; }
        .btn { display: inline-block; padding: 10px 15px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; flex: 1; text-align: center; }
        .btn:hover { background: #2d43d9; transform: translateY(-2px); }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-cart { background: #10b981; }
        .btn-cart:hover { background: #0da271; }
        .btn-buy { background: #f59e0b; }
        .btn-buy:hover { background: #d97706; }
        .btn:disabled { background: #9ca3af; cursor: not-allowed; transform: none; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .page-item { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #374151; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .page-item:hover:not(.active) { background: #e5e7eb; }
        .page-item.active { background: #3751fe; color: white; }
        .page-item.prev, .page-item.next { width: auto; padding: 0 15px; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 20px; }
        .empty-state h3 { color: #374151; font-size: 20px; margin-bottom: 10px; }
        .empty-state p { color: #6b7280; margin-bottom: 20px; }
        
        /* 🔒 Modal Login Required */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-content { background: white; width: 90%; max-width: 450px; border-radius: 16px; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); transform: scale(0.95); transition: transform 0.3s ease; text-align: center; animation: modalPop 0.3s ease-out; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        @keyframes modalPop { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .modal-icon { width: 70px; height: 70px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 32px; }
        .modal-title { font-size: 22px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .modal-text { color: var(--gray); font-size: 15px; margin-bottom: 25px; line-height: 1.5; }
        .modal-buttons { display: flex; gap: 12px; }
        .modal-btn { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; font-size: 14px; }
        .modal-btn-primary { background: var(--primary); color: white; }
        .modal-btn-primary:hover { background: var(--primary-dark); }
        .modal-btn-secondary { background: var(--light-gray); color: var(--gray); }
        .modal-btn-secondary:hover { background: #e2e8f0; color: var(--dark); }
        
        @media (max-width: 1024px) { .sidebar { width: 240px; } .main-content { margin-left: 240px; } .search-bar input { width: 200px; } }
        @media (max-width: 768px) {
            .sidebar { width: 70px; overflow: hidden; }
            .sidebar:hover { width: 280px; overflow: visible; }
            .logo-text, .logo-subtext, .menu-text, .menu-title, .menu-badge { display: none; }
            .sidebar:hover .logo-text, .sidebar:hover .logo-subtext, .sidebar:hover .menu-text, .sidebar:hover .menu-title, .sidebar:hover .menu-badge { display: block; }
            .menu-icon { margin-right: 0; }
            .main-content { margin-left: 70px; }
            .search-bar { display: none; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
            .dashboard-content { padding: 20px; }
            .product-actions { flex-direction: column; }
            .btn { flex: none; }
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
            <div class="menu-section">
                <div class="menu-title">Main Menu</div>
                <a href="user_dashboard.php" class="menu-item active">
                    <i class="fas fa-home menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </div>
            <div class="menu-section">
                <div class="menu-title">Shopping</div>
                <a href="user_dashboard.php" class="menu-item">
                    <i class="fas fa-shopping-bag menu-icon"></i>
                    <span class="menu-text">Produk</span>
                </a>
                <!-- 🔒 Menu keranjang & riwayat hanya untuk user login -->
                <a href="<?php echo $is_logged_in ? 'keranjang.php' : '#'; ?>" class="menu-item <?php echo !$is_logged_in ? 'guest' : ''; ?>" <?php echo !$is_logged_in ? 'onclick="showLoginModal(\'keranjang\'); return false;"' : ''; ?>>
                    <i class="fas fa-shopping-cart menu-icon"></i>
                    <span class="menu-text">Keranjang</span>
                    <?php if($is_logged_in && $cart_count > 0): ?>
                        <span class="menu-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo $is_logged_in ? 'riwayat_transaksi.php' : '#'; ?>" class="menu-item <?php echo !$is_logged_in ? 'guest' : ''; ?>" <?php echo !$is_logged_in ? 'onclick="showLoginModal(\'riwayat\'); return false;"' : ''; ?>>
                    <i class="fas fa-history menu-icon"></i>
                    <span class="menu-text">Riwayat Transaksi</span>
                </a>
            </div>
            <div class="menu-section">
                <div class="menu-title">Settings</div>
                <a href="<?php echo $is_logged_in ? 'profile_user.php' : '../Auth/login.php'; ?>" class="menu-item">
                    <i class="fas fa-user menu-icon"></i>
                    <span class="menu-text"><?php echo $is_logged_in ? 'Profile' : 'Login'; ?></span>
                </a>
                <?php if($is_logged_in): ?>
                <a href="../Auth/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt menu-icon"></i>
                    <span class="menu-text">Logout</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari produk..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="header-right">
                <!-- 🔒 Cart icon untuk guest -->
                <div class="cart-icon <?php echo !$is_logged_in ? 'disabled' : ''; ?>" <?php echo $is_logged_in ? 'onclick="window.location.href=\'keranjang.php\'"' : 'onclick="showLoginModal(\'keranjang\')"'; ?>>
                    <i class="fas fa-shopping-cart"></i>
                    <?php if($is_logged_in && $cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </div>
                <!-- 🔒 User profile untuk guest -->
                <div class="user-profile <?php echo !$is_logged_in ? 'guest' : ''; ?>" <?php echo $is_logged_in ? 'onclick="window.location.href=\'profile_user.php\'"' : 'onclick="showLoginModal(\'profile\')"'; ?>>
                    <div class="avatar">
                        <?php if($is_logged_in && $current_user['profile_picture'] && file_exists('../uploads/profiles/' . $current_user['profile_picture'])): ?>
                            <img src="../uploads/profiles/<?php echo $current_user['profile_picture']; ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $is_logged_in ? $current_user['full_name'] : 'Guest'; ?></div>
                        <div class="user-role"><?php echo $is_logged_in ? ucfirst($current_user['role']) : 'Belum Login'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <h1 class="page-title">
                <?php echo $is_logged_in ? 'Selamat Datang, ' . htmlspecialchars($current_user['full_name']) . '!' : 'Selamat Datang di E-Commerce!'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo $is_logged_in ? 'Temukan produk-produk terbaik untuk kebutuhan Anda.' : 'Jelajahi produk kami. Login untuk membeli dan menikmati fitur lengkap.'; ?>
            </p>
            
            <!-- Filters -->
            <div class="filters">
                <div class="filters-title"><i class="fas fa-filter"></i> Filter Produk</div>
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label for="category">Kategori</label>
                        <select id="category" name="category">
                            <option value="">Semua Kategori</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group" style="align-self: flex-end;">
                        <button type="submit" class="btn" style="width: 100%;"><i class="fas fa-filter"></i> Terapkan Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- Products Grid -->
            <?php if(count($products) > 0): ?>
                <div class="products-grid">
                    <?php foreach($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if($product['image'] && file_exists('../uploads/products/' . $product['image'])): ?>
                                    <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-content">
                                <span class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                                <h3 class="product-title" onclick="viewProduct(<?php echo $product['id']; ?>)"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                                <div class="product-price">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                                <?php $stockClass = $product['stock'] < 10 ? 'low-stock' : 'in-stock'; ?>
                                <div class="product-stock <?php echo $stockClass; ?>"><?php echo $product['stock']; ?> pcs tersedia</div>
                                <div class="product-actions">
                                    <!-- 🔒 Tombol keranjang untuk guest -->
                                    <button class="btn btn-cart" onclick="<?php echo $is_logged_in ? "addToCart({$product['id']}, '" . addslashes($product['name']) . "', {$product['price']})" : "showLoginModal('keranjang')"; ?>">
                                        <i class="fas fa-cart-plus"></i> <?php echo $is_logged_in ? 'Keranjang' : 'Login Dulu'; ?>
                                    </button>
                                    <!-- 🔒 Tombol beli untuk guest -->
                                    <button class="btn btn-buy" onclick="<?php echo $is_logged_in ? "buyNow({$product['id']}, '" . addslashes($product['name']) . "', {$product['price']}, {$product['stock']})" : "showLoginModal('checkout')"; ?>">
                                        <i class="fas fa-shopping-cart"></i> <?php echo $is_logged_in ? 'Beli' : 'Login Dulu'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>" class="page-item prev"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>" class="page-item <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>" class="page-item next"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-boxes"></i>
                    <h3>Tidak ada produk</h3>
                    <p>Maaf, tidak ada produk yang tersedia saat ini.</p>
                    <?php if($search || $category_filter): ?>
                        <button class="btn" onclick="window.location.href='user_dashboard.php'"><i class="fas fa-undo"></i> Reset Filter</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 🔒 Modal Login Required -->
    <div class="modal-overlay" id="loginModal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-lock"></i></div>
            <h3 class="modal-title" id="modalTitle">Harap Login Terlebih Dahulu</h3>
            <p class="modal-text" id="modalText">Fitur ini hanya tersedia untuk user yang sudah login. Silakan login untuk melanjutkan.</p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-secondary" onclick="closeLoginModal()">Nanti Saja</button>
                <button class="modal-btn modal-btn-primary" onclick="redirectToLogin()">Login Sekarang</button>
            </div>
        </div>
    </div>
    
    <script>
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        let modalTarget = '';
        
        // 🔒 Fungsi tampilkan modal login
        function showLoginModal(target) {
            if(isLoggedIn) return;
            modalTarget = target;
            const titles = {
                'keranjang': 'Tambah ke Keranjang',
                'checkout': 'Beli Sekarang',
                'riwayat': 'Lihat Riwayat',
                'profile': 'Akses Profile'
            };
            const texts = {
                'keranjang': 'Silakan login untuk menambahkan produk ke keranjang belanja Anda.',
                'checkout': 'Silakan login untuk melanjutkan proses pembelian.',
                'riwayat': 'Silakan login untuk melihat riwayat transaksi Anda.',
                'profile': 'Silakan login untuk mengakses halaman profile.'
            };
            document.getElementById('modalTitle').textContent = titles[target] || 'Harap Login';
            document.getElementById('modalText').textContent = texts[target] || 'Fitur ini hanya tersedia untuk user yang sudah login.';
            document.getElementById('loginModal').classList.add('show');
        }
        
        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('show');
        }
        
        function redirectToLogin() {
            closeLoginModal();
            // Simpan halaman sebelumnya agar bisa redirect kembali setelah login
            const currentUrl = window.location.href;
            window.location.href = '../Auth/login.php?redirect=' + encodeURIComponent(currentUrl);
        }
        
        // Tutup modal jika klik di luar
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if(e.target === this) closeLoginModal();
        });
        
        // 🔒 Fungsi addToCart (hanya jalan jika login)
        function addToCart(productId, productName, price) {
            if(!isLoggedIn) { showLoginModal('keranjang'); return; }
            fetch('update_keranjang.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', product_id: productId, quantity: 1 })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Produk "' + productName + '" berhasil ditambahkan ke keranjang!');
                    updateCartBadge();
                } else {
                    alert('Gagal menambahkan ke keranjang: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan ke keranjang');
            });
        }
        
        // 🔒 Fungsi buyNow (hanya jalan jika login)
        function buyNow(productId, productName, price, stock) {
            if(!isLoggedIn) { showLoginModal('checkout'); return; }
            if(stock <= 0) { alert('Maaf, produk ini sudah habis stok!'); return; }
            fetch('prepare_checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, quantity: 1 })
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
        }
        
        function viewProduct(productId) {
            window.location.href = 'produk_detail.php?id=' + productId;
        }
        
        function updateCartBadge() {
            if(!isLoggedIn) return;
            fetch('get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.cart-badge');
                if(badge) {
                    badge.textContent = data.count;
                    badge.style.display = data.count === 0 ? 'none' : 'flex';
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Live search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
                const searchValue = this.value.trim();
                if(searchValue) {
                    window.location.href = '?search=' + encodeURIComponent(searchValue);
                } else {
                    window.location.href = 'user_dashboard.php';
                }
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if(isLoggedIn) updateCartBadge();
        });
    </script>
</body>
</html>