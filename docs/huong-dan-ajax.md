# HƯỚNG DẪN TRIỂN KHAI AJAX TRONG HỆ THỐNG LTW

## Giới thiệu

AJAX (Asynchronous JavaScript and XML) cho phép gửi và nhận dữ liệu từ máy chủ mà không cần tải lại toàn bộ trang. Việc triển khai AJAX trong ứng dụng Quản lý Ký túc xá LTW cải thiện đáng kể trải nghiệm người dùng bằng cách làm cho các tương tác trở nên mượt mà và phản hồi nhanh hơn.

## Các file JavaScript chính

- **ajax-utils.js**: Chứa các hàm tiện ích AJAX để gửi yêu cầu, xử lý biểu mẫu và hiển thị thông báo
- **main.js**: Chứa mã JavaScript chính, bao gồm xử lý sự kiện và tương tác người dùng

## Các chức năng đã triển khai AJAX

### 1. Thay đổi Mật khẩu (views/auth/change_password.php)

**Trước khi thay đổi:**
- Gửi form thông thường, tải lại trang
- Hiển thị thông báo sau khi tải lại trang

**Sau khi thay đổi:**
- Gửi biểu mẫu thông qua AJAX đến `/LTW/api/change_password.php`
- Hiển thị phản hồi ngay lập tức mà không cần tải lại trang
- Làm trống biểu mẫu khi thành công

**Đoạn code JavaScript đã thêm:**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const changePasswordForm = document.getElementById('change-password-form');
    const responseContainer = document.getElementById('ajax-response-container');
    
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Hiển thị chỉ báo đang tải
            responseContainer.innerHTML = '<div class="alert alert-info">Đang xử lý...</div>';
            
            // Gửi form qua AJAX
            submitFormAjax(
                this,
                function(response) {
                    // Xử lý phản hồi thành công
                    if (response.success) {
                        // Hiển thị thông báo thành công
                        showNotification('success', response.message, 'ajax-response-container');
                        // Đặt lại form
                        changePasswordForm.reset();
                    } else {
                        // Hiển thị thông báo lỗi
                        showNotification('danger', response.message, 'ajax-response-container');
                    }
                },
                function(error, status) {
                    // Xử lý lỗi
                    showNotification('danger', 'Đã xảy ra lỗi khi thay đổi mật khẩu. Vui lòng thử lại.', 'ajax-response-container');
                }
            );
        });
    }
});
```

### 2. Cập nhật Trạng thái Sinh viên (views/admin/students/edit.php)

**Trước khi thay đổi:**
- Phải gửi form và tải lại trang để cập nhật trạng thái
- Không có phản hồi ngay lập tức

**Sau khi thay đổi:**
- Cập nhật trạng thái thông qua AJAX đến `/LTW/api/update_student_status.php`
- Cập nhật giao diện người dùng ngay lập tức
- Hiển thị thông báo thành công/thất bại mà không tải lại trang

**Đoạn code JavaScript đã thêm:**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const statusForm = document.getElementById('student-status-form');
    const responseContainer = document.getElementById('status-response-container');
    
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Hiển thị chỉ báo đang tải
            responseContainer.innerHTML = '<div class="alert alert-info">Đang xử lý...</div>';
            
            // Gửi form qua AJAX
            submitFormAjax(
                this,
                function(response) {
                    // Xử lý phản hồi thành công
                    if (response.success) {
                        // Cập nhật trạng thái trên giao diện
                        const statusBadges = document.querySelectorAll('.badge');
                        if (statusBadges.length) {
                            statusBadges.forEach(badge => {
                                badge.className = response.status_class + ' badge';
                                badge.textContent = response.status_text;
                            });
                        }
                        
                        // Hiển thị thông báo thành công
                        showNotification('success', response.message, 'status-response-container');
                        
                        // Đặt lại trường lý do
                        document.getElementById('status_reason').value = '';
                    } else {
                        // Hiển thị thông báo lỗi
                        showNotification('danger', response.message, 'status-response-container');
                    }
                },
                function(error, status) {
                    // Xử lý lỗi
                    showNotification('danger', 'Đã xảy ra lỗi khi cập nhật trạng thái. Vui lòng thử lại.', 'status-response-container');
                }
            );
        });
    }
});
```

### 3. Xóa Đối tượng (assets/js/main.js)

**Trước khi thay đổi:**
- Xác nhận xóa, sau đó chuyển hướng đến URL xóa
- Tải lại trang sau khi xóa

**Sau khi thay đổi:**
- Xác nhận xóa, sau đó gửi yêu cầu AJAX để xóa qua `/LTW/api/delete_item.php`
- Xóa phần tử khỏi DOM nếu xóa thành công
- Hiển thị thông báo trực tiếp trong giao diện

