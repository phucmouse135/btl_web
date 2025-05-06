<?php
// Include cơ sở dữ liệu và các hàm
require_once '../config/database.php';
require_once '../config/functions.php';

// Yêu cầu đăng nhập để truy cập trang này
requireLogin();

// Khởi tạo biến
$message = '';
$errorMsg = '';
$successMsg = '';

// Lấy thông tin người dùng dựa trên vai trò và ID
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Xử lý cập nhật hồ sơ nếu biểu mẫu được gửi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    // Lấy dữ liệu biểu mẫu
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Cập nhật thông tin cơ bản (tên, email, điện thoại)
    $updateBasicInfoSuccess = false;
    
    // Sử dụng bảng users cho mọi loại người dùng
    $sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $name, $email, $phone, $userId);
    $updateBasicInfoSuccess = $stmt->execute();
    $stmt->close();
    
    // Xử lý thay đổi mật khẩu nếu được yêu cầu
    if (!empty($currentPassword) && !empty($newPassword)) {
        if ($newPassword != $confirmPassword) {
            $errorMsg = "Mật khẩu mới và xác nhận không khớp.";
        } else {
            // Xác minh mật khẩu hiện tại
            $passwordField = "password";
            $table = "users"; // Luôn sử dụng bảng users
            
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
                        $successMsg = "Hồ sơ và mật khẩu đã được cập nhật thành công.";
                        // Ghi lại hoạt động
                        logActivity("Đã thay đổi mật khẩu", "Người dùng đã thay đổi mật khẩu của họ", $userId, $userRole);
                    } else {
                        $errorMsg = "Lỗi khi cập nhật mật khẩu: " . $conn->error;
                    }
                } else {
                    $errorMsg = "Mật khẩu hiện tại không chính xác.";
                }
            }
            $stmt->close();
        }
    } else if ($updateBasicInfoSuccess) {
        $successMsg = "Hồ sơ đã được cập nhật thành công.";
        // Ghi lại hoạt động
        logActivity("Đã cập nhật hồ sơ", "Người dùng đã cập nhật thông tin hồ sơ của họ", $userId, $userRole);
    }
}

// Lấy thông tin người dùng hiện tại
$userInfo = [];

// Lấy thông tin người dùng từ bảng users (cho tất cả vai trò)
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userInfo = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Nếu là sinh viên, lấy thêm thông tin thanh toán
    if ($userRole == 'student') {
        // Lấy thông tin thanh toán
        $paySql = "SELECT status as payment_status, due_date as next_payment 
                  FROM payments 
                  WHERE student_id = ? AND status = 'pending' 
                  ORDER BY due_date ASC LIMIT 1";
        $payStmt = $conn->prepare($paySql);
        if ($payStmt) {
            $payStmt->bind_param("i", $userId);
            $payStmt->execute();
            $payResult = $payStmt->get_result();
            
            if ($payResult->num_rows > 0) {
                $payInfo = $payResult->fetch_assoc();
                // Thêm thông tin thanh toán vào mảng thông tin người dùng
                $userInfo['payment_status'] = $payInfo['payment_status'];
                $userInfo['next_payment'] = $payInfo['next_payment'];
            } else {
                $userInfo['payment_status'] = 'Không có thanh toán hoạt động';
                $userInfo['next_payment'] = 'N/A';
            }
            $payStmt->close();
        } else {
            // Xử lý lỗi chuẩn bị câu lệnh
            $userInfo['payment_status'] = 'Lỗi khi truy xuất thông tin thanh toán';
            $userInfo['next_payment'] = 'N/A';
        }
    }
} else {
    // Ghi log lỗi SQL để gỡ lỗi
    error_log("Chuẩn bị SQL thất bại: " . $conn->error);
}

// Lấy thông tin phòng cho sinh viên
$roomInfo = '';
if ($userRole == 'student') {
    $roomSql = "SELECT r.id, r.room_number, r.building, r.capacity, r.type,
                GROUP_CONCAT(u.name SEPARATOR ', ') as roommates
                FROM room_assignments ra 
                JOIN rooms r ON ra.room_id = r.id 
                LEFT JOIN room_assignments ra2 ON r.id = ra2.room_id AND ra2.status = 'current' AND ra2.student_id != ?
                LEFT JOIN users u ON ra2.student_id = u.id AND u.role = 'student'
                WHERE ra.student_id = ? AND ra.status = 'current'
                GROUP BY r.id";
    $roomStmt = $conn->prepare($roomSql);
    if ($roomStmt) {
        $roomStmt->bind_param("ii", $userId, $userId);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        
        if ($roomResult->num_rows > 0) {
            $roomData = $roomResult->fetch_assoc();
        }
        $roomStmt->close();
    }
}

