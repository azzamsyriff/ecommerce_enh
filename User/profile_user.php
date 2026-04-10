<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../Auth/login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Get current user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    if(!$user) {
        header('Location: ../Auth/logout.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header('Location: user_dashboard.php');
    exit();
}

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']); // Field baru

    // Validasi email uniqueness (kecuali untuk user sendiri)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $stmt->execute(['email' => $email, 'id' => $user_id]);
    if($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email sudah digunakan oleh user lain!";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET full_name = :full_name, email = :email, phone = :phone, address = :address WHERE id = :id");
            $stmt->execute([
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'id' => $user_id
            ]);
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['address'] = $address; // Simpan ke session
            
            $_SESSION['success'] = "Profile berhasil diupdate!";
            header('Location: profile_user.php');
            exit();
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    if(!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = "Password lama tidak sesuai!";
    } elseif($new_password !== $confirm_password) {
        $_SESSION['error'] = "Password baru tidak cocok!";
    } elseif(strlen($new_password) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter!";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute([
                'password' => $hashed_password,
                'id' => $user_id
            ]);
            $_SESSION['success'] = "Password berhasil diubah!";
            header('Location: profile_user.php');
            exit();
        } catch(PDOException $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Handle upload profile picture
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    // Validasi file
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if($file['error'] != 0) {
        $_SESSION['error'] = "Terjadi kesalahan saat upload file!";
    } elseif(!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Ekstensi file tidak valid! Hanya JPG, JPEG, PNG, dan GIF yang diperbolehkan.";
    } elseif($file['size'] > $max_size) {
        $_SESSION['error'] = "Ukuran file terlalu besar! Maksimal 5MB.";
    } else {
        $target_dir = "../uploads/profiles/";
        if(!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        // Delete old picture if exists
        if($user['profile_picture'] && file_exists($target_dir . $user['profile_picture'])) {
            unlink($target_dir . $user['profile_picture']);
        }
        // Generate unique filename
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $filename;
        
        if(move_uploaded_file($file['tmp_name'], $target_file)) {
            try {
                $stmt = $conn->prepare("UPDATE users SET profile_picture = :picture WHERE id = :id");
                $stmt->execute([
                    'picture' => $filename,
                    'id' => $user_id
                ]);
                $_SESSION['success'] = "Foto profile berhasil diupdate!";
                header('Location: profile_user.php');
                exit();
            } catch(PDOException $e) {
                $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Gagal mengupload file!";
        }
    }
}

// Handle delete profile picture
if(isset($_GET['delete_picture'])) {
    try {
        $target_dir = "../uploads/profiles/";
        if($user['profile_picture'] && file_exists($target_dir . $user['profile_picture'])) {
            unlink($target_dir . $user['profile_picture']);
        }
        $stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $_SESSION['success'] = "Foto profile berhasil dihapus!";
        header('Location: profile_user.php');
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
<title>Profile - User Dashboard</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
.container { max-width: 1200px; margin: 30px auto; padding: 20px; }
.header { background: white; padding: 20px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
.header h1 { color: #3751fe; font-size: 24px; display: flex; align-items: center; gap: 10px; }
.btn { display: inline-block; padding: 12px 25px; background: #3751fe; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
.btn:hover { background: #2d43d9; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(55, 81, 254, 0.3); }
.btn-secondary { background: #6b7280; }
.btn-secondary:hover { background: #4b5563; }
.btn-danger { background: #ef4444; }
.btn-danger:hover { background: #dc2626; }
.btn-success { background: #10b981; }
.btn-success:hover { background: #0da271; }
.btn-warning { background: #f59e0b; }
.btn-warning:hover { background: #d97706; }
.profile-container { display: grid; grid-template-columns: 300px 1fr; gap: 25px; }
.profile-sidebar { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.profile-picture { display: flex; flex-direction: column; align-items: center; gap: 20px; margin-bottom: 25px; }
.avatar-large { width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #3751fe 0%, #667eea 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 48px; overflow: hidden; position: relative; box-shadow: 0 4px 15px rgba(55, 81, 254, 0.3); cursor: pointer; transition: all 0.3s; }
.avatar-large:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(55, 81, 254, 0.5); }
.avatar-large img { width: 100%; height: 100%; object-fit: cover; }
.upload-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(55, 81, 254, 0.8); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; border-radius: 50%; opacity: 0; transition: opacity 0.3s; }
.avatar-large:hover .upload-overlay { opacity: 1; }
.upload-icon { font-size: 24px; margin-bottom: 5px; }
.upload-text { font-size: 12px; font-weight: 500; }
.user-info { text-align: center; margin-bottom: 20px; }
.user-name { font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
.user-email { font-size: 14px; color: #6b7280; margin-bottom: 5px; }
.user-role { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; }
.user-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
.stat-item { text-align: center; padding: 15px; background: #f9fafb; border-radius: 10px; }
.stat-value { font-size: 24px; font-weight: 700; color: #3751fe; margin-bottom: 5px; }
.stat-label { font-size: 12px; color: #6b7280; }
.profile-content { display: flex; flex-direction: column; gap: 25px; }
.profile-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.card-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f3f4f6; display: flex; align-items: center; gap: 10px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-weight: 500; color: #374151; margin-bottom: 8px; font-size: 14px; }
.form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s; font-family: inherit; }
.form-group textarea { min-height: 80px; resize: vertical; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.btn-group { display: flex; gap: 10px; margin-top: 20px; }
.alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.file-upload-area { background: #f9fafb; border: 2px dashed #3751fe; border-radius: 10px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s; margin-top: 20px; }
.file-upload-area:hover { background: #dbeafe; border-color: #2d43d9; }
.file-upload-area i { font-size: 48px; color: #3751fe; margin-bottom: 15px; }
.file-upload-area h4 { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 10px; }
.file-upload-area p { color: #6b7280; font-size: 14px; margin-bottom: 15px; }
.allowed-extensions { display: flex; justify-content: center; gap: 15px; margin-top: 15px; flex-wrap: wrap; }
.allowed-extensions span { display: inline-flex; align-items: center; gap: 5px; background: white; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #3751fe; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.max-size { background: #fef3c7; color: #92400e; padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; margin-top: 10px; }
.delete-picture { margin-top: 15px; }
@media (max-width: 768px) {
    .profile-container { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <a href="user_dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h1><i class="fas fa-user"></i> Profile Saya</h1>
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
    <div class="profile-container">
        <!-- Sidebar Profile -->
        <div class="profile-sidebar">
            <div class="profile-picture">
                <div class="avatar-large" onclick="document.getElementById('file_input').click()">
                    <?php if($user['profile_picture'] && file_exists('../uploads/profiles/' . $user['profile_picture'])): ?>
                    <img src="../uploads/profiles/<?php echo $user['profile_picture']; ?>" alt="Profile Picture" id="profile_image">
                    <?php else: ?>
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                    <div class="upload-overlay">
                        <div class="upload-icon"><i class="fas fa-camera"></i></div>
                        <div class="upload-text">Ganti Foto</div>
                    </div>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                </div>
            </div>
            <div class="user-stats">
                <div class="stat-item"><div class="stat-value">3</div><div class="stat-label">Role</div></div>
                <div class="stat-item"><div class="stat-value">0</div><div class="stat-label">Notifikasi</div></div>
                <div class="stat-item"><div class="stat-value"><?php echo date('Y'); ?></div><div class="stat-label">Tahun Aktif</div></div>
                <div class="stat-item"><div class="stat-value">✓</div><div class="stat-label">Verified</div></div>
            </div>
        </div>
        <!-- Main Content -->
        <div class="profile-content">
            <!-- Update Profile -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-edit"></i> Update Profile</div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Nama Lengkap <span style="color: red;">*</span></label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span style="color: red;">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Nomor Telepon</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Contoh: 081234567890">
                        </div>
                        <div class="form-group">
                            <label for="address">Alamat Lengkap <span style="color: red;">*</span></label>
                            <textarea id="address" name="address" placeholder="Masukkan alamat lengkap untuk pengiriman" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Informasi profile dan alamat akan digunakan untuk komunikasi, notifikasi, dan pengiriman pesanan.
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_profile" class="btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        <button type="reset" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</button>
                    </div>
                </form>
            </div>
            <!-- Upload Profile Picture -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-image"></i> Foto Profile</div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="file-upload-area" onclick="document.getElementById('file_input').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h4>Klik untuk Upload Foto</h4>
                        <p>atau drag & drop foto Anda ke sini</p>
                        <div class="allowed-extensions">
                            <span><i class="fas fa-file-image"></i> JPG</span>
                            <span><i class="fas fa-file-image"></i> JPEG</span>
                            <span><i class="fas fa-file-image"></i> PNG</span>
                            <span><i class="fas fa-file-image"></i> GIF</span>
                        </div>
                        <div class="max-size"><i class="fas fa-info-circle"></i> Maksimal: 5MB</div>
                        <input type="file" id="file_input" name="profile_picture" accept=".jpg,.jpeg,.png,.gif" style="display: none;">
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> <strong>Catatan:</strong>
                        <ul style="margin-left: 20px; margin-top: 8px;">
                            <li>Hanya file dengan ekstensi <strong>JPG, JPEG, PNG, dan GIF</strong> yang diperbolehkan</li>
                            <li>Ukuran file maksimal <strong>5MB</strong></li>
                            <li>Foto akan otomatis mengganti foto lama</li>
                        </ul>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn"><i class="fas fa-upload"></i> Upload Foto</button>
                        <?php if($user['profile_picture']): ?>
                        <a href="?delete_picture=1" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus foto profile?')"><i class="fas fa-trash"></i> Hapus Foto</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <!-- Change Password -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-key"></i> Ubah Password</div>
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Password Lama <span style="color: red;">*</span></label>
                        <input type="password" id="current_password" name="current_password" required placeholder="Masukkan password lama">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Password Baru <span style="color: red;">*</span></label>
                            <input type="password" id="new_password" name="new_password" required placeholder="Minimal 6 karakter" minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password <span style="color: red;">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Ulangi password baru" minlength="6">
                        </div>
                    </div>
                    <div class="security-section">
                        <div class="security-title"><i class="fas fa-shield-alt"></i> <span>Keamanan Password</span></div>
                        <div class="security-text">
                            <ul style="margin-left: 20px; margin-top: 8px;">
                                <li>Password minimal 6 karakter</li>
                                <li>Gunakan kombinasi huruf, angka, dan simbol</li>
                                <li>Jangan gunakan password yang mudah ditebak</li>
                                <li>Rutin ganti password setiap 3 bulan</li>
                            </ul>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="change_password" class="btn btn-warning"><i class="fas fa-key"></i> Ubah Password</button>
                    </div>
                </form>
            </div>
            <!-- Account Information -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Informasi Akun</div>
                <div style="display: grid; gap: 15px;">
                    <div class="form-group"><label>ID Akun</label><input type="text" value="<?php echo $user['id']; ?>" readonly style="background: #f9fafb; cursor: not-allowed;"></div>
                    <div class="form-group"><label>Role</label><input type="text" value="<?php echo ucfirst($user['role']); ?>" readonly style="background: #f9fafb; cursor: not-allowed;"></div>
                    <div class="form-group"><label>Tanggal Daftar</label><input type="text" value="<?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?>" readonly style="background: #f9fafb; cursor: not-allowed;"></div>
                    <div class="form-group"><label>Terakhir Update</label><input type="text" value="<?php echo date('d M Y, H:i', strtotime($user['updated_at'])); ?>" readonly style="background: #f9fafb; cursor: not-allowed;"></div>
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f3f4f6;">
                    <a href="../Auth/logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('file_input').addEventListener('change', function(e) {
    if(this.files && this.files[0]) {
        const file = this.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        alert(`File dipilih:\n${fileName}\nUkuran: ${fileSize} MB`);
    }
});
</script>
</body>
</html>