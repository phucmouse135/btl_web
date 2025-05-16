<?php
// Bắt đầu output buffering
ob_start();
// Include các tệp cần thiết
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra xem người dùng đã đăng nhập và là quản trị viên
requireLogin();
if (!isAdmin()) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra xem bảng cài đặt tồn tại chưa, tạo nếu chưa có
    $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($tableCheck->num_rows == 0) {
        $conn->query("CREATE TABLE settings (
            id INT(11) NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Xử lý dữ liệu biểu mẫu
    foreach ($_POST as $key => $value) {
        if ($key !== 'submit') {
            $safeKey = $conn->real_escape_string($key);
            $safeValue = $conn->real_escape_string($value);
            
            // Kiểm tra xem cài đặt đã tồn tại chưa
            $result = $conn->query("SELECT * FROM settings WHERE setting_key = '$safeKey'");
            
            if ($result->num_rows > 0) {
                // Cập nhật cài đặt đã tồn tại
                $conn->query("UPDATE settings SET setting_value = '$safeValue', updated_at = NOW() WHERE setting_key = '$safeKey'");
            } else {
                // Chèn cài đặt mới
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$safeKey', '$safeValue')");
            }
        }
    }
    
    // Đặt thông báo thành công
    $_SESSION['success'] = "Cài đặt đã được cập nhật thành công!";
    header("Location: /LTW/views/admin/settings.php");
    exit();
}

// Lấy cài đặt hiện tại
$settings = [];
$result = $conn->query("SHOW TABLES LIKE 'settings'");
if ($result->num_rows > 0) {
    $settingsResult = $conn->query("SELECT * FROM settings");
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Giá trị mặc định nếu chưa được đặt
$defaults = [
    'site_title' => 'Hệ Thống Quản Lý Ký Túc Xá',
    'admin_email' => 'admin@example.com',
    'maintenance_mode' => '0',
    'currency' => 'VND',
    'payment_due_days' => '30',
    'late_fee_percentage' => '5',
    'system_theme' => 'light',
    'academic_year_start' => date('Y') . '-09-01',
    'academic_year_end' => (date('Y')+1) . '-06-30'
];

// Kết hợp giá trị mặc định với cài đặt từ cơ sở dữ liệu
foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Include header
$pageTitle = "Cài Đặt Hệ Thống";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Nội dung chính -->
        <main class="col-md-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Cài Đặt Hệ Thống</h1>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Cấu hình Cài Đặt Hệ Thống</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <!-- Cài đặt chung -->
                                <h5 class="border-bottom pb-2">Cài Đặt Chung</h5>
                                <div class="mb-3 row">
                                    <label for="site_title" class="col-sm-3 col-form-label">Tiêu Đề Trang</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="admin_email" class="col-sm-3 col-form-label">Email Quản Trị</label>
                                    <div class="col-sm-9">
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="maintenance_mode" class="col-sm-3 col-form-label">Chế Độ Bảo Trì</label>
                                    <div class="col-sm-9">
                                        <select class="form-select" id="maintenance_mode" name="maintenance_mode">
                                            <option value="0" <?php echo $settings['maintenance_mode'] == '0' ? 'selected' : ''; ?>>Tắt</option>
                                            <option value="1" <?php echo $settings['maintenance_mode'] == '1' ? 'selected' : ''; ?>>Bật</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="system_theme" class="col-sm-3 col-form-label">Giao Diện Hệ Thống</label>
                                    <div class="col-sm-9">
                                        <select class="form-select" id="system_theme" name="system_theme" onchange="updateLocalStorageTheme(this.value)">
                                            <option value="light" <?php echo $settings['system_theme'] == 'light' ? 'selected' : ''; ?>>Sáng</option>
                                            <option value="dark" <?php echo $settings['system_theme'] == 'dark' ? 'selected' : ''; ?>>Tối</option>
                                        </select>
                                    </div>
                                </div>

<script>
// Cập nhật localStorage khi theme được thay đổi từ dropdown
function updateLocalStorageTheme(theme) {
    localStorage.setItem('theme', theme);
    document.documentElement.setAttribute('data-bs-theme', theme);
    
    // Cập nhật icon trên thanh điều hướng
    var darkIcon = document.getElementById('darkModeIcon');
    var lightIcon = document.getElementById('lightModeIcon');
    
    if (theme === 'dark') {
        darkIcon.style.display = 'none';
        lightIcon.style.display = 'inline-block';
    } else {
        darkIcon.style.display = 'inline-block';
        lightIcon.style.display = 'none';
    }
}

// Đảm bảo form submit vẫn hoạt động bình thường
document.addEventListener('DOMContentLoaded', function() {
    // Ưu tiên lấy giá trị theme từ localStorage trước
    var savedTheme = localStorage.getItem('theme');
    
    if (savedTheme) {
        // Nếu có theme trong localStorage, cập nhật dropdown để hiển thị đúng giá trị
        document.getElementById('system_theme').value = savedTheme;
        // Áp dụng theme
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    } else {
        // Nếu không có theme trong localStorage, lấy từ database
        var currentTheme = '<?php echo $settings['system_theme']; ?>';
        localStorage.setItem('theme', currentTheme);
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
    }
});
</script>
                                
                                <!-- Cài đặt tài chính -->
                                <h5 class="border-bottom pb-2 mt-4">Cài Đặt Tài Chính</h5>
                                <div class="mb-3 row">
                                    <label for="currency" class="col-sm-3 col-form-label">Đơn Vị Tiền Tệ</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control" id="currency" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="payment_due_days" class="col-sm-3 col-form-label">Số Ngày Thanh Toán</label>
                                    <div class="col-sm-9">
                                        <input type="number" class="form-control" id="payment_due_days" name="payment_due_days" value="<?php echo htmlspecialchars($settings['payment_due_days']); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="late_fee_percentage" class="col-sm-3 col-form-label">Phần Trăm Phí Trễ Hạn</label>
                                    <div class="col-sm-9">
                                        <input type="number" step="0.01" class="form-control" id="late_fee_percentage" name="late_fee_percentage" value="<?php echo htmlspecialchars($settings['late_fee_percentage']); ?>" required>
                                    </div>
                                </div>
                                
                                <!-- Cài đặt năm học -->
                                <h5 class="border-bottom pb-2 mt-4">Năm Học</h5>
                                <div class="mb-3 row">
                                    <label for="academic_year_start" class="col-sm-3 col-form-label">Bắt Đầu Năm Học</label>
                                    <div class="col-sm-9">
                                        <input type="date" class="form-control" id="academic_year_start" name="academic_year_start" value="<?php echo htmlspecialchars($settings['academic_year_start']); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 row">
                                    <label for="academic_year_end" class="col-sm-3 col-form-label">Kết Thúc Năm Học</label>
                                    <div class="col-sm-9">
                                        <input type="date" class="form-control" id="academic_year_end" name="academic_year_end" value="<?php echo htmlspecialchars($settings['academic_year_end']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="submit" class="btn btn-primary">Lưu Cài Đặt</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>