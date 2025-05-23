# TÀI LIỆU KỸ THUẬT HỆ THỐNG QUẢN LÝ KÝ TÚC XÁ (LTW)

## 1. KIẾN TRÚC HỆ THỐNG

### 1.1. Tổng quan kiến trúc

Hệ thống Quản lý Ký túc xá LTW được phát triển theo mô hình MVC (Model-View-Controller) đơn giản, với các thành phần chính như sau:

```
[Client] <---> [Web Server] <---> [PHP Application] <---> [Database]
```

Trong đó:
- **Client**: Trình duyệt người dùng (Chrome, Firefox, Edge...)
- **Web Server**: Apache hoặc Nginx
- **PHP Application**: Mã nguồn ứng dụng LTW
- **Database**: MySQL/MariaDB

### 1.2. Mô hình MVC

- **Model**: Đại diện cho dữ liệu và logic nghiệp vụ, nằm trong thư mục `models/`
- **View**: Giao diện người dùng, nằm trong thư mục `views/`
- **Controller**: Logic điều khiển, được triển khai trong các file view và API

Lưu ý: Hệ thống này sử dụng mô hình MVC đơn giản không tách biệt hoàn toàn, một số logic điều khiển có thể được nhúng trực tiếp trong các file view.

## 2. CÔNG NGHỆ SỬ DỤNG

### 2.1. Backend
- **PHP 7.4+**: Ngôn ngữ lập trình phía server
- **MySQL 5.7+/MariaDB 10.2+**: Hệ quản trị cơ sở dữ liệu
- **PDO/MySQLi**: Giao diện kết nối cơ sở dữ liệu

### 2.2. Frontend
- **HTML5/CSS3**: Đánh dấu và định kiểu
- **Bootstrap 5**: Framework CSS
- **JavaScript/jQuery**: Xử lý client-side
- **AJAX**: Giao tiếp không đồng bộ với server
- **SweetAlert2**: Hiển thị thông báo tương tác

### 2.3. Công cụ bổ sung
- **FontAwesome 6**: Bộ icon
- **Chart.js**: Hiển thị biểu đồ và thống kê
- **DataTables**: Quản lý bảng dữ liệu tương tác

## 3. CẤU TRÚC MÃ NGUỒN CHI TIẾT

### 3.1. Thư mục gốc
- `index.php`: Điểm vào chính của ứng dụng
- `login.php`: Xử lý đăng nhập
- `logout.php`: Xử lý đăng xuất
- `dashboard.php`: Bảng điều khiển chính

### 3.2. Thư mục `config/`
- `database.php`: Cấu hình kết nối cơ sở dữ liệu
- `functions.php`: Định nghĩa hàm tiện ích toàn cục
- `setup.php`: Script thiết lập ban đầu

### 3.3. Thư mục `includes/`
- `header.php`: Header chung cho tất cả các trang
- `footer.php`: Footer chung cho tất cả các trang
- `navigation.php`: Menu điều hướng
- `debug_helper.php`: Công cụ debug

### 3.4. Thư mục `models/`
- `Building.php`: Model cho tòa nhà
- Các model khác (nếu có)

### 3.5. Thư mục `views/`
- `admin/`: Giao diện quản trị
  - `students/`: Quản lý sinh viên
  - `rooms/`: Quản lý phòng
  - `users/`: Quản lý người dùng
- `auth/`: Xác thực người dùng
- `maintenance/`: Quản lý bảo trì

### 3.6. Thư mục `api/`
- `delete_item.php`: API xóa các mục
- `update_student_status.php`: API cập nhật trạng thái sinh viên
- `change_password.php`: API đổi mật khẩu

### 3.7. Thư mục `assets/`
- `css/`: Các file CSS
- `js/`: Các file JavaScript
- `images/`: Hình ảnh tĩnh

### 3.8. Thư mục `uploads/`
- `profile_pics/`: Ảnh đại diện người dùng
- Các thư mục upload khác

## 4. LUỒNG XỬ LÝ DỮ LIỆU

### 4.1. Luồng xử lý cơ bản

1. Người dùng truy cập URL (ví dụ: `/LTW/views/admin/students/list.php`)
2. Server xử lý yêu cầu và nạp file PHP tương ứng
3. File PHP thực hiện các bước:
   - Khởi tạo session
   - Nhúng file cấu hình và hàm tiện ích
   - Kiểm tra xác thực và phân quyền
   - Xử lý logic nghiệp vụ (truy vấn cơ sở dữ liệu, xử lý dữ liệu...)
   - Hiển thị giao diện người dùng
4. Giao diện được trả về cho người dùng

### 4.2. Luồng xử lý AJAX

1. Người dùng thực hiện hành động (ví dụ: nhấp vào nút xóa)
2. JavaScript/jQuery bắt sự kiện và hiển thị hộp thoại xác nhận
3. Nếu xác nhận, JavaScript gửi yêu cầu AJAX đến API endpoint
4. Server xử lý yêu cầu và trả về dữ liệu JSON
5. JavaScript xử lý phản hồi và cập nhật giao diện người dùng

## 5. QUẢN LÝ PHIÊN VÀ XÁC THỰC

### 5.1. Quản lý phiên
Hệ thống sử dụng `session` của PHP để quản lý phiên làm việc:
- Session được khởi tạo trong `includes/header.php`
- Thông tin người dùng được lưu trong session khi đăng nhập
- Session timeout được cấu hình trong `php.ini`

