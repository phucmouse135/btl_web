<?php
// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu đăng nhập
requireLogin();

// Kiểm tra xem ID có được cung cấp hay không
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setAlert('error', 'ID yêu cầu bảo trì không hợp lệ.');
    redirect('/LTW/views/maintenance/list.php');
}

$requestId = (int)$_GET['id'];
$request = null;
$canEdit = false;
$canAddComment = false;
$message = '';
$error = '';

// Lấy chi tiết yêu cầu bảo trì từ cơ sở dữ liệu
$query = "SELECT mr.*, 
           r.room_number, r.floor, r.building_name,
           CONCAT(u1.first_name, ' ', u1.last_name) as reporter_name,
           u1.email as reporter_email,
           CONCAT(u2.first_name, ' ', u2.last_name) as assignee_name,
           u2.email as assignee_email 
           FROM maintenance_requests mr
           LEFT JOIN rooms r ON mr.room_id = r.id
           LEFT JOIN users u1 ON mr.reported_by = u1.id
           LEFT JOIN users u2 ON mr.assigned_to = u2.id
           WHERE mr.id = ?";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error in SQL query: " . $conn->error . "<br>Query: " . htmlspecialchars($query));
}
$stmt->bind_param("i", $_GET['id']);

if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setAlert('error', 'Không tìm thấy yêu cầu bảo trì.');
    redirect('/LTW/views/maintenance/list.php');
}

$request = $result->fetch_assoc();
$stmt->close();

// Kiểm tra quyền hạn
if (hasRole('student')) {
    // Sinh viên chỉ có thể xem yêu cầu của chính họ
    if ($request['reported_by'] != $_SESSION['user_id']) {
        setAlert('error', 'Bạn không có quyền xem yêu cầu này.');
        redirect('/LTW/views/maintenance/list.php');
    }
    $canAddComment = true;
} elseif (hasRole('staff') || hasRole('admin')) {
    $canEdit = true;
    $canAddComment = true;
}

