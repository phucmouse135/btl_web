<?php
// Bao gồm các tệp cần thiết
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/LTW/config/functions.php';

// Kiểm tra nếu người dùng đã đăng nhập và có vai trò admin hoặc nhân viên
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    header('Location: /LTW/index.php');
    exit;
}

// Khởi tạo biến
$error = '';
$success = '';
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student = null;
$documents = [];

// Lấy thông tin sinh viên
if ($student_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name, u.email, u.role
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    
    if (!$stmt) {
        $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
    } else {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Không tìm thấy sinh viên";
        } else {
            $student = $result->fetch_assoc();
            
            // Lấy tài liệu của sinh viên
            $docStmt = $conn->prepare("
                SELECT * FROM student_documents 
                WHERE student_id = ?
                ORDER BY upload_date DESC
            ");
            
            if ($docStmt) {
                $docStmt->bind_param("i", $student_id);
                $docStmt->execute();
                $docResult = $docStmt->get_result();
                
                while ($doc = $docResult->fetch_assoc()) {
                    $documents[] = $doc;
                }
                
                $docStmt->close();
            } else {
                // Tạo bảng student_documents nếu chưa tồn tại
                $createTableSQL = "
                    CREATE TABLE IF NOT EXISTS student_documents (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        student_id INT(11) NOT NULL,
                        document_name VARCHAR(255) NOT NULL,
                        file_name VARCHAR(255) NOT NULL,
                        document_type VARCHAR(100) NOT NULL,
                        file_size INT(11) NOT NULL,
                        file_extension VARCHAR(10) NOT NULL,
                        upload_date DATETIME NOT NULL,
                        notes TEXT,
                        status ENUM('active', 'archived') DEFAULT 'active',
                        created_by INT(11) NOT NULL,
                        created_at DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        KEY student_id (student_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";
                
                if ($conn->query($createTableSQL) === TRUE) {
                    // Bảng được tạo thành công
                } else {
                    $error = "Lỗi khi tạo bảng tài liệu: " . $conn->error;
                }
            }
        }
        
        $stmt->close();
    }
}

// Xử lý tải lên tài liệu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload' && $student_id > 0) {
    $document_name = sanitizeInput($_POST['document_name']);
    $document_type = sanitizeInput($_POST['document_type']);
    $notes = sanitizeInput($_POST['notes']);
    
    if (empty($document_name)) {
        $error = "Tên tài liệu là bắt buộc";
    } elseif (empty($document_type)) {
        $error = "Vui lòng chọn loại tài liệu";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Vui lòng chọn tệp để tải lên";
    } else {
        $file = $_FILES['document_file'];
        $allowedTypes = [
            'application/pdf', 
            'image/jpeg', 
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Tạo thư mục tải lên nếu chưa tồn tại
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/student_documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Tải lên tệp
        $uploadResult = uploadFile($file, $uploadDir, $allowedTypes, $maxSize);
        
        if ($uploadResult['status']) {
            $fileName = $uploadResult['fileName'];
            $fileSize = $file['size'];
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $createdBy = $_SESSION['user_id'];
            $uploadDate = date('Y-m-d H:i:s');
            
            // Thêm bản ghi tài liệu
            $stmt = $conn->prepare("
                INSERT INTO student_documents 
                (student_id, document_name, file_name, document_type, file_size, file_extension, upload_date, notes, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt) {
                $stmt->bind_param("isssssssss", 
                    $student_id, 
                    $document_name, 
                    $fileName, 
                    $document_type, 
                    $fileSize, 
                    $fileExtension, 
                    $uploadDate, 
                    $notes, 
                    $createdBy, 
                    $uploadDate
                );
                
                if ($stmt->execute()) {
                    // Ghi lại hoạt động
                    logActivity('upload_document', "Đã tải lên tài liệu '$document_name' cho sinh viên {$student['full_name']}");
                    $success = "Tài liệu đã được tải lên thành công";
                    
                    // Làm mới trang để hiển thị tài liệu đã tải lên
                    header("Location: documents.php?id=$student_id&success=upload");
                    exit;
                } else {
                    $error = "Lỗi khi lưu bản ghi tài liệu: " . $stmt->error;
                }
                
                $stmt->close();
            } else {
                $error = "Lỗi cơ sở dữ liệu: " . $conn->error;
            }
        } else {
            $error = $uploadResult['message'];
        }
    }
}

// Xử lý xóa tài liệu
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['doc_id']) && $student_id > 0) {
    $doc_id = intval($_GET['doc_id']);
    
    // Lấy tên tệp trước khi xóa
    $stmt = $conn->prepare("SELECT file_name, document_name FROM student_documents WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $doc_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $doc = $result->fetch_assoc();
        $fileName = $doc['file_name'];
        $docName = $doc['document_name'];
        
        // Xóa bản ghi khỏi cơ sở dữ liệu
        $deleteStmt = $conn->prepare("DELETE FROM student_documents WHERE id = ?");
        $deleteStmt->bind_param("i", $doc_id);
        
        if ($deleteStmt->execute()) {
            // Thử xóa tệp
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/LTW/uploads/student_documents/' . $fileName;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Ghi lại hoạt động
            logActivity('delete_document', "Đã xóa tài liệu '$docName' của sinh viên {$student['full_name']}");
            
            // Chuyển hướng với thông báo thành công
            header("Location: documents.php?id=$student_id&success=delete");
            exit;
        } else {
            $error = "Lỗi khi xóa tài liệu: " . $deleteStmt->error;
        }
        
        $deleteStmt->close();
    } else {
        $error = "Không tìm thấy tài liệu";
    }
    
    $stmt->close();
}

// Tiêu đề trang
$page_title = $student ? "Tài liệu - {$student['full_name']}" : "Tài liệu sinh viên";

// Bao gồm header
include $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/header.php';
?>

<div class="container-fluid">
    <!-- Tiêu đề trang -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Tài liệu sinh viên</h1>
        <div>
            <?php if ($student): ?>
                <a href="view.php?id=<?= $student_id ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại sinh viên
                </a>
            <?php else: ?>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Danh sách sinh viên
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <?= displayError($error); ?>
    <?php endif; ?>
    
    <?php if ($success || (isset($_GET['success']) && $_GET['success'] === 'upload')): ?>
        <?= displaySuccess($success ?: "Tài liệu đã được tải lên thành công"); ?>
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'delete'): ?>
        <?= displaySuccess("Tài liệu đã được xóa thành công"); ?>
    <?php endif; ?>

    <?php if (!$student): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Không có sinh viên nào được chọn. Vui lòng truy cập <a href="list.php">danh sách sinh viên</a> và chọn sinh viên để quản lý tài liệu của họ.
        </div>
    <?php elseif ($error === "Không tìm thấy sinh viên"): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> Không tìm thấy sinh viên. Vui lòng truy cập <a href="list.php">danh sách sinh viên</a> và chọn sinh viên hợp lệ.
        </div>
    <?php else: ?>
        <!-- Thẻ thông tin sinh viên -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Thông tin sinh viên</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <img class="img-profile rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;"
                            src="<?= !empty($student['profile_pic']) ? '/LTW/uploads/profile_pics/' . $student['profile_pic'] : '/LTW/assets/images/user.png' ?>">
                    </div>
                    <div class="col-md-10">
                        <h5><?= htmlspecialchars($student['full_name']) ?></h5>
                        <p class="mb-1"><strong>Mã sinh viên:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
                        <p class="mb-1"><strong>Khoa:</strong> <?= htmlspecialchars($student['department']) ?></p>
                        <p class="mb-0"><strong>Trạng thái:</strong> 
                            <span class="badge <?= $student['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($student['status']) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Biểu mẫu tải lên tài liệu -->
            <div class="col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Tải lên tài liệu</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload">
                            
                            <div class="mb-3">
                                <label for="document_name" class="form-label">Tên tài liệu*</label>
                                <input type="text" class="form-control" id="document_name" name="document_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="document_type" class="form-label">Loại tài liệu*</label>
                                <select class="form-select" id="document_type" name="document_type" required>
                                    <option value="">Chọn loại tài liệu</option>
                                    <option value="id_card">CMND/CCCD</option>
                                    <option value="passport">Hộ chiếu</option>
                                    <option value="birth_certificate">Giấy khai sinh</option>
                                    <option value="academic_transcript">Bảng điểm</option>
                                    <option value="admission_letter">Thư nhập học</option>
                                    <option value="medical_record">Hồ sơ y tế</option>
                                    <option value="contract">Hợp đồng ký túc xá</option>
                                    <option value="payment_receipt">Biên lai thanh toán</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="document_file" class="form-label">Tệp tài liệu*</label>
                                <input type="file" class="form-control" id="document_file" name="document_file" required>
                                <div class="form-text">Các loại tệp được phép: PDF, JPEG, PNG, DOC, DOCX. Kích thước tối đa: 5MB</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Ghi chú</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-secondary me-2">Đặt lại</button>
                                <button type="submit" class="btn btn-primary">Tải lên tài liệu</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Danh sách tài liệu -->
            <div class="col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Danh sách tài liệu</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Không tìm thấy tài liệu nào cho sinh viên này. Sử dụng biểu mẫu để tải lên tài liệu.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Tên tài liệu</th>
                                            <th>Loại</th>
                                            <th>Ngày tải lên</th>
                                            <th>Kích thước</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($doc['document_name']) ?></td>
                                                <td>
                                                    <?= ucwords(str_replace('_', ' ', htmlspecialchars($doc['document_type']))) ?>
                                                </td>
                                                <td><?= formatDateTime($doc['upload_date']) ?></td>
                                                <td><?= formatSize($doc['file_size']) ?></td>
                                                <td>
                                                    <a href="/LTW/uploads/student_documents/<?= htmlspecialchars($doc['file_name']) ?>" 
                                                       class="btn btn-primary btn-sm" target="_blank">
                                                        <i class="fas fa-eye"></i> Xem
                                                    </a>
                                                    <a href="#" class="btn btn-danger btn-sm" 
                                                       onclick="confirmDelete(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['document_name'])) ?>')">
                                                        <i class="fas fa-trash"></i> Xóa
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(docId, docName) {
    if (confirm('Bạn có chắc chắn muốn xóa tài liệu "' + docName + '"? Hành động này không thể hoàn tác.')) {
        window.location.href = 'documents.php?id=<?= $student_id ?>&action=delete&doc_id=' + docId;
    }
}
</script>

<?php
// Bao gồm footer
include $_SERVER['DOCUMENT_ROOT'] . '/LTW/includes/footer.php';
?>