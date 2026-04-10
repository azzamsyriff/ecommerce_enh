<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - E-Commerce Platform</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: flex;
            flex-direction: row;
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #3751fe 0%, #667eea 100%);
            padding: 60px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }

        .right-panel {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-family: 'Inter', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #3751fe;
            margin-right: 10px;
        }

        .logo span {
            font-size: 24px;
            color: rgba(0, 0, 0, 0.6);
        }

        .welcome-text h2 {
            font-family: 'Inter', sans-serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
        }

        .welcome-text p {
            font-family: 'Montserrat', sans-serif;
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .form-title {
            font-family: 'Inter', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #3751fe;
            margin-bottom: 10px;
        }

        .form-subtitle {
            font-family: 'Montserrat', sans-serif;
            color: rgba(0, 0, 0, 0.6);
            font-size: 16px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: rgba(0, 0, 0, 0.6);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            border-color: #3751fe;
            box-shadow: 0 0 0 3px rgba(55, 81, 254, 0.1);
        }

        .terms {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .terms input[type="checkbox"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
        }

        .terms label {
            font-size: 14px;
            color: #333;
            margin: 0;
        }

        .terms a {
            color: #3751fe;
            text-decoration: none;
            font-weight: 600;
            margin: 0 5px;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
            width: 100%;
        }

        .btn-primary {
            background: #3751fe;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #2d43d9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(55, 81, 254, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #3751fe;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .left-panel, .right-panel {
                padding: 40px;
            }
        }
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
                <h2>Create Your Account & Start Shopping Smarter</h2>
                <p>Sign up to explore thousands of products, track your orders, and enjoy exclusive deals tailored just for you.</p>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="logo">
                <h1>E-Commerce</h1>
                <span>Platform</span>
            </div>
            
            <h2 class="form-title">Create Account</h2>
            <p class="form-subtitle">Fill in your details to get started with your shopping journey</p>
            
            <?php
            // session_start();
            if(isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }
            if(isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            ?>
            
            <form action="process_register.php" method="POST">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number (optional)</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Create a strong password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter your password">
                </div>
                
                <div class="terms">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Account</button>
                <div class="login-link">
                    Already have an account? <a href="login.php">Sign In</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>