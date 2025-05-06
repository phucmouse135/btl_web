<?php
// Bao gồm header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Kiểm tra xem room_id có được cung cấp không
if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
    echo displayError("Không tìm thấy ID phòng.");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
    exit();
}

$room_id = (int)$_GET['room_id'];
$currentUserId = $_SESSION['user_id'];
$message = '';
$error = '';

// Lấy thông tin phòng
$roomQuery = "SELECT * FROM rooms WHERE id = ?";
$roomStmt = $conn->prepare($roomQuery);
$roomStmt->bind_param("i", $room_id);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();

if ($roomResult->num_rows === 0) {
    echo displayError("Phòng không tồn tại.");
    require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
    exit();
}

$room = $roomResult->fetch_assoc();
$roomStmt->close();

// Xử lý hành động phân phòng
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Thêm sinh viên vào phòng
        if ($_POST['action'] == 'assign' && isset($_POST['student_id']) && !empty($_POST['student_id'])) {
            $student_id = (int)$_POST['student_id'];
            
            // Kiểm tra xem sinh viên đã có phòng chưa
            $checkQuery = "SELECT * FROM room_assignments WHERE user_id = ? AND status = 'active'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("i", $student_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "Sinh viên này đã được phân phòng khác.";
            } else {
                // Kiểm tra xem phòng còn chỗ không
                if ($room['current_occupancy'] >= $room['capacity']) {
                    $error = "Phòng đã đạt sức chứa tối đa.";
                } else {
                    // Tạo mã phân phòng
                    $year = date('Y');
                    $assignmentNumber = 'RA' . $year . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Lấy thông tin hợp đồng từ form
                    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
                    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('+1 year'));
                    $monthlyRent = isset($_POST['monthly_rent']) ? (float)$_POST['monthly_rent'] : $room['monthly_rent'];
                    $deposit = isset($_POST['deposit']) ? (float)$_POST['deposit'] : $room['monthly_rent'];
                    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
                    $generateContract = isset($_POST['generate_contract']) ? 1 : 0;
                    
                    // Thêm phân phòng mới với thông tin hợp đồng
                    $insertQuery = "INSERT INTO room_assignments (user_id, room_id, assignment_number, start_date, end_date, 
                                    monthly_rent, deposit, notes, has_contract, status, assigned_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
                    $insertStmt = $conn->prepare($insertQuery);
                    $insertStmt->bind_param("iisssddsii", $student_id, $room_id, $assignmentNumber, $startDate, $endDate, 
                                           $monthlyRent, $deposit, $notes, $generateContract, $currentUserId);
                    
                    if ($insertStmt->execute()) {
                        $assignmentId = $insertStmt->insert_id;
                        
                        // Cập nhật current_occupancy trong bảng rooms
                        $updateRoomQuery = "UPDATE rooms SET current_occupancy = current_occupancy + 1, status = IF(current_occupancy + 1 >= capacity, 'occupied', status) WHERE id = ?";
                        $updateRoomStmt = $conn->prepare($updateRoomQuery);
                        $updateRoomStmt->bind_param("i", $room_id);
                        $updateRoomStmt->execute();
                        $updateRoomStmt->close();
                        
                        $message = "Phân phòng thành công.";
                        
                        // Tạo hợp đồng nếu được chọn
                        if ($generateContract) {
                            // Chuyển hướng để tải xuống hợp đồng
                            header("Location: /LTW/views/admin/contracts/view_contract.php?id=" . $assignmentId);
                            exit();
                        }
                        
                        // Refresh dữ liệu phòng
                        $roomStmt = $conn->prepare($roomQuery);
                        $roomStmt->bind_param("i", $room_id);
                        $roomStmt->execute();
                        $roomResult = $roomStmt->get_result();
                        $room = $roomResult->fetch_assoc();
                        $roomStmt->close();
                    } else {
                        $error = "Lỗi khi phân phòng: " . $insertStmt->error;
                    }
                    
                    $insertStmt->close();
                }
            }
            
            $checkStmt->close();
        }
        
        // Hủy phân phòng
        if ($_POST['action'] == 'remove' && isset($_POST['assignment_id']) && !empty($_POST['assignment_id'])) {
            $assignment_id = (int)$_POST['assignment_id'];
            
            // Cập nhật trạng thái phân phòng thành 'completed'
            $updateQuery = "UPDATE room_assignments SET status = 'completed', updated_at = NOW() WHERE id = ? AND room_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ii", $assignment_id, $room_id);
            
            if ($updateStmt->execute()) {
                // Cập nhật current_occupancy trong bảng rooms
                $updateRoomQuery = "UPDATE rooms SET current_occupancy = current_occupancy - 1, 
                                    status = CASE 
                                        WHEN current_occupancy - 1 = 0 THEN 'available'
                                        ELSE status
                                    END 
                                    WHERE id = ?";
                $updateRoomStmt = $conn->prepare($updateRoomQuery);
                $updateRoomStmt->bind_param("i", $room_id);
                $updateRoomStmt->execute();
                $updateRoomStmt->close();
                
                $message = "Đã hủy phân phòng thành công.";
                
                // Refresh dữ liệu phòng
                $roomStmt = $conn->prepare($roomQuery);
                $roomStmt->bind_param("i", $room_id);
                $roomStmt->execute();
                $roomResult = $roomStmt->get_result();
                $room = $roomResult->fetch_assoc();
                $roomStmt->close();
            } else {
                $error = "Lỗi khi hủy phân phòng: " . $updateStmt->error;
            }
            
            $updateStmt->close();
        }
    }
}

