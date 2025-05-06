<?php
// Bao gồm tệp header
require_once 'includes/header.php';

// Yêu cầu người dùng phải đăng nhập
requireLogin();

// Lấy thống kê cho bảng điều khiển
function getDashboardStats() {
    global $conn;
    $stats = [
        'students' => 0,
        'rooms' => 0,
        'available_rooms' => 0,
        'pending_maintenance' => 0
    ];
    
    // Đếm số lượng sinh viên (users với role='student')
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student'");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['students'] = $row['total'];
    }
    
    // Đếm số lượng phòng
    $result = $conn->query("SELECT COUNT(*) as total FROM rooms");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['rooms'] = $row['total'];
    }
    
    // Đếm số lượng phòng trống
    $result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'available'");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['available_rooms'] = $row['total'];
    }
    
    // Đếm số lượng yêu cầu bảo trì đang chờ xử lý
    $result = $conn->query("SELECT COUNT(*) as total FROM maintenance_requests WHERE status = 'pending'");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['pending_maintenance'] = $row['total'];
    }
    
    return $stats;
}

$stats = getDashboardStats();
?>

<!-- Hero Section with Carousel -->
<div class="row mb-4">
    <div class="col-12">
        <div id="dashboardCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#dashboardCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#dashboardCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#dashboardCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner rounded shadow">
                <div class="carousel-item active">
                    <div class="carousel-image-container">
                        <img src="https://images.unsplash.com/photo-1555854877-bab0e564b8d5?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&h=400&q=80" class="d-block w-100" alt="Dormitory Exterior">
                    </div>
                    <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2">
                        <h3>Chào mừng đến với Hệ thống Quản lý Ký túc xá</h3>
                        <p>Nơi an toàn và tiện nghi cho sinh viên</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="carousel-image-container">
                        <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&h=400&q=80" alt="Student Lounge">
                    </div>
                    <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2">
                        <h3>Không gian học tập hiện đại</h3>
                        <p>Trang bị đầy đủ tiện nghi cho việc học tập và nghiên cứu</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="carousel-image-container">
                        <img src="https://images.unsplash.com/photo-1594312915251-48db9280c8f1?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&h=400&q=80" alt="Dormitory Room">
                    </div>
                    <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2">
                        <h3>Phòng ở tiện nghi</h3>
                        <p>Môi trường sống thoải mái và an toàn cho sinh viên</p>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#dashboardCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#dashboardCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</div>

<style>
/* Custom styles for carousel */
.carousel-image-container {
    height: 400px;
    width: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #000;
}

.carousel-image-container img {
    width: 100%;
    height: 100%;
    object-fit: cover; /* This ensures images cover the container while maintaining aspect ratio */
}

.carousel-caption {
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 80%;
    max-width: 800px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .carousel-image-container {
        height: 300px;
    }
    
    .carousel-caption {
        display: block !important;
        padding: 10px;
        bottom: 10px;
    }
    
    .carousel-caption h3 {
        font-size: 1.2rem;
    }
    
    .carousel-caption p {
        font-size: 0.9rem;
        margin-bottom: 0;
    }
}

@media (max-width: 576px) {
    .carousel-image-container {
        height: 200px;
    }
}
</style>

<!-- Phần Chào Mừng -->
<div class="dashboard-welcome mb-4 p-4 bg-white rounded shadow">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h4>Chào mừng, <?php echo $_SESSION['username']; ?>!</h4>
            <p class="text-muted">Dưới đây là những gì đang diễn ra tại ký túc xá của bạn hôm nay.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <span class="text-muted">Hôm nay là <?php echo date('l, d F Y'); ?></span>
        </div>
    </div>
</div>

<?php if (hasRole('admin') || hasRole('staff')): ?>
<!-- Bảng Điều Khiển Quản Trị/ Nhân Viên -->
<div class="row">
    <!-- Sinh viên -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng số sinh viên</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['students']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/LTW/views/admin/students/list.php" class="text-primary">Xem chi tiết <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Phòng -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Phòng trống</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['available_rooms']; ?> trên <?php echo $stats['rooms']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-door-open fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/LTW/views/admin/rooms/list.php" class="text-success">Xem chi tiết <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Yêu cầu bảo trì -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Bảo trì đang chờ</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_maintenance']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/LTW/views/maintenance/list.php" class="text-warning">Xem chi tiết <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- FAQ Section with Accordion -->
