$(document).ready(function () {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    $('.photo-upload-input').change(function (e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('.photo-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

    $('[data-bs-toggle="tooltip"]').tooltip();

    $('.alert-dismissible').delay(5000).fadeOut('slow');

    $('.confirm-action').click(function (e) {
        if (!confirm('Are you sure you want to perform this action?')) {
            e.preventDefault();
        }
    });

    $('[data-toggle="password-visibility"]').click(function () {
        var input = $($(this).data('target'));
        var icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    $('.auto-submit').change(function () {
        $(this).closest('form').submit();
    });

    $('[data-auto-submit]').change(function () {
        $(this).closest('form').submit();
    });

    $('.table-responsive').on('click', '.sortable', function () {
        var column = $(this).data('column');
        var currentUrl = new URL(window.location.href);
        var sort = currentUrl.searchParams.get('sort');
        var dir = currentUrl.searchParams.get('dir');

        if (sort === column) {
            dir = dir === 'asc' ? 'desc' : 'asc';
        } else {
            dir = 'asc';
        }

        currentUrl.searchParams.set('sort', column);
        currentUrl.searchParams.set('dir', dir);
        window.location.href = currentUrl.toString();
    });

    if (typeof initCharts !== 'undefined') {
        initCharts();
    }
});

function initCharts() {
    var ctxGender = document.getElementById('genderChart');
    if (ctxGender) {
        new Chart(ctxGender, {
            type: 'doughnut',
            data: {
                labels: Object.keys(genderData),
                datasets: [{
                    data: Object.values(genderData),
                    backgroundColor: ['#ffc107', '#0dcaf0'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20 }
                    }
                },
                cutout: '65%'
            }
        });
    }

    var ctxBar = document.getElementById('sportsChart');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: Object.keys(sportsData),
                datasets: [{
                    label: 'Registered Players',
                    data: Object.values(sportsData),
                    backgroundColor: ['#dc3545', '#0d6efd', '#198754', '#ffc107'],
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    var ctxCounty = document.getElementById('countyChart');
    if (ctxCounty) {
        new Chart(ctxCounty, {
            type: 'polarArea',
            data: {
                labels: Object.keys(countyData),
                datasets: [{
                    data: Object.values(countyData),
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(25, 135, 84, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(111, 66, 193, 0.7)',
                        'rgba(13, 202, 240, 0.7)',
                        'rgba(253, 126, 20, 0.7)',
                        'rgba(108, 117, 125, 0.7)',
                        'rgba(214, 51, 132, 0.7)',
                        'rgba(32, 201, 151, 0.7)',
                        'rgba(94, 114, 228, 0.7)',
                        'rgba(246, 194, 62, 0.7)',
                        'rgba(38, 166, 154, 0.7)',
                        'rgba(171, 71, 188, 0.7)',
                        'rgba(255, 112, 67, 0.7)'
                    ],
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 11 } }
                    }
                }
            }
        });
    }
}
