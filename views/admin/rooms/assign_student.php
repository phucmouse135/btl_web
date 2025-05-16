<?php
// Bật hiển thị lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bắt đầu output buffering
ob_start();

// Bao gồm các file cần thiết
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
if (!isset($conn) || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn->connect_error ?? "Biến \$conn không tồn tại"));
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Khởi tạo biến
$error = '';
$success = '';
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$selected_student = null;

if ($roomId <= 0) {
    header("Location: /LTW/views/admin/rooms/list.php?error=invalid_id");
    exit();
}

// Lấy thông tin phòng
$roomSql = "SELECT r.*, 
           (SELECT COUNT(*) FROM room_assignments WHERE room_id = r.id AND status = 'active') as current_occupants
           FROM rooms r 
           WHERE r.id = ?";
$roomStmt = $conn->prepare($roomSql);
$roomStmt->bind_param("i", $roomId);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();

if ($roomResult->num_rows === 0) {
    $roomStmt->close();
    header("Location: /LTW/views/admin/rooms/list.php?error=not_found");
    exit();
}

$room = $roomResult->fetch_assoc();
$roomStmt->close();

// Kiểm tra nếu phòng đã đầy
if ($room['current_occupants'] >= $room['capacity']) {
    $error = "Phòng này đã đầy. Không thể thêm sinh viên mới.";
}

// Nếu sinh viên đã được chọn, lấy thông tin sinh viên đó
if ($student_id > 0) {
    $studentSql = "SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM users u 
                  WHERE u.id = ? AND u.role = 'student'";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->bind_param("i", $student_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    
    if ($studentResult->num_rows > 0) {
        $selected_student = $studentResult->fetch_assoc();        // Kiểm tra xem sinh viên này đã có phòng chưa
        $checkAssignmentSql = "SELECT ra.*, r.room_number, r.building_name
                              FROM room_assignments ra 
                              JOIN rooms r ON ra.room_id = r.id
                              WHERE ra.user_id = ? AND ra.status = 'active'";
        $checkStmt = $conn->prepare($checkAssignmentSql);
        if ($checkStmt === false) {
            $error = "Lỗi SQL khi kiểm tra phòng hiện tại: " . $conn->error;
        } else {
            $checkStmt->bind_param("i", $student_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $currentRoom = $checkResult->fetch_assoc();            $error = "Sinh viên này hiện đang ở phòng {$currentRoom['building_name']} - {$currentRoom['room_number']}. Vui lòng kết thúc phân công hiện tại trước khi thêm mới.";
        }
        $checkStmt->close();
        }
        
        // Kiểm tra sự phù hợp về giới tính nếu phòng đã có người
        if ($room['current_occupants'] > 0 && empty($error)) {
            $genderCheckSql = "SELECT u.gender 
                              FROM room_assignments ra
                              JOIN users u ON ra.user_id = u.id
                              WHERE ra.room_id = ? AND ra.status = 'active'
                              LIMIT 1";
            $genderStmt = $conn->prepare($genderCheckSql);
            $genderStmt->bind_param("i", $roomId);
            $genderStmt->execute();
            $genderResult = $genderStmt->get_result();
            
            if ($genderResult->num_rows > 0) {
                $roomGender = $genderResult->fetch_assoc()['gender'];
                if ($roomGender != $selected_student['gender']) {
                    $error = "Phòng này đã có sinh viên " . ($roomGender == 'male' ? 'nam' : 'nữ') . ". Không thể thêm sinh viên có giới tính khác.";
                }
            }
            $genderStmt->close();
        }
    } else {
        $error = "Không tìm thấy sinh viên với ID đã chọn.";
    }
    $studentStmt->close();
}

