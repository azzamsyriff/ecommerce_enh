<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../Auth/login.php');
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT t.*, u.full_name as customer_name FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
$params = [];

if($search) {
    $query .= " AND (t.transaction_code LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if($status_filter) {
    $query .= " AND t.status = :status";
    $params[':status'] = $status_filter;
}

if($date_from) {
    $query .= " AND DATE(t.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if($date_to) {
    $query .= " AND DATE(t.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY t.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch(PDOException $e) {
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - Admin Dashboard</title>
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
            font-size: 14px;
        }
        
        .btn:hover {
            background: #2d43d9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-success {
            background: #10b981;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.paid { background: #d1fae5; color: #065f46; }
        .status-badge.shipped { background: #dbeafe; color: #1e40af; }
        .status-badge.delivered { background: #dcfce7; color: #166534; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }
        
        .price {
            font-weight: 600;
            color: #1f2937;
        }
        
        .customer {
            color: #4b5563;
        }
        
        .date {
            color: #6b7280;
            font-size: 13px;
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
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice"></i> Laporan Transaksi</h1>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filters-title">
                <i class="fas fa-filter"></i> Filter Laporan
            </div>
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="search">Pencarian</label>
                    <input type="text" id="search" name="search" placeholder="Cari transaksi..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_from">Dari Tanggal</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">Sampai Tanggal</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
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
                <div>
                    <i class="fas fa-list"></i> Daftar Transaksi
                </div>
                <div>
                    Total: <?php echo count($transactions); ?> transaksi
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Kode Transaksi</th>
                        <th>Pelanggan</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($transactions) > 0): ?>
                        <?php foreach($transactions as $trx): ?>
                            <tr>
                                <td><strong><?php echo $trx['transaction_code']; ?></strong></td>
                                <td class="customer"><?php echo $trx['customer_name'] ?? 'Guest'; ?></td>
                                <td class="price">Rp<?php echo number_format($trx['total_amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $trx['status']; ?>">
                                        <?php echo ucfirst($trx['status']); ?>
                                    </span>
                                </td>
                                <td class="date"><?php echo date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="#" class="btn btn-sm btn-success">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <div class="empty-state">
                                    <i class="fas fa-file-invoice"></i>
                                    <h3>Tidak ada transaksi</h3>
                                    <p>Belum ada transaksi yang ditemukan.</p>
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