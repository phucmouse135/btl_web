<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'dormitory_db';

// Create database connection
try {
    // Check if database exists first
    $temp_conn = new mysqli($host, $username, $password);
    
    // Check temporary connection
    if ($temp_conn->connect_error) {
        throw new Exception("Connection to MySQL server failed: " . $temp_conn->connect_error);
    }
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($temp_conn->query($sql) === TRUE) {
        // Database created successfully or already exists
        error_log("Database '$database' confirmed to exist or was created.");
    } else {
        throw new Exception("Error creating database: " . $temp_conn->error);
    }
    
    // Close temporary connection
    $temp_conn->close();
    
    // Connect with database specified
    $conn = new mysqli($host, $username, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set character set
    $conn->set_charset("utf8mb4");
    
    error_log("Successfully connected to database '$database'");
} catch (Exception $e) {
    // Log error with full details
    error_log("Database connection error: " . $e->getMessage());
    
    // Display user-friendly error message
    echo "<div style='color:red; padding:20px; margin:20px; border:1px solid red;'>
        <h3>Database Connection Error</h3>
        <p>Unable to connect to the database. Please check that:</p>
        <ol>
            <li>MySQL server is running in XAMPP Control Panel</li>
            <li>The database credentials are correct</li>
            <li>The database '{$database}' exists</li>
        </ol>
        <p>Technical details: " . $e->getMessage() . "</p>
    </div>";
    exit();
}

/**
 * Require user to be logged in to access page
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /LTW/index.php");
        exit();
    }
}
?>