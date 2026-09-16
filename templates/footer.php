    <?php if (isLoggedIn()): ?>
            </main>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= APP_URL ?>assets/js/main.js"></script>
    <script>
    (function() {
        var sidebar = document.getElementById('sidebarMenu');
        var overlay = document.getElementById('sidebarOverlay');
        var toggler = document.getElementById('sidebarToggle');
        if (!sidebar || !overlay || !toggler) return;

        function isMobile() {
            return window.innerWidth < 768;
        }

        function openSidebar() {
            sidebar.classList.add('show');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        toggler.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', function() {
            closeSidebar();
        });

        sidebar.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (isMobile()) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function() {
            if (!isMobile()) {
                closeSidebar();
            }
        });
    })();
    </script>
</body>
</html>
