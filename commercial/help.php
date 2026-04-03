<?php
require_once 'config.php';

ob_start(); // Start output buffering

// Set unique session name for Commercial edition BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help | Smart Locker System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }

        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* HERO */
        .help-hero {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 50px 0 60px;
            margin-bottom: -30px;
        }
        .help-hero h1 { font-weight: 700; font-size: 2rem; }
        .help-hero p  { opacity: 0.85; font-size: 1.05rem; }

        /* CARDS */
        .help-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            margin-bottom: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .help-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .help-card .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            margin-bottom: 16px;
            flex-shrink: 0;
        }
        .help-card h5 {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 14px;
        }

        /* STEPS */
        .step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }
        .step-num {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .step p { margin: 0; color: #555; font-size: 0.95rem; }

        /* FAQ */
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .accordion-button:not(.collapsed)::after {
            filter: brightness(10);
        }
        .accordion-button {
            font-weight: 600;
            color: #2c3e50;
        }
        .accordion-body { color: #555; font-size: 0.95rem; }

        /* CONTACT */
        .contact-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .contact-item:last-child { border-bottom: none; }
        .contact-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }
        .contact-item strong { display: block; color: #2c3e50; font-size: 0.85rem; }
        .contact-item span { color: #555; font-size: 0.95rem; }

        /* LOCKER LOCATIONS */
        .location-badge {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 0.9rem;
        }
        .location-badge i { color: #3498db; }

        /* BACK BTN */
        .btn-back {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 8px;
            padding: 6px 16px;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.25); color: white; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fas fa-lock me-2"></i>Smart Locker
        </a>
        <div class="navbar-nav ms-auto align-items-center gap-2">
            <span class="text-white-50 small"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user_name); ?></span>
            <a href="dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<div class="help-hero">
    <div class="container text-center">
        <i class="fas fa-question-circle fa-3x mb-3 opacity-75"></i>
        <h1>Help Center</h1>
        <p>Guidelines for using the Smart Locker System</p>
    </div>
</div>

<div class="container py-5">
    <div class="row">

        <!-- CARA GUNA LOCKER -->
        <div class="col-lg-6">
            <div class="help-card">
                <div class="icon-box" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-box-open"></i>
                </div>
                <h5><i class="fas fa-list-ol me-2 text-primary"></i>How to Use the Locker</h5>

                <div class="step">
                    <div class="step-num">1</div>
                    <p><strong>Register & Login</strong> — Register an account with your email and student ID, then log in.</p>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <p><strong>Assign Locker</strong> — Go to <em>Assign Locker</em> and select an available locker to request.</p>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <p><strong>Wait for Approval</strong> — The admin will approve your request. You will be able to access the locker after approval.</p>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <p><strong>Print QR Code</strong> — Go to <em>Print QR</em> to print or save your locker's QR code.</p>
                </div>
                <div class="step">
                    <div class="step-num">5</div>
                    <p><strong>Scan to Open</strong> — Go to <em>Scan Access</em>, scan the QR code to open the locker.</p>
                </div>
                <div class="step">
                    <div class="step-num">6</div>
                    <p><strong>Remove Access</strong> — after using the locker, click <em>Remove Access</em> to free up the locker.</p>
                </div>
            </div>
        </div>

        <!-- CARA SCAN QR -->
        <div class="col-lg-6">
            <div class="help-card">
                <div class="icon-box" style="background: linear-gradient(135deg, #00b09b, #96c93d);">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h5><i class="fas fa-camera me-2 text-success"></i>How to Scan QR Code</h5>

                <div class="step">
                    <div class="step-num">1</div>
                    <p>Click <strong>Scan Access</strong> from the dashboard.</p>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <p>Allow access to the <strong>camera</strong> when the browser requests permission.</p>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <p>Point your QR code at the camera — the system will <strong>scan automatically</strong>.</p>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <p>Once the scan is successful, click the <strong>Open Locker</strong> button to open the locker.</p>
                </div>
                <div class="step">
                    <div class="step-num">5</div>
                    <p>The locker will <strong>remain open for 5 seconds</strong> — retrieve/place items and close it again.</p>
                </div>

                <hr class="my-3">
                <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1 text-primary"></i>
                Make sure the QR code is in good condition and the lighting is sufficient for the best scanning results.</p>
            </div>
        </div>

        <!-- FAQ -->
        <div class="col-12">
            <div class="help-card">
                <div class="icon-box" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-comments"></i>
                </div>
                <h5><i class="fas fa-question me-2 text-danger"></i> Frequently Asked Questions (FAQ)</h5>

                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item border-0 mb-2" style="background:#f8f9fa; border-radius:10px; overflow:hidden;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                What should I do if the QR code cannot be scanned?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Make sure the camera is working properly and the QR code is not damaged. Try printing the QR code again from the <strong>Print QR</strong> menu. If the issue persists, contact the admin to reset your access key.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 mb-2" style="background:#f8f9fa; border-radius:10px; overflow:hidden;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                How long can I use the locker?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                The usage duration of the locker depends on the policies set by the admin. Please contact the admin for more information about the allowed usage period.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 mb-2" style="background:#f8f9fa; border-radius:10px; overflow:hidden;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What if the locker doesn't open after scanning?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Make sure you have a good internet connection. Try scanning again and click <strong>Open Locker</strong>. If the problem persists, the locker may be experiencing technical issues — contact the admin immediately.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 mb-2" style="background:#f8f9fa; border-radius:10px; overflow:hidden;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Can I have more than one locker?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                This depends on the admin's policies. By default, each user can request one available locker. Contact the admin for special approval if you need more than one locker.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0" style="background:#f8f9fa; border-radius:10px; overflow:hidden;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                How to remove locker access?
                            </button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                go to <strong>Dashboard</strong> → click the three-dot icon (⋮) on your locker → select <strong>Remove Access</strong>. Or click the <strong>Remove Access</strong> button from Quick Actions. Make sure the locker is empty before removing access.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOKASI LOCKER & CONTACT -->
        <div class="col-lg-6">
            <div class="help-card">
                <div class="icon-box" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h5><i class="fas fa-map me-2 text-info"></i>Locker Locations</h5>
                <div class="location-badge"><i class="fas fa-map-pin"></i> Locker 001 — Tingkat 4, Nombor 17</div>
                <div class="location-badge"><i class="fas fa-map-pin"></i> Locker 002 — Building A, Floor 1</div>
                <div class="location-badge"><i class="fas fa-map-pin"></i> Locker 003 — Building A, Floor 1</div>
                <p class="text-muted small mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>Locker locations may be updated. Contact the admin for the latest information.</p>
            </div>
        </div>

        <!-- HUBUNGI KAMI -->
        <div class="col-lg-6">
            <div class="help-card">
                <div class="icon-box" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                    <i class="fas fa-headset"></i>
                </div>
                <h5><i class="fas fa-phone me-2 text-warning"></i>Reach Us</h5>

                <div class="contact-item">
                    <div class="contact-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <strong>Email</strong>
                        <span>admin@system.edu.my</span>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon" style="background: linear-gradient(135deg, #00b09b, #96c93d);">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div>
                        <strong>Phone</strong>
                        <span>03-XXXX XXXX (Office Hours)</span>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <strong>Office Hours</strong>
                        <span>Monday – Friday, 8:00am – 5:00pm</span>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <strong>Address</strong>
                        <span>lot 189,jalan teknologi, 54100 Kuala Lumpur, Malaysia</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- BACK BUTTON -->
    <div class="text-center mt-2 mb-4">
        <a href="dashboard.php" class="btn btn-primary px-4 py-2 rounded-pill">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>