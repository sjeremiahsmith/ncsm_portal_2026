<footer class="public-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                    <img src="<?= APP_URL ?>assets/images/ncsm.png" alt="NCSM" style="height:45px;width:45px;object-fit:contain;border-radius:50%;margin-bottom:0.8rem;">
                    <h5>National County Sports Meet</h5>
                    <p class="text-white-50">Ministry of Youth & Sports, Republic of Liberia. Uniting 15 counties through the power of sports.</p>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="footer-heading">Quick Links</h6>
                <ul class="footer-links">
                    <li><a href="<?= APP_URL ?>">Home</a></li>
                    <li><a href="<?= APP_URL ?>pages/media.php">Media</a></li>
                    <li><a href="<?= APP_URL ?>pages/about.php">About</a></li>
                    <li><a href="<?= APP_URL ?>pages/contact.php">Contact Us</a></li>
                    <li><a href="<?= APP_URL ?>pages/public_livescore.php">LiveScore</a></li>
                    <li><a href="<?= APP_URL ?>auth/login.php">Login</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h6 class="footer-heading">Sports Disciplines</h6>
                <ul class="footer-links">
                    <li><a href="#"><i class="bi bi-circle-fill text-success me-2" style="font-size:0.5rem;"></i>Football</a></li>
                    <li><a href="#"><i class="bi bi-circle-fill text-danger me-2" style="font-size:0.5rem;"></i>Kickball</a></li>
                    <li><a href="#"><i class="bi bi-circle-fill text-warning me-2" style="font-size:0.5rem;"></i>Basketball</a></li>
                    <li><a href="#"><i class="bi bi-circle-fill text-primary me-2" style="font-size:0.5rem;"></i>Athletics</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-heading">Contact Info</h6>
                <ul class="footer-links">
                    <li><i class="bi bi-geo-alt me-2"></i>Ministry of Youth & Sports, Monrovia, Liberia</li>
                    <li><i class="bi bi-envelope me-2"></i>info@ncsm.gov.lr</li>
                    <li><i class="bi bi-telephone me-2"></i>+231 770 000 000</li>
                </ul>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> National County Sports Meet. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0 text-white-50 small">Ministry of Youth & Sports &mdash; Republic of Liberia</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    // Navbar scroll effect
    const nav = document.getElementById('mainNav');
    if (nav) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        }, { passive: true });
    }

    // Close mobile nav when a link is clicked
    var navCollapse = document.getElementById('publicNav');
    if (navCollapse) {
        var navLinks = navCollapse.querySelectorAll('.nav-link');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                var bsCollapse = bootstrap.Collapse.getInstance(navCollapse);
                if (bsCollapse && navCollapse.classList.contains('show')) {
                    bsCollapse.hide();
                }
            });
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                var navHeight = nav ? nav.offsetHeight : 0;
                var targetPos = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                window.scrollTo({ top: targetPos, behavior: 'smooth' });
            }
        });
    });
})();
</script>
</body>
</html>
