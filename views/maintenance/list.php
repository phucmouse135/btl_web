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
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($currentPage - 1) * $limit;
$totalRequests = 0;

// Xây dựng truy vấn dựa trên vai trò người dùng và bộ lọc
$countQuery = "SELECT COUNT(*) as total FROM maintenance_requests mr
               JOIN rooms r ON mr.room_id = r.id
               LEFT JOIN users u ON mr.reported_by = u.id";
               
$query = "SELECT mr.*, r.room_number, r.building_name, 
          CONCAT(u.first_name, ' ', u.last_name) as reported_by_name,
          (CASE WHEN mr.assigned_to IS NOT NULL THEN
            (SELECT CONCAT(u_staff.first_name, ' ', u_staff.last_name) 
             FROM users u_staff 
             WHERE u_staff.id = mr.assigned_to)
           ELSE NULL END) as assigned_to_name
          FROM maintenance_requests mr
          JOIN rooms r ON mr.room_id = r.id
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
    $whereClause[] = "(mr.assigned_to = ? OR mr.assigned_to IS NULL)";
    array_push($params, $_SESSION['user_id']);
    $types .= "i";
}

// Bộ lọc tìm kiếm
if (!empty($search)) {
    $whereClause[] = "(mr.id LIKE ? OR r.room_number LIKE ? OR r.building_name LIKE ? OR mr.description LIKE ?)";
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

// Áp dụng mệnh đề WHERE nếu cần
if (!empty($whereClause)) {
    $query .= " WHERE " . implode(" AND ", $whereClause);
    $countQuery .= " WHERE " . implode(" AND ", $whereClause);
}

$query .= " ORDER BY CASE mr.priority 
                      WHEN 'emergency' THEN 1 
                      WHEN 'high' THEN 2 
                      WHEN 'medium' THEN 3 
                      WHEN 'low' THEN 4 
                      ELSE 5 
                    END, 
                    CASE mr.status 
                      WHEN 'pending' THEN 1 
                      WHEN 'in_progress' THEN 2 
                      WHEN 'completed' THEN 3 
                      WHEN 'rejected' THEN 4
                      ELSE 5 
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
    if (!empty($params)) {
        $queryStmt->bind_param($types, ...$params);
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
    $issueTypes = ['Plumbing', 'Electrical', 'Furniture', 'HVAC', 'Other']; // Các loại mặc định
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
            // Chỉ cho phép hủy nếu yêu cầu đang chờ xử lý
            if ($requestData['status'] == 'pending') {
                $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'rejected' WHERE id = ? AND reported_by = ?");
                $updateStmt->bind_param("ii", $id, $_SESSION['user_id']);
                
                if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                    $message = "Yêu cầu bảo trì #" . sprintf('%06d', $id) . " đã được hủy bỏ.";
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
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'in_progress', assigned_to = ? WHERE id = ?");
                    $updateStmt->bind_param("ii", $_SESSION['user_id'], $id);
                    break;
                    
                case 'complete':
                    $updateStmt = $conn->prepare("
                        UPDATE maintenance_requests 
                        SET status = 'completed', completed_date = CURRENT_DATE()
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("i", $id);
                    break;
                    
                case 'reject':
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'rejected' WHERE id = ?");
                    $updateStmt->bind_param("i", $id);
                    break;
                    
                case 'reopen':
                    $updateStmt = $conn->prepare("
                        UPDATE maintenance_requests 
                        SET status = 'pending', completed_date = NULL
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("i", $id);
                    break;
            }
            
            if (isset($updateStmt)) {
                if ($updateStmt->execute()) {
                    $message = "Yêu cầu bảo trì #" . sprintf('%06d', $id) . " đã được cập nhật thành " . str_replace('_', ' ', $action) . ".";
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
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Bộ lọc và Tìm kiếm -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Bộ lọc</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Tìm kiếm yêu cầu..." name="search" value="<?php echo $search; ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                    <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>Đang xử lý</option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Đã hủy</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="priority" onchange="this.form.submit()">
                    <option value="all" <?php echo $priority == 'all' ? 'selected' : ''; ?>>Tất cả độ ưu tiên</option>
                    <option value="low" <?php echo $priority == 'low' ? 'selected' : ''; ?>>Thấp</option>
                    <option value="medium" <?php echo $priority == 'medium' ? 'selected' : ''; ?>>Bình thường</option>
                    <option value="high" <?php echo $priority == 'high' ? 'selected' : ''; ?>>Cao</option>
                    <option value="emergency" <?php echo $priority == 'emergency' ? 'selected' : ''; ?>>Khẩn cấp</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="issue_type" onchange="this.form.submit()">
                    <option value="all" <?php echo $issueType == 'all' ? 'selected' : ''; ?>>Tất cả loại sự cố</option>
                    <?php foreach ($issueTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $issueType == $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Áp dụng</button>
            </div>
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
                                case 'completed':
                                    $statusBadgeClass = 'bg-success';
                                    $statusText = 'Hoàn thành';
                                    break;
                                case 'rejected':
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
                                case 'medium':
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
                                    <?php echo ucfirst($req['issue_type']); ?>
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
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo $isAdminView ? '/LTW/views/admin/maintenance/view.php?id=' . $req['id'] : '/LTW/views/maintenance/view.php?id=' . $req['id']; ?>" class="btn btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasRole('admin') || hasRole('staff')): ?>
                                            <?php if ($req['status'] == 'pending'): ?>
                                                <a href="?action=in_progress&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-primary">
                                                    <i class="fas fa-play"></i> Bắt đầu
                                                </a>
                                                <a href="?action=reject&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Từ chối
                                                </a>
                                            <?php elseif ($req['status'] == 'in_progress'): ?>
                                                <a href="?action=complete&id=<?php echo $req['id']; ?>&page=<?php echo $currentPage; ?><?php echo isset($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, array_flip(['action', 'id', 'page']))) : ''; ?>" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Hoàn thành
                                                </a>
                                            <?php elseif ($req['status'] == 'completed' || $req['status'] == 'rejected'): ?>
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
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&priority=<?php echo $priority; ?>&issue_type=<?php echo $issueType; ?>">
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
                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&status=' . $status . '&priority=' . $priority . '&issue_type=' . $issueType . '">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $currentPage) {
                                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . $status . '&priority=' . $priority . '&issue_type=' . $issueType . '">' . $i . '</a></li>';
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . $status . '&priority=' . $priority . '&issue_type=' . $issueType . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&priority=<?php echo $priority; ?>&issue_type=<?php echo $issueType; ?>">
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

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>