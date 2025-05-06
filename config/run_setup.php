<?php
// Kết nối đến cơ sở dữ liệu
require_once 'database.php';

// Thiết lập transaction để đảm bảo tính nhất quán
$conn->begin_transaction();

try {
    // Kiểm tra và xóa các bảng cũ nếu tồn tại
    // Xóa các bảng theo thứ tự từ các bảng con đến bảng cha để tránh lỗi ràng buộc
    
    // Xóa các ràng buộc khóa ngoại trước
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Xóa các bảng cũ nếu tồn tại
    $tables = [
        'maintenance_requests',
        'room_assignments',
        'rooms',
        'buildings',
        'room_types',
        'users'
    ];
    
    foreach ($tables as $table) {
        $conn->query("DROP TABLE IF EXISTS $table");
    }
    
    // Bật lại kiểm tra khóa ngoại
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Tạo bảng users
    $conn->query("CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `password` varchar(255) NOT NULL,
      `email` varchar(100) NOT NULL,
      `role` enum('admin','staff','student') NOT NULL,
      `first_name` varchar(50) NOT NULL,
      `last_name` varchar(50) NOT NULL,
      `phone` varchar(20) DEFAULT NULL,
      `profile_pic` varchar(255) DEFAULT 'default.jpg',
      `student_id` varchar(20) DEFAULT NULL,
      `gender` enum('male','female','other') DEFAULT NULL,
      `address` text DEFAULT NULL,
      `department` varchar(100) DEFAULT NULL,
      `year_of_study` int(1) DEFAULT NULL,
      `student_status` enum('active','inactive','graduated') DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      `last_login` timestamp NULL DEFAULT NULL,
      `status` enum('active','inactive') DEFAULT 'active',
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`),
      UNIQUE KEY `email` (`email`),
      UNIQUE KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Tạo bảng rooms
    $conn->query("CREATE TABLE `rooms` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `building_name` varchar(100) NOT NULL,
      `room_number` varchar(10) NOT NULL,
      `floor` int(2) NOT NULL,
      `capacity` int(2) NOT NULL,
      `current_occupancy` int(2) DEFAULT 0,
      `monthly_rent` decimal(10,2) NOT NULL DEFAULT 0.00,
      `status` enum('available','occupied','maintenance') DEFAULT 'available',
      `description` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `building_room` (`building_name`,`room_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Tạo bảng room_assignments
    $conn->query("CREATE TABLE `room_assignments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `room_id` int(11) NOT NULL,
      `assignment_number` varchar(20) NOT NULL,
      `start_date` date NOT NULL,
      `end_date` date DEFAULT NULL,
      `monthly_rent` decimal(10,2) NOT NULL,
      `status` enum('pending','active','completed','terminated') DEFAULT 'pending',
      `assigned_by` int(11) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `assignment_number` (`assignment_number`),
      KEY `user_id` (`user_id`),
      KEY `room_id` (`room_id`),
      KEY `assigned_by` (`assigned_by`),
      CONSTRAINT `room_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `room_assignments_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
      CONSTRAINT `room_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Tạo bảng maintenance_requests
    $conn->query("CREATE TABLE `maintenance_requests` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `room_id` int(11) NOT NULL,
      `reported_by` int(11) NOT NULL,
      `issue_type` varchar(100) NOT NULL,
      `description` text NOT NULL,
      `request_date` date NOT NULL,
      `priority` enum('low','medium','high','emergency') DEFAULT 'medium',
      `status` enum('pending','in_progress','completed','rejected') DEFAULT 'pending',
      `assigned_to` int(11) DEFAULT NULL,
      `resolution` text DEFAULT NULL,
      `completed_date` date DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `room_id` (`room_id`),
      KEY `reported_by` (`reported_by`),
      KEY `assigned_to` (`assigned_to`),
      CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
      CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    // Thêm dữ liệu mẫu
    // Thêm users
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);
    $studentPassword = password_hash('student123', PASSWORD_DEFAULT);
    
    $conn->query("INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `first_name`, `last_name`, `phone`, `profile_pic`, `student_id`, `gender`, `address`, `department`, `year_of_study`, `student_status`, `created_at`, `updated_at`, `last_login`, `status`) VALUES
    (1, 'admin', '$adminPassword', 'admin@dorm.com', 'admin', 'Admin', 'User', '1234567890', 'default.jpg', NULL, NULL, NULL, NULL, NULL, NULL, CURRENT_TIMESTAMP, NULL, NULL, 'active'),
    (2, 'staff', '$staffPassword', 'staff@dorm.com', 'staff', 'Staff', 'Member', '2345678901', 'default.jpg', NULL, NULL, NULL, NULL, NULL, NULL, CURRENT_TIMESTAMP, NULL, NULL, 'active'),
    (3, 'student1', '$studentPassword', 'student1@edu.com', 'student', 'John', 'Doe', '3456789012', 'default.jpg', 'STU2023001', 'male', '123 College St, Apt 1', 'Computer Science', 2, 'active', CURRENT_TIMESTAMP, NULL, NULL, 'active'),
    (4, 'student2', '$studentPassword', 'student2@edu.com', 'student', 'Jane', 'Smith', '4567890123', 'default.jpg', 'STU2023002', 'female', '456 University Ave, Apt 2B', 'Business Administration', 3, 'active', CURRENT_TIMESTAMP, NULL, NULL, 'active'),
    (5, 'student3', '$studentPassword', 'student3@edu.com', 'student', 'Michael', 'Johnson', '5678901234', 'default.jpg', 'STU2023003', 'male', '789 Campus Rd, Apt 3C', 'Engineering', 1, 'active', CURRENT_TIMESTAMP, NULL, NULL, 'active'),
    (6, 'student4', '$studentPassword', 'student4@edu.com', 'student', 'Emily', 'Williams', '6789012345', 'default.jpg', 'STU2023004', 'female', '101 Academic Lane, Apt 4D', 'Arts and Humanities', 4, 'active', CURRENT_TIMESTAMP, NULL, NULL, 'active'),
    (7, 'student5', '$studentPassword', 'student5@edu.com', 'student', 'David', 'Brown', '7890123456', 'default.jpg', 'STU2023005', 'male', '202 Scholar Way, Apt 5E', 'Medicine', 2, 'active', CURRENT_TIMESTAMP, NULL, NULL, 'active')");
    
    // Thêm rooms
    $conn->query("INSERT INTO `rooms` (`id`, `building_name`, `room_number`, `floor`, `capacity`, `current_occupancy`, `monthly_rent`, `status`, `description`, `created_at`, `updated_at`) VALUES
    (1, 'Building A', '101', 1, 2, 2, 500.00, 'occupied', 'Twin room with shared bathroom', CURRENT_TIMESTAMP, NULL),
    (2, 'Building A', '102', 1, 2, 1, 500.00, 'occupied', 'Twin room with shared bathroom', CURRENT_TIMESTAMP, NULL),
    (3, 'Building A', '201', 2, 1, 1, 600.00, 'occupied', 'Single room with private bathroom', CURRENT_TIMESTAMP, NULL),
    (4, 'Building A', '202', 2, 1, 0, 600.00, 'available', 'Single room with private bathroom', CURRENT_TIMESTAMP, NULL),
    (5, 'Building B', '101', 1, 4, 1, 400.00, 'occupied', 'Quad room with shared facilities', CURRENT_TIMESTAMP, NULL),
    (6, 'Building B', '201', 2, 2, 0, 550.00, 'available', 'Twin room with kitchenette', CURRENT_TIMESTAMP, NULL),
    (7, 'Building B', '301', 3, 1, 0, 650.00, 'maintenance', 'Single room with private bathroom (undergoing repairs)', CURRENT_TIMESTAMP, NULL)");
    
    // Thêm room_assignments
    $conn->query("INSERT INTO `room_assignments` (`id`, `user_id`, `room_id`, `assignment_number`, `start_date`, `end_date`, `monthly_rent`, `status`, `assigned_by`, `created_at`, `updated_at`) VALUES
    (1, 3, 1, 'RA2023001', '2023-09-01', '2024-06-30', 500.00, 'active', 1, CURRENT_TIMESTAMP, NULL),
    (2, 4, 1, 'RA2023002', '2023-09-01', '2024-06-30', 500.00, 'active', 1, CURRENT_TIMESTAMP, NULL),
    (3, 5, 2, 'RA2023003', '2023-09-01', '2024-06-30', 500.00, 'active', 1, CURRENT_TIMESTAMP, NULL),
    (4, 6, 3, 'RA2023004', '2023-09-01', '2024-06-30', 600.00, 'active', 1, CURRENT_TIMESTAMP, NULL),
    (5, 7, 5, 'RA2023005', '2023-09-01', '2024-06-30', 400.00, 'active', 1, CURRENT_TIMESTAMP, NULL)");
    
    // Thêm maintenance_requests
    $conn->query("INSERT INTO `maintenance_requests` (`id`, `room_id`, `reported_by`, `issue_type`, `description`, `request_date`, `priority`, `status`, `assigned_to`, `resolution`, `completed_date`, `created_at`, `updated_at`) VALUES
    (1, 1, 3, 'Plumbing', 'Sink is leaking', '2025-05-01', 'medium', 'completed', 2, 'Fixed leaking pipe under sink', '2025-05-04', CURRENT_TIMESTAMP, NULL),
    (2, 2, 4, 'Electrical', 'Light fixture not working', '2025-05-03', 'medium', 'in_progress', 2, NULL, NULL, CURRENT_TIMESTAMP, NULL),
    (3, 3, 5, 'Furniture', 'Desk chair is broken', '2025-05-05', 'low', 'pending', NULL, NULL, NULL, CURRENT_TIMESTAMP, NULL),
    (4, 7, 1, 'HVAC', 'Heating system malfunction', '2025-04-26', 'high', 'in_progress', 2, 'Ordered replacement parts', NULL, CURRENT_TIMESTAMP, NULL)");
    
    // Hoàn thành transaction
    $conn->commit();
    
    echo "<div class='alert alert-success'>
            <p><strong>Thành công!</strong> Cơ sở dữ liệu đã được thiết lập lại thành công với cấu trúc mới.</p>
            <p>Bạn có thể <a href='/LTW/dashboard.php' class='alert-link'>quay lại bảng điều khiển</a> hoặc kiểm tra <a href='/LTW/views/admin/rooms/list.php' class='alert-link'>danh sách phòng</a> để xác nhận.</p>
         </div>";
    
} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    $conn->rollback();
    echo "<div class='alert alert-danger'>
            <p><strong>Lỗi!</strong> Không thể thiết lập cơ sở dữ liệu: " . $e->getMessage() . "</p>
            <p>Vui lòng liên hệ quản trị viên để được hỗ trợ.</p>
         </div>";
}

// Đóng kết nối
$conn->close();
?>