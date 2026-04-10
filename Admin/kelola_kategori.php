<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../Auth/login.php');
    exit();
}

// Handle add kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_kategori'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    // Validasi nama kategori
    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = :name");
    $stmt->execute(['name' => $name]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Kategori dengan nama tersebut sudah ada!";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
            $stmt->execute([
                'name' => $name,
                'description' => $description
            ]);
            
            $_SESSION['success'] = "Kategori berhasil ditambahkan!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    header('Location: kelola_kategori.php');
    exit();
}

// Handle update kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_kategori'])) {
    $kategori_id = $_POST['kategori_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    // Validasi nama kategori uniqueness (kecuali untuk kategori sendiri)
    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = :name AND id != :id");
    $stmt->execute(['name' => $name, 'id' => $kategori_id]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Kategori dengan nama tersebut sudah ada!";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE categories SET name = :name, description = :description WHERE id = :id");
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'id' => $kategori_id
            ]);
            
            $_SESSION['success'] = "Kategori berhasil diupdate!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    header('Location: kelola_kategori.php');
    exit();
}

// Handle delete kategori
if(isset($_GET['delete'])) {
    $kategori_id = $_GET['delete'];
    
    try {
        // Cek apakah kategori digunakan oleh produk
        $stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = :id");
        $stmt->execute(['id' => $kategori_id]);
        $product_count = $stmt->fetch()['product_count'];
        
        if($product_count > 0) {
            $_SESSION['error'] = "Kategori tidak bisa dihapus karena masih digunakan oleh $product_count produk!";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $kategori_id]);
            $_SESSION['success'] = "Kategori berhasil dihapus!";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    header('Location: kelola_kategori.php');
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id WHERE 1=1";
$params = [];

if($search) {
    $query .= " AND (c.name LIKE :search OR c.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " GROUP BY c.id ORDER BY c.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Admin Dashboard</title>
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
            flex-wrap: wrap;
            gap: 15px;
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
        
        .btn-danger {
            background: #ef4444;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-warning {
            background: #f59e0b;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
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
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
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
            padding: 20px 30px;
            background: linear-gradient(135deg, #3751fe 0%, #667eea 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
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
            color: white;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3751fe;
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        
        .filter-group input {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-group input:focus {
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
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .table-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
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
        
        .category-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 16px;
        }
        
        .category-desc {
            font-size: 13px;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .product-count {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .date-badge {
            display: inline-block;
            background: #f3f4f6;
            color: #4b5563;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #374151;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6b7280;
            margin-bottom: 20px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <h1><i class="fas fa-tags"></i> Kelola Kategori</h1>
            <button class="btn" onclick="openModal('addKategoriModal')">
                <i class="fas fa-plus"></i> Tambah Kategori
            </button>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filters-title">
                <i class="fas fa-filter"></i> Filter Kategori
            </div>
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="search">Pencarian</label>
                    <input type="text" id="search" name="search" placeholder="Cari kategori..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group" style="align-self: flex-end;">
                    <button type="submit" class="btn" style="width: 100%;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i> Daftar Kategori
                </div>
                <div class="table-stats">
                    <span><i class="fas fa-tags"></i> Total: <?php echo count($categories); ?> kategori</span>
                </div>
            </div>
            
            <?php if(count($categories) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Produk Terkait</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categories as $category): ?>
                            <tr>
                                <td>
                                    <div class="category-name"><?php echo $category['name']; ?></div>
                                </td>
                                <td>
                                    <?php if($category['description']): ?>
                                        <div class="category-desc"><?php echo substr($category['description'], 0, 100); ?>...</div>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="product-count">
                                        <?php echo $category['product_count']; ?> produk
                                    </span>
                                </td>
                                <td>
                                    <span class="date-badge">
                                        <?php echo date('d M Y', strtotime($category['created_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="editKategori(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')" class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if($category['product_count'] == 0): ?>
                                            <a href="kelola_kategori.php?delete=<?php echo $category['id']; ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Yakin ingin menghapus kategori ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-danger" title="Tidak bisa dihapus (masih digunakan)" disabled>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3>Tidak ada kategori</h3>
                    <p>Belum ada kategori yang dibuat.</p>
                    <button class="btn" onclick="openModal('addKategoriModal')">
                        <i class="fas fa-plus"></i> Tambah Kategori Pertama
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Kategori Modal -->
    <div class="modal-overlay" id="addKategoriModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('addKategoriModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Tambah Kategori Baru</div>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Nama Kategori <span style="color: red;">*</span></label>
                        <input type="text" id="name" name="name" required placeholder="Masukkan nama kategori">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Deskripsi (opsional)</label>
                        <textarea id="description" name="description" placeholder="Masukkan deskripsi kategori"></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Nama kategori harus unik dan tidak boleh sama dengan kategori yang sudah ada.
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="add_kategori" class="btn">
                            <i class="fas fa-save"></i> Simpan Kategori
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addKategoriModal')">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Kategori Modal -->
    <div class="modal-overlay" id="editKategoriModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editKategoriModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Edit Kategori</div>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="kategori_id" id="editKategoriId">
                    
                    <div class="form-group">
                        <label for="edit_name">Nama Kategori <span style="color: red;">*</span></label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Deskripsi (opsional)</label>
                        <textarea id="edit_description" name="description"></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Nama kategori harus unik dan tidak boleh sama dengan kategori yang sudah ada.
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="update_kategori" class="btn">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editKategoriModal')">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // Edit kategori
        function editKategori(id, name, description) {
            document.getElementById('editKategoriId').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            
            document.getElementById('editKategoriModal').classList.add('show');
        }
    </script>
</body>
</html>