<?php
// 🔒 WAJIB: Start session agar bisa membaca $_SESSION['error'] dari process_login.php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce Platform</title>
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@700&display=swap">
    <!-- 🔒 Font Awesome untuk ikon notifikasi -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); overflow: hidden; max-width: 1000px; width: 100%; display: flex; flex-direction: row; }
        .left-panel { flex: 1; background: linear-gradient(135deg, #3751fe 0%, #667eea 100%); padding: 60px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .right-panel { flex: 1; padding: 60px; display: flex; flex-direction: column; justify-content: center; }
        .logo { display: flex; align-items: center; margin-bottom: 30px; }
        .logo h1 { font-family: 'Inter', sans-serif; font-size: 32px; font-weight: 700; color: #3751fe; margin-right: 10px; }
        .logo span { font-size: 24px; color: rgba(0, 0, 0, 0.6); }
        .welcome-text h2 { font-family: 'Inter', sans-serif; font-size: 36px; font-weight: 700; margin-bottom: 20px; color: white; }
        .welcome-text p { font-size: 18px; color: rgba(255, 255, 255, 0.8); line-height: 1.6; }
        .form-title { font-family: 'Inter', sans-serif; font-size: 32px; font-weight: 700; color: #3751fe; margin-bottom: 10px; }
        .form-subtitle { color: rgba(0, 0, 0, 0.6); font-size: 16px; margin-bottom: 40px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-size: 14px; color: rgba(0, 0, 0, 0.6); margin-bottom: 8px; font-weight: 500; }
        .form-group input { width: 100%; padding: 15px 20px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; transition: all 0.3s; }
        .form-group input:focus { border-color: #3751fe; box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1); }
        .remember-forgot { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .remember { display: flex; align-items: center; }
        .remember input[type="checkbox"] { margin-right: 10px; width: 18px; height: 18px; }
        .forgot-password { color: #3751fe; text-decoration: none; font-weight: 500; transition: opacity 0.3s; }
        .forgot-password:hover { opacity: 0.8; }
        .btn { padding: 15px 40px; border: none; border-radius: 10px; font-size: 16px; font-weight: 500; cursor: pointer; transition: all 0.3s; display: inline-block; text-align: center; }
        .btn-primary { background: #3751fe; color: white; width: 100%; }
        .btn-primary:hover { background: #2d43d9; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(55, 81, 254, 0.3); }
        .btn-secondary { background: white; color: #3751fe; border: 2px solid #3751fe; width: 100%; margin-top: 15px; }
        .btn-secondary:hover { background: #3751fe; color: white; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 768px) { .container { flex-direction: column; } .left-panel, .right-panel { padding: 40px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="logo">
                <h1>E-Commerce</h1>
                <span>Platform</span>
            </div>
            <div class="welcome-text">
                <h2>Join Us and Enjoy Seamless Online Shopping</h2>
                <p>Welcome back! Please login to your account to access exclusive features and start shopping smarter.</p>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="logo">
                <h1>E-Commerce</h1>
                <span>Platform</span>
            </div>
            
            <h2 class="form-title">Welcome Back</h2>
            <p class="form-subtitle">Login to continue your shopping journey</p>
            
            <!-- 🔒 MENAMPILKAN NOTIFIKASI ERROR/SUCCESS DARI SESSION -->
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <span><?php echo htmlspecialchars($_SESSION['success']); ?></span>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <form action="process_login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <div class="remember-forgot">
                    <div class="remember">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember" style="display: inline; margin: 0;">Remember Me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary">Sign In</button>
                <a href="register.php" class="btn btn-secondary">Sign Up</a>
                <a href="../User/user_dashboard.php" class="btn btn-secondary">Continue as Guest</a>
            </form>
        </div>
    </div>
</body>
</html>