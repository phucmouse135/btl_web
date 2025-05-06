<?php 
// Include các tệp cần thiết
require_once "../../config/database.php";
require_once "../../config/functions.php";

// Kiểm tra xem người dùng đã đăng nhập và có quyền quản trị viên không
if (!isLoggedIn() || !isAdmin()) {
    redirect("/LTW/index.php");
    exit;
}

// Đặt tiêu đề trang
$page_title = "Báo Cáo";

// Include header
include "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Báo Cáo</h1>
    </div>

    <div class="row">
        <div class="col-xl-12 col-md-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Báo Cáo Có Sẵn</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Báo Cáo Thanh Toán -->
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="h5 mb-0 font-weight-bold text-success">Báo Cáo Thanh Toán</div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-3">
                                                Tổng kết và thống kê thanh toán
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-success btn-sm">Tạo Báo Cáo</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Báo Cáo Lưu Trú -->
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="h5 mb-0 font-weight-bold text-primary">Báo Cáo Lưu Trú</div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-3">
                                                Thống kê sử dụng phòng
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-home fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-primary btn-sm">Tạo Báo Cáo</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Báo Cáo Sinh Viên -->
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="h5 mb-0 font-weight-bold text-info">Báo Cáo Sinh Viên</div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-3">
                                                Nhân khẩu học và trạng thái sinh viên
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-info btn-sm">Tạo Báo Cáo</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>