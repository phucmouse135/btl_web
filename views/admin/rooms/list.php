<?php
// Bao gồm header
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Khởi tạo biến
$rooms = [];
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$building = isset($_GET['building']) ? sanitizeInput($_GET['building']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($currentPage - 1) * $limit;
$totalRooms = 0;

// Lấy danh sách các tòa nhà duy nhất từ bảng rooms
$buildings = [];
$buildingQuery = "SELECT DISTINCT building_name FROM rooms ORDER BY building_name ASC";
$buildingResult = $conn->query($buildingQuery);

// Kiểm tra xem truy vấn có thành công không
if ($buildingResult === false) {
    echo displayError("Lỗi truy vấn danh sách tòa nhà: " . $conn->error);
} else {
    while ($row = $buildingResult->fetch_assoc()) {
        $buildings[] = $row;
    }
}

// Xây dựng truy vấn dựa trên bộ lọc
$countQuery = "SELECT COUNT(*) as total FROM rooms r";
               
$query = "SELECT r.*, r.capacity as type_capacity, r.monthly_rent as price
          FROM rooms r";
          
$whereClause = [];
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause[] = "(r.room_number LIKE ? OR r.building_name LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam, $searchParam);
    $types .= "ss";
}

if (!empty($building)) {
    $whereClause[] = "r.building_name = ?";
    array_push($params, $building);
    $types .= "s";
}

if ($status != 'all') {
    $whereClause[] = "r.status = ?";
    array_push($params, $status);
    $types .= "s";
}

if (!empty($whereClause)) {
    $query .= " WHERE " . implode(" AND ", $whereClause);
    $countQuery .= " WHERE " . implode(" AND ", $whereClause);
}

$query .= " ORDER BY r.building_name ASC, CAST(r.room_number AS UNSIGNED) ASC LIMIT ? OFFSET ?";
array_push($params, $limit, $offset);
$types .= "ii";

// Lấy tổng số
$stmt = $conn->prepare($countQuery);
if ($stmt === false) {
    echo "Lỗi trong truy vấn SQL: " . $countQuery . "<br>";
    die("Lỗi khi chuẩn bị truy vấn đếm: " . $conn->error);
}

if (!empty($params)) {
    try {
        // Tạo một mảng mới chỉ với các tham số cần thiết cho truy vấn đếm (loại bỏ tham số phân trang)
        $countParams = array_slice($params, 0, count($params) - 2);
        $countTypes = substr($types, 0, strlen($types) - 2);
        
        if (!empty($countParams)) {
            if (!$stmt->bind_param($countTypes, ...$countParams)) {
                die("Liên kết tham số thất bại: " . $stmt->error);
            }
        }
    } catch (Exception $e) {
        die("Ngoại lệ trong quá trình liên kết tham số: " . $e->getMessage());
    }
}

if (!$stmt->execute()) {
    die("Thực thi thất bại: " . $stmt->error);
}
$result = $stmt->get_result();
$totalRooms = $result->fetch_assoc()['total'];
$totalPages = ceil($totalRooms / $limit);
$stmt->close();

// Lấy danh sách phòng
$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo displayError("Lỗi khi chuẩn bị truy vấn phòng: " . $conn->error);
    echo "<div class='alert alert-info mt-3'>
            <p><strong>Truy vấn:</strong> " . htmlspecialchars($query) . "</p>
            <p>Vui lòng kiểm tra cấu trúc cơ sở dữ liệu và đảm bảo các bảng cần thiết tồn tại.</p>
            <p><a href='/LTW/config/run_setup.php' class='btn btn-sm btn-primary'>Chạy thiết lập cơ sở dữ liệu</a></p>
         </div>";
} else {
    if (!empty($params) && count($params) > 0) {
        try {
            $stmt->bind_param($types, ...$params);
        } catch (Exception $e) {
            echo displayError("Lỗi khi gắn tham số: " . $e->getMessage());
        }
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
    } else {
        echo displayError("Lỗi thực thi truy vấn: " . $stmt->error);
    }
    
    $stmt->close();
}

