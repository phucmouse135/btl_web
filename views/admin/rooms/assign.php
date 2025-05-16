<?php
// Bắt đầu output buffering
ob_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($roomId <= 0) {
    header("Location: /LTW/views/admin/rooms/list.php?error=invalid_id");
    exit();
}

// Lấy thông tin phòng
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->bind_param("i", $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lấy danh sách phân công phòng (tất cả trạng thái)
$stmt = $conn->prepare(
    "SELECT ra.*, u.student_id, u.first_name, u.last_name, u.email, u.phone
     FROM room_assignments ra
     JOIN users u ON ra.user_id = u.id
     WHERE ra.room_id = ?
     ORDER BY ra.status DESC, ra.start_date DESC"
);
$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();
$assignments = [];
while ($row = $result->fetch_assoc()) $assignments[] = $row;
$stmt->close();

// Xử lý kết thúc phân công
if (isset($_GET['end']) && is_numeric($_GET['end'])) {
    $assignmentId = (int)$_GET['end'];
    $conn->query("UPDATE room_assignments SET status='completed', end_date=CURDATE() WHERE id=$assignmentId");
    header("Location: /LTW/views/admin/contracts/room_assignments.php?room_id=$roomId");
    exit();
}

// Xử lý xóa phân công
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $assignmentId = (int)$_GET['delete'];
    $conn->query("DELETE FROM room_assignments WHERE id=$assignmentId");
    header("Location: /LTW/views/admin/contracts/room_assignments.php?room_id=$roomId");
    exit();
}
?>

<div class="container mt-4">
    <h2>Quản lý phân phòng: <?php echo $room['building_name'] . ' - ' . $room['room_number']; ?></h2>
    <a href="/LTW/views/admin/rooms/view.php?id=<?php echo $roomId; ?>" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Quay lại phòng
    </a>
    <a href="assign_student.php?room_id=<?php echo $roomId; ?>" class="btn btn-primary mb-3">
        <i class="fas fa-plus"></i> Thêm sinh viên vào phòng
    </a>
    <div class="card">
        <div class="card-header">Danh sách phân công phòng</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Mã sinh viên</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Điện thoại</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?php echo $a['student_id']; ?></td>
                        <td><?php echo $a['first_name'] . ' ' . $a['last_name']; ?></td>
                        <td><?php echo $a['email']; ?></td>
                        <td><?php echo $a['phone']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($a['start_date'])); ?></td>
                        <td><?php echo $a['end_date'] ? date('d/m/Y', strtotime($a['end_date'])) : '-'; ?></td>
                        <td>
                            <span class="badge bg-<?php
                                switch ($a['status']) {
                                    case 'active': echo 'success'; break;
                                    case 'pending': echo 'warning'; break;
                                    case 'completed': echo 'secondary'; break;
                                    case 'terminated': echo 'danger'; break;
                                    default: echo 'info';
                                }
                            ?>"><?php echo ucfirst($a['status']); ?></span>
                        </td>
                        <td>
                            <?php if ($a['status'] == 'active'): ?>
                                <a href="?room_id=<?php echo $roomId; ?>&end=<?php echo $a['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Kết thúc phân công này?')">Kết thúc</a>
                            <?php endif; ?>
                            <a href="?room_id=<?php echo $roomId; ?>&delete=<?php echo $a['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa phân công này?')">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; if (empty($assignments)): ?>
                    <tr><td colspan="8" class="text-center">Chưa có phân công phòng nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
// Kết thúc và xóa bộ đệm
ob_end_flush();
?>