# LTW System Documentation

## Tổng quan hệ thống

Hệ thống quản lý ký túc xá LTW là một ứng dụng web PHP với các chức năng quản lý sinh viên, phòng ở, hợp đồng và các yêu cầu bảo trì. Hệ thống được thiết kế theo mô hình MVC đơn giản với các thành phần chính:

- **Includes**: Chứa các file cấu hình và hàm tiện ích
- **Views**: Chứa các trang giao diện người dùng
- **Models**: Chứa logic xử lý dữ liệu
- **Config**: Chứa cấu hình hệ thống
- **Assets**: Chứa các file CSS, JS và hình ảnh

## Luồng chạy code của các chức năng

### 1. Luồng đăng nhập và xác thực

```
Login.php → database.php → functions.php → dashboard.php
```

1. Người dùng truy cập `login.php` và điền thông tin đăng nhập
2. Form gửi POST request đến chính nó
3. `login.php` gọi hàm xác thực từ `functions.php`
4. `functions.php` kết nối đến database thông qua `database.php`
5. Nếu xác thực thành công, tạo session và chuyển hướng đến `dashboard.php`
6. Nếu không thành công, hiển thị thông báo lỗi

### 2. Luồng quản lý hồ sơ người dùng

```
profile.php → database.php → functions.php
↓
auth/change_password.php (nếu thay đổi mật khẩu)
```

1. Người dùng truy cập `profile.php` để xem thông tin cá nhân
2. `profile.php` lấy dữ liệu người dùng từ database thông qua `database.php`
3. Người dùng có thể cập nhật thông tin cá nhân trực tiếp tại `profile.php`
4. Để thay đổi mật khẩu, người dùng được chuyển hướng đến `auth/change_password.php`

### 3. Luồng quản lý phòng và sinh viên

```
admin/rooms/index.php → admin/rooms/create.php hoặc edit.php → database.php → functions.php
↓
admin/students/index.php → database.php → functions.php
```

1. Admin truy cập trang quản lý phòng (`admin/rooms/index.php`)
2. Có thể thêm phòng mới thông qua `admin/rooms/create.php`
3. Hoặc cập nhật phòng hiện có thông qua `admin/rooms/edit.php`
4. Dữ liệu được xử lý và lưu vào database thông qua `database.php`
5. Tương tự cho quản lý sinh viên thông qua `admin/students/index.php`

### 4. Luồng quản lý yêu cầu bảo trì

```
maintenance/create.php → database.php → functions.php
↓
maintenance/index.php → maintenance/view.php
↓
maintenance/update.php
```

1. Người dùng tạo yêu cầu bảo trì thông qua `maintenance/create.php`
2. Dữ liệu được lưu vào bảng `maintenance_requests`
3. Quản trị viên xem danh sách yêu cầu tại `maintenance/index.php`
4. Chi tiết yêu cầu được xem tại `maintenance/view.php`
5. Cập nhật trạng thái yêu cầu thông qua `maintenance/update.php`

## Ý nghĩa của các file chính

### File cấu hình và tiện ích

#### includes/database.php
**Mục đích**: Thiết lập kết nối đến cơ sở dữ liệu MySQL.
**Chức năng chính**:
- Cấu hình thông số kết nối (host, username, password, database)
- Khởi tạo biến kết nối `$conn`
- Kiểm tra và báo lỗi nếu kết nối thất bại

#### includes/functions.php
**Mục đích**: Chứa các hàm tiện ích dùng chung trong hệ thống.
**Các hàm quan trọng**:
- `requireLogin()`: Kiểm tra xác thực người dùng, chuyển hướng nếu chưa đăng nhập
- `displayError()`: Hiển thị thông báo lỗi với định dạng Bootstrap
- `displaySuccess()`: Hiển thị thông báo thành công với định dạng Bootstrap
- `logActivity()`: Ghi lại hoạt động của người dùng
- `sanitizeInput()`: Làm sạch dữ liệu đầu vào
- `uploadFile()`: Xử lý tải lên file

#### includes/header.php
**Mục đích**: Hiển thị phần đầu HTML và navigation chung cho tất cả các trang.
**Chức năng chính**:
- Bắt đầu session
- Hiển thị DOCTYPE, head, CSS, JS chung
- Hiển thị menu navigation dựa trên quyền người dùng

#### includes/footer.php
**Mục đích**: Hiển thị phần footer chung và đóng các thẻ HTML.
**Chức năng chính**:
- Hiển thị thông tin footer
- Đóng các thẻ HTML
- Bao gồm JS chung

### File xác thực và người dùng

#### views/login.php
**Mục đích**: Hiển thị form đăng nhập và xử lý quá trình đăng nhập.
**Chức năng chính**:
- Hiển thị form đăng nhập
- Kiểm tra thông tin đăng nhập
- Xác thực người dùng thông qua database
- Lưu thông tin người dùng vào session
- Chuyển hướng đến trang thích hợp dựa trên vai trò

