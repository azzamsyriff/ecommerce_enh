<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role petugas
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';

// Build query
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if($search) {
    $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if($category_filter) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

$query .= " ORDER BY p.stock ASC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    $categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch(PDOException $e) {
    $products = [];
    $categories = [];
}

// Calculate statistics
$total_products = count($products);
$low_stock = array_filter($products, fn($p) => $p['stock'] < 10 && $p['stock'] > 0);
$out_of_stock = array_filter($products, fn($p) => $p['stock'] == 0);
$in_stock = array_filter($products, fn($p) => $p['stock'] >= 10);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok - Petugas Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #3751fe;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            box-shadow: 0 4px 8px rgba(55, 81, 254, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-warning {
            background: #f59e0b;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }
        
        .stat-value.low {
            color: #ef4444;
        }
        
        .stat-value.out {
            color: #9ca3af;
        }
        
        .stat-value.good {
            color: #10b981;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .filters-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: #374151;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3751fe;
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 30px;
            background: linear-gradient(135deg, #3751fe 0%, #667eea 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        td {
            padding: 15px 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #4b5563;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .product-image {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .product-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .product-desc {
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .stock-badge.in-stock {
            background: #d1fae5;
            color: #065f46;
        }
        
        .stock-badge.low-stock {
            background: #fef3c7;
            color: #92400e;
        }
        
        .stock-badge.out-of-stock {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .category-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .price {
            font-weight: 600;
            color: #1f2937;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-boxes"></i> Laporan Stok</h1>
            <a href="petugas_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value low"><?php echo count($low_stock); ?></div>
                <div class="stat-label">Stok Menipis</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value out"><?php echo count($out_of_stock); ?></div>
                <div class="stat-label">Habis Stok</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value good"><?php echo count($in_stock); ?></div>
                <div class="stat-label">Stok Aman</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Produk</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filters-title">
                <i class="fas fa-filter"></i> Filter Produk
            </div>
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="search">Pencarian Produk</label>
                    <input type="text" id="search" name="search" placeholder="Cari produk..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="category">Kategori</label>
                    <select id="category" name="category">
                        <option value="">Semua Kategori</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="align-self: flex-end;">
                    <button type="submit" class="btn" style="width: 100%;">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i> Daftar Stok Produk
                </div>
                <div>
                    <?php echo $total_products; ?> produk
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($products) > 0): ?>
                        <?php foreach($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <?php if($product['image'] && file_exists('../uploads/products/' . $product['image'])): ?>
                                            <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6b7280;">
                                                <i class="fas fa-box"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="product-name"><?php echo $product['name']; ?></div>
                                            <div class="product-desc"><?php echo substr($product['description'], 0, 50); ?>...</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($product['category_name']): ?>
                                        <span class="category-badge"><?php echo $product['category_name']; ?></span>
                                    <?php else: ?>
                                        <span class="category-badge">No Category</span>
                                    <?php endif; ?>
                                </td>
                                <td class="price">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php
                                    $stockClass = 'in-stock';
                                    if($product['stock'] == 0) $stockClass = 'out-of-stock';
                                    else if($product['stock'] < 10) $stockClass = 'low-stock';
                                    ?>
                                    <span class="stock-badge <?php echo $stockClass; ?>">
                                        <?php echo $product['stock']; ?> pcs
                                    </span>
                                </td>
                                <td>
                                    <span class="stock-badge <?php echo $stockClass; ?>">
                                        <?php
                                        if($product['stock'] == 0) echo 'Habis';
                                        else if($product['stock'] < 10) echo 'Menipis';
                                        else echo 'Aman';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="ubah_produk_petugas.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning" title="Edit Produk">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-box-open"></i>
                                    <p>Tidak ada produk</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>