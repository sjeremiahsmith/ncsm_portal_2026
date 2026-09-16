<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$contactSuccess = '';
$contactError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    requireCsrfToken();
    $name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $contactError = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter a valid email address.';
    } else {
        $db = getDb();
        try {
            ensureContactMessagesTable();
            $db->insert(
                "INSERT INTO contact_messages (full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)",
                [$name, $email, $phone, $subject, $message]
            );
            if (isLoggedIn()) {
                try { logActivity('contact_message', "New contact message from $name ($email)"); } catch (Exception $ignored) {}
            }
            $contactSuccess = 'Your message has been sent successfully! We will get back to you soon.';
        } catch (Exception $e) {
            error_log('Contact form error: ' . $e->getMessage());
            $contactError = 'An error occurred while sending your message. Please try again later.';
        }
    }
}

$pageTitle = 'Contact Us';
include __DIR__ . '/../templates/public_header.php';
?>

<!-- Hero -->
<section class="contact-hero">
    <div class="hero-bg"></div>
    <div class="container">
        <span class="hero-badge">Get In Touch</span>
        <h1>Contact Us</h1>
        <p>Have questions about the National County Sports Meet? Reach out to the Ministry of Youth & Sports.</p>
    </div>
</section>

<!-- Contact Section -->
<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="contact-form-card">
                    <h4 class="fw-bold mb-1">Send Us a Message</h4>
                    <p class="text-muted mb-4">Fill out the form below and we'll get back to you as soon as possible.</p>
                    <?php if ($contactSuccess): ?>
                        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= $contactSuccess ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($contactError): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-1"></i><?= $contactError ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="full_name" class="form-control" placeholder="Your full name" required value="<?= sanitize($_POST['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="your@email.com" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" placeholder="+231 770 000 000" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Subject</label>
                                <select name="subject" class="form-select">
                                    <option value="">Select a topic...</option>
                                    <?php
                                    $subjects = ['Player Registration', 'Match Schedules', 'County Groupings', 'Portal Access', 'General Inquiry', 'Media & Press'];
                                    $selectedSubject = $_POST['subject'] ?? '';
                                    foreach ($subjects as $s):
                                    ?>
                                    <option value="<?= $s ?>" <?= $selectedSubject === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Message</label>
                                <textarea name="message" class="form-control" rows="5" placeholder="Write your message here..." required><?= sanitize($_POST['message'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="contact_submit" class="btn btn-primary btn-lg px-4">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="contact-info-card">
                    <h4 class="fw-bold mb-4">Contact Information</h4>

                    <div class="contact-info-item">
                        <div class="icon-box">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <h6>Office Address</h6>
                            <p>Ministry of Youth & Sports<br>Paynesville City, Monrovia, Liberia</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="icon-box">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div>
                            <h6>Email Address</h6>
                            <p>info@moys.gov.lr</p>
                            <h6>Website</h6>
                            <p>moys.gov.lr</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="icon-box">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div>
                            <h6>Phone Number</h6>
                            <p>+231 770 000 000<br>+231 880 000 000</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="icon-box">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <h6>Office Hours</h6>
                            <p>Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 9:00 AM - 1:00 PM</p>
                        </div>
                    </div>

                    <hr style="border-color:rgba(255,255,255,0.1);margin:1.5rem 0;">

                    <h6 class="mb-3" style="font-weight:600;">Follow Us</h6>
                    <div class="d-flex gap-3">
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section" style="background:#f0f0f0;">
    <div class="container">
        <div class="section-header">
            <span class="overline">FAQ</span>
            <h2>Frequently Asked Questions</h2>
            <p>Quick answers to common questions about the National County Sports Meet.</p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item border-0 mb-3 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I register a player for the NCSM?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                Player registration is done through the NCSM Portal. County Coordinators can register players for their respective counties. Each player needs personal details, sport discipline, and a photo. After registration, the Association Admin reviews and approves the registration.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item border-0 mb-3 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                What are the county groupings?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                The 15 counties are divided into 4 groups: Group A (Nimba, Grand Gedeh - Host, River Gee, Gbarpolu), Group B (Grand Cape Mount, Bong - Host, Maryland, River Cess), Group C (Grand Bassa, Lofa - Host, Montserrado, Sinoe), and Group D (Margibi, Grand Kru - Host, Bomi).
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item border-0 mb-3 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What sports are included in the NCSM?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                The NCSM features four sports disciplines: Football (governed by LFA), Kickball (LKA), Basketball (LBA), and Athletics (LAA). Each sport has its own set of rules, schedules, and championship titles.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item border-0 mb-3 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                How can I access the player portal?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                Click the "Login" button in the navigation bar. Enter your username and password to access the portal. If you need an account, contact the Ministry of Youth & Sports or your county coordinator for credentials.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item border-0 mb-3 rounded-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                Where are the matches played?
                            </button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                Matches are played at various venues across Liberia, with the main events typically held at the Samuel Kanyon Doe Sports Complex in Paynesville and the Antoinette Tubman Stadium in Monrolia. Group stage matches may also be held in county capitals.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/public_footer.php'; ?>
