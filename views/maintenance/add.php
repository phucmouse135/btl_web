<?php
// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu đăng nhập
requireLogin();

// Khởi tạo biến
$message = '';
$error = '';
$formData = [
    'room_id' => '',
    'issue_type' => '',
    'priority' => 'medium', 
    'description' => '',
    'status' => 'pending',
    'assigned_to' => null
];

// Lấy phòng của người dùng nếu họ là sinh viên
$userRoomId = 0;
if (hasRole('student')) {
    $stmt = $conn->prepare("
        SELECT ra.room_id, r.room_number, r.building_name
        FROM room_assignments ra 
        JOIN rooms r ON ra.room_id = r.id
        WHERE ra.user_id = ? AND ra.status = 'active'
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $roomData = $result->fetch_assoc();
            $userRoomId = $roomData['room_id'];
            $formData['room_id'] = $userRoomId;
        }
        $stmt->close();
    } else {
        $error = "Lỗi khi chuẩn bị truy vấn: " . $conn->error;
    }
}

// Lấy tất cả phòng cho nhân viên/quản trị viên
$rooms = [];
if (hasRole('admin') || hasRole('staff')) {
    $stmt = $conn->prepare("
        SELECT id, room_number, building_name, floor, status
        FROM rooms
        ORDER BY building_name ASC, floor ASC, room_number ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
        $stmt->close();
    } else {
        $error = "Lỗi khi chuẩn bị truy vấn danh sách phòng: " . $conn->error;
    }
}