### 5.2. Xác thực người dùng
- Xác thực dựa trên username và password
- Mật khẩu được băm bằng `password_hash()` và `password_verify()`
- Hàm `requireLogin()` trong `functions.php` kiểm tra đăng nhập

### 5.3. Phân quyền
- Hệ thống có 3 vai trò: admin, staff, student
- Hàm `hasRole()` trong `functions.php` kiểm tra quyền
- Phân quyền được thực hiện ở đầu mỗi file view

## 6. XỬ LÝ BẢO MẬT

### 6.1. Bảo mật đầu vào
- Tất cả dữ liệu đầu vào được làm sạch qua hàm `sanitizeInput()`
- Sử dụng Prepared Statements cho tất cả truy vấn SQL
- Kiểm tra và xác thực dữ liệu form phía server

### 6.2. Bảo mật CSRF
- Form chứa token CSRF được tạo cho mỗi phiên
- Token được kiểm tra khi form được gửi
- Ngăn chặn tấn công CSRF từ các trang khác

### 6.3. Bảo mật tệp tin
- Kiểm tra loại tệp và phần mở rộng trước khi tải lên
- Đổi tên tệp sau khi tải lên để tránh xung đột
- Hạn chế quyền truy cập vào thư mục uploads

## 7. XỬ LÝ LỖI VÀ LOGGING

### 7.1. Xử lý lỗi
- Sử dụng khối try-catch để bắt và xử lý ngoại lệ
- Hiển thị thông báo lỗi thân thiện với người dùng
- Ẩn thông tin lỗi chi tiết trong môi trường sản xuất

### 7.2. Logging
- Hàm `logActivity()` trong `functions.php` ghi lại các hoạt động chính
- Lỗi hệ thống được ghi vào log của PHP/Apache
- Các sự kiện quan trọng (đăng nhập, thay đổi dữ liệu) được ghi lại

## 8. GIAO DIỆN NGƯỜI DÙNG

### 8.1. Framework CSS
- Bootstrap 5 được sử dụng cho layout và thành phần
- Responsive design hỗ trợ các thiết bị di động
- Theme switching (chế độ sáng/tối)

### 8.2. Tương tác người dùng
- SweetAlert2 cho thông báo và xác nhận
- DataTables cho bảng dữ liệu với tính năng tìm kiếm, sắp xếp
- Form validation phía client và server

### 8.3. AJAX và tương tác động
- Xóa mục mà không cần tải lại trang
- Cập nhật trạng thái theo thời gian thực
- Tải form và dữ liệu không đồng bộ

## 9. TỐI ƯU HÓA VÀ HIỆU SUẤT

### 9.1. Tối ưu database
- Sử dụng index cho các trường tìm kiếm
- Tối ưu hóa câu truy vấn
- Sử dụng phân trang cho các danh sách lớn

### 9.2. Tối ưu frontend
- Minify CSS và JavaScript
- Tải không đồng bộ các script không quan trọng
- Lazy loading cho hình ảnh

### 9.3. Caching
- Sử dụng browser caching cho tài nguyên tĩnh
- Tối ưu hóa session handling
- Sử dụng biến đệm khi thích hợp

## 10. MỞ RỘNG VÀ BẢO TRÌ

### 10.1. Thêm tính năng mới
1. Tạo model mới trong `models/` (nếu cần)
2. Tạo các file view trong thư mục thích hợp
3. Thêm API endpoint trong `api/` (nếu cần)
4. Cập nhật navigation và quyền truy cập

### 10.2. Sửa lỗi
1. Sử dụng `includes/debug_helper.php` để phát hiện lỗi
2. Kiểm tra logs của PHP và MySQL
3. Sửa lỗi và kiểm tra kỹ lưỡng trước khi triển khai

### 10.3. Nâng cấp
- Cập nhật phiên bản PHP và MySQL khi cần
- Kiểm tra tính tương thích khi nâng cấp
- Sao lưu dữ liệu trước khi nâng cấp lớn

## 11. TÀI LIỆU API

### 11.1. API `delete_item.php`

- **URL**: `/LTW/api/delete_item.php`
- **Method**: POST
- **Parameters**:
  - `type`: Loại mục (student, room, user...)
  - `id`: ID của mục
  - `ajax_delete`: Flag xác nhận (1)
- **Response**:
  ```json
  {
    "success": true|false,
    "message": "Thông báo",
    "redirect": "URL chuyển hướng (nếu có)"
  }
  ```

### 11.2. API `update_student_status.php`

- **URL**: `/LTW/api/update_student_status.php`
- **Method**: POST
- **Parameters**:
  - `student_id`: ID của sinh viên
  - `status`: Trạng thái mới
- **Response**:
  ```json
  {
    "success": true|false,
    "message": "Thông báo"
  }
  ```

### 11.3. API `change_password.php`

- **URL**: `/LTW/api/change_password.php`
- **Method**: POST
- **Parameters**:
  - `current_password`: Mật khẩu hiện tại
  - `new_password`: Mật khẩu mới
  - `confirm_password`: Xác nhận mật khẩu
- **Response**:
  ```json
  {
    "success": true|false,
    "message": "Thông báo"
  }
  ```

## 12. THUẬT NGỮ VÀ ĐỊNH NGHĨA

- **KTX**: Ký túc xá
- **LTW**: Tên dự án/mã dự án
- **Admin**: Quản trị viên hệ thống
- **Staff**: Nhân viên quản lý ký túc xá
- **Student**: Sinh viên đang ở ký túc xá
