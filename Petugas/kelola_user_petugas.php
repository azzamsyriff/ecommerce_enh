<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

// Handle delete
if(isset($_GET['delete'])) {
    if($_GET['delete'] == $_SESSION['user_id']) {
        $_SESSION['error'] = "Anda tidak bisa menghapus akun sendiri!";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'user'");
            $stmt->execute(['id' => $_GET['delete']]);
            $_SESSION['success'] = "User berhasil dihapus!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    header('Location: kelola_user_petugas.php');
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT * FROM users WHERE role = 'user' AND 1=1";
$params = [];

if($search) {
    $query .= " AND (full_name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

if($status_filter) {
    // Status filter bisa untuk active/inactive users
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin Dashboard</title>
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
        
        .table-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .table-responsive {
            overflow-x: auto;
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
            background: linear-gradient(135deg, #3751fe 0%, #667eea 100%);
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
        
        .user-phone {
            font-size: 12px;
            color: #6b7280;
        }
        
        .role-badge {
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
        
        /* Modal Popup Styles */
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
            padding: 25px;
            background: linear-gradient(135deg, #3751fe 0%, #667eea 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            text-align: center;
            position: relative;
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
            <h1><i class="fas fa-users"></i> Kelola User</h1>
            <a href="petugas_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
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
        
        <!-- Filters -->
        <div class="filters">
            <div class="filters-title">
                <i class="fas fa-filter"></i> Filter User
            </div>
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="search">Pencarian</label>
                    <input type="text" id="search" name="search" placeholder="Cari user..." value="<?php echo htmlspecialchars($search); ?>">
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
                    <i class="fas fa-list"></i> Daftar User
                </div>
                <div class="table-stats">
                    <span><i class="fas fa-users"></i> Total: <?php echo count($users); ?> user</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <?php if(count($users) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Tanggal Daftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="avatar">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="user-name"><?php echo $user['full_name']; ?></div>
                                                <div class="user-email"><?php echo $user['email']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
                                        <?php if($user['phone']): ?>
                                            <span class="user-phone"><?php echo $user['phone']; ?></span>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="role-badge"><?php echo ucfirst($user['role']); ?></span>
                                    </td>
                                    <td>
                                        <span class="date-badge">
                                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="viewDetail('<?php echo $user['id']; ?>', '<?php echo $user['full_name']; ?>', '<?php echo $user['email']; ?>', '<?php echo $user['phone']; ?>', '<?php echo $user['role']; ?>', '<?php echo date('d M Y', strtotime($user['created_at'])); ?>')" class="btn btn-success" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="kelola_user_petugas.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Yakin ingin menghapus user ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Tidak ada user</h3>
                        <p>Belum ada user yang terdaftar.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Detail User Modal -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('detailModal')">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-title">Detail User</div>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be filled dynamically -->
            </div>
        </div>
    </div>
    
    <script>
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
        
        // View detail user
        function viewDetail(id, name, email, phone, role, created_at) {
            // Format phone number
            const formattedPhone = phone ? phone : '-';
            
            // Populate modal content
            document.getElementById('detailContent').innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">ID User:</span>
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
                    <span class="detail-label">Tanggal Daftar:</span>
                    <span class="detail-value">${created_at}</span>
                </div>
            `;
            
            // Show modal
            document.getElementById('detailModal').classList.add('show');
        }
    </script>
</body>
</html>