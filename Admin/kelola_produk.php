<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../Auth/login.php');
    exit();
}

// Handle delete
if(isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute(['id' => $_GET['delete']]);
        $_SESSION['success'] = "Produk berhasil dihapus!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    header('Location: kelola_produk.php');
    exit();
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if(!empty($search)) {
    $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if($category_filter) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if($status_filter) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY p.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    $categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch(PDOException $e) {
    $products = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .container { max-width: 1400px; margin: 30px auto; padding: 20px; }
        .header { background: white; padding: 20px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #3751fe; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .btn { display: inline-block; padding: 12px 25px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2d43d9; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(55, 81, 254, 0.3); }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #0da271; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        .filters { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .filters-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 15px; }
        .filters-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 13px; font-weight: 500; color: #374151; }
        .filter-group input, .filter-group select { padding: 10px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .table-header { padding: 20px 30px; background: linear-gradient(135deg, #3751fe 0%, #667eea 100%); color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .table-title { font-size: 20px; font-weight: 600; }
        .table-stats { display: flex; gap: 20px; font-size: 14px; }
        .table-stats span { display: flex; align-items: center; gap: 5px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; }
        th { padding: 15px 20px; text-align: left; font-weight: 600; color: #374151; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #4b5563; }
        tr:hover { background: #f9fafb; }
        .product-image { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; background: #e5e7eb; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 24px; }
        .product-name { font-weight: 600; color: #1f2937; }
        .product-desc { font-size: 12px; color: #6b7280; margin-top: 3px; }
        .price-badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .stock-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .stock-badge.in-stock { background: #d1fae5; color: #065f46; }
        .stock-badge.low-stock { background: #fef3c7; color: #92400e; }
        .stock-badge.out-of-stock { background: #fee2e2; color: #991b1b; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .status-badge.active { background: #d1fae5; color: #065f46; }
        .status-badge.inactive { background: #e5e7eb; color: #4b5563; }
        .category-badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 13px; }
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
            <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            <a href="tambah_produk.php" class="btn"><i class="fas fa-plus"></i> Tambah Produk</a>
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
            <div class="filter-group">
                <label for="category">Kategori</label>
                <select id="category" name="category">
                    <option value="">Semua Kategori</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-group" style="align-self: flex-end;">
                <button type="submit" class="btn" style="width: 100%;"><i class="fas fa-search"></i> Cari</button>
            </div>
            <?php if(!empty($search)): ?>
            <div class="filter-group" style="align-self: flex-end;">
                <a href="kelola_produk.php" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-times"></i> Reset</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Table -->
    <div class="table-container">
        <div class="table-header">
            <div class="table-title"><i class="fas fa-list"></i> Daftar Produk</div>
            <div class="table-stats">
                <span><i class="fas fa-box"></i> 
                    <?php if(!empty($search)): ?>
                        Hasil: "<?php echo htmlspecialchars($search); ?>" (<?php echo count($products); ?> ditemukan)
                    <?php else: ?>
                        Total: <?php echo count($products); ?> produk
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="table-responsive">
            <?php if(count($products) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if($product['image']): ?>
                                        <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                    <?php else: ?>
                                        <div class="product-image"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
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
                                    <div class="product-desc"><?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>...</div>
                                </td>
                                <td>
                                    <?php if($product['category_name']): ?>
                                        <span class="category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                    <?php else: ?>
                                        <span class="category-badge">No Category</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="price-badge">Rp<?php echo number_format($product['price'], 0, ',', '.'); ?></span></td>
                                <td>
                                    <?php
                                    $stockClass = 'in-stock';
                                    if($product['stock'] == 0) $stockClass = 'out-of-stock';
                                    else if($product['stock'] < 10) $stockClass = 'low-stock';
                                    ?>
                                    <span class="stock-badge <?php echo $stockClass; ?>"><?php echo $product['stock']; ?> pcs</span>
                                </td>
                                <td><span class="status-badge <?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="ubah_produk.php?id=<?php echo $product['id']; ?>" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="kelola_produk.php?delete=<?php echo $product['id']; ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Yakin ingin menghapus produk ini?')"><i class="fas fa-trash"></i></a>
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
                    <p><?php echo !empty($search) ? 'Coba dengan kata kunci lain atau reset pencarian.' : 'Belum ada produk yang ditambahkan. Silakan tambahkan produk baru.'; ?></p>
                    <?php if(!empty($search)): ?>
                        <a href="kelola_produk.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset Pencarian</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>