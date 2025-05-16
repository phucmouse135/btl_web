<?php
// Bắt đầu output buffering
ob_start();
// Bao gồm header trước
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Xử lý gửi biểu mẫu để thêm/chỉnh sửa loại phòng
$message = '';
$error = '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editData = null;

// Khởi tạo biến
$roomTypes = [];
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($currentPage - 1) * $limit;
$totalRoomTypes = 0;

// Xây dựng truy vấn dựa trên bộ lọc
$countQuery = "SELECT COUNT(*) as total FROM rooms";
$query = "SELECT DISTINCT building_name, 
                (SELECT COUNT(*) FROM rooms r2 WHERE r2.building_name = r.building_name) as room_count,
                (SELECT COUNT(*) FROM rooms r3 WHERE r3.building_name = r.building_name AND r3.current_occupancy > 0) as occupied_count
          FROM rooms r";

$whereClause = [];
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause[] = "(building_name LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam);
    $types .= "s";
}

if (!empty($whereClause)) {
    $query .= " WHERE " . implode(" AND ", $whereClause);
    $countQuery .= " WHERE " . implode(" AND ", $whereClause);
}

$query .= " ORDER BY building_name ASC LIMIT ? OFFSET ?";
array_push($params, $limit, $offset);
$types .= "ii";

// Lấy tổng số
$stmt = $conn->prepare($countQuery);
if ($stmt) {
    if (!empty($params) && count($params) > 0 && count($params) > 2) {
        // Tạo một mảng mới chỉ với các tham số cần thiết cho truy vấn đếm (loại bỏ tham số phân trang)
        $countParams = array_slice($params, 0, count($params) - 2);
        $countTypes = substr($types, 0, strlen($types) - 2);
        
        if (!empty($countParams)) {
            $stmt->bind_param($countTypes, ...$countParams);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRoomTypes = $result->fetch_assoc()['total'];
    $totalPages = ceil($totalRoomTypes / $limit);
    $stmt->close();
} else {
    $error = "Lỗi khi chuẩn bị truy vấn đếm: " . $conn->error;
}

// Lấy danh sách tòa nhà
$roomTypeStmt = $conn->prepare($query);
if ($roomTypeStmt) {
    if (!empty($params) && count($params) > 0) {
        $roomTypeStmt->bind_param($types, ...$params);
    }
    
    if ($roomTypeStmt->execute()) {
        $result = $roomTypeStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $roomTypes[] = $row;
        }
    } else {
        $error = "Lỗi thực thi truy vấn: " . $roomTypeStmt->error;
    }
    
    $roomTypeStmt->close();
} else {
    $error = "Lỗi khi chuẩn bị truy vấn tòa nhà: " . $conn->error;
}

// Xử lý khi form được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'rename') {
        $oldName = sanitizeInput($_POST['old_name']);
        $newName = sanitizeInput($_POST['new_name']);
        
        if (empty($newName)) {
            $error = "Tên tòa nhà mới không được để trống.";
        } else {
            // Kiểm tra xem tên mới đã tồn tại chưa
            $checkQuery = "SELECT COUNT(*) as count FROM rooms WHERE building_name = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("s", $newName);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $error = "Tòa nhà với tên '$newName' đã tồn tại.";
            } else {
                // Cập nhật tên tòa nhà
                $updateQuery = "UPDATE rooms SET building_name = ? WHERE building_name = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $newName, $oldName);
                
                if ($stmt->execute()) {
                    $message = "Đã đổi tên tòa nhà '$oldName' thành '$newName'.";
                    
                    // Chuyển hướng để làm mới trang
                    header("Location: /LTW/views/admin/rooms/room_types.php?success=1");
                    exit();
                } else {
                    $error = "Lỗi khi đổi tên tòa nhà: " . $stmt->error;
                }
            }
            
            $stmt->close();
        }
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Quản lý tòa nhà</h1>
    <a href="/LTW/views/admin/rooms/list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Quay lại danh sách phòng
    </a>
</div>

<?php if ($message): ?>
    <?php echo displaySuccess($message); ?>
<?php endif; ?>

