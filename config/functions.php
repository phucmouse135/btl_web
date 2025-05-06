<?php
/**
 * Common functions for Dormitory Management System
 */

// Include database configuration
require_once 'database.php';

/**
 * Display error message
 * @param string $message Error message to display
 * @return string HTML for formatted error message
 */
function displayError($message) {
    if ($message) {
        return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    return '';
}

/**
 * Display success message
 * @param string $message Success message to display
 * @return string HTML for formatted success message
 */
function displaySuccess($message) {
    if ($message) {
        return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    return '';
}

/**
 * Display warning message
 * @param string $message Warning message to display
 * @return string HTML for warning alert
 */
function displayWarning($message) {
    if (!empty($message)) {
        return '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    return '';
}

/**
 * Display info message
 * @param string $message Info message to display
 * @return string HTML for info alert
 */
function displayInfo($message) {
    if (!empty($message)) {
        return '<div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i> ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    return '';
}

/**
 * Sanitize user input
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    return $data;
}

/**
 * Generate a unique file name
 * @param string $originalName Original file name
 * @return string Unique file name
 */
function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Upload a file
 * @param array $file File data from $_FILES
 * @param string $destination Destination directory
 * @param array $allowedTypes Allowed file types
 * @param int $maxSize Maximum file size in bytes
 * @return array Result array with status and message/filename
 */
function uploadFile($file, $destination, $allowedTypes = [], $maxSize = 5242880) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : 'Unknown upload error';
        return ['status' => false, 'message' => $errorMessage];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'File is too large. Maximum size is ' . ($maxSize / 1024 / 1024) . 'MB'];
    }
    
    // Check file type if specified
    if (!empty($allowedTypes)) {
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            return ['status' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)];
        }
    }
    
    // Generate a unique file name
    $fileName = generateUniqueFileName($file['name']);
    $targetPath = $destination . '/' . $fileName;
    
    // Create directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Move the file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'fileName' => $fileName];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Generate pagination links
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $url Base URL for pagination links
 * @return string HTML for pagination links
 */