// Xử lý form phân phòng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_room']) && $student_id > 0 && empty($error)) {    $start_date = isset($_POST['start_date']) ? sanitizeInput($_POST['start_date']) : date('Y-m-d');
    $end_date = isset($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
    $monthly_rent = isset($_POST['monthly_rent']) ? (float)$_POST['monthly_rent'] : $room['monthly_rent'];
    $status = 'active';
    // Tạo mã phân công duy nhất
    $assignment_number = 'RA-' . time() . '-' . rand(1000, 9999);
      try {
        // Bắt đầu transaction
        $conn->begin_transaction();
          // Thêm phân công mới
        $insertSql = "INSERT INTO room_assignments (user_id, room_id, assignment_number, start_date, end_date, monthly_rent, status, assigned_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt === false) {
            throw new Exception("Lỗi SQL khi chuẩn bị câu lệnh: " . $conn->error);
        }
        $insertStmt->bind_param("iisssdsi", $student_id, $roomId, $assignment_number, $start_date, $end_date, $monthly_rent, $status, $_SESSION['user_id']);
        $insertStmt->execute();
        $insertStmt->close();
          // Cập nhật số người ở và trạng thái phòng
        $updateSql = "UPDATE rooms SET 
                    current_occupancy = current_occupancy + 1,
                    status = CASE WHEN status = 'available' THEN 'occupied' ELSE status END
                    WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt === false) {
            throw new Exception("Lỗi SQL khi cập nhật phòng: " . $conn->error);
        }
        $updateStmt->bind_param("i", $roomId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Hoàn thành transaction
        $conn->commit();
        
        // Ghi log hoạt động
        $student_name = $selected_student['first_name'] . ' ' . $selected_student['last_name'];
        $room_info = $room['building_name'] . ' - ' . $room['room_number'];
        logActivity('room_assignment', "Đã phân phòng $room_info cho sinh viên $student_name");
        
        // Thông báo thành công và chuyển hướng
        $_SESSION['success_message'] = "Đã thêm sinh viên vào phòng thành công!";
        header("Location: /LTW/views/admin/contracts/room_assignments.php?room_id=$roomId");
        exit();
        
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollback();
        $error = "Đã xảy ra lỗi: " . $e->getMessage();
    }
}

// Tìm kiếm sinh viên
$students = [];
if (!empty($search_term)) {
    $searchSql = "SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
                 FROM users u
                 WHERE u.role = 'student' AND (
                     u.student_id LIKE ? OR 
                     CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
                     u.email LIKE ?
                 )
                 LIMIT 50";
    $searchParam = "%" . $search_term . "%";
    
    $searchStmt = $conn->prepare($searchSql);
    $searchStmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $searchStmt->execute();
    $searchResult = $searchStmt->get_result();
    
    while ($row = $searchResult->fetch_assoc()) {        // Khởi tạo giá trị mặc định
        $row['has_room'] = false;
        $row['room_info'] = '';
        
        try {
            // Kiểm tra xem sinh viên đã có phòng chưa
            $hasRoomSql = "SELECT r.building_name, r.room_number FROM room_assignments ra
                           JOIN rooms r ON ra.room_id = r.id
                           WHERE ra.user_id = ? AND ra.status = 'active'";
            
            $hasRoomStmt = $conn->prepare($hasRoomSql);
            if ($hasRoomStmt === false) {
                throw new Exception("Lỗi SQL prepare: " . $conn->error);
            }
            
            $hasRoomStmt->bind_param("i", $row['id']);
            $hasRoomStmt->execute();
            $hasRoomResult = $hasRoomStmt->get_result();
              if ($hasRoomResult->num_rows > 0) {
                $roomInfo = $hasRoomResult->fetch_assoc();
                $row['has_room'] = true;
                $row['room_info'] = $roomInfo['building_name'] . ' - ' . $roomInfo['room_number'];
            }
            $hasRoomStmt->close();
        } catch (Exception $e) {
            // Ghi log lỗi hoặc xử lý theo ý muốn
            // echo '<div class="alert alert-danger">Lỗi: ' . $e->getMessage() . '</div>';
        }
        
        $students[] = $row;
    }
    $searchStmt->close();
}
?>

