<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role petugas
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect ke kelola_produk_petugas jika tidak ada ID atau ID tidak valid
if($product_id <= 0) {
    $_SESSION['error'] = "Silakan pilih produk yang ingin diubah dari halaman Kelola Produk";
    header('Location: kelola_produk_petugas.php');
    exit();
}

// Get product data
try {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $product = $stmt->fetch();
    
    if(!$product) {
        $_SESSION['error'] = "Produk dengan ID $product_id tidak ditemukan!";
        header('Location: kelola_produk_petugas.php');
        exit();
    }
    
    $categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: kelola_produk_petugas.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    $status = $_POST['status'];
    
    // Handle image upload
    $image = $product['image']; // Keep old image by default
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/products/";
        if(!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Delete old image if exists
        if($product['image'] && file_exists($target_dir . $product['image'])) {
            unlink($target_dir . $product['image']);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $filename;
        
        if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = $filename;
        }
    }
    
    try {
        $stmt = $conn->prepare("UPDATE products SET name = :name, description = :description, price = :price, stock = :stock, category_id = :category_id, image = :image, status = :status WHERE id = :id");
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'stock' => $stock,
            'category_id' => $category_id,
            'image' => $image,
            'status' => $status,
            'id' => $product_id
        ]);
        
        $_SESSION['success'] = "Produk berhasil diupdate!";
        header('Location: kelola_produk_petugas.php');
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Produk - Petugas Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        
        .container {
            max-width: 1000px;
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
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3751fe;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
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
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .current-image {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .current-image img {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3751fe;
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
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
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-edit"></i> Ubah Produk</h1>
        <a href="kelola_produk_petugas.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
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
    
    <div class="form-container">
        <div class="form-title">
            <i class="fas fa-edit"></i> Form Edit Produk: <?php echo htmlspecialchars($product['name']); ?>
        </div>
        
        <?php if($product['image']): ?>
            <div class="current-image">
                <p><strong>Gambar Saat Ini:</strong></p>
                <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Nama Produk <span style="color: red;">*</span></label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($product['name']); ?>" placeholder="Masukkan nama produk">
            </div>
            
            <div class="form-group">
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description" placeholder="Masukkan deskripsi produk"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Harga (Rp) <span style="color: red;">*</span></label>
                <input type="number" id="price" name="price" required value="<?php echo $product['price']; ?>" placeholder="Masukkan harga produk" min="0">
            </div>
            
            <div class="form-group">
                <label for="stock">Stok <span style="color: red;">*</span></label>
                <input type="number" id="stock" name="stock" required value="<?php echo $product['stock']; ?>" placeholder="Masukkan jumlah stok" min="0">
            </div>
            
            <div class="form-group">
                <label for="category_id">Kategori <span style="color: red;">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="image">Ganti Gambar (opsional)</label>
                <input type="file" id="image" name="image" accept="image/*">
                <p style="font-size: 12px; color: #6b7280; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Biarkan kosong jika tidak ingin mengganti gambar
                </p>
            </div>
            
            <div class="form-group">
                <label for="status">Status <span style="color: red;">*</span></label>
                <select id="status" name="status" required>
                    <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <a href="kelola_produk_petugas.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>