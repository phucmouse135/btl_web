<?php
// Bao gồm header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Lấy ID phòng từ tham số URL
$roomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($roomId <= 0) {
    // Nếu không có ID hợp lệ, chuyển hướng về trang danh sách
    header("Location: /LTW/views/admin/rooms/list.php?error=invalid_id");
    exit();
}

// Lấy thông tin phòng
$roomQuery = "SELECT * FROM rooms WHERE id = ?";
$stmt = $conn->prepare($roomQuery);
$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Nếu không tìm thấy phòng, chuyển hướng về trang danh sách
    $stmt->close();
    header("Location: /LTW/views/admin/rooms/list.php?error=not_found");
    exit();
}

$room = $result->fetch_assoc();
$stmt->close();

// Lấy danh sách sinh viên đang ở phòng này
$studentsQuery = "SELECT u.id, u.first_name, u.last_name, u.student_id, u.email, u.phone, ra.start_date, ra.end_date, ra.status 
                 FROM users u
                 JOIN room_assignments ra ON u.id = ra.user_id
                 WHERE ra.room_id = ? AND ra.status = 'active'";
$stmt = $conn->prepare($studentsQuery);
$stmt->bind_param("i", $roomId);
$stmt->execute();
$studentsResult = $stmt->get_result();
$students = [];

while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// Xác định loại phòng dựa trên sức chứa
$roomType = '';
switch ($room['capacity']) {
    case 1:
        $roomType = 'Phòng đơn';
        break;
    case 2:
        $roomType = 'Phòng đôi';
        break;
    case 3:
        $roomType = 'Phòng ba';
        break;
    case 4:
        $roomType = 'Phòng bốn';
        break;
    default:
        $roomType = 'Phòng ' . $room['capacity'] . ' người';
}

// Xử lý các hành động
$message = '';
$error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'set_maintenance') {
        $updateStmt = $conn->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = ?");
        $updateStmt->bind_param("i", $id);
        
        if ($updateStmt->execute()) {
            $message = "Phòng đã được chuyển sang chế độ bảo trì.";
            $room['status'] = 'maintenance';
        } else {
            $error = "Không thể cập nhật trạng thái phòng.";
        }
        
        $updateStmt->close();
    } elseif ($action === 'set_available') {
        $updateStmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
        $updateStmt->bind_param("i", $id);
        
        if ($updateStmt->execute()) {
            $message = "Phòng đã được đặt là khả dụng.";
            $room['status'] = 'available';
        } else {
            $error = "Không thể cập nhật trạng thái phòng.";
        }
        
        $updateStmt->close();
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chi tiết phòng: <?php echo $room['building_name'] . ' - ' . $room['room_number']; ?></h1>
    <div>
        <a href="/LTW/views/admin/rooms/edit.php?id=<?php echo $roomId; ?>" class="btn btn-primary me-2">
            <i class="fas fa-edit me-1"></i> Chỉnh sửa
        </a>
        <a href="/LTW/views/admin/rooms/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
        </a>
    </div>
</div>

<?php if ($message): ?>
    <?php echo displaySuccess($message); ?>
<?php endif; ?>

<?php if ($error): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<!-- Thông tin phòng -->
<div class="row">
    <div class="col-lg-12">
        <!-- Thông tin cơ bản -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Thông tin phòng</h6>
                <?php
                $statusClass = '';
                $statusText = '';
                switch ($room['status']) {
                    case 'available':
                        $statusClass = 'bg-success';
                        $statusText = 'Khả dụng';
                        break;
                    case 'occupied':
                        $statusClass = 'bg-danger';
                        $statusText = 'Đã sử dụng';
                        break;
                    case 'maintenance':
                        $statusClass = 'bg-warning';
                        $statusText = 'Bảo trì';
                        break;
                }
                ?>
                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6 col-lg-3">
                        <p><strong>Tòa nhà:</strong> <?php echo $room['building_name']; ?></p>
                        <p><strong>Số phòng:</strong> <?php echo $room['room_number']; ?></p>
                        <p><strong>Tầng:</strong> <?php echo $room['floor']; ?></p>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <p><strong>Loại phòng:</strong> <?php echo $roomType; ?></p>
                        <p><strong>Sức chứa:</strong> <?php echo $room['capacity']; ?> người</p>
                        <p><strong>Số người hiện tại:</strong> <?php echo $room['current_occupancy']; ?>/<?php echo $room['capacity']; ?></p>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <p><strong>Giá thuê hàng tháng:</strong> <?php echo number_format($room['monthly_rent'], 0, ',', '.'); ?> đ</p>
                        <p><strong>Ngày tạo:</strong> <?php echo date('d/m/Y', strtotime($room['created_at'])); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="d-grid gap-2">
                            <?php if ($room['status'] != 'maintenance'): ?>
                                <a class="btn btn-warning" href="?action=set_maintenance&id=<?php echo $roomId; ?>" onclick="return confirm('Đặt phòng này vào chế độ bảo trì?')">
                                    <i class="fas fa-tools me-2"></i> Đặt thành bảo trì
                                </a>
                            <?php else: ?>
                                <a class="btn btn-success" href="?action=set_available&id=<?php echo $roomId; ?>" onclick="return confirm('Đánh dấu phòng này là khả dụng?')">
                                    <i class="fas fa-check-circle me-2"></i> Đặt thành khả dụng
                                </a>
                            <?php endif; ?>
                            <a href="/LTW/views/admin/contracts/room_assignments.php?room_id=<?php echo $roomId; ?>" class="btn btn-primary">
                                <i class="fas fa-users me-2"></i> Quản lý phân phòng
                            </a>
                        </div>
                    </div>
                </div>
                <div class="mb-0">
                    <p><strong>Mô tả:</strong></p>
                    <p><?php echo empty($room['description']) ? 'Không có mô tả' : nl2br($room['description']); ?></p>
                </div>
            </div>
        </div>

        <!-- Danh sách sinh viên đang ở -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Sinh viên đang ở (<?php echo count($students); ?>)</h6>
                <?php if (!empty($students)): ?>
                <a href="/LTW/views/admin/contracts/room_assignments.php?room_id=<?php echo $roomId; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-users me-1"></i> Quản lý người ở
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-1"></i> Không có sinh viên nào đang ở phòng này.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã sinh viên</th>
                                    <th>Họ tên</th>
                                    <th>Liên hệ</th>
                                    <th>Ngày bắt đầu</th>
                                    <th>Ngày kết thúc</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['student_id']; ?></td>
                                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                        <td>
                                            <small><i class="fas fa-envelope me-1"></i> <?php echo $student['email']; ?></small><br>
                                            <small><i class="fas fa-phone me-1"></i> <?php echo $student['phone']; ?></small>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($student['start_date'])); ?></td>
                                        <td>
                                            <?php echo $student['end_date'] ? date('d/m/Y', strtotime($student['end_date'])) : 'Không xác định'; ?>
                                        </td>
                                        <td>
                                            <a href="/LTW/views/admin/students/view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Xem
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>