<?php if ($error): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<!-- Card quản lý tòa nhà -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Danh sách tòa nhà</h6>
    </div>
    <div class="card-body">
        <?php if (empty($roomTypes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Chưa có tòa nhà nào trong hệ thống.
            </div>
            <p>Bạn có thể thêm tòa nhà khi <a href="/LTW/views/admin/rooms/add.php">tạo phòng mới</a>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tòa nhà</th>
                            <th>Số phòng</th>
                            <th>Đang sử dụng</th>
                            <th>Thống kê</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roomTypes as $building): ?>
                            <tr>
                                <td><?php echo $building['building_name']; ?></td>
                                <td><?php echo $building['room_count']; ?></td>
                                <td>
                                    <?php 
                                    $occupancyPercent = ($building['room_count'] > 0) ? 
                                        round(($building['occupied_count'] / $building['room_count']) * 100) : 0;
                                    
                                    $badgeClass = 'bg-info';
                                    if ($occupancyPercent >= 90) {
                                        $badgeClass = 'bg-danger';
                                    } else if ($occupancyPercent >= 70) {
                                        $badgeClass = 'bg-warning';
                                    } else if ($occupancyPercent >= 50) {
                                        $badgeClass = 'bg-primary';
                                    } else if ($occupancyPercent >= 30) {
                                        $badgeClass = 'bg-success';
                                    }
                                    ?>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                            <div class="progress-bar <?php echo $badgeClass; ?>" role="progressbar" style="width: <?php echo $occupancyPercent; ?>%" 
                                                aria-valuenow="<?php echo $occupancyPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $building['occupied_count']; ?>/<?php echo $building['room_count']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <a href="/LTW/views/admin/rooms/list.php?building=<?php echo urlencode($building['building_name']); ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-search me-1"></i> Xem phòng
                                    </a>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#renameModal<?php echo str_replace(' ', '', $building['building_name']); ?>">
                                        <i class="fas fa-edit me-1"></i> Đổi tên
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Modal đổi tên tòa nhà -->
                            <div class="modal fade" id="renameModal<?php echo str_replace(' ', '', $building['building_name']); ?>" tabindex="-1" aria-labelledby="renameModalLabel<?php echo str_replace(' ', '', $building['building_name']); ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post" action="">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="renameModalLabel<?php echo str_replace(' ', '', $building['building_name']); ?>">Đổi tên tòa nhà</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="rename">
                                                <input type="hidden" name="old_name" value="<?php echo $building['building_name']; ?>">
                                                <div class="mb-3">
                                                    <label for="old_name_display" class="form-label">Tên hiện tại</label>
                                                    <input type="text" class="form-control" id="old_name_display" value="<?php echo $building['building_name']; ?>" disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="new_name<?php echo str_replace(' ', '', $building['building_name']); ?>" class="form-label">Tên mới</label>
                                                    <input type="text" class="form-control" id="new_name<?php echo str_replace(' ', '', $building['building_name']); ?>" name="new_name" required>
                                                </div>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i> Lưu ý: Đổi tên tòa nhà sẽ ảnh hưởng đến tất cả các phòng trong tòa nhà này.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Thống kê phòng theo tòa nhà -->
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Thống kê theo tòa nhà</h6>
            </div>
            <div class="card-body">
                <canvas id="buildingsChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Tỷ lệ sử dụng phòng</h6>
            </div>
            <div class="card-body">
                <canvas id="occupancyChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dữ liệu cho biểu đồ thống kê theo tòa nhà
    const buildingData = {
        labels: [<?php echo !empty($roomTypes) ? "'" . implode("', '", array_column($roomTypes, 'building_name')) . "'" : ""; ?>],
        datasets: [{
            label: 'Số phòng',
            data: [<?php echo !empty($roomTypes) ? implode(", ", array_column($roomTypes, 'room_count')) : ""; ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };
    
    const buildingsCtx = document.getElementById('buildingsChart').getContext('2d');
    new Chart(buildingsCtx, {
        type: 'bar',
        data: buildingData,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 1
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Phân bố phòng theo tòa nhà',
                    font: {
                        size: 16
                    }
                }
            }
        }
    });
    
    // Dữ liệu cho biểu đồ tỷ lệ sử dụng
    const occupancyData = {
        labels: [<?php echo !empty($roomTypes) ? "'" . implode("', '", array_column($roomTypes, 'building_name')) . "'" : ""; ?>],
        datasets: [{
            label: 'Đang sử dụng',
            data: [<?php echo !empty($roomTypes) ? implode(", ", array_column($roomTypes, 'occupied_count')) : ""; ?>],
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
        },
        {
            label: 'Khả dụng',
            data: [
                <?php 
                    if (!empty($roomTypes)) {
                        $availableCounts = [];
                        foreach ($roomTypes as $building) {
                            $availableCounts[] = $building['room_count'] - $building['occupied_count'];
                        }
                        echo implode(", ", $availableCounts);
                    }
                ?>
            ],
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    };
    
    const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
    new Chart(occupancyCtx, {
        type: 'bar',
        data: occupancyData,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 1
                },
                x: {
                    stacked: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Tỷ lệ sử dụng phòng theo tòa nhà',
                    font: {
                        size: 16
                    }
                }
            }
        }
    });
});
</script>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
// Kết thúc và xóa bộ đệm
ob_end_flush();
?>