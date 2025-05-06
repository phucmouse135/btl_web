<?php
// Bao gồm các tệp cần thiết
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Import namespace cho PhpSpreadsheet (đặt ở đầu file)
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Kiểm tra người dùng đã đăng nhập và có quyền admin hoặc staff
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    header('Location: /LTW/index.php');
    exit;
}

// Xác định loại xuất dữ liệu (CSV hoặc Excel)
$export_type = isset($_GET['type']) ? $_GET['type'] : 'csv';

// Lọc theo trạng thái sinh viên nếu có
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Xây dựng câu truy vấn SQL
$query = "
    SELECT 
        u.id, 
        u.student_id as student_code, 
        u.first_name, 
        u.last_name, 
        u.gender, 
        u.date_of_birth as dob, 
        u.department, 
        u.phone, 
        u.address, 
        u.student_status as status,
        u.email,
        r.room_number
    FROM 
        users u
    LEFT JOIN 
        room_assignments ra ON u.id = ra.student_id AND ra.status = 'active'
    LEFT JOIN 
        rooms r ON ra.room_id = r.id
    WHERE
        u.role = 'student'
";

// Thêm điều kiện lọc nếu có
if ($status_filter !== '' && in_array($status_filter, ['active', 'inactive'])) {
    $query .= " AND u.student_status = '$status_filter'";
}

$query .= " ORDER BY u.id";

// Thực hiện truy vấn
$result = $conn->query($query);

// Kiểm tra lỗi khi thực hiện truy vấn
if (!$result) {
    die("Lỗi khi truy vấn dữ liệu: " . $conn->error);
}

// Tên file xuất
$filename = 'danh_sach_sinh_vien_' . date('Y-m-d_H-i-s');

// Các cột dữ liệu sẽ xuất
$columns = [
    'ID', 
    'Mã Sinh Viên', 
    'Họ', 
    'Tên', 
    'Giới Tính', 
    'Ngày Sinh', 
    'Khoa/Ngành', 
    'Số Điện Thoại', 
    'Địa Chỉ', 
    'Trạng Thái', 
    'Email', 
    'Phòng'
];

// Xử lý xuất dữ liệu theo định dạng được chọn
if ($export_type === 'excel') {
    // Xuất file Excel (.xlsx)
    // Kiểm tra xem có thư viện PhpSpreadsheet không
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Nếu chưa có thư viện, hiển thị thông báo lỗi
        echo "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Lỗi - Thiếu Thư Viện PhpSpreadsheet</title>
            <link rel='stylesheet' href='/LTW/assets/css/style.css'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                .container { max-width: 800px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
                h1 { color: #d9534f; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
                pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Lỗi: Thiếu Thư Viện PhpSpreadsheet</h1>
                <p>Để xuất dữ liệu sang định dạng Excel, hệ thống cần cài đặt thư viện PhpSpreadsheet.</p>
                <h3>Hướng Dẫn Cài Đặt:</h3>
                <ol>
                    <li>Mở terminal hoặc command prompt</li>
                    <li>Điều hướng đến thư mục gốc của dự án</li>
                    <li>Chạy lệnh: <pre>composer require phpoffice/phpspreadsheet</pre></li>
                    <li>Sau khi cài đặt xong, thử lại chức năng xuất Excel</li>
                </ol>
                <p>Hoặc bạn có thể:</p>
                <a href='/LTW/exports/export_students.php?type=csv' class='btn'>Xuất Sang CSV Thay Thế</a>
                <br><br>
                <a href='/LTW/views/admin/students/list.php' class='btn' style='background: #6c757d;'>Quay Lại Danh Sách Sinh Viên</a>
            </div>
        </body>
        </html>
        ";
        exit;
    }
    
    // Sử dụng thư viện PhpSpreadsheet để tạo file Excel
    require $_SERVER['DOCUMENT_ROOT'] . '/LTW/vendor/autoload.php';
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Thêm tiêu đề cột
    foreach ($columns as $index => $column) {
        $sheet->setCellValueByColumnAndRow($index + 1, 1, $column);
    }
    
    // Thêm dữ liệu
    $row = 2;
    while ($student = $result->fetch_assoc()) {
        $col = 1;
        foreach ($student as $value) {
            // Xử lý các trường đặc biệt
            if ($col === 5) { // Giới tính
                $value = ($value === 'male') ? 'Nam' : (($value === 'female') ? 'Nữ' : 'Khác');
            } elseif ($col === 6) { // Ngày sinh
                $value = $value ? date('d/m/Y', strtotime($value)) : '';
            } elseif ($col === 12) { // Trạng thái
                $value = ($value === 'active') ? 'Đang hoạt động' : 'Không hoạt động';
            }
            
            $sheet->setCellValueByColumnAndRow($col, $row, $value);
            $col++;
        }
        $row++;
    }
    
    // Điều chỉnh độ rộng cột
    foreach (range('A', 'N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Thiết lập header cho file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} else {
    // Xuất file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Tạo file handle cho đầu ra
    $output = fopen('php://output', 'w');
    
    // Thêm BOM (Byte Order Mark) để Excel có thể nhận dạng UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Ghi tiêu đề cột
    fputcsv($output, $columns);
    
    // Ghi dữ liệu
    while ($student = $result->fetch_assoc()) {
        // Xử lý các trường đặc biệt trước khi xuất
        if ($student['gender'] === 'male') {
            $student['gender'] = 'Nam';
        } elseif ($student['gender'] === 'female') {
            $student['gender'] = 'Nữ';
        } else {
            $student['gender'] = 'Khác';
        }
        
        if ($student['date_of_birth']) {
            $student['date_of_birth'] = date('d/m/Y', strtotime($student['date_of_birth']));
        }
        
        if ($student['status'] === 'active') {
            $student['status'] = 'Đang hoạt động';
        } else {
            $student['status'] = 'Không hoạt động';
        }
        
        fputcsv($output, $student);
    }
    
    // Đóng file handle
    fclose($output);
}

// Ghi log hoạt động
$export_type_display = $export_type === 'excel' ? 'Excel' : 'CSV';
$filter_info = $status_filter ? " (lọc: $status_filter)" : "";
logActivity('export_data', "Xuất danh sách sinh viên sang định dạng $export_type_display$filter_info");

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>