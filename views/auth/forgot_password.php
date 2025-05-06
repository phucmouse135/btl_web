<?php
require_once 'config/functions.php';
$pageTitle = "Quên mật khẩu";

// Khởi tạo biến
$email = "";
$error = "";
$success = "";

// Xử lý khi biểu mẫu được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra email
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Vui lòng nhập email";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Định dạng email không hợp lệ";
    } else {
        // Kiểm tra xem email có tồn tại trong cơ sở dữ liệu không
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        if (!$stmt) {
            $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = "Không tìm thấy tài khoản với địa chỉ email này";
            } else {
                $user = $result->fetch_assoc();
                
                // Tạo mã đặt lại mật khẩu
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // Mã hết hạn sau 1 giờ
                
                // Lưu mã vào cơ sở dữ liệu
                $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                if (!$stmt) {
                    $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
                } else {
                    $stmt->bind_param("issss", $user['id'], $token, $expires, $token, $expires);
                    if ($stmt->execute()) {
                        // Thành công! Gửi email (chỉ là trình giữ chỗ)
                        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/LTW/reset-password.php?token=" . $token;
                        
                        // Trong ứng dụng thực tế, bạn sẽ gửi email thực tại đây
                        // Hiện tại, chỉ hiển thị liên kết (dành cho mục đích phát triển)
                        $success = "Liên kết đặt lại mật khẩu đã được tạo. Trong môi trường thực tế, email sẽ được gửi.";
                        
                        // Hiển thị liên kết đặt lại (xóa trong môi trường thực tế)
                        $success .= "<br><br>Liên kết đặt lại (chỉ dành cho phát triển): <a href='$resetLink'>$resetLink</a>";
                        $email = ""; // Xóa biểu mẫu
                    } else {
                        $error = "Lỗi khi tạo mã đặt lại mật khẩu: " . $stmt->error;
                    }
                }
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
    <title><?php echo $pageTitle; ?> - Hệ thống quản lý ký túc xá</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-key me-2"></i><?php echo $pageTitle; ?></h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        echo displayError($error);
                        echo displaySuccess($success);
                        ?>
                        
                        <?php if (empty($success)): ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Địa chỉ Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" required>
                                <div class="form-text">
                                    Nhập địa chỉ email liên kết với tài khoản của bạn.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-envelope me-2"></i>Gửi liên kết đặt lại
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Quay lại đăng nhập
                                </a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>