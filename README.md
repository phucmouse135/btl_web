# Hệ Thống Quản Lý Ký Túc Xá (Dormitory Management System)

Hệ thống quản lý ký túc xá là một ứng dụng web toàn diện được phát triển để giúp quản lý hiệu quả các hoạt động trong ký túc xá, bao gồm quản lý phòng, sinh viên, yêu cầu bảo trì và nhiều tính năng khác.

### Tính Năng Chính

#### Quản Lý Người Dùng
- **Vai trò người dùng**: Quản trị viên, Nhân viên, Sinh viên
- **Quản lý tài khoản**: Đăng nhập, đổi mật khẩu, quên mật khẩu
- **Phân quyền**: Kiểm soát quyền truy cập dựa trên vai trò

#### Quản Lý Phòng & Tòa Nhà
- **Quản lý tòa nhà**: Thêm, sửa, xóa tòa nhà
- **Quản lý phòng**: Thêm, sửa, xóa phòng trong tòa nhà
- **Phân loại phòng**: Quản lý các loại phòng khác nhau
- **Tình trạng phòng**: Trống, đã sử dụng, đang bảo trì

#### Quản Lý Sinh Viên
- **Hồ sơ sinh viên**: Thông tin cá nhân, liên hệ khẩn cấp
- **Quản lý tài liệu**: Lưu trữ tài liệu của sinh viên
- **Phân phòng**: Phân sinh viên vào phòng, quản lý thời gian ở

#### Yêu Cầu Bảo Trì
- **Báo cáo sự cố**: Sinh viên báo cáo vấn đề phòng ở
- **Xử lý yêu cầu**: Nhân viên tiếp nhận và xử lý yêu cầu
- **Theo dõi tiến độ**: Cập nhật trạng thái và lịch sử xử lý
- **Ưu tiên yêu cầu**: Phân loại mức độ ưu tiên khác nhau

#### Báo Cáo & Thống Kê
- **Báo cáo phòng**: Tình trạng sử dụng phòng
- **Báo cáo bảo trì**: Thống kê yêu cầu bảo trì
- **Nhật ký hoạt động**: Ghi lại tất cả hoạt động trong hệ thống

### Cấu Trúc Thư Mục

```
/
├── dashboard.php              # Trang chính sau khi đăng nhập
├── index.php                  # Trang đăng nhập
├── logout.php                 # Xử lý đăng xuất
├── assets/                    # Tài nguyên tĩnh (CSS, JS, hình ảnh)
├── config/                    # Tệp cấu hình
│   ├── database.php           # Cấu hình kết nối cơ sở dữ liệu
│   └── functions.php          # Các hàm tiện ích
├── includes/                  # Các phần được bao gồm trong trang
│   ├── header.php             # Header chung cho tất cả trang
│   └── footer.php             # Footer chung cho tất cả trang
├── uploads/                   # Thư mục lưu trữ tệp tải lên
│   ├── profile_pics/          # Ảnh hồ sơ người dùng
│   └── maintenance/           # Ảnh yêu cầu bảo trì
├── views/                     # Giao diện người dùng
│   ├── auth/                  # Các trang xác thực
│   │   ├── change_password.php # Thay đổi mật khẩu
│   │   └── reset_password.php  # Đặt lại mật khẩu
│   ├── profile.php            # Trang hồ sơ người dùng
│   ├── maintenance/           # Quản lý bảo trì
│   │   ├── add.php            # Thêm yêu cầu bảo trì
│   │   ├── list.php           # Danh sách yêu cầu bảo trì
│   │   └── view.php           # Xem chi tiết yêu cầu bảo trì
│   ├── student/               # Giao diện sinh viên
│   └── admin/                 # Giao diện quản trị
│       ├── logs.php           # Nhật ký hoạt động
│       ├── reports.php        # Báo cáo và thống kê
│       ├── settings.php       # Cài đặt hệ thống
│       ├── maintenance/       # Quản lý bảo trì (admin)
│       ├── rooms/             # Quản lý phòng và tòa nhà
│       ├── students/          # Quản lý sinh viên
│       └── users/             # Quản lý người dùng
```

## Yêu Cầu Hệ Thống

- PHP 7.4 trở lên
- MySQL 5.7 trở lên
- Máy chủ web Apache/Nginx

## Cài Đặt

