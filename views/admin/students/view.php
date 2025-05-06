<?php
// Bao gồm header
require_once '../../../includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Lấy ID sinh viên
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    // ID không hợp lệ
    echo displayError("ID sinh viên không hợp lệ");
    require_once '../../../includes/footer.php';
    exit();
}

// Lấy thông tin chi tiết sinh viên
$sql = "SELECT * FROM users WHERE id = ? AND role = 'student'";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo displayError("Lỗi cơ sở dữ liệu: " . $conn->error);
    require_once '../../../includes/footer.php';
    exit();
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Không tìm thấy sinh viên
    echo displayError("Không tìm thấy sinh viên");
    require_once '../../../includes/footer.php';
    exit();
}

$student = $result->fetch_assoc();

// Lấy thông tin phòng hiện tại nếu có
$roomSql = "SELECT ra.*, r.room_number, b.name as building_name, r.room_type as room_type 
            FROM room_assignments ra 
            JOIN rooms r ON ra.room_id = r.id 
            JOIN buildings b ON r.building_id = b.id 
            WHERE ra.student_id = ? AND ra.status = 'current'";
$roomStmt = $conn->prepare($roomSql);
$roomAssignment = null;

if ($roomStmt) {
    $roomStmt->bind_param("i", $id);
    $roomStmt->execute();
    $roomResult = $roomStmt->get_result();
    $roomAssignment = ($roomResult->num_rows > 0) ? $roomResult->fetch_assoc() : null;
    $roomStmt->close();
} else {
    // Ghi nhật ký lỗi nhưng vẫn tiếp tục - điều này không nên ngăn trang tải
    error_log("Truy vấn phân công phòng thất bại: " . $conn->error);
}

// Lấy yêu cầu bảo trì
$requestSql = "SELECT mr.*, r.room_number, b.name as building_name 
               FROM maintenance_requests mr 
               JOIN rooms r ON mr.room_id = r.id 
               JOIN buildings b ON r.building_id = b.id 
               WHERE mr.reported_by = ? 
               ORDER BY mr.request_date DESC LIMIT 5";
$requestStmt = $conn->prepare($requestSql);
$requests = [];

if ($requestStmt) {
    $requestStmt->bind_param("i", $id);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    
    while ($row = $requestResult->fetch_assoc()) {
        $requests[] = $row;
    }
    $requestStmt->close();
} else {
    // Ghi nhật ký lỗi nhưng vẫn tiếp tục
    error_log("Truy vấn yêu cầu bảo trì thất bại: " . $conn->error);
}

// Ghi nhật ký xem này cho mục đích kiểm toán
logActivity('view_student', 'Đã xem hồ sơ sinh viên: ' . $student['first_name'] . ' ' . $student['last_name']);
?>

<!-- Tiêu đề trang -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Thông tin chi tiết sinh viên</h1>
    <div>
        <a href="/LTW/views/admin/students/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Quay lại danh sách
        </a>
        <a href="/LTW/views/admin/students/edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="fas fa-edit mr-1"></i> Chỉnh sửa sinh viên
        </a>
    </div>
</div>

