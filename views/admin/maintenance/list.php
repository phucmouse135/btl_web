<?php
// File này là một wrapper cho views/maintenance/list.php với các tùy chọn admin thêm vào
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Kiểm tra đăng nhập và quyền admin/nhân viên
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    header('Location: /LTW/index.php');
    exit;
}

// Đặt biến môi trường để views/maintenance/list.php biết rằng nó đang được gọi bởi giao diện admin
$_SESSION['is_admin_maintenance_list'] = true;

// Chuyển hướng đến file maintenance thông thường
include $_SERVER['DOCUMENT_ROOT'] . '/LTW/views/maintenance/list.php';