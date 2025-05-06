<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Tiêu đề trang
$pageTitle = "Hệ Thống Quản Lý Ký Túc Xá";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        /* Tùy chỉnh CSS cho trang chủ */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/images/dormitory.jpg');
            background-size: cover;
            background-position: center;
            padding: 100px 0;
            color: #fff;
        }
        
        .feature-card {
            transition: transform 0.3s;
            height: 100%;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #4e73df;
        }
        
        .cta-section {
            background-color: #4e73df;
            color: white;
            padding: 60px 0;
        }
        
        .testimonial-card {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .partner-logo {
            max-height: 80px;
            filter: grayscale(100%);
            transition: filter 0.3s;
        }
        
        .partner-logo:hover {
            filter: grayscale(0%);
        }
        
        .main-navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white main-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-school me-2 text-primary"></i>
                <span class="fw-bold">Quản Lý Ký Túc Xá</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav" aria-controls="navbarNav" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Trang Chủ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Tính Năng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Giới Thiệu</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Liên Hệ</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white ms-2 px-3" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Bảng Điều Khiển
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-primary ms-2 px-3" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Đăng Nhập
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">Hệ Thống Quản Lý Ký Túc Xá Hiện Đại</h1>
            <p class="lead mb-5">Giải pháp toàn diện giúp quản lý ký túc xá hiệu quả, thuận tiện cho sinh viên và nhân viên</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="d-flex justify-content-center gap-3">
                    <a href="login.php" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-sign-in-alt me-2"></i> Đăng Nhập
                    </a>
                    <a href="#features" class="btn btn-outline-light btn-lg px-4">
                        <i class="fas fa-info-circle me-2"></i> Tìm Hiểu Thêm
                    </a>
                </div>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-primary btn-lg px-4">
                    <i class="fas fa-tachometer-alt me-2"></i> Đi Đến Bảng Điều Khiển
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Tính Năng Chính</h2>
                <p class="text-muted">Hệ thống của chúng tôi cung cấp đầy đủ các tính năng cần thiết</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <h5 class="card-title">Quản Lý Phòng</h5>
                            <p class="card-text">Quản lý thông tin chi tiết của phòng, tình trạng phòng và cơ sở vật chất</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="card-title">Quản Lý Sinh Viên</h5>
                            <p class="card-text">Theo dõi thông tin sinh viên, tài liệu và phân công phòng</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h5 class="card-title">Yêu Cầu Bảo Trì</h5>
                            <p class="card-text">Hệ thống báo cáo và xử lý các yêu cầu bảo trì nhanh chóng</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <h5 class="card-title">Báo Cáo & Thống Kê</h5>
                            <p class="card-text">Xem báo cáo và thống kê chi tiết để đưa ra quyết định quản lý tốt hơn</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="py-5 bg-light" id="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="fw-bold mb-3">Về Hệ Thống Của Chúng Tôi</h2>
                    <p class="lead mb-4">Hệ thống quản lý ký túc xá hiện đại, giúp nâng cao hiệu quả quản lý và trải nghiệm của sinh viên</p>
                    <p class="mb-4">Phần mềm được thiết kế với giao diện thân thiện, dễ sử dụng, và cung cấp đầy đủ các công cụ cần thiết cho quản lý ký túc xá hiện đại. Từ việc phân phòng tự động, quản lý yêu cầu bảo trì đến thống kê báo cáo chi tiết - tất cả đều được tối ưu hóa cho trải nghiệm người dùng tốt nhất.</p>
                    <div class="d-flex align-items-center mb-4">
                        <div class="me-4">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="fas fa-shield-alt text-white fs-3"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Bảo Mật Cao</h5>
                            <p class="text-muted mb-0">Thông tin được bảo mật và mã hóa theo tiêu chuẩn</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="me-4">
                            <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="fas fa-sync-alt text-white fs-3"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Cập Nhật Liên Tục</h5>
                            <p class="text-muted mb-0">Hệ thống được cập nhật thường xuyên với các tính năng mới</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/dormitory-management.jpg" alt="Hệ thống quản lý ký túc xá" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="fw-bold mb-4">Bạn Là Quản Lý Ký Túc Xá?</h2>
            <p class="lead mb-4">Khám phá cách hệ thống của chúng tôi có thể giúp bạn quản lý ký túc xá hiệu quả hơn</p>
            <a href="login.php" class="btn btn-light btn-lg px-4">
                <i class="fas fa-sign-in-alt me-2"></i> Bắt Đầu Ngay
            </a>
        </div>
    </section>

    <!-- Testimonial Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Khách Hàng Nói Gì</h2>
                <p class="text-muted">Những đánh giá từ khách hàng đã sử dụng hệ thống của chúng tôi</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <img src="https://randomuser.me/api/portraits/men/1.jpg" alt="Customer" class="rounded-circle" width="60" height="60">
                                </div>
                                <div>
                                    <h5 class="mb-1">Nguyễn Văn A</h5>
                                    <p class="text-muted mb-0">Quản lý KTX ĐH Quốc Gia</p>
                                </div>
                            </div>
                            <p class="mb-0">"Hệ thống đã giúp chúng tôi tiết kiệm rất nhiều thời gian trong việc quản lý phòng và xử lý yêu cầu bảo trì. Giao diện dễ sử dụng và tính năng đầy đủ."</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <img src="https://randomuser.me/api/portraits/women/2.jpg" alt="Customer" class="rounded-circle" width="60" height="60">
                                </div>
                                <div>
                                    <h5 class="mb-1">Trần Thị B</h5>
                                    <p class="text-muted mb-0">Giám đốc KTX Tư nhân</p>
                                </div>
                            </div>
                            <p class="mb-0">"Tôi rất hài lòng với hệ thống này. Báo cáo thống kê giúp tôi có cái nhìn tổng quan về tình hình ký túc xá, từ đó đưa ra các quyết định điều hành hiệu quả."</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <img src="https://randomuser.me/api/portraits/men/3.jpg" alt="Customer" class="rounded-circle" width="60" height="60">
                                </div>
                                <div>
                                    <h5 class="mb-1">Lê Văn C</h5>
                                    <p class="text-muted mb-0">Nhân viên ĐH Công Nghệ</p>
                                </div>
                            </div>
                            <p class="mb-0">"Sinh viên của chúng tôi rất hài lòng với việc có thể gửi yêu cầu bảo trì trực tiếp qua hệ thống và theo dõi tiến độ xử lý. Đây là một công cụ tuyệt vời!"</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Partner Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Đối Tác Của Chúng Tôi</h2>
                <p class="text-muted">Những đối tác tin cậy đã sử dụng hệ thống</p>
            </div>
            
            <div class="row justify-content-center align-items-center g-4">
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <img src="assets/images/partners/partner1.png" alt="Partner 1" class="img-fluid partner-logo">
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <img src="assets/images/partners/partner2.png" alt="Partner 2" class="img-fluid partner-logo">
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <img src="assets/images/partners/partner3.png" alt="Partner 3" class="img-fluid partner-logo">
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <img src="assets/images/partners/partner1.png" alt="Partner 4" class="img-fluid partner-logo">
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <img src="assets/images/partners/partner2.png" alt="Partner 5" class="img-fluid partner-logo">
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <img src="assets/images/partners/partner3.png" alt="Partner 6" class="img-fluid partner-logo">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="fw-bold mb-4">Liên Hệ Với Chúng Tôi</h2>
                    <p class="mb-4">Hãy liên hệ với chúng tôi nếu bạn có bất kỳ thắc mắc hoặc yêu cầu nào. Đội ngũ của chúng tôi sẽ hỗ trợ bạn trong thời gian sớm nhất.</p>
                    
                    <div class="d-flex align-items-start mb-3">
                        <div class="me-3 text-primary">
                            <i class="fas fa-map-marker-alt fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Địa Chỉ</h5>
                            <p class="mb-0">Số 1 Đại Cồ Việt, Hai Bà Trưng, Hà Nội</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-start mb-3">
                        <div class="me-3 text-primary">
                            <i class="fas fa-phone fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Điện Thoại</h5>
                            <p class="mb-0">+84 123 456 789</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-start mb-3">
                        <div class="me-3 text-primary">
                            <i class="fas fa-envelope fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Email</h5>
                            <p class="mb-0">info@dormmanagement.vn</p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="#" class="text-decoration-none me-3 fs-4">
                            <i class="fab fa-facebook-square text-primary"></i>
                        </a>
                        <a href="#" class="text-decoration-none me-3 fs-4">
                            <i class="fab fa-twitter-square text-primary"></i>
                        </a>
                        <a href="#" class="text-decoration-none me-3 fs-4">
                            <i class="fab fa-linkedin text-primary"></i>
                        </a>
                        <a href="#" class="text-decoration-none fs-4">
                            <i class="fab fa-youtube-square text-primary"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h3 class="fw-bold mb-4 text-center">Gửi Tin Nhắn</h3>
                            
                            <form>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Họ Tên</label>
                                        <input type="text" class="form-control" id="name" placeholder="Nhập họ tên">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" placeholder="Nhập email">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Tiêu Đề</label>
                                    <input type="text" class="form-control" id="subject" placeholder="Nhập tiêu đề">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="message" class="form-label">Tin Nhắn</label>
                                    <textarea class="form-control" id="message" rows="5" placeholder="Nhập tin nhắn của bạn"></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Gửi Tin Nhắn</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="mb-3">Hệ Thống Quản Lý Ký Túc Xá</h4>
                    <p>Giải pháp toàn diện giúp quản lý ký túc xá hiệu quả, nâng cao trải nghiệm của sinh viên và nhân viên.</p>
                </div>
                
                <div class="col-lg-2 col-md-4">
                    <h5 class="mb-3">Liên Kết</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white text-decoration-none">Trang Chủ</a></li>
                        <li><a href="#features" class="text-white text-decoration-none">Tính Năng</a></li>
                        <li><a href="#about" class="text-white text-decoration-none">Giới Thiệu</a></li>
                        <li><a href="#contact" class="text-white text-decoration-none">Liên Hệ</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-4">
                    <h5 class="mb-3">Điều Khoản</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white text-decoration-none">Điều Khoản Dịch Vụ</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Chính Sách Bảo Mật</a></li>
                        <li><a href="#" class="text-white text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Hỗ Trợ</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-4">
                    <h5 class="mb-3">Liên Hệ</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Số 1 Đại Cồ Việt, Hà Nội</li>
                        <li><i class="fas fa-phone me-2"></i> +84 123 456 789</li>
                        <li><i class="fas fa-envelope me-2"></i> info@dormmanagement.vn</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Hệ Thống Quản Lý Ký Túc Xá. Tất cả quyền được bảo lưu.</p>
                </div>
                <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                    <a href="#" class="text-white text-decoration-none me-3">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="text-white text-decoration-none me-3">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="text-white text-decoration-none me-3">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="text-white text-decoration-none">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>