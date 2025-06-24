</div> <!-- Kết thúc Container Nội dung Chính -->

    <!-- Footer -->
    <footer class="footer mt-5 bg-dark text-white pt-5 pb-3">
        <div class="container">
            <div class="row">
                <!-- Thông tin liên hệ -->
                <div class="col-lg-4 mb-4">
                    <h5 class="text-uppercase border-start border-primary border-4 ps-3 mb-4"><?= getSetting('dormitory_name', 'Hệ thống quản lý ký túc xá') ?></h5>
                    <p>Địa chỉ: Km10 Trần Phú Hà Đông Hà Nội</p>
                    <p>Điện thoại: 0357762898</p>
                    <p>Email: ktx@example.edu.vn</p>
                    <div class="mt-4">
                        <a href="https://www.facebook.com/phucmouse" class="text-white me-3 fs-5"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white me-3 fs-5"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3 fs-5"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white me-3 fs-5"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <!-- Links hữu ích -->
                <div class="col-lg-4 mb-4">
                    <h5 class="text-uppercase border-start border-primary border-4 ps-3 mb-4">Liên kết hữu ích</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/LTW/views/maintenance/add.php" class="text-white text-decoration-none"><i class="fas fa-angle-right me-2"></i>Báo cáo sự cố</a></li>
                        <li class="mb-2"><a href="/LTW/views/profile.php" class="text-white text-decoration-none"><i class="fas fa-angle-right me-2"></i>Thông tin cá nhân</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none"><i class="fas fa-angle-right me-2"></i>Nội quy KTX</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none"><i class="fas fa-angle-right me-2"></i>Câu hỏi thường gặp</a></li>
                    </ul>
                </div>
                
                <!-- Liên hệ và địa điểm -->
                <div class="col-lg-4 mb-4">
                    <h5 class="text-uppercase border-start border-primary border-4 ps-3 mb-4">Bản đồ</h5>
                    <p>Địa chỉ chính xác của ký túc xá:</p>
                    <div class="ratio ratio-16x9 mt-3">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3725.3025489553853!2d105.78573597506477!3d20.980908089413893!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135accdd8a1ad71%3A0xa2f9b16036648187!2zS8O9IHTPg9SJIEM2LCDEkOG6oWkgaOG7jWMgQuG6r2MgS2h1IEE!5e0!3m2!1svi!2s!4v1704555566325!5m2!1svi!2s" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
            
            <!-- Phần giữa footer -->
            <div class="row mt-3">
                <div class="col-md-6 mb-3">
                    <h6 class="text-uppercase border-start border-primary border-4 ps-3 mb-3">Giờ làm việc</h6>
                    <p class="mb-1"><strong>Văn phòng quản lý:</strong> Thứ 2 - Thứ 6: 8:00 - 17:00</p>
                    <p class="mb-1"><strong>Bảo vệ & Kỹ thuật:</strong> 24/7</p>
                </div>
                
                <!-- Đối tác - Đã sửa lại để hiển thị đúng -->
                <div class="col-md-6 mb-3">
                    <h6 class="text-uppercase border-start border-primary border-4 ps-3 mb-3">Đối tác</h6>
                    <div class="partner-logos-container d-flex flex-wrap align-items-center">
                    </div>
                </div>
            </div>
            
            <!-- Phần đối tác hiển thị ở màn hình nhỏ - giống trong hình bạn đã chia sẻ -->
            <div class="row mt-3 partners-mobile d-md-none">
                <div class="col-12 text-center">
                    <h6 class="text-uppercase mb-4">ĐỐI TÁC</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <img src="/LTW/assets/images/partners/partner1.png" alt="Partner 1" class="partner-logo-mobile mx-2">
                        <img src="/LTW/assets/images/partners/partner2.png" alt="Partner 2" class="partner-logo-mobile mx-2">
                        <img src="/LTW/assets/images/partners/partner3.png" alt="Partner 3" class="partner-logo-mobile mx-2">
                    </div>
                </div>
            </div>
            
            <!-- Phần dưới cùng -->
            <hr class="mt-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">© <?= date('Y') ?> <?= getSetting('dormitory_name', 'Hệ thống quản lý ký túc xá') ?> | Bản quyền thuộc về chúng tôi</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="#" class="text-white text-decoration-none">Điều khoản sử dụng</a></li>
                        <li class="list-inline-item">|</li>
                        <li class="list-inline-item"><a href="#" class="text-white text-decoration-none">Chính sách bảo mật</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Nút trở về đầu trang -->
    <button id="backToTop" class="btn btn-primary rounded-circle position-fixed bottom-0 end-0 m-4" style="display:none;z-index:9999;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <style>
        /* Định dạng cho phần đối tác */
        .partner-logo {
            height: 40px;
            max-width: 100px;
            object-fit: contain;
            filter: brightness(0) invert(1); /* Chuyển logo sang màu trắng */
        }
        
        /* Định dạng cho phần đối tác ở màn hình nhỏ */
        .partners-mobile {
            background-color: #1e1e24;
            padding: 20px 0;
            border-radius: 5px;
        }
         
        .partner-logo-mobile {
            height: 30px;
            max-width: 90px;
            object-fit: contain;
            filter: brightness(0) invert(1); /* Chuyển logo sang màu trắng */
        }
    </style>    <!-- Gói Bootstrap JS kèm Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- JavaScript Tùy chỉnh -->
    <script src="/LTW/assets/js/ajax-utils.js"></script>
    <script src="/LTW/assets/js/main.js"></script>
    
    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>

    <!-- Theme Switching & Back to Top Button Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme Toggle
            var themeToggle = document.getElementById('themeToggle');
            var darkIcon = document.getElementById('darkModeIcon');
            var lightIcon = document.getElementById('lightModeIcon');
            
            // Cập nhật trạng thái icon dựa trên theme hiện tại
            function updateThemeIcon() {
                var currentTheme = localStorage.getItem('theme') || 'light';
                if (currentTheme === 'dark') {
                    darkIcon.style.display = 'none';
                    lightIcon.style.display = 'inline-block';
                } else {
                    darkIcon.style.display = 'inline-block';
                    lightIcon.style.display = 'none';
                }
            }
            
            // Khởi tạo icon phù hợp với theme hiện tại
            if (themeToggle) {
                updateThemeIcon();
                
                // Xử lý sự kiện click nút chuyển đổi theme
                themeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    var currentTheme = localStorage.getItem('theme') || 'light';
                    var newTheme = currentTheme === 'light' ? 'dark' : 'light';
                    
                    // Cập nhật theme trong localStorage
                    localStorage.setItem('theme', newTheme);
                    
                    // Áp dụng theme mới
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    
                    // Cập nhật database nếu đang ở trang settings
                    var systemThemeSelect = document.getElementById('system_theme');
                    if (systemThemeSelect) {
                        systemThemeSelect.value = newTheme;
                    }
                    
                    // Cập nhật icon
                    updateThemeIcon();
                });
            }
            
            // Back to Top Button
            const backToTopButton = document.getElementById('backToTop');
            
            if (backToTopButton) {
                // Hiện nút khi cuộn xuống 300px
                window.addEventListener('scroll', () => {
                    if (window.pageYOffset > 300) {
                        backToTopButton.style.display = 'block';
                    } else {
                        backToTopButton.style.display = 'none';
                    }
                });
                
                // Cuộn lên đầu trang khi click vào nút
                backToTopButton.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>
</body>
</html>