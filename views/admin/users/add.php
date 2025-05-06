<?php
// Include necessary configurations
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

// Require login
requireLogin();

// Check if user has admin role
if (!hasRole('admin')) {
    header("Location: /LTW/dashboard.php");
    exit();
}

$errors = [];

// Initialize empty user data
$user = [
    'username' => '',
    'email' => '',
    'role' => 'student',
    'status' => 'active'
];

// Initialize role info
$roleInfo = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'position' => '',
    'department' => '',
    'hire_date' => date('Y-m-d'),
    'student_id' => '',
    'year_of_study' => '',
    'gender' => '',
    'address' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);
    
    // Validate username
    if (empty($username)) {
        $errors[] = 'Tên đăng nhập không được để trống';
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = 'Email không được để trống';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = 'Mật khẩu không được để trống';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }
    
    // Confirm password match
    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp';
    }
    
    // Check if username or email already exists
    $checkSql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    // Check if prepare was successful
    if ($checkStmt === false) {
        $errors[] = 'Lỗi truy vấn kiểm tra: ' . $conn->error;
    } else {
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $errors[] = 'Tên đăng nhập hoặc email đã tồn tại';
        }
        $checkStmt->close();
    }
    
    // If no errors, proceed with insertion
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user information
            $insertSql = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            
            if ($insertStmt === false) {
                throw new Exception('Lỗi chuẩn bị truy vấn thêm người dùng: ' . $conn->error);
            }
            
            $insertStmt->bind_param("sssss", $username, $email, $hashedPassword, $role, $status);
            $insertStmt->execute();
            
            // Get the new user ID
            $userId = $insertStmt->insert_id;
            $insertStmt->close();
            
            // Insert role-specific information
            if ($role == 'admin' || $role == 'staff') {
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                // Insert new staff record
                $insertStaffSql = "INSERT INTO staff 
                                  (user_id, first_name, last_name, phone, position) 
                                  VALUES (?, ?, ?, ?, ?)";
                $insertStaffStmt = $conn->prepare($insertStaffSql);
                
                if ($insertStaffStmt === false) {
                    throw new Exception('Lỗi chuẩn bị truy vấn thêm nhân viên: ' . $conn->error);
                }
                
                $insertStaffStmt->bind_param("issss", $userId, $firstName, $lastName, $phone, 
                                           $position);
                $insertStaffStmt->execute();
                $insertStaffStmt->close();
                
            } elseif ($role == 'student') {
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $studentId = trim($_POST['student_id'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $yearOfStudy = trim($_POST['year_of_study'] ?? '');
                $gender = trim($_POST['gender'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                
                // Insert new student record
                $insertStudentSql = "INSERT INTO students 
                                    (user_id, first_name, last_name, student_id, department, 
                                     year_of_study, gender, phone, address) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStudentStmt = $conn->prepare($insertStudentSql);
                
                if ($insertStudentStmt === false) {
                    throw new Exception('Lỗi chuẩn bị truy vấn thêm sinh viên: ' . $conn->error);
                }
                
                $insertStudentStmt->bind_param("issssssss", $userId, $firstName, $lastName, $studentId, 
                                             $department, $yearOfStudy, $gender, 
                                             $phone, $address);
                $insertStudentStmt->execute();
                $insertStudentStmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = 'Thêm người dùng mới thành công';
            header("Location: view.php?id=" . $userId);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = 'Đã xảy ra lỗi: ' . $e->getMessage();
        }
    }
}

// Set page title
$pageTitle = 'Thêm người dùng mới';

// Include header
include '../../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/LTW/dashboard.php">Bảng điều khiển</a></li>
        <li class="breadcrumb-item"><a href="list.php">Quản lý người dùng</a></li>
        <li class="breadcrumb-item active">Thêm người dùng</li>
    </ol>
    
    <?php
    // Display errors if any
    if (!empty($errors)) {
        echo '<div class="alert alert-danger">';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-plus me-1"></i>
            Thông tin người dùng
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <!-- Basic User Information -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Thông tin tài khoản</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">Mật khẩu phải có ít nhất 6 ký tự.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Quản trị viên</option>
                                    <option value="staff" <?php echo ($user['role'] == 'staff') ? 'selected' : ''; ?>>Nhân viên</option>
                                    <option value="student" <?php echo ($user['role'] == 'student') ? 'selected' : ''; ?>>Sinh viên</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Trạng thái <span class="text-danger">*</span></label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>>Đang hoạt động</option>
                                    <option value="inactive" <?php echo ($user['status'] != 'active') ? 'selected' : ''; ?>>Bị vô hiệu hóa</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Role-specific Information -->
                <div id="admin-staff-fields" class="mb-4" style="display:none;">
                    <h5 class="border-bottom pb-2">Thông tin nhân viên</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Tên</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($roleInfo['first_name']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Họ</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($roleInfo['last_name']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($roleInfo['phone']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="position" class="form-label">Chức vụ</label>
                                <input type="text" class="form-control" id="position" name="position" 
                                       value="<?php echo htmlspecialchars($roleInfo['position']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">Phòng/Ban</label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="<?php echo htmlspecialchars($roleInfo['department']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hire_date" class="form-label">Ngày vào làm</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                       value="<?php echo htmlspecialchars($roleInfo['hire_date']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="student-fields" class="mb-4">
                    <h5 class="border-bottom pb-2">Thông tin sinh viên</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name_student" class="form-label">Tên</label>
                                <input type="text" class="form-control" id="first_name_student" name="first_name" 
                                       value="<?php echo htmlspecialchars($roleInfo['first_name']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name_student" class="form-label">Họ</label>
                                <input type="text" class="form-control" id="last_name_student" name="last_name" 
                                       value="<?php echo htmlspecialchars($roleInfo['last_name']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Mã sinh viên</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" 
                                       value="<?php echo htmlspecialchars($roleInfo['student_id']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_student" class="form-label">Khoa/Ngành</label>
                                <input type="text" class="form-control" id="department_student" name="department" 
                                       value="<?php echo htmlspecialchars($roleInfo['department']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="year_of_study" class="form-label">Năm học</label>
                                <input type="text" class="form-control" id="year_of_study" name="year_of_study" 
                                       value="<?php echo htmlspecialchars($roleInfo['year_of_study']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Giới tính</label>
                                <select class="form-control" id="gender" name="gender">
                                    <option value="">-- Chọn giới tính --</option>
                                    <option value="male" <?php echo ($roleInfo['gender'] == 'male') ? 'selected' : ''; ?>>Nam</option>
                                    <option value="female" <?php echo ($roleInfo['gender'] == 'female') ? 'selected' : ''; ?>>Nữ</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Ngày sinh</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                                <small class="form-text text-muted">Lưu ý: Thông tin này chỉ hiển thị và không được lưu vào cơ sở dữ liệu.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone_student" class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" id="phone_student" name="phone" 
                                       value="<?php echo htmlspecialchars($roleInfo['phone']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Địa chỉ</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($roleInfo['address']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-plus me-1"></i> Thêm người dùng
                    </button>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Trở lại
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide fields based on selected role
document.addEventListener('DOMContentLoaded', function() {
    // Run once on page load
    toggleFields();
    
    // Add event listener for role changes
    document.getElementById('role').addEventListener('change', toggleFields);
    
    function toggleFields() {
        var role = document.getElementById('role').value;
        var adminStaffFields = document.getElementById('admin-staff-fields');
        var studentFields = document.getElementById('student-fields');
        
        if (role === 'admin' || role === 'staff') {
            adminStaffFields.style.display = 'block';
            studentFields.style.display = 'none';
        } else if (role === 'student') {
            adminStaffFields.style.display = 'none';
            studentFields.style.display = 'block';
        } else {
            adminStaffFields.style.display = 'none';
            studentFields.style.display = 'none';
        }
    }
});
</script>

<?php
// Include footer
include '../../../includes/footer.php';
?>