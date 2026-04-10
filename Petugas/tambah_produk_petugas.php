<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role petugas
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
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
    $image = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/products/";
        if(!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $filename;
        
        if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = $filename;
        }
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, category_id, image, status) VALUES (:name, :description, :price, :stock, :category_id, :image, :status)");
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'stock' => $stock,
            'category_id' => $category_id,
            'image' => $image,
            'status' => $status
        ]);
        
        $_SESSION['success'] = "Produk berhasil ditambahkan!";
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
    <title>Tambah Produk - Petugas Dashboard</title>
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
            <h1><i class="fas fa-box"></i> Tambah Produk</h1>
            <div style="display: flex; gap: 10px;">
                <a href="petugas_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <a href="kelola_produk_petugas.php" class="btn">
                    <i class="fas fa-list"></i> Daftar Produk
                </a>
            </div>
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
                <i class="fas fa-plus-circle"></i> Form Tambah Produk
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Nama Produk <span style="color: red;">*</span></label>
                    <input type="text" id="name" name="name" required placeholder="Masukkan nama produk">
                </div>
                
                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" placeholder="Masukkan deskripsi produk"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="price">Harga (Rp) <span style="color: red;">*</span></label>
                    <input type="number" id="price" name="price" required placeholder="Masukkan harga produk" min="0">
                </div>
                
                <div class="form-group">
                    <label for="stock">Stok <span style="color: red;">*</span></label>
                    <input type="number" id="stock" name="stock" required placeholder="Masukkan jumlah stok" min="0">
                </div>
                
                <div class="form-group">
                    <label for="category_id">Kategori <span style="color: red;">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT * FROM categories ORDER BY name");
                            $categories = $stmt->fetchAll();
                            foreach($categories as $category):
                        ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo $category['name']; ?>
                            </option>
                        <?php
                            endforeach;
                        } catch(PDOException $e) {
                            echo '<option value="">Error loading categories</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="image">Gambar Produk (opsional)</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="status">Status <span style="color: red;">*</span></label>
                    <select id="status" name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Simpan Produk
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