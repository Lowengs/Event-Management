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
    <title>NAAP Student Organization</title>
    <link rel="icon" href="../../assets/img/philsca.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400&family=Kanit:ital,wght@0,400;0,600;0,700;0,800;1,400&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <style>
        :root {
            --primary-color: #1e3a8a;
            --primary-dark: #0f2b72;
            --primary-mid: #1e40af;
            --accent-color: #077daf;
            --accent-blue: #2563eb;
            --surface-white: #ffffff;
            --bg-light: #ffffff;
            --border-light: #e2e8f0;
            --border-hover: #1e40af;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --font: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            --radius: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font);
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Institutional Header matching index.php Navigation Palette ── */
        header {
            background: linear-gradient(180deg, #020b2f 0%, #061844 100%);
            border-bottom: 1px solid rgba(96, 165, 250, 0.16);
            padding: 16px 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 50%;
            max-width: 960px;
            width: 100%;
            gap: 20px;
        }

        .header-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            user-select: none;
        }

        .header-text {
            text-align: center;
            flex: 1;
        }

        .header-text p {
            font-family: var(--font);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: #94a3b8;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .header-text p span {
            display: inline-block;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.03em;
            margin: 3px 0;
            color: #ffffff;
        }

        .header-text p .location {
            font-size: 11.5px;
            font-weight: 500;
            letter-spacing: 0.06em;
            color: #93c5fd;
        }

        /* ── Main Portals Section ───────────────────────── */
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px 80px;
            background-color: var(--bg-light);
        }

        .section-header {
            text-align: center;
            margin-bottom: 44px;
        }

        .section-badge {
            display: inline-block;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--primary-mid);
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 5px 16px;
            border-radius: 999px;
            margin-bottom: 12px;
        }

        .section-title {
            font-family: var(--font);
            font-size: clamp(24px, 3.5vw, 32px);
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.02em;
        }

        .usercontainer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(40px, 8vw, 100px);
            max-width: 1050px;
            width: 100%;
            flex-wrap: wrap;
        }

        .portal-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            padding: 20px 24px;
            background: transparent;
            border: none;
            transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1);
            user-select: none;
            box-shadow: none !important;
            filter: none !important;
        }

        .portal-item:hover {
            transform: translateY(-8px);
        }

        .portal-item:active {
            transform: translateY(-2px);
        }

        .portal-icon {
            width: 96px;
            height: 96px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            color: var(--primary-color);
            transition: color 0.25s ease, transform 0.25s ease;
            box-shadow: none !important;
            filter: none !important;
            background: transparent;
            border: none;
        }

        .portal-icon ion-icon {
            font-size: 88px;
            width: 88px;
            height: 88px;
        }

        .portal-item:hover .portal-icon {
            color: var(--primary-mid);
            transform: scale(1.06);
        }

        .portal-label {
            font-family: var(--font);
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            text-align: center;
            letter-spacing: -0.01em;
            transition: color 0.25s ease;
        }

        .portal-item:hover .portal-label {
            color: var(--primary-mid);
        }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 768px) {
            header {
                padding: 14px 16px;
            }

            .header-inner {
                gap: 12px;
            }

            .header-logo {
                width: 64px;
                height: 64px;
            }

            .header-text p {
                font-size: 11px;
            }

            .header-text p span {
                font-size: 14px;
            }

            .header-text p .location {
                font-size: 10px;
            }

            .usercontainer {
                gap: 36px;
            }

            .portal-icon {
                width: 76px;
                height: 76px;
            }

            .portal-icon ion-icon {
                font-size: 72px;
                width: 72px;
                height: 72px;
            }

            .portal-label {
                font-size: 18px;
            }
        }

        /* Floating Student Portal Bottom Button */
        .student-portal-btn {
            position: fixed;
            bottom: 24px;
            right: 28px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            background-color: var(--primary-color);
            color: #ffffff;
            font-family: var(--font);
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 999px;
            border: 1px solid var(--primary-color);
            box-shadow: none !important;
            filter: none !important;
            transition: background-color 0.2s ease, transform 0.2s ease;
            z-index: 100;
        }

        .student-portal-btn:hover {
            background-color: var(--primary-mid);
            border-color: var(--primary-mid);
            transform: translateY(-2px);
        }

        .student-portal-btn ion-icon {
            font-size: 18px;
        }

        @media (max-width: 600px) {
            .student-portal-btn {
                bottom: 16px;
                right: 16px;
                padding: 9px 15px;
                font-size: 12.5px;
            }
        }
    </style>
<script src="../../assets/js/security.js"></script>
</head>
<body>

    <!-- Institutional Header with matching index.php Navigation Palette -->
    <header>
        <div class="header-inner">
            <img src="../../assets/img/naap logo.png" alt="NAAP Logo" class="header-logo" onerror="this.src='../../assets/img/philsca.png'">
            <div class="header-text">
                <p>
                    REPUBLIC OF THE PHILIPPINES <br>
                    <span>National Aviation Academy of the Philippines</span> <br>
                    <span class="location">PICCIO GARDEN, VILLAMOR PASAY CITY</span>
                </p>
            </div>
            
        </div>
    </header>

    <!-- Main Portals Area -->
    <main>
        <div class="section-header">
            <span class="section-badge">Institutional Portals</span>
            <h1 class="section-title">Select a Portal</h1>
        </div>

        <div class="usercontainer">
            <!-- 1. OSA -->
            <a href="<?= htmlspecialchars($osaUrl) ?>" class="portal-item" id="linkOsa">
                <div class="portal-icon">
                    <ion-icon name="people"></ion-icon>
                </div>
                <h2 class="portal-label">OSA</h2>
            </a>

            <!-- 2. Organization -->
            <a href="<?= htmlspecialchars($orgUrl) ?>" class="portal-item" id="linkOrg">
                <div class="portal-icon">
                    <ion-icon name="book"></ion-icon>
                </div>
                <h2 class="portal-label">Organization</h2>
            </a>

            <!-- 3. Admin -->
            <a href="<?= htmlspecialchars($adminUrl) ?>" class="portal-item" id="linkAdmin">
                <div class="portal-icon">
                    <ion-icon name="settings"></ion-icon>
                </div>
                <h2 class="portal-label">Admin</h2>
            </a>
        </div>
    </main>

    <!-- Floating Bottom-Right Button to Student Portal -->
    <a href="../index.php" class="student-portal-btn" id="studentPortalBtn" title="Go to Student Portal">
        <ion-icon name="school-outline"></ion-icon>
        <span>Student Portal</span>
        <ion-icon name="arrow-forward-outline" style="font-size:14px;"></ion-icon>
    </a>

</body>
</html>
