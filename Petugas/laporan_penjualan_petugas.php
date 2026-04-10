<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role petugas
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

// Ambil data laporan penjualan - DIPERBAIKI: Gunakan transaction_details dan subtotal
try {
    // Total revenue dari transaksi yang sudah dibayar/dikirim/diterima
    $stmt = $conn->query("SELECT SUM(total_amount) as total FROM transactions WHERE status IN ('paid', 'shipped', 'delivered')");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Top products - DIPERBAIKI: Gunakan transaction_details dan subtotal
    $stmt = $conn->query("SELECT p.name, SUM(td.quantity) as total_quantity, SUM(td.subtotal) as total_revenue 
                          FROM transaction_details td 
                          JOIN products p ON td.product_id = p.id 
                          JOIN transactions t ON td.transaction_id = t.id
                          WHERE t.status IN ('paid', 'shipped', 'delivered')
                          GROUP BY p.id 
                          ORDER BY total_quantity DESC 
                          LIMIT 5");
    $top_products = $stmt->fetchAll();
    
    // Revenue by category - DIPERBAIKI: Gunakan transaction_details dan subtotal
    $stmt = $conn->query("SELECT c.name, SUM(td.subtotal) as total_revenue 
                          FROM transaction_details td 
                          JOIN products p ON td.product_id = p.id 
                          JOIN categories c ON p.category_id = c.id
                          JOIN transactions t ON td.transaction_id = t.id
                          WHERE t.status IN ('paid', 'shipped', 'delivered')
                          GROUP BY c.id 
                          ORDER BY total_revenue DESC");
    $revenue_by_category = $stmt->fetchAll();
    
    // Revenue by month untuk chart
    $stmt = $conn->query("SELECT 
                            DATE_FORMAT(t.created_at, '%Y-%m') as month,
                            SUM(t.total_amount) as revenue,
                            COUNT(t.id) as transaction_count
                          FROM transactions t
                          WHERE t.status IN ('paid', 'shipped', 'delivered')
                          GROUP BY month
                          ORDER BY month DESC
                          LIMIT 6");
    $revenue_by_month = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)); // Reverse untuk urutan chronologis
    
} catch(PDOException $e) {
    $total_revenue = 0;
    $top_products = [];
    $revenue_by_category = [];
    $revenue_by_month = [];
    error_log("Error in laporan_penjualan_petugas.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Petugas Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.primary {
            background: rgba(55, 81, 254, 0.1);
            color: var(--primary);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            font-size: 13px;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .chart-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            text-align: center;
        }

        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
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

        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-color: #3751fe;
        }

        .product-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .product-stats {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .product-stat {
            display: flex;
            align-items: center;
            gap: 5px;
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
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Laporan Penjualan</h1>
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Total Penjualan</div>
                        <div class="stat-value">Rp<?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>Data dari transaksi yang sudah dibayar/dikirim/diterima</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Total Produk Terjual</div>
                        <div class="stat-value"><?php 
                            $total_products = 0;
                            foreach($top_products as $product) {
                                $total_products += $product['total_quantity'];
                            }
                            echo $total_products ?: 0;
                        ?></div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Kategori Terlaris</div>
                        <div class="stat-value"><?php echo count($revenue_by_category) > 0 ? $revenue_by_category[0]['name'] : 'Belum ada data'; ?></div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Total Transaksi</div>
                        <div class="stat-value"><?php 
                            $stmt = $conn->query("SELECT COUNT(*) as total FROM transactions WHERE status IN ('paid', 'shipped', 'delivered')");
                            echo $stmt->fetch()['total'] ?? 0;
                        ?></div>
                    </div>
                    <div class="stat-icon danger">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="chart-container">
            <h2 class="chart-title">Pendapatan Berdasarkan Kategori</h2>
            <div class="chart-row">
                <div class="chart-wrapper">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <h2 class="chart-title">Pendapatan Bulanan</h2>
            <div class="chart-row">
                <div class="chart-wrapper">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="chart-container">
            <h2 class="chart-title">Produk Terlaris</h2>
            <?php if(count($top_products) > 0): ?>
                <div class="product-list">
                    <?php foreach($top_products as $product): ?>
                        <div class="product-card">
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-stats">
                                <div class="product-stat">
                                    <i class="fas fa-box"></i>
                                    <span><?php echo $product['total_quantity']; ?> terjual</span>
                                </div>
                                <div class="product-stat">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Rp<?php echo number_format($product['total_revenue'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>Belum Ada Data Penjualan</h3>
                    <p>Belum ada transaksi yang berhasil diselesaikan (status: paid/shipped/delivered). Data akan muncul setelah ada transaksi yang berhasil.</p>
                    <a href="petugas_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-shopping-bag"></i> Lihat Produk
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Chart for revenue by category
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php 
                    $labels = [];
                    foreach($revenue_by_category as $category) {
                        $labels[] = $category['name'];
                    }
                    echo json_encode($labels ?: ['Belum ada data']);
                ?>,
                datasets: [{
                    data: <?php 
                        $data = [];
                        foreach($revenue_by_category as $category) {
                            $data[] = $category['total_revenue'];
                        }
                        echo json_encode($data ?: [100]);
                    ?>,
                    backgroundColor: [
                        '#3751fe',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#6b7280',
                        '#8b5cf6',
                        '#ec4899'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: Rp${value.toLocaleString('id-ID')} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Chart for monthly revenue
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php 
                    $months = [];
                    foreach($revenue_by_month as $month) {
                        // Format month to "Jan 2026"
                        $date = DateTime::createFromFormat('Y-m', $month['month']);
                        $months[] = $date ? $date->format('M Y') : $month['month'];
                    }
                    echo json_encode($months ?: ['Jan 2026']);
                ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?php 
                        $revenues = [];
                        foreach($revenue_by_month as $month) {
                            $revenues[] = $month['revenue'];
                        }
                        echo json_encode($revenues ?: [0]);
                    ?>,
                    backgroundColor: 'rgba(55, 81, 254, 0.7)',
                    borderColor: 'rgba(55, 81, 254, 1)',
                    borderWidth: 1
                }, {
                    label: 'Jumlah Transaksi',
                    data: <?php 
                        $transactions = [];
                        foreach($revenue_by_month as $month) {
                            $transactions[] = $month['transaction_count'];
                        }
                        echo json_encode($transactions ?: [0]);
                    ?>,
                    type: 'line',
                    backgroundColor: 'rgba(16, 185, 129, 0.3)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Pendapatan (Rp)') {
                                    return `Rp${context.parsed.y.toLocaleString('id-ID')}`;
                                }
                                return `${context.parsed.y} transaksi`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Jumlah Transaksi'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>