<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../Auth/login.php');
    exit();
}

// Ambil data user lengkap termasuk foto profil
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    if(!$current_user) {
        header('Location: ../Auth/logout.php');
        exit();
    }
    
    // Ambil statistik dari database
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'petugas'");
    $total_petugas = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $total_admin = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $total_semua = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM categories");
    $total_categories = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transactions");
    $total_transactions = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT SUM(total_amount) as total FROM transactions WHERE status IN ('paid', 'shipped', 'delivered')");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
} catch(PDOException $e) {
    $total_users = 0;
    $total_petugas = 0;
    $total_admin = 0;
    $total_semua = 0;
    $total_products = 0;
    $total_categories = 0;
    $total_transactions = 0;
    $total_revenue = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Commerce Platform</title>
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
            cursor: pointer;
            position: relative;
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

        .submenu {
            display: none;
            padding-left: 40px;
            background: rgba(0, 0, 0, 0.1);
        }

        .submenu.show {
            display: block;
        }

        .submenu-item {
            padding: 12px 25px 12px 55px !important;
            font-size: 14px !important;
            color: rgba(255, 255, 255, 0.7) !important;
        }

        .submenu-item:hover {
            background: var(--sidebar-hover) !important;
            color: var(--white) !important;
            border-left-color: transparent !important;
            padding-left: 60px !important;
        }

        .submenu-item.active {
            color: var(--white) !important;
            background: rgba(55, 81, 254, 0.3) !important;
        }

        .menu-arrow {
            margin-left: auto;
            transition: transform 0.3s;
            font-size: 12px;
        }

        .menu-arrow.rotate {
            transform: rotate(90deg);
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

        .notification {
            position: relative;
            cursor: pointer;
        }

        .notification i {
            font-size: 20px;
            color: var(--gray);
        }

        .notification-badge {
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.primary {
            background: rgba(55, 81, 254, 0.1);
            color: var(--primary);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            font-size: 13px;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 30px 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(55, 81, 254, 0.1), transparent);
            transition: left 0.5s;
        }

        .action-card:hover::before {
            left: 100%;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .action-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: var(--primary);
            transition: transform 0.3s;
        }

        .action-card:hover .action-icon {
            transform: scale(1.2);
        }

        .action-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .action-desc {
            font-size: 13px;
            color: var(--gray);
        }

        /* Modal Popup */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--white);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            border-radius: 20px 20px 0 0;
            text-align: center;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            color: var(--white);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .modal-option {
            padding: 20px;
            background: var(--light-gray);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--dark);
            border: 2px solid transparent;
        }

        .modal-option:hover {
            background: var(--white);
            border-color: var(--primary);
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }

        .modal-option i {
            font-size: 24px;
            color: var(--primary);
            width: 30px;
        }

        .modal-option-text {
            flex: 1;
        }

        .modal-option-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .modal-option-desc {
            font-size: 13px;
            color: var(--gray);
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
            
            .logo-text, .logo-subtext, .menu-text, .menu-title, .menu-badge, .submenu {
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
            
            .stats-grid,
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .modal {
                width: 95%;
                max-width: 450px;
            }
        }
    </style>
</head>
<body>
    <!-- Modal Popups -->
    <div class="modal-overlay" id="laporanModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('laporanModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Laporan</div>
            </div>
            <div class="modal-body">
                <div class="modal-options">
                    <a href="laporan_transaksi.php" class="modal-option">
                        <i class="fas fa-file-invoice"></i>
                        <div class="modal-option-text">
                            <div class="modal-option-title">Laporan Transaksi</div>
                            <div class="modal-option-desc">View transaction reports</div>
                        </div>
                    </a>
                    <a href="laporan_penjualan.php" class="modal-option">
                        <i class="fas fa-chart-line"></i>
                        <div class="modal-option-text">
                            <div class="modal-option-title">Laporan Penjualan</div>
                            <div class="modal-option-desc">View sales reports</div>
                        </div>
                    </a>
                    <a href="laporan_stok.php" class="modal-option">
                        <i class="fas fa-boxes"></i>
                        <div class="modal-option-text">
                            <div class="modal-option-title">Laporan Stok</div>
                            <div class="modal-option-desc">View inventory reports</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="produkModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('produkModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Kelola Produk</div>
            </div>
            <div class="modal-body">
                <div class="modal-options">
                    <a href="tambah_produk.php" class="modal-option">
                        <i class="fas fa-plus-circle"></i>
                        <div class="modal-option-text">
                            <div class="modal-option-title">Tambah Produk</div>
                            <div class="modal-option-desc">Add new product</div>
                        </div>
                    </a>
                    <a href="kelola_produk.php" class="modal-option">
                        <i class="fas fa-list"></i>
                        <div class="modal-option-text">
                            <div class="modal-option-title">Kelola Produk</div>
                            <div class="modal-option-desc">Manage all products</div>
                        </div>
                    </a>
                    <!-- <a href="ubah_produk.php" class="modal-option"> ULAH DIAKTIPKEUN NGEBUG EUY
                        <i class="fas fa-edit"></i> 
                        <div class="modal-option-text">
                            <div class="modal-option-title">Ubah Produk</div>
                            <div class="modal-option-desc">Edit product details</div>
                        </div>
                    </a> -->
                </div>
            </div>
        </div>
    </div>

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
                <a href="admin_dashboard.php" class="menu-item active">
                    <i class="fas fa-home menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </div>
            
            <!-- Management Section -->
            <div class="menu-section">
                <div class="menu-title">Management</div>
                <a href="kelola_user.php" class="menu-item">
                    <i class="fas fa-users menu-icon"></i>
                    <span class="menu-text">Kelola User</span>
                    <span class="menu-badge"><?php echo $total_users; ?></span>
                </a>
                <a href="kelola_petugas.php" class="menu-item">
                    <i class="fas fa-user-tie menu-icon"></i>
                    <span class="menu-text">Kelola Petugas</span>
                    <span class="menu-badge"><?php echo $total_petugas; ?></span>
                </a>
                <div class="menu-item" onclick="toggleSubmenu('produkSubmenu')">
                    <i class="fas fa-box menu-icon"></i>
                    <span class="menu-text">Kelola Produk</span>
                    <i class="fas fa-chevron-right menu-arrow" id="produkArrow"></i>
                </div>
                <div class="submenu" id="produkSubmenu">
                    <a href="tambah_produk.php" class="menu-item submenu-item">
                        <i class="fas fa-plus"></i>
                        <span class="menu-text">Tambah Produk</span>
                    </a>
                    <a href="kelola_produk.php" class="menu-item submenu-item">
                        <i class="fas fa-list"></i>
                        <span class="menu-text">Kelola Produk</span>
                    </a>
                    <!-- <a href="ubah_produk.php" class="menu-item submenu-item">
                        <i class="fas fa-edit"></i>
                        <span class="menu-text">Ubah Produk</span>
                    </a> -->
                </div>
                <a href="kelola_kategori.php" class="menu-item">
                    <i class="fas fa-tags menu-icon"></i>
                    <span class="menu-text">Kelola Kategori</span>
                    <span class="menu-badge"><?php echo $total_categories; ?></span>
                </a>
                <a href="kelola_transaksi.php" class="menu-item">
                    <i class="fas fa-shopping-bag menu-icon"></i>
                    <span class="menu-text">Kelola Transaksi</span>
                    <span class="menu-badge"><?php echo $total_transactions; ?></span>
                </a>
            </div>
            
            <!-- Reports Section -->
            <div class="menu-section">
                <div class="menu-title">Reports</div>
                <div class="menu-item" onclick="toggleSubmenu('laporanSubmenu')">
                    <i class="fas fa-chart-bar menu-icon"></i>
                    <span class="menu-text">Laporan</span>
                    <i class="fas fa-chevron-right menu-arrow" id="laporanArrow"></i>
                </div>
                <div class="submenu" id="laporanSubmenu">
                    <a href="laporan_transaksi.php" class="menu-item submenu-item">
                        <i class="fas fa-file-invoice"></i>
                        <span class="menu-text">Laporan Transaksi</span>
                    </a>
                    <a href="laporan_penjualan.php" class="menu-item submenu-item">
                        <i class="fas fa-chart-line"></i>
                        <span class="menu-text">Laporan Penjualan</span>
                    </a>
                    <a href="laporan_stok.php" class="menu-item submenu-item">
                        <i class="fas fa-boxes"></i>
                        <span class="menu-text">Laporan Stok</span>
                    </a>
                </div>
                <a href="backup_restore.php" class="menu-item">
                    <i class="fas fa-database menu-icon"></i>
                    <span class="menu-text">Backup & Restore</span>
                </a>
            </div>
            
            <!-- Settings Section -->
            <div class="menu-section">
                <div class="menu-title">Settings</div>
                <a href="profile.php" class="menu-item">
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
                <!-- <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div> -->
            </div>
            
            <div class="header-right">
                <div class="notification">
                    <!-- <i class="fas fa-bell"></i> -->
                    <!-- <span class="notification-badge">3</span> -->
                </div>
                
                <div class="user-profile" onclick="window.location.href='profile.php'">
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
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo $current_user['full_name']; ?>! Manage all e-commerce platform activities from here.</p>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="openModal('laporanModal')">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-value">Rp<?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12% from last month</span>
                    </div>
                </div>
                
                <div class="stat-card" onclick="openModal('produkModal')">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Products</div>
                            <div class="stat-value"><?php echo $total_products; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8% from last month</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Transactions</div>
                            <div class="stat-value"><?php echo $total_transactions; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+15% growth</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Categories</div>
                            <div class="stat-value"><?php echo $total_categories; ?></div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-minus"></i>
                        <span>No change</span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card" onclick="window.location.href='kelola_user.php'">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title">Kelola User</div>
                    <div class="action-desc">Manage user accounts</div>
                </div>
                
                <div class="action-card" onclick="window.location.href='kelola_petugas.php'">
                    <div class="action-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="action-title">Kelola Petugas</div>
                    <div class="action-desc">Manage staff accounts</div>
                </div>
                
                <div class="action-card" onclick="openModal('produkModal')">
                    <div class="action-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="action-title">Kelola Produk</div>
                    <div class="action-desc">Manage products</div>
                </div>
                
                <div class="action-card" onclick="window.location.href='kelola_transaksi.php'">
                    <div class="action-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="action-title">Kelola Transaksi</div>
                    <div class="action-desc">View transactions</div>
                </div>
                
                <div class="action-card" onclick="openModal('laporanModal')">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-title">Laporan</div>
                    <div class="action-desc">View reports</div>
                </div>
                
                <div class="action-card" onclick="window.location.href='../Auth/logout.php'">
                    <div class="action-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="action-title">Logout</div>
                    <div class="action-desc">Sign out</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle submenu
        function toggleSubmenu(submenuId) {
            const submenu = document.getElementById(submenuId);
            const arrow = document.getElementById(submenuId.replace('Submenu', 'Arrow'));
            
            submenu.classList.toggle('show');
            arrow.classList.toggle('rotate');
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        });

        // Active menu item
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item:not([onclick])');
            const currentPath = window.location.pathname;
            
            menuItems.forEach(item => {
                const href = item.getAttribute('href');
                if (currentPath.includes(href)) {
                    item.classList.add('active');
                    
                    // Also activate parent if it's a submenu item
                    const parentMenu = item.closest('.submenu');
                    if (parentMenu) {
                        parentMenu.classList.add('show');
                        const arrowId = parentMenu.id.replace('Submenu', 'Arrow');
                        const arrow = document.getElementById(arrowId);
                        if (arrow) arrow.classList.add('rotate');
                    }
                }
            });
        });
    </script>
</body>
</html>