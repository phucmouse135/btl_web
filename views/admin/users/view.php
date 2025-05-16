<?php
// Bắt đầu output buffering
ob_start();
// Include necessary configurations
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

// Require login
requireLogin();

// Check if user has admin role
if (!hasRole('admin')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID người dùng không hợp lệ';
    header("Location: list.php");
    exit();
}

$userId = (int)$_GET['id'];

// Get user information
$sql = "SELECT u.*, CASE 
            WHEN u.role = 'admin' THEN 'Quản trị viên'
            WHEN u.role = 'staff' THEN 'Nhân viên' 
            WHEN u.role = 'student' THEN 'Sinh viên'
            ELSE 'Không xác định'
        END as role_name
        FROM users u
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Không tìm thấy người dùng';
    header("Location: list.php");
    exit();
}

$user = $result->fetch_assoc();

// Get role-specific information
$roleInfo = null;
$activityLogs = [];

if ($user['role'] == 'admin' || $user['role'] == 'staff') {
    // Get staff information
    $staffSql = "SELECT * FROM staff WHERE user_id = ?";
    $staffStmt = $conn->prepare($staffSql);
    $staffStmt->bind_param("i", $userId);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();
    
    if ($staffResult->num_rows > 0) {
        $roleInfo = $staffResult->fetch_assoc();
    }
    
    // Get recent activity logs
    $logSql = "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $logStmt = $conn->prepare($logSql);
    $logStmt->bind_param("i", $userId);
    $logStmt->execute();
    $logResult = $logStmt->get_result();
    
    while ($log = $logResult->fetch_assoc()) {
        $activityLogs[] = $log;
    }
} elseif ($user['role'] == 'student') {
    // Get student information from users table instead of students table
    $studentSql = "SELECT u.*, u.name as full_name FROM users u WHERE u.id = ?";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->bind_param("i", $userId);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    
    if ($studentResult->num_rows > 0) {
        $roleInfo = $studentResult->fetch_assoc();
    }
    
    // Get current room assignment if any
    if ($roleInfo) {
        $roomSql = "SELECT ra.*, r.room_number, b.name as building_name 
                    FROM room_assignments ra
                    JOIN rooms r ON ra.room_id = r.id
                    JOIN buildings b ON r.building_id = b.id
                    WHERE ra.student_id = ? AND ra.status = 'current'
                    LIMIT 1";
        $roomStmt = $conn->prepare($roomSql);
        $roomStmt->bind_param("i", $roleInfo['id']);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        
        if ($roomResult->num_rows > 0) {
            $roleInfo['room_assignment'] = $roomResult->fetch_assoc();
        }
    }
}

// Set page title
$pageTitle = 'Chi tiết người dùng: ' . htmlspecialchars($user['username']);

