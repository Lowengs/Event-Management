<?php
session_start();

// Check existing active sessions for fast redirect
$osaSession   = !empty($_SESSION['osa_id']);
$orgSession   = !empty($_SESSION['org_id']);
$adminSession = !empty($_SESSION['admin_id']);

$osaUrl   = $osaSession ? 'dashboard_final.php' : 'login.php?role=osa';
$orgUrl   = $orgSession ? '../organization/dashboard_org.php' : 'login.php?role=org';
$adminUrl = $adminSession ? '../admin/dashboard.php' : '../admin/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhilSCA – Administrative &amp; Organization Portal</title>
    <meta name="description" content="Philippine State College of Aeronautics - Gateway for Office of Student Affairs (OSA), Student Organizations, and System Administration.">
    <link rel="icon" href="../../assets/img/philsca.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #0f172a;
            --accent-blue: #2563eb;
            --accent-gold: #d97706;
            --bg-light: #f8fafc;
            --card-border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* ── Header Banner ───────────────────────────────── */
        .philsca-header {
            background: #ffffff;
            border-bottom: 2px solid #cbd5e1;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 10;
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1000px;
            width: 100%;
            gap: 20px;
        }

        .header-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }

        .header-logo:hover {
            transform: scale(1.05);
        }

        .header-text {
            text-align: center;
            flex: 1;
        }

        .header-text h3 {
            font-family: 'Cinzel', serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #334155;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .header-text h1 {
            font-family: 'Outfit', 'Inter', sans-serif;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #0f172a;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .header-text p {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: #475569;
            text-transform: uppercase;
        }

        /* ── Main Gateway Container ──────────────────────── */
        .gateway-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
        }

        .gateway-title-area {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeInDown 0.6s ease-out;
        }

        .gateway-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: #dbeafe;
            color: #1e40af;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }

        .gateway-title-area h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .gateway-title-area p {
            font-size: 15px;
            color: var(--text-muted);
            max-width: 540px;
            margin: 0 auto;
        }

        /* ── Gateway Grid Cards ──────────────────────────── */
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            max-width: 980px;
            width: 100%;
            animation: fadeInUp 0.7s ease-out;
        }

        .portal-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1.5px solid var(--card-border);
            padding: 40px 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: transparent;
            transition: all 0.3s ease;
        }

        .portal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 45px -10px rgba(15, 23, 42, 0.14), 0 16px 20px -8px rgba(15, 23, 42, 0.08);
            border-color: #93c5fd;
        }

        /* OSA Card Styling */
        .portal-card.card-osa:hover::before {
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        }
        .portal-card.card-osa .icon-wrap {
            background: #eff6ff;
            color: #2563eb;
            border: 2px solid #dbeafe;
        }
        .portal-card.card-osa:hover .icon-wrap {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
        }

        /* Organization Card Styling */
        .portal-card.card-org:hover::before {
            background: linear-gradient(90deg, #10b981, #047857);
        }
        .portal-card.card-org .icon-wrap {
            background: #ecfdf5;
            color: #059669;
            border: 2px solid #a7f3d0;
        }
        .portal-card.card-org:hover .icon-wrap {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
            box-shadow: 0 10px 25px rgba(5, 150, 105, 0.35);
        }

        /* Admin Card Styling */
        .portal-card.card-admin:hover::before {
            background: linear-gradient(90deg, #8b5cf6, #6d28d9);
        }
        .portal-card.card-admin .icon-wrap {
            background: #f5f3ff;
            color: #7c3aed;
            border: 2px solid #ddd6fe;
        }
        .portal-card.card-admin:hover .icon-wrap {
            background: #7c3aed;
            color: #ffffff;
            border-color: #7c3aed;
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.35);
        }

        .icon-wrap {
            width: 88px;
            height: 88px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            margin-bottom: 24px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .portal-name {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .portal-role {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 20px;
            line-height: 1.45;
        }

        .portal-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s ease;
            width: 100%;
            justify-content: center;
        }

        .card-osa .portal-btn {
            background: #f1f5f9;
            color: #1e3a8a;
            border: 1.5px solid #cbd5e1;
        }
        .card-osa:hover .portal-btn {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        .card-org .portal-btn {
            background: #f1f5f9;
            color: #065f46;
            border: 1.5px solid #cbd5e1;
        }
        .card-org:hover .portal-btn {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
        }

        .card-admin .portal-btn {
            background: #f1f5f9;
            color: #5b21b6;
            border: 1.5px solid #cbd5e1;
        }
        .card-admin:hover .portal-btn {
            background: #7c3aed;
            color: #ffffff;
            border-color: #7c3aed;
        }

        /* ── Back to Student Link ────────────────────────── */
        .student-portal-link {
            margin-top: 36px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 9999px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }

        .student-portal-link:hover {
            color: #2563eb;
            border-color: #93c5fd;
            box-shadow: 0 4px 12px rgba(37,99,235,0.12);
            transform: translateY(-1px);
        }

        /* ── Footer ──────────────────────────────────────── */
        .portal-footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 860px) {
            .portal-grid {
                grid-template-columns: 1fr;
                max-width: 420px;
                gap: 20px;
            }
            .header-inner {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }
            .header-logo {
                width: 56px;
                height: 56px;
            }
            .header-text h1 {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

    <!-- ── Institutional Header Banner ── -->
    <header class="philsca-header">
        <div class="header-inner">
            <img src="../../assets/img/philsca.png" alt="PhilSCA Coat of Arms" class="header-logo">
            <div class="header-text">
                <h3>Republic of the Philippines</h3>
                <h1>Philippine State College of Aeronautics</h1>
                <p>Piccio Garden, Villamor Pasay City</p>
            </div>
            <img src="../../assets/img/naap logo.png" alt="NAAP / College Logo" class="header-logo">
        </div>
    </header>

    <!-- ── Gateway Main Selection ── -->
    <main class="gateway-main">
        <div class="gateway-title-area">
            <div class="gateway-badge">
                <ion-icon name="shield-checkmark-outline"></ion-icon>
                <span>Administrative &amp; Leadership Portal</span>
            </div>
            <h2>Select Your Portal</h2>
            <p>Please choose your designated administrative role to sign in or access your dashboard.</p>
        </div>

        <div class="portal-grid">
            <!-- 1. OSA Portal -->
            <a href="<?= htmlspecialchars($osaUrl) ?>" class="portal-card card-osa" id="cardOsa">
                <div class="icon-wrap">
                    <ion-icon name="people"></ion-icon>
                </div>
                <h3 class="portal-name">OSA</h3>
                <p class="portal-role">Office of Student Affairs<br>Management &amp; Oversight</p>
                <div class="portal-btn">
                    <span>Access OSA Portal</span>
                    <ion-icon name="arrow-forward-outline"></ion-icon>
                </div>
            </a>

            <!-- 2. Organization Portal -->
            <a href="<?= htmlspecialchars($orgUrl) ?>" class="portal-card card-org" id="cardOrg">
                <div class="icon-wrap">
                    <ion-icon name="business"></ion-icon>
                </div>
                <h3 class="portal-name">Organization</h3>
                <p class="portal-role">Student Organization Officers<br>&amp; Event Managers</p>
                <div class="portal-btn">
                    <span>Access Organization</span>
                    <ion-icon name="arrow-forward-outline"></ion-icon>
                </div>
            </a>

            <!-- 3. Admin Portal -->
            <a href="<?= htmlspecialchars($adminUrl) ?>" class="portal-card card-admin" id="cardAdmin">
                <div class="icon-wrap">
                    <ion-icon name="settings"></ion-icon>
                </div>
                <h3 class="portal-name">Admin</h3>
                <p class="portal-role">System Administrator<br>&amp; IT Security Control</p>
                <div class="portal-btn">
                    <span>Access Admin Portal</span>
                    <ion-icon name="arrow-forward-outline"></ion-icon>
                </div>
            </a>
        </div>

        <!-- Student Portal Link -->
        <a href="../index.php" class="student-portal-link">
            <ion-icon name="school-outline"></ion-icon>
            <span>Looking for the Student Portal? Click here</span>
            <ion-icon name="chevron-forward-outline" style="font-size:12px;"></ion-icon>
        </a>
    </main>

    <!-- ── Footer ── -->
    <footer class="portal-footer">
        <p>&copy; <?= date('Y') ?> Philippine State College of Aeronautics (PhilSCA). All rights reserved.</p>
    </footer>

</body>
</html>
