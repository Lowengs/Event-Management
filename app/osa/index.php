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
    <title>PhilSCA</title>
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
            --bg-body: #f4f8ff;
            --navbar-sidebar-bg: #1e293b;
            --primary-blue: #1e40af;
            --accent-blue: #2563eb;
            --text-dark: #0f172a;
            --text-white: #ffffff;
            --text-sub: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header / Navbar matching Sidebar Color ────────── */
        header {
            background-color: var(--navbar-sidebar-bg);
            padding: 16px 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 960px;
            width: 100%;
            gap: 20px;
        }

        .header-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            user-select: none;
        }

        .header-text {
            text-align: center;
            flex: 1;
        }

        .header-text p {
            font-family: 'Roboto', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--text-sub);
            line-height: 1.35;
            text-transform: uppercase;
        }

        .header-text p span {
            display: inline-block;
            font-size: 19px;
            font-weight: 900;
            letter-spacing: 0.03em;
            margin: 2px 0;
            color: var(--text-white);
        }

        .header-text p .location {
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: 0.06em;
            color: #cbd5e1;
        }

        /* ── Main Area ─────────────────────────────────────── */
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background-color: var(--bg-body);
        }

        .usercontainer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(40px, 8vw, 120px);
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
            color: var(--text-dark);
            cursor: pointer;
            padding: 20px 24px;
            transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1), color 0.25s cubic-bezier(0.2, 0, 0, 1);
            user-select: none;
            box-shadow: none !important;
            filter: none !important;
        }

        .portal-icon {
            font-size: 96px;
            width: 104px;
            height: 104px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--text-dark);
            transition: color 0.25s ease, transform 0.25s ease;
            box-shadow: none !important;
            filter: none !important;
        }

        .portal-icon ion-icon,
        .portal-icon svg {
            font-size: 96px;
            width: 96px;
            height: 96px;
        }

        .portal-label {
            font-family: 'Roboto', 'Inter', sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            text-align: center;
            letter-spacing: 0.02em;
            transition: color 0.25s ease;
        }

        /* Hover animation: Blue palette color & smooth lift, strictly NO shadows / glow */
        .portal-item:hover {
            transform: translateY(-8px);
        }

        .portal-item:hover .portal-icon {
            color: var(--primary-blue);
        }

        .portal-item:hover .portal-label {
            color: var(--primary-blue);
        }

        .portal-item:active {
            transform: translateY(-2px);
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
                width: 52px;
                height: 52px;
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
                gap: 40px;
            }

            .portal-icon {
                font-size: 76px;
                width: 80px;
                height: 80px;
            }

            .portal-icon ion-icon,
            .portal-icon svg {
                font-size: 76px;
                width: 76px;
                height: 76px;
            }

            .portal-label {
                font-size: 17px;
            }
        }

        /* Student Portal Bottom Button */
        .student-portal-btn {
            position: fixed;
            bottom: 24px;
            right: 28px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background-color: var(--navbar-sidebar-bg);
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: none;
            transition: background-color 0.2s ease, transform 0.2s ease;
            z-index: 100;
        }

        .student-portal-btn:hover {
            background-color: var(--primary-blue);
            transform: translateY(-2px);
        }

        .student-portal-btn ion-icon {
            font-size: 18px;
        }

        @media (max-width: 600px) {
            .student-portal-btn {
                bottom: 16px;
                right: 16px;
                padding: 8px 14px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

    <!-- Institutional Header with Sidebar Color & Swapped Logos -->
    <header>
        <div class="header-inner">
            <img src="../../assets/img/naap logo.png" alt="NAAP Logo" class="header-logo" onerror="this.src='../../assets/img/philsca.png'">
            <div class="header-text">
                <p>
                    REPUBLIC OF THE PHILIPPINES <br>
                    <span>PHILIPPINE STATE COLLEGE OF AERONAUTICS</span> <br>
                    <span class="location">PICCIO GARDEN, VILLAMOR PASAY CITY</span>
                </p>
            </div>
            <img src="../../assets/img/philsca.png" alt="PhilSCA Logo" class="header-logo">
        </div>
    </header>

    <!-- Main Portals Area -->
    <main>
        <div class="usercontainer">
            <!-- 1. OSA -->
            <a href="<?= htmlspecialchars($osaUrl) ?>" class="portal-item" id="linkOsa">
                <div class="portal-icon">
                    <ion-icon name="people"></ion-icon>
                </div>
                <p class="portal-label">OSA</p>
            </a>

            <!-- 2. Organization -->
            <a href="<?= htmlspecialchars($orgUrl) ?>" class="portal-item" id="linkOrg">
                <div class="portal-icon">
                    <ion-icon name="book"></ion-icon>
                </div>
                <p class="portal-label">Organization</p>
            </a>

            <!-- 3. Admin -->
            <a href="<?= htmlspecialchars($adminUrl) ?>" class="portal-item" id="linkAdmin">
                <div class="portal-icon">
                    <ion-icon name="settings"></ion-icon>
                </div>
                <p class="portal-label">Admin</p>
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