// Include header
include '../../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/LTW/dashboard.php">Bảng điều khiển</a></li>
        <li class="breadcrumb-item"><a href="list.php">Quản lý người dùng</a></li>
        <li class="breadcrumb-item active">Chi tiết người dùng</li>
    </ol>
    
    <?php
    // Display messages if any
    if (isset($_SESSION['success'])) {
        echo displaySuccess($_SESSION['success']);
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        echo displayError($_SESSION['error']);
        unset($_SESSION['error']);
    }
    ?>
    
    <div class="row">
        <!-- User Info Card -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    Thông tin tài khoản
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mb-3">
                            <span class="avatar-text"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        </div>
                        <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                        <p>
                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                <?php echo $user['status'] == 'active' ? 'Đang hoạt động' : 'Bị vô hiệu hóa'; ?>
                            </span>
                            <span class="badge bg-primary ms-2">
                                <?php echo $user['role_name']; ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <p><strong>ID:</strong> <?php echo $user['id']; ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Ngày tạo:</strong> <?php echo formatDateTime($user['created_at']); ?></p>
                        <p><strong>Cập nhật lần cuối:</strong> 
                            <?php echo isset($user['updated_at']) ? formatDateTime($user['updated_at']) : 'Không có cập nhật'; ?>
                        </p>
                        <p><strong>Đăng nhập lần cuối:</strong> 
                            <?php echo isset($user['last_login']) ? formatDateTime($user['last_login']) : 'Chưa từng đăng nhập'; ?>
                        </p>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                        </a>
                        <a href="reset_password.php?id=<?php echo $user['id']; ?>" class="btn btn-warning" onclick="return confirm('Bạn có chắc chắn muốn đặt lại mật khẩu cho người dùng này?')">
                            <i class="fas fa-key me-1"></i> Đặt lại mật khẩu
                        </a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <?php if ($user['status'] == 'active'): ?>
                                <a href="status.php?id=<?php echo $user['id']; ?>&action=deactivate" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn vô hiệu hóa người dùng này?')">
                                    <i class="fas fa-ban me-1"></i> Vô hiệu hóa
                                </a>
                            <?php else: ?>
                                <a href="status.php?id=<?php echo $user['id']; ?>&action=activate" class="btn btn-success" onclick="return confirm('Bạn có chắc chắn muốn kích hoạt người dùng này?')">
                                    <i class="fas fa-check me-1"></i> Kích hoạt
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Role-specific Information -->
        <div class="col-xl-8">
            <?php if ($user['role'] == 'student' && $roleInfo): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-graduate me-1"></i>
                        Thông tin sinh viên
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Họ và tên:</strong> <?php echo htmlspecialchars($roleInfo['full_name']); ?></p>
                                <p><strong>Mã sinh viên:</strong> <?php echo htmlspecialchars($roleInfo['student_id']); ?></p>
                                <p><strong>Khoa/Ngành:</strong> <?php echo htmlspecialchars($roleInfo['department']); ?></p>
                                <p><strong>Năm học:</strong> <?php echo htmlspecialchars($roleInfo['year_of_study']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Giới tính:</strong> <?php echo isset($roleInfo['gender']) ? ($roleInfo['gender'] == 'male' ? 'Nam' : 'Nữ') : 'N/A'; ?></p>
                                <p><strong>Ngày sinh:</strong> <?php echo isset($roleInfo['date_of_birth']) ? formatDate($roleInfo['date_of_birth']) : 'Không có thông tin'; ?></p>
                                <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($roleInfo['phone'] ?? 'N/A'); ?></p>
                                <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($roleInfo['address'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        
                        <?php if (isset($roleInfo['room_assignment'])): ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-home me-1"></i> Thông tin phòng hiện tại</h5>
                                <p><strong>Tòa nhà:</strong> <?php echo htmlspecialchars($roleInfo['room_assignment']['building_name']); ?></p>
                                <p><strong>Phòng:</strong> <?php echo htmlspecialchars($roleInfo['room_assignment']['room_number']); ?></p>
                                <p><strong>Ngày bắt đầu:</strong> <?php echo formatDate($roleInfo['room_assignment']['start_date']); ?></p>
                                <p><strong>Ngày kết thúc:</strong> <?php echo $roleInfo['room_assignment']['end_date'] ? formatDate($roleInfo['room_assignment']['end_date']) : 'Không xác định'; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> Sinh viên này chưa được phân phòng.
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="../students/view.php?id=<?php echo $roleInfo['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-info-circle me-1"></i> Xem chi tiết sinh viên
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif (($user['role'] == 'admin' || $user['role'] == 'staff') && $roleInfo): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-id-card me-1"></i>
                        Thông tin nhân viên
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Họ:</strong> <?php echo htmlspecialchars($roleInfo['last_name']); ?></p>
                                <p><strong>Tên:</strong> <?php echo htmlspecialchars($roleInfo['first_name']); ?></p>
                                <p><strong>Chức vụ:</strong> <?php echo htmlspecialchars($roleInfo['position'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($roleInfo['phone'] ?? 'N/A'); ?></p>
                                <p><strong>Phòng/Ban:</strong> <?php echo htmlspecialchars($roleInfo['department'] ?? 'N/A'); ?></p>
                                <p><strong>Ngày vào làm:</strong> <?php echo isset($roleInfo['hire_date']) ? formatDate($roleInfo['hire_date']) : 'N/A'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <?php if (!empty($activityLogs)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-history me-1"></i>
                            Hoạt động gần đây
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Hoạt động</th>
                                            <th>Chi tiết</th>
                                            <th>Thời gian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activityLogs as $log): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['activity_type']); ?></td>
                                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i> Không tìm thấy thông tin chi tiết cho người dùng này.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}

.avatar-text {
    font-size: 3rem;
    color: white;
    font-weight: bold;
}
</style>

<?php
// Include footer
include '../../../includes/footer.php';
?>