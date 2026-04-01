<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عزنا بلهجتنا - منصة توثيق اللهجات السعودية</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #1a5c4b;
            --dark-green: #0d3d32;
            --deep-green: #0a2e26;
            --accent-magenta: #9c2d5a;
            --light-magenta: #b33d6a;
            --cream-bg: #f8f5f0;
            --cream-light: #fcfaf7;
            --text-dark: #1a1a1a;
            --text-gray: #5a5a5a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--cream-bg);
            color: var(--text-dark);
            line-height: 1.7;
            direction: rtl;
        }

        /* ===== Header ===== */
        header {
            background: var(--cream-light);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.scrolled {
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        nav {
            max-width: 1300px;
            margin: 0 auto;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            overflow: hidden;
        }

        .nav-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 50px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            font-size: 16px;
            transition: color 0.3s;
            position: relative;
            padding-bottom: 5px;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0;
            height: 2px;
            background: var(--accent-magenta);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--accent-magenta);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-cta::after {
            display: none !important;
        }

        .nav-cta {
            background: var(--accent-magenta) !important;
            color: white !important;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
        }

        .nav-cta:hover {
            background: var(--light-magenta) !important;
        }

        /* ===== Pattern Strip - ثابت مع تكرار الصورة ===== */
        .pattern-strip {
            height: 55px;
            background-color: var(--deep-green);
            background-image: url('assets/images/pattern.png');
            background-repeat: repeat-x;
            background-size: auto 100%;
            background-position: center;
        }

        .pattern-strip.first {
            margin-top: 86px;
        }

        /* ===== Hero Section ===== */
        .hero {
            background: var(--cream-bg);
            padding: 80px 40px 100px;
        }

        .hero-container {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 80px;
            align-items: center;
        }

        /* اللوقو على اليسار */
        .hero-logo-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-logo-image {
            width: 400px;
            height: 400px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            transition: all 0.4s ease;
        }

        .hero-logo-image:hover {
            transform: scale(1.05);
            box-shadow: 0 30px 80px rgba(0,0,0,0.25);
        }

        .hero-logo-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* النص على اليمين */
        .hero-text {
            text-align: right;
        }

        .hero-text h1 {
            font-family: 'Amiri', serif;
            font-size: 58px;
            color: var(--accent-magenta);
            line-height: 1.3;
            margin-bottom: 28px;
            font-weight: 700;
        }

        .hero-text p {
            font-size: 19px;
            color: var(--text-gray);
            line-height: 2;
            margin-bottom: 40px;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
        }

        .btn-primary {
            background: var(--accent-magenta);
            color: white;
            padding: 16px 42px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--light-magenta);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(156, 45, 90, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--dark-green);
            padding: 16px 42px;
            border: 2px solid var(--dark-green);
            border-radius: 12px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-outline:hover {
            background: var(--dark-green);
            color: white;
            transform: translateY(-2px);
        }

        /* ===== Stats Section ===== */
        .stats {
            padding: 70px 40px;
            background: var(--deep-green);
        }

        .stats-container {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }

        .stat-item {
            text-align: center;
            color: white;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .stat-item.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .stat-item:nth-child(1) { transition-delay: 0.1s; }
        .stat-item:nth-child(2) { transition-delay: 0.2s; }
        .stat-item:nth-child(3) { transition-delay: 0.3s; }
        .stat-item:nth-child(4) { transition-delay: 0.4s; }

        .stat-number {
            font-family: 'Amiri', serif;
            font-size: 52px;
            font-weight: 700;
            color: var(--accent-magenta);
            line-height: 1;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }

        /* ===== Services Section ===== */
        .services {
            padding: 100px 40px;
            background: white;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-subtitle {
            color: var(--accent-magenta);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 12px;
        }

        .section-title {
            font-family: 'Amiri', serif;
            font-size: 42px;
            color: var(--text-dark);
        }

        .services-grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 35px;
        }

        .service-card {
            background: var(--cream-bg);
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.4s ease;
            border: 2px solid transparent;
            opacity: 0;
            transform: translateY(40px);
        }

        .service-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .service-card:nth-child(1) { transition-delay: 0.1s; }
        .service-card:nth-child(2) { transition-delay: 0.2s; }
        .service-card:nth-child(3) { transition-delay: 0.3s; }

        .service-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary-green);
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
        }

        .service-card.visible:hover {
            transform: translateY(-8px);
        }

        .service-icon {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .service-icon svg {
            width: 38px;
            height: 38px;
        }

        .service-card h3 {
            font-size: 22px;
            color: var(--text-dark);
            margin-bottom: 14px;
            font-weight: 700;
        }

        .service-card p {
            font-size: 15px;
            color: var(--text-gray);
            line-height: 1.8;
        }

        /* ===== CTA Section ===== */
        .cta {
            padding: 100px 40px;
            background: var(--cream-bg);
            text-align: center;
        }

        .cta-box {
            max-width: 750px;
            margin: 0 auto;
            background: white;
            padding: 65px 50px;
            border-radius: 28px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .cta-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-green), var(--accent-magenta), var(--primary-green));
        }

        .cta h2 {
            font-family: 'Amiri', serif;
            font-size: 38px;
            color: var(--text-dark);
            margin-bottom: 18px;
        }

        .cta p {
            font-size: 18px;
            color: var(--text-gray);
            margin-bottom: 35px;
        }

        /* ===== Footer ===== */
        footer {
            background: var(--text-dark);
            color: white;
            padding: 60px 40px 35px;
        }

        .footer-content {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            padding-bottom: 45px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .footer-brand h3 {
            font-family: 'Amiri', serif;
            font-size: 26px;
            color: var(--accent-magenta);
            margin-bottom: 18px;
        }

        .footer-brand p {
            font-size: 14px;
            opacity: 0.7;
            line-height: 1.9;
        }

        .footer-links h4 {
            font-size: 16px;
            margin-bottom: 22px;
            color: white;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 14px;
        }

        .footer-links a {
            color: white;
            opacity: 0.7;
            text-decoration: none;
            font-size: 14px;
            transition: opacity 0.3s;
        }

        .footer-links a:hover {
            opacity: 1;
        }

        .footer-bottom {
            max-width: 1100px;
            margin: 0 auto;
            padding-top: 30px;
            text-align: center;
        }

        .footer-bottom p {
            font-size: 14px;
            opacity: 0.5;
        }

        /* ===== Mobile Menu ===== */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
        }

        .mobile-menu-btn span {
            display: block;
            width: 28px;
            height: 3px;
            background: var(--text-dark);
            margin: 6px 0;
            border-radius: 2px;
        }

        /* ===== Responsive ===== */
        @media (max-width: 1100px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 50px;
            }

            .hero-text {
                text-align: center;
                order: 1;
            }

            .hero-logo-wrapper {
                order: 2;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-logo-image {
                width: 340px;
                height: 340px;
            }

            .hero-text h1 {
                font-size: 46px;
            }

            .services-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }

            .footer-content {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 25px;
                gap: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                z-index: 100;
            }

            .nav-links.active {
                display: flex;
            }

            nav {
                padding: 15px 20px;
                position: relative;
            }

            .hero {
                padding: 50px 20px 70px;
            }

            .hero-text h1 {
                font-size: 36px;
            }

            .hero-text p {
                font-size: 16px;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-primary,
            .btn-outline {
                width: 100%;
                max-width: 280px;
                text-align: center;
            }

            .hero-logo-image {
                width: 280px;
                height: 280px;
            }

            .pattern-strip {
                height: 45px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .stats-container {
                grid-template-columns: 1fr 1fr;
            }

            .stat-number {
                font-size: 40px;
            }

            .section-title {
                font-size: 32px;
            }

            .cta-box {
                padding: 45px 25px;
            }

            .cta h2 {
                font-size: 28px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .services, .cta {
                padding: 70px 20px;
            }

            .stats {
                padding: 50px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header>
        <nav>
            <div class="nav-logo">
                <img src="assets/images/hero-logo.png" alt="عزنا بلهجتنا">
            </div>
            
            <ul class="nav-links">
                <li><a href="#">الرئيسية</a></li>
                <li><a href="#services">خدماتنا</a></li>
                <li><a href="#contact">تواصل معنا</a></li>
                <li><a href="public/login.php" class="nav-cta">تسجيل الدخول</a></li>
            </ul>
            
            <button class="mobile-menu-btn" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </nav>
    </header>

    <!-- Pattern Strip - ثابت مع تكرار الصورة -->
    <div class="pattern-strip first"></div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <!-- اللوقو الدائري - على اليسار -->
            <div class="hero-logo-wrapper">
                <div class="hero-logo-image">
                    <img src="assets/images/hero-logo.png" alt="عزنا بلهجتنا">
                </div>
            </div>

            <!-- النص - على اليمين -->
            <div class="hero-text">
                <h1>عِزّنا بلهجتنا</h1>
                <p>
                  مرحباً بكم في منصة عِزنّا بلهجتنا لتعلم مختلف اللهجات السعودية عن طريقة أسئله ثقافيه متنوعة 
سجل معنا وخوض تجربه ثقافيه فريده وممتعه.
                </p>
                <div class="hero-buttons">
                    <a href="public/register.php" class="btn-primary">انضم إلينا</a>
                    <a href="#services" class="btn-outline">اعرف المزيد</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pattern Strip -->
    <div class="pattern-strip"></div>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-container">
            <div class="stat-item">
                <div class="stat-number">١٨K</div>
                <div class="stat-label">أسئلة ثقافيها</div>
            </div>
            <div class="stat-item">  
                <div class="stat-number">٦</div>
                <div class="stat-label">لهجات سعودية</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">٤</div>
                <div class="stat-label">انواع اسئلة</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">٩٨٪</div>
                <div class="stat-label">دقة التصنيف</div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="section-header">
            <p class="section-subtitle">خدماتنا</p>
            <h2 class="section-title">ماذا نقدم؟</h2>
        </div>

        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="white">
                        <path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                    </svg>
                </div>
                <h3>أسئلة ثقافيه</h3>
                <p>نقدم أسئله ثقافيه عن اللهجات السعودية ونعزز من هويتنا و تاريخنا</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="white">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                    </svg>
                </div>
                <h3>جمع للبيانات</h3>
                <p>نوفر رسوم بيانيه تساعد في الدراسات والمهام البحثية بناء على إجابات المستخدم</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="white">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <h3>تجربة المستخدم</h3>
                <p>نهدف إلى تقديم خدمات سلسة وسهلة للمستخدمين للتعرف على ثقافتنا الجميلة</p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="contact">
        <div class="cta-box">
            <h2>كن جزءاً من رحلة الحفاظ على تراثنا</h2>
            <p>انضم إلى مجتمعنا وساهم في توثيق اللهجات السعودية للأجيال القادمة</p>
            <a href="public/register.php" class="btn-primary">سجّل الآن مجاناً</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <h3>عِزّنا بلهجتنا</h3>
                <p>منصة وطنية لتوثيق وحفظ اللهجات السعودية، نعمل على بناء أكبر قاعدة بيانات لغوية عربية.</p>
            </div>

            <div class="footer-links">
                <h4>روابط سريعة</h4>
                <ul>
                    <li><a href="#">الرئيسية</a></li>
                    <li><a href="#services">خدماتنا</a></li>
                    <li><a href="#contact">تواصل معنا</a></li>
                </ul>
            </div>

            <div class="footer-links">
                <h4>الحساب</h4>
                <ul>
                    <li><a href="public/login.php">تسجيل الدخول</a></li>
                    <li><a href="public/register.php">إنشاء حساب</a></li>
                </ul>
            </div>

            <div class="footer-links">
                <h4>تواصل معنا</h4>
                <ul>
                    <li><a href="/cdn-cgi/l/email-protection#88e1e6eee7c8edf0e9e5f8e4eda6ebe7e5">البريد الإلكتروني</a></li>
                    <li><a href="#">الدعم الفني</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2024 عِزّنا بلهجتنا. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script><script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Scroll animations for stats and services
        const observerOptions = {
            threshold: 0.2,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Counter animation for numbers
                    if (entry.target.classList.contains('stat-item')) {
                        const numberEl = entry.target.querySelector('.stat-number');
                        if (numberEl && !numberEl.dataset.animated) {
                            animateNumber(numberEl);
                            numberEl.dataset.animated = 'true';
                        }
                    }
                }
            });
        }, observerOptions);

        // Observe stat items
        document.querySelectorAll('.stat-item').forEach(item => {
            observer.observe(item);
        });

        // Observe service cards
        document.querySelectorAll('.service-card').forEach(card => {
            observer.observe(card);
        });

        // Number counter animation
        function animateNumber(element) {
            const text = element.textContent;
            const hasPlus = text.includes('+');
            const hasPercent = text.includes('٪') || text.includes('%');
            const hasK = text.includes('K');
            
            // Extract the number (Arabic or English)
            let finalNumber = parseInt(text.replace(/[^\d٠-٩]/g, '').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
            
            if (isNaN(finalNumber)) return;
            
            let current = 0;
            const increment = finalNumber / 40;
            const duration = 1500;
            const stepTime = duration / 40;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalNumber) {
                    current = finalNumber;
                    clearInterval(timer);
                }
                
                // Convert to Arabic numerals
                let displayNum = Math.floor(current).toString().replace(/\d/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
                
                
                let suffix = '';
                if (hasK) suffix = 'K';
                if (hasPlus) suffix += '+';
                if (hasPercent) suffix += '٪';
                
                element.textContent = displayNum + suffix;
            }, stepTime);
        }
    </script>
</body>
</html>
