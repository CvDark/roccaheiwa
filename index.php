<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Locker System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --cream:   #f7f4ef;
            --dark:    #1a1a18;
            --mid:     #3d3d38;
            --accent:  #2a6ef5;
            --accent2: #0fce8f;
            --border:  #e0dcd4;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── NOISE TEXTURE OVERLAY ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        /* ── NAV ── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 20px 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(12px);
            background: rgba(247, 244, 239, 0.85);
            border-bottom: 1px solid var(--border);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 18px;
            color: var(--dark);
            text-decoration: none;
        }

        .logo-icon {
            width: 36px; height: 36px;
            background: var(--dark);
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: var(--cream);
            font-size: 16px;
        }

        .nav-badge {
            font-size: 11px;
            font-weight: 500;
            color: var(--mid);
            background: var(--border);
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.04em;
        }

        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 120px 24px 80px;
            position: relative;
            text-align: center;
        }

        /* decorative blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }
        .blob-1 {
            width: 500px; height: 500px;
            background: rgba(42, 110, 245, 0.08);
            top: -100px; right: -100px;
        }
        .blob-2 {
            width: 400px; height: 400px;
            background: rgba(15, 206, 143, 0.07);
            bottom: 0; left: -80px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            background: rgba(42, 110, 245, 0.08);
            border: 1px solid rgba(42, 110, 245, 0.18);
            padding: 6px 16px;
            border-radius: 20px;
            margin-bottom: 28px;
            animation: fadeUp 0.6s ease both;
        }

        .hero-eyebrow span {
            width: 6px; height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }

        .hero-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(42px, 7vw, 80px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.02em;
            color: var(--dark);
            max-width: 800px;
            animation: fadeUp 0.6s 0.1s ease both;
        }

        .hero-title em {
            font-style: normal;
            position: relative;
            color: var(--accent);
        }

        .hero-title em::after {
            content: '';
            position: absolute;
            bottom: 4px; left: 0; right: 0;
            height: 3px;
            background: var(--accent2);
            border-radius: 2px;
            transform: scaleX(0);
            transform-origin: left;
            animation: lineIn 0.5s 0.8s ease forwards;
        }

        @keyframes lineIn {
            to { transform: scaleX(1); }
        }

        .hero-sub {
            margin-top: 20px;
            font-size: 17px;
            color: var(--mid);
            font-weight: 300;
            max-width: 480px;
            line-height: 1.7;
            animation: fadeUp 0.6s 0.2s ease both;
        }

        .hero-arrow {
            margin-top: 48px;
            animation: fadeUp 0.6s 0.3s ease both, bounce 2s 1.5s ease-in-out infinite;
            color: var(--mid);
            font-size: 22px;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(8px); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── CHOOSE SECTION ── */
        .choose {
            padding: 80px 24px 100px;
            position: relative;
        }

        .section-label {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--mid);
            margin-bottom: 12px;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 700;
            text-align: center;
            color: var(--dark);
            margin-bottom: 56px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            max-width: 900px;
            margin: 0 auto;
        }

        /* ── CARD ── */
        .card {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 20px;
            padding: 36px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 20px;
        }

        .card.commercial::before {
            background: linear-gradient(135deg, rgba(42,110,245,0.04), rgba(42,110,245,0.01));
        }

        .card.institution::before {
            background: linear-gradient(135deg, rgba(15,206,143,0.06), rgba(15,206,143,0.01));
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 60px rgba(0,0,0,0.10);
        }

        .card:hover::before { opacity: 1; }

        .card.commercial:hover { border-color: rgba(42,110,245,0.35); }
        .card.institution:hover { border-color: rgba(15,206,143,0.45); }

        .card-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .card.commercial .card-icon {
            background: rgba(42,110,245,0.10);
        }

        .card.institution .card-icon {
            background: rgba(15,206,143,0.12);
        }

        .card-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 6px;
        }

        .card.commercial .card-tag {
            background: rgba(42,110,245,0.10);
            color: var(--accent);
        }

        .card.institution .card-tag {
            background: rgba(15,206,143,0.12);
            color: #0a9e6e;
        }

        .card-title {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.2;
        }

        .card-desc {
            font-size: 14px;
            color: var(--mid);
            line-height: 1.7;
            font-weight: 300;
        }

        .card-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 4px;
        }

        .card-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            color: var(--mid);
        }

        .feat-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .card.commercial .feat-dot { background: var(--accent); }
        .card.institution .feat-dot { background: var(--accent2); }

        .card-cta {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cta-text {
            font-size: 14px;
            font-weight: 600;
        }

        .card.commercial .cta-text { color: var(--accent); }
        .card.institution .cta-text { color: #0a9e6e; }

        .cta-arrow {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 16px;
            transition: transform 0.25s ease;
        }

        .card.commercial .cta-arrow {
            background: rgba(42,110,245,0.10);
            color: var(--accent);
        }

        .card.institution .cta-arrow {
            background: rgba(15,206,143,0.12);
            color: #0a9e6e;
        }

        .card:hover .cta-arrow {
            transform: translate(3px, -3px);
        }

        /* ── DIVIDER TAG ── */
        .or-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin: 0 auto;
            max-width: 900px;
        }

        .or-divider::before,
        .or-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .or-text {
            font-size: 12px;
            font-weight: 600;
            color: #999;
            letter-spacing: 0.08em;
        }

        /* ── FOOTER ── */
        footer {
            border-top: 1px solid var(--border);
            padding: 28px 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .footer-logo {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 15px;
            color: var(--dark);
        }

        .footer-copy {
            font-size: 12px;
            color: #aaa;
        }

        /* ── SCROLL REVEAL ── */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }

        @media (max-width: 600px) {
            nav { padding: 16px 20px; }
            .hero { padding: 100px 20px 60px; }
            .choose { padding: 60px 20px 80px; }
            footer { padding: 24px 20px; }
        }
    </style>
</head>
<body>

    <!-- NAV -->
    <nav>
        <a class="nav-logo" href="#">
            <div class="logo-icon">🔒</div>
            Smart Locker System
        </a>
        <span class="nav-badge">v2.0 · 2026</span>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="hero-eyebrow">
            <span></span> Secure · Smart · Simple
        </div>

        <h1 class="hero-title">
            One Platform,<br><em>Two Worlds</em>
        </h1>

        <p class="hero-sub">
            Choose the experience built for you — whether you're running a business or part of an institution.
        </p>

        <div class="hero-arrow">↓</div>
    </section>

    <!-- CHOOSE -->
    <section class="choose">
        <p class="section-label reveal">Select your platform</p>
        <h2 class="section-title reveal">Which best describes you?</h2>

        <div class="cards-grid">

            <!-- COMMERCIAL CARD -->
            <a href="commercial/login.php" class="card commercial reveal">
                <div>
                    <span class="card-tag">Commercial</span>
                    <div class="card-icon">🏢</div>
                </div>
                <div>
                    <h3 class="card-title">For Businesses &amp; Offices</h3>
                    <p class="card-desc">
                        Perfect for offices, gyms, hotels, and any organisation that needs a flexible, modern locker solution.
                    </p>
                </div>
                <ul class="card-features">
                    <li><span class="feat-dot"></span> Email & password login</li>
                    <li><span class="feat-dot"></span> QR Code locker access</li>
                    <li><span class="feat-dot"></span> Multi-user management</li>
                    <li><span class="feat-dot"></span> Activity logs & analytics</li>
                </ul>
                <div class="card-cta">
                    <span class="cta-text">Get Started →</span>
                    <div class="cta-arrow">↗</div>
                </div>
            </a>

            <!-- INSTITUTION CARD -->
            <a href="institution/login.php" class="card institution reveal reveal-delay-1">
                <div>
                    <span class="card-tag">Institution</span>
                    <div class="card-icon">🎓</div>
                </div>
                <div>
                    <h3 class="card-title">For Schools &amp; Colleges</h3>
                    <p class="card-desc">
                        Designed for universities, matriculation colleges, and schools — integrates with your student card system.
                    </p>
                </div>
                <ul class="card-features">
                    <li><span class="feat-dot"></span> Student card scan (QR / Barcode)</li>
                    <li><span class="feat-dot"></span> Manual Student ID login</li>
                    <li><span class="feat-dot"></span> Institutional admin panel</li>
                    <li><span class="feat-dot"></span> Bulk locker assignment</li>
                </ul>
                <div class="card-cta">
                    <span class="cta-text">Get Started →</span>
                    <div class="cta-arrow">↗</div>
                </div>
            </a>

        </div>

        <div class="or-divider reveal reveal-delay-2" style="margin-top: 40px;">
            <span class="or-text">Not sure? Contact your admin for guidance.</span>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <h1 class="footer-logo">🔒 Smart Locker System</h1>
        <span class="footer-copy">© 2026 Smart Locker System · Institutional Version v2.0</span>
    </footer>

    <script>
        // Scroll reveal
        const reveals = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    observer.unobserve(e.target);
                }
            });
        }, { threshold: 0.15 });

        reveals.forEach(el => observer.observe(el));
    </script>
</body>
</html>