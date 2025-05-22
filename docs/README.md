# Hệ Thống Quản Lý Ký Túc Xá (Dormitory Management System)

Hệ thống quản lý ký túc xá là một ứng dụng web toàn diện được phát triển để giúp quản lý hiệu quả các hoạt động trong ký túc xá, bao gồm quản lý phòng, sinh viên, yêu cầu bảo trì và nhiều tính năng khác.

## Tính Năng Chính

### Quản Lý Người Dùng
- **Vai trò người dùng**: Quản trị viên, Nhân viên, Sinh viên
- **Quản lý tài khoản**: Đăng nhập, đăng xuất, đổi mật khẩu
- **Phân quyền**: Kiểm soát quyền truy cập dựa trên vai trò
- **Đặt lại mật khẩu**: Chức năng quên mật khẩu và đặt lại mật khẩu
- **Hồ sơ người dùng**: Tùy chỉnh thông tin cá nhân và ảnh đại diện

### Quản Lý Phòng
- **Quản lý phòng**: Thêm, sửa, xóa phòng
- **Phân loại phòng**: Quản lý các loại phòng khác nhau
- **Tình trạng phòng**: Trống, đã sử dụng, đang bảo trì
- **Phân công phòng**: Hệ thống phân công sinh viên vào phòng

### Quản Lý Sinh Viên
- **Hồ sơ sinh viên**: Thông tin cá nhân, liên hệ khẩn cấp
- **Quản lý tài liệu**: Lưu trữ tài liệu của sinh viên
- **Phân phòng**: Phân sinh viên vào phòng, quản lý thời gian ở
- **Xuất danh sách**: Xuất danh sách sinh viên ra các định dạng khác nhau

### Yêu Cầu Bảo Trì
- **Báo cáo sự cố**: Sinh viên báo cáo vấn đề phòng ở
- **Yêu cầu bảo trì**: Ghi nhận và xử lý các yêu cầu bảo trì
- **Theo dõi tiến độ**: Cập nhật trạng thái và lịch sử xử lý
- **Ưu tiên yêu cầu**: Phân loại mức độ ưu tiên khác nhau (thấp, trung bình, cao, khẩn cấp)

### Báo Cáo & Thống Kê
- **Báo cáo phòng**: Tình trạng sử dụng phòng
- **Báo cáo bảo trì**: Thống kê yêu cầu bảo trì
- **Phân tích dữ liệu**: Tạo báo cáo và thống kê theo yêu cầu

### Giao Diện & Trải Nghiệm Người Dùng
- **Thiết kế đáp ứng**: Giao diện tương thích với các thiết bị khác nhau
- **Dashboard trực quan**: Hiển thị thông tin quan trọng dựa trên vai trò người dùng
- **Giao diện thân thiện**: Thiết kế dễ sử dụng, trực quan

## Cấu Trúc Hệ Thống

