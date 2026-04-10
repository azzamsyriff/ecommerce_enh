<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../Auth/login.php');
    exit();
}

// Handle add petugas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_petugas'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    
    // Validasi email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email sudah terdaftar!";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (:full_name, :email, :phone, :password, 'petugas', 'active')");
            $stmt->execute([
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'password' => $hashed_password
            ]);
            
            $_SESSION['success'] = "Petugas berhasil ditambahkan!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    header('Location: kelola_petugas.php');
    exit();
}

// Handle update petugas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_petugas'])) {
    $petugas_id = $_POST['petugas_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $status = $_POST['status'];
    
    // Validasi email uniqueness (kecuali untuk user sendiri)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $stmt->execute(['email' => $email, 'id' => $petugas_id]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email sudah digunakan oleh user lain!";
    } else {
        try {
            if(!empty($password)) {
                // Update dengan password baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name = :full_name, email = :email, phone = :phone, password = :password, status = :status WHERE id = :id");
                $stmt->execute([
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $hashed_password,
                    'status' => $status,
                    'id' => $petugas_id
                ]);
            } else {
                // Update tanpa password
                $stmt = $conn->prepare("UPDATE users SET full_name = :full_name, email = :email, phone = :phone, status = :status WHERE id = :id");
                $stmt->execute([
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'status' => $status,
                    'id' => $petugas_id
                ]);
            }
            
            $_SESSION['success'] = "Data petugas berhasil diupdate!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    header('Location: kelola_petugas.php');
    exit();
}

// Handle reset password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $petugas_id = $_POST['petugas_id'];
    $new_password = $_POST['new_password'];
    
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->execute([
            'password' => $hashed_password,
            'id' => $petugas_id
        ]);
        
        $_SESSION['success'] = "Password berhasil direset!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    header('Location: kelola_petugas.php');
    exit();
}