// Lấy danh sách các phân phòng hiện tại cho phòng này
$assignmentsQuery = "SELECT ra.*, u.first_name, u.last_name, u.student_id, u.email, u.phone
                    FROM room_assignments ra
                    JOIN users u ON ra.user_id = u.id
                    WHERE ra.room_id = ? AND ra.status = 'active'
                    ORDER BY ra.created_at DESC";
$assignmentsStmt = $conn->prepare($assignmentsQuery);
$assignmentsStmt->bind_param("i", $room_id);
$assignmentsStmt->execute();
$assignmentsResult = $assignmentsStmt->get_result();
$currentAssignments = [];

while ($row = $assignmentsResult->fetch_assoc()) {
    $currentAssignments[] = $row;
}
$assignmentsStmt->close();

// Lấy danh sách sinh viên chưa được phân phòng
$availableStudentsQuery = "SELECT u.id, u.first_name, u.last_name, u.student_id, u.email, u.department, u.year_of_study
                          FROM users u
                          LEFT JOIN (
                              SELECT user_id
                              FROM room_assignments
                              WHERE status = 'active'
                          ) ra ON u.id = ra.user_id
                          WHERE u.role = 'student' AND u.status = 'active' AND ra.user_id IS NULL
                          ORDER BY u.first_name, u.last_name";
$availableStudentsStmt = $conn->prepare($availableStudentsQuery);
$availableStudentsStmt->execute();
$availableStudentsResult = $availableStudentsStmt->get_result();
$availableStudents = [];

while ($row = $availableStudentsResult->fetch_assoc()) {
    $availableStudents[] = $row;
}
$availableStudentsStmt->close();

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

// Trạng thái phòng
$statusClass = 'bg-success'; // Khả dụng
$statusText = 'Khả dụng';

if ($room['status'] == 'occupied') {
    $statusClass = 'bg-danger';
    $statusText = 'Đã sử dụng';
} else if ($room['status'] == 'maintenance') {
    $statusClass = 'bg-warning';
    $statusText = 'Bảo trì';
}
?>

