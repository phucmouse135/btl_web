<?php
// Bắt đầu output buffering
ob_start();
// Bao gồm header
require_once '../../../includes/header.php';

// Yêu cầu vai trò quản trị viên hoặc nhân viên
if (!hasRole('admin') && !hasRole('staff')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

// Khởi tạo biến
$error = '';
$success = '';

// Lấy ID sinh viên
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    // ID không hợp lệ
    echo displayError("ID sinh viên không hợp lệ");
    require_once '../../../includes/footer.php';
    exit();
}

// Khởi tạo dữ liệu sinh viên
$student = [
    'id' => 0,
    'student_id' => '',
    'first_name' => '',
    'last_name' => '',
    'gender' => '',
    'dob' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'department' => '',
    'year_of_study' => '',
    'username' => '',
    'profile_pic' => '',
    'status' => 'active'
];

// Kiểm tra nếu đang yêu cầu thay đổi trạng thái
$statusAction = (isset($_GET['action']) && $_GET['action'] == 'status');

// Lấy thông tin sinh viên - truy vấn từ bảng users thay vì students
$sql = "SELECT * FROM users WHERE id = ? AND role = 'student'";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo displayError("Lỗi cơ sở dữ liệu: " . $conn->error);
    require_once '../../../includes/footer.php';
    exit();
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Không tìm thấy sinh viên
    echo displayError("Không tìm thấy sinh viên");
    require_once '../../../includes/footer.php';
    exit();
}

$studentData = $result->fetch_assoc();
$student = array_merge($student, $studentData);

