<?php
// Đặt biến phiên để đánh dấu rằng đây là giao diện admin
session_start();
$_SESSION['is_admin_maintenance'] = true;

// Lấy room_id từ tham số URL nếu có
$room_id = isset($_GET['room_id']) ? '?room_id=' . intval($_GET['room_id']) : '';

// Chuyển hướng đến file chính
header('Location: /LTW/views/maintenance/add.php' . $room_id);
exit;
?>