```
/
├── dashboard.php              # Trang dashboard chính
├── home.php                   # Trang chủ
├── index.php                  # Trang khởi động ứng dụng
├── login.php                  # Trang đăng nhập
├── logout.php                 # Xử lý đăng xuất
├── README.md                  # Tài liệu dự án
├── assets/                    # Tài nguyên tĩnh
│   ├── css/                   # Tệp CSS
│   │   └── style.css          # CSS chính
│   ├── images/                # Hình ảnh
│   │   └── partners/          # Logo đối tác
│   └── js/                    # JavaScript
│       └── main.js            # JS chính
├── config/                    # Tệp cấu hình
│   ├── database.php           # Cấu hình kết nối cơ sở dữ liệu
│   ├── functions.php          # Các hàm tiện ích
│   ├── run_setup.php          # Chạy cài đặt
│   └── setup.php              # Thiết lập hệ thống
├── exports/                   # Xuất dữ liệu
│   └── export_students.php    # Xuất danh sách sinh viên
├── includes/                  # Các thành phần chung
│   ├── db_connection.php      # Kết nối database
│   ├── debug_helper.php       # Trợ giúp gỡ lỗi
│   ├── footer.php             # Footer chung
│   └── header.php             # Header chung
├── uploads/                   # Thư mục lưu trữ tệp tải lên
│   └── profile_pics/          # Ảnh hồ sơ người dùng
├── views/                     # Giao diện người dùng
│   ├── profile.php            # Trang hồ sơ người dùng
│   ├── admin/                 # Khu vực quản trị viên
│   │   ├── reports.php        # Báo cáo và thống kê
│   │   ├── settings.php       # Cài đặt hệ thống
│   │   ├── maintenance/       # Quản lý bảo trì
│   │   │   ├── add.php        # Thêm yêu cầu bảo trì
│   │   │   ├── list.php       # Danh sách yêu cầu
│   │   │   └── view.php       # Xem chi tiết
│   │   ├── rooms/             # Quản lý phòng
│   │   │   ├── add.php        # Thêm phòng
│   │   │   ├── assign.php     # Gán phòng
│   │   │   ├── edit.php       # Sửa phòng
│   │   │   ├── list.php       # Danh sách phòng
│   │   │   ├── room_types.php # Loại phòng
│   │   │   └── view.php       # Xem phòng
│   │   ├── students/          # Quản lý sinh viên
│   │   │   ├── add.php        # Thêm sinh viên
│   │   │   ├── documents.php  # Tài liệu sinh viên
│   │   │   ├── edit.php       # Sửa sinh viên
│   │   │   ├── list.php       # Danh sách sinh viên
│   │   │   ├── reset_password.php # Đặt lại mật khẩu
│   │   │   └── view.php       # Xem hồ sơ sinh viên
│   │   └── users/             # Quản lý người dùng
│   │       ├── add.php        # Thêm người dùng
│   │       ├── edit.php       # Sửa người dùng
│   │       ├── list.php       # Danh sách người dùng
│   │       └── reset_password.php # Đặt lại mật khẩu
│   ├── auth/                  # Xác thực
│   │   ├── change_password.php # Thay đổi mật khẩu
│   │   ├── forgot_password.php # Quên mật khẩu
│   │   └── reset_password.php # Đặt lại mật khẩu
│   └── maintenance/           # Quản lý bảo trì (người dùng)
│       ├── add.php            # Thêm yêu cầu bảo trì
│       ├── list.php           # Danh sách yêu cầu
│       └── view.php           # Xem chi tiết
```

## Cấu Trúc Cơ Sở Dữ Liệu

### Bảng chính
- **users**: Quản lý người dùng (admin, staff, student)
- **rooms**: Quản lý thông tin phòng
- **room_assignments**: Quản lý phân công phòng
- **maintenance_requests**: Quản lý yêu cầu bảo trì

## Yêu Cầu Hệ Thống

- PHP 7.4 trở lên
- MySQL 5.7 trở lên hoặc MariaDB 10.2 trở lên
- Máy chủ web Apache/Nginx
- Trình duyệt hiện đại (Chrome, Firefox, Edge, Safari)

## Hướng Dẫn Cài Đặt

1. Clone hoặc tải xuống dự án vào thư mục XAMPP htdocs hoặc thư mục máy chủ web
2. Tạo cơ sở dữ liệu MySQL/MariaDB mới
3. Nhập dữ liệu từ file SQL cung cấp hoặc chạy script thiết lập
4. Cấu hình kết nối database trong `config/database.php`
5. Truy cập vào ứng dụng qua URL: `http://localhost/LTW/`

## Tài Khoản Mặc Định

- **Quản trị viên**: admin / 123456
- **Nhân viên**: staff / 123456
- **Sinh viên**: student / 123456

*Lưu ý: Hãy đổi mật khẩu ngay sau khi đăng nhập lần đầu tiên*

## Vai Trò Người Dùng

### Sinh viên
- Xem thông tin phòng được phân công
- Gửi và theo dõi yêu cầu bảo trì
- Cập nhật thông tin cá nhân

### Nhân viên
- Quản lý yêu cầu bảo trì
- Xem và cập nhật thông tin phòng, sinh viên
- Xử lý các báo cáo và vấn đề thường ngày

### Quản trị viên
- Quản lý tài khoản người dùng và phân quyền
- Quản lý phòng ở và phân công phòng
- Xem báo cáo tổng hợp và thống kê
- Cấu hình hệ thống

## Tính Năng Bảo Mật

- Mã hóa mật khẩu sử dụng thuật toán bảo mật
- Bảo vệ chống tấn công SQL Injection
- Phiên đăng nhập an toàn
- Xác thực và phân quyền người dùng

## Liên Hệ & Hỗ Trợ

Nếu có bất kỳ câu hỏi hoặc gặp sự cố, vui lòng liên hệ với quản trị viên hệ thống.

---

© 2025 Hệ Thống Quản Lý Ký Túc Xá. Bản quyền đã được đăng ký.
