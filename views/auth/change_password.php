<?php
// Include cơ sở dữ liệu và các hàm
require_once '../config/database.php';
require_once '../config/functions.php';

// Yêu cầu đăng nhập để truy cập trang này
requireLogin();

// Khởi tạo biến
$errorMsg = '';
$successMsg = '';

// Lấy thông tin người dùng dựa trên vai trò và ID
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Xử lý thay đổi mật khẩu nếu biểu mẫu được gửi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // Lấy dữ liệu biểu mẫu
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Xác thực đầu vào
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMsg = "Tất cả các trường đều bắt buộc.";
    } else if ($newPassword != $confirmPassword) {
        $errorMsg = "Mật khẩu mới và xác nhận không khớp.";
    } else if (strlen($newPassword) < 8) {
        $errorMsg = "Mật khẩu mới phải có ít nhất 8 ký tự.";
    } else {
        // Xác định bảng và trường mật khẩu
        $passwordField = "password";
        $table = "users"; // Use users table for all users including students
        
        // Xác minh mật khẩu hiện tại
        $sql = "SELECT $passwordField FROM $table WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            
            if (password_verify($currentPassword, $row[$passwordField])) {
                // Mật khẩu hiện tại đã xác minh, cập nhật với mật khẩu mới
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $sql = "UPDATE $table SET $passwordField = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $successMsg = "Thay đổi mật khẩu thành công.";
                    // Ghi lại hoạt động
                    logActivity("Mật khẩu Đã thay đổi", "Người dùng đã thay đổi mật khẩu của họ", $userId, $userRole);
                } else {
                    $errorMsg = "Lỗi khi cập nhật mật khẩu: " . $conn->error;
                }
            } else {
                $errorMsg = "Mật khẩu hiện tại không chính xác.";
            }
        } else {
            $errorMsg = "Không tìm thấy người dùng.";
        }
        $stmt->close();
    }
}

// Include header
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Thay đổi Mật khẩu</h1>
    </div>

    <div class="row">
        <div class="col-xl-6 col-lg-7 col-md-8 col-sm-10 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cập nhật Mật khẩu của Bạn</h6>
                </div>
                <div class="card-body">
                    <?php
                        echo displayError($errorMsg);
                        echo displaySuccess($successMsg);
                    ?>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="current_password">Mật khẩu Hiện tại <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Mật khẩu Mới <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">Mật khẩu phải có ít nhất 8 ký tự.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Xác nhận Mật khẩu Mới <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="change_password" class="btn btn-primary">Thay đổi Mật khẩu</button>
                            <a href="/LTW/views/profile.php" class="btn btn-secondary">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Mẹo Bảo mật Mật khẩu</h6>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Sử dụng ít nhất 8 ký tự, bao gồm chữ hoa và chữ thường, số và ký hiệu.</li>
                        <li>Tránh sử dụng thông tin cá nhân như tên, ngày sinh hoặc từ thông dụng.</li>
                        <li>Đừng sử dụng lại mật khẩu trên các trang web khác nhau.</li>
                        <li>Xem xét việc sử dụng trình quản lý mật khẩu để tạo và lưu trữ mật khẩu mạnh.</li>
                        <li>Thay đổi mật khẩu của bạn định kỳ để bảo mật tốt hơn.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>