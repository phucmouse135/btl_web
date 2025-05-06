<?php
// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu đăng nhập
requireLogin();

// Kiểm tra xem file này được gọi từ giao diện admin hay không
$isAdminView = isset($_SESSION['is_admin_maintenance_list']) && $_SESSION['is_admin_maintenance_list'] === true;

// Dọn dẹp biến phiên
if ($isAdminView) {
    unset($_SESSION['is_admin_maintenance_list']);
}

// Khởi tạo biến
$requests = [];
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$priority = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : 'all';
$issueType = isset($_GET['issue_type']) ? sanitizeInput($_GET['issue_type']) : 'all';
$building = isset($_GET['building']) ? sanitizeInput($_GET['building']) : 'all';
$assignedTo = isset($_GET['assigned_to']) ? sanitizeInput($_GET['assigned_to']) : 'all';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($currentPage - 1) * $limit;
$totalRequests = 0;

// Xây dựng truy vấn dựa trên vai trò người dùng và bộ lọc
$countQuery = "SELECT COUNT(*) as total FROM maintenance_requests mr
               JOIN rooms r ON mr.room_id = r.id
               JOIN buildings b ON r.building_id = b.id
               LEFT JOIN users u ON mr.reported_by = u.id";
               
$query = "SELECT mr.*, r.room_number, b.name as building_name, 
          CONCAT(u.first_name, ' ', u.last_name) as reported_by_name,
          (SELECT COUNT(*) FROM maintenance_photos WHERE request_id = mr.id) as photo_count,
          (CASE WHEN mr.assigned_to IS NOT NULL THEN
            (SELECT CONCAT(u_staff.first_name, ' ', u_staff.last_name) 
             FROM users u_staff 
             JOIN staff s ON u_staff.id = s.user_id
             WHERE s.id = mr.assigned_to)
           ELSE NULL END) as assigned_to_name
          FROM maintenance_requests mr
          JOIN rooms r ON mr.room_id = r.id
          JOIN buildings b ON r.building_id = b.id
          LEFT JOIN users u ON mr.reported_by = u.id";
          
$whereClause = [];
$params = [];
$types = "";

// Lọc theo vai trò nếu không phải ở giao diện admin
if (!$isAdminView && hasRole('student')) {
    $whereClause[] = "mr.reported_by = ?";
    array_push($params, $_SESSION['user_id']);
    $types .= "i";
} elseif (!$isAdminView && hasRole('staff') && !hasRole('admin')) {
    // Nếu giao diện sinh viên và người dùng là nhân viên không phải admin, hiển thị các yêu cầu được phân công
    $staffIdQuery = "SELECT id FROM staff WHERE user_id = ?";
    $staffStmt = $conn->prepare($staffIdQuery);
    $staffStmt->bind_param("i", $_SESSION['user_id']);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();
    
    if ($staffResult->num_rows > 0) {
        $staffData = $staffResult->fetch_assoc();
        $staffId = $staffData['id'];
        $whereClause[] = "(mr.assigned_to = ? OR mr.assigned_to IS NULL)";
        array_push($params, $staffId);
        $types .= "i";
    }
    $staffStmt->close();
}

// Bộ lọc tìm kiếm
if (!empty($search)) {
    $whereClause[] = "(mr.id LIKE ? OR r.room_number LIKE ? OR b.name LIKE ? OR mr.description LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
    $types .= "ssss";
}

// Bộ lọc trạng thái
if ($status != 'all') {
    $whereClause[] = "mr.status = ?";
    array_push($params, $status);
    $types .= "s";
}

// Bộ lọc mức độ ưu tiên
if ($priority != 'all') {
    $whereClause[] = "mr.priority = ?";
    array_push($params, $priority);
    $types .= "s";
}

// Bộ lọc loại sự cố
if ($issueType != 'all') {
    $whereClause[] = "mr.issue_type = ?";
    array_push($params, $issueType);
    $types .= "s";
}

