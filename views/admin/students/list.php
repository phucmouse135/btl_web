<?php
// Bao gồm header
require_once '../../../includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Xử lý xóa sinh viên
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Kiểm tra xem sinh viên có tồn tại không
    $checkSql = "SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'student'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $student = $checkResult->fetch_assoc();
        
        try {
            // Xóa tài khoản người dùng (bao gồm cả thông tin sinh viên)
            $deleteUserSql = "DELETE FROM users WHERE id = ?";
            $deleteUserStmt = $conn->prepare($deleteUserSql);
            $deleteUserStmt->bind_param("i", $id);
            $deleteUserStmt->execute();
            
            // Thông báo thành công
            $success = "Sinh viên " . $student['first_name'] . " " . $student['last_name'] . " đã được xóa thành công";
            
            // Ghi nhật ký hoạt động
            logActivity('delete_student', 'Đã xóa sinh viên: ' . $student['first_name'] . ' ' . $student['last_name']);
        } catch (Exception $e) {
            $error = "Lỗi khi xóa sinh viên: " . $e->getMessage();
        }
    } else {
        $error = "Không tìm thấy sinh viên";
    }
}

// Lấy tham số tìm kiếm
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';

// Phân trang
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Xây dựng truy vấn SQL - bây giờ chỉ cần truy vấn trong bảng users 
// với role='student' thay vì phải kết nối từ bảng students
$sql = "SELECT * FROM users WHERE role = 'student'";

// Thêm điều kiện tìm kiếm nếu tham số tìm kiếm tồn tại
if (!empty($search)) {
    $search = "%$search%";
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? 
            OR student_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
}

// Thêm điều kiện trạng thái nếu không phải 'all'
if ($status != 'all') {
    $sql .= " AND student_status = ?";
}

// Đếm tổng số bản ghi cho phân trang
$countSql = $sql;
$stmt = $conn->prepare($countSql);

// Gắn tham số cho truy vấn đếm
if (!empty($search) && $status != 'all') {
    $stmt->bind_param("ssssss", $search, $search, $search, $search, $search, $status);
} elseif (!empty($search)) {
    $stmt->bind_param("sssss", $search, $search, $search, $search, $search);
} elseif ($status != 'all') {
    $stmt->bind_param("s", $status);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalRecords = $countResult->num_rows;
$totalPages = ceil($totalRecords / $recordsPerPage);

// Thêm mệnh đề LIMIT cho phân trang
$sql .= " ORDER BY id DESC LIMIT ?, ?";

// Chuẩn bị câu lệnh cho truy vấn dữ liệu thực tế
$stmt = $conn->prepare($sql);

// Gắn tham số cho truy vấn dữ liệu
if (!empty($search) && $status != 'all') {
    $stmt->bind_param("sssssii", $search, $search, $search, $search, $search, $status, $offset, $recordsPerPage);
} elseif (!empty($search)) {
    $stmt->bind_param("sssssii", $search, $search, $search, $search, $search, $offset, $recordsPerPage);
} elseif ($status != 'all') {
    $stmt->bind_param("sii", $status, $offset, $recordsPerPage);
} else {
    $stmt->bind_param("ii", $offset, $recordsPerPage);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Quản lý sinh viên</h1>
    <a href="/LTW/views/admin/students/add.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> Thêm sinh viên mới
    </a>
</div>

<?php if (isset($success)): ?>
    <?php echo displaySuccess($success); ?>
<?php endif; ?>

<?php if (isset($error)): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<!-- Lọc và Tìm kiếm -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm và Lọc</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Tìm kiếm theo tên, mã số, điện thoại hoặc email" 
                               value="<?php echo $search; ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                        <option value="graduated" <?php echo $status == 'graduated' ? 'selected' : ''; ?>>Đã tốt nghiệp</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <a href="/LTW/views/admin/students/list.php" class="btn btn-secondary w-100">Đặt lại</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Danh sách sinh viên -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Danh sách sinh viên</h6>
        <div>
            <a href="/LTW/exports/export_students.php" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel me-1"></i> Xuất Excel
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Mã sinh viên</th>
                            <th>Tên</th>
                            <th>Giới tính</th>
                            <th>Liên hệ</th>
                            <th>Khoa</th>
                            <th>Năm</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo isset($student['student_id']) ? $student['student_id'] : 'N/A'; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo !empty($student['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $student['profile_pic']) 
                                            ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] 
                                            : '/LTW/assets/images/user.png'; ?>" 
                                             class="rounded-circle me-2" width="32" height="32">
                                        <?php echo (isset($student['first_name']) ? $student['first_name'] : '') . ' ' . (isset($student['last_name']) ? $student['last_name'] : ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $gender = isset($student['gender']) ? $student['gender'] : '';
                                        echo ucfirst($gender) == 'Male' ? 'Nam' : (ucfirst($gender) == 'Female' ? 'Nữ' : 'Khác'); 
                                    ?>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-phone me-1"></i> <?php echo isset($student['phone']) ? $student['phone'] : 'N/A'; ?><br>
                                        <i class="fas fa-envelope me-1"></i> <?php echo isset($student['email']) ? $student['email'] : 'N/A'; ?>
                                    </small>
                                </td>
                                <td><?php echo isset($student['department']) ? $student['department'] : 'N/A'; ?></td>
                                <td><?php echo isset($student['year_of_study']) ? $student['year_of_study'] : 'N/A'; ?></td>
                                <td>
                                    <?php 
                                        $studentStatus = isset($student['student_status']) ? $student['student_status'] : 'active';
                                        $statusClass = 'bg-success';
                                        $statusText = 'Hoạt động';
                                        if ($studentStatus == 'inactive') {
                                            $statusClass = 'bg-danger';
                                            $statusText = 'Không hoạt động';
                                        } else if ($studentStatus == 'graduated') {
                                            $statusClass = 'bg-info';
                                            $statusText = 'Đã tốt nghiệp';
                                        }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/LTW/views/admin/students/view.php?id=<?php echo $student['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Xem">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/LTW/views/admin/students/edit.php?id=<?php echo $student['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/LTW/views/admin/students/list.php?action=delete&id=<?php echo $student['id']; ?>" 
                                           class="btn btn-sm btn-danger btn-delete" 
                                           data-item-name="<?php echo (isset($student['first_name']) ? $student['first_name'] : '') . ' ' . (isset($student['last_name']) ? $student['last_name'] : ''); ?>"
                                           title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Phân trang -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Không tìm thấy sinh viên nào với các tiêu chí đã chỉ định.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Bao gồm footer
require_once '../../../includes/footer.php';
?>