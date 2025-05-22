# Tài liệu Triển khai AJAX cho Hệ thống LTW

## Tổng quan về triển khai AJAX

Hệ thống quản lý ký túc xá LTW đã được nâng cấp với chức năng AJAX để cải thiện trải nghiệm người dùng và hiệu suất hệ thống. Những thay đổi này giúp người dùng tương tác với hệ thống mà không cần tải lại trang.

### 1. Thay đổi Mật khẩu (views/auth/change_password.php)

Biểu mẫu thay đổi mật khẩu ban đầu sử dụng phương pháp gửi biểu mẫu truyền thống, khiến trang phải tải lại hoàn toàn. Giờ đây, biểu mẫu đã được chuyển đổi để sử dụng AJAX giúp trải nghiệm người dùng mượt mà hơn.

#### Lợi ích chính:
- Không cần tải lại trang
- Phản hồi ngay lập tức cho người dùng
- Xử lý lỗi tốt hơn với phản hồi cụ thể
- Vẫn duy trì tính bảo mật với việc xác thực phía máy chủ

#### Chi tiết triển khai:
- Sử dụng API endpoint có sẵn tại `/LTW/api/change_password.php`
- Thêm xác thực biểu mẫu trong JavaScript trước khi gửi
- Hiển thị chỉ báo đang tải trong quá trình gửi
- Hiển thị thông báo thành công/lỗi mà không cần tải lại trang

### 2. Cập nhật Trạng thái Sinh viên (views/admin/students/edit.php)

Biểu mẫu cập nhật trạng thái sinh viên trước đây yêu cầu tải lại trang đầy đủ để cập nhật trạng thái của sinh viên. Hiện tại, nó sử dụng AJAX để cung cấp phản hồi ngay lập tức.

#### Lợi ích chính:
- Cập nhật trạng thái diễn ra ngay lập tức mà không cần tải lại trang
- Phản hồi trực quan với biểu tượng trạng thái được cập nhật
- Cải thiện trải nghiệm người dùng cho quản trị viên
- Giảm tải cho máy chủ đối với các thay đổi trạng thái thường xuyên

#### Chi tiết triển khai:
- Sử dụng API endpoint tại `/LTW/api/update_student_status.php`
- Cập nhật biểu tượng trạng thái theo thời gian thực
- Cung cấp phản hồi trực quan cho các thao tác thành công/thất bại
- Duy trì tất cả xác thực phía máy chủ

### 3. Xác nhận Xóa (assets/js/main.js)

Chức năng xác nhận xóa đã được nâng cấp để sử dụng AJAX cho việc xóa mục, tránh việc tải lại trang không cần thiết.

#### Lợi ích chính:
- Các mục có thể được xóa mà không cần tải lại trang
- Phản hồi trực quan ngay lập tức khi các mục được xóa
- Cải thiện trải nghiệm người dùng khi quản lý danh sách các mục
- Tùy chọn để quay lại phương pháp xóa truyền thống nếu cần

#### Chi tiết triển khai:
- API endpoint mới `/LTW/api/delete_item.php` xử lý các yêu cầu xóa
- Nâng cao hộp thoại xác nhận SweetAlert2
- Hỗ trợ nhiều loại mục (sinh viên, phòng, tòa nhà, v.v.)
- Xóa các mục đã xóa khỏi DOM để có phản hồi trực quan ngay lập tức
- Có thể cấu hình thông qua các thuộc tính dữ liệu:
  - `data-ajax-delete="true"` - Bật xóa AJAX
  - `data-target-id="element-id"` - Phần tử DOM cần xóa khi thành công

### Cách triển khai xóa AJAX trên các nút

Để chuyển đổi nút xóa truyền thống sang sử dụng AJAX:

```html
<!-- Trước: Xóa truyền thống -->
<a href="/LTW/views/admin/students/delete.php?id=123" class="btn btn-danger btn-delete" data-item-name="Sinh viên Nguyễn Văn A">
    <i class="fa fa-trash"></i> Xóa
</a>

<!-- Sau: Xóa qua AJAX -->
<a href="/LTW/api/delete_item.php?type=student&id=123" class="btn btn-danger btn-delete" 
   data-item-name="Sinh viên Nguyễn Văn A" 
   data-ajax-delete="true" 
   data-target-id="student-row-123">
    <i class="fa fa-trash"></i> Xóa
</a>
```

### Triển khai kỹ thuật

1. Chức năng AJAX được xây dựng dựa trên các hàm tiện ích trong `assets/js/ajax-utils.js`:
   - `sendAjaxRequest()` - Hàm yêu cầu AJAX cốt lõi
   - `submitFormAjax()` - Trình trợ giúp gửi biểu mẫu
   - `showNotification()` - Trình trợ giúp hiển thị thông báo

2. Các API endpoint tuân theo một mẫu nhất quán:
   - Kiểm tra tiêu đề yêu cầu AJAX
   - Xác thực quyền người dùng
   - Xử lý yêu cầu
   - Trả về phản hồi JSON với thông tin thành công/lỗi

3. Các cân nhắc về bảo mật:
   - Tất cả xác thực phía máy chủ được duy trì
   - Bảo vệ CSRF được triển khai
   - Quyền người dùng được xác minh cho mỗi hành động

---

*Tài liệu được cập nhật: Ngày 22 tháng 5 năm 2025*
