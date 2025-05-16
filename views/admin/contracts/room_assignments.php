<?php
// Add this at the very top of the file
ob_start();
?>
<?php
// Bao gồm các file cần thiết
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Lấy ID phòng từ tham số URL
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

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

// Xử lý kết thúc phân công
if (isset($_GET['end']) && is_numeric($_GET['end'])) {
    $assignmentId = (int)$_GET['end'];
    
    // Lấy thông tin phân công hiện tại
    $assignmentQuery = "SELECT * FROM room_assignments WHERE id = ?";
    $stmt = $conn->prepare($assignmentQuery);
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($assignment) {
        // Cập nhật trạng thái phân công
        $updateStmt = $conn->prepare("UPDATE room_assignments SET status = 'completed', end_date = CURDATE() WHERE id = ?");
        $updateStmt->bind_param("i", $assignmentId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Cập nhật số người ở trong phòng
        $updateRoomStmt = $conn->prepare("UPDATE rooms SET current_occupancy = GREATEST(current_occupancy - 1, 0) WHERE id = ?");
        $updateRoomStmt->bind_param("i", $roomId);
        $updateRoomStmt->execute();
        $updateRoomStmt->close();
        
        // Nếu phòng không còn ai, đặt trạng thái thành available
        $checkOccupancyStmt = $conn->prepare("SELECT current_occupancy FROM rooms WHERE id = ?");
        $checkOccupancyStmt->bind_param("i", $roomId);
        $checkOccupancyStmt->execute();
        $occupancyResult = $checkOccupancyStmt->get_result()->fetch_assoc();
        $checkOccupancyStmt->close();
        
        if ($occupancyResult['current_occupancy'] == 0) {
            $updateStatusStmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $updateStatusStmt->bind_param("i", $roomId);
            $updateStatusStmt->execute();
            $updateStatusStmt->close();
        }
        
        $_SESSION['success_message'] = "Đã kết thúc phân công phòng thành công.";
    }
    
    header("Location: /LTW/views/admin/contracts/room_assignments.php?room_id=$roomId");
    exit();
}

// Xử lý xóa phân công
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $assignmentId = (int)$_GET['delete'];
    
    // Lấy thông tin phân công hiện tại
    $assignmentQuery = "SELECT * FROM room_assignments WHERE id = ?";
    $stmt = $conn->prepare($assignmentQuery);
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($assignment && $assignment['status'] == 'active') {
        // Cập nhật số người ở trong phòng nếu xóa phân công active
        $updateRoomStmt = $conn->prepare("UPDATE rooms SET current_occupancy = GREATEST(current_occupancy - 1, 0) WHERE id = ?");
        $updateRoomStmt->bind_param("i", $roomId);
        $updateRoomStmt->execute();
        $updateRoomStmt->close();
        
        // Nếu phòng không còn ai, đặt trạng thái thành available
        $checkOccupancyStmt = $conn->prepare("SELECT current_occupancy FROM rooms WHERE id = ?");
        $checkOccupancyStmt->bind_param("i", $roomId);
        $checkOccupancyStmt->execute();
        $occupancyResult = $checkOccupancyStmt->get_result()->fetch_assoc();
        $checkOccupancyStmt->close();
        
        if ($occupancyResult['current_occupancy'] == 0) {
            $updateStatusStmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $updateStatusStmt->bind_param("i", $roomId);
            $updateStatusStmt->execute();
            $updateStatusStmt->close();
        }
    }
    
    // Xóa phân công
    $deleteStmt = $conn->prepare("DELETE FROM room_assignments WHERE id = ?");
    $deleteStmt->bind_param("i", $assignmentId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    $_SESSION['success_message'] = "Đã xóa phân công phòng thành công.";
    header("Location: /LTW/views/admin/contracts/room_assignments.php?room_id=$roomId");
    exit();
}

// Lấy danh sách phân công phòng (tất cả trạng thái)
$assignmentsQuery = "SELECT ra.*, u.student_id, u.first_name, u.last_name, u.email, u.phone, u.gender,
                    CONCAT(a.first_name, ' ', a.last_name) as assigned_by_name
                   FROM room_assignments ra
                   JOIN users u ON ra.user_id = u.id
                   LEFT JOIN users a ON ra.assigned_by = a.id
                   WHERE ra.room_id = ?
                   ORDER BY ra.status DESC, ra.start_date DESC";

$stmt = $conn->prepare($assignmentsQuery);
$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();
?>

<!-- Tiêu đề trang -->
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Quản lý phân phòng: <?php echo $room['building_name'] . ' - ' . $room['room_number']; ?></h1>
        <a href="/LTW/views/admin/rooms/view.php?id=<?php echo $roomId; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại phòng
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Thông tin phòng -->
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
                    $statusClass = 'bg-info';
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
            <div class="row">
                <div class="col-md-3">
                    <p><strong>Tòa nhà:</strong> <?php echo $room['building_name']; ?></p>
                    <p><strong>Số phòng:</strong> <?php echo $room['room_number']; ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Tầng:</strong> <?php echo $room['floor']; ?></p>
                    <p><strong>Sức chứa:</strong> <?php echo $room['capacity']; ?> người</p>
                </div>
                <div class="col-md-3">
                    <p><strong>Số người hiện tại:</strong> <?php echo $room['current_occupancy']; ?>/<?php echo $room['capacity']; ?></p>
                    <p><strong>Giá thuê:</strong> <?php echo number_format($room['monthly_rent'], 0, ',', '.'); ?> đ/tháng</p>
                </div>                <div class="col-md-3 text-end">
                    <a href="/LTW/views/admin/rooms/assign_student.php?room_id=<?php echo $roomId; ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i> Thêm sinh viên vào phòng
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách phân công -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách phân công phòng</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>MSSV</th>
                            <th>Họ tên</th>
                            <th>Giới tính</th>
                            <th>Liên hệ</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Trạng thái</th>
                            <th>Người phân công</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Không có phân công phòng nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo $assignment['student_id']; ?></td>
                                    <td><?php echo $assignment['first_name'] . ' ' . $assignment['last_name']; ?></td>
                                    <td><?php echo ucfirst($assignment['gender']); ?></td>
                                    <td>
                                        <small><i class="fas fa-envelope me-1"></i> <?php echo $assignment['email']; ?></small><br>
                                        <small><i class="fas fa-phone me-1"></i> <?php echo $assignment['phone']; ?></small>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($assignment['start_date'])); ?></td>
                                    <td>
                                        <?php echo $assignment['end_date'] ? date('d/m/Y', strtotime($assignment['end_date'])) : 'Không xác định'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                        switch ($assignment['status']) {
                                            case 'active': echo 'success'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'completed': echo 'secondary'; break;
                                            case 'terminated': echo 'danger'; break;
                                            default: echo 'info';
                                        }
                                        ?>">
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $assignment['assigned_by_name']; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/LTW/views/admin/students/view.php?id=<?php echo $assignment['user_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($assignment['status'] == 'active' || $assignment['status'] == 'pending'): ?>
                                                <a href="?room_id=<?php echo $roomId; ?>&end=<?php echo $assignment['id']; ?>" 
                                                   class="btn btn-sm btn-warning" 
                                                   onclick="return confirm('Bạn có chắc chắn muốn kết thúc phân công này không?')">
                                                    <i class="fas fa-clock"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?room_id=<?php echo $roomId; ?>&delete=<?php echo $assignment['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa phân công này không?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
// At the end of the file, flush the buffer
ob_end_flush();
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php'; ?>