// Xử lý các hành động
$message = '';
$error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'set_maintenance') {
        $updateStmt = $conn->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = ?");
        $updateStmt->bind_param("i", $id);
        
        if ($updateStmt->execute()) {
            $message = "Phòng đã được chuyển sang chế độ bảo trì.";
        } else {
            $error = "Không thể cập nhật trạng thái phòng.";
        }
        
        $updateStmt->close();
    } elseif ($action === 'set_available') {
        $updateStmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
        $updateStmt->bind_param("i", $id);
        
        if ($updateStmt->execute()) {
            $message = "Phòng đã được đặt là khả dụng.";
        } else {
            $error = "Không thể cập nhật trạng thái phòng.";
        }
        
        $updateStmt->close();
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Quản lý phòng</h1>
    <a href="/LTW/views/admin/rooms/add.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i> Thêm phòng mới
    </a>
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
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Tìm kiếm theo số phòng hoặc tòa nhà" name="search" value="<?php echo $search; ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="building" onchange="this.form.submit()">
                    <option value="" <?php echo $building == '' ? 'selected' : ''; ?>>Tất cả tòa nhà</option>
                    <?php foreach ($buildings as $b): ?>
                        <option value="<?php echo $b['building_name']; ?>" <?php echo $building == $b['building_name'] ? 'selected' : ''; ?>><?php echo $b['building_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Khả dụng</option>
                    <option value="occupied" <?php echo $status == 'occupied' ? 'selected' : ''; ?>>Đã sử dụng</option>
                    <option value="maintenance" <?php echo $status == 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Áp dụng</button>
            </div>
        </form>
    </div>
</div>

<!-- Thẻ phòng -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Phòng</h6>
        <div>
            <span class="badge bg-primary rounded-pill"><?php echo $totalRooms; ?> phòng</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($rooms)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Không tìm thấy phòng nào phù hợp với tiêu chí của bạn.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($rooms as $room): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card h-100 room-card">
                            <?php
                            $statusClass = 'bg-success'; // Khả dụng
                            $statusText = 'Khả dụng';
                            
                            // Tự động điều chỉnh trạng thái dựa trên current_occupancy
                            if ($room['current_occupancy'] > 0) {
                                // Nếu có người ở thì phòng phải ở trạng thái occupied
                                $statusClass = 'bg-danger';
                                $statusText = 'Đã sử dụng';
                                
                                // Đảm bảo cơ sở dữ liệu được cập nhật nếu trạng thái không khớp
                                if ($room['status'] != 'occupied') {
                                    $updateRoomStatus = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                                    $updateRoomStatus->bind_param("i", $room['id']);
                                    $updateRoomStatus->execute();
                                    $updateRoomStatus->close();
                                    // Cập nhật trạng thái trong biến hiện tại
                                    $room['status'] = 'occupied';
                                }
                            } else if ($room['status'] == 'occupied' && $room['current_occupancy'] == 0) {
                                // Nếu không có người ở nhưng trạng thái là occupied, cập nhật về available
                                $statusClass = 'bg-success';
                                $statusText = 'Khả dụng';
                                
                                $updateRoomStatus = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                                $updateRoomStatus->bind_param("i", $room['id']);
                                $updateRoomStatus->execute();
                                $updateRoomStatus->close();
                                // Cập nhật trạng thái trong biến hiện tại
                                $room['status'] = 'available';
                            } else if ($room['status'] == 'maintenance') {
                                $statusClass = 'bg-warning';
                                $statusText = 'Bảo trì';
                            }
                            ?>
                            <div class="position-absolute top-0 end-0 p-2">
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h5 class="card-title font-weight-bold mb-0">Phòng <?php echo $room['room_number']; ?></h5>
                                </div>
                                <p class="text-muted small">
                                    <i class="fas fa-building me-1"></i> <?php echo $room['building_name']; ?>
                                </p>
                                <hr>
                                <div class="mb-2">
                                    <p class="mb-0"><i class="fas fa-bed me-1"></i> 
                                    <?php 
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
                                        echo $roomType;
                                    ?>
                                    </p>
                                    <p class="mb-0"><i class="fas fa-user me-1"></i> 
                                    <?php 
                                        // Hiển thị số người hiện tại trên tổng số sức chứa của phòng
                                        echo $room['current_occupancy'] . '/' . $room['capacity'];
                                        
                                        // Nếu current_occupancy là 0 nhưng trạng thái là "occupied", ghi lại lỗi này
                                        if ($room['current_occupancy'] == 0 && $room['status'] == 'occupied') {
                                            // Log lỗi cho admin biết
                                            error_log("Phòng {$room['room_number']} có trạng thái occupied nhưng current_occupancy = 0");
                                        }
                                    ?> sức chứa</p>
                                    <p class="mb-0"><i class="fas fa-layer-group me-1"></i> Tầng <?php echo $room['floor']; ?></p>
                                    <p class="mb-0"><i class="fas fa-money-bill me-1"></i> <?php echo number_format($room['monthly_rent'], 0, ',', '.'); ?> đ/tháng</p>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between">
                                    <a href="/LTW/views/admin/rooms/view.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i> Chi tiết
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $room['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Hành động
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $room['id']; ?>">
                                            <li><a class="dropdown-item" href="/LTW/views/admin/rooms/edit.php?id=<?php echo $room['id']; ?>">
                                                <i class="fas fa-edit me-2"></i> Chỉnh sửa
                                            </a></li>
                                            <li><a class="dropdown-item" href="/LTW/views/admin/contracts/room_assignments.php?room_id=<?php echo $room['id']; ?>">
                                                <i class="fas fa-users me-2"></i> Phân phòng
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($room['status'] != 'maintenance'): ?>
                                                <li><a class="dropdown-item text-warning" href="/LTW/views/admin/rooms/list.php?action=set_maintenance&id=<?php echo $room['id']; ?>" onclick="return confirm('Đặt phòng này vào chế độ bảo trì?')">
                                                    <i class="fas fa-tools me-2"></i> Đặt thành bảo trì
                                                </a></li>
                                            <?php else: ?>
                                                <li><a class="dropdown-item text-success" href="/LTW/views/admin/rooms/list.php?action=set_available&id=<?php echo $room['id']; ?>" onclick="return confirm('Đánh dấu phòng này là khả dụng?')">
                                                    <i class="fas fa-check-circle me-2"></i> Đặt thành khả dụng
                                                </a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Phân trang -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&building=<?php echo urlencode($building); ?>&status=<?php echo $status; ?>">
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
                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&building=' . urlencode($building) . '&status=' . $status . '">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $currentPage) {
                                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&building=' . urlencode($building) . '&status=' . $status . '">' . $i . '</a></li>';
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&search=' . urlencode($search) . '&building=' . urlencode($building) . '&status=' . $status . '">' . $totalPages . '</a></li>';
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&building=<?php echo urlencode($building); ?>&status=<?php echo $status; ?>">
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
    // Tùy chọn: Thêm bất kỳ JavaScript cụ thể cho phòng ở đây
});
</script>

<style>
.room-card {
    transition: all 0.3s;
}
.room-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>