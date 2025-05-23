<?php
// Bắt đầu output buffering
ob_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Start the session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Lấy danh sách các tòa nhà duy nhất từ bảng rooms
$buildings = [];
$buildingQuery = "SELECT DISTINCT building_name FROM rooms ORDER BY building_name ASC";
$buildingResult = $conn->query($buildingQuery);

if ($buildingResult) {
    while ($row = $buildingResult->fetch_assoc()) {
        $buildings[] = $row;
    }
}

// Xử lý khi form được gửi
$errors = [];
$success = false;
$roomData = [
    'building_name' => '',
    'room_number' => '',
    'floor' => '',
    'capacity' => '',
    'monthly_rent' => '',
    'description' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy và kiểm tra dữ liệu đầu vào
    $roomData = [
        'building_name' => sanitizeInput($_POST['building_name']),
        'room_number' => sanitizeInput($_POST['room_number']),
        'floor' => (int)$_POST['floor'],
        'capacity' => (int)$_POST['capacity'],
        'monthly_rent' => (float)$_POST['monthly_rent'],
        'description' => sanitizeInput($_POST['description'])
    ];
    
    // Xác thực dữ liệu
    if (empty($roomData['building_name'])) {
        $errors[] = "Tòa nhà không được để trống.";
    }
    
    if (empty($roomData['room_number'])) {
        $errors[] = "Số phòng không được để trống.";
    }
    
    if ($roomData['floor'] <= 0) {
        $errors[] = "Tầng phải là số dương.";
    }
    
    if ($roomData['capacity'] <= 0) {
        $errors[] = "Sức chứa phải là số dương.";
    }
    
    if ($roomData['monthly_rent'] <= 0) {
        $errors[] = "Giá thuê hàng tháng phải là số dương.";
    }
    
    // Kiểm tra xem phòng đã tồn tại chưa
    $stmt = $conn->prepare("SELECT id FROM rooms WHERE building_name = ? AND room_number = ?");
    $stmt->bind_param("ss", $roomData['building_name'], $roomData['room_number']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Phòng này đã tồn tại trong tòa nhà đã chỉ định.";
    }
    
    $stmt->close();
    
    // Nếu không có lỗi, thêm phòng mới vào cơ sở dữ liệu
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO rooms (building_name, room_number, floor, capacity, current_occupancy, monthly_rent, status, description) VALUES (?, ?, ?, ?, 0, ?, 'available', ?)");
        $stmt->bind_param("ssiids", $roomData['building_name'], $roomData['room_number'], $roomData['floor'], $roomData['capacity'], $roomData['monthly_rent'], $roomData['description']);
        
        if ($stmt->execute()) {
            $success = true;
            // Chuyển hướng đến trang danh sách phòng với thông báo thành công
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Phòng đã được thêm thành công.'
            ];
            header("Location: /LTW/views/admin/rooms/list.php?success=1");
            exit();
        } else {
            $errors[] = "Lỗi khi thêm phòng: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Now that we've processed all redirects, we can include the header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Thêm phòng mới</h1>
    <a href="/LTW/views/admin/rooms/list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Quay lại danh sách
    </a>
</div>

<!-- Hiển thị lỗi nếu có -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Form thêm phòng mới -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Thông tin phòng</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="building_name" class="form-label">Tòa nhà <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="building_name" name="building_name" value="<?php echo htmlspecialchars($roomData['building_name']); ?>" required list="building_list">
                    <datalist id="building_list">
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo htmlspecialchars($building['building_name']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="form-text text-muted">Nhập tên tòa nhà (có thể chọn từ danh sách hiện có hoặc nhập mới)</small>
                </div>
                <div class="col-md-6">
                    <label for="room_number" class="form-label">Số phòng <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="room_number" name="room_number" value="<?php echo htmlspecialchars($roomData['room_number']); ?>" required>
                    <small class="form-text text-muted">Nhập số phòng (ví dụ: 101, 202, A101)</small>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="floor" class="form-label">Tầng <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="floor" name="floor" value="<?php echo htmlspecialchars($roomData['floor']); ?>" min="1" required>
                </div>
                <div class="col-md-6">
                    <label for="capacity" class="form-label">Sức chứa <span class="text-danger">*</span></label>
                    <select class="form-select" id="capacity" name="capacity" required>
                        <option value="">-- Chọn sức chứa --</option>
                        <option value="1" <?php if ($roomData['capacity'] == 1) echo 'selected'; ?>>1 người (Phòng đơn)</option>
                        <option value="2" <?php if ($roomData['capacity'] == 2) echo 'selected'; ?>>2 người (Phòng đôi)</option>
                        <option value="3" <?php if ($roomData['capacity'] == 3) echo 'selected'; ?>>3 người (Phòng ba)</option>
                        <option value="4" <?php if ($roomData['capacity'] == 4) echo 'selected'; ?>>4 người (Phòng bốn)</option>
                        <option value="6" <?php if ($roomData['capacity'] == 6) echo 'selected'; ?>>6 người (Phòng ở ghép)</option>
                        <option value="8" <?php if ($roomData['capacity'] == 8) echo 'selected'; ?>>8 người (Phòng ký túc xá)</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="monthly_rent" class="form-label">Giá thuê hàng tháng (VNĐ) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="monthly_rent" name="monthly_rent" value="<?php echo htmlspecialchars($roomData['monthly_rent']); ?>" min="0" step="10000" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Mô tả</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($roomData['description']); ?></textarea>
                <small class="form-text text-muted">Nhập mô tả về phòng, bao gồm các tiện nghi và tính năng đặc biệt</small>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="reset" class="btn btn-secondary me-md-2">Xóa form</button>
                <button type="submit" class="btn btn-primary">Thêm phòng</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tùy chọn: Thêm bất kỳ JavaScript cụ thể cho form phòng ở đây
});
</script>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
// Kết thúc và xóa bộ đệm
ob_end_flush();
?>