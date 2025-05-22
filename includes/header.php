<?php
// Bắt đầu phiên nếu chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bao gồm tệp cơ sở dữ liệu và các hàm
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Kiểm tra nếu người dùng đã đăng nhập
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;

// Lấy thông tin người dùng nếu đã đăng nhập
if ($isLoggedIn) {
    $currentUser = getUserById($_SESSION['user_id']);
}

// Lấy cài đặt trang web
$dormitoryName = getSetting('dormitory_name', 'Hệ thống quản lý ký túc xá');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= $dormitoryName ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Tùy chỉnh -->
    <link href="/LTW/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>

    <!-- Theme Switching Script -->
    <script>
        // Áp dụng theme từ localStorage khi trang tải
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme) {
                document.documentElement.setAttribute('data-bs-theme', theme);
            } else {
                // Mặc định nếu không có theme được lưu
                const systemTheme = "<?php echo getSetting('system_theme', 'light'); ?>";
                document.documentElement.setAttribute('data-bs-theme', systemTheme);
                localStorage.setItem('theme', systemTheme);
            }
        })();
    </script>

    <style>
        /* Styles cho theme tối */
        [data-bs-theme="dark"] {
            --bs-body-bg: #212529;
            --bs-body-color: #f8f9fa;
        }
        
        /* Đảm bảo các card và container có màu nền phù hợp trong chế độ tối */
        [data-bs-theme="dark"] .card {
            background-color: #2c3034;
            border-color: #343a40;
        }
        
        [data-bs-theme="dark"] .table {
            color: #f8f9fa;
        }
        
        [data-bs-theme="dark"] .bg-light {
            background-color: #343a40 !important;
        }
        
        /* Thanh điều hướng trong chế độ tối */
        [data-bs-theme="dark"] .navbar-dark.bg-primary {
            background-color: #1a1d20 !important;
        }
        
        /* Footer trong chế độ tối */
        [data-bs-theme="dark"] .footer {
            background-color: #343a40 !important;
        }
        
        [data-bs-theme="dark"] .footer .text-muted {
            color: #adb5bd !important;
        }
        
        /* Dropdown menus trong chế độ tối */
        [data-bs-theme="dark"] .dropdown-menu {
            background-color: #2c3034;
            border-color: #343a40;
        }
        
        [data-bs-theme="dark"] .dropdown-item {
            color: #f8f9fa;
        }
        
        [data-bs-theme="dark"] .dropdown-item:hover {
            background-color: #343a40;
            color: white;
        }
        
        [data-bs-theme="dark"] .dropdown-divider {
            border-top-color: #444;
        }
        
        /* Input fields trong chế độ tối */
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2c3034;
            border-color: #444;
            color: #f8f9fa;
        }
        
        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #2c3034;
            border-color: #0d6efd;
            color: #f8f9fa;
        }
        
        /* Các nút trong chế độ tối */
        [data-bs-theme="dark"] .btn-secondary {
            background-color: #495057;
            border-color: #495057;
        }
        
        [data-bs-theme="dark"] .btn-outline-secondary {
            color: #adb5bd;
            border-color: #495057;
        }
        
        /* Alerts trong chế độ tối */
        [data-bs-theme="dark"] .alert {
            background-color: #2c3034;
            border-color: #343a40;
        }
        /* Cài đặt toàn bộ cho chế độ tối */
        [data-bs-theme="dark"] {
            --bs-body-bg: #121212;
            --bs-body-color: #e0e0e0;
            color-scheme: dark;
        }
        
        /* Body và container chính */
        [data-bs-theme="dark"] body {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        /* Thanh điều hướng */
        [data-bs-theme="dark"] .navbar-dark.bg-primary,
        [data-bs-theme="dark"] .navbar,
        [data-bs-theme="dark"] nav {
            background-color: #1a1a1a !important;
            border-bottom: 1px solid #333;
        }
        
        /* Các card và container */
        [data-bs-theme="dark"] .card,
        [data-bs-theme="dark"] .container-fluid,
        [data-bs-theme="dark"] .container {
            background-color: #1e1e1e;
            border-color: #333;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .card-header,
        [data-bs-theme="dark"] .card-footer {
            background-color: #252525;
            border-color: #333;
        }
        
        /* Bảng */
        [data-bs-theme="dark"] .table {
            color: #e0e0e0;
            border-color: #333;
        }
        
        [data-bs-theme="dark"] .table thead th {
            background-color: #252525;
            border-color: #333;
        }
        
        [data-bs-theme="dark"] .table tbody tr:hover {
            background-color: #252525;
        }
        
        [data-bs-theme="dark"] .table-bordered th, 
        [data-bs-theme="dark"] .table-bordered td {
            border-color: #333;
        }
        
        /* Footer */
        [data-bs-theme="dark"] .footer,
        [data-bs-theme="dark"] .bg-light {
            background-color: #1a1a1a !important;
            border-top: 1px solid #333;
        }
        
        [data-bs-theme="dark"] .footer .text-muted {
            color: #adb5bd !important;
        }
        
        /* Dropdown menus */
        [data-bs-theme="dark"] .dropdown-menu {
            background-color: #252525;
            border-color: #333;
        }
        
        [data-bs-theme="dark"] .dropdown-item {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .dropdown-item:hover {
            background-color: #333;
            color: #fff;
        }
        
        [data-bs-theme="dark"] .dropdown-divider {
            border-top-color: #444;
        }
        
        /* Form controls */
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #252525;
            border-color: #444;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #252525;
            border-color: #0d6efd;
            color: #e0e0e0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        [data-bs-theme="dark"] .form-control::placeholder {
            color: #adb5bd;
        }
        
        /* Buttons */
        [data-bs-theme="dark"] .btn-secondary {
            background-color: #444;
            border-color: #555;
        }
        
        [data-bs-theme="dark"] .btn-outline-secondary {
            color: #bbb;
            border-color: #555;
        }
        
        /* Modal */
        [data-bs-theme="dark"] .modal-content {
            background-color: #252525;
            border-color: #333;
        }
        
        [data-bs-theme="dark"] .modal-header,
        [data-bs-theme="dark"] .modal-footer {
            border-color: #333;
        }
        
        /* Alerts */
        [data-bs-theme="dark"] .alert {
            background-color: #252525;
            border-color: #333;
        }
        
        /* Cards in dashboard */
        [data-bs-theme="dark"] .card-body h5,
        [data-bs-theme="dark"] .card-body h6 {
            color: #e0e0e0;
        }
        
        /* Labels and headings */
        [data-bs-theme="dark"] label,
        [data-bs-theme="dark"] .col-form-label {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] h1, 
        [data-bs-theme="dark"] h2, 
        [data-bs-theme="dark"] h3, 
        [data-bs-theme="dark"] h4, 
        [data-bs-theme="dark"] h5, 
        [data-bs-theme="dark"] h6 {
            color: #e0e0e0;
        }
        
        /* Borders */
        [data-bs-theme="dark"] .border,
        [data-bs-theme="dark"] .border-bottom,
        [data-bs-theme="dark"] .border-top,
        [data-bs-theme="dark"] .border-start,
        [data-bs-theme="dark"] .border-end {
            border-color: #333 !important;
        }
        
        /* Text colors */
        [data-bs-theme="dark"] .text-muted {
            color: #adb5bd !important;
        }
        
        [data-bs-theme="dark"] .text-dark {
            color: #e0e0e0 !important;
        }
        
        /* Đảm bảo màu chữ phù hợp */
        [data-bs-theme="dark"] a:not(.btn):not(.nav-link) {
            color: #8ab4f8;
        }
        
        [data-bs-theme="dark"] a:not(.btn):not(.nav-link):hover {
            color: #aecbfa;
        }
    </style>
</head>
<body>
    <!-- Thanh Điều Hướng -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/LTW/dashboard.php">
                <i class="fas fa-building me-2"></i><?= $dormitoryName ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <?php if ($isLoggedIn): ?>
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="/LTW/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Bảng điều khiển
                            </a>
                        </li>
                        
                        <?php if (hasRole('admin') || hasRole('staff')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="studentDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-users me-1"></i> Sinh viên
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/LTW/views/admin/students/list.php">Tất cả sinh viên</a></li>
                                    <li><a class="dropdown-item" href="/LTW/views/admin/students/add.php">Thêm sinh viên</a></li>
                                </ul>
                            </li>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="roomsDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-door-open me-1"></i> Phòng
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/LTW/views/admin/rooms/list.php">Tất cả phòng</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/LTW/views/admin/rooms/room_types.php">Loại phòng</a></li>
                                </ul>
                            </li>
                            
                        <?php endif; ?>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="maintenanceDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-tools me-1"></i> Bảo trì
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/LTW/views/maintenance/list.php">Tất cả yêu cầu</a></li>
                                <li><a class="dropdown-item" href="/LTW/views/maintenance/add.php">Yêu cầu mới</a></li>
                            </ul>
                        </li>
                        
                        <?php if (hasRole('admin')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cogs me-1"></i> Quản trị
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/LTW/views/admin/users/list.php">Quản lý người dùng</a></li>
                                    <li><a class="dropdown-item" href="/LTW/views/admin/settings.php">Cài đặt</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <!-- Theme Toggle -->
                        <li class="nav-item dropdown me-2">
                            <a class="nav-link" href="#" id="themeToggle" role="button">
                                <i class="fas fa-moon" id="darkModeIcon"></i>
                                <i class="fas fa-sun" id="lightModeIcon" style="display:none;"></i>
                            </a>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> 
                                <?= htmlspecialchars($_SESSION['username'] ?? 'Người dùng') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/LTW/views/profile.php">
                                    <i class="fas fa-user me-2"></i> Hồ sơ của tôi
                                </a></li>
                                <li><a class="dropdown-item" href="/LTW/views/change_password.php">
                                    <i class="fas fa-key me-2"></i> Đổi mật khẩu
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/LTW/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Đăng xuất
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>    <!-- Container Nội dung Chính -->
    <div class="container-fluid py-4">
        <!-- Container hiển thị phản hồi AJAX -->
        <div id="ajax-response-container"></div>
        
        <?php if (isset($_SESSION['alert'])): ?>
            <?php 
                $alertType = $_SESSION['alert']['type'] ?? 'info';
                $alertMessage = $_SESSION['alert']['message'] ?? '';
                
                switch ($alertType) {
                    case 'success':
                        echo displaySuccess($alertMessage);
                        break;
                    case 'error':
                        echo displayError($alertMessage);
                        break;
                    case 'warning':
                        echo displayWarning($alertMessage);
                        break;
                    default:
                        echo displayInfo($alertMessage);
                }
                
                // Xóa thông báo
                unset($_SESSION['alert']);
            ?>
        <?php endif; ?>