<?php
// Start session and include required files without outputting HTML
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Khởi tạo biến
$error = '';
$success = '';
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : 0;
$filter_status = 'available'; // Mặc định chỉ hiển thị phòng trống

// Kiểm tra nếu sinh viên tồn tại
$student = null;
if ($student_id > 0) {
    $studentSql = "SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name, 
                   u.email, u.gender, u.department, u.year_of_study
                   FROM users u 
                   WHERE u.id = ? AND u.role = 'student'";
    $studentStmt = $conn->prepare($studentSql);
    if (!$studentStmt) {
        $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
    } else {
        $studentStmt->bind_param("i", $student_id);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();
        
        if ($studentResult->num_rows === 0) {
            $error = "Không tìm thấy sinh viên";
        } else {
            $student = $studentResult->fetch_assoc();
        }
        $studentStmt->close();
    }
}

// Kiểm tra nếu sinh viên đã có phòng
$currentAssignment = null;
if ($student_id > 0 && !$error) {
    $assignmentSql = "SELECT ra.*, r.room_number, r.floor, b.name as building_name, r.id as room_id
                     FROM room_assignments ra
                     JOIN rooms r ON ra.room_id = r.id
                     JOIN buildings b ON r.building_id = b.id
                     WHERE ra.student_id = ? AND ra.status = 'current'";
    $assignmentStmt = $conn->prepare($assignmentSql);
    if ($assignmentStmt) {
        $assignmentStmt->bind_param("i", $student_id);
        $assignmentStmt->execute();
        $assignmentResult = $assignmentStmt->get_result();
        
        if ($assignmentResult->num_rows > 0) {
            $currentAssignment = $assignmentResult->fetch_assoc();
        }
        $assignmentStmt->close();
    }
}

// Lấy danh sách tòa nhà cho bộ lọc
$buildings = [];
$buildingQuery = "SELECT id, name FROM buildings ORDER BY name ASC";
$buildingResult = $conn->query($buildingQuery);
while ($row = $buildingResult->fetch_assoc()) {
    $buildings[] = $row;
}

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_room']) && $student_id > 0) {
    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    $start_date = isset($_POST['start_date']) ? sanitizeInput($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : '';
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
    
    if ($room_id <= 0) {
        $error = "Vui lòng chọn phòng để xếp";
    } elseif (empty($start_date)) {
        $error = "Ngày bắt đầu là bắt buộc";
    } else {
        // Kiểm tra phòng có tồn tại và còn trống không
        $roomSql = "SELECT r.*, rt.capacity, rt.name as room_type, 
                   (SELECT COUNT(*) FROM room_assignments 
                    WHERE room_id = r.id AND status = 'current') as current_occupants
                   FROM rooms r
                   JOIN room_types rt ON r.room_type_id = rt.id
                   WHERE r.id = ? AND (r.status = 'available' OR r.status = 'occupied')";
        $roomStmt = $conn->prepare($roomSql);
        $roomStmt->bind_param("i", $room_id);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        
        if ($roomResult->num_rows === 0) {
            $error = "Không tìm thấy phòng đã chọn hoặc phòng không khả dụng";
        } else {
            $room = $roomResult->fetch_assoc();
            
            // Kiểm tra phòng đã đầy chưa
            if ($room['current_occupants'] >= $room['capacity']) {
                $error = "Phòng đã chọn đã đạt số lượng sinh viên tối đa";
            } else {
                // Kiểm tra giới hạn giới tính và sự phù hợp
                $gender_restricted = false;
                
                // Nếu có sinh viên hiện tại và phòng có nhiều hơn một giường
                if ($room['current_occupants'] > 0 && $room['capacity'] > 1) {
                    // Kiểm tra giới tính của sinh viên hiện tại
                    $genderSql = "SELECT DISTINCT u.gender FROM room_assignments ra
                                 JOIN users u ON ra.student_id = u.id
                                 WHERE ra.room_id = ? AND ra.status = 'current' AND u.role = 'student'";
                    $genderStmt = $conn->prepare($genderSql);
                    $genderStmt->bind_param("i", $room_id);
                    $genderStmt->execute();
                    $genderResult = $genderStmt->get_result();
                    
                    if ($genderResult->num_rows > 0) {
                        $occupantGender = $genderResult->fetch_assoc()['gender'];
                        if ($occupantGender != $student['gender']) {
                            $gender_restricted = true;
                        }
                    }
                    $genderStmt->close();
                }
                
                if ($gender_restricted) {
                    $error = "Phòng này đã có sinh viên có giới tính khác";
                } else {
                    // Bắt đầu giao dịch
                    $conn->begin_transaction();
                    
                    try {
                        // Nếu sinh viên đã có phòng, cập nhật thành 'previous'
                        if ($currentAssignment) {
                            $updateSql = "UPDATE room_assignments SET status = 'previous', end_date = CURRENT_DATE() WHERE id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->bind_param("i", $currentAssignment['id']);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                        
                        // Tạo phân công mới
                        $insertSql = "INSERT INTO room_assignments (student_id, room_id, start_date, end_date, status, assigned_by)
                                     VALUES (?, ?, ?, ?, 'current', ?)";
                        $insertStmt = $conn->prepare($insertSql);
                        if ($insertStmt === false) {
                            throw new Exception("Lỗi chuẩn bị truy vấn: " . $conn->error);
                        }
                        $insertStmt->bind_param("iissi", $student_id, $room_id, $start_date, $end_date, $_SESSION['user_id']);
                        $insertStmt->execute();
                        $insertStmt->close();
                        
                        // Cập nhật trạng thái phòng thành 'occupied' nếu đây là sinh viên đầu tiên
                        if ($room['current_occupants'] == 0) {
                            $roomUpdateSql = "UPDATE rooms SET status = 'occupied', current_occupancy = 1 WHERE id = ?";
                            $roomUpdateStmt = $conn->prepare($roomUpdateSql);
                            $roomUpdateStmt->bind_param("i", $room_id);
                            $roomUpdateStmt->execute();
                            $roomUpdateStmt->close();
                        } else {
                            // Chỉ tăng số lượng người ở
                            $roomUpdateSql = "UPDATE rooms SET current_occupancy = current_occupancy + 1 WHERE id = ?";
                            $roomUpdateStmt = $conn->prepare($roomUpdateSql);
                            $roomUpdateStmt->bind_param("i", $room_id);
                            $roomUpdateStmt->execute();
                            $roomUpdateStmt->close();
                        }
                        
                        // Hoàn thành giao dịch
                        $conn->commit();
                        
                        // Set success message in session to display after redirect
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => "Phân phòng thành công cho " . $student['full_name']
                        ];
                        
                        // Ghi nhật ký hoạt động
                        $roomNumber = $room['room_number'];
                        logActivity('room_assignment', "Đã phân phòng $roomNumber cho " . $student['full_name']);
                        
                        // Chuyển hướng đến trang xem sinh viên sau khi phân phòng thành công
                        header("Location: /LTW/views/admin/students/view.php?id=$student_id&success=room_assigned");
                        exit();
                        
                    } catch (Exception $e) {
                        // Hoàn tác giao dịch nếu có lỗi
                        $conn->rollback();
                        $error = "Lỗi khi phân phòng: " . $e->getMessage();
                    }
                }
            }
            $roomStmt->close();
        }
    }
}

