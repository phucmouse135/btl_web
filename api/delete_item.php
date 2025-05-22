<?php
/**
 * API endpoint for deleting items via AJAX
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

// Check if user is logged in and has necessary permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'redirect' => null
];

// Process delete request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_delete'])) {
    // Get the item type and ID from the request
    $itemType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
    $itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Validate input
    if (empty($itemType) || $itemId <= 0) {
        $response['message'] = "Thông tin không hợp lệ để xóa.";
    } else {
        // Determine which table to use based on item type
        $table = '';
        $redirect = '';
        $itemName = '';
        
        switch ($itemType) {
            case 'student':
                $table = 'users';
                $redirect = '/LTW/views/admin/students/list.php';
                $itemName = 'sinh viên';
                
                // Check if user has permission to delete students
                if (!hasRole('admin') && !hasRole('staff')) {
                    $response['message'] = "Bạn không có quyền xóa sinh viên.";
                    break;
                }
                
                // Additional condition for students
                $where = "id = ? AND role = 'student'";
                break;
                
            case 'room':
                $table = 'rooms';
                $redirect = '/LTW/views/admin/rooms/list.php';
                $itemName = 'phòng';
                
                // Check if user has permission to delete rooms
                if (!hasRole('admin') && !hasRole('staff')) {
                    $response['message'] = "Bạn không có quyền xóa phòng.";
                    break;
                }
                
                $where = "id = ?";
                break;
                
            case 'building':
                $table = 'buildings';
                $redirect = '/LTW/views/admin/buildings.php';
                $itemName = 'tòa nhà';
                
                // Check if user has permission to delete buildings
                if (!hasRole('admin')) {
                    $response['message'] = "Bạn không có quyền xóa tòa nhà.";
                    break;
                }
                
                $where = "id = ?";
                break;
                
            case 'user':
                $table = 'users';
                $redirect = '/LTW/views/admin/users/list.php';
                $itemName = 'người dùng';
                
                // Check if user has permission to delete users
                if (!hasRole('admin')) {
                    $response['message'] = "Bạn không có quyền xóa người dùng.";
                    break;
                }
                
                // Don't allow deleting yourself
                if ($itemId == $_SESSION['user_id']) {
                    $response['message'] = "Bạn không thể xóa tài khoản của chính mình.";
                    break;
                }
                
                $where = "id = ? AND role != 'admin'";
                break;
                
            case 'maintenance':
                $table = 'maintenance_requests';
                $redirect = '/LTW/views/maintenance/list.php';
                $itemName = 'yêu cầu bảo trì';
                
                // Check if user has permission to delete maintenance requests
                if (!hasRole('admin') && !hasRole('staff')) {
                    $response['message'] = "Bạn không có quyền xóa yêu cầu bảo trì.";
                    break;
                }
                
                $where = "id = ?";
                break;
                
            default:
                $response['message'] = "Loại mục không được hỗ trợ.";
                break;
        }
        
        // If table is determined, proceed with deletion
        if (!empty($table) && empty($response['message'])) {
            // Delete the item
            $sql = "DELETE FROM $table WHERE $where";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $itemId);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = ucfirst($itemName) . " đã được xóa thành công.";
                    $response['redirect'] = $redirect;
                    
                    // Log the activity
                    logActivity("delete_{$itemType}", "Đã xóa {$itemName} với ID {$itemId}");
                } else {
                    $response['message'] = ucfirst($itemName) . " không tồn tại hoặc đã bị xóa.";
                }
            } else {
                $response['message'] = "Lỗi khi xóa {$itemName}: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
