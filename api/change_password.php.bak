<?php
/**
 * API endpoint for changing password via AJAX
 */
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// For debugging
error_log("Session data: " . json_encode($_SESSION));

// Check if this is an AJAX request - but make it optional to help with debugging
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    error_log("Warning: Non-AJAX request to change_password.php, but proceeding anyway for testing");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Process change password request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $response['message'] = "Tất cả các trường đều bắt buộc.";
    } else if ($newPassword != $confirmPassword) {
        $response['message'] = "Mật khẩu mới và xác nhận không khớp.";
    } else if (strlen($newPassword) < 8) {
        $response['message'] = "Mật khẩu mới phải có ít nhất 8 ký tự.";
    } else {
        // Determine password field and table
        $passwordField = "password";
        $table = "users"; // Use users table for all users including students
        
        // Verify current password
        $sql = "SELECT $passwordField FROM $table WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $userId = $_SESSION['user_id'];
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            
            if (password_verify($currentPassword, $row[$passwordField])) {
                // Current password verified, update with new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $sql = "UPDATE $table SET $passwordField = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Thay đổi mật khẩu thành công.";
                    // Log activity
                    logActivity("Mật khẩu Đã thay đổi", "Người dùng đã thay đổi mật khẩu của họ", $userId, $_SESSION['user_role']);
                } else {
                    $response['message'] = "Lỗi khi cập nhật mật khẩu: " . $conn->error;
                }
            } else {
                $response['message'] = "Mật khẩu hiện tại không chính xác.";
            }
        } else {
            $response['message'] = "Không tìm thấy người dùng.";
        }
        $stmt->close();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