// Lấy danh sách phòng khả dụng dựa trên bộ lọc
$rooms = [];
if ($student_id > 0 && !$error) {
    $query = "SELECT r.*, b.name as building_name, rt.name as room_type, rt.capacity,
             (SELECT COUNT(*) FROM room_assignments WHERE room_id = r.id AND status = 'current') as current_occupants
             FROM rooms r
             JOIN buildings b ON r.building_id = b.id
             JOIN room_types rt ON r.room_type_id = rt.id
             WHERE r.status != 'maintenance' AND r.current_occupancy < r.capacity";
    
    $params = []; // Array to hold all parameters for the prepared statement
    
    // Áp dụng bộ lọc tòa nhà nếu được chỉ định
    if ($building_id > 0) {
        $query .= " AND r.building_id = ?";
        $params[] = $building_id;
    }
    
    // Nếu sinh viên có giới tính, chỉ hiển thị phòng trống hoặc có sinh viên cùng giới tính
    if ($student && isset($student['gender']) && !empty($student['gender'])) {
        $query .= " AND (r.current_occupancy = 0 OR r.id IN (
                  SELECT DISTINCT ra.room_id FROM room_assignments ra
                  JOIN users u ON ra.student_id = u.id
                  WHERE ra.status = 'current' AND u.gender = ? AND u.role = 'student'
                  ))";
        $params[] = $student['gender'];
    }
    
    $query .= " ORDER BY b.name ASC, CAST(r.room_number AS UNSIGNED) ASC";
    
    // Sử dụng prepared statement để tránh lỗi SQL và tăng bảo mật
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Bind parameters dynamically based on the number of conditions
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // Assume all params are strings for simplicity
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rooms[] = $row;
            }
        } else {
            $error = "Lỗi truy vấn danh sách phòng: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
    }
}

// Now we can include the header to start HTML output
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Phân phòng cho sinh viên</h1>
    <div>
        <a href="/LTW/views/admin/students/view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại sinh viên
        </a>
    </div>
</div>

<?php if ($error): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<?php if ($success): ?>
    <?php echo displaySuccess($success); ?>
<?php endif; ?>

<?php if (!$student_id): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i> Vui lòng chọn một sinh viên để phân phòng.
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Chọn sinh viên</h6>
        </div>
        <div class="card-body">
            <p>Vui lòng truy cập <a href="/LTW/views/admin/students/list.php">danh sách sinh viên</a> và chọn một sinh viên để phân phòng.</p>
        </div>
    </div>
