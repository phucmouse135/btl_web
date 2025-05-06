<?php
// Lấy id từ URL nếu có
$queryParams = isset($_GET['id']) ? '?id=' . intval($_GET['id']) : '';

// Chuyển hướng đến trang reset mật khẩu chung
header('Location: /LTW/views/auth/reset_password.php' . $queryParams);
exit;
?>