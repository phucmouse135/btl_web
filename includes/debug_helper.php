<?php
/**
 * Debug helper functions
 */

// Function to safely dump variables for debugging
function debug_dump($var, $die = false) {
    echo '<pre style="background-color:#f5f5f5;color:#333;padding:10px;margin:10px;border:1px solid #ccc;border-radius:5px;">';
    var_dump($var);
    echo '</pre>';
    if ($die) die();
}

// Function to test database connection and query
function debug_query($conn, $query, $params = []) {
    echo '<div style="background-color:#f5f5f5;color:#333;padding:10px;margin:10px;border:1px solid #ccc;border-radius:5px;">';
    echo '<h4>Query Debug:</h4>';
    echo '<p><strong>Query:</strong> ' . htmlspecialchars($query) . '</p>';
    
    if (!empty($params)) {
        echo '<p><strong>Parameters:</strong></p><ul>';
        foreach ($params as $key => $value) {
            echo '<li>' . $key . ' => ' . htmlspecialchars($value) . '</li>';
        }
        echo '</ul>';
    }
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo '<p style="color:red;"><strong>Prepare Error:</strong> ' . mysqli_error($conn) . '</p>';
    } else {
        echo '<p style="color:green;"><strong>Prepare:</strong> Success</p>';
        // Close the statement as we're just testing
        mysqli_stmt_close($stmt);
    }
    
    echo '</div>';
}

// Function to check if table exists and show structure
function debug_table($conn, $table_name) {
    echo '<div style="background-color:#f5f5f5;color:#333;padding:10px;margin:10px;border:1px solid #ccc;border-radius:5px;">';
    echo '<h4>Table Debug: ' . htmlspecialchars($table_name) . '</h4>';
    
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table_name) . "'");
    
    if ($check_table && mysqli_num_rows($check_table) > 0) {
        echo '<p style="color:green;"><strong>Table exists:</strong> Yes</p>';
        
        $describe_table = mysqli_query($conn, "DESCRIBE " . mysqli_real_escape_string($conn, $table_name));
        if ($describe_table) {
            echo '<p><strong>Table Structure:</strong></p>';
            echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            
            while ($row = mysqli_fetch_assoc($describe_table)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['Field']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                echo '<td>' . (isset($row['Default']) ? htmlspecialchars($row['Default']) : 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($row['Extra']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:red;"><strong>Error describing table:</strong> ' . mysqli_error($conn) . '</p>';
        }
    } else {
        echo '<p style="color:red;"><strong>Table exists:</strong> No</p>';
    }
    
    echo '</div>';
}
?>
