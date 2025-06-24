# Dormitory Management System Installation Guide

## System Requirements

- Web server: [XAMPP](https://www.apachefriends.org/download.html) (including Apache, MySQL, PHP)
- PHP version 7.4 or higher
- MySQL version 5.7 or higher
- Modern web browser (Chrome, Firefox, Edge, Safari)

## Installation Steps

### 1. Install XAMPP

1. Download XAMPP from the [official website](https://www.apachefriends.org/download.html)
2. Install XAMPP according to your operating system:
   - Windows: Run the installer and follow the steps in the wizard
   - macOS: Open the .dmg file and drag XAMPP to the Applications folder
   - Linux: Give executable permission to the installer (`chmod +x xampp-linux-*-installer.run`) and run it (`sudo ./xampp-linux-*-installer.run`)

### 2. Start XAMPP

1. Open XAMPP Control Panel:
   - Windows: From Start menu or desktop icon
   - macOS: From the Applications folder
   - Linux: Run the command `sudo /opt/lampp/manager-linux-x64.run` or similar

2. Start the services:
   - Apache
   - MySQL

### 3. Download and Install the Project

#### Method 1: Using Git (if available)

1. Open terminal or command prompt
2. Navigate to the htdocs folder in your XAMPP installation:
   ```
   cd [path_to_xampp]/htdocs
   ```
   - Windows: `cd C:\xampp\htdocs`
   - macOS: `cd /Applications/XAMPP/xampp/htdocs`
   - Linux: `cd /opt/lampp/htdocs`

3. Clone the project from repository (if available):
   ```
   git clone [repository_url] LTW
   ```

#### Method 2: Manual Copy

1. Download the project source code as a ZIP file
2. Extract the ZIP file to the htdocs folder in your XAMPP installation:
   - Windows: `C:\xampp\htdocs\LTW`
   - macOS: `/Applications/XAMPP/xampp/htdocs/LTW`
   - Linux: `/opt/lampp/htdocs/LTW`

### 4. Database Setup

#### Method 1: Using the automatic setup page

1. Open your web browser and navigate to:
   ```
   http://localhost/LTW/config/setup.php
   ```

2. The system will automatically create the database and required tables
3. If you want to reinstall the database with sample data, navigate to:
   ```
   http://localhost/LTW/config/run_setup.php
   ```

#### Method 2: Manual setup

1. Open your web browser and navigate to phpMyAdmin:
   ```
   http://localhost/phpmyadmin
   ```

2. Create a new database named `dormitory_db`
   - Click on the "Databases" tab
   - Enter "dormitory_db" in the "Database name" field
   - Select "utf8mb4_unicode_ci" as the collation
   - Click "Create"

3. After creating the database, access the setup page to create tables and sample data:
   ```
   http://localhost/LTW/config/setup.php
   ```

### 5. Configure Database Connection (If Needed)

If you need to change the database connection information (e.g., MySQL password is not default), edit the following files:

1. Open the file `config/database.php` and update the connection information:
   ```php
   $host = 'localhost';      // MySQL server address, usually localhost
   $username = 'root';       // MySQL username
   $password = '';           // MySQL password
   $database = 'dormitory_db'; // Database name
   ```

2. Similarly, open the file `includes/db_connection.php` and update the connection information if needed.

### 6. Access the System

1. Open your web browser and navigate to:
   ```
   http://localhost/LTW/
   ```

2. Log in with the default accounts:
   - Admin:
     - Username: `admin`
     - Password: `admin123`
   - Sample student:
     - Username: `student1`
     - Password: `student123`
   - Staff:
     - Username: `staff`
     - Password: `staff123`

## Troubleshooting

### Cannot connect to the database

1. Ensure MySQL service is running in XAMPP Control Panel
2. Check the connection information in `config/database.php` and `includes/db_connection.php`
3. Make sure the `dormitory_db` database has been created

### "Access denied" error when connecting to MySQL

1. Check the MySQL username and password in the configuration files
2. If you have changed the MySQL root password, update the information in the configuration files

### PHP error page displayed

1. Ensure Apache is running in XAMPP Control Panel
2. Check PHP version (requires 7.4 or higher)
3. Make sure directories and files have appropriate read permissions

### File access permission issues

For Linux and macOS, ensure the `uploads` and `uploads/profile_pics` directories have write permissions:
```
chmod -R 755 /path/to/htdocs/LTW
chmod -R 777 /path/to/htdocs/LTW/uploads
```

## Project Structure

- `/api` - APIs and data processing endpoints
- `/assets` - Static resources like CSS, JavaScript, images
- `/config` - System configuration files
- `/includes` - PHP files reused across multiple pages
- `/views` - User interface files
- `/uploads` - Directory for storing uploaded files

## Security

1. After installation, change the password for the default admin account
2. Ensure the XAMPP directory is secured according to the provider's guidelines
3. In production environments, consider additional measures such as HTTPS, firewalls, etc.

---

This guide was created on: 06/25/2025
