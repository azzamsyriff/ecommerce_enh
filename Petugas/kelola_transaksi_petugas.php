<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

if(isset($_POST['update_status'])) {
    $transaction_id = $_POST['transaction_id'];
    $new_status = $_POST['status'];
    try {
        $stmt = $conn->prepare("UPDATE transactions SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $new_status, 'id' => $transaction_id]);
        $_SESSION['success'] = "Status transaksi berhasil diupdate!";
        header('Location: kelola_transaksi_petugas.php');
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
}

if(isset($_POST['process_return'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    $action = $_POST['process_return'];
    $admin_note = trim($_POST['admin_note'] ?? '');
    try {
        $conn->beginTransaction();
        if($action === 'approve') {
            $stmt = $conn->prepare("UPDATE transactions SET return_status = 'approved', return_processed_at = NOW(), return_processed_by = :admin_id, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['admin_id' => $_SESSION['user_id'], 'id' => $transaction_id]);
            
            $stmt = $conn->prepare("SELECT td.product_id, td.quantity FROM transaction_details td WHERE td.transaction_id = :tid");
            $stmt->execute(['tid' => $transaction_id]);
            $items = $stmt->fetchAll();
            foreach($items as $item) {
                $stmt = $conn->prepare("UPDATE products SET stock = stock + :qty WHERE id = :pid");
                $stmt->execute(['qty' => $item['quantity'], 'pid' => $item['product_id']]);
            }
            $_SESSION['success'] = "✅ Pembatalan disetujui! Stok produk telah dikembalikan.";
        } elseif($action === 'reject') {
            $stmt = $conn->prepare("UPDATE transactions SET return_status = 'rejected', return_processed_at = NOW(), return_processed_by = :admin_id, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['admin_id' => $_SESSION['user_id'], 'id' => $transaction_id]);
            $_SESSION['success'] = "❌ Pembatalan ditolak." . ($admin_note ? " Catatan: " . htmlspecialchars($admin_note) : "");
        }
        $conn->commit();
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    header('Location: kelola_transaksi_petugas.php');
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$query = "SELECT t.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
          FROM transactions t
          LEFT JOIN users u ON t.user_id = u.id
          WHERE 1=1";
$params = [];

if($search) {
    $query .= " AND (t.transaction_code LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)";
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
    
    $transactionDetails = [];
    foreach($transactions as $trx) {
        $stmt = $conn->prepare("SELECT td.*, p.name as product_name, p.image
                                FROM transaction_details td
                                JOIN products p ON td.product_id = p.id
                                WHERE td.transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $trx['id']]);
        $products = $stmt->fetchAll();
        
        $transactionDetails[$trx['id']] = [
            'transaction_code' => $trx['transaction_code'],
            'status' => $trx['status'],
            'return_status' => $trx['return_status'] ?? 'none',
            'return_reason' => nl2br(htmlspecialchars($trx['return_reason'] ?? '-')),
            'total_amount' => $trx['total_amount'],
            'total_amount_formatted' => 'Rp' . number_format($trx['total_amount'], 0, ',', '.'),
            'created_at' => $trx['created_at'],
            'created_at_formatted' => date('d M Y, H:i', strtotime($trx['created_at'])),
            'customer_name' => $trx['customer_name'] ?? 'Guest',
            'customer_email' => $trx['customer_email'] ?? '-',
            'customer_phone' => $trx['customer_phone'] ?? '-',
            'shipping_address' => nl2br(htmlspecialchars($trx['shipping_address'] ?? '-')),
            'order_notes' => nl2br(htmlspecialchars($trx['notes'] ?? '-')),
            'payment_method' => $trx['payment_method'] ?? 'Tidak ditentukan',
            'products' => $products
        ];
    }
} catch(PDOException $e) {
    $transactions = [];
    $transactionDetails = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Transaksi - Petugas Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .container { max-width: 1400px; margin: 30px auto; padding: 20px; }
        .header { background: white; padding: 20px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { color: #3751fe; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2d43d9; transform: translateY(-2px); }
        .btn-secondary { background: #6b7280; }
        .btn-success { background: #10b981; }
        .btn-warning { background: #f59e0b; }
        .btn-danger { background: #ef4444; }
        .filters { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .filters-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 15px; }
        .filters-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 13px; font-weight: 500; color: #374151; }
        .filter-group input, .filter-group select { padding: 10px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .table-header { padding: 20px 30px; background: linear-gradient(135deg, #3751fe 0%, #667eea 100%); color: white; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; }
        th { padding: 15px 20px; text-align: left; font-weight: 600; color: #374151; font-size: 14px; text-transform: uppercase; }
        td { padding: 15px 20px; border-top: 1px solid #e5e7eb; font-size: 14px; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.paid { background: #d1fae5; color: #065f46; }
        .status-badge.shipped { background: #dbeafe; color: #1e40af; }
        .status-badge.delivered { background: #dcfce7; color: #166534; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }
        .status-badge.return_requested { background: #fef3c7; color: #92400e; border: 2px solid #f59e0b; }
        .status-badge.return_approved { background: #dcfce7; color: #166534; border: 2px solid #10b981; }
        .status-badge.return_rejected { background: #fee2e2; color: #991b1b; border: 2px solid #ef4444; }
        .price { font-weight: 600; color: #1f2937; }
        .customer { color: #4b5563; }
        .date { color: #6b7280; font-size: 13px; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 20px; }
        .action-buttons { display: flex; gap: 8px; }
        .btn-sm { padding: 8px 12px; font-size: 13px; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: white; border-radius: 15px; width: 90%; max-width: 700px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); animation: modalSlideIn 0.3s ease-out; max-height: 90vh; overflow-y: auto; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-50px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 20px 30px; background: linear-gradient(135deg, #3751fe 0%, #667eea 100%); color: white; border-radius: 15px 15px 0 0; position: relative; }
        .modal-title { font-size: 20px; font-weight: 600; }
        .modal-close { position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.2); border: none; width: 30px; height: 30px; border-radius: 50%; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .modal-close:hover { background: rgba(255, 255, 255, 0.3); transform: rotate(90deg); }
        .modal-body { padding: 30px; }
        .transaction-detail { margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .detail-label { color: #6b7280; font-weight: 500; }
        .detail-value { font-weight: 600; color: #1f2937; }
        .product-list { margin-top: 20px; }
        .product-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
        .product-image { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #f3f4f6; margin-right: 10px; }
        .product-info { flex: 1; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shopping-bag"></i> Kelola Transaksi</h1>
            <a href="petugas_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="filters">
            <div class="filters-title"><i class="fas fa-filter"></i> Filter Transaksi</div>
            <form method="GET" class="filters-row">
                <div class="filter-group"><label for="search">Pencarian</label><input type="text" id="search" name="search" placeholder="Cari transaksi..." value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="filter-group"><label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group"><label for="date_from">Dari Tanggal</label><input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"></div>
                <div class="filter-group"><label for="date_to">Sampai Tanggal</label><input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"></div>
                <div class="filter-group" style="align-self: flex-end;"><button type="submit" class="btn" style="width: 100%;"><i class="fas fa-search"></i> Filter</button></div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header"><div><i class="fas fa-list"></i> Daftar Transaksi</div><div>Total: <?php echo count($transactions); ?> transaksi</div></div>
            <table>
                <thead><tr><th>Kode Transaksi</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php if(count($transactions) > 0): ?>
                        <?php foreach($transactions as $trx): ?>
                            <?php
                                $rs = $trx['return_status'] ?? 'none';
                                $badgeClass = ($rs === 'approved') ? 'return_approved' :
                                            (($rs === 'requested') ? 'return_requested' :
                                            (($rs === 'rejected') ? 'return_rejected' : $trx['status']));
                                $badgeText = ($rs === 'approved') ? 'Pembatalan Disetujui' :
                                           (($rs === 'requested') ? 'Pembatalan Diajukan' :
                                           (($rs === 'rejected') ? 'Pembatalan Ditolak' : ucfirst($trx['status'])));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($trx['transaction_code']); ?></strong></td>
                                <td class="customer"><?php echo htmlspecialchars($trx['customer_name'] ?? 'Guest'); ?><br><span style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($trx['customer_email'] ?? '-'); ?></span></td>
                                <td class="price">Rp<?php echo number_format($trx['total_amount'], 0, ',', '.'); ?></td>
                                <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
                                <td class="date"><?php echo date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                                <td><div class="action-buttons">
                                    <button onclick="viewDetail(<?php echo (int)$trx['id']; ?>)" class="btn btn-sm btn-success"><i class="fas fa-eye"></i> Detail</button>
                                    <button onclick="updateStatus(<?php echo (int)$trx['id']; ?>)" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Update</button>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px;"><div class="empty-state"><i class="fas fa-shopping-bag"></i><h3>Tidak ada transaksi</h3><p>Belum ada transaksi yang ditemukan.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal">
            <div class="modal-header"><button class="modal-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button><div class="modal-title">Detail Transaksi</div></div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal-overlay" id="updateModal">
        <div class="modal">
            <div class="modal-header"><button class="modal-close" onclick="closeModal('updateModal')"><i class="fas fa-times"></i></button><div class="modal-title">Update Status Transaksi</div></div>
            <div class="modal-body">
                <form method="POST" id="updateForm">
                    <input type="hidden" name="transaction_id" id="updateTransactionId">
                    <div class="filter-group"><label for="status">Status Baru</label>
                        <select name="status" id="status" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="action-buttons" style="margin-top: 20px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')"><i class="fas fa-times"></i> Batal</button>
                        <button type="submit" name="update_status" class="btn"><i class="fas fa-save"></i> Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const transactionDetails = <?php echo json_encode($transactionDetails, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
        document.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); });
        
        function viewDetail(transactionId) {
            const details = transactionDetails[transactionId];
            if (!details) { alert('Detail transaksi tidak ditemukan!'); return; }
            
            let productsHTML = '';
            details.products.forEach(product => {
                const imageUrl = product.image && product.image.trim() !== '' ? '../uploads/products/' + product.image : 'image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="%239ca3af" stroke-width="2"%3E%3Cpath stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"%3E%3C/path%3E%3C/svg%3E';
                productsHTML += `<div class="product-item"><div style="display: flex; align-items: center; gap: 10px; flex: 1;"><img src="${imageUrl}" alt="${product.product_name}" class="product-image"><div class="product-info"><div style="font-weight: 600; color: #1f2937;">${product.product_name}</div><div style="font-size: 13px; color: #6b7280;">${product.quantity} x Rp${parseInt(product.price).toLocaleString('id-ID')}</div></div></div><div style="font-weight: 600; color: #10b981;">Rp${parseInt(product.subtotal).toLocaleString('id-ID')}</div></div>`;
            });
            
            let returnSection = '';
            if(details.return_status === 'requested') {
                returnSection = `
                <div style="margin-top:20px;padding:15px;background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;">
                    <h4 style="color:#92400e;margin-bottom:10px;"><i class="fas fa-exclamation-triangle"></i> Pembatalan Diajukan</h4>
                    <p style="margin-bottom:15px;"><strong>Alasan:</strong><br>${details.return_reason || '-'}</p>
                    <form method="POST" id="returnForm" style="display:flex;flex-direction:column;gap:10px;">
                        <input type="hidden" name="transaction_id" value="${transactionId}">
                        <textarea name="admin_note" placeholder="Catatan petugas (opsional)" style="padding:10px;border:1px solid #e5e7eb;border-radius:6px;min-height:60px;"></textarea>
                        <div style="display:flex;gap:10px;">
                            <button type="submit" name="process_return" value="approve" class="btn btn-success" style="flex:1;" onclick="return confirm('Setujui pembatalan? Stok akan dikembalikan.')"><i class="fas fa-check"></i> Setujui</button>
                            <button type="submit" name="process_return" value="reject" class="btn btn-danger" style="flex:1;" onclick="return confirm('Tolak pembatalan?')"><i class="fas fa-times"></i> Tolak</button>
                        </div>
                    </form>
                </div>`;
            } else if(details.return_status === 'approved') {
                returnSection = `<div style="margin-top:20px;padding:12px;background:#dcfce7;border-left:4px solid #10b981;border-radius:6px;"><strong style="color:#166534;">✓ Pembatalan Disetujui</strong><br><small style="color:#6b7280;">Stok produk telah dikembalikan ke inventory.</small></div>`;
            } else if(details.return_status === 'rejected') {
                returnSection = `<div style="margin-top:20px;padding:12px;background:#fee2e2;border-left:4px solid #ef4444;border-radius:6px;"><strong style="color:#991b1b;">✗ Pembatalan Ditolak</strong></div>`;
            }
            
            const statusBadgeClass = details.return_status !== 'none' ? 'return_'+details.return_status : details.status;
            const statusBadgeText = details.return_status === 'approved' ? 'Pembatalan Disetujui' :
                                   (details.return_status === 'requested' ? 'Pembatalan Diajukan' :
                                   (details.return_status === 'rejected' ? 'Pembatalan Ditolak' :
                                   details.status.charAt(0).toUpperCase() + details.status.slice(1)));
                                   
            document.getElementById('detailContent').innerHTML = `
                <div class="transaction-detail">
                    <div class="detail-row"><span class="detail-label">Kode Transaksi:</span><span class="detail-value">${details.transaction_code}</span></div>
                    <div class="detail-row"><span class="detail-label">Status:</span><span class="detail-value"><span class="status-badge ${statusBadgeClass}">${statusBadgeText}</span></span></div>
                    <div class="detail-row"><span class="detail-label">Total:</span><span class="detail-value" style="color: #10b981; font-size: 18px;">${details.total_amount_formatted}</span></div>
                    <div class="detail-row"><span class="detail-label">Tanggal:</span><span class="detail-value">${details.created_at_formatted}</span></div>
                    <div class="detail-row"><span class="detail-label">Pelanggan:</span><span class="detail-value">${details.customer_name}</span></div>
                    <div class="detail-row"><span class="detail-label">Email:</span><span class="detail-value">${details.customer_email}</span></div>
                    <div class="detail-row"><span class="detail-label">Telepon:</span><span class="detail-value">${details.customer_phone}</span></div>
                    <div class="detail-row"><span class="detail-label">Metode Pembayaran:</span><span class="detail-value">${details.payment_method}</span></div>
                    <div class="detail-row" style="align-items: flex-start;"><span class="detail-label">Alamat Pengiriman:</span><span class="detail-value" style="text-align: right;">${details.shipping_address}</span></div>
                    <div class="detail-row" style="align-items: flex-start;"><span class="detail-label">Catatan Pesanan:</span><span class="detail-value" style="text-align: right; font-style: italic; color: #6b7280;">${details.order_notes}</span></div>
                    ${returnSection}
                </div>
                <div class="product-list">
                    <h4 style="margin-bottom: 15px; color: #1f2937; padding-bottom: 10px; border-bottom: 2px solid #f3f4f6;">Daftar Produk:</h4>
                    ${productsHTML}
                    <div class="detail-row" style="border-top: 2px solid #3751fe; padding-top: 15px; margin-top: 15px; font-weight: 700; font-size: 18px;">
                        <span>Total Keseluruhan:</span><span style="color: #10b981;">${details.total_amount_formatted}</span>
                    </div>
                </div>`;
            document.getElementById('detailModal').classList.add('show');
        }
        
        function updateStatus(transactionId) { 
            document.getElementById('updateTransactionId').value = transactionId; 
            document.getElementById('updateModal').classList.add('show'); 
        }
    </script>
</body>
</html>