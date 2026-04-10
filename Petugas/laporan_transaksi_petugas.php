<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan role petugas
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

// Ambil data laporan transaksi
try {
    // Total transactions
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transactions");
    $total_transactions = $stmt->fetch()['total'];
    
    // Total revenue
    $stmt = $conn->query("SELECT SUM(total_amount) as total FROM transactions WHERE status IN ('paid', 'shipped', 'delivered')");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Transactions by status
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
    $transactions_by_status = $stmt->fetchAll();
    
    // Transactions by month
    $stmt = $conn->query("SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month, 
        COUNT(*) as count, 
        SUM(total_amount) as total
        FROM transactions 
        GROUP BY month
        ORDER BY month");
    $transactions_by_month = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $total_transactions = 0;
    $total_revenue = 0;
    $transactions_by_status = [];
    $transactions_by_month = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - Petugas Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Style yang sama dengan admin_dashboard.php */
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice"></i> Laporan Transaksi</h1>
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
                        <div class="stat-label">Total Transaksi</div>
                        <div class="stat-value"><?php echo $total_transactions; ?></div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">Rp<?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Status Transaksi</div>
                        <div class="stat-value"><?php echo count($transactions_by_status); ?> status</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="chart-container">
            <h2 class="chart-title">Transaksi Berdasarkan Status</h2>
            <div class="chart-row">
                <div>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <h2 class="chart-title">Transaksi Berdasarkan Bulan</h2>
            <div class="chart-row">
                <div>
                    <canvas id="monthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Chart for transactions by status
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php 
                    $labels = [];
                    foreach($transactions_by_status as $status) {
                        $labels[] = ucfirst($status['status']);
                    }
                    echo json_encode($labels);
                ?>,
                datasets: [{
                    data: <?php 
                        $data = [];
                        foreach($transactions_by_status as $status) {
                            $data[] = $status['count'];
                        }
                        echo json_encode($data);
                    ?>,
                    backgroundColor: [
                        '#3751fe',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#6b7280'
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
                    }
                }
            }
        });
        
        // Chart for transactions by month
        const monthCtx = document.getElementById('monthChart').getContext('2d');
        const monthChart = new Chart(monthCtx, {
            type: 'bar',
            data: {
                labels: <?php 
                    $labels = [];
                    foreach($transactions_by_month as $month) {
                        $labels[] = date('M Y', strtotime($month['month']));
                    }
                    echo json_encode($labels);
                ?>,
                datasets: [{
                    label: 'Jumlah Transaksi',
                    data: <?php 
                        $data = [];
                        foreach($transactions_by_month as $month) {
                            $data[] = $month['count'];
                        }
                        echo json_encode($data);
                    ?>,
                    backgroundColor: '#3751fe',
                    borderColor: '#3751fe',
                    borderWidth: 1
                }, {
                    label: 'Total Revenue',
                    data: <?php 
                        $data = [];
                        foreach($transactions_by_month as $month) {
                            $data[] = $month['total'];
                        }
                        echo json_encode($data);
                    ?>,
                    backgroundColor: '#10b981',
                    borderColor: '#10b981',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>