<!-- Tiêu đề trang -->
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Thêm sinh viên vào phòng: <?php echo $room['building_name'] . ' - ' . $room['room_number']; ?></h1>
        <a href="/LTW/views/admin/contracts/room_assignments.php?room_id=<?php echo $roomId; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại danh sách phân phòng
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Thông tin phòng -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin phòng</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p><strong>Tòa nhà:</strong> <?php echo $room['building_name']; ?></p>
                    <p><strong>Số phòng:</strong> <?php echo $room['room_number']; ?></p>
                    <p><strong>Tầng:</strong> <?php echo $room['floor']; ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Giá thuê:</strong> <?php echo number_format($room['monthly_rent'], 0, ',', '.'); ?> đ/tháng</p>
                    <p><strong>Sức chứa:</strong> <?php echo $room['capacity']; ?> người</p>
                    <p>
                        <strong>Số người hiện tại:</strong> 
                        <span class="badge <?php echo ($room['current_occupants'] >= $room['capacity']) ? 'bg-danger' : 'bg-success'; ?>">
                            <?php echo $room['current_occupants']; ?>/<?php echo $room['capacity']; ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6">
                    <p><strong>Mô tả:</strong> <?php echo nl2br($room['description'] ?? 'Không có mô tả'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($room['current_occupants'] < $room['capacity']): ?>
        <?php if ($selected_student): ?>
            <!-- Biểu mẫu phân phòng cho sinh viên đã chọn -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Phân phòng cho sinh viên</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <?php 
                            $profile_pic = !empty($selected_student['profile_pic']) ? '/LTW/uploads/profile_pics/' . $selected_student['profile_pic'] : '/LTW/assets/images/user.png';
                            ?>
                            <img src="<?php echo $profile_pic; ?>" class="img-fluid rounded-circle mb-3" style="max-width: 100px;">
                        </div>
                        <div class="col-md-9">
                            <h4><?php echo $selected_student['full_name']; ?></h4>
                            <p><strong>Mã sinh viên:</strong> <?php echo $selected_student['student_id']; ?></p>
                            <p><strong>Email:</strong> <?php echo $selected_student['email']; ?></p>
                            <p><strong>Điện thoại:</strong> <?php echo $selected_student['phone']; ?></p>
                            <p>
                                <strong>Giới tính:</strong> 
                                <?php echo $selected_student['gender'] == 'male' ? 'Nam' : 'Nữ'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (empty($error)): ?>
                        <form method="post" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">Ngày kết thúc</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+6 months')); ?>">
                                    <small class="text-muted">Để trống nếu không xác định thời gian kết thúc</small>
                                </div>
                            </div>                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="monthly_rent" class="form-label">Giá thuê hàng tháng</label>
                                    <input type="number" class="form-control" id="monthly_rent" name="monthly_rent" value="<?php echo $room['monthly_rent']; ?>">
                                    <small class="text-muted">Có thể thay đổi giá cho sinh viên cụ thể (ưu đãi, học bổng...)</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <a href="?room_id=<?php echo $roomId; ?>" class="btn btn-secondary me-2">Hủy</a>
                                <button type="submit" name="assign_room" class="btn btn-primary">
                                    <i class="fas fa-check-circle me-2"></i> Phân phòng cho sinh viên
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Tìm kiếm sinh viên -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm sinh viên</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="mb-4">
                        <input type="hidden" name="room_id" value="<?php echo $roomId; ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Tìm theo mã SV, họ tên hoặc email" value="<?php echo $search_term; ?>" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-1"></i> Tìm kiếm
                            </button>
                        </div>
                        <small class="text-muted">Nhập ít nhất 3 ký tự để tìm kiếm</small>
                    </form>
                    
                    <?php if (!empty($search_term)): ?>
                        <?php if (empty($students)): ?>
                            <div class="alert alert-info">
                                Không tìm thấy sinh viên nào phù hợp với từ khóa "<?php echo $search_term; ?>".
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mã SV</th>
                                            <th>Họ tên</th>
                                            <th>Email</th>
                                            <th>Giới tính</th>
                                            <th>Trạng thái</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['full_name']; ?></td>
                                            <td><?php echo $student['email']; ?></td>
                                            <td><?php echo $student['gender'] == 'male' ? 'Nam' : 'Nữ'; ?></td>
                                            <td>
                                                <?php if ($student['has_room']): ?>
                                                    <span class="badge bg-warning">Đang ở phòng: <?php echo $student['room_info']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Chưa phân phòng</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$student['has_room']): ?>
                                                    <a href="?room_id=<?php echo $roomId; ?>&student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-plus-circle me-1"></i> Chọn
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled>Đã có phòng</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i> Phòng này đã đạt số lượng sinh viên tối đa (<?php echo $room['capacity']; ?>). Không thể thêm sinh viên mới.
        </div>    <?php endif; ?>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
// Kết thúc và xóa bộ đệm
ob_end_flush();
?>
