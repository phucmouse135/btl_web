<?php
// Bao gồm các tệp cần thiết
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Kiểm tra người dùng đã đăng nhập và có quyền admin hoặc staff
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    header('Location: /LTW/index.php');
    exit;
}

// Khởi tạo các biến
$error = '';
$success = '';
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$student = null;

// Lấy thông tin sinh viên
if ($student_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name, u.email, r.room_number
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN room_assignments ra ON s.id = ra.student_id AND ra.status = 'active'
        LEFT JOIN rooms r ON ra.room_id = r.id
        WHERE s.id = ?
    ");
    
    if (!$stmt) {
        $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
    } else {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Không tìm thấy sinh viên";
        } else {
            $student = $result->fetch_assoc();
        }
        
        $stmt->close();
    }
}

// Tạo bảng issues nếu chưa tồn tại
$createTableSQL = "
    CREATE TABLE IF NOT EXISTS issues (
        id INT(11) NOT NULL AUTO_INCREMENT,
        student_id INT(11) NOT NULL,
        issue_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        room_number VARCHAR(50) DEFAULT NULL,
        status ENUM('pending', 'in_progress', 'resolved', 'rejected') NOT NULL DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
        reported_by INT(11) NOT NULL,
        assigned_to INT(11) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        resolved_at DATETIME DEFAULT NULL,
        resolution_notes TEXT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY student_id (student_id),
        KEY status (status),
        KEY priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($createTableSQL) !== TRUE) {
    $error = "Lỗi khi tạo bảng issues: " . $conn->error;
}

// Xử lý thêm mới vấn đề
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_issue') {
    // Xác thực và sanitize dữ liệu đầu vào
    $issue_type = sanitizeInput($_POST['issue_type']);
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $room_number = !empty($_POST['room_number']) ? sanitizeInput($_POST['room_number']) : ($student ? $student['room_number'] : null);
    $priority = sanitizeInput($_POST['priority']);
    $reported_by = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');
    
    // Kiểm tra dữ liệu đầu vào
    if (empty($issue_type)) {
        $error = "Vui lòng chọn loại vấn đề";
    } elseif (empty($title)) {
        $error = "Vui lòng nhập tiêu đề vấn đề";
    } elseif (empty($description)) {
        $error = "Vui lòng mô tả chi tiết vấn đề";
    } elseif ($student_id <= 0) {
        $error = "Không có sinh viên được chọn";
    } else {
        // Thêm vấn đề vào cơ sở dữ liệu
        $stmt = $conn->prepare("
            INSERT INTO issues (student_id, issue_type, title, description, room_number, priority, reported_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
        } else {
            $stmt->bind_param("isssssis", 
                $student_id, 
                $issue_type, 
                $title, 
                $description, 
                $room_number, 
                $priority, 
                $reported_by, 
                $current_time,
                $current_time
            );
            
            if ($stmt->execute()) {
                $issue_id = $stmt->insert_id;
                
                // Ghi nhật ký hoạt động
                logActivity('add_issue', "Đã thêm vấn đề '$title' (ID: $issue_id) cho sinh viên {$student['full_name']}");
                
                // Thông báo thành công
                $success = "Đã thêm vấn đề thành công!";
                
                // Chuyển hướng đến trang danh sách vấn đề
                header("Location: list.php?success=add");
                exit;
            } else {
                $error = "Lỗi khi thêm vấn đề: " . $stmt->error;
            }
            
            $stmt->close();
        }
    }
}

// Tiêu đề trang
$page_title = "Thêm Vấn Đề Mới";

// Bao gồm header
include $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';
?>

<div class="container-fluid">
    <!-- Tiêu đề trang -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Thêm Vấn Đề Mới</h1>
        <a href="list.php" class="d-none d-sm-inline-block btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Quay Lại Danh Sách
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success; ?></div>
    <?php endif; ?>

    <?php if (!$student && $student_id > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Không tìm thấy sinh viên. Vui lòng <a href="/LTW/views/admin/students/list.php">chọn một sinh viên khác</a>.
        </div>
    <?php elseif (!$student_id): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Vui lòng <a href="/LTW/views/admin/students/list.php">chọn một sinh viên</a> để báo cáo vấn đề.
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Thông tin sinh viên -->
            <div class="col-xl-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Thông Tin Sinh Viên</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <img class="img-profile rounded-circle" style="width: 120px; height: 120px; object-fit: cover;"
                                 src="<?= !empty($student['profile_pic']) ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] : '/LTW/assets/images/user.png' ?>">
                        </div>
                        <h5 class="text-center mb-3"><?= htmlspecialchars($student['full_name']) ?></h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Mã sinh viên:</strong> <?= htmlspecialchars($student['student_id']) ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></li>
                            <li class="list-group-item"><strong>Phòng:</strong> <?= $student['room_number'] ? htmlspecialchars($student['room_number']) : '<span class="text-muted">Chưa được gán</span>' ?></li>
                            <li class="list-group-item"><strong>Khoa/Ngành:</strong> <?= htmlspecialchars($student['department']) ?></li>
                            <li class="list-group-item">
                                <strong>Trạng thái:</strong> 
                                <span class="badge <?= $student['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $student['status'] === 'active' ? 'Đang hoạt động' : 'Đã vô hiệu hóa' ?>
                                </span>
                            </li>
                        </ul>
                        <div class="mt-3 text-center">
                            <a href="/LTW/views/admin/students/view.php?id=<?= $student_id ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-user"></i> Xem Hồ Sơ Đầy Đủ
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form thêm mới vấn đề -->
            <div class="col-xl-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Thông Tin Vấn Đề</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_issue">
                            
                            <div class="mb-3">
                                <label for="issue_type" class="form-label">Loại Vấn Đề <span class="text-danger">*</span></label>
                                <select class="form-select" id="issue_type" name="issue_type" required>
                                    <option value="">-- Chọn Loại Vấn Đề --</option>
                                    <option value="discipline">Vi Phạm Kỷ Luật</option>
                                    <option value="payment">Thanh Toán</option>
                                    <option value="behavior">Hành Vi</option>
                                    <option value="complaint">Khiếu Nại</option>
                                    <option value="maintenance">Bảo Trì</option>
                                    <option value="health">Vấn Đề Sức Khỏe</option>
                                    <option value="roommate">Mâu Thuẫn Với Bạn Cùng Phòng</option>
                                    <option value="noise">Ồn Ào</option>
                                    <option value="security">An Ninh</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Tiêu Đề <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" maxlength="255" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Mô Tả Chi Tiết <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                                <small class="form-text text-muted">Vui lòng cung cấp thông tin cụ thể về vấn đề, bao gồm thời gian, địa điểm và những người liên quan.</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="room_number" class="form-label">Phòng</label>
                                    <input type="text" class="form-control" id="room_number" name="room_number" 
                                           value="<?= $student['room_number'] ? htmlspecialchars($student['room_number']) : '' ?>">
                                    <small class="form-text text-muted">Để trống nếu vấn đề không liên quan đến phòng cụ thể.</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Mức Độ Ưu Tiên <span class="text-danger">*</span></label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="low">Thấp</option>
                                        <option value="medium" selected>Trung bình</option>
                                        <option value="high">Cao</option>
                                        <option value="urgent">Khẩn cấp</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="list.php" class="btn btn-secondary">Hủy</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Thêm Vấn Đề
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Bao gồm footer
include $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>