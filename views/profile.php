<?php
// Include necessary files
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /LTW/login.php");
    exit;
}

// Get user information
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission for profile update
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Process profile picture upload if provided
    $profilePicUpdate = '';
    $profilePicParams = [];
    
    if (!empty($_FILES['profile_pic']['name'])) {
        // Define allowed file types and maximum file size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if ($_FILES['profile_pic']['size'] > $maxSize) {
            $errorMsg = "Kích thước ảnh quá lớn. Tối đa 2MB.";
        } elseif (!in_array($_FILES['profile_pic']['type'], $allowedTypes)) {
            $errorMsg = "Chỉ chấp nhận file ảnh định dạng JPEG, PNG, GIF.";
        } else {
            // Create unique filename
            $fileExt = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid(rand(), true) . '_' . time() . '.' . $fileExt;
            $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $newFilename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadPath)) {
                // Add to update query
                $profilePicUpdate = ", profile_pic = ?";
                $profilePicParams[] = $newFilename;
                
                // Remove old profile pic if it exists and is not default
                if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default.jpg') {
                    $oldPicPath = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $user['profile_pic'];
                    if (file_exists($oldPicPath)) {
                        @unlink($oldPicPath);
                    }
                }
            } else {
                $errorMsg = "Lỗi khi tải lên ảnh đại diện.";
            }
        }
    }
    
    if (empty($errorMsg)) {
        // Update user information
        $updateQuery = "UPDATE users SET fullname = ?, phone = ?" . $profilePicUpdate . " WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        $paramTypes = "ss" . str_repeat("s", count($profilePicParams)) . "i";
        $bindParams = [$fullname, $phone];
        foreach ($profilePicParams as $param) {
            $bindParams[] = $param;
        }
        $bindParams[] = $userId;
        
        $updateStmt->bind_param($paramTypes, ...$bindParams);
        
        if ($updateStmt->execute()) {
            $successMsg = "Thông tin hồ sơ đã được cập nhật thành công!";
            // Refresh user data
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $errorMsg = "Có lỗi xảy ra khi cập nhật hồ sơ: " . $conn->error;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h1 class="h3 text-gray-800 mb-4">Hồ sơ của tôi</h1>
            
            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMsg; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMsg; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information Card -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin Hồ sơ</h6>
                </div>                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fullname"><strong>Họ và tên</strong></label>
                                    <input type="text" class="form-control" id="fullname" name="fullname" 
                                           value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email"><strong>Email</strong></label>
                                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                                    <small class="form-text text-muted">Email không thể thay đổi</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone"><strong>Điện thoại</strong></label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role"><strong>Vai trò</strong></label>
                                    <input type="text" class="form-control" id="role" 
                                           value="<?php echo htmlspecialchars($userRole); ?>" readonly>
                                </div>                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="profile_pic"><strong>Ảnh đại diện</strong></label>
                                    <input type="file" class="form-control-file" id="profile_pic" name="profile_pic">
                                    <small class="form-text text-muted">Chọn file ảnh (JPEG, PNG, GIF, tối đa 2MB)</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Cập nhật Hồ sơ
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Password Change Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Đổi mật khẩu</h6>
                </div>
                <div class="card-body">
                    <form action="/LTW/views/auth/change_password.php" method="get" class="d-inline">
                        <div class="form-group mb-0">
                            <p class="mb-3">Cập nhật mật khẩu của bạn thường xuyên giúp giữ tài khoản an toàn.</p>
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-key mr-1"></i> Thay đổi Mật khẩu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Account Summary Card -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin Tài khoản</h6>
                </div>                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php 
                        // Check if user has a custom profile picture
                        $profilePic = '/LTW/assets/images/ktx.jpg'; // Default image
                        if (!empty($user['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/profile_pics/' . $user['profile_pic'])) {
                            $profilePic = '/LTW/uploads/profile_pics/' . $user['profile_pic'];
                        }
                        ?>
                        <img class="img-profile rounded-circle mb-3" src="<?php echo $profilePic; ?>" width="150" height="150" alt="User Profile Picture">
                        <h5><?php echo htmlspecialchars($user['fullname'] ?? 'Người dùng'); ?></h5>
                        <p class="badge badge-primary"><?php echo htmlspecialchars($userRole); ?></p>
                    </div>
                    
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between">
                            <span class="font-weight-bold">Đăng nhập gần đây:</span>
                            <span><?php echo date("d/m/Y H:i"); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between">
                            <span class="font-weight-bold">Tài khoản được tạo:</span>
                            <span><?php echo $user['created_at'] ? date("d/m/Y", strtotime($user['created_at'])) : 'N/A'; ?></span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <span class="font-weight-bold">Trạng thái:</span>
                        <span class="badge badge-success">Hoạt động</span>
                    </div>
                </div>
            </div>
            
            <!-- Activity Log Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Hoạt động gần đây</h6>
                </div>
                <div class="card-body">
                    <div class="activity-item d-flex mb-3 pb-3 border-bottom">
                        <div class="activity-icon bg-primary text-white rounded-circle p-2 mr-3">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div>
                            <div>Đăng nhập vào hệ thống</div>
                            <small class="text-muted"><?php echo date("d/m/Y H:i"); ?></small>
                        </div>
                    </div>
                    
                    <div class="activity-item d-flex">
                        <div class="activity-icon bg-info text-white rounded-circle p-2 mr-3">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div>
                            <div>Cập nhật thông tin hồ sơ</div>
                            <small class="text-muted"><?php echo date("d/m/Y", strtotime("-2 days")); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php'; ?>