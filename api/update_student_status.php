<?php
/**
 * API endpoint for updating student status via AJAX
 */
// Include necessary files
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user has admin or staff role
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'staff')) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Process student status update request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $statusReason = sanitizeInput($_POST['status_reason'] ?? '');
    
    // Validate input
    if ($studentId <= 0) {
        $response['message'] = "ID sinh viên không hợp lệ.";
    } else if (empty($newStatus)) {
        $response['message'] = "Trạng thái không được để trống.";
    } else if (!in_array($newStatus, ['active', 'inactive', 'graduated'])) {
        $response['message'] = "Trạng thái không hợp lệ.";
    } else {
        // Update student status
        $updateSql = "UPDATE users SET student_status = ? WHERE id = ? AND role = 'student'";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $newStatus, $studentId);
        
        if ($updateStmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Trạng thái của sinh viên đã được cập nhật thành công thành " . ucfirst($newStatus);
            
            // Get student name for the log
            $studentSql = "SELECT first_name, last_name FROM users WHERE id = ? AND role = 'student'";
            $studentStmt = $conn->prepare($studentSql);
            $studentStmt->bind_param("i", $studentId);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            
            if ($studentResult->num_rows > 0) {
                $student = $studentResult->fetch_assoc();
                // Log activity
                logActivity('update_student_status', "Đã cập nhật trạng thái sinh viên {$student['first_name']} {$student['last_name']} thành {$newStatus}. Lý do: {$statusReason}");
            }
            
            // Add status information to response
            $statusClass = 'bg-success';
            $statusText = 'Hoạt động';
            
            if ($newStatus == 'inactive') {
                $statusClass = 'bg-danger';
                $statusText = 'Không hoạt động';
            } else if ($newStatus == 'graduated') {
                $statusClass = 'bg-info';
                $statusText = 'Đã tốt nghiệp';
            }
            
            $response['status_class'] = $statusClass;
            $response['status_text'] = $statusText;
        } else {
            $response['message'] = "Lỗi khi cập nhật trạng thái sinh viên: " . $conn->error;
        }
        $updateStmt->close();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
