<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role petugas
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

// Ambil parameter search dari URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ambil data produk dengan filter search
try {
    if(!empty($search)) {
        // Query dengan filter pencarian (nama produk atau deskripsi)
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.name LIKE :search OR p.description LIKE :search
                                ORDER BY p.created_at DESC");
        $stmt->execute(['search' => "%$search%"]);
    } else {
        // Query tanpa filter (tampilkan semua)
        $stmt = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC");
    }
    $products = $stmt->fetchAll();
    
    // Hitung total products (sesuai hasil filter)
    $total_products = count($products);
    
} catch(PDOException $e) {
    $products = [];
    $total_products = 0;
    error_log("Error in kelola_produk_petugas.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Petugas Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #3751fe; --primary-dark: #2d43d9; --secondary: #667eea; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --dark: #1f2937; --gray: #6b7280; --light-gray: #f3f4f6; --white: #ffffff; --sidebar-bg: #1e293b; --sidebar-hover: #334155; --card-bg: #ffffff; --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fb; display: flex; min-height: 100vh; }
        .container { max-width: 1400px; margin: 30px auto; padding: 20px; }
        .header { background: white; padding: 20px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #3751fe; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .btn { display: inline-block; padding: 12px 25px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2d43d9; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(55, 81, 254, 0.3); }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        .filters { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .filters-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 15px; }
        .filters-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 8px; font-size: 14px; }
        .filter-group input, .filter-group select { padding: 10px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .table-header { padding: 20px 30px; background: linear-gradient(135deg, #3751fe 0%, #667eea 100%); color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .table-title { font-size: 20px; font-weight: 600; }
        .table-stats { display: flex; gap: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; }
        th { padding: 15px 20px; text-align: left; font-weight: 600; color: #374151; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #4b5563; }
        tr:hover { background: #f9fafb; }
        .product-image { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .product-name { font-weight: 600; color: #1f2937; }
        .category-badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .price { font-weight: 600; color: #10b981; }
        .stock { font-weight: 500; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .status-badge.active { background: #d1fae5; color: #065f46; }
        .status-badge.inactive { background: #e5e7eb; color: #4b5563; }
        .action-buttons { display: flex; gap: 8px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 20px; }
        .empty-state h3 { color: #374151; font-size: 20px; margin-bottom: 10px; }
        .empty-state p { color: #6b7280; margin-bottom: 20px; }
        .search-highlight { background: #fff3cd; padding: 2px 4px; border-radius: 3px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-box"></i> Kelola Produk</h1>
            <div style="display: flex; gap: 10px;">
                <a href="petugas_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                <a href="tambah_produk_petugas.php" class="btn"><i class="fas fa-plus"></i> Tambah Produk</a>
            </div>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filters-title"><i class="fas fa-filter"></i> Filter Produk</div>
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="search">Pencarian</label>
                    <input type="text" id="search" name="search" placeholder="Cari nama atau deskripsi produk..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group" style="align-self: flex-end;">
                    <button type="submit" class="btn" style="width: 100%;"><i class="fas fa-search"></i> Cari</button>
                </div>
                <?php if(!empty($search)): ?>
                <div class="filter-group" style="align-self: flex-end;">
                    <a href="kelola_produk_petugas.php" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-times"></i> Reset</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title"><i class="fas fa-list"></i> Daftar Produk</div>
                <div class="table-stats">
                    <span><i class="fas fa-boxes"></i> 
                        <?php if(!empty($search)): ?>
                            Hasil: "<?php echo htmlspecialchars($search); ?>" (<?php echo $total_products; ?> ditemukan)
                        <?php else: ?>
                            Total: <?php echo $total_products; ?> produk
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <?php if(count($products) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if($product['image'] && file_exists('../uploads/products/' . $product['image'])): ?>
                                                <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6b7280;"><i class="fas fa-box"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="product-name">
                                                    <?php 
                                                    // Highlight search keyword in product name
                                                    if(!empty($search) && stripos($product['name'], $search) !== false) {
                                                        echo preg_replace('/(' . preg_quote($search, '/') . ')/i', '<span class="search-highlight">$1</span>', htmlspecialchars($product['name']));
                                                    } else {
                                                        echo htmlspecialchars($product['name']);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="price">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></span></td>
                                    <td><span class="stock"><?php echo $product['stock']; ?></span></td>
                                    <td><span class="category-badge"><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></span></td>
                                    <td><span class="status-badge <?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="ubah_produk_petugas.php?id=<?php echo $product['id']; ?>" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3><?php echo !empty($search) ? 'Produk tidak ditemukan' : 'Tidak ada produk'; ?></h3>
                        <p><?php echo !empty($search) ? 'Coba dengan kata kunci lain atau reset pencarian.' : 'Belum ada produk yang terdaftar.'; ?></p>
                        <?php if(!empty($search)): ?>
                            <a href="kelola_produk_petugas.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset Pencarian</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>