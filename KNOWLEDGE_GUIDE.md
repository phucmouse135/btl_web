# KIẾN THỨC CẦN THIẾT CHO DỰ ÁN QUẢN LÝ KÝ TÚC XÁ

## Mục lục
1. [PHP](#php)
2. [Bootstrap](#bootstrap)
3. [JavaScript](#javascript)
4. [MySQL](#mysql)
5. [Kiến trúc ứng dụng](#kiến-trúc-ứng-dụng)
6. [Các tài nguyên học tập](#các-tài-nguyên-học-tập)

---

## PHP

### Cơ bản PHP
- **Cú pháp cơ bản**: Biến, hằng số, toán tử, cấu trúc điều khiển
- **Kiểu dữ liệu**: String, Integer, Float, Boolean, Array, Object, NULL
- **Hàm trong PHP**: Định nghĩa hàm, tham số, giá trị trả về
- **Mảng**: Mảng tuần tự, mảng kết hợp, mảng đa chiều
- **Biến toàn cục**: `$_GET`, `$_POST`, `$_SESSION`, `$_COOKIE`, `$_SERVER`
- **Include và Require**: `include`, `include_once`, `require`, `require_once`

### PHP và MySQL
- **PDO và MySQLi**: Các phương pháp kết nối cơ sở dữ liệu
- **Prepared Statements**: Tạo và thực thi câu lệnh an toàn
- **Truy vấn CRUD**: Select, Insert, Update, Delete
- **Xử lý kết quả truy vấn**: `fetch_assoc()`, `fetch_array()`, `num_rows`

### PHP và Form
- **Xử lý biểu mẫu**: Lấy dữ liệu từ form, xác thực dữ liệu
- **Tải lên tệp**: Xử lý file upload, kiểm tra loại tệp, lưu trữ tệp
- **Bảo mật biểu mẫu**: CSRF token, XSS prevention

### PHP nâng cao
- **Xử lý lỗi**: try-catch, error handling
- **Phiên và Cookie**: Quản lý phiên đăng nhập, lưu trữ thông tin người dùng
- **Bảo mật**: Mã hóa mật khẩu, sanitize input, phòng chống SQL injection
- **Hướng đối tượng trong PHP**: Class, Object, Inheritance, Interface
- **Namespace**: Tổ chức mã nguồn, tránh xung đột tên

### Các hàm PHP phổ biến trong dự án
- `mysqli_connect()`: Kết nối cơ sở dữ liệu
- `mysqli_query()`: Thực thi truy vấn
- `mysqli_prepare()`: Chuẩn bị statement
- `password_hash()`, `password_verify()`: Mã hóa và xác thực mật khẩu
- `header()`: Chuyển hướng trang
- `session_start()`: Bắt đầu phiên
- `date()`, `strtotime()`: Định dạng và xử lý thời gian

---

## Bootstrap

### Cơ bản Bootstrap
- **Grid System**: Hệ thống lưới 12 cột, responsive design
- **Breakpoints**: xs, sm, md, lg, xl, xxl
- **Container**: `.container`, `.container-fluid`, `.container-{breakpoint}`

### Components Bootstrap
- **Navigation**: Navbar, Nav, Pagination
- **Cards**: Hiển thị thông tin trong khối card
- **Forms**: Form controls, Input group, Validation
- **Tables**: Styling tables, Responsive tables
- **Buttons**: Button styles, Button groups
- **Modals**: Hiển thị hộp thoại popup
- **Alerts**: Thông báo success, warning, error
- **Badges**: Hiển thị nhãn, trạng thái

### Utilities trong Bootstrap
- **Spacing**: Margin và Padding (m-*, p-*)
- **Display**: Hiển thị và ẩn phần tử (d-none, d-flex)
- **Flex**: Flexbox layouts
- **Text**: Text alignment, weight, transform
- **Colors**: Text colors, background colors
- **Borders**: Border styles, border-radius

### Bootstrap JavaScript
- **Modal**: Hiện/ẩn modal dialog
- **Tooltips**: Hiển thị tooltip khi hover
- **Popovers**: Hiển thị popover khi click
- **Collapse**: Thu gọn/mở rộng nội dung
- **Dropdowns**: Menu dropdown
- **Tabs**: Chuyển đổi giữa các tab

---

## JavaScript

### Cơ bản JavaScript
- **Cú pháp cơ bản**: Biến, hằng số, kiểu dữ liệu, toán tử
- **Cấu trúc điều khiển**: if-else, switch, vòng lặp
- **Hàm**: Định nghĩa hàm, arrow functions, callbacks
- **Objects và Arrays**: Làm việc với đối tượng và mảng

### DOM Manipulation
- **Truy cập phần tử**: getElementById, querySelector, querySelectorAll
- **Thay đổi nội dung**: innerHTML, textContent
- **Thay đổi thuộc tính**: setAttribute, style
- **Event Handling**: addEventListener, onclick, onsubmit

### AJAX và Fetch API
- **XMLHttpRequest**: AJAX cơ bản
- **Fetch API**: Promise-based HTTP requests
- **JSON**: Parse và stringify

### Client-side Form Validation
- **Kiểm tra dữ liệu nhập**: Kiểm tra rỗng, định dạng, giá trị
- **Hiển thị thông báo lỗi**: Hiển thị lỗi realtime
- **Gửi form với AJAX**: Gửi dữ liệu form không reload trang

### JavaScript Libraries
- **jQuery**: Đơn giản hóa DOM manipulation và AJAX
- **DataTables**: Tạo và quản lý bảng dữ liệu động
- **Chart.js/ApexCharts**: Tạo biểu đồ cho báo cáo và thống kê
- **Moment.js**: Định dạng và xử lý thời gian

---

## MySQL

### Cơ bản MySQL
- **Cơ sở dữ liệu và Bảng**: Tạo, thay đổi, xóa
- **Kiểu dữ liệu**: VARCHAR, INT, TEXT, DATE, ENUM,...
- **Truy vấn cơ bản**: SELECT, INSERT, UPDATE, DELETE
- **WHERE, ORDER BY, LIMIT**: Lọc, sắp xếp, phân trang dữ liệu

### Quan hệ và Ràng buộc
- **Primary Key**: Khóa chính
- **Foreign Key**: Khóa ngoại và mối quan hệ
- **Constraints**: Ràng buộc UNIQUE, NOT NULL, DEFAULT

### Truy vấn nâng cao
- **JOIN**: INNER JOIN, LEFT JOIN, RIGHT JOIN
- **Subqueries**: Truy vấn con
- **Aggregate Functions**: COUNT, SUM, AVG, MIN, MAX
- **GROUP BY, HAVING**: Nhóm và lọc kết quả

### Tối ưu hóa MySQL
- **Indexing**: Tạo và sử dụng index
- **Query Optimization**: Tối ưu hóa câu truy vấn
- **Explain**: Phân tích hiệu suất truy vấn

---

## Kiến trúc ứng dụng

### Mô hình MVC trong dự án
- **Models**: Tương tác với cơ sở dữ liệu (Ví dụ: Users, Rooms, Maintenance)
- **Views**: Hiển thị dữ liệu (Các file trong thư mục views/)
- **Controllers**: Xử lý logic nghiệp vụ (Các file xử lý logic)

### Cấu trúc thư mục
- **config/**: Cấu hình database, functions
- **includes/**: Header, footer, kết nối DB
- **views/**: Giao diện người dùng
- **assets/**: CSS, JavaScript, Images
- **uploads/**: Files người dùng tải lên

### Authentication và Authorization
- **Đăng nhập/Đăng xuất**: Session management
- **Phân quyền**: Admin, Staff, Student
- **Password Hashing**: BCrypt

### Data Flow trong ứng dụng
- **Request Handling**: Từ user đến server
- **Database Interaction**: CRUD operations
- **Response Generation**: Từ server đến user

---

## Các tài nguyên học tập

### PHP
- [PHP Documentation](https://www.php.net/docs.php)
- [W3Schools PHP Tutorial](https://www.w3schools.com/php/)
- [PHP The Right Way](https://phptherightway.com/)

### Bootstrap
- [Bootstrap Documentation](https://getbootstrap.com/docs/)
- [Bootstrap Examples](https://getbootstrap.com/docs/5.3/examples/)
- [Bootstrap CSS Classes Reference](https://www.w3schools.com/bootstrap5/bootstrap_ref_all_classes.php)

### JavaScript
- [MDN JavaScript Guide](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide)
- [JavaScript.info](https://javascript.info/)
- [W3Schools JavaScript Tutorial](https://www.w3schools.com/js/)

### MySQL
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [W3Schools SQL Tutorial](https://www.w3schools.com/sql/)
- [MySQL Tutorial](https://www.mysqltutorial.org/)

### Khóa học online
- [Codecademy - PHP](https://www.codecademy.com/learn/learn-php)
- [Udemy - Complete PHP Developer Course](https://www.udemy.com/course/php-for-complete-beginners-includes-msql-object-oriented/)
- [freeCodeCamp - Responsive Web Design](https://www.freecodecamp.org/learn/responsive-web-design/)

---

## Lời khuyên khi làm việc với dự án

1. **Đọc hiểu cấu trúc dự án** trước khi thực hiện thay đổi
2. **Sử dụng IDE/Editor hiệu quả** như Visual Studio Code với PHP, HTML extensions
3. **Tạo database backup** trước khi thực hiện thay đổi lớn
4. **Kiểm tra tương thích trình duyệt** khi thêm tính năng mới
5. **Tuân thủ quy ước đặt tên** hiện có trong dự án
6. **Test kỹ lưỡng** các chức năng sau khi thay đổi
7. **Đảm bảo bảo mật** khi xử lý đầu vào người dùng

---

*Tài liệu này được cập nhật vào ngày 8 tháng 5, 2025*