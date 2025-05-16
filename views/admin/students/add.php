<?php
// Bắt đầu output buffering
ob_start();
// Bao gồm header
require_once '../../../includes/header.php';

// Yêu cầu vai trò admin hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Khởi tạo biến
$error = '';
$success = '';

// Giá trị mặc định
$student = [
    'student_id' => '',
    'first_name' => '',
    'last_name' => '',
    'gender' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'department' => '',
    'year_of_study' => '',
    'username' => '',
    'student_status' => 'active'
];

// Xử lý khi biểu mẫu được gửi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ biểu mẫu
    $student['student_id'] = sanitizeInput($_POST['student_id']);
    $student['first_name'] = sanitizeInput($_POST['first_name']);
    $student['last_name'] = sanitizeInput($_POST['last_name']);
    $student['gender'] = sanitizeInput($_POST['gender']);
    $student['phone'] = sanitizeInput($_POST['phone']);
    $student['email'] = sanitizeInput($_POST['email']);
    $student['address'] = sanitizeInput($_POST['address']);
    $student['department'] = sanitizeInput($_POST['department']);
    $student['year_of_study'] = (int)$_POST['year_of_study'];
    $student['username'] = sanitizeInput($_POST['username']);
    $student['student_status'] = isset($_POST['student_status']) ? sanitizeInput($_POST['student_status']) : 'active';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Kiểm tra dữ liệu biểu mẫu
    if (empty($student['student_id']) || empty($student['first_name']) || empty($student['last_name']) || 
        empty($student['gender']) || empty($student['phone']) || empty($student['email']) || 
        empty($student['address']) || empty($student['department']) || empty($student['year_of_study']) || 
        empty($student['username']) || empty($password) || empty($student['student_status'])) {
        $error = 'Tất cả các trường là bắt buộc';
    } else if ($password != $confirm_password) {
        $error = 'Mật khẩu không khớp';
    } else if (!filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng email không hợp lệ';
    } else {
        // Kiểm tra nếu mã sinh viên đã tồn tại
        $checkSql = "SELECT * FROM users WHERE student_id = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("s", $student['student_id']);
        $stmt->execute();
        $checkResult = $stmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = 'Mã sinh viên đã tồn tại';
        } else {
            // Kiểm tra nếu tên người dùng đã tồn tại
            $checkSql = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param("s", $student['username']);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = 'Tên người dùng đã tồn tại';
            } else {
                // Kiểm tra nếu email đã tồn tại
                $checkSql = "SELECT * FROM users WHERE email = ?";
                $stmt = $conn->prepare($checkSql);
                $stmt->bind_param("s", $student['email']);
                $stmt->execute();
                $checkResult = $stmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $error = 'Email đã tồn tại';
                } else {
                    // Bắt đầu giao dịch
                    $conn->begin_transaction();
                    
                    try {
                        // Mã hóa mật khẩu
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Tải lên ảnh đại diện nếu có
                        $profilePicPath = 'default.jpg'; // Ảnh mặc định
                        
                        if (!empty($_FILES['profile_pic']['name'])) {
                            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/';
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                            $maxSize = 2 * 1024 * 1024; // 2MB
                            
                            $uploadResult = uploadFile($_FILES['profile_pic'], $uploadDir, $allowedTypes, $maxSize);
                            
                            if ($uploadResult['status']) {
                                $profilePicPath = $uploadResult['fileName'];
                            } else {
                                throw new Exception($uploadResult['message']);
                            }
                        }
                        
                        // Thêm người dùng với role = student và tất cả thông tin sinh viên
                        $sql = "INSERT INTO users (username, password, email, role, first_name, last_name, 
                              phone, profile_pic, student_id, gender, address, department, 
                              year_of_study, student_status, status)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($sql);
                        if ($stmt === false) {
                            throw new Exception("Prepare statement failed: " . $conn->error);
                        }
                        
                        $role = 'student';
                        $status = 'active';
                        $stmt->bind_param("sssssssssssssss", 
                                          $student['username'], 
                                          $hashed_password, 
                                          $student['email'],
                                          $role,
                                          $student['first_name'],
                                          $student['last_name'], 
                                          $student['phone'],
                                          $profilePicPath,
                                          $student['student_id'], 
                                          $student['gender'],
                                          $student['address'], 
                                          $student['department'], 
                                          $student['year_of_study'], 
                                          $student['student_status'],
                                          $status);
                        $stmt->execute();
                        
                        // Cam kết giao dịch
                        $conn->commit();
                        
                        // Thông báo thành công
                        $success = "Sinh viên " . $student['first_name'] . " " . $student['last_name'] . " đã được thêm thành công";
                        
                        // Ghi lại hoạt động
                        logActivity('add_student', 'Thêm sinh viên mới: ' . $student['first_name'] . ' ' . $student['last_name']);
                        
                        // Đặt lại biểu mẫu
                        $student = [
                            'student_id' => '',
                            'first_name' => '',
                            'last_name' => '',
                            'gender' => '',
                            'phone' => '',
                            'email' => '',
                            'address' => '',
                            'department' => '',
                            'year_of_study' => '',
                            'username' => '',
                            'student_status' => 'active'
                        ];
                    } catch (Exception $e) {
                        // Hoàn tác giao dịch nếu có lỗi
                        $conn->rollback();
                        $error = "Lỗi khi thêm sinh viên: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Thêm sinh viên mới</h1>
    <a href="/LTW/views/admin/students/list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Quay lại danh sách
    </a>
</div>

<?php echo displayError($error); ?>
<?php echo displaySuccess($success); ?>

<!-- Biểu mẫu thêm sinh viên -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Thông tin sinh viên</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row">
                <!-- Thông tin cá nhân -->
                <div class="col-md-6">
                    <h5 class="mb-3">Thông tin cá nhân</h5>
                    
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Mã sinh viên *</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" 
                               value="<?php echo $student['student_id']; ?>" required>
                        <div class="invalid-feedback">Mã sinh viên là bắt buộc</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">Họ *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo $student['first_name']; ?>" required>
                            <div class="invalid-feedback">Họ là bắt buộc</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Tên *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo $student['last_name']; ?>" required>
                            <div class="invalid-feedback">Tên là bắt buộc</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label">Giới tính *</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="" disabled <?php echo empty($student['gender']) ? 'selected' : ''; ?>>Chọn giới tính</option>
                            <option value="male" <?php echo $student['gender'] == 'male' ? 'selected' : ''; ?>>Nam</option>
                            <option value="female" <?php echo $student['gender'] == 'female' ? 'selected' : ''; ?>>Nữ</option>
                            <option value="other" <?php echo $student['gender'] == 'other' ? 'selected' : ''; ?>>Khác</option>
                        </select>
                        <div class="invalid-feedback">Vui lòng chọn giới tính</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Địa chỉ *</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $student['address']; ?></textarea>
                        <div class="invalid-feedback">Địa chỉ là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="profile_pic" class="form-label">Ảnh đại diện</label>
                        <input type="file" class="form-control image-input" id="profile_pic" name="profile_pic" 
                               accept="image/jpeg, image/png, image/gif" data-preview="profile_pic_preview">
                        <small class="form-text text-muted">Tải lên ảnh đại diện (tối đa 2MB, JPG/PNG/GIF)</small>
                        <div class="mt-2">
                            <img id="profile_pic_preview" src="/LTW/assets/images/user.png" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                        </div>
                    </div>
                </div>
                
                <!-- Thông tin liên hệ & học tập -->
                <div class="col-md-6">
                    <h5 class="mb-3">Thông tin liên hệ & học tập</h5>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Số điện thoại *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo $student['phone']; ?>" required>
                        <div class="invalid-feedback">Số điện thoại là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Địa chỉ Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo $student['email']; ?>" required>
                        <div class="invalid-feedback">Email hợp lệ là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Khoa *</label>
                        <input type="text" class="form-control" id="department" name="department" 
                               value="<?php echo $student['department']; ?>" required>
                        <div class="invalid-feedback">Khoa là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="year_of_study" class="form-label">Năm học *</label>
                        <select class="form-select" id="year_of_study" name="year_of_study" required>
                            <option value="" disabled <?php echo empty($student['year_of_study']) ? 'selected' : ''; ?>>Chọn năm học</option>
                            <option value="1" <?php echo $student['year_of_study'] == '1' ? 'selected' : ''; ?>>Năm 1</option>
                            <option value="2" <?php echo $student['year_of_study'] == '2' ? 'selected' : ''; ?>>Năm 2</option>
                            <option value="3" <?php echo $student['year_of_study'] == '3' ? 'selected' : ''; ?>>Năm 3</option>
                            <option value="4" <?php echo $student['year_of_study'] == '4' ? 'selected' : ''; ?>>Năm 4</option>
                            <option value="5" <?php echo $student['year_of_study'] == '5' ? 'selected' : ''; ?>>Năm 5</option>
                        </select>
                        <div class="invalid-feedback">Năm học là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="student_status" class="form-label">Trạng thái *</label>
                        <select class="form-select" id="student_status" name="student_status" required>
                            <option value="active" <?php echo $student['student_status'] == 'active' ? 'selected' : ''; ?>>Đang học</option>
                            <option value="inactive" <?php echo $student['student_status'] == 'inactive' ? 'selected' : ''; ?>>Tạm dừng</option>
                            <option value="graduated" <?php echo $student['student_status'] == 'graduated' ? 'selected' : ''; ?>>Đã tốt nghiệp</option>
                            <option value="expelled" <?php echo $student['student_status'] == 'expelled' ? 'selected' : ''; ?>>Đã thôi học</option>
                        </select>
                        <div class="invalid-feedback">Trạng thái là bắt buộc</div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Thông tin tài khoản</h5>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Tên người dùng *</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo $student['username']; ?>" required>
                        <div class="invalid-feedback">Tên người dùng là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Mật khẩu là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Vui lòng xác nhận mật khẩu</div>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <button type="reset" class="btn btn-secondary me-md-2">Đặt lại</button>
                <button type="submit" class="btn btn-primary">Thêm sinh viên</button>
            </div>
        </form>
    </div>
</div>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
// Kết thúc và xóa bộ đệm
ob_end_flush();
?>