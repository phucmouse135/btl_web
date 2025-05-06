<?php
// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu đăng nhập
requireLogin();

// Yêu cầu quyền admin hoặc staff
if (!hasRole('admin') && !hasRole('staff')) {
    setAlert('error', 'Bạn không có quyền truy cập trang này.');
    redirect('/LTW/dashboard.php');
}

// Kiểm tra xem ID có được cung cấp hay không
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setAlert('error', 'ID yêu cầu bảo trì không hợp lệ.');
    redirect('/LTW/views/admin/maintenance/list.php');
}

$requestId = (int)$_GET['id'];
$request = null;
$comments = [];
$history = [];
$photos = [];
$canEdit = true; // Admin/staff luôn có quyền chỉnh sửa
$canAddComment = true; // Admin/staff luôn có quyền thêm bình luận
$message = '';
$error = '';

// Lấy chi tiết yêu cầu
$query = "SELECT mr.*, r.room_number, r.floor, b.name as building_name, b.id as building_id,
          CONCAT(reporter.first_name, ' ', reporter.last_name) as reported_by_name,
          CONCAT(completer.first_name, ' ', completer.last_name) as completed_by_name,
          reporter.email as reporter_email,
          completer.email as completer_email
          FROM maintenance_requests mr
          JOIN rooms r ON mr.room_id = r.id
          JOIN buildings b ON r.building_id = b.id
          LEFT JOIN users reporter ON mr.reported_by = reporter.id
          LEFT JOIN users completer ON mr.completed_by = completer.id
          WHERE mr.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error in prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setAlert('error', 'Không tìm thấy yêu cầu bảo trì.');
    redirect('/LTW/views/admin/maintenance/list.php');
}

$request = $result->fetch_assoc();
$stmt->close();

// Lấy bình luận
$commentQuery = "SELECT mc.*, 
                CONCAT(u.first_name, ' ', u.last_name) as commenter_name,
                u.role, u.email
                FROM maintenance_comments mc
                JOIN users u ON mc.user_id = u.id
                WHERE mc.request_id = ?
                ORDER BY mc.created_at ASC";

$stmt = $conn->prepare($commentQuery);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$commentResult = $stmt->get_result();

while ($row = $commentResult->fetch_assoc()) {
    $comments[] = $row;
}
$stmt->close();

// Lấy lịch sử
$historyQuery = "SELECT mh.*, 
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                u.role
                FROM maintenance_history mh
                JOIN users u ON mh.user_id = u.id
                WHERE mh.request_id = ?
                ORDER BY mh.created_at ASC";

$stmt = $conn->prepare($historyQuery);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$historyResult = $stmt->get_result();

while ($row = $historyResult->fetch_assoc()) {
    $history[] = $row;
}
$stmt->close();

// Lấy ảnh
$photoQuery = "SELECT * FROM maintenance_photos WHERE request_id = ? ORDER BY created_at ASC";
$stmt = $conn->prepare($photoQuery);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$photoResult = $stmt->get_result();

