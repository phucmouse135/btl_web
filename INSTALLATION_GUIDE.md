# Hướng Dẫn Cài Đặt và Chạy Hệ Thống Quản Lý Ký Túc Xá

## Yêu Cầu Hệ Thống

- Web server: [XAMPP](https://www.apachefriends.org/download.html) (bao gồm Apache, MySQL, PHP)
- PHP phiên bản 7.4 trở lên
- MySQL phiên bản 5.7 trở lên
- Trình duyệt web hiện đại (Chrome, Firefox, Edge, Safari)

## Các Bước Cài Đặt

### 1. Cài Đặt XAMPP

1. Tải xuống XAMPP từ [trang web chính thức](https://www.apachefriends.org/download.html)
2. Cài đặt XAMPP theo hướng dẫn dựa trên hệ điều hành của bạn:
   - Windows: Chạy tệp cài đặt và làm theo các bước trong trình hướng dẫn
   - macOS: Mở tệp .dmg và kéo XAMPP vào thư mục Applications
   - Linux: Cấp quyền thực thi cho tệp cài đặt (`chmod +x xampp-linux-*-installer.run`) và chạy nó (`sudo ./xampp-linux-*-installer.run`)

### 2. Khởi Động XAMPP

1. Mở XAMPP Control Panel:
   - Windows: Từ menu Start hoặc biểu tượng trên desktop
   - macOS: Từ thư mục Applications
   - Linux: Chạy lệnh `sudo /opt/lampp/manager-linux-x64.run` hoặc tương tự

2. Khởi động các dịch vụ:
   - Apache
   - MySQL

### 3. Tải và Cài Đặt Dự Án

#### Cách 1: Sử dụng Git (nếu có)

1. Mở terminal hoặc command prompt
2. Di chuyển đến thư mục htdocs trong cài đặt XAMPP:
   ```
   cd [đường_dẫn_đến_xampp]/htdocs
   ```
   - Windows: `cd C:\xampp\htdocs`
   - macOS: `cd /Applications/XAMPP/xampp/htdocs`
   - Linux: `cd /opt/lampp/htdocs`

3. Sao chép dự án từ repository (nếu có):
   ```
   git clone [url_repository] LTW
   ```

#### Cách 2: Sao chép thủ công

1. Tải xuống mã nguồn dự án ở dạng file ZIP
2. Giải nén file ZIP vào thư mục htdocs trong cài đặt XAMPP:
   - Windows: `C:\xampp\htdocs\LTW`
   - macOS: `/Applications/XAMPP/xampp/htdocs/LTW`
   - Linux: `/opt/lampp/htdocs/LTW`

### 4. Thiết Lập Cơ Sở Dữ Liệu

#### Cách 1: Sử dụng trang thiết lập tự động

1. Mở trình duyệt web và truy cập:
   ```
   http://localhost/LTW/config/setup.php
   ```

2. Hệ thống sẽ tự động tạo cơ sở dữ liệu và các bảng cần thiết
3. Nếu muốn cài đặt lại cơ sở dữ liệu với dữ liệu mẫu, truy cập:
   ```
   http://localhost/LTW/config/run_setup.php
   ```

#### Cách 2: Thiết lập thủ công

1. Mở trình duyệt web và truy cập phpMyAdmin:
   ```
   http://localhost/phpmyadmin
   ```

2. Tạo cơ sở dữ liệu mới với tên `dormitory_db`
   - Click vào tab "Databases" (Cơ sở dữ liệu)
   - Nhập "dormitory_db" vào trường "Database name" (Tên cơ sở dữ liệu)
   - Chọn "utf8mb4_unicode_ci" làm mã hóa
   - Click "Create" (Tạo)

3. Sau khi tạo cơ sở dữ liệu, truy cập trang thiết lập để tạo các bảng và dữ liệu mẫu:
   ```
   http://localhost/LTW/config/setup.php
   ```

### 5. Cấu Hình Kết Nối Cơ Sở Dữ Liệu (Nếu Cần)

Nếu bạn cần thay đổi thông tin kết nối cơ sở dữ liệu (ví dụ: mật khẩu MySQL không phải là mặc định), hãy chỉnh sửa các file sau:

1. Mở file `config/database.php` và cập nhật thông tin kết nối:
   ```php
   $host = 'localhost';      // Địa chỉ máy chủ MySQL, thường là localhost
   $username = 'root';       // Tên người dùng MySQL
   $password = '';           // Mật khẩu MySQL
   $database = 'dormitory_db'; // Tên cơ sở dữ liệu
   ```

2. Tương tự, mở file `includes/db_connection.php` và cập nhật thông tin kết nối nếu cần.

### 6. Truy Cập Hệ Thống

1. Mở trình duyệt web và truy cập:
   ```
   http://localhost/LTW/
   ```

2. Đăng nhập với các tài khoản mặc định:
   - Admin:
     - Tên đăng nhập: `admin`
     - Mật khẩu: `admin123`
   - Sinh viên mẫu:
     - Tên đăng nhập: `student1`
     - Mật khẩu: `student123`
   - Nhân viên:
     - Tên đăng nhập: `staff`
     - Mật khẩu: `staff123`

## Xử Lý Sự Cố

### Không kết nối được đến cơ sở dữ liệu

1. Đảm bảo dịch vụ MySQL đang chạy trong XAMPP Control Panel
2. Kiểm tra thông tin kết nối trong `config/database.php` và `includes/db_connection.php`
3. Đảm bảo cơ sở dữ liệu `dormitory_db` đã được tạo

### Lỗi "Access denied" khi kết nối MySQL

1. Kiểm tra tên người dùng và mật khẩu MySQL trong các file cấu hình
2. Nếu bạn đã thay đổi mật khẩu root MySQL, hãy cập nhật thông tin trong file cấu hình

### Trang hiển thị lỗi PHP

1. Đảm bảo Apache đang chạy trong XAMPP Control Panel
2. Kiểm tra phiên bản PHP (yêu cầu 7.4 trở lên)
3. Đảm bảo các thư mục và file có quyền đọc phù hợp

### Các vấn đề về quyền truy cập file

Đối với Linux và macOS, đảm bảo thư mục `uploads` và `uploads/profile_pics` có quyền ghi:
```
chmod -R 755 /path/to/htdocs/LTW
chmod -R 777 /path/to/htdocs/LTW/uploads
```

## Cấu Trúc Dự Án

- `/api` - Các API và endpoint xử lý dữ liệu
- `/assets` - Các tài nguyên tĩnh như CSS, JavaScript, hình ảnh
- `/config` - File cấu hình hệ thống
- `/includes` - Các file PHP được sử dụng lại trong nhiều trang
- `/views` - Các file giao diện người dùng
- `/uploads` - Thư mục lưu trữ file tải lên

## Bảo Mật

1. Sau khi cài đặt, hãy thay đổi mật khẩu cho tài khoản admin mặc định
2. Đảm bảo thư mục XAMPP được bảo mật theo hướng dẫn của nhà cung cấp
3. Trong môi trường sản xuất, hãy xem xét các biện pháp bổ sung như HTTPS, tường lửa, v.v.

---

Hướng dẫn này được tạo ngày: 25/06/2025