<div class="row">
    <!-- Thẻ hồ sơ sinh viên -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Hồ sơ sinh viên</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <img class="img-fluid rounded-circle mb-3" style="max-width: 150px;" 
                         src="<?php echo !empty($student['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $student['profile_pic']) 
                            ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] 
                            : '/LTW/assets/images/user.png'; ?>" alt="Ảnh hồ sơ">
                    <h5 class="font-weight-bold"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h5>
                    <p class="text-muted mb-2"><?php echo $student['student_id']; ?></p>
                    <span class="badge <?php echo ($student['status'] == 'active') ? 'bg-success' : (($student['status'] == 'inactive') ? 'bg-danger' : 'bg-info'); ?>">
                        <?php echo ucfirst($student['status']); ?>
                    </span>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Khoa:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo $student['department']; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Năm:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo $student['year_of_study']; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Giới tính:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo ucfirst($student['gender']); ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Ngày sinh:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo isset($student['date_of_birth']) ? formatDate($student['date_of_birth']) : 'N/A'; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Điện thoại:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo $student['phone']; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Email:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo $student['email']; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Đăng nhập cuối:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo isset($student['last_login']) ? formatDateTime($student['last_login']) : 'Chưa bao giờ'; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted">Đăng ký:</p>
                    </div>
                    <div class="col-sm-8">
                        <p class="mb-0"><?php echo formatDateTime($student['created_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chi tiết sinh viên -->
    <div class="col-xl-8 col-lg-7">
        <!-- Thẻ phân công phòng -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Phân công phòng</h6>
            </div>
            <div class="card-body">
                <?php if ($roomAssignment): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?php echo $roomAssignment['building_name'] . ' - Phòng ' . $roomAssignment['room_number']; ?></h5>
                            <p class="mb-2">Loại phòng: <?php echo ucfirst($roomAssignment['room_type']); ?></p>
                            <p class="mb-2">Ngày phân công: <?php echo formatDate($roomAssignment['assignment_date']); ?></p>
                            <p>Trạng thái phân công: 
                                <span class="badge bg-success">Hiện tại</span>
                            </p>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="/LTW/views/admin/rooms/view.php?id=<?php echo $roomAssignment['room_id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye mr-1"></i> Xem phòng
                            </a>
                            <a href="/LTW/views/admin/rooms/change_assignment.php?student_id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-exchange-alt mr-1"></i> Đổi phòng
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-home fa-3x text-gray-300 mb-3"></i>
                        <p class="mb-3">Sinh viên này hiện chưa được phân công phòng nào.</p>
                        <a href="/LTW/views/admin/rooms/assign.php?student_id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus mr-1"></i> Phân công phòng
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thẻ yêu cầu bảo trì -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Yêu cầu bảo trì</h6>
                <a href="/LTW/views/maintenance/list.php?student_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-list mr-1"></i> Xem tất cả yêu cầu
                </a>
            </div>
            <div class="card-body">
                <?php if (count($requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Mã yêu cầu</th>
                                    <th>Loại sự cố</th>
                                    <th>Phòng</th>
                                    <th>Ngày</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <a href="/LTW/views/maintenance/view.php?id=<?php echo $request['id']; ?>">
                                                #<?php echo sprintf('%06d', $request['id']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $request['issue_type'])); ?></td>
                                        <td><?php echo $request['building_name'] . ' - ' . $request['room_number']; ?></td>
                                        <td><?php echo formatDate($request['request_date']); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = 'bg-secondary';
                                                if ($request['status'] == 'pending') {
                                                    $statusClass = 'bg-warning text-dark';
                                                } elseif ($request['status'] == 'in_progress') {
                                                    $statusClass = 'bg-info';
                                                } elseif ($request['status'] == 'completed') {
                                                    $statusClass = 'bg-success';
                                                }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tools fa-3x text-gray-300 mb-3"></i>
                        <p>Không tìm thấy yêu cầu bảo trì nào cho sinh viên này.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thẻ thao tác nhanh -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Thao tác nhanh</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 col-md-6 mb-3">
                        <a href="/LTW/views/admin/students/reset_password.php?id=<?php echo $id; ?>" class="btn btn-warning btn-block">
                            <i class="fas fa-key mr-1"></i> Đặt lại mật khẩu
                        </a>
                    </div>
                    <div class="col-lg-6 col-md-6 mb-3">
                        <a href="/LTW/views/admin/students/edit.php?id=<?php echo $id; ?>&action=status" class="btn btn-secondary btn-block">
                            <i class="fas fa-user-clock mr-1"></i> Thay đổi trạng thái
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Thêm JavaScript tùy chỉnh nếu cần -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Bất kỳ JavaScript tùy chỉnh nào có thể được thêm vào đây
    });
</script>

<?php
// Bao gồm footer
require_once '../../../includes/footer.php';
?>