while ($row = $photoResult->fetch_assoc()) {
    $photos[] = $row;
}
$stmt->close();

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý gửi bình luận
    if (isset($_POST['action']) && $_POST['action'] === 'comment') {
        $comment = sanitizeInput($_POST['comment']);
        
        if (empty($comment)) {
            $error = "Bình luận không được để trống.";
        } else {
            $stmt = $conn->prepare("INSERT INTO maintenance_comments (request_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $requestId, $_SESSION['user_id'], $comment);
            
            if ($stmt->execute()) {
                // Ghi vào lịch sử
                $historyStmt = $conn->prepare("INSERT INTO maintenance_history (request_id, user_id, action, details) VALUES (?, ?, 'comment_added', ?)");
                $details = "Bình luận: " . substr($comment, 0, 50) . (strlen($comment) > 50 ? "..." : "");
                $historyStmt->bind_param("iis", $requestId, $_SESSION['user_id'], $details);
                $historyStmt->execute();
                $historyStmt->close();
                
                $message = "Đã thêm bình luận thành công.";
                
                // Làm mới trang để hiển thị bình luận mới
                redirect("/LTW/views/admin/maintenance/view.php?id=$requestId");
            } else {
                $error = "Không thể thêm bình luận.";
            }
            $stmt->close();
        }
    }
    
    // Xử lý cập nhật trạng thái
    if (isset($_POST['action']) && $_POST['action'] === 'status_update') {
        $newStatus = sanitizeInput($_POST['status']);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $oldStatus = $request['status'];
        
        if ($newStatus === $oldStatus) {
            $error = "Trạng thái đã được đặt thành $newStatus.";
        } else {
            $updateQuery = "UPDATE maintenance_requests SET status = ?";
            $params = [$newStatus];
            $types = "s";
            
            // Nếu đánh dấu là hoàn thành
            if ($newStatus === 'completed') {
                $updateQuery .= ", completed_date = CURRENT_DATE(), completed_by = ?";
                $params[] = $_SESSION['user_id'];
                $types .= "i";
            }
            
            if ($newStatus === 'reopened' || $newStatus === 'pending') {
                $updateQuery .= ", completed_date = NULL, completed_by = NULL";
            }
            
            $updateQuery .= " WHERE id = ?";
            $params[] = $requestId;
            $types .= "i";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Thêm bản ghi lịch sử
                $historyStmt = $conn->prepare("
                    INSERT INTO maintenance_history 
                    (request_id, user_id, action, details, old_value, new_value) 
                    VALUES (?, ?, 'status_change', ?, ?, ?)
                ");
                $details = !empty($notes) ? "Ghi chú: $notes" : "Trạng thái đã thay đổi";
                $historyStmt->bind_param("iisss", $requestId, $_SESSION['user_id'], $details, $oldStatus, $newStatus);
                $historyStmt->execute();
                $historyStmt->close();
                
                $message = "Trạng thái yêu cầu đã được cập nhật thành " . ucfirst(str_replace('_', ' ', $newStatus)) . ".";
                
                // Làm mới trang để hiển thị cập nhật
                redirect("/LTW/views/admin/maintenance/view.php?id=$requestId");
            } else {
                $error = "Không thể cập nhật trạng thái yêu cầu.";
            }
            $stmt->close();
        }
    }
    
    // Xử lý cập nhật mức độ ưu tiên
    if (isset($_POST['action']) && $_POST['action'] === 'priority_update') {
        $newPriority = sanitizeInput($_POST['priority']);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $oldPriority = $request['priority'];
        
        if ($newPriority === $oldPriority) {
            $error = "Mức độ ưu tiên đã được đặt thành $newPriority.";
        } else {
            $stmt = $conn->prepare("UPDATE maintenance_requests SET priority = ? WHERE id = ?");
            $stmt->bind_param("si", $newPriority, $requestId);
            
            if ($stmt->execute()) {
                // Thêm bản ghi lịch sử
                $historyStmt = $conn->prepare("
                    INSERT INTO maintenance_history 
                    (request_id, user_id, action, details, old_value, new_value) 
                    VALUES (?, ?, 'priority_change', ?, ?, ?)
                ");
                $details = !empty($notes) ? "Ghi chú: $notes" : "Mức độ ưu tiên đã thay đổi";
                $historyStmt->bind_param("iisss", $requestId, $_SESSION['user_id'], $details, $oldPriority, $newPriority);
                $historyStmt->execute();
                $historyStmt->close();
                
                $message = "Mức độ ưu tiên của yêu cầu đã được cập nhật thành " . ucfirst($newPriority) . ".";
                
                // Làm mới trang để hiển thị cập nhật
                redirect("/LTW/views/admin/maintenance/view.php?id=$requestId");
            } else {
                $error = "Không thể cập nhật mức độ ưu tiên của yêu cầu.";
            }
            $stmt->close();
        }
    }
    
    // Xử lý tải lên tệp
    if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
        // Kiểm tra xem có tệp nào được tải lên không
        if (!empty($_FILES['photo']['name'])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/maintenance/';
            
            // Tạo thư mục nếu nó không tồn tại
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = basename($_FILES['photo']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Tạo tên tệp duy nhất
            $newFileName = 'request_' . $requestId . '_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $targetFile = $uploadDir . $newFileName;
            
            // Kiểm tra loại tệp
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($fileExt, $allowedTypes)) {
                $error = "Chỉ cho phép các tệp JPG, JPEG, PNG và GIF.";
            } elseif ($_FILES['photo']['size'] > 5000000) { // Giới hạn 5MB
                $error = "Tệp quá lớn. Kích thước tối đa là 5MB.";
            } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                // Tệp đã được tải lên thành công, thêm vào cơ sở dữ liệu
                $caption = sanitizeInput($_POST['caption'] ?? '');
                
                $stmt = $conn->prepare("INSERT INTO maintenance_photos (request_id, file_path, caption, uploaded_by) VALUES (?, ?, ?, ?)");
                $relativePath = '/LTW/uploads/maintenance/' . $newFileName;
                $stmt->bind_param("issi", $requestId, $relativePath, $caption, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Thêm bản ghi lịch sử
                    $historyStmt = $conn->prepare("
                        INSERT INTO maintenance_history 
                        (request_id, user_id, action, details) 
                        VALUES (?, ?, 'photo_added', ?)
                    ");
                    $details = "Đã tải lên ảnh" . (!empty($caption) ? ": $caption" : "");
                    $historyStmt->bind_param("iis", $requestId, $_SESSION['user_id'], $details);
                    $historyStmt->execute();
                    $historyStmt->close();
                    
                    $message = "Đã tải lên ảnh thành công.";
                    
                    // Làm mới trang để hiển thị ảnh mới
                    redirect("/LTW/views/admin/maintenance/view.php?id=$requestId");
                } else {
                    $error = "Không thể lưu thông tin ảnh vào cơ sở dữ liệu.";
                }
                $stmt->close();
            } else {
                $error = "Không thể tải lên ảnh.";
            }
        } else {
            $error = "Không có ảnh nào được chọn để tải lên.";
        }
    }
    
    // Xử lý giao việc
    if (isset($_POST['action']) && $_POST['action'] === 'assign_staff') {
        $staffId = (int)$_POST['staff_id'];
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        if ($staffId <= 0) {
            $error = "Vui lòng chọn nhân viên để giao việc.";
        } else {
            $stmt = $conn->prepare("UPDATE maintenance_requests SET assigned_to = ? WHERE id = ?");
            $stmt->bind_param("ii", $staffId, $requestId);
            
            if ($stmt->execute()) {
                // Thêm bản ghi lịch sử
                $historyStmt = $conn->prepare("
                    INSERT INTO maintenance_history 
                    (request_id, user_id, action, details) 
                    VALUES (?, ?, 'assigned', ?)
                ");
                
                // Lấy tên nhân viên được giao việc
                $staffQuery = "SELECT CONCAT(first_name, ' ', last_name) as staff_name FROM users WHERE id = ?";
                $staffStmt = $conn->prepare($staffQuery);
                $staffStmt->bind_param("i", $staffId);
                $staffStmt->execute();
                $staffResult = $staffStmt->get_result();
                $staffName = $staffResult->fetch_assoc()['staff_name'];
                $staffStmt->close();
                
                $details = "Đã giao việc cho $staffName" . (!empty($notes) ? ". Ghi chú: $notes" : "");
                $historyStmt->bind_param("iis", $requestId, $_SESSION['user_id'], $details);
                $historyStmt->execute();
                $historyStmt->close();
                
                $message = "Yêu cầu đã được giao cho $staffName.";
                
                // Làm mới trang để hiển thị cập nhật
                redirect("/LTW/views/admin/maintenance/view.php?id=$requestId");
            } else {
                $error = "Không thể giao việc cho nhân viên.";
            }
            $stmt->close();
        }
    }
}

// Định dạng dấu thời gian
function formatTimestamp($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $interval = $date->diff($now);
    
    if ($interval->d == 0) {
        if ($interval->h == 0) {
            if ($interval->i == 0) {
                return "vừa xong";
            }
            return $interval->i . " phút trước";
        }
        return $interval->h . " giờ trước";
    } elseif ($interval->d < 7) {
        return $interval->d . " ngày trước";
    } else {
        return $date->format('d/m/Y \l\ú\c H:i');
    }
}

// Xác định huy hiệu trạng thái
$statusBadgeClass = 'bg-secondary';
$statusText = ucfirst(str_replace('_', ' ', $request['status']));

switch ($request['status']) {
    case 'pending':
        $statusBadgeClass = 'bg-warning text-dark';
        $statusText = 'Đang chờ';
        break;
    case 'in_progress':
        $statusBadgeClass = 'bg-info';
        $statusText = 'Đang xử lý';
        break;
    case 'scheduled':
        $statusBadgeClass = 'bg-primary';
        $statusText = 'Đã lên lịch';
        break;
    case 'completed':
        $statusBadgeClass = 'bg-success';
        $statusText = 'Hoàn thành';
        break;
    case 'canceled':
        $statusBadgeClass = 'bg-secondary';
        $statusText = 'Đã hủy';
        break;
}

// Xác định huy hiệu mức độ ưu tiên
$priorityBadgeClass = 'bg-secondary';

switch ($request['priority']) {
    case 'low':
        $priorityBadgeClass = 'bg-success';
        break;
    case 'normal':
        $priorityBadgeClass = 'bg-primary';
        break;
    case 'high':
        $priorityBadgeClass = 'bg-warning text-dark';
        break;
    case 'emergency':
        $priorityBadgeClass = 'bg-danger';
        break;
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            Yêu cầu Bảo trì #<?php echo sprintf('%06d', $request['id']); ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/LTW/dashboard.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="/LTW/views/admin/maintenance/list.php">Quản lý Bảo trì</a></li>
                <li class="breadcrumb-item active" aria-current="page">Yêu cầu #<?php echo sprintf('%06d', $request['id']); ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="/LTW/views/admin/maintenance/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại Danh sách
        </a>
    </div>
</div>

<?php if ($message): ?>
    <?php echo displaySuccess($message); ?>
<?php endif; ?>

<?php if ($error): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<div class="row">
    <!-- Chi tiết Yêu cầu Chính -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Chi tiết Yêu cầu</h6>
                <div>
                    <span class="badge <?php echo $statusBadgeClass; ?> me-2"><?php echo $statusText; ?></span>
                    <span class="badge <?php echo $priorityBadgeClass; ?>"><?php echo ucfirst($request['priority']) == 'Low' ? 'Thấp' : (ucfirst($request['priority']) == 'Normal' ? 'Bình thường' : (ucfirst($request['priority']) == 'High' ? 'Cao' : 'Khẩn cấp')); ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Thông tin Sự cố</h5>
                        <table class="table table-sm">
                            <tr>
                                <th width="130">Loại Sự cố:</th>
                                <td><?php echo ucfirst(str_replace('_', ' ', $request['issue_type'])); ?></td>
                            </tr>
                            <tr>
                                <th>Vị trí:</th>
                                <td><?php echo $request['building_name'] . ' - Phòng ' . $request['room_number'] . ' (Tầng ' . $request['floor'] . ')'; ?></td>
                            </tr>
                            <tr>
                                <th>Ngày Báo cáo:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Người Báo cáo:</th>
                                <td>
                                    <?php echo $request['reported_by_name']; ?> 
                                    <?php if ($request['reporter_email']): ?>
                                        <br><small class="text-muted"><?php echo $request['reporter_email']; ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($request['status'] === 'completed'): ?>
                            <tr>
                                <th>Hoàn thành:</th>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($request['completed_date'])); ?>
                                    <?php if ($request['completed_by_name']): ?>
                                        bởi <?php echo $request['completed_by_name']; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="admin-actions mb-4">
                            <h5>Thao tác Quản trị</h5>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                                    <i class="fas fa-sync-alt me-2"></i> Cập nhật Trạng thái
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#priorityModal">
                                    <i class="fas fa-flag me-2"></i> Thay đổi Mức độ ưu tiên
                                </button>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#assignModal">
                                    <i class="fas fa-user-plus me-2"></i> Giao việc cho Nhân viên
                                </button>
                            </div>
                        </div>
                        
                        <h5>Trạng thái Hiện tại</h5>
                        <div class="status-timeline mb-3">
                            <div class="d-flex justify-content-between position-relative mb-4">
                                <div class="timeline-line"></div>
                                <?php
                                $statuses = ['pending', 'in_progress', 'scheduled', 'completed'];
                                $statusNames = ['Đang chờ', 'Đang xử lý', 'Đã lên lịch', 'Hoàn thành'];
                                $currentIndex = array_search($request['status'], $statuses);
                                if ($currentIndex === false) $currentIndex = -1; // Cho trạng thái đã hủy hoặc khác
                                
                                foreach ($statuses as $index => $status):
                                    $isActive = $index <= $currentIndex;
                                    $statusName = $statusNames[$index];
                                ?>
                                <div class="timeline-point <?php echo $isActive ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <?php if ($isActive): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-label"><?php echo $statusName; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h5>Mô tả</h5>
                <div class="p-3 bg-light rounded mb-4">
                    <?php echo nl2br(htmlspecialchars($request['description'])); ?>
                </div>

                <?php if (!empty($request['access_instructions'])): ?>
                <h5>Hướng dẫn Tiếp cận</h5>
                <div class="p-3 bg-light rounded mb-4">
                    <?php echo nl2br(htmlspecialchars($request['access_instructions'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ảnh -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Ảnh</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                    <i class="fas fa-upload me-1"></i> Tải lên Ảnh
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($photos)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Chưa có ảnh nào được tải lên cho yêu cầu này.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($photos as $photo): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <a href="<?php echo $photo['file_path']; ?>" target="_blank" data-lightbox="maintenance-photos" data-title="<?php echo htmlspecialchars($photo['caption']); ?>">
                                        <img src="<?php echo $photo['file_path']; ?>" class="card-img-top img-thumbnail" alt="Ảnh bảo trì">
                                    </a>
                                    <div class="card-body">
                                        <?php if (!empty($photo['caption'])): ?>
                                            <p class="card-text"><?php echo htmlspecialchars($photo['caption']); ?></p>
                                        <?php endif; ?>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Đã tải lên <?php echo formatTimestamp($photo['created_at']); ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bình luận -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Bình luận & Cập nhật</h6>
            </div>
            <div class="card-body">
                <div class="comments mb-4">
                    <?php if (empty($comments)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Chưa có bình luận nào.
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <?php 
                            $isStaff = $comment['role'] === 'staff' || $comment['role'] === 'admin';
                            $userName = $comment['commenter_name'];
                            ?>
                            <div class="comment-item <?php echo $isStaff ? 'staff-comment' : 'student-comment'; ?>">
                                <div class="comment-header">
                                    <strong>
                                        <?php echo $userName; ?>
                                        <?php if ($isStaff): ?>
                                            <span class="badge bg-info">Nhân viên</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sinh viên</span>
                                        <?php endif; ?>
                                    </strong>
                                    <span class="comment-time text-muted"><?php echo formatTimestamp($comment['created_at']); ?></span>
                                </div>
                                <div class="comment-body">
                                    <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($canAddComment && $request['status'] !== 'canceled'): ?>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="comment">
                        <div class="form-group mb-3">
                            <label for="comment" class="form-label">Thêm Bình luận</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-comment me-1"></i> Gửi Bình luận
                        </button>
                    </form>
                <?php elseif ($request['status'] === 'canceled'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Yêu cầu này đã bị hủy. Bình luận đã bị vô hiệu hóa.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Thanh bên -->
    <div class="col-lg-4">
        <!-- Thông tin Nhân viên Được giao -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Nhân viên Được giao</h6>
            </div>
            <div class="card-body">
                <?php
                $assignedStaffId = $request['assigned_to'] ?? null;
                $assignedStaff = null;
                
                if ($assignedStaffId) {
                    $staffQuery = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, phone 
                                FROM users 
                                WHERE id = ? AND (role = 'staff' OR role = 'admin')";
                    $staffStmt = $conn->prepare($staffQuery);
                    $staffStmt->bind_param("i", $assignedStaffId);
                    $staffStmt->execute();
                    $staffResult = $staffStmt->get_result();
                    
                    if ($staffResult->num_rows > 0) {
                        $assignedStaff = $staffResult->fetch_assoc();
                    }
                    
                    $staffStmt->close();
                }
                
                if ($assignedStaff):
                ?>
                    <div class="text-center mb-3">
                        <i class="fas fa-user-circle fa-4x text-gray-300 mb-2"></i>
                        <h5><?php echo $assignedStaff['full_name']; ?></h5>
                        <p class="text-muted">Nhân viên được giao</p>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-envelope me-2"></i> Email</span>
                            <span><?php echo $assignedStaff['email']; ?></span>
                        </li>
                        <?php if (!empty($assignedStaff['phone'])): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-phone me-2"></i> Điện thoại</span>
                            <span><?php echo $assignedStaff['phone']; ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Chưa có nhân viên nào được giao cho yêu cầu này.
                    </div>
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#assignModal">
                        <i class="fas fa-user-plus me-1"></i> Giao việc cho Nhân viên
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lịch sử Hoạt động -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lịch sử Hoạt động</h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php if (empty($history)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Không có lịch sử hoạt động.
                        </div>
                    <?php else: ?>
                        <!-- Sự kiện tạo yêu cầu -->
                        <div class="timeline-item">
                            <div class="timeline-item-marker"></div>
                            <div class="timeline-item-content">
                                <h6>Đã Tạo Yêu cầu</h6>
                                <p>
                                    Yêu cầu được gửi bởi <?php echo $request['reported_by_name']; ?>
                                </p>
                                <span class="timeline-item-date">
                                    <?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php foreach ($history as $event): ?>
                            <?php 
                            $isStaff = $event['role'] === 'staff' || $event['role'] === 'admin';
                            $userName = $event['user_name'];
                            $actionText = '';
                            $iconClass = 'fas fa-history';
                            
                            switch ($event['action']) {
                                case 'status_change':
                                    $oldStatus = $event['old_value'] == 'pending' ? 'Đang chờ' : 
                                               ($event['old_value'] == 'in_progress' ? 'Đang xử lý' : 
                                               ($event['old_value'] == 'scheduled' ? 'Đã lên lịch' : 
                                               ($event['old_value'] == 'completed' ? 'Hoàn thành' : 
                                               ($event['old_value'] == 'canceled' ? 'Đã hủy' : ucfirst(str_replace('_', ' ', $event['old_value']))))));
                                    
                                    $newStatus = $event['new_value'] == 'pending' ? 'Đang chờ' : 
                                               ($event['new_value'] == 'in_progress' ? 'Đang xử lý' : 
                                               ($event['new_value'] == 'scheduled' ? 'Đã lên lịch' : 
                                               ($event['new_value'] == 'completed' ? 'Hoàn thành' : 
                                               ($event['new_value'] == 'canceled' ? 'Đã hủy' : ucfirst(str_replace('_', ' ', $event['new_value']))))));
                                    
                                    $actionText = "Trạng thái thay đổi từ <strong>" . $oldStatus . "</strong> thành <strong>" . $newStatus . "</strong>";
                                    $iconClass = 'fas fa-sync-alt';
                                    break;
                                case 'priority_change':
                                    $oldPriority = $event['old_value'] == 'low' ? 'Thấp' :
                                                 ($event['old_value'] == 'normal' ? 'Bình thường' :
                                                 ($event['old_value'] == 'high' ? 'Cao' :
                                                 ($event['old_value'] == 'emergency' ? 'Khẩn cấp' : ucfirst($event['old_value']))));
                                                 
                                    $newPriority = $event['new_value'] == 'low' ? 'Thấp' :
                                                 ($event['new_value'] == 'normal' ? 'Bình thường' :
                                                 ($event['new_value'] == 'high' ? 'Cao' :
                                                 ($event['new_value'] == 'emergency' ? 'Khẩn cấp' : ucfirst($event['new_value']))));
                                    
                                    $actionText = "Mức độ ưu tiên thay đổi từ <strong>" . $oldPriority . "</strong> thành <strong>" . $newPriority . "</strong>";
                                    $iconClass = 'fas fa-flag';
                                    break;
                                case 'comment_added':
                                    $actionText = "Đã thêm bình luận";
                                    $iconClass = 'fas fa-comment';
                                    break;
                                case 'photo_added':
                                    $actionText = "Đã tải lên ảnh";
                                    $iconClass = 'fas fa-image';
                                    break;
                                case 'assigned':
                                    $actionText = "Đã giao việc";
                                    $iconClass = 'fas fa-user-plus';
                                    break;
                                default:
                                    $actionText = ucfirst(str_replace('_', ' ', $event['action']));
                            }
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-item-marker">
                                    <i class="<?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="timeline-item-content">
                                    <h6><?php echo $userName; ?> <?php echo $actionText; ?></h6>
                                    <?php if (!empty($event['details'])): ?>
                                        <p><?php echo htmlspecialchars($event['details']); ?></p>
                                    <?php endif; ?>
                                    <span class="timeline-item-date">
                                        <?php echo formatTimestamp($event['created_at']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Yêu cầu Liên quan -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Yêu cầu Khác trong Phòng này</h6>
            </div>
            <div class="card-body">
                <?php
                // Lấy các yêu cầu khác cho cùng một phòng
                $relatedQuery = "SELECT id, issue_type, status, request_date, priority 
                               FROM maintenance_requests 
                               WHERE room_id = ? AND id != ? 
                               ORDER BY request_date DESC 
                               LIMIT 5";
                $stmt = $conn->prepare($relatedQuery);
                $stmt->bind_param("ii", $request['room_id'], $requestId);
                $stmt->execute();
                $relatedResult = $stmt->get_result();
                
                if ($relatedResult->num_rows === 0):
                ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Không có yêu cầu nào khác cho phòng này.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php while ($related = $relatedResult->fetch_assoc()): ?>
                            <?php 
                            // Xác định các lớp huy hiệu
                            $relStatusClass = 'bg-secondary';
                            $relStatusText = ucfirst(str_replace('_', ' ', $related['status']));
                            
                            switch ($related['status']) {
                                case 'pending': 
                                    $relStatusClass = 'bg-warning text-dark'; 
                                    $relStatusText = 'Đang chờ';
                                    break;
                                case 'in_progress': 
                                    $relStatusClass = 'bg-info'; 
                                    $relStatusText = 'Đang xử lý';
                                    break;
                                case 'scheduled': 
                                    $relStatusClass = 'bg-primary'; 
                                    $relStatusText = 'Đã lên lịch';
                                    break;
                                case 'completed': 
                                    $relStatusClass = 'bg-success'; 
                                    $relStatusText = 'Hoàn thành';
                                    break;
                                case 'canceled': 
                                    $relStatusClass = 'bg-secondary'; 
                                    $relStatusText = 'Đã hủy';
                                    break;
                            }
                            
                            $relPriorityClass = 'bg-secondary';
                            $relPriorityText = ucfirst($related['priority']);
                            
                            switch ($related['priority']) {
                                case 'low': 
                                    $relPriorityClass = 'bg-success'; 
                                    $relPriorityText = 'Thấp';
                                    break;
                                case 'normal': 
                                    $relPriorityClass = 'bg-primary'; 
                                    $relPriorityText = 'Bình thường';
                                    break;
                                case 'high': 
                                    $relPriorityClass = 'bg-warning text-dark'; 
                                    $relPriorityText = 'Cao';
                                    break;
                                case 'emergency': 
                                    $relPriorityClass = 'bg-danger'; 
                                    $relPriorityText = 'Khẩn cấp';
                                    break;
                            }
                            ?>
                            <a href="/LTW/views/admin/maintenance/view.php?id=<?php echo $related['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        #<?php echo sprintf('%06d', $related['id']); ?>: 
                                        <?php echo ucfirst(str_replace('_', ' ', $related['issue_type'])); ?>
                                    </h6>
                                    <small><?php echo date('d/m', strtotime($related['request_date'])); ?></small>
                                </div>
                                <div>
                                    <span class="badge <?php echo $relStatusClass; ?> me-1">
                                        <?php echo $relStatusText; ?>
                                    </span>
                                    <span class="badge <?php echo $relPriorityClass; ?>">
                                        <?php echo $relPriorityText; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if ($relatedResult->num_rows == 5): ?>
                        <div class="mt-3 text-center">
                            <a href="/LTW/views/admin/maintenance/list.php?room_id=<?php echo $request['room_id']; ?>" class="btn btn-sm btn-outline-primary">
                                Xem Tất cả Yêu cầu cho Phòng này
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; $stmt->close(); ?>
            </div>
        </div>
    </div>
</div>

<!-- Hộp thoại Cập nhật Trạng thái -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <input type="hidden" name="action" value="status_update">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Cập nhật Trạng thái Yêu cầu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="status" class="form-label">Trạng thái Mới</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Chọn Trạng thái</option>
                            <option value="pending" <?php echo $request['status'] === 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                            <option value="in_progress" <?php echo $request['status'] === 'in_progress' ? 'selected' : ''; ?>>Đang xử lý</option>
                            <option value="scheduled" <?php echo $request['status'] === 'scheduled' ? 'selected' : ''; ?>>Đã lên lịch</option>
                            <option value="completed" <?php echo $request['status'] === 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                            <option value="canceled" <?php echo $request['status'] === 'canceled' ? 'selected' : ''; ?>>Đã hủy</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="notes" class="form-label">Ghi chú (Không bắt buộc)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Cập nhật Trạng thái</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hộp thoại Cập nhật Mức độ ưu tiên -->
<div class="modal fade" id="priorityModal" tabindex="-1" aria-labelledby="priorityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <input type="hidden" name="action" value="priority_update">
                <div class="modal-header">
                    <h5 class="modal-title" id="priorityModalLabel">Cập nhật Mức độ ưu tiên Yêu cầu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="priority" class="form-label">Mức độ ưu tiên Mới</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="">Chọn Mức độ ưu tiên</option>
                            <option value="low" <?php echo $request['priority'] === 'low' ? 'selected' : ''; ?>>Thấp</option>
                            <option value="normal" <?php echo $request['priority'] === 'normal' ? 'selected' : ''; ?>>Bình thường</option>
                            <option value="high" <?php echo $request['priority'] === 'high' ? 'selected' : ''; ?>>Cao</option>
                            <option value="emergency" <?php echo $request['priority'] === 'emergency' ? 'selected' : ''; ?>>Khẩn cấp</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="notes" class="form-label">Ghi chú (Không bắt buộc)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Cập nhật Mức độ ưu tiên</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hộp thoại Tải ảnh lên -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_photo">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPhotoModalLabel">Tải Ảnh lên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="photo" class="form-label">Chọn Ảnh (JPG, PNG, GIF; tối đa 5MB)</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="caption" class="form-label">Chú thích (Không bắt buộc)</label>
                        <textarea class="form-control" id="caption" name="caption" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Tải lên</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hộp thoại Giao việc -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <input type="hidden" name="action" value="assign_staff">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalLabel">Giao việc cho Nhân viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="staff_id" class="form-label">Chọn Nhân viên</label>
                        <select class="form-select" id="staff_id" name="staff_id" required>
                            <option value="">Chọn Nhân viên</option>
                            <?php
                            // Lấy danh sách nhân viên
                            $staffQuery = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
                                        FROM users 
                                        WHERE role = 'staff' OR role = 'admin' 
                                        ORDER BY full_name ASC";
                            $staffStmt = $conn->prepare($staffQuery);
                            $staffStmt->execute();
                            $staffResult = $staffStmt->get_result();
                            
                            while ($staff = $staffResult->fetch_assoc()):
                                $selected = ($staff['id'] == $request['assigned_to']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo $staff['full_name']; ?>
                                </option>
                            <?php endwhile; $staffStmt->close(); ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="notes" class="form-label">Ghi chú (Không bắt buộc)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Giao việc</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>