// Khởi tạo biến để tránh lỗi undefined
$comments = [];
$history = [];
$photos = [];

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý gửi bình luận
    if (isset($_POST['action']) && $_POST['action'] === 'comment') {
        if (!$canAddComment) {
            $error = "Bạn không có quyền thêm bình luận.";
        } else {
            $comment = sanitizeInput($_POST['comment']);
            
            if (empty($comment)) {
                $error = "Bình luận không được để trống.";
            } else {
                // Sử dụng trường resolution để lưu bình luận và lịch sử
                if (hasRole('staff') || hasRole('admin')) {
                    $currentResolution = $request['resolution'] ?? '';
                    $newResolution = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': ' . $comment;
                    
                    if (!empty($currentResolution)) {
                        $newResolution = $currentResolution . "\n\n" . $newResolution;
                    }
                    
                    $stmt = $conn->prepare("UPDATE maintenance_requests SET resolution = ? WHERE id = ?");
                    if ($stmt === false) {
                        $error = "Error preparing comment update: " . $conn->error;
                    } else {
                        $stmt->bind_param("si", $newResolution, $requestId);
                        
                        if ($stmt->execute()) {
                            $message = "Đã thêm bình luận thành công.";
                            
                            // Làm mới trang để hiển thị bình luận mới
                            redirect("/LTW/views/maintenance/view.php?id=$requestId");
                        } else {
                            $error = "Không thể thêm bình luận: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
    
    // Xử lý cập nhật trạng thái
    if (isset($_POST['action']) && $_POST['action'] === 'status_update') {
        if (!$canEdit) {
            $error = "Bạn không có quyền cập nhật yêu cầu này.";
        } else {
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
                    $updateQuery .= ", completed_date = CURRENT_DATE()";
                    
                    // Thêm ghi chú hoàn thành vào trường resolution
                    $currentResolution = $request['resolution'] ?? '';
                    $completionNote = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': HOÀN THÀNH - ' . $notes;
                    
                    if (!empty($currentResolution)) {
                        $newResolution = $currentResolution . "\n\n" . $completionNote;
                    } else {
                        $newResolution = $completionNote;
                    }
                    
                    $updateQuery .= ", resolution = ?";
                    $params[] = $newResolution;
                    $types .= "s";
                }
                
                if ($newStatus === 'pending') {
                    $updateQuery .= ", completed_date = NULL";
                    
                    // Thêm ghi chú về việc đặt lại trạng thái
                    $currentResolution = $request['resolution'] ?? '';
                    $reopenNote = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': ĐÃ ĐẶT LẠI TRẠNG THÁI - ' . $notes;
                    
                    if (!empty($currentResolution)) {
                        $newResolution = $currentResolution . "\n\n" . $reopenNote;
                    } else {
                        $newResolution = $reopenNote;
                    }
                    
                    $updateQuery .= ", resolution = ?";
                    $params[] = $newResolution;
                    $types .= "s";
                }
                
                if ($newStatus === 'in_progress' && ($oldStatus === 'pending' || $oldStatus === 'rejected')) {
                    // Thêm ghi chú về việc bắt đầu xử lý
                    $currentResolution = $request['resolution'] ?? '';
                    $inProgressNote = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': BẮT ĐẦU XỬ LÝ - ' . $notes;
                    
                    if (!empty($currentResolution)) {
                        $newResolution = $currentResolution . "\n\n" . $inProgressNote;
                    } else {
                        $newResolution = $inProgressNote;
                    }
                    
                    $updateQuery .= ", resolution = ?";
                    $params[] = $newResolution;
                    $types .= "s";
                }
                
                if ($newStatus === 'rejected') {
                    // Thêm ghi chú về việc từ chối
                    $currentResolution = $request['resolution'] ?? '';
                    $rejectedNote = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': TỪ CHỐI - ' . $notes;
                    
                    if (!empty($currentResolution)) {
                        $newResolution = $currentResolution . "\n\n" . $rejectedNote;
                    } else {
                        $newResolution = $rejectedNote;
                    }
                    
                    $updateQuery .= ", resolution = ?";
                    $params[] = $newResolution;
                    $types .= "s";
                }
                
                $updateQuery .= " WHERE id = ?";
                $params[] = $requestId;
                $types .= "i";
                
                $stmt = $conn->prepare($updateQuery);
                if ($stmt === false) {
                    $error = "Error preparing status update: " . $conn->error;
                } else {
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $message = "Trạng thái yêu cầu đã được cập nhật thành " . ucfirst(str_replace('_', ' ', $newStatus)) . ".";
                        
                        // Làm mới trang để hiển thị cập nhật
                        redirect("/LTW/views/maintenance/view.php?id=$requestId");
                    } else {
                        $error = "Không thể cập nhật trạng thái yêu cầu: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Xử lý cập nhật mức độ ưu tiên
    if (isset($_POST['action']) && $_POST['action'] === 'priority_update') {
        if (!$canEdit) {
            $error = "Bạn không có quyền cập nhật yêu cầu này.";
        } else {
            $newPriority = sanitizeInput($_POST['priority']);
            $notes = sanitizeInput($_POST['notes'] ?? '');
            $oldPriority = $request['priority'];
            
            if ($newPriority === $oldPriority) {
                $error = "Mức độ ưu tiên đã được đặt thành $newPriority.";
            } else {
                // Cập nhật mức độ ưu tiên
                $stmt = $conn->prepare("UPDATE maintenance_requests SET priority = ? WHERE id = ?");
                if ($stmt === false) {
                    $error = "Error preparing priority update: " . $conn->error;
                } else {
                    $stmt->bind_param("si", $newPriority, $requestId);
                    
                    if ($stmt->execute()) {
                        // Thêm ghi chú về việc thay đổi ưu tiên vào trường resolution
                        $currentResolution = $request['resolution'] ?? '';
                        $priorityNote = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': THAY ĐỔI MỨC ĐỘ ƯU TIÊN từ ' . 
                                        ucfirst($oldPriority) . ' thành ' . ucfirst($newPriority);
                        
                        if (!empty($notes)) {
                            $priorityNote .= ' - ' . $notes;
                        }
                        
                        if (!empty($currentResolution)) {
                            $newResolution = $currentResolution . "\n\n" . $priorityNote;
                        } else {
                            $newResolution = $priorityNote;
                        }
                        
                        $resolutionStmt = $conn->prepare("UPDATE maintenance_requests SET resolution = ? WHERE id = ?");
                        if ($resolutionStmt === false) {
                            $error = "Error preparing resolution update: " . $conn->error;
                        } else {
                            $resolutionStmt->bind_param("si", $newResolution, $requestId);
                            $resolutionStmt->execute();
                            $resolutionStmt->close();
                        }
                        
                        $message = "Mức độ ưu tiên của yêu cầu đã được cập nhật thành " . ucfirst($newPriority) . ".";
                        
                        // Làm mới trang để hiển thị cập nhật
                        redirect("/LTW/views/maintenance/view.php?id=$requestId");
                    } else {
                        $error = "Không thể cập nhật mức độ ưu tiên của yêu cầu: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Xử lý chỉ định nhân viên
    if (isset($_POST['action']) && $_POST['action'] === 'assign_staff') {
        if (!$canEdit) {
            $error = "Bạn không có quyền chỉ định nhân viên cho yêu cầu này.";
        } else {
            $assignedTo = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : null;
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            // Lấy tên của nhân viên được chỉ định
            $staffName = "Không được chỉ định";
            if ($assignedTo) {
                $staffQuery = "SELECT CONCAT(first_name, ' ', last_name) AS staff_name FROM users WHERE id = ?";
                $staffStmt = $conn->prepare($staffQuery);
                if ($staffStmt) {
                    $staffStmt->bind_param("i", $assignedTo);
                    $staffStmt->execute();
                    $staffResult = $staffStmt->get_result();
                    if ($staffRow = $staffResult->fetch_assoc()) {
                        $staffName = $staffRow['staff_name'];
                    }
                    $staffStmt->close();
                }
            }
            
            // Cập nhật nhân viên được chỉ định
            $stmt = $conn->prepare("UPDATE maintenance_requests SET assigned_to = ? WHERE id = ?");
            if ($stmt === false) {
                $error = "Error preparing assignment update: " . $conn->error;
            } else {
                if ($assignedTo === null) {
                    $stmt->bind_param("ii", NULL, $requestId);
                } else {
                    $stmt->bind_param("ii", $assignedTo, $requestId);
                }
                
                if ($stmt->execute()) {
                    // Thêm ghi chú về việc chỉ định nhân viên vào trường resolution
                    $currentResolution = $request['resolution'] ?? '';
                    $assignmentNote = date('Y-m-d H:i') . ' - ' . $_SESSION['username'] . ': CHỈ ĐỊNH NHÂN VIÊN - ' . 
                                     $staffName;
                    
                    if (!empty($notes)) {
                        $assignmentNote .= ' - ' . $notes;
                    }
                    
                    if (!empty($currentResolution)) {
                        $newResolution = $currentResolution . "\n\n" . $assignmentNote;
                    } else {
                        $newResolution = $assignmentNote;
                    }
                    
                    $resolutionStmt = $conn->prepare("UPDATE maintenance_requests SET resolution = ? WHERE id = ?");
                    if ($resolutionStmt === false) {
                        $error = "Error preparing resolution update: " . $conn->error;
                    } else {
                        $resolutionStmt->bind_param("si", $newResolution, $requestId);
                        $resolutionStmt->execute();
                        $resolutionStmt->close();
                    }
                    
                    $message = "Đã chỉ định nhân viên thành công.";
                    
                    // Làm mới trang để hiển thị cập nhật
                    redirect("/LTW/views/maintenance/view.php?id=$requestId");
                } else {
                    $error = "Không thể chỉ định nhân viên: " . $stmt->error;
                }
                $stmt->close();
            }
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
    case 'completed':
        $statusBadgeClass = 'bg-success';
        $statusText = 'Hoàn thành';
        break;
    case 'rejected':
        $statusBadgeClass = 'bg-danger';
        $statusText = 'Từ chối';
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
                <li class="breadcrumb-item"><a href="/LTW/views/maintenance/list.php">Yêu cầu Bảo trì</a></li>
                <li class="breadcrumb-item active" aria-current="page">Yêu cầu #<?php echo sprintf('%06d', $request['id']); ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="/LTW/views/maintenance/list.php" class="btn btn-secondary">
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
                                <td><?php echo 'Phòng ' . $request['room_number'] . ' (Tầng ' . $request['floor'] . ')'; ?></td>
                            </tr>
                            <tr>
                                <th>Ngày Báo cáo:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Người Báo cáo:</th>
                                <td>
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
                        <?php if ($canEdit): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Tác vụ Quản trị</h6>
                            <hr>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                                    <i class="fas fa-sync-alt me-2"></i> Cập nhật Trạng thái
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#priorityModal">
                                    <i class="fas fa-flag me-2"></i> Thay đổi Mức độ ưu tiên
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
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

        <!-- Bình luận -->       <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Bình luận & Cập nhật</h6>
            </div>
            <div class="card-body">
                <div class="comments mb-4">
                    <?php if (empty($request['resolution'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Chưa có bình luận nào.
                        </div>
                    <?php else: ?>
                        <div class="comment-timeline p-3 bg-light rounded">
                            <pre class="m-0" style="white-space: pre-wrap; font-family: inherit;"><?php echo htmlspecialchars($request['resolution']); ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($canAddComment && $request['status'] !== 'rejected'): ?>
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
                <?php elseif ($request['status'] === 'rejected'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Yêu cầu này đã bị từ chối. Bình luận đã bị vô hiệu hóa.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
      <!-- Thanh bên -->
    <div class="col-lg-4">
        <!-- Thông tin hệ thống -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Thông tin Hệ thống</h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <!-- Sự kiện tạo yêu cầu -->
                    <div class="timeline-item">
                        <div class="timeline-item-marker"></div>
                        <div class="timeline-item-content">
                            <h6>Đã Tạo Yêu cầu</h6>
                            <p>
                                Yêu cầu được gửi bởi <?php echo $request['reporter_name']; ?>
                            </p>
                            <span class="timeline-item-date">
                                <?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Xử lý trạng thái hiện tại -->
                    <div class="timeline-item">
                        <div class="timeline-item-marker"></div>
                        <div class="timeline-item-content">
                            <h6>Trạng thái Hiện tại</h6>
                            <p>
                                <span class="badge <?php echo $statusBadgeClass; ?>"><?php echo $statusText; ?></span>
                            </p>
                            <p>
                                Mức độ ưu tiên: <span class="badge <?php echo $priorityBadgeClass; ?>"><?php echo ucfirst($request['priority']); ?></span>
                            </p>
                            <?php if ($request['assigned_to']): ?>
                            <p>
                                Nhân viên phụ trách: <?php echo $request['assignee_name']; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($request['updated_at'] && $request['updated_at'] !== $request['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-item-marker"></div>
                        <div class="timeline-item-content">
                            <h6>Cập nhật Gần nhất</h6>
                            <p>
                                Yêu cầu được cập nhật lần cuối
                            </p>
                            <span class="timeline-item-date">
                                <?php echo date('d/m/Y H:i', strtotime($request['updated_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['completed_date']): ?>
                    <div class="timeline-item">
                        <div class="timeline-item-marker"></div>
                        <div class="timeline-item-content">
                            <h6>Đã Hoàn thành</h6>
                            <p>
                                Yêu cầu đã được xử lý và hoàn thành
                            </p>
                            <span class="timeline-item-date">
                                <?php echo date('d/m/Y', strtotime($request['completed_date'])); ?>
                            </span>
                        </div>
                    </div>                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Lịch sử Hoạt động (Comment) -->
        <?php if (!empty($request['resolution'])): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lịch sử Hoạt động</h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-item-marker">
                            <i class="fas fa-comment"></i>
                        </div>
                        <div class="timeline-item-content">
                            <h6>Nhật ký cập nhật</h6>
                            <div class="p-2 bg-light rounded">
                                <pre style="white-space: pre-wrap; font-family: inherit; margin-bottom: 0;"><?php echo htmlspecialchars($request['resolution']); ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>        <?php endif; ?>
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
                            <a href="/LTW/views/maintenance/view.php?id=<?php echo $related['id']; ?>" class="list-group-item list-group-item-action">
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
                            <a href="/LTW/views/maintenance/list.php?room_id=<?php echo $request['room_id']; ?>" class="btn btn-sm btn-outline-primary">
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
                        <label for="priority_notes" class="form-label">Lý do Thay đổi (Không bắt buộc)</label>
                        <textarea class="form-control" id="priority_notes" name="notes" rows="3"></textarea>
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

<!-- Hộp thoại Tải lên Ảnh -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_photo">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPhotoModalLabel">Tải lên Ảnh</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="photo" class="form-label">Chọn Ảnh</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*" required>
                        <div class="form-text">
                            Kích thước tệp tối đa: 5MB. Định dạng cho phép: JPG, JPEG, PNG, GIF
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="caption" class="form-label">Chú thích (Không bắt buộc)</label>
                        <input type="text" class="form-control" id="caption" name="caption" maxlength="255">
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

<style>
/* Kiểu dáng dòng thời gian */
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline-item {
    position: relative;
    padding-left: 40px;
    margin-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    height: 100%;
    width: 2px;
    background-color: #e3e6f0;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-item-marker {
    position: absolute;
    left: 0;
    top: 0;
    height: 30px;
    width: 30px;
    border-radius: 50%;
    background-color: #4e73df;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.8rem;
    z-index: 2;
}

.timeline-item-content {
    padding: 10px 15px;
    border-radius: 0.35rem;
    background-color: #f8f9fc;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.timeline-item-date {
    display: block;
    font-size: 0.8rem;
    color: #858796;
    margin-top: 5px;
}

/* Kiểu dáng bình luận */
.comments {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.comment-item {
    margin-bottom: 15px;
    padding: 15px;
    border-radius: 0.35rem;
}

.student-comment {
    background-color: #f8f9fc;
    border-left: 4px solid #4e73df;
}

.staff-comment {
    background-color: #eaf6ff;
    border-left: 4px solid #36b9cc;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.comment-time {
    font-size: 0.8rem;
}

.comment-body {
    font-size: 0.95rem;
}

/* Dòng thời gian trạng thái */
.status-timeline {
    padding: 20px 0;
    position: relative;
}

.timeline-line {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background-color: #e3e6f0;
    transform: translateY(-50%);
}

.timeline-point {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
}

.timeline-icon {
    width: 25px;
    height: 25px;
    border-radius: 50%;
    background-color: #e3e6f0;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.7rem;
}

.timeline-point.active .timeline-icon {
    background-color: #1cc88a;
}

.timeline-label {
    font-size: 0.75rem;
    text-align: center;
    color: #858796;
}

.timeline-point.active .timeline-label {
    color: #5a5c69;
    font-weight: bold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chức năng lightbox ảnh có thể được thêm vào đây
    
    // Xử lý thay đổi trạng thái - hiển thị/ẩn tùy chọn dựa trên trạng thái hiện tại
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            // Có thể thêm logic bổ sung tại đây
        });
    }
});
</script>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>