function generatePagination($currentPage, $totalPages, $url) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $url . '?page=' . ($currentPage - 1) . '" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>';
    } else {
        $html .= '<li class="page-item disabled">
                    <span class="page-link" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </span>
                </li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $url . '?page=' . ($currentPage + 1) . '" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>';
    } else {
        $html .= '<li class="page-item disabled">
                    <span class="page-link" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </span>
                </li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Format date for display
 * @param string $dateString Date string
 * @param string $format Date format (default: 'd/m/Y')
 * @return string Formatted date
 */
function formatDate($dateString, $format = 'd/m/Y') {
    if (!$dateString || $dateString == 'N/A' || $dateString == '-') {
        return $dateString ?: '-';
    }
    
    try {
        $date = new DateTime($dateString);
        return $date->format($format);
    } catch (Exception $e) {
        // If the date can't be parsed, return the original string or a placeholder
        error_log("Date parsing error: " . $e->getMessage() . " for value: " . $dateString);
        return '-';
    }
}

/**
 * Format date and time
 * @param string $dateTime Date and time string
 * @param string $format Date and time format (default: 'd M Y h:i A')
 * @return string Formatted date and time
 */
function formatDateTime($dateTime, $format = 'd M Y h:i A') {
    if (empty($dateTime)) {
        return '-';
    }
    return date($format, strtotime($dateTime));
}

/**
 * Format currency for display
 * @param float $amount Amount
 * @param string $currency Currency symbol (default: 'đ')
 * @return string Formatted currency
 */
function formatCurrency($amount, $currency = 'đ') {
    return number_format($amount, 0, ',', '.') . ' ' . $currency;
}

/**
 * Get user role name
 * @param string $role Role code
 * @return string Role name
 */
function getRoleName($role) {
    $roles = [
        'admin' => 'Administrator',
        'staff' => 'Staff',
        'student' => 'Student'
    ];
    
    return isset($roles[$role]) ? $roles[$role] : 'Unknown';
}

/**
 * Generate a random password
 * @param int $length Password length
 * @return string Random password
 */
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_';
    $password = '';
    
    for ($i = 0; $length > $i; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Send email
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body
 * @param array $headers Additional headers
 * @return boolean Success status
 */
function sendEmail($to, $subject, $body, $headers = []) {
    $defaultHeaders = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Dormitory Management System <dormitory@example.com>'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    return mail($to, $subject, $body, implode("\r\n", $allHeaders));
}

/**
 * Log activity
 * @param string $action Action performed
 * @param string $details Action details
 * @param int $userId User ID
 * @param string $userRole User role
 * @return void
 */
function logActivity($action, $details, $userId = null, $userRole = null) {
    // Simplified version without using activity_logs table
    error_log("Activity: $action by user ID: $userId, role: $userRole - $details");
}

/**
 * Check if a student exists
 * @param string $studentId Student ID
 * @return boolean True if student exists, false otherwise
 */
function studentExists($studentId) {
    global $conn;
    
    $sql = "SELECT id FROM users WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

/**
 * Update room occupancy
 * @param int $roomId Room ID
 * @return boolean Success status
 */
function updateRoomOccupancy($roomId) {
    global $conn;
    
    // Count active assignments for this room
    $sql = "SELECT COUNT(*) as count FROM room_assignments 
            WHERE room_id = ? AND status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $currentOccupancy = $row['count'];
    $stmt->close();
    
    // Update room occupancy
    $sql = "UPDATE rooms SET current_occupancy = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $currentOccupancy, $roomId);
    $status = $stmt->execute();
    $stmt->close();
    
    // Update room status
    $sql = "SELECT capacity FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $capacity = $row['capacity'];
    $stmt->close();
    
    $status = 'available';
    if ($currentOccupancy >= $capacity) {
        $status = 'occupied';
    } else if ($currentOccupancy == 0) {
        $status = 'available';
    }
    
    $sql = "UPDATE rooms SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $roomId);
    $status = $stmt->execute() && $status;
    $stmt->close();
    
    return $status;
}

/**
 * Generate unique ID
 * @param string $prefix Prefix for ID (default: '')
 * @param int $length Length of random part (default: 8)
 * @return string Unique ID
 */
function generateUniqueId($prefix = '', $length = 8) {
    $bytes = random_bytes(ceil($length / 2));
    $randomStr = substr(bin2hex($bytes), 0, $length);
    return $prefix . strtoupper($randomStr);
}

/**
 * Generate assignment number
 * @return string Assignment number
 */
function generateAssignmentNumber() {
    $prefix = 'ASG';
    $year = date('Y');
    $month = date('m');
    $random = generateUniqueId('', 4);
    
    return $prefix . '-' . $year . $month . '-' . $random;
}

/**
 * Format file size
 * @param int $bytes Size in bytes
 * @param int $precision Precision (default: 2)
 * @return string Formatted size
 */
function formatSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Check if user is logged in
 * @return boolean True if user is logged in, false otherwise
 */
function isLoggedIn() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    return false;
}

/**
 * Check if user has a specific role
 * @param string $role Role to check
 * @return boolean True if user has the role, false otherwise
 */
function hasRole($role) {
    if (!isLoggedIn() || !isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] == $role;
}

/**
 * Check if the current user has admin privileges
 * 
 * @return bool True if user has admin role, false otherwise
 */
function isAdmin() {
    if (!isLoggedIn() || !isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] == 'admin';
}

/**
 * Get user by ID
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    global $conn;
    
    $userId = (int)$userId;
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get student by user ID (now just gets user with role='student')
 * @param int $userId User ID
 * @return array|null Student data or null if not found
 */
function getStudentByUserId($userId) {
    global $conn;
    
    $userId = (int)$userId;
    
    $sql = "SELECT * FROM users WHERE id = ? AND role = 'student'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get room details
 * @param int $roomId Room ID
 * @return array|null Room data or null if not found
 */
function getRoomDetails($roomId) {
    global $conn;
    
    $roomId = (int)$roomId;
    
    $sql = "SELECT * FROM rooms WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Check if student has current room assignment
 * @param int $userId User ID
 * @return boolean True if student has current room assignment, false otherwise
 */
function hasCurrentRoomAssignment($userId) {
    global $conn;
    
    $userId = (int)$userId;
    
    $sql = "SELECT COUNT(*) as count FROM room_assignments 
            WHERE user_id = ? AND status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return (int)$row['count'] > 0;
}

/**
 * Get student's current room assignment
 * @param int $userId User ID
 * @return array|null Room assignment data or null if not found
 */
function getCurrentRoomAssignment($userId) {
    global $conn;
    
    $userId = (int)$userId;
    
    $sql = "SELECT ra.*, r.room_number, r.floor, r.building_name
            FROM room_assignments ra 
            JOIN rooms r ON ra.room_id = r.id 
            WHERE ra.user_id = ? AND ra.status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Calculate room occupancy percentage
 * @return array Room occupancy statistics
 */
function calculateRoomOccupancy() {
    global $conn;
    
    $sql = "SELECT 
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
            COUNT(*) as total
            FROM rooms";
    
    $result = $conn->query($sql);
    $stats = $result->fetch_assoc();
    
    $stats['occupancy_percentage'] = ($stats['total'] > 0) ? ($stats['occupied'] / $stats['total'] * 100) : 0;
    $stats['occupancy_percentage'] = round($stats['occupancy_percentage'], 2);
    
    return $stats;
}

/**
 * Get settings value
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null) {
    // Since we've simplified the database and might not have a settings table anymore,
    // we'll return default values for common settings
    $commonSettings = [
        'dormitory_name' => 'Hệ thống quản lý ký túc xá',
        'system_theme' => 'light',
        'contact_email' => 'admin@dormitory.com',
        'contact_phone' => '0123456789',
        'academic_year' => '2024-2025',
        'allow_student_registration' => 'yes',
        'maintenance_contact' => 'maintenance@dormitory.com'
    ];
    
    // Check if the key exists in our hardcoded settings
    if (array_key_exists($key, $commonSettings)) {
        return $commonSettings[$key];
    }
    
    // Return the default value if the key doesn't exist
    return $default;
}

/**
 * Get notification count (simplified version without the messages table)
 * @param int $userId User ID
 * @return int Notification count
 */
function getNotificationCount($userId) {
    // Luôn trả về 0 vì chúng ta đã loại bỏ chức năng thông báo và tin nhắn
    return 0;
}
?>