// Bộ lọc tòa nhà (chỉ cho giao diện admin)
if ($isAdminView && $building != 'all') {
    $whereClause[] = "b.id = ?";
    array_push($params, $building);
    $types .= "i";
}

// Bộ lọc theo nhân viên được phân công (chỉ cho giao diện admin)
if ($isAdminView && $assignedTo != 'all') {
    if ($assignedTo === 'unassigned') {
        $whereClause[] = "mr.assigned_to IS NULL";
    } else {
        $whereClause[] = "mr.assigned_to = ?";
        array_push($params, $assignedTo);
        $types .= "i";
    }
}

// Áp dụng mệnh đề WHERE nếu cần
if (!empty($whereClause)) {
    $query .= " WHERE " . implode(" AND ", $whereClause);
    $countQuery .= " WHERE " . implode(" AND ", $whereClause);
}

$query .= " ORDER BY CASE mr.priority 
                      WHEN 'emergency' THEN 1 
                      WHEN 'high' THEN 2 
                      WHEN 'normal' THEN 3 
                      WHEN 'low' THEN 4 
                      ELSE 5 
                    END, 
                    CASE mr.status 
                      WHEN 'pending' THEN 1 
                      WHEN 'in_progress' THEN 2 
                      WHEN 'scheduled' THEN 3 
                      WHEN 'completed' THEN 4 
                      WHEN 'canceled' THEN 5 
                      ELSE 6 
                    END,
                    mr.request_date DESC
                    LIMIT ? OFFSET ?";
array_push($params, $limit, $offset);
$types .= "ii";

// Lấy tổng số yêu cầu
$countStmt = $conn->prepare($countQuery);
if ($countStmt) {
    if (!empty($whereClause)) {
        // Chỉ sử dụng tham số mệnh đề WHERE cho truy vấn đếm (không bao gồm LIMIT/OFFSET)
        $countParams = array_slice($params, 0, count($params) - 2);
        $countTypes = substr($types, 0, -2); // Loại bỏ 'ii' cho LIMIT và OFFSET
        
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $totalRequests = $row['total'];
    } else {
        error_log("Lỗi SQL trong truy vấn đếm danh sách bảo trì: " . $conn->error);
        $totalRequests = 0;
    }
    
    $countStmt->close();
} else {
    error_log("Không thể chuẩn bị truy vấn đếm: " . $conn->error);
    $totalRequests = 0;
}
$totalPages = ceil($totalRequests / $limit);

// Lấy danh sách yêu cầu bảo trì
$queryStmt = $conn->prepare($query);
if ($queryStmt) {
    // Chúng ta cần xử lý tham số LIMIT và OFFSET một cách chính xác
    if (!empty($whereClause)) {
        // Nếu có tham số mệnh đề WHERE, sử dụng mảng tham số đầy đủ
        $queryStmt->bind_param($types, ...$params);
    } else {
        // Nếu không có mệnh đề WHERE, nhưng vẫn cần gắn LIMIT và OFFSET
        $queryStmt->bind_param("ii", $limit, $offset);
    }
    
    $queryStmt->execute();
    $queryResult = $queryStmt->get_result();
    
    if ($queryResult) {
        while ($row = $queryResult->fetch_assoc()) {
            $requests[] = $row;
        }
    } else {
        error_log("Lỗi SQL trong truy vấn danh sách bảo trì: " . $conn->error);
    }
    $queryStmt->close();
} else {
    error_log("Không thể chuẩn bị truy vấn: " . $conn->error . " - Truy vấn: " . $query);
}

// Lấy các loại sự cố duy nhất cho bộ lọc
$issueTypes = [];
$issueTypeQuery = "SELECT DISTINCT issue_type FROM maintenance_requests ORDER BY issue_type";
$issueTypeResult = $conn->query($issueTypeQuery);

