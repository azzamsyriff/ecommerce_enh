<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../Auth/login.php');
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    if(!$current_user) {
        header('Location: ../Auth/logout.php');
        exit();
    }
    
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Query transaksi user - FIX: Tambah search filter
    $query = "SELECT t.*,
              (SELECT COUNT(*) FROM transaction_details WHERE transaction_id = t.id) as item_count,
              (SELECT SUM(quantity) FROM transaction_details WHERE transaction_id = t.id) as total_quantity
              FROM transactions t
              WHERE t.user_id = :user_id";
    $params = [':user_id' => $_SESSION['user_id']];
    
    if($search) {
        $query .= " AND t.transaction_code LIKE :search";
        $params[':search'] = "%$search%";
    }
    if($status_filter !== 'all') {
        $query .= " AND t.status = :status";
        $params[':status'] = $status_filter;
    }
    $query .= " ORDER BY t.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM transactions WHERE user_id = :user_id GROUP BY status");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $cart_result = $stmt->fetch();
    $cart_count = $cart_result['total'] ?? 0;
} catch(PDOException $e) {
    $transactions = [];
    $status_stats = [];
    $cart_count = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - E-Commerce Platform</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #3751fe; --primary-dark: #2d43d9; --secondary: #667eea; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --dark: #1f2937; --gray: #6b7280; --light-gray: #f3f4f6; --white: #ffffff; --sidebar-bg: #1e293b; --sidebar-hover: #334155; --card-bg: #ffffff; --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fb; display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, var(--sidebar-bg) 0%, #1a2234 100%); color: var(--white); position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 1000; }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: center; }
        .logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 15px; }
        .logo-icon { font-size: 32px; color: var(--primary); }
        .logo-text { font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo-subtext { font-size: 14px; color: rgba(255, 255, 255, 0.6); margin-top: 5px; }
        .sidebar-menu { padding: 20px 0; }
        .menu-section { margin-bottom: 20px; }
        .menu-title { padding: 15px 25px 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255, 255, 255, 0.5); font-weight: 600; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255, 255, 255, 0.85); text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover { background: var(--sidebar-hover); color: var(--white); border-left-color: var(--primary); }
        .menu-item.active { background: rgba(55, 81, 254, 0.2); color: var(--white); border-left-color: var(--primary); }
        .menu-icon { width: 24px; margin-right: 15px; font-size: 18px; }
        .menu-text { flex: 1; font-size: 15px; font-weight: 500; }
        .menu-badge { background: var(--primary); color: var(--white); padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .main-content { flex: 1; margin-left: 280px; transition: all 0.3s; }
        .header { background: var(--white); padding: 20px 40px; box-shadow: var(--shadow); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 999; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .search-bar { position: relative; }
        .search-bar input { padding: 12px 20px 12px 45px; border: 2px solid var(--light-gray); border-radius: 10px; width: 300px; font-size: 14px; transition: all 0.3s; }
        .search-bar input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--gray); font-size: 16px; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .cart-icon { position: relative; cursor: pointer; }
        .cart-icon i { font-size: 20px; color: var(--gray); }
        .cart-badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: var(--white); width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; }
        .user-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: var(--white); font-weight: 600; font-size: 16px; overflow: hidden; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: var(--dark); }
        .user-role { font-size: 12px; color: var(--gray); }
        .dashboard-content { padding: 30px 40px; }
        .page-title { font-size: 28px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .page-subtitle { color: var(--gray); font-size: 16px; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: var(--shadow); text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .stat-value { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
        .stat-label { font-size: 14px; color: var(--gray); }
        .stat-value.pending { color: var(--warning); }
        .stat-value.paid { color: var(--success); }
        .stat-value.shipped { color: var(--primary); }
        .stat-value.delivered { color: var(--success); }
        .stat-value.cancelled { color: var(--danger); }
        .filters { background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow); margin-bottom: 30px; }
        .filters-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 15px; }
        .filter-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; background: var(--light-gray); border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .filter-btn:hover { background: #e5e7eb; }
        .filter-btn.active { background: var(--primary); color: white; }
        .transactions-container { background: white; border-radius: 15px; box-shadow: var(--shadow); overflow: hidden; }
        .table-header { padding: 20px 30px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 20px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; }
        th { padding: 15px 20px; text-align: left; font-weight: 600; color: #374151; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #4b5563; }
        tr:hover { background: #f9fafb; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.paid { background: #d1fae5; color: #065f46; }
        .status-badge.shipped { background: #dbeafe; color: #1e40af; }
        .status-badge.delivered { background: #dcfce7; color: #166534; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }
        .status-badge.return_requested { background: #fef3c7; color: #92400e; border: 2px solid #f59e0b; }
        .status-badge.return_approved { background: #dcfce7; color: #166534; border: 2px solid #10b981; }
        .status-badge.return_rejected { background: #fee2e2; color: #991b1b; border: 2px solid #ef4444; }
        .transaction-code { font-weight: 600; color: var(--dark); text-decoration: none; }
        .transaction-code:hover { color: var(--primary); }
        .transaction-items { font-size: 13px; color: var(--gray); }
        .transaction-total { font-weight: 700; color: var(--success); font-size: 16px; }
        .action-btn { padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .action-btn:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 20px; }
        .empty-state h3 { color: #374151; font-size: 24px; margin-bottom: 15px; }
        .empty-state p { color: #6b7280; margin-bottom: 25px; max-width: 500px; margin-left: auto; margin-right: auto; }
        .btn { display: inline-block; padding: 12px 25px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2d43d9; transform: translateY(-2px); }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        @media (max-width: 1024px) { .sidebar { width: 240px; } .main-content { margin-left: 240px; } .search-bar input { width: 200px; } }
        @media (max-width: 768px) {
            .sidebar { width: 70px; overflow: hidden; }
            .sidebar:hover { width: 280px; overflow: visible; }
            .logo-text, .logo-subtext, .menu-text, .menu-title, .menu-badge { display: none; }
            .sidebar:hover .logo-text, .sidebar:hover .logo-subtext, .sidebar:hover .menu-text, .sidebar:hover .menu-title, .sidebar:hover .menu-badge { display: block; }
            .menu-icon { margin-right: 0; }
            .main-content { margin-left: 70px; }
            .search-bar { display: none; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-buttons { flex-direction: column; gap: 8px; }
            .filter-btn { width: 100%; }
            table { font-size: 13px; }
            th, td { padding: 12px 15px; }
            .dashboard-content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo"><i class="fas fa-shopping-cart logo-icon"></i><div><div class="logo-text">E-Commerce</div><div class="logo-subtext">Platform</div></div></div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section"><div class="menu-title">Main Menu</div><a href="user_dashboard.php" class="menu-item"><i class="fas fa-home menu-icon"></i><span class="menu-text">Dashboard</span></a></div>
            <div class="menu-section"><div class="menu-title">Shopping</div><a href="user_dashboard.php" class="menu-item"><i class="fas fa-shopping-bag menu-icon"></i><span class="menu-text">Produk</span></a><a href="keranjang.php" class="menu-item"><i class="fas fa-shopping-cart menu-icon"></i><span class="menu-text">Keranjang</span><?php if($cart_count > 0): ?><span class="menu-badge"><?php echo $cart_count; ?></span><?php endif; ?></a><a href="riwayat_transaksi.php" class="menu-item active"><i class="fas fa-history menu-icon"></i><span class="menu-text">Riwayat Transaksi</span></a></div>
            <div class="menu-section"><div class="menu-title">Settings</div><a href="profile_user.php" class="menu-item"><i class="fas fa-user menu-icon"></i><span class="menu-text">Profile</span></a><a href="../Auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt menu-icon"></i><span class="menu-text">Logout</span></a></div>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <!-- FIX: Search bar dibungkus form & pakai method GET -->
                <form method="GET" style="display:flex; gap: 10px;">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Cari transaksi..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <button type="submit" class="btn" style="padding: 12px 20px;"><i class="fas fa-search"></i> Cari</button>
                </form>
            </div>
            <div class="header-right">
                <div class="cart-icon" onclick="window.location.href='keranjang.php'"><i class="fas fa-shopping-cart"></i><?php if($cart_count > 0): ?><span class="cart-badge"><?php echo $cart_count; ?></span><?php endif; ?></div>
                <div class="user-profile" onclick="window.location.href='profile_user.php'">
                    <div class="avatar"><?php if(isset($current_user['profile_picture']) && $current_user['profile_picture'] && file_exists('../uploads/profiles/' . $current_user['profile_picture'])): ?><img src="../uploads/profiles/<?php echo htmlspecialchars($current_user['profile_picture']); ?>" alt="Profile"><?php else: ?><?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?><?php endif; ?></div>
                    <div class="user-info"><div class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div><div class="user-role"><?php echo ucfirst($current_user['role']); ?></div></div>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <h1 class="page-title"><i class="fas fa-history"></i> Riwayat Transaksi</h1>
            <p class="page-subtitle">Lihat semua transaksi pembelian Anda</p>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value <?php echo isset($status_stats['pending']) ? 'pending' : ''; ?>"><?php echo isset($status_stats['pending']) ? $status_stats['pending'] : 0; ?></div><div class="stat-label">Menunggu</div></div>
                <div class="stat-card"><div class="stat-value <?php echo isset($status_stats['paid']) ? 'paid' : ''; ?>"><?php echo isset($status_stats['paid']) ? $status_stats['paid'] : 0; ?></div><div class="stat-label">Dibayar</div></div>
                <div class="stat-card"><div class="stat-value <?php echo isset($status_stats['shipped']) ? 'shipped' : ''; ?>"><?php echo isset($status_stats['shipped']) ? $status_stats['shipped'] : 0; ?></div><div class="stat-label">Dikirim</div></div>
                <div class="stat-card"><div class="stat-value <?php echo isset($status_stats['delivered']) ? 'delivered' : ''; ?>"><?php echo isset($status_stats['delivered']) ? $status_stats['delivered'] : 0; ?></div><div class="stat-label">Diterima</div></div>
            </div>

            <div class="filters">
                <div class="filters-title"><i class="fas fa-filter"></i> Filter Status</div>
                <div class="filter-buttons">
                    <button class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>" onclick="filterTransactions('all')">Semua Status</button>
                    <button class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" onclick="filterTransactions('pending')">Menunggu</button>
                    <button class="filter-btn <?php echo $status_filter === 'paid' ? 'active' : ''; ?>" onclick="filterTransactions('paid')">Dibayar</button>
                    <button class="filter-btn <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>" onclick="filterTransactions('shipped')">Dikirim</button>
                    <button class="filter-btn <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>" onclick="filterTransactions('delivered')">Diterima</button>
                    <button class="filter-btn <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>" onclick="filterTransactions('cancelled')">Dibatalkan</button>
                </div>
            </div>

            <div class="transactions-container">
                <div class="table-header"><div class="table-title"><i class="fas fa-list"></i> Daftar Transaksi</div><div><?php echo count($transactions); ?> transaksi</div></div>
                <?php if(count($transactions) > 0): ?>
                    <table>
                        <thead><tr><th>Kode Transaksi</th><th>Tanggal</th><th>Produk</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach($transactions as $transaction): ?>
                            <tr>
                                <td><a href="detail_transaksi.php?id=<?php echo (int)$transaction['id']; ?>" class="transaction-code">#<?php echo htmlspecialchars($transaction['transaction_code']); ?></a></td>
                                <td><?php echo date('d M Y', strtotime($transaction['created_at'])); ?><br><span style="font-size: 12px; color: #9ca3af;"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></span></td>
                                <td><div class="transaction-items"><?php echo $transaction['item_count']; ?> produk<br><?php echo $transaction['total_quantity']; ?> item</div></td>
                                <td class="transaction-total">Rp<?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php
                                    $rs = $transaction['return_status'] ?? 'none';
                                    $badgeClass = ($rs === 'approved') ? 'return_approved' :
                                                (($rs === 'requested') ? 'return_requested' :
                                                (($rs === 'rejected') ? 'return_rejected' : $transaction['status']));
                                    $badgeText = ($rs === 'approved') ? 'Pembatalan Disetujui' :
                                               (($rs === 'requested') ? 'Pembatalan Diajukan' :
                                               (($rs === 'rejected') ? 'Pembatalan Ditolak' : ucfirst($transaction['status'])));
                                    ?>
                                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                </td>
                                <td><button class="action-btn" onclick="viewDetail(<?php echo (int)$transaction['id']; ?>)"><i class="fas fa-eye"></i> Detail</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-receipt"></i><h3>Belum Ada Transaksi</h3><p>Anda belum memiliki riwayat transaksi. Mulailah berbelanja sekarang!</p><a href="user_dashboard.php" class="btn"><i class="fas fa-shopping-bag"></i> Jelajahi Produk</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterTransactions(status) { window.location.href = 'riwayat_transaksi.php?status=' + status; }
        function viewDetail(transactionId) { window.location.href = 'detail_transaksi.php?id=' + transactionId; }
    </script>
</body>
</html>