// Handle toggle status (soft delete)
if(isset($_GET['toggle_status'])) {
    $petugas_id = $_GET['toggle_status'];
    
    try {
        // Get current status
        $stmt = $conn->prepare("SELECT status FROM users WHERE id = :id");
        $stmt->execute(['id' => $petugas_id]);
        $current_status = $stmt->fetch()['status'];
        
        // Toggle status
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([
            'status' => $new_status,
            'id' => $petugas_id
        ]);
        
        $_SESSION['success'] = "Status petugas berhasil diubah!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    header('Location: kelola_petugas.php');
    exit();
}

// Handle delete permanently (hard delete)
if(isset($_GET['delete'])) {
    if($_GET['delete'] == $_SESSION['user_id']) {
        $_SESSION['error'] = "Anda tidak bisa menghapus akun sendiri!";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'petugas'");
            $stmt->execute(['id' => $_GET['delete']]);
            $_SESSION['success'] = "Petugas berhasil dihapus permanen!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    header('Location: kelola_petugas.php');
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build query
$query = "SELECT * FROM users WHERE role = 'petugas'";
$params = [];

if($search) {
    $query .= " AND (full_name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

if($status_filter && $status_filter != 'all') {
    $query .= " AND status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $petugas = $stmt->fetchAll();
} catch(PDOException $e) {
    $petugas = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Petugas - Admin Dashboard</title>
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
        
        .btn-success {
            background: #10b981;
        }
        
        .btn-success:hover {
            background: #0da271;
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
            max-width: 550px;
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
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3751fe;
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
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
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .user-email {
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }
        
        .role-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
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
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #1f2937;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <a href="admin_dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h1><i class="fas fa-user-tie"></i> Kelola Petugas</h1>
        <button class="btn" onclick="openModal('addPetugasModal')">
            <i class="fas fa-plus"></i> Tambah Petugas
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
                <i class="fas fa-filter"></i> Filter Petugas
            </div>
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="search">Pencarian</label>
                    <input type="text" id="search" name="search" placeholder="Cari petugas..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="status_filter">Status</label>
                    <select id="status_filter" name="status_filter">
                        <option value="all" <?php echo $status_filter == 'all' || $status_filter == '' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                    <i class="fas fa-list"></i> Daftar Petugas
                </div>
                <div class="table-stats">
                    <span><i class="fas fa-user-tie"></i> Total: <?php echo count($petugas); ?> petugas</span>
                </div>
            </div>
            
            <?php if(count($petugas) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Petugas</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Tanggal Bergabung</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($petugas as $staff): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="avatar">
                                            <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="user-name"><?php echo $staff['full_name']; ?></div>
                                            <div class="user-email"><?php echo $staff['email']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $staff['email']; ?></td>
                                <td>
                                    <?php if($staff['phone']): ?>
                                        <?php echo $staff['phone']; ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge"><?php echo ucfirst($staff['role']); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $staff['status']; ?>">
                                        <?php echo ucfirst($staff['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="date-badge">
                                        <?php echo date('d M Y', strtotime($staff['created_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewDetail('<?php echo $staff['id']; ?>', '<?php echo $staff['full_name']; ?>', '<?php echo $staff['email']; ?>', '<?php echo $staff['phone']; ?>', '<?php echo $staff['role']; ?>', '<?php echo $staff['status']; ?>', '<?php echo date('d M Y', strtotime($staff['created_at'])); ?>')" class="btn btn-success" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editPetugas(<?php echo $staff['id']; ?>, '<?php echo $staff['full_name']; ?>', '<?php echo $staff['email']; ?>', '<?php echo $staff['phone']; ?>', '<?php echo $staff['status']; ?>')" class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="resetPassword(<?php echo $staff['id']; ?>, '<?php echo $staff['full_name']; ?>')" class="btn btn-secondary" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <a href="kelola_petugas.php?toggle_status=<?php echo $staff['id']; ?>" class="btn <?php echo $staff['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>" title="<?php echo $staff['status'] == 'active' ? 'Nonaktifkan' : 'Aktifkan'; ?>" onclick="return confirm('Yakin ingin <?php echo $staff['status'] == 'active' ? 'menonaktifkan' : 'mengaktifkan'; ?> petugas ini?')">
                                            <i class="fas fa-toggle-<?php echo $staff['status'] == 'active' ? 'on' : 'off'; ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h3>Tidak ada petugas</h3>
                    <p>Belum ada petugas yang terdaftar.</p>
                    <button class="btn" onclick="openModal('addPetugasModal')">
                        <i class="fas fa-plus"></i> Tambah Petugas Pertama
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Petugas Modal -->
    <div class="modal-overlay" id="addPetugasModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('addPetugasModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Tambah Petugas Baru</div>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Nama Lengkap <span style="color: red;">*</span></label>
                        <input type="text" id="full_name" name="full_name" required placeholder="Masukkan nama lengkap">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span style="color: red;">*</span></label>
                        <input type="email" id="email" name="email" required placeholder="Masukkan email">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Nomor Telepon (opsional)</label>
                        <input type="tel" id="phone" name="phone" placeholder="Masukkan nomor telepon">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span style="color: red;">*</span></label>
                        <input type="password" id="password" name="password" required placeholder="Minimal 6 karakter" minlength="6">
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Password akan di-hash untuk keamanan
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="add_petugas" class="btn">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addPetugasModal')">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Petugas Modal -->
    <div class="modal-overlay" id="editPetugasModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editPetugasModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Edit Petugas</div>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="petugas_id" id="editPetugasId">
                    
                    <div class="form-group">
                        <label for="edit_full_name">Nama Lengkap <span style="color: red;">*</span></label>
                        <input type="text" id="edit_full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email <span style="color: red;">*</span></label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_phone">Nomor Telepon</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">Password Baru (kosongkan jika tidak diubah)</label>
                        <input type="password" id="edit_password" name="password" placeholder="Minimal 6 karakter" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status Akun <span style="color: red;">*</span></label>
                        <select id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Kosongkan password jika tidak ingin mengubahnya
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="update_petugas" class="btn">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editPetugasModal')">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Detail Petugas Modal -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('detailModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Detail Petugas</div>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be filled dynamically -->
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal-overlay" id="resetPasswordModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('resetPasswordModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title" id="resetModalTitle">Reset Password</div>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="petugas_id" id="resetPetugasId">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Perhatian!</strong> Password baru akan mengganti password lama.
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Password Baru <span style="color: red;">*</span></label>
                        <input type="password" id="new_password" name="new_password" required placeholder="Minimal 6 karakter" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password <span style="color: red;">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Ulangi password baru" minlength="6">
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="reset_password" class="btn btn-warning" onclick="return validatePassword()">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">
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
        
        // View detail petugas
        function viewDetail(id, name, email, phone, role, status, created_at) {
            const formattedPhone = phone ? phone : '-';
            const formattedDate = created_at;
            
            document.getElementById('detailContent').innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">ID Petugas:</span>
                    <span class="detail-value">${id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nama Lengkap:</span>
                    <span class="detail-value">${name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${email}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nomor Telepon:</span>
                    <span class="detail-value">${formattedPhone}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Role:</span>
                    <span class="detail-value">${role}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge ${status}">${status}</span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tanggal Bergabung:</span>
                    <span class="detail-value">${formattedDate}</span>
                </div>
            `;
            
            document.getElementById('detailModal').classList.add('show');
        }
        
        // Edit petugas
        function editPetugas(id, name, email, phone, status) {
            document.getElementById('editPetugasId').value = id;
            document.getElementById('edit_full_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('edit_status').value = status;
            
            document.getElementById('editPetugasModal').classList.add('show');
        }
        
        // Reset password
        function resetPassword(id, name) {
            document.getElementById('resetPetugasId').value = id;
            document.getElementById('resetModalTitle').textContent = 'Reset Password - ' + name;
            document.getElementById('resetPasswordModal').classList.add('show');
        }
        
        // Validate password match
        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if(password !== confirm) {
                alert('Password tidak cocok!');
                return false;
            }
            
            if(password.length < 6) {
                alert('Password minimal 6 karakter!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>