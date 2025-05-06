<?php
// Bao gồm cấu hình cơ sở dữ liệu
require_once 'config/database.php';
require_once 'config/functions.php';

// Khởi tạo biến
$token = "";
$email = "";
$password = "";
$confirm_password = "";
$error_message = "";
$success_message = "";

// Kiểm tra nếu token và email được cung cấp trong URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = trim($_GET['token']);
    $email = trim($_GET['email']);
    
    // Xác thực token
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND created_at > (NOW() - INTERVAL 24 HOUR)");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Liên kết đặt lại không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu đặt lại mật khẩu mới.";
    }
    $stmt->close();
}

// Xử lý khi biểu mẫu được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Kiểm tra mật khẩu
    if (empty($password)) {
        $error_message = "Vui lòng nhập mật khẩu";
    } elseif (strlen($password) < 8) {
        $error_message = "Mật khẩu phải có ít nhất 8 ký tự";
    } elseif ($password !== $confirm_password) {
        $error_message = "Mật khẩu không khớp";
    } else {
        // Xác thực lại token
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND created_at > (NOW() - INTERVAL 24 HOUR)");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = "Liên kết đặt lại không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu đặt lại mật khẩu mới.";
        } else {
            // Cập nhật mật khẩu
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Use the users table for all user types
            $user_table = "users";
            
            // Cập nhật mật khẩu
            $stmt = $conn->prepare("UPDATE $user_table SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                // Xóa token
                $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $success_message = "Mật khẩu của bạn đã được cập nhật thành công. Bạn có thể <a href='index.php'>đăng nhập</a> bằng mật khẩu mới.";
            } else {
                $error_message = "Lỗi khi cập nhật mật khẩu. Vui lòng thử lại.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>Đặt lại mật khẩu của bạn</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php else: ?>
                <?php if (!empty($email) && !empty($token) && empty($error_message)): ?>
                    <form method="post" action="">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label for="password">Mật khẩu mới</label>
                            <input type="password" id="password" name="password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Xác nhận mật khẩu mới</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Đặt lại mật khẩu</button>
                    </form>
                <?php elseif (empty($success_message)): ?>
                    <div class="alert alert-info">
                        <p>Vui lòng sử dụng liên kết từ email của bạn để đặt lại mật khẩu.</p>
                        <p><a href="forgot-password.php">Yêu cầu đặt lại mật khẩu mới</a></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="links">
                <a href="index.php">Quay lại đăng nhập</a>
            </div>
        </div>
    </div>
</body>
</html>