// Include header
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Hồ sơ của tôi</h1>
    </div>

    <?php
        echo displayError($errorMsg);
        echo displaySuccess($successMsg);
    ?>

    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin Hồ sơ</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group row">
                            <label for="name" class="col-sm-3 col-form-label">Họ và tên</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($userInfo['name']) ? htmlspecialchars($userInfo['name']) : ''; ?>">
                            </div>
                        </div>
                        
        <h1 class="h3 mb-0 text-gray-800">Hồ sơ của tôi</h1>
    </div>

    <?php
        echo displayError($errorMsg);
        echo displaySuccess($successMsg);
    ?>

    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin Hồ sơ</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group row">
                            <label for="name" class="col-sm-3 col-form-label">Họ và tên</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($userInfo['name']) ? htmlspecialchars($userInfo['name']) : ''; ?>">
                            </div>
                        </div>
                        
                        <?php if ($userRole == 'student'): ?>
                        <div class="form-group row">
                            <label for="student_id" class="col-sm-3 col-form-label">Mã sinh viên</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="student_id" value="<?php echo isset($userInfo['student_id']) ? htmlspecialchars($userInfo['student_id']) : ''; ?>" readonly>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group row">
                            <label for="email" class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($userInfo['email']) ? htmlspecialchars($userInfo['email']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="phone" class="col-sm-3 col-form-label">Điện thoại</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($userInfo['phone']) ? htmlspecialchars($userInfo['phone']) : ''; ?>">
                            </div>
                        </div>
                        
                        <?php if ($userRole == 'student'): ?>
                        <div class="form-group row">
                            <label for="dob" class="col-sm-3 col-form-label">Ngày sinh</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="dob" value="<?php echo isset($userInfo['date_of_birth']) ? formatDate($userInfo['date_of_birth']) : ''; ?>" readonly>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group row">
                            <label for="role" class="col-sm-3 col-form-label">Vai trò</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="role" value="<?php echo getRoleName($userRole); ?>" readonly>
                            </div>
                        </div>
                        
                        <hr>
                        <h5>Đổi mật khẩu</h5>
                        
                        <div class="form-group row">
                            <label for="current_password" class="col-sm-3 col-form-label">Mật khẩu hiện tại</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="new_password" class="col-sm-3 col-form-label">Mật khẩu mới</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="confirm_password" class="col-sm-3 col-form-label">Xác nhận mật khẩu</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" name="update_profile" class="btn btn-primary">Cập nhật Hồ sơ</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin Tài khoản</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img class="img-fluid rounded-circle mb-3" style="max-width: 150px;" src="/LTW/assets/img/default-profile.png" alt="Ảnh hồ sơ">
                        <h5><?php echo isset($userInfo['name']) ? htmlspecialchars($userInfo['name']) : 'Người dùng'; ?></h5>
                        <p class="text-muted"><?php echo getRoleName($userRole); ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Đăng nhập gần đây:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($userInfo['last_login']) ? formatDateTime($userInfo['last_login']) : '-'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Tài khoản được tạo:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($userInfo['created_at']) ? formatDateTime($userInfo['created_at']) : '-'; ?></p>
                        </div>
                    </div>
                    
                    <?php if ($userRole == 'student'): ?>
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Số phòng:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($roomData['building']) ? $roomData['building'] . ' - ' . $roomData['room_number'] : 'Chưa được phân phòng'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Loại phòng:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($roomData['type']) ? $roomData['type'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Sức chứa phòng:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($roomData['capacity']) ? $roomData['capacity'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Trạng thái thanh toán:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($userInfo['payment_status']) ? $userInfo['payment_status'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Thanh toán tiếp theo:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($userInfo['next_payment']) ? formatDate($userInfo['next_payment']) : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Chương trình học:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($academicInfo['program']) ? $academicInfo['program'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Năm học:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($academicInfo['year_level']) ? $academicInfo['year_level'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Ngày nhập học:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($academicInfo['enrollment_date']) ? formatDate($academicInfo['enrollment_date']) : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Trạng thái học tập:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($academicInfo['academic_status']) ? $academicInfo['academic_status'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Liên hệ khẩn cấp:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($emergencyContact['name']) ? $emergencyContact['name'] . ' (' . $emergencyContact['relationship'] . ')' : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Điện thoại khẩn cấp:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($emergencyContact['phone']) ? $emergencyContact['phone'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Email khẩn cấp:</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0"><?php echo isset($emergencyContact['email']) ? $emergencyContact['email'] : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Yêu cầu bảo trì gần đây:</p>
                        </div>
                        <div class="col-sm-6">
                            <ul class="list-unstyled mb-0">
                                <?php if (!empty($maintenanceRequests)): ?>
                                    <?php foreach ($maintenanceRequests as $request): ?>
                                        <li><?php echo htmlspecialchars($request['issue_type']) . ' - ' . htmlspecialchars($request['status']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>N/A</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted">Thanh toán gần đây:</p>
                        </div>
                        <div class="col-sm-6">
                            <ul class="list-unstyled mb-0">
                                <?php if (!empty($recentPayments)): ?>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <li><?php echo htmlspecialchars($payment['amount']) . ' - ' . htmlspecialchars($payment['status']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>N/A</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>