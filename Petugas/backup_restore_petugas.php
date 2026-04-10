<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../Auth/login.php');
    exit();
}

$backup_dir = '../backups/';
if(!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$message = '';
$message_type = '';

// Handle backup
if(isset($_POST['backup'])) {
    try {
        $tables = ['users', 'categories', 'products', 'transactions', 'transaction_details'];
        $backup_data = [];
        
        foreach($tables as $table) {
            // Get table structure
            $stmt = $conn->query("SHOW CREATE TABLE $table");
            $row = $stmt->fetch();
            $backup_data[] = $row['Create Table'] . ";\n\n";
            
            // Get table data
            $stmt = $conn->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll();
            
            if(count($rows) > 0) {
                foreach($rows as $row) {
                    $values = array_map(function($value) use ($conn) {
                        return $value === null ? 'NULL' : $conn->quote($value);
                    }, $row);
                    
                    $backup_data[] = "INSERT INTO $table VALUES (" . implode(',', $values) . ");\n";
                }
                $backup_data[] = "\n";
            }
        }
        
        // Create backup file
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        file_put_contents($filepath, implode('', $backup_data));
        
        $message = "Backup berhasil dibuat: $filename";
        $message_type = 'success';
        
    } catch(PDOException $e) {
        $message = "Backup gagal: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle restore
if(isset($_POST['restore']) && isset($_FILES['backup_file'])) {
    if($_FILES['backup_file']['error'] == 0 && $_FILES['backup_file']['type'] == 'application/sql') {
        $temp_file = $_FILES['backup_file']['tmp_name'];
        $sql_content = file_get_contents($temp_file);
        
        try {
            // Execute SQL statements
            $conn->exec($sql_content);
            
            $message = "Restore berhasil!";
            $message_type = 'success';
            
        } catch(PDOException $e) {
            $message = "Restore gagal: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "File backup tidak valid!";
        $message_type = 'error';
    }
}

// Get backup files
$backup_files = [];
if(is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach($files as $file) {
        if($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file)
            ];
        }
    }
    // Sort by date descending
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Admin Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        
        .container {
            max-width: 1200px;
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
        
        .btn-success {
            background: #10b981;
        }
        
        .btn-danger {
            background: #ef4444;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .card-content {
            margin-top: 20px;
        }
        
        .backup-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .info-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .file-list {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .file-item:hover {
            background: #f3f4f6;
            transform: translateX(5px);
        }
        
        .file-name {
            font-weight: 500;
            color: #1f2937;
        }
        
        .file-meta {
            font-size: 13px;
            color: #6b7280;
        }
        
        .file-size {
            background: #e5e7eb;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .file-date {
            font-size: 12px;
            color: #9ca3af;
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
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3751fe;
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
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
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .warning-title {
            font-weight: 600;
            color: #92400e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-text {
            color: #92400e;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-database"></i> Backup & Restore Database</h1>
            <a href="petugas_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Backup Section -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-save"></i> Backup Database
            </div>
            
            <div class="backup-info">
                <div class="info-row">
                    <span class="info-label">Database:</span>
                    <span class="info-value">ecommerce_enh</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tabel:</span>
                    <span class="info-value">users, categories, products, transactions, transaction_details</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lokasi Backup:</span>
                    <span class="info-value"><?php echo realpath($backup_dir); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Backup Files:</span>
                    <span class="info-value"><?php echo count($backup_files); ?> file</span>
                </div>
            </div>
            
            <form method="POST" class="card-content">
                <div class="warning-box">
                    <div class="warning-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Perhatian!</span>
                    </div>
                    <div class="warning-text">
                        Backup database akan membuat salinan lengkap dari semua data. Pastikan Anda memiliki ruang penyimpanan yang cukup.
                    </div>
                </div>
                
                <button type="submit" name="backup" class="btn btn-success">
                    <i class="fas fa-download"></i> Buat Backup Sekarang
                </button>
            </form>
        </div>
        
        <!-- Restore Section -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-upload"></i> Restore Database
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Restore database akan mengganti semua data yang ada dengan data dari file backup. Pastikan Anda sudah membuat backup terbaru sebelum melakukan restore!
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="card-content">
                <div class="form-group">
                    <label for="backup_file">Pilih File Backup (.sql) <span style="color: red;">*</span></label>
                    <input type="file" id="backup_file" name="backup_file" accept=".sql" required>
                </div>
                
                <div class="warning-box">
                    <div class="warning-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>PERINGATAN!</span>
                    </div>
                    <div class="warning-text">
                        <strong>Proses restore akan menghapus semua data yang ada saat ini dan menggantinya dengan data dari file backup.</strong>
                        <br><br>
                        Pastikan Anda:
                        <ul style="margin-left: 20px; margin-top: 10px;">
                            <li>Sudah membuat backup terbaru</li>
                            <li>Memilih file backup yang benar</li>
                            <li>Memahami konsekuensi dari restore database</li>
                        </ul>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="restore" class="btn btn-danger" onclick="return confirm('Yakin ingin restore database? Semua data akan diganti!')">
                        <i class="fas fa-upload"></i> Restore Database
                    </button>
                    <a href="petugas_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Backup Files List -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-folder"></i> Daftar Backup Files
            </div>
            
            <?php if(count($backup_files) > 0): ?>
                <div class="file-list">
                    <?php foreach($backup_files as $file): ?>
                        <div class="file-item">
                            <div>
                                <div class="file-name">
                                    <i class="fas fa-file"></i> <?php echo $file['name']; ?>
                                </div>
                                <div class="file-meta">
                                    <span class="file-size">
                                        <?php 
                                        $size = $file['size'];
                                        if($size < 1024) echo $size . ' B';
                                        elseif($size < 1024*1024) echo round($size/1024, 2) . ' KB';
                                        else echo round($size/(1024*1024), 2) . ' MB';
                                        ?>
                                    </span>
                                    <span class="file-date">
                                        <i class="fas fa-clock"></i> <?php echo date('d M Y H:i', $file['date']); ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <a href="<?php echo $backup_dir . $file['name']; ?>" download class="btn btn-sm btn-secondary" style="padding: 8px 15px;">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #9ca3af;">
                    <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>Belum ada backup file</p>
                    <p style="font-size: 14px; margin-top: 10px;">Buat backup pertama Anda sekarang!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>