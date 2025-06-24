<?php
// Bắt đầu output buffering
ob_start();
// Include necessary configurations
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

// Require login
requireLogin();

// Check if user has admin role
if (!hasRole('admin')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Set page title
$pageTitle = 'Quản lý người dùng';

// Handle search and pagination
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total users for pagination
$countSql = "SELECT COUNT(id) as total FROM users WHERE 1=1";
if (!empty($search)) {
    $countSql .= " AND (username LIKE ? OR email LIKE ?)";
}

$countStmt = $conn->prepare($countSql);
if (!empty($search)) {
    $searchParam = "%{$search}%";
    $countStmt->bind_param("ss", $searchParam, $searchParam);
}
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users with pagination
$sql = "SELECT u.*, CASE 
            WHEN u.role = 'admin' THEN 'Quản trị viên'
            WHEN u.role = 'staff' THEN 'Nhân viên' 
            WHEN u.role = 'student' THEN 'Sinh viên'
            ELSE 'Không xác định'
        END as role_name
        FROM users u
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
}

$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchParam = "%{$search}%";
    $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Include header
include '../../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/LTW/dashboard.php">Bảng điều khiển</a></li>
        <li class="breadcrumb-item active"><?php echo $pageTitle; ?></li>
    </ol>
    
    <?php
    // Display messages if any
    if (isset($_SESSION['success'])) {
        echo displaySuccess($_SESSION['success']);
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        echo displayError($_SESSION['error']);
        unset($_SESSION['error']);
    }
    ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <i class="fas fa-users me-1"></i>
                    Danh sách người dùng
                </div>
                <div class="col-md-6 text-end">
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Thêm người dùng mới
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Tìm kiếm theo tên đăng nhập hoặc email" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Xóa bộ lọc
                    </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Email</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['role_name']; ?></td>
                                    <td>
                                        <?php if ($user['status'] == 'active'): ?>
                                            <span class="badge bg-success">Đang hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Bị vô hiệu hóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDateTime($user['created_at']); ?></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="/LTW/api/delete_item.php?type=user&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-ajax-delete="true" data-item-name="<?php echo htmlspecialchars($user['username']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Không tìm thấy người dùng nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php
            // Display pagination
            if ($totalPages > 1) {
                echo generatePagination($page, $totalPages, 'list.php');
            }
            ?>
        </div>
    </div>
</div>

<?php
// Include footer
include '../../../includes/footer.php';
?>