#### views/auth/change_password.php
**Mục đích**: Cho phép người dùng thay đổi mật khẩu.
**Chức năng chính**:
- Kiểm tra mật khẩu hiện tại
- Xác thực mật khẩu mới (độ dài, phức tạp)
- Cập nhật mật khẩu trong database
- Hiển thị thông báo kết quả

#### views/profile.php
**Mục đích**: Hiển thị và cho phép cập nhật thông tin cá nhân.
**Chức năng chính**:
- Hiển thị thông tin người dùng từ database
- Cho phép cập nhật thông tin cá nhân
- Liên kết đến trang đổi mật khẩu
- Hiển thị lịch sử hoạt động

### Các file quản lý chức năng chính

#### views/admin/dashboard.php
**Mục đích**: Trang tổng quan cho admin.
**Chức năng chính**:
- Hiển thị thống kê tổng quan (số sinh viên, phòng trống, yêu cầu bảo trì, etc.)
- Hiển thị biểu đồ và dữ liệu quan trọng
- Liên kết nhanh đến các chức năng quản lý

#### views/admin/rooms/*.php
**Mục đích**: Quản lý thông tin phòng ký túc xá.
**Các file chính**:
- `index.php`: Hiển thị danh sách phòng, tìm kiếm và lọc
- `create.php`: Form thêm phòng mới
- `edit.php`: Form cập nhật thông tin phòng
- `view.php`: Xem chi tiết phòng và sinh viên ở trong phòng
- `delete.php`: Xử lý xóa phòng

#### views/admin/students/*.php
**Mục đích**: Quản lý thông tin sinh viên.
**Các file chính**:
- `index.php`: Hiển thị danh sách sinh viên, tìm kiếm và lọc
- `create.php`: Form thêm sinh viên mới
- `edit.php`: Form cập nhật thông tin sinh viên
- `view.php`: Xem chi tiết sinh viên
- `delete.php`: Xử lý xóa sinh viên

#### views/maintenance/*.php
**Mục đích**: Quản lý yêu cầu bảo trì.
**Các file chính**:
- `index.php`: Hiển thị danh sách yêu cầu
- `create.php`: Form tạo yêu cầu mới
- `view.php`: Xem chi tiết yêu cầu
- `update.php`: Cập nhật trạng thái yêu cầu

## Cấu trúc cơ sở dữ liệu

### Bảng users
- Lưu trữ thông tin người dùng (admin, nhân viên, sinh viên)
- Các trường: id, email, password, fullname, role, phone, etc.

### Bảng rooms
- Lưu trữ thông tin phòng ký túc xá
- Các trường: id, room_number, capacity, floor, status, etc.

### Bảng room_assignments
- Lưu trữ thông tin phân phòng cho sinh viên
- Các trường: id, student_id, room_id, start_date, end_date, status

### Bảng maintenance_requests
- Lưu trữ các yêu cầu bảo trì
- Các trường: id, room_id, reported_by, issue_type, description, request_date, priority, status, etc.

### Bảng activity_logs
- Lưu trữ lịch sử hoạt động của người dùng
- Các trường: id, user_id, action, description, user_role, created_at

## Các hàm quan trọng và cách sử dụng

### Xử lý xác thực
```php
// Kiểm tra đăng nhập
requireLogin();

// Kiểm tra quyền admin
requireAdmin();

// Xác minh mật khẩu
verifyPassword($inputPassword, $hashedPassword);
```

### Xử lý dữ liệu
```php
// Lấy thông tin người dùng
getUserById($userId);

// Lấy danh sách phòng
getRooms($filters);

// Lấy thông tin sinh viên trong phòng
getStudentsByRoom($roomId);
```

### Xử lý biểu mẫu
```php
// Kiểm tra và làm sạch dữ liệu đầu vào
$cleanData = sanitizeInput($_POST['data']);

// Tải file lên
$fileUrl = uploadFile($_FILES['file'], 'images/profiles/');

// Ghi log hoạt động
logActivity("Cập nhật hồ sơ", "Người dùng đã cập nhật thông tin cá nhân", $userId, $userRole);
```

## Quy trình làm việc phổ biến

### 1. Thêm sinh viên và phân phòng
1. Admin đăng nhập vào hệ thống
2. Truy cập trang quản lý sinh viên
3. Thêm sinh viên mới với thông tin cá nhân
4. Truy cập trang quản lý phân phòng
5. Chọn sinh viên và phòng phù hợp
6. Thiết lập thời gian bắt đầu và kết thúc
7. Lưu thông tin phân phòng

### 2. Xử lý yêu cầu bảo trì
1. Sinh viên đăng nhập và tạo yêu cầu bảo trì
2. Nhân viên kỹ thuật xem danh sách yêu cầu
3. Nhận xử lý yêu cầu và cập nhật trạng thái thành "đang xử lý"
4. Sau khi hoàn thành, cập nhật trạng thái thành "đã hoàn thành"
5. Ghi chú cách xử lý và kết quả
