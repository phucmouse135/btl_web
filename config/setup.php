<?php
// Include database configuration
require_once 'database.php';

// Function to check if table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Start transaction
$conn->begin_transaction();

try {
    // Create combined users table (incorporating student data)
    if (!tableExists($conn, 'users')) {
        $conn->query("CREATE TABLE users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL,
            role ENUM('admin', 'staff', 'student') NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            profile_pic VARCHAR(255) DEFAULT 'default.jpg',
            -- Student-specific fields (nullable for admin/staff)
            student_id VARCHAR(20) NULL UNIQUE,
            gender ENUM('male', 'female', 'other') NULL,
            address TEXT NULL,
            department VARCHAR(100) NULL,
            year_of_study INT(1) NULL,
            student_status ENUM('active', 'inactive', 'graduated') NULL,
            -- Common fields
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY (username),
            UNIQUE KEY (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "Table 'users' created<br>";
    }
    
    // Create rooms table (simplified)
    if (!tableExists($conn, 'rooms')) {
        $conn->query("CREATE TABLE rooms (
            id INT(11) NOT NULL AUTO_INCREMENT,
            building_name VARCHAR(100) NOT NULL,
            room_number VARCHAR(10) NOT NULL,
            floor INT(2) NOT NULL,
            capacity INT(2) NOT NULL,
            current_occupancy INT(2) DEFAULT 0,
            monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY building_room (building_name, room_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "Table 'rooms' created<br>";
    }
    
    // Create room_assignments table (simplified)
    if (!tableExists($conn, 'room_assignments')) {
        $conn->query("CREATE TABLE room_assignments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            room_id INT(11) NOT NULL,
            assignment_number VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            monthly_rent DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'active', 'completed', 'terminated') DEFAULT 'pending',
            assigned_by INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (assignment_number),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "Table 'room_assignments' created<br>";
    }
    
    // Create maintenance_requests table
    if (!tableExists($conn, 'maintenance_requests')) {
        $conn->query("CREATE TABLE maintenance_requests (
            id INT(11) NOT NULL AUTO_INCREMENT,
            room_id INT(11) NOT NULL,
            reported_by INT(11) NOT NULL,
            issue_type VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            request_date DATE NOT NULL,
            priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
            status ENUM('pending', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
            assigned_to INT(11) NULL,
            resolution TEXT NULL,
            completed_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "Table 'maintenance_requests' created<br>";
    }
    
    // Insert default admin user if users table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $adminUsername = 'admin';
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminEmail = 'admin@example.com';
        
        $conn->query("INSERT INTO users (username, password, email, role, first_name, last_name, phone) 
                     VALUES ('$adminUsername', '$adminPassword', '$adminEmail', 'admin', 'Admin', 'User', '1234567890')");
        
        echo "Default admin user created (Username: admin, Password: admin123)<br>";
        
        // Insert demo student user
        $studentUsername = 'student';
        $studentPassword = password_hash('student123', PASSWORD_DEFAULT);
        $studentEmail = 'student@example.com';
        
        $conn->query("INSERT INTO users (
                     username, password, email, role, first_name, last_name, phone,
                     student_id, gender, address, department, year_of_study, student_status
                     ) VALUES (
                     '$studentUsername', '$studentPassword', '$studentEmail', 'student', 
                     'John', 'Doe', '0987654321', 'STU001', 'male', '123 Main St', 
                     'Computer Science', 2, 'active'
                     )");
        
        echo "Demo student user created (Username: student, Password: student123)<br>";
        
        // Insert demo rooms
        $conn->query("INSERT INTO rooms (building_name, room_number, floor, capacity, monthly_rent, status) VALUES 
                     ('Building A', '101', 1, 2, 500.00, 'available'),
                     ('Building A', '102', 1, 2, 500.00, 'available'),
                     ('Building B', '201', 2, 4, 800.00, 'available')");
        
        echo "Demo rooms created<br>";
    }
    
    // Commit transaction
    $conn->commit();
    echo "Database setup completed successfully!";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error setting up database: " . $e->getMessage();
}

$conn->close();
?>