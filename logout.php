<?php
// Bắt đầu phiên làm việc
session_start();

// Bao gồm các hàm
require_once 'config/database.php';
require_once 'config/functions.php';

// Ghi lại hoạt động đăng xuất nếu người dùng đã đăng nhập
if (isset($_SESSION['user_id'])) {
    logActivity('logout', 'Người dùng đã đăng xuất', $_SESSION['user_id'], $_SESSION['user_role']);
}

// Xóa tất cả các biến phiên
$_SESSION = array();

// Hủy phiên làm việc
session_destroy();

// Chuyển hướng đến trang đăng nhập
header("Location: index.php");
exit();
?>