<!-- Tiêu đề trang -->
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Phân phòng: <?php echo $room['building_name'] . ' - Phòng ' . $room['room_number']; ?></h1>
        <a href="/LTW/views/admin/rooms/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại danh sách phòng
        </a>
    </div>

    <?php if ($message): ?>
        <?php echo displaySuccess($message); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <?php echo displayError($error); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Thông tin phòng -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin phòng</h6>
                    <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Phòng <?php echo $room['room_number']; ?></h5>
                        <p class="text-muted"><?php echo $room['building_name']; ?></p>
                    </div>
                    <div class="mb-3">
                        <p><i class="fas fa-bed me-2"></i> <?php echo $roomType; ?></p>
                        <p><i class="fas fa-user me-2"></i> <?php echo $room['current_occupancy']; ?>/<?php echo $room['capacity']; ?> sức chứa</p>
                        <p><i class="fas fa-layer-group me-2"></i> Tầng <?php echo $room['floor']; ?></p>
                        <p><i class="fas fa-money-bill me-2"></i> <?php echo number_format($room['monthly_rent'], 0, ',', '.'); ?> đ/tháng</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Mô tả:</h6>
                        <p><?php echo $room['description'] ? $room['description'] : 'Không có mô tả'; ?></p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="/LTW/views/admin/rooms/edit.php?id=<?php echo $room['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i> Chỉnh sửa phòng
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danh sách sinh viên hiện tại -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sinh viên hiện tại (<?php echo count($currentAssignments); ?>/<?php echo $room['capacity']; ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($currentAssignments)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Chưa có sinh viên nào được phân vào phòng này.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>MSSV</th>
                                        <th>Họ và tên</th>
                                        <th>Email</th>
                                        <th>Điện thoại</th>
                                        <th>Ngày bắt đầu</th>
                                        <th>Ngày kết thúc</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentAssignments as $assignment): ?>
                                        <tr>
                                            <td><?php echo $assignment['student_id']; ?></td>
                                            <td>
                                                <a href="/LTW/views/admin/students/view.php?id=<?php echo $assignment['user_id']; ?>">
                                                    <?php echo $assignment['first_name'] . ' ' . $assignment['last_name']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo $assignment['email']; ?></td>
                                            <td><?php echo $assignment['phone']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($assignment['start_date'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($assignment['end_date'])); ?></td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn hủy phân phòng này?');">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-user-minus"></i> Hủy
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thêm sinh viên vào phòng -->
            <?php if ($room['current_occupancy'] < $room['capacity'] && $room['status'] != 'maintenance'): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Thêm sinh viên vào phòng</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assignmentForm">
                            <input type="hidden" name="action" value="assign">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="student_id" class="form-label">Chọn sinh viên</label>
                                    <select class="form-select" name="student_id" id="student_id" required>
                                        <option value="">-- Chọn sinh viên --</option>
                                        <?php foreach ($availableStudents as $student): ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo $student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']; ?> 
                                                (<?php echo $student['department']; ?>, Năm <?php echo $student['year_of_study']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="card border-left-info shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-info">Thông tin hợp đồng</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="start_date" class="form-label">Ngày bắt đầu</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="end_date" class="form-label">Ngày kết thúc</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="monthly_rent" class="form-label">Tiền phòng hàng tháng (đ)</label>
                                            <input type="number" class="form-control" id="monthly_rent" name="monthly_rent" value="<?php echo $room['monthly_rent']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="deposit" class="form-label">Tiền đặt cọc (đ)</label>
                                            <input type="number" class="form-control" id="deposit" name="deposit" value="<?php echo $room['monthly_rent']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="payment_method" class="form-label">Phương thức thanh toán</label>
                                            <select class="form-select" id="payment_method" name="payment_method">
                                                <option value="monthly">Hàng tháng</option>
                                                <option value="quarterly">Hàng quý (3 tháng)</option>
                                                <option value="biannual">Nửa năm</option>
                                                <option value="annual">Hàng năm</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="utility_fees" class="form-label">Phí tiện ích (đ/tháng)</label>
                                            <input type="number" class="form-control" id="utility_fees" name="utility_fees" value="150000">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="notes" class="form-label">Ghi chú</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="generate_contract" name="generate_contract" value="1" checked>
                                        <label class="form-check-label" for="generate_contract">
                                            Tạo hợp đồng sau khi phân phòng
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i> Phân phòng cho sinh viên
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <?php if ($room['status'] == 'maintenance'): ?>
                                Phòng này đang trong tình trạng bảo trì, không thể phân phòng.
                            <?php else: ?>
                                Phòng này đã đạt sức chứa tối đa (<?php echo $room['capacity']; ?> sinh viên), không thể thêm sinh viên.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lịch sử phân phòng -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Lịch sử phân phòng</h6>
                </div>
                <div class="card-body">
                    <!-- Lịch sử phân phòng sẽ hiển thị ở đây -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Tính năng lịch sử phân phòng đang được phát triển.
                    </div>
                </div>
            </div>

            <!-- Modal xem trước hợp đồng -->
            <div class="modal fade" id="contractPreviewModal" tabindex="-1" aria-labelledby="contractPreviewModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="contractPreviewModalLabel">Xem trước hợp đồng</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="contractPreview" class="p-3 border">
                                <div class="text-center mb-4">
                                    <h4>HỢP ĐỒNG THUÊ PHÒNG KÝ TÚC XÁ</h4>
                                    <p>Năm học: <span id="preview-academic-year"></span></p>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>Bên A (Đại diện KTX):</strong> <span id="preview-ktx-name">Ký túc xá sinh viên</span></p>
                                        <p><strong>Địa chỉ:</strong> <span id="preview-ktx-address">Địa chỉ ký túc xá</span></p>
                                        <p><strong>Đại diện:</strong> <span id="preview-ktx-representative">Ban quản lý KTX</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Bên B (Sinh viên):</strong> <span id="preview-student-name">____</span></p>
                                        <p><strong>MSSV:</strong> <span id="preview-student-id">____</span></p>
                                        <p><strong>Liên hệ:</strong> <span id="preview-student-contact">____</span></p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <p><strong>Thông tin phòng ở:</strong></p>
                                        <p>Tòa nhà: <span id="preview-building"><?php echo $room['building_name']; ?></span>, Phòng: <span id="preview-room"><?php echo $room['room_number']; ?></span>, Tầng: <span id="preview-floor"><?php echo $room['floor']; ?></span></p>
                                        <p>Loại phòng: <span id="preview-room-type"><?php echo $roomType; ?></span>, Sức chứa: <span id="preview-capacity"><?php echo $room['capacity']; ?></span> người</p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <p><strong>Thời hạn hợp đồng:</strong></p>
                                        <p>Từ ngày: <span id="preview-start-date">____</span> đến ngày: <span id="preview-end-date">____</span></p>
                                        <p>Tổng thời gian: <span id="preview-duration">____</span> tháng</p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <p><strong>Chi phí:</strong></p>
                                        <p>Tiền phòng: <span id="preview-monthly-rent">____</span> đồng/tháng</p>
                                        <p>Tiền đặt cọc: <span id="preview-deposit">____</span> đồng</p>
                                        <p>Phí tiện ích (điện, nước, internet): <span id="preview-utility-fees">____</span> đồng/tháng</p>
                                        <p>Phương thức thanh toán: <span id="preview-payment-method">____</span></p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <p><strong>Điều khoản và quy định:</strong></p>
                                        <ol id="preview-terms">
                                            <li>Sinh viên phải tuân thủ nội quy KTX và chấp hành các quy định về an ninh, trật tự, vệ sinh.</li>
                                            <li>Không được tự ý sửa chữa, cải tạo phòng ở.</li>
                                            <li>Không được cho người ngoài lưu trú qua đêm khi chưa được phép.</li>
                                            <li>Bảo quản tài sản, trang thiết bị trong phòng.</li>
                                            <li>Thanh toán tiền phòng đúng hạn.</li>
                                        </ol>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <p><strong>Ghi chú:</strong> <span id="preview-notes">____</span></p>
                                    </div>
                                </div>
                                
                                <div class="row mt-5">
                                    <div class="col-6 text-center">
                                        <p><strong>Đại diện bên A</strong></p>
                                        <p>(Ký và ghi rõ họ tên)</p>
                                    </div>
                                    <div class="col-6 text-center">
                                        <p><strong>Bên B</strong></p>
                                        <p>(Ký và ghi rõ họ tên)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                            <button type="button" class="btn btn-primary" id="confirmAssignment">Xác nhận và tạo hợp đồng</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- JavaScript cho trang phân phòng -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Tính toán năm học hiện tại
                const today = new Date();
                let academicYear;
                if (today.getMonth() > 7) { // Sau tháng 8 (0-based index)
                    academicYear = today.getFullYear() + "-" + (today.getFullYear() + 1);
                } else {
                    academicYear = (today.getFullYear() - 1) + "-" + today.getFullYear();
                }
                
                // Hiển thị năm học trong xem trước hợp đồng
                document.getElementById('preview-academic-year').textContent = academicYear;
                
                // Tham chiếu đến form
                const assignmentForm = document.getElementById('assignmentForm');
                
                // Lắng nghe sự kiện submit form
                if (assignmentForm) {
                    assignmentForm.addEventListener('submit', function(event) {
                        const generateContract = document.getElementById('generate_contract').checked;
                        const previewContract = document.getElementById('preview_contract').checked;
                        
                        // Nếu chọn xem trước hợp đồng, hiển thị modal thay vì submit form
                        if (generateContract && previewContract) {
                            event.preventDefault();
                            updateContractPreview();
                            const contractModal = new bootstrap.Modal(document.getElementById('contractPreviewModal'));
                            contractModal.show();
                        }
                    });
                }
                
                // Nút xác nhận trong modal xem trước hợp đồng
                const confirmButton = document.getElementById('confirmAssignment');
                if (confirmButton) {
                    confirmButton.addEventListener('click', function() {
                        // Submit form khi nhấn xác nhận
                        assignmentForm.submit();
                    });
                }
                
                // Cập nhật xem trước hợp đồng
                function updateContractPreview() {
                    // Lấy thông tin sinh viên được chọn
                    const studentSelect = document.getElementById('student_id');
                    const studentOption = studentSelect.options[studentSelect.selectedIndex];
                    const studentText = studentOption.text;
                    
                    // Phân tách thông tin sinh viên
                    const studentParts = studentText.split(' - ');
                    const studentId = studentParts[0];
                    const studentName = studentParts[1].split(' (')[0];
                    
                    // Cập nhật thông tin sinh viên
                    document.getElementById('preview-student-name').textContent = studentName;
                    document.getElementById('preview-student-id').textContent = studentId;
                    
                    // Cập nhật thông tin hợp đồng
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    const monthlyRent = document.getElementById('monthly_rent').value;
                    const deposit = document.getElementById('deposit').value;
                    const utilityFees = document.getElementById('utility_fees').value;
                    const paymentMethodSelect = document.getElementById('payment_method');
                    const paymentMethodText = paymentMethodSelect.options[paymentMethodSelect.selectedIndex].text;
                    const notes = document.getElementById('notes').value;
                    
                    // Tính số tháng
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    const months = (end.getFullYear() - start.getFullYear()) * 12 + end.getMonth() - start.getMonth();
                    
                    // Cập nhật các phần tử trong xem trước
                    document.getElementById('preview-start-date').textContent = formatDate(startDate);
                    document.getElementById('preview-end-date').textContent = formatDate(endDate);
                    document.getElementById('preview-duration').textContent = months;
                    document.getElementById('preview-monthly-rent').textContent = formatCurrency(monthlyRent);
                    document.getElementById('preview-deposit').textContent = formatCurrency(deposit);
                    document.getElementById('preview-utility-fees').textContent = formatCurrency(utilityFees);
                    document.getElementById('preview-payment-method').textContent = paymentMethodText;
                    document.getElementById('preview-notes').textContent = notes || 'Không có ghi chú';
                    
                    // Cập nhật thông tin liên hệ sinh viên nếu có
                    const studentContact = document.getElementById('student_contact') ? 
                        document.getElementById('student_contact').value : 'Chưa cập nhật';
                    document.getElementById('preview-student-contact').textContent = studentContact;
                }
                
                // Định dạng ngày tháng
                function formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('vi-VN');
                }
                
                // Định dạng tiền tệ
                function formatCurrency(amount) {
                    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
                }
                
                // Cập nhật điều khoản tùy chỉnh nếu có
                const customTerms = document.getElementById('custom_terms');
                if (customTerms) {
                    customTerms.addEventListener('change', function() {
                        const termsPreview = document.getElementById('preview-terms');
                        if (this.value.trim()) {
                            const terms = this.value.split('\n');
                            let termsHTML = '';
                            
                            terms.forEach(term => {
                                if (term.trim()) {
                                    termsHTML += `<li>${term}</li>`;
                                }
                            });
                            
                            termsPreview.innerHTML = termsHTML;
                        }
                    });
                }
            });
            </script>
        </div>
    </div>
</div>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>