**Đoạn code JavaScript đã thêm:**
```javascript
// Trong main.js, chức năng xác nhận xóa
const deleteButtons = document.querySelectorAll('.btn-delete');
if (deleteButtons) {
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const deleteUrl = this.getAttribute('href');
            const itemName = this.getAttribute('data-item-name') || 'mục này';
            const isAjax = this.getAttribute('data-ajax-delete') === 'true';
            
            Swal.fire({
                title: 'Bạn có chắc chắn?',
                text: `Bạn sắp xóa ${itemName}. Hành động này không thể hoàn tác.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: 'Có, xóa nó!'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (isAjax) {
                        // Sử dụng AJAX để xóa
                        sendAjaxRequest(
                            deleteUrl,
                            'POST',
                            { ajax_delete: '1' },
                            function(response) {
                                if (response.success) {
                                    // Hiển thị thông báo thành công và xóa phần tử
                                    showNotification('success', response.message, 'ajax-response-container');
                                    
                                    // Xóa mục đã xóa khỏi DOM nếu đã chỉ định
                                    const targetId = button.getAttribute('data-target-id');
                                    if (targetId) {
                                        const targetElement = document.getElementById(targetId);
                                        if (targetElement) {
                                            targetElement.remove();
                                        }
                                    }
                                    
                                    // Nếu có URL chuyển hướng, chuyển hướng sau 2 giây
                                    if (response.redirect) {
                                        setTimeout(function() {
                                            window.location.href = response.redirect;
                                        }, 2000);
                                    }
                                } else {
                                    // Hiển thị thông báo lỗi
                                    showNotification('danger', response.message || 'Không thể xóa mục', 'ajax-response-container');
                                }
                            },
                            function(error, status) {
                                // Xử lý lỗi
                                showNotification('danger', 'Đã xảy ra lỗi khi xử lý yêu cầu của bạn', 'ajax-response-container');
                            }
                        );
                    } else {
                        // Sử dụng chuyển hướng truyền thống để xóa
                        window.location.href = deleteUrl;
                    }
                }
            });
        });
    });
}
```

## Hướng dẫn sử dụng các chức năng AJAX

### 1. Chức năng Thay đổi Mật khẩu

Không cần thay đổi cách sử dụng. Người dùng vẫn truy cập vào trang thay đổi mật khẩu và điền thông tin như bình thường. Sự khác biệt là kết quả sẽ hiển thị ngay lập tức mà không cần tải lại trang.

### 2. Cập nhật Trạng thái Sinh viên

Quản trị viên và nhân viên vẫn truy cập vào trang chỉnh sửa sinh viên và cập nhật trạng thái như trước đây. Phản hồi sẽ hiển thị ngay lập tức và biểu tượng trạng thái sẽ được cập nhật mà không cần tải lại trang.

### 3. Xóa Đối tượng

Để sử dụng chức năng xóa qua AJAX, cần thêm các thuộc tính sau vào nút xóa:

```html
<!-- Ví dụ nút xóa sử dụng AJAX -->
<a href="/LTW/api/delete_item.php?type=student&id=123" class="btn btn-danger btn-delete" 
   data-item-name="Sinh viên Nguyễn Văn A" 
   data-ajax-delete="true" 
   data-target-id="student-row-123">
    <i class="fa fa-trash"></i> Xóa
</a>
```

Các thuộc tính quan trọng:
- `data-ajax-delete="true"`: Bật chức năng xóa qua AJAX
- `data-target-id="ID"`: ID của phần tử DOM cần xóa sau khi xóa thành công
- `data-item-name="Tên mục"`: Tên mục sẽ hiển thị trong hộp thoại xác nhận

## Lợi ích của việc sử dụng AJAX

1. **Trải nghiệm người dùng tốt hơn**: Không cần tải lại trang, giúp người dùng làm việc liên tục
2. **Giảm tải cho máy chủ**: Chỉ tải dữ liệu cần thiết, không phải tải lại toàn bộ trang
3. **Phản hồi nhanh hơn**: Người dùng nhận được phản hồi ngay lập tức khi thực hiện hành động
4. **Tương tác mượt mà hơn**: Không bị gián đoạn bởi việc tải lại trang
5. **Giảm băng thông**: Chỉ truyền tải dữ liệu cần thiết giữa máy khách và máy chủ

## Những điều cần lưu ý khi sử dụng AJAX

1. **Bảo mật**: Vẫn phải xác thực dữ liệu ở phía máy chủ để đảm bảo an toàn
2. **Xử lý lỗi**: Cần xử lý các trường hợp lỗi kết nối, lỗi máy chủ, và hiển thị thông báo phù hợp
3. **Trạng thái trang**: Cần cập nhật URL hoặc lưu trạng thái khi cần thiết để tránh mất ngữ cảnh
4. **Khả năng tiếp cận**: Đảm bảo trang vẫn hoạt động khi JavaScript bị tắt
5. **Kiểm tra trên nhiều trình duyệt**: Đảm bảo tính tương thích trên các trình duyệt khác nhau

## Các chức năng có thể triển khai trong tương lai

Các chức năng sau có thể được chuyển đổi sang AJAX trong các phiên bản tiếp theo:

1. **Đăng nhập/Đăng ký**: Cho phép đăng nhập mà không tải lại trang
2. **Tìm kiếm và lọc**: Cập nhật kết quả tìm kiếm theo thời gian thực
3. **Biểu mẫu gửi phản hồi**: Xử lý phản hồi của người dùng mà không tải lại trang
4. **Tải lên tệp**: Hiển thị tiến trình tải lên và xác nhận tệp mà không tải lại trang

---

*Tài liệu được cập nhật ngày 22 tháng 5 năm 2025*