<div class="row mt-4 mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Câu hỏi thường gặp</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                Làm thế nào để đăng ký phòng ở KTX?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Sinh viên cần hoàn thành biểu mẫu đăng ký trực tuyến hoặc nộp đơn trực tiếp tại văn phòng quản lý KTX. Sau khi đơn được xét duyệt, bạn sẽ nhận được thông báo và hướng dẫn thanh toán.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                Quy trình báo cáo sự cố và bảo trì phòng?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Khi gặp sự cố về cơ sở vật chất trong phòng, bạn có thể báo cáo trực tuyến qua hệ thống quản lý hoặc liên hệ trực tiếp với nhân viên quản lý tòa nhà. Yêu cầu bảo trì sẽ được xử lý trong vòng 24-48 giờ tùy theo mức độ ưu tiên.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFour">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                Làm thế nào để đổi phòng hoặc đổi bạn cùng phòng?
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yêu cầu đổi phòng được xem xét sau 2 tuần đầu của học kỳ, và phụ thuộc vào tình trạng phòng trống. Cả hai bạn cùng phòng cần đồng ý và nộp đơn yêu cầu lên văn phòng quản lý KTX để được xem xét.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Thống kê và Biểu đồ -->
<div class="row mt-4">
    <div class="col-12">
        <!-- Thống kê tình trạng phòng -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-success">Tình trạng phòng</h6>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light" type="button" id="roomChartOptions" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="roomChartOptions">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-download me-2"></i>Tải xuống</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-print me-2"></i>In báo cáo</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-file-csv me-2"></i>Xuất CSV</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie" style="height: 300px;">
                    <canvas id="roomStatusChart"></canvas>
                </div>
                <div class="mt-4">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="d-flex align-items-center mb-2">
                                <div style="width: 15px; height: 15px; background-color: rgba(255, 99, 132, 0.6); margin-right: 10px;"></div>
                                <div>
                                    <span class="small">Đã đầy:</span>
                                    <span class="small fw-bold ms-1"><?php echo $stats['rooms'] - $stats['available_rooms']; ?> phòng</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <div style="width: 15px; height: 15px; background-color: rgba(54, 162, 235, 0.6); margin-right: 10px;"></div>
                                <div>
                                    <span class="small">Còn trống:</span>
                                    <span class="small fw-bold ms-1"><?php echo $stats['available_rooms']; ?> phòng</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="d-flex align-items-center mb-2">
                                <div style="width: 15px; height: 15px; background-color: rgba(255, 206, 86, 0.6); margin-right: 10px;"></div>
                                <div>
                                    <span class="small">Đang bảo trì:</span>
                                    <span class="small fw-bold ms-1">5 phòng</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Truy cập nhanh -->
<div class="row mt-2 mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Truy cập nhanh</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 col-6 mb-3">
                        <a href="/LTW/views/profile.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="fas fa-user fa-2x mb-2"></i>
                            <span>Hồ sơ của tôi</span>
                        </a>
                    </div>
                    <div class="col-md-4 col-6 mb-3">
                        <a href="/LTW/views/maintenance/add.php" class="btn btn-outline-warning btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="fas fa-tools fa-2x mb-2"></i>
                            <span>Báo sự cố</span>
                        </a>
                    </div>
                    <div class="col-md-4 col-6 mb-3">
                        <a href="/LTW/views/admin/rooms/list.php" class="btn btn-outline-success btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="fas fa-door-open fa-2x mb-2"></i>
                            <span>Danh sách phòng</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Biểu đồ tình trạng phòng
    var roomCtx = document.getElementById('roomStatusChart').getContext('2d');
    var roomChart = new Chart(roomCtx, {
        type: 'doughnut',
        data: {
            labels: ['Đã đầy', 'Còn trống', 'Đang bảo trì'],
            datasets: [{
                data: [<?php echo $stats['rooms'] - $stats['available_rooms']; ?>, <?php echo $stats['available_rooms']; ?>, 5],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});
</script>

<!-- Bao gồm footer -->
<?php
require_once 'includes/footer.php';
?>