// Bảo vệ với kiểm tra nghiêm ngặt hơn
if ($issueTypeResult && !is_bool($issueTypeResult)) {
    while ($row = $issueTypeResult->fetch_assoc()) {
        $issueTypes[] = $row['issue_type'];
    }
} else {
    error_log("Lỗi SQL trong truy vấn lấy loại sự cố: " . $conn->error);
    // Đảm bảo $issueTypes không rỗng nếu truy vấn thất bại
    $issueTypes = ['plumbing', 'electrical', 'furniture', 'hvac', 'other']; // Các loại mặc định
}

// Lấy danh sách tòa nhà cho bộ lọc
$buildings = [];
if ($isAdminView) {
    $buildingQuery = "SELECT id, name FROM buildings ORDER BY name";
    $buildingResult = $conn->query($buildingQuery);
    
    // Bảo vệ với kiểm tra nghiêm ngặt hơn
    if ($buildingResult && !is_bool($buildingResult)) {
        while ($row = $buildingResult->fetch_assoc()) {
            $buildings[] = $row;
        }
    } else {
        error_log("Lỗi SQL trong truy vấn lấy danh sách tòa nhà: " . $conn->error);
    }
}

// Lấy danh sách nhân viên cho bộ lọc
$staff = [];
if ($isAdminView) {
    $staffQuery = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name 
                   FROM staff s 
                   JOIN users u ON s.user_id = u.id
                   WHERE u.status = 'active'
                   ORDER BY s.last_name, s.first_name";
    $staffResult = $conn->query($staffQuery);
    
    // Bảo vệ với kiểm tra nghiêm ngặt hơn
    if ($staffResult && !is_bool($staffResult)) {
        while ($row = $staffResult->fetch_assoc()) {
            $staff[] = $row;
        }
    } else {
        error_log("Lỗi SQL trong truy vấn lấy danh sách nhân viên: " . $conn->error);
    }
}

