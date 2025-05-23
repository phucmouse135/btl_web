# HƯỚNG DẪN TRIỂN KHAI AJAX TRONG DỰ ÁN LTW

Tài liệu này cung cấp tổng quan và hướng dẫn chi tiết về cách AJAX đã được triển khai trong dự án Quản lý Ký túc xá LTW.

## 1. Các file JavaScript chính

### 1.1. `ajax-utils.js`
File này chứa các hàm tiện ích để gửi và xử lý các yêu cầu AJAX. Các hàm chính bao gồm:

- `sendAjaxRequest(url, method, data, successCallback, errorCallback)`: Gửi yêu cầu AJAX
- `submitFormAjax(form, successCallback, errorCallback)`: Gửi form thông qua AJAX
- `showNotification(type, message, containerId)`: Hiển thị thông báo

### 1.2. `main.js`
File này chứa các xử lý sự kiện và logic chung cho toàn bộ ứng dụng, bao gồm các xử lý AJAX như:

- Xử lý nút xóa (`.btn-delete`)
- Xử lý form AJAX
- Xử lý cập nhật trạng thái

## 2. Cấu trúc API

Các API endpoints được lưu trong thư mục `api/`:

### 2.1. `delete_item.php`
- **Chức năng**: Xóa các mục như sinh viên, phòng, người dùng...
- **Phương thức**: POST
- **Tham số**: 
  - `type`: Loại mục cần xóa (student, room, user...)
  - `id`: ID của mục
  - `ajax_delete`: Flag xác nhận xóa qua AJAX

### 2.2. `update_student_status.php`
- **Chức năng**: Cập nhật trạng thái sinh viên
- **Phương thức**: POST
- **Tham số**:
  - `student_id`: ID của sinh viên
  - `status`: Trạng thái mới (active, inactive, graduated)

### 2.3. `change_password.php`
- **Chức năng**: Đổi mật khẩu người dùng
- **Phương thức**: POST
- **Tham số**:
  - `current_password`: Mật khẩu hiện tại
  - `new_password`: Mật khẩu mới
  - `confirm_password`: Xác nhận mật khẩu mới

## 3. Cách triển khai AJAX trong các trang

### 3.1. Xóa dữ liệu

```html
<a href="/LTW/api/delete_item.php?type=student&id=123" 
   class="btn btn-sm btn-danger btn-delete" 
   data-ajax-delete="true"
   data-item-name="Tên Sinh Viên">
    <i class="fas fa-trash"></i>
</a>
```

Các thuộc tính:
- `href`: URL của API endpoint
- `data-ajax-delete="true"`: Kích hoạt xử lý AJAX
- `data-item-name`: Tên hiển thị trong thông báo xác nhận

### 3.2. Form AJAX

```html
<form action="/LTW/api/change_password.php" method="POST" class="ajax-form">
    <!-- Nội dung form -->
    <button type="submit" class="btn btn-primary">Lưu</button>
</form>
```

Lớp CSS `ajax-form` kích hoạt xử lý AJAX trong `main.js`.

### 3.3. Cập nhật trạng thái

```html
<select class="status-change" data-student-id="123">
    <option value="active" selected>Hoạt động</option>
    <option value="inactive">Không hoạt động</option>
    <option value="graduated">Đã tốt nghiệp</option>
</select>
```

Lớp CSS `status-change` kích hoạt xử lý AJAX khi giá trị thay đổi.

## 4. Xử lý response

Tất cả các API endpoint đều trả về dữ liệu JSON với cấu trúc:

```json
{
    "success": true|false,
    "message": "Thông báo thành công hoặc lỗi",
    "redirect": "URL chuyển hướng (nếu có)",
    "data": { /* Dữ liệu bổ sung */ }
}
```

Các callback xử lý response:

```javascript
function handleSuccess(response) {
    if (response.success) {
        showNotification('success', response.message, 'ajax-response-container');
        
        if (response.redirect) {
            setTimeout(function() {
                window.location.href = response.redirect;
            }, 2000);
        }
    } else {
        showNotification('danger', response.message, 'ajax-response-container');
    }
}

function handleError(error, status) {
    showNotification('danger', 'Đã xảy ra lỗi: ' + status, 'ajax-response-container');
}
```

## 5. Container thông báo AJAX

Thông báo AJAX được hiển thị trong container có ID `ajax-response-container`, đã được thêm vào `header.php`:

```html
<div class="container-fluid py-4">
    <!-- Container hiển thị phản hồi AJAX -->
    <div id="ajax-response-container"></div>
    
    <!-- Nội dung khác -->
</div>
```

## 6. Sửa lỗi thường gặp

### 6.1. Lỗi "Call to a member function bind_param() on bool"
- **Nguyên nhân**: Câu lệnh SQL không hợp lệ hoặc lỗi cú pháp
- **Giải pháp**: Kiểm tra và sửa đổi cú pháp câu lệnh SQL

### 6.2. Lỗi "Parse error: syntax error"
- **Nguyên nhân**: Lỗi cú pháp PHP như thiếu dấu ngoặc, dấu chấm phẩy
- **Giải pháp**: Kiểm tra cú pháp PHP và sửa lỗi

### 6.3. Lỗi "Network error" trong console
- **Nguyên nhân**: Lỗi kết nối đến server hoặc API endpoint không tồn tại
- **Giải pháp**: Kiểm tra đường dẫn URL và kết nối mạng

## 7. Bảo mật trong AJAX

### 7.1. Kiểm tra AJAX request
```php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
```

### 7.2. Kiểm tra xác thực
```php
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}
```

### 7.3. Kiểm tra quyền
```php
if (!hasRole('admin')) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}
```

## 8. Kết luận

Triển khai AJAX trong dự án LTW giúp cải thiện trải nghiệm người dùng bằng cách giảm thời gian tải trang và cung cấp phản hồi tức thì. Các thành phần AJAX được thiết kế để dễ sử dụng và mở rộng, cho phép thêm các tính năng mới một cách dễ dàng.