// Lấy danh sách nhân viên
$staffMembers = [];
if (hasRole('admin')) {
    $stmt = $conn->prepare("
        SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
        FROM users u
        WHERE u.status = 'active' AND u.role IN ('staff', 'admin')
        ORDER BY u.last_name ASC, u.first_name ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $staffMembers[] = $row;
        }
        $stmt->close();
    } else {
        $error = "Lỗi khi chuẩn bị truy vấn danh sách nhân viên: " . $conn->error;
    }
}

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu biểu mẫu cơ bản
    $formData = [
        'room_id' => isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0,
        'issue_type' => sanitizeInput($_POST['issue_type'] ?? ''),
        'priority' => sanitizeInput($_POST['priority'] ?? 'medium'),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'status' => 'pending', // Default status
        'assigned_to' => null   // Default assigned_to
    ];
    
    // Thêm dữ liệu dành riêng cho admin nếu có
    if (hasRole('admin') && isset($_POST['status'])) {
        $formData['status'] = sanitizeInput($_POST['status']);
    }

    if (hasRole('admin') && isset($_POST['assigned_to']) && !empty($_POST['assigned_to'])) {
        $formData['assigned_to'] = intval($_POST['assigned_to']);
    }
    
    // Xác thực dữ liệu biểu mẫu
    $validationErrors = [];
    
    if (empty($formData['room_id'])) {
        $validationErrors[] = 'Phòng là bắt buộc';
    }
    
    if (empty($formData['issue_type'])) {
        $validationErrors[] = 'Loại sự cố là bắt buộc';
    }
    
    if (empty($formData['priority'])) {
        $validationErrors[] = 'Mức độ ưu tiên là bắt buộc';
    } elseif (!in_array($formData['priority'], ['low', 'medium', 'high', 'emergency'])) {
        $validationErrors[] = 'Giá trị mức độ ưu tiên không hợp lệ';
    }
    
    if (empty($formData['description'])) {
        $validationErrors[] = 'Mô tả là bắt buộc';
    } elseif (strlen($formData['description']) < 10) {
        $validationErrors[] = 'Mô tả nên có ít nhất 10 ký tự';
    }
    
    // Xử lý tải lên ảnh
    $photoNames = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/maintenance/';
        
        // Đảm bảo thư mục tải lên tồn tại
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Xử lý từng tệp đã tải lên
        for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
            if ($_FILES['photos']['error'][$i] == 0) {
                $tempFile = $_FILES['photos']['tmp_name'][$i];
                $fileType = $_FILES['photos']['type'][$i];
                
                if (in_array($fileType, $allowedTypes)) {
                    $fileName = 'maintenance_' . uniqid() . '_' . time() . '_' . $_FILES['photos']['name'][$i];
                    $targetFile = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tempFile, $targetFile)) {
                        $photoNames[] = $fileName;
                    } else {
                        $validationErrors[] = 'Không thể tải lên ảnh: ' . $_FILES['photos']['name'][$i];
                    }
                } else {
                    $validationErrors[] = 'Loại tệp không hợp lệ: ' . $_FILES['photos']['name'][$i];
                }
            } elseif ($_FILES['photos']['error'][$i] != 4) { // 4 có nghĩa là không có tệp nào được tải lên, điều này không sao
                $validationErrors[] = 'Lỗi tải lên tệp: ' . $_FILES['photos']['name'][$i];
            }
        }
    }
    
    // Nếu không có lỗi xác thực, chèn yêu cầu bảo trì
    if (empty($validationErrors)) {
        $conn->begin_transaction();
        try {
            // Tạo yêu cầu bảo trì - Cập nhật theo schema mới
            $sql = "
                INSERT INTO maintenance_requests (
                    room_id, issue_type, description, request_date, 
                    priority, status, reported_by, assigned_to
                ) VALUES (?, ?, ?, CURRENT_DATE(), ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("issssii", 
                    $formData['room_id'], 
                    $formData['issue_type'], 
                    $formData['description'],
                    $formData['priority'],
                    $formData['status'],
                    $_SESSION['user_id'],
                    $formData['assigned_to']
                );
                
                $stmt->execute();
                $requestId = $stmt->insert_id;
                $stmt->close();
                
                // Note: maintenance_photos table is missing in current schema
                // Save photo information to a temporary file until we have the correct table
                if (!empty($photoNames)) {
                    $photoInfo = [
                        'request_id' => $requestId,
                        'photos' => $photoNames,
                        'uploaded_by' => $_SESSION['user_id'],
                        'upload_date' => date('Y-m-d H:i:s')
                    ];
                    
                    // Save photo info to temp file
                    $infoFile = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/maintenance/photo_info_' . $requestId . '.json';
                    file_put_contents($infoFile, json_encode($photoInfo));
                }
                
                $conn->commit();
                
                // Thông báo thành công
                $message = "Yêu cầu bảo trì đã được gửi thành công! Mã yêu cầu: #" . sprintf('%06d', $requestId);
                
                // Đặt lại dữ liệu biểu mẫu
                $formData = [
                    'room_id' => hasRole('student') ? $userRoomId : '',
                    'issue_type' => '',
                    'priority' => 'medium',
                    'description' => '',
                ];
            } else {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $conn->error);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $validationErrors);
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Gửi Yêu Cầu Bảo Trì</h1>
    <div>
        <a href="/LTW/views/maintenance/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại Danh sách Yêu cầu
        </a>
    </div>
</div>

<?php if ($message): ?>
    <?php echo displaySuccess($message); ?>
<?php endif; ?>

<?php if ($error): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<!-- Biểu mẫu Yêu Cầu Bảo Trì -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Chi tiết Yêu cầu</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row">
                <!-- Thông tin Phòng -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="room_id" class="form-label">Phòng *</label>
                        <?php if (hasRole('student') && $userRoomId > 0): ?>
                            <input type="hidden" name="room_id" value="<?php echo $userRoomId; ?>">
                            <input type="text" class="form-control" value="<?php echo $roomData['building_name'] . ' - Phòng ' . $roomData['room_number']; ?>" readonly>
                            <small class="text-muted">Đây là phòng được phân công cho bạn</small>
                        <?php elseif (hasRole('admin') || hasRole('staff')): ?>
                            <select class="form-select" id="room_id" name="room_id" required>
                                <option value="" disabled <?php echo empty($formData['room_id']) ? 'selected' : ''; ?>>Chọn Phòng</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" <?php echo $formData['room_id'] == $room['id'] ? 'selected' : ''; ?>>
                                        <?php echo $room['building_name'] . ' - Phòng ' . $room['room_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> Bạn chưa được phân công phòng. Vui lòng liên hệ với ban quản lý ký túc xá.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="issue_type" class="form-label">Loại Sự cố *</label>
                        <select class="form-select" id="issue_type" name="issue_type" required>
                            <option value="" disabled <?php echo empty($formData['issue_type']) ? 'selected' : ''; ?>>Chọn Loại Sự cố</option>
                            <option value="Plumbing" <?php echo $formData['issue_type'] == 'Plumbing' ? 'selected' : ''; ?>>Nước/Đường ống</option>
                            <option value="Electrical" <?php echo $formData['issue_type'] == 'Electrical' ? 'selected' : ''; ?>>Điện</option>
                            <option value="Furniture" <?php echo $formData['issue_type'] == 'Furniture' ? 'selected' : ''; ?>>Đồ nội thất</option>
                            <option value="Appliance" <?php echo $formData['issue_type'] == 'Appliance' ? 'selected' : ''; ?>>Thiết bị</option>
                            <option value="Structural" <?php echo $formData['issue_type'] == 'Structural' ? 'selected' : ''; ?>>Cấu trúc (Tường/Trần/Sàn)</option>
                            <option value="Lock/Key" <?php echo $formData['issue_type'] == 'Lock/Key' ? 'selected' : ''; ?>>Khóa/Chìa khóa</option>
                            <option value="HVAC" <?php echo $formData['issue_type'] == 'HVAC' ? 'selected' : ''; ?>>Sưởi/Làm mát</option>
                            <option value="Pest Control" <?php echo $formData['issue_type'] == 'Pest Control' ? 'selected' : ''; ?>>Kiểm soát côn trùng</option>
                            <option value="Internet" <?php echo $formData['issue_type'] == 'Internet' ? 'selected' : ''; ?>>Internet/Mạng</option>
                            <option value="Other" <?php echo $formData['issue_type'] == 'Other' ? 'selected' : ''; ?>>Khác</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="priority" class="form-label">Mức độ ưu tiên *</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="low" <?php echo $formData['priority'] == 'low' ? 'selected' : ''; ?>>Thấp - Có thể đợi, không khẩn cấp</option>
                            <option value="medium" <?php echo $formData['priority'] == 'medium' ? 'selected' : ''; ?>>Trung bình - Ưu tiên tiêu chuẩn</option>
                            <option value="high" <?php echo $formData['priority'] == 'high' ? 'selected' : ''; ?>>Cao - Cần chú ý nhanh chóng</option>
                            <option value="emergency" <?php echo $formData['priority'] == 'emergency' ? 'selected' : ''; ?>>Khẩn cấp - Rủi ro an toàn, cần xử lý ngay lập tức</option>
                        </select>
                    </div>
                    
                    <?php if (hasRole('admin')): ?>
                    <!-- Các trường chỉ dành cho Admin -->
                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending" <?php echo $formData['status'] == 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                            <option value="in_progress" <?php echo $formData['status'] == 'in_progress' ? 'selected' : ''; ?>>Đang xử lý</option>
                            <option value="completed" <?php echo $formData['status'] == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                            <option value="rejected" <?php echo $formData['status'] == 'rejected' ? 'selected' : ''; ?>>Từ chối</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Phân công cho</label>
                        <select class="form-select" id="assigned_to" name="assigned_to">
                            <option value="" <?php echo empty($formData['assigned_to']) ? 'selected' : ''; ?>>-- Chọn Nhân viên --</option>
                            <?php foreach ($staffMembers as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $formData['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo $staff['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Mô tả và Ảnh -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả vấn đề *</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo $formData['description']; ?></textarea>
                        <div class="form-text">Vui lòng mô tả chi tiết vấn đề bạn đang gặp phải.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="photos" class="form-label">Tải lên ảnh (tùy chọn)</label>
                        <input type="file" class="form-control" id="photos" name="photos[]" multiple>
                        <div class="form-text">Tải lên ảnh của vấn đề để giúp đội bảo trì hiểu rõ hơn. Bạn có thể chọn nhiều ảnh.</div>
                        <div class="form-text text-warning">Lưu ý: Tính năng lưu trữ ảnh có thể đang trong quá trình nâng cấp. Ảnh sẽ được lưu tạm thời.</div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i> Gửi Yêu Cầu Bảo Trì
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>