<?php elseif ($error == "Không tìm thấy sinh viên"): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i> Không tìm thấy sinh viên. Vui lòng chọn một sinh viên hợp lệ.
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-body">
            <p>Quay lại <a href="/LTW/views/admin/students/list.php">danh sách sinh viên</a> và chọn một sinh viên hợp lệ.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Thẻ thông tin sinh viên -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin sinh viên</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center">
                    <img class="img-fluid rounded-circle mb-3" style="max-width: 100px;" 
                         src="<?php echo !empty($student['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $student['profile_pic']) 
                            ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] 
                            : '/LTW/assets/images/user.png'; ?>" alt="Ảnh đại diện">
                </div>
                <div class="col-md-10">
                    <h5><?php echo $student['full_name']; ?></h5>
                    <p class="mb-1"><strong>Mã sinh viên:</strong> <?php echo $student['student_id']; ?></p>
                    <p class="mb-1"><strong>Giới tính:</strong> <?php echo ucfirst($student['gender']); ?></p>
                    <p class="mb-1"><strong>Khoa:</strong> <?php echo $student['department']; ?></p>
                    <p class="mb-1"><strong>Năm học:</strong> <?php echo $student['year_of_study']; ?></p>
                    <p class="mb-0"><strong>Trạng thái:</strong> 
                        <span class="badge <?php echo ($student['status'] == 'active') ? 'bg-success' : (($student['status'] == 'inactive') ? 'bg-danger' : 'bg-info'); ?>">
                            <?php echo ucfirst($student['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($currentAssignment): ?>
    <!-- Cảnh báo phân phòng hiện tại -->
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i> Sinh viên này hiện đang ở phòng 
        <strong><?php echo $currentAssignment['building_name'] . ' - Phòng ' . $currentAssignment['room_number']; ?></strong>.
        
        <div class="mt-2">
            <p class="mb-1">Phân phòng mới sẽ cập nhật phân phòng hiện tại thành trạng thái trước đó.</p>
            <div class="btn-group mt-2">
                <a href="/LTW/views/admin/rooms/view.php?id=<?php echo $currentAssignment['room_id']; ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-eye me-1"></i> Xem phòng hiện tại
                </a>
                <a href="/LTW/views/admin/rooms/change_assignment.php?student_id=<?php echo $student_id; ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-exchange-alt me-1"></i> Thay đổi phân phòng
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Bộ lọc và Lựa chọn phòng -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lựa chọn phòng</h6>
        </div>
        <div class="card-body">
            <!-- Bộ lọc tòa nhà -->
            <form method="GET" action="" class="mb-4">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                
                <div class="row">
                    <div class="col-md-4">
                        <label for="building_id" class="form-label">Lọc theo tòa nhà:</label>
                        <select class="form-select" name="building_id" id="building_id" onchange="this.form.submit()">
                            <option value="0">Tất cả các tòa nhà</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo $building_id == $b['id'] ? 'selected' : ''; ?>>
                                    <?php echo $b['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
            
            <?php if (count($rooms) == 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Không tìm thấy phòng phù hợp với tiêu chí.
                </div>
            <?php else: ?>
                <!-- Biểu mẫu phân phòng -->
                <form method="POST" action="" id="assignRoomForm">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Chọn</th>
                                    <th>Phòng</th>
                                    <th>Tòa nhà</th>
                                    <th>Loại</th>
                                    <th>Tầng</th>
                                    <th>Số người</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input room-select" type="radio" name="room_id" 
                                                       value="<?php echo $room['id']; ?>" id="room<?php echo $room['id']; ?>">
                                            </div>
                                        </td>
                                        <td><?php echo $room['room_number']; ?></td>
                                        <td><?php echo $room['building_name']; ?></td>
                                        <td><?php echo $room['room_type']; ?></td>
                                        <td><?php echo $room['floor']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $room['current_occupants'] == 0 ? 'bg-success' : 
                                                  ($room['current_occupants'] == $room['capacity'] ? 'bg-danger' : 'bg-warning'); ?>">
                                                <?php echo $room['current_occupants']; ?>/<?php echo $room['capacity']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/LTW/views/admin/rooms/view.php?id=<?php echo $room['id']; ?>" 
                                               class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-eye"></i> Chi tiết
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Chi tiết phân phòng</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Ngày bắt đầu *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">Ngày kết thúc</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo date('Y-m-d', strtotime('+6 months')); ?>">
                                    <small class="text-muted">Để trống nếu phân phòng vô thời hạn</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Ghi chú</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="window.history.back();">Hủy</button>
                                <button type="submit" class="btn btn-primary" name="assign_room" id="assignButton" disabled>
                                    <i class="fas fa-check-circle me-2"></i> Phân phòng
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Kích hoạt nút gửi khi một phòng được chọn
    const roomSelects = document.querySelectorAll('.room-select');
    const assignButton = document.getElementById('assignButton');
    
    roomSelects.forEach(function(radio) {
        radio.addEventListener('change', function() {
            assignButton.disabled = false;
        });
    });
    
    // Xác thực biểu mẫu
    const form = document.getElementById('assignRoomForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            const selectedRoom = document.querySelector('input[name="room_id"]:checked');
            const startDate = document.getElementById('start_date');
            
            if (!selectedRoom) {
                event.preventDefault();
                alert('Vui lòng chọn một phòng để phân công');
                return false;
            }
            
            if (!startDate.value) {
                event.preventDefault();
                alert('Vui lòng nhập ngày bắt đầu');
                startDate.focus();
                return false;
            }
        });
    }
});
</script>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>