// Xử lý các hành động
$message = '';
$error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    // Xác minh yêu cầu tồn tại
    $checkStmt = $conn->prepare("SELECT id, status FROM maintenance_requests WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $requestData = $checkResult->fetch_assoc();
        
        if ($action === 'cancel' && hasRole('student')) {
            // Chỉ cho phép hủy nếu yêu cầu đang chờ xử lý hoặc đã lên lịch
            if (in_array($requestData['status'], ['pending', 'scheduled'])) {
                $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'canceled' WHERE id = ? AND reported_by = ?");
                $updateStmt->bind_param("ii", $id, $_SESSION['user_id']);
                
                if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                    $message = "Yêu cầu bảo trì #" . sprintf('%06d', $id) . " đã được hủy bỏ.";
                    logActivity('maintenance_request', "Đã hủy yêu cầu bảo trì #" . sprintf('%06d', $id));
                } else {
                    $error = "Không thể hủy yêu cầu. Bạn chỉ có thể hủy yêu cầu của chính mình.";
                }
                
                $updateStmt->close();
            } else {
                $error = "Không thể hủy yêu cầu này vì nó đã ở trạng thái " . $requestData['status'] . ".";
            }
        } elseif (hasRole('admin') || hasRole('staff')) {
            // Hành động của nhân viên
            switch ($action) {
                case 'in_progress':
                    // Lấy staff_id của người dùng hiện tại
                    $staffIdQuery = "SELECT id FROM staff WHERE user_id = ?";
                    $staffStmt = $conn->prepare($staffIdQuery);
                    $staffStmt->bind_param("i", $_SESSION['user_id']);
                    $staffStmt->execute();
                    $staffResult = $staffStmt->get_result();
                    $staffId = null;
                    
                    if ($staffResult->num_rows > 0) {
                        $staffData = $staffResult->fetch_assoc();
                        $staffId = $staffData['id'];
                    }
                    $staffStmt->close();
                    
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'in_progress', assigned_to = ? WHERE id = ?");
                    $updateStmt->bind_param("ii", $staffId, $id);
                    break;
                    
                case 'complete':
                    $updateStmt = $conn->prepare("
                        UPDATE maintenance_requests 
                        SET status = 'completed', completed_date = CURRENT_DATE(), completed_by = ? 
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("ii", $_SESSION['user_id'], $id);
                    break;
                    
                case 'schedule':
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'scheduled' WHERE id = ?");
                    $updateStmt->bind_param("i", $id);
                    break;
                    
                case 'reopen':
                    $updateStmt = $conn->prepare("
                        UPDATE maintenance_requests 
                        SET status = 'pending', completed_date = NULL, completed_by = NULL 
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("i", $id);
                    break;
            }
            
            if (isset($updateStmt)) {
                if ($updateStmt->execute()) {
                    $message = "Yêu cầu bảo trì #" . sprintf('%06d', $id) . " đã được cập nhật thành " . str_replace('_', ' ', $action) . ".";
                    logActivity('maintenance_request', "Đã cập nhật yêu cầu bảo trì #" . sprintf('%06d', $id) . " thành " . str_replace('_', ' ', $action));
                    
                    // Thêm thông báo cho sinh viên
                    // Lấy user_id của sinh viên đã báo cáo yêu cầu
                    $getReporterQuery = "SELECT reported_by FROM maintenance_requests WHERE id = ?";
                    $reporterStmt = $conn->prepare($getReporterQuery);
                    $reporterStmt->bind_param("i", $id);
                    $reporterStmt->execute();
                    $reporterResult = $reporterStmt->get_result();
                    
                    if ($reporterResult->num_rows > 0) {
                        $reporterData = $reporterResult->fetch_assoc();
                        $reporterId = $reporterData['reported_by'];
                        
                        // Tạo thông báo cho sinh viên
                        $notificationTitle = "Cập nhật yêu cầu bảo trì #" . sprintf('%06d', $id);
                        $notificationContent = "Yêu cầu bảo trì của bạn đã được cập nhật thành: " . ucfirst(str_replace('_', ' ', $action));
                        
                        createNotification(
                            $reporterId,
                            'maintenance_update',
                            $notificationTitle,
                            $notificationContent,
                            "/LTW/views/maintenance/view.php?id=$id"
                        );
                    }
                    $reporterStmt->close();
                } else {
                    $error = "Không thể cập nhật trạng thái yêu cầu.";
                }
                
                $updateStmt->close();
            }
        }
    } else {
        $error = "Không tìm thấy yêu cầu.";
    }
    $checkStmt->close();
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <?php echo $isAdminView ? 'Quản lý Yêu Cầu Bảo Trì' : 'Yêu Cầu Bảo Trì'; ?>
    </h1>
    <div>
        <?php if ($isAdminView): ?>
            <a href="/LTW/dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        <?php endif; ?>
        <a href="<?php echo $isAdminView ? '/LTW/views/admin/maintenance/add.php' : '/LTW/views/maintenance/add.php'; ?>" class="btn btn-primary">
            <i class="fas fa-plus-circle me-2"></i> <?php echo $isAdminView ? 'Tạo Yêu Cầu Mới' : 'Gửi Yêu Cầu Mới'; ?>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <?php echo displaySuccess($message); ?>
<?php endif; ?>

<?php if ($error): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<!-- Bộ lọc và Tìm kiếm -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Bộ lọc</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="<?php echo $isAdminView ? 'col-md-3' : 'col-md-4'; ?>">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Tìm kiếm yêu cầu..." name="search" value="<?php echo $search; ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="<?php echo $isAdminView ? 'col-md-2' : 'col-md-2'; ?>">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                    <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>Đang xử lý</option>
                    <option value="scheduled" <?php echo $status == 'scheduled' ? 'selected' : ''; ?>>Đã lên lịch</option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                    <option value="canceled" <?php echo $status == 'canceled' ? 'selected' : ''; ?>>Đã hủy</option>
                </select>
            </div>
            <div class="<?php echo $isAdminView ? 'col-md-2' : 'col-md-2'; ?>">
                <select class="form-select" name="priority" onchange="this.form.submit()">
                    <option value="all" <?php echo $priority == 'all' ? 'selected' : ''; ?>>Tất cả độ ưu tiên</option>
                    <option value="low" <?php echo $priority == 'low' ? 'selected' : ''; ?>>Thấp</option>
                    <option value="normal" <?php echo $priority == 'normal' ? 'selected' : ''; ?>>Bình thường</option>
                    <option value="high" <?php echo $priority == 'high' ? 'selected' : ''; ?>>Cao</option>
                    <option value="emergency" <?php echo $priority == 'emergency' ? 'selected' : ''; ?>>Khẩn cấp</option>
                </select>
            </div>
            <div class="<?php echo $isAdminView ? 'col-md-2' : 'col-md-2'; ?>">
                <select class="form-select" name="issue_type" onchange="this.form.submit()">
                    <option value="all" <?php echo $issueType == 'all' ? 'selected' : ''; ?>>Tất cả loại sự cố</option>
                    <?php foreach ($issueTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $issueType == $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($isAdminView): ?>
            <!-- Bộ lọc bổ sung cho giao diện Admin -->
            <div class="col-md-2">
                <select class="form-select" name="building" onchange="this.form.submit()">
                    <option value="all" <?php echo $building == 'all' ? 'selected' : ''; ?>>Tất cả tòa nhà</option>
                    <?php foreach ($buildings as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $building == $b['id'] ? 'selected' : ''; ?>>
                            <?php echo $b['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="assigned_to" onchange="this.form.submit()">
                    <option value="all" <?php echo $assignedTo == 'all' ? 'selected' : ''; ?>>Tất cả nhân viên</option>
                    <option value="unassigned" <?php echo $assignedTo == 'unassigned' ? 'selected' : ''; ?>>Chưa phân công</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $assignedTo == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo $s['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Áp dụng</button>
            </div>
            <?php endif; ?>
            
            <?php if ($isAdminView): ?>
            <div class="col-md-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i> Áp dụng Bộ lọc
                </button>
                <a href="<?php echo $isAdminView ? '/LTW/views/admin/maintenance/list.php' : '/LTW/views/maintenance/list.php'; ?>" class="btn btn-secondary ms-2">
                    <i class="fas fa-sync-alt me-2"></i> Xóa Bộ lọc
                </a>
                
                <?php if (hasRole('admin')): ?>
                <a href="/LTW/exports/export_maintenance.php" class="btn btn-success float-end">
                    <i class="fas fa-file-excel me-2"></i> Xuất Excel
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Danh sách Yêu cầu Bảo trì -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Yêu cầu Bảo trì</h6>
        <div>
            <span class="badge bg-primary rounded-pill"><?php echo $totalRequests; ?> yêu cầu</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Không tìm thấy yêu cầu bảo trì nào phù hợp với tiêu chí của bạn.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã số</th>
                            <th>Phòng</th>
                            <th>Loại sự cố</th>
                            <th>Ưu tiên</th>
                            <th>Trạng thái</th>
                            <?php if ($isAdminView): ?>
                            <th>Người tạo</th>
                            <th>Phân công cho</th>
                            <?php endif; ?>
                            <th>Ngày</th>
                            <th>Ảnh</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <?php
                            // Xác định kiểu dáng hàng dựa trên mức độ ưu tiên
                            $rowClass = '';
                            if ($req['priority'] == 'emergency') {
                                $rowClass = 'table-danger';
                            } elseif ($req['priority'] == 'high') {
                                $rowClass = 'table-warning';
                            }
                            
                            // Xác định huy hiệu trạng thái
                            $statusBadgeClass = 'bg-secondary';
                            $statusText = ucfirst($req['status']);
                            
                            switch ($req['status']) {
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
                            
                            // Xác định huy hiệu ưu tiên
                            $priorityBadgeClass = 'bg-secondary';
                            $priorityText = ucfirst($req['priority']);
                            
                            switch ($req['priority']) {
                                case 'low':
                                    $priorityBadgeClass = 'bg-success';
                                    $priorityText = 'Thấp';
                                    break;
                                case 'normal':
                                    $priorityBadgeClass = 'bg-primary';
                                    $priorityText = 'Bình thường';
                                    break;
                                case 'high':
                                    $priorityBadgeClass = 'bg-warning text-dark';
                                    $priorityText = 'Cao';
                                    break;
                                case 'emergency':
                                    $priorityBadgeClass = 'bg-danger';
                                    $priorityText = 'Khẩn cấp';
                                    break;
                            }
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <a href="<?php echo $isAdminView ? '/LTW/views/admin/maintenance/view.php?id=' . $req['id'] : '/LTW/views/maintenance/view.php?id=' . $req['id']; ?>">
                                        #<?php echo sprintf('%06d', $req['id']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $req['building_name'] . ' - ' . $req['room_number']; ?>
                                </td>
                                <td>
                                    <?php echo ucfirst(str_replace('_', ' ', $req['issue_type'])); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $priorityBadgeClass; ?>">
                                        <?php echo $priorityText; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $statusBadgeClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <?php if ($isAdminView): ?>
                                <td>
                                    <?php echo !empty($req['reported_by_name']) ? $req['reported_by_name'] : 'N/A'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($req['assigned_to_name'])): ?>
                                        <?php echo $req['assigned_to_name']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Chưa phân công</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($req['request_date'])); ?>
                                </td>
                                <td>
                                    <?php if ($req['photo_count'] > 0): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-image me-1"></i> <?php echo $req['photo_count']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Không có</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo $isAdminView ? '/LTW/views/admin/maintenance/view.php?id=' . $req['id'] : '/LTW/views/maintenance/view.php?id=' . $req['id']; ?>" class="btn btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasRole('admin') || hasRole('staff')): ?>
                                            <?php if ($req['status'] == 'pending'): ?>
                                                <a href="?action=in_progress&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-primary">
                                                    <i class="fas fa-play"></i> Bắt đầu
                                                </a>
                                                <a href="?action=schedule&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-secondary">
                                                    <i class="fas fa-calendar"></i> Lên lịch
                                                </a>
                                            <?php elseif ($req['status'] == 'in_progress'): ?>
                                                <a href="?action=complete&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Hoàn thành
                                                </a>
                                            <?php elseif ($req['status'] == 'scheduled'): ?>
                                                <a href="?action=in_progress&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-primary">
                                                    <i class="fas fa-play"></i> Bắt đầu
                                                </a>
                                            <?php elseif ($req['status'] == 'completed'): ?>
                                                <a href="?action=reopen&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-warning">
                                                    <i class="fas fa-redo"></i> Mở lại
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif (hasRole('student') && $req['status'] == 'pending'): ?>
                                            <a href="?action=cancel&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn hủy yêu cầu này không?')">
                                                <i class="fas fa-times"></i> Hủy
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Phân trang -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&priority=<?php echo $priority; ?>&issue_type=<?php echo $issueType; ?><?php echo $isAdminView ? '&building=' . $building . '&assigned_to=' . $assignedTo : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&status=' . $status . '&priority=' . $priority . '&issue_type=' . $issueType . ($isAdminView ? '&building=' . $building . '&assigned_to=' . $assignedTo : '') . '">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $currentPage) {
                                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . $status . '&priority=' . $priority . '&issue_type=' . $issueType . ($isAdminView ? '&building=' . $building . '&assigned_to=' . $assignedTo : '') . '">' . $i . '</a></li>';
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . $status . '&priority=' . $priority . '&issue_type=' . $issueType . ($isAdminView ? '&building=' . $building . '&assigned_to=' . $assignedTo : '') . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&priority=<?php echo $priority; ?>&issue_type=<?php echo $issueType; ?><?php echo $isAdminView ? '&building=' . $building . '&assigned_to=' . $assignedTo : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tùy chọn: Thêm chức năng JavaScript nếu cần
});
</script>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>