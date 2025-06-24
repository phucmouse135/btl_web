<?php
// This file provides quick access to the installation guides
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dormitory Management System - Installation Guides</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .options {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }
        .option {
            flex: 1;
            min-width: 250px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            border-left: 5px solid #3498db;
        }
        .option h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 10px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #777;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dormitory Management System - Installation Guides</h1>
        
        <p>Welcome to the Dormitory Management System. Please choose your preferred installation guide format:</p>
        
        <div class="options">
            <div class="option">
                <h2>HTML Installation Guide</h2>
                <p>Interactive HTML guide with detailed instructions and styling.</p>
                <a href="installation_guide.html" class="btn">View HTML Guide</a>
            </div>
            
            <div class="option">
                <h2>Vietnamese Markdown Guide</h2>
                <p>Simple text-based installation guide in Vietnamese.</p>
                <a href="INSTALLATION_GUIDE.md" class="btn">View Vietnamese Guide</a>
            </div>
            
            <div class="option">
                <h2>English Markdown Guide</h2>
                <p>Simple text-based installation guide in English.</p>
                <a href="INSTALLATION_GUIDE_EN.md" class="btn">View English Guide</a>
            </div>
        </div>

        <div class="options" style="margin-top: 20px;">
            <div class="option">
                <h2>Continue to Login</h2>
                <p>If you've already completed installation, continue to the login page.</p>
                <a href="index.php" class="btn">Go to Login</a>
            </div>
            
            <div class="option">
                <h2>Setup Database</h2>
                <p>Run the setup script to initialize or reset your database.</p>
                <a href="config/setup.php" class="btn">Setup Database</a>
            </div>
        </div>
        
        <div class="footer">
            <p>© 2025 Dormitory Management System</p>
        </div>
    </div>
</body>
</html>
