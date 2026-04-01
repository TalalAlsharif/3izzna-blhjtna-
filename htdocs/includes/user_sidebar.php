<!-- User Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-pattern"></div>
        <div class="sidebar-logo">
            <div class="sidebar-logo-img">
                <img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا">
            </div>
            <h1>عِزّنا بلهجتنا</h1>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </span>
            <span>لوحة التحكم</span>
        </a>
        
        <a href="projects.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['projects.php', 'solve.php', 'review.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
            </span>
            <span>المشاريع</span>
        </a>
        
        <a href="statistics.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'statistics.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 9.2h3V19H5V9.2zM10.6 5h2.8v14h-2.8V5zm5.6 8H19v6h-2.8v-6z"/></svg>
            </span>
            <span>الإحصائيات</span>
        </a>
        
        <a href="../logout.php" class="nav-item" style="margin-top: auto;">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </span>
            <span>تسجيل الخروج</span>
        </a>
    </nav>
</aside>