// Xử lý gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Kiểm tra nếu là cập nhật trạng thái
    if (isset($_POST['status_update']) && $_POST['status_update'] == '1') {
        // Cập nhật trạng thái sinh viên
        $newStatus = sanitizeInput($_POST['status']);
        $statusReason = sanitizeInput($_POST['status_reason']);
        
        $updateSql = "UPDATE users SET student_status = ? WHERE id = ? AND role = 'student'";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $newStatus, $id);
        
        if ($updateStmt->execute()) {
            $success = "Trạng thái của sinh viên đã được cập nhật thành công thành " . ucfirst($newStatus);
            $student['status'] = $newStatus;
            
            // Ghi nhật ký hoạt động
            logActivity('update_student_status', "Đã cập nhật trạng thái sinh viên {$student['first_name']} {$student['last_name']} thành {$newStatus}. Lý do: {$statusReason}");
        } else {
            $error = "Lỗi khi cập nhật trạng thái sinh viên: " . $conn->error;
        }
    } else {
        // Cập nhật biểu mẫu thông thường
        $student['student_id'] = sanitizeInput($_POST['student_id']);
        $student['first_name'] = sanitizeInput($_POST['first_name']);
        $student['last_name'] = sanitizeInput($_POST['last_name']);
        $student['gender'] = sanitizeInput($_POST['gender']);
        $student['dob'] = sanitizeInput($_POST['dob']);
        $student['phone'] = sanitizeInput($_POST['phone']);
        $student['email'] = sanitizeInput($_POST['email']);
        $student['address'] = sanitizeInput($_POST['address']);
        $student['department'] = sanitizeInput($_POST['department']);
        $student['year_of_study'] = (int)$_POST['year_of_study'];
        $student['username'] = sanitizeInput($_POST['username']);
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Xác thực dữ liệu biểu mẫu
        if (empty($student['student_id']) || empty($student['first_name']) || empty($student['last_name']) || 
            empty($student['gender']) || empty($student['dob']) || empty($student['phone']) || 
            empty($student['email']) || empty($student['address']) || empty($student['department']) || 
            empty($student['year_of_study'])) {
            $error = 'Tất cả các trường bắt buộc phải được điền đầy đủ';
        } else if (!empty($password) && $password != $confirm_password) {
            $error = 'Mật khẩu không khớp';
        } else if (!filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Định dạng email không hợp lệ';
        } else {
            // Kiểm tra nếu mã sinh viên đã tồn tại (loại trừ sinh viên hiện tại)
            $checkSql = "SELECT * FROM users WHERE student_id = ? AND id != ? AND role = 'student'";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param("si", $student['student_id'], $id);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = 'Mã sinh viên đã tồn tại';
            } else {
                // Kiểm tra nếu tên người dùng đã tồn tại (loại trừ người dùng hiện tại)
                $checkSql = "SELECT * FROM users WHERE username = ? AND id != ?";
                $stmt = $conn->prepare($checkSql);
                $stmt->bind_param("si", $student['username'], $id);
                $stmt->execute();
                $checkResult = $stmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $error = 'Tên người dùng đã tồn tại';
                } else {
                    // Kiểm tra nếu email đã tồn tại (loại trừ người dùng hiện tại)
                    $checkSql = "SELECT * FROM users WHERE email = ? AND id != ?";
                    $stmt = $conn->prepare($checkSql);
                    $stmt->bind_param("si", $student['email'], $id);
                    $stmt->execute();
                    $checkResult = $stmt->get_result();
                    
                    if ($checkResult->num_rows > 0) {
                        $error = 'Email đã tồn tại';
                    } else {
                        // Bắt đầu giao dịch
                        $conn->begin_transaction();
                        
                        try {
                            // Cập nhật tài khoản người dùng và thông tin sinh viên trong cùng một bảng users
                            $userSql = "UPDATE users SET 
                                       username = ?, email = ?, student_id = ?, first_name = ?, 
                                       last_name = ?, gender = ?, dob = ?, phone = ?, 
                                       address = ?, department = ?, year_of_study = ?
                                       WHERE id = ? AND role = 'student'";
                            
                            $stmt = $conn->prepare($userSql);
                            $stmt->bind_param("ssssssssssii", 
                                           $student['username'], $student['email'], $student['student_id'], 
                                           $student['first_name'], $student['last_name'], $student['gender'], 
                                           $student['dob'], $student['phone'], $student['address'], 
                                           $student['department'], $student['year_of_study'], $id);
                            $stmt->execute();
                            
                            // Cập nhật mật khẩu nếu được cung cấp
                            if (!empty($password)) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $passSql = "UPDATE users SET password = ? WHERE id = ?";
                                $passStmt = $conn->prepare($passSql);
                                $passStmt->bind_param("si", $hashed_password, $id);
                                $passStmt->execute();
                            }
                            
                            // Xử lý tải lên ảnh đại diện
                            $profilePicPath = $student['profile_pic']; // Giữ nguyên ảnh hiện tại
                            
                            if (!empty($_FILES['profile_pic']['name'])) {
                                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/';
                                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                                $maxSize = 2 * 1024 * 1024; // 2MB
                                
                                $uploadResult = uploadFile($_FILES['profile_pic'], $uploadDir, $allowedTypes, $maxSize);
                                
                                if ($uploadResult['status']) {
                                    $profilePicPath = $uploadResult['fileName'];
                                    
                                    // Xóa ảnh đại diện cũ nếu nó tồn tại và không phải là mặc định
                                    if (!empty($student['profile_pic']) && $student['profile_pic'] != 'default.jpg' && 
                                        file_exists($uploadDir . $student['profile_pic'])) {
                                        unlink($uploadDir . $student['profile_pic']);
                                    }
                                    
                                    // Cập nhật ảnh đại diện trong bảng users
                                    $profileSql = "UPDATE users SET profile_pic = ? WHERE id = ?";
                                    $profileStmt = $conn->prepare($profileSql);
                                    $profileStmt->bind_param("si", $profilePicPath, $id);
                                    $profileStmt->execute();
                                } else {
                                    throw new Exception($uploadResult['message']);
                                }
                            }
                            
                            // Hoàn thành giao dịch
                            $conn->commit();
                            
                            // Thông báo thành công
                            $success = "Sinh viên " . $student['first_name'] . " " . $student['last_name'] . " đã được cập nhật thành công";
                            
                            // Ghi nhật ký hoạt động
                            logActivity('update_student', 'Đã cập nhật sinh viên: ' . $student['first_name'] . ' ' . $student['last_name']);
                            
                            // Cập nhật ảnh đại diện hiển thị
                            $student['profile_pic'] = $profilePicPath;
                        } catch (Exception $e) {
                            // Rollback giao dịch nếu có lỗi
                            $conn->rollback();
                            $error = "Lỗi khi cập nhật sinh viên: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $statusAction ? 'Cập nhật trạng thái sinh viên' : 'Chỉnh sửa sinh viên'; ?></h1>
    <div>
        <a href="/LTW/views/admin/students/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Quay lại danh sách
        </a>
        <?php if (!$statusAction): ?>
        <a href="/LTW/views/admin/students/view.php?id=<?php echo $id; ?>" class="btn btn-info">
            <i class="fas fa-eye me-2"></i> Xem hồ sơ
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($error)): ?>
    <?php echo displayError($error); ?>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <?php echo displaySuccess($success); ?>
<?php endif; ?>

<?php if ($statusAction): ?>
<!-- Biểu mẫu cập nhật trạng thái -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Cập nhật trạng thái sinh viên</h6>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-2 text-center">
                <img class="img-fluid rounded-circle mb-3" style="max-width: 100px;" 
                     src="<?php echo !empty($student['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $student['profile_pic']) 
                        ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] 
                        : '/LTW/assets/images/user.png'; ?>" alt="Ảnh đại diện">
            </div>
            <div class="col-md-10">
                <h5><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h5>
                <p class="mb-1"><strong>Mã sinh viên:</strong> <?php echo $student['student_id']; ?></p>
                <p class="mb-1"><strong>Khoa:</strong> <?php echo $student['department']; ?></p>
                <p class="mb-0"><strong>Trạng thái hiện tại:</strong> 
                    <span class="badge <?php echo ($student['student_status'] == 'active') ? 'bg-success' : (($student['student_status'] == 'inactive') ? 'bg-danger' : 'bg-info'); ?>">
                        <?php echo ucfirst($student['student_status']); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="status_update" value="1">
            
            <div class="mb-3">
                <label for="status" class="form-label">Trạng thái mới</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="active" <?php echo $student['student_status'] == 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                    <option value="inactive" <?php echo $student['student_status'] == 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                    <option value="graduated" <?php echo $student['student_status'] == 'graduated' ? 'selected' : ''; ?>>Đã tốt nghiệp</option>
                    <option value="suspended" <?php echo $student['student_status'] == 'suspended' ? 'selected' : ''; ?>>Đã đình chỉ</option>
                    <option value="transferred" <?php echo $student['student_status'] == 'transferred' ? 'selected' : ''; ?>>Đã chuyển trường</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="status_reason" class="form-label">Lý do thay đổi trạng thái</label>
                <textarea class="form-control" id="status_reason" name="status_reason" rows="3" required></textarea>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/LTW/views/admin/students/view.php?id=<?php echo $id; ?>" class="btn btn-secondary me-md-2">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary">Cập nhật trạng thái</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Biểu mẫu chỉnh sửa sinh viên -->
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
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Giới tính *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="" disabled>Chọn giới tính</option>
                                <option value="male" <?php echo $student['gender'] == 'male' ? 'selected' : ''; ?>>Nam</option>
                                <option value="female" <?php echo $student['gender'] == 'female' ? 'selected' : ''; ?>>Nữ</option>
                                <option value="other" <?php echo $student['gender'] == 'other' ? 'selected' : ''; ?>>Khác</option>
                            </select>
                            <div class="invalid-feedback">Vui lòng chọn giới tính</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="dob" class="form-label">Ngày sinh *</label>
                            <input type="date" class="form-control" id="dob" name="dob" 
                                   value="<?php echo $student['dob']; ?>" required>
                            <div class="invalid-feedback">Ngày sinh là bắt buộc</div>
                        </div>
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
                        <small class="form-text text-muted">Tải lên ảnh đại diện mới (tối đa 2MB, JPG/PNG/GIF)</small>
                        <div class="mt-2">
                            <img id="profile_pic_preview" 
                                 src="<?php echo !empty($student['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $student['profile_pic']) 
                                    ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] 
                                    : '/LTW/assets/images/user.png'; ?>" 
                                 class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
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
                        <label for="email" class="form-label">Địa chỉ email *</label>
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
                            <option value="" disabled>Chọn năm</option>
                            <option value="1" <?php echo $student['year_of_study'] == '1' ? 'selected' : ''; ?>>Năm 1</option>
                            <option value="2" <?php echo $student['year_of_study'] == '2' ? 'selected' : ''; ?>>Năm 2</option>
                            <option value="3" <?php echo $student['year_of_study'] == '3' ? 'selected' : ''; ?>>Năm 3</option>
                            <option value="4" <?php echo $student['year_of_study'] == '4' ? 'selected' : ''; ?>>Năm 4</option>
                            <option value="5" <?php echo $student['year_of_study'] == '5' ? 'selected' : ''; ?>>Năm 5</option>
                        </select>
                        <div class="invalid-feedback">Năm học là bắt buộc</div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Thông tin tài khoản</h5>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Tên người dùng *</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo $student['username']; ?>" required>
                        <div class="invalid-feedback">Tên người dùng là bắt buộc</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu <small class="text-muted">(để trống nếu giữ nguyên mật khẩu hiện tại)</small></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <div class="d-flex align-items-center">
                            <span class="badge <?php echo ($student['student_status'] == 'active') ? 'bg-success' : (($student['student_status'] == 'inactive') ? 'bg-danger' : 'bg-info'); ?> me-2">
                                <?php echo ucfirst($student['student_status']); ?>
                            </span>
                            <a href="?id=<?php echo $id; ?>&action=status" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-exchange-alt me-1"></i> Thay đổi trạng thái
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <a href="/LTW/views/admin/students/view.php?id=<?php echo $id; ?>" class="btn btn-secondary me-md-2">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary">Cập nhật sinh viên</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xác thực biểu mẫu
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Xem trước ảnh
    var imageInputs = document.querySelectorAll('.image-input');
    imageInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            var previewId = this.getAttribute('data-preview');
            var preview = document.getElementById(previewId);
            var file = this.files[0];
            
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Chuyển đổi hiển thị mật khẩu
    var toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var passwordInput = document.getElementById(targetId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
});
</script>

<?php
// Bao gồm footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
// Kết thúc và xóa bộ đệm
ob_end_flush();
?>