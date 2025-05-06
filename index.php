<?php
// Bắt đầu phiên làm việc
session_start();

// Chuyển hướng đến dashboard nếu người dùng đã đăng nhập
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Chuyển hướng đến trang chủ cho khách (không cần đăng nhập)
header("Location: home.php");
exit();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Hệ thống quản lý ký túc xá</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6 col-md-8">
                <div class="card login-card p-4">
                    <div class="login-logo text-center">
                        <i class="fas fa-school fa-4x text-primary"></i>
                        <h2 class="mt-3 text-primary">Quản lý ký túc xá</h2>
                        <p class="text-muted">Nhập tên đăng nhập và mật khẩu của bạn</p>
                    </div>
                    
                    <?php echo displayError($loi); ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Nhập tên đăng nhập của bạn" value="<?php echo $tendangnhap; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Nhập mật khẩu của bạn" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" 
                                        data-target="password" aria-label="Hiện/Ẩn mật khẩu">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                            <a href="forgot-password.php" class="float-end">Quên mật khẩu?</a>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Đăng nhập</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">Thông tin đăng nhập demo:</p>
                        <small class="text-muted">Quản trị viên: admin / admin123</small><br>
                        <small class="text-muted">Sinh viên: student / student123</small>
                    </div>
                </div>
                
                <div class="text-center text-white mt-3">
                    <p>&copy; <?php echo date('Y'); ?> Hệ thống quản lý ký túc xá</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>