1. Clone hoặc tải xuống dự án vào thư mục web server
2. Tạo cơ sở dữ liệu MySQL
3. Cấu hình kết nối database trong `config/database.php`
4. Chạy script thiết lập ban đầu: `config/setup.php`
5. Truy cập vào ứng dụng qua trình duyệt

## Tài Khoản Mặc Định

- **Quản trị viên**: admin / 123456
- **Nhân viên**: staff / 123456
- **Sinh viên**: student / 123456

## Phát Triển Tiếp Theo

- Tích hợp hệ thống thanh toán
- Thêm tính năng đặt phòng trực tuyến
- Phát triển ứng dụng di động
- Thêm thông báo qua email và SMS

## Vai trò người dùng

### Sinh viên
- Xem thông tin phòng được phân công
- Gửi và theo dõi yêu cầu bảo trì
- Cập nhật thông tin cá nhân

### Nhân viên
- Quản lý yêu cầu bảo trì
- Xem thông tin phòng và sinh viên
- Xử lý một số báo cáo

### Quản trị viên
- Đầy đủ quyền trên hệ thống
- Quản lý tài khoản người dùng
- Quản lý phòng và tòa nhà
- Xem báo cáo và thống kê
- Cấu hình hệ thống

## Bảo mật

Hệ thống sử dụng các biện pháp bảo mật sau:
- Mã hóa mật khẩu
- Kiểm tra quyền truy cập
- Dọn dẹp đầu vào
- Phiên đăng nhập an toàn

## Liên hệ

Nếu có bất kỳ câu hỏi hoặc vấn đề nào, vui lòng liên hệ với quản trị viên hệ thống.

## English

The Dormitory Management System is a comprehensive web application developed to efficiently manage dormitory operations, including room management, student administration, maintenance requests, and various other features.

### Key Features

#### User Management
- **User Roles**: Administrator, Staff, Student
- **Account Management**: Login, password change, password recovery
- **Access Control**: Role-based permission system

#### Room & Building Management
- **Building Management**: Add, edit, delete buildings
- **Room Management**: Add, edit, delete rooms within buildings
- **Room Categories**: Manage different room types
- **Room Status**: Vacant, occupied, under maintenance

#### Student Management
- **Student Profiles**: Personal information, emergency contacts
- **Document Management**: Store student documents
- **Room Assignment**: Assign students to rooms, manage duration of stay

#### Maintenance Requests
- **Incident Reporting**: Students report room issues
- **Request Processing**: Staff receives and processes requests
- **Progress Tracking**: Update status and processing history
- **Request Prioritization**: Classify different priority levels

#### Reports & Statistics
- **Room Reports**: Room occupancy status
- **Maintenance Reports**: Statistics on maintenance requests
- **Activity Logs**: Record all system activities

### Directory Structure

```
/
├── dashboard.php              # Main page after login
├── index.php                  # Login page
├── logout.php                 # Logout processing
├── assets/                    # Static resources (CSS, JS, images)
├── config/                    # Configuration files
│   ├── database.php           # Database connection configuration
│   └── functions.php          # Utility functions
├── includes/                  # Components included in pages
│   ├── header.php             # Common header for all pages
│   └── footer.php             # Common footer for all pages
├── uploads/                   # Storage directory for uploaded files
│   ├── profile_pics/          # User profile pictures
│   └── maintenance/           # Maintenance request images
├── views/                     # User interfaces
│   ├── auth/                  # Authentication pages
│   │   ├── change_password.php # Change password
│   │   └── reset_password.php  # Reset password
│   ├── profile.php            # User profile page
│   ├── maintenance/           # Maintenance management
│   │   ├── add.php            # Add maintenance request
│   │   ├── list.php           # List of maintenance requests
│   │   └── view.php           # View maintenance request details
│   ├── student/               # Student interface
│   └── admin/                 # Administrator interface
```

### System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### Installation
1. Clone the repository to your web server directory
2. Import the database schema from `dormitory_db.sql`
3. Configure the database connection in `config/database.php`
4. Access the application through your web browser

### Default Accounts
- **Administrator**: admin / admin123
- **Staff**: staff / staff123
- **Student**: student / student123

### Security Measures
- Password encryption using password_hash()
- Session protection against hijacking
- Input validation and sanitization
- CSRF protection
- Secure file upload handling

### Future Development
- Mobile application integration
- Payment system for fees
- Visitor management system
- API for third-party integration