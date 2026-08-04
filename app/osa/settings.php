<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

$osa_name  = htmlspecialchars($_SESSION['osa_name']  ?? 'Administrator');
$osa_email = htmlspecialchars($_SESSION['osa_email'] ?? '');

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_start();
    $_GET['action'] = 'update_osa_settings';
    require __DIR__ . '/../../config/API/endpoints/index.php';
    $apiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
    header('Content-Type: text/html; charset=UTF-8');

    if ($apiRes['success'] ?? false) {
        $success_msg = $apiRes['message'] ?? 'Settings updated successfully.';
        $osa_name  = htmlspecialchars($_SESSION['osa_name']  ?? 'Administrator');
        $osa_email = htmlspecialchars($_SESSION['osa_email'] ?? '');
    } else {
        $error_msg = $apiRes['message'] ?? 'Failed to update settings.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Settings</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/settings.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/osa/settings.css?v=<?= time() ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" href="../../assets/img/philsca.png" />
</head>

<body>
  <header>
    <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
      <ion-icon name="menu-outline"></ion-icon>
    </button>
    <h1>NAAP OSA PORTAL</h1>
  </header>

  <main>
    <nav class="navigation" id="sidebar">
      <ul>
        <li>
          <div class="span">
            <div class="logo-border">
              <img src="../../assets/img/philsca.png" alt="NAAP Logo">
            </div>
            <div class="text">
              <h1>NAAP</h1>
              <p>OSA Portal</p>
            </div>
          </div>
        </li>
        <li><a href="dashboard_final.php" class="nav"><ion-icon name="grid-outline"></ion-icon><span>Dashboard</span></a></li>
        <li><a href="organization.php" class="nav"><ion-icon name="business-outline"></ion-icon><span>Organization</span></a></li>
        <li><a href="calendar.php" class="nav"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
        <li><a href="events.php" class="nav"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
        <li><a href="students.php" class="nav"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
        <li><a href="announcement.php" class="nav"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
        <li><a href="reports.php" class="nav"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
        <li><a href="audit-trail.php" class="nav"><ion-icon name="analytics-outline"></ion-icon><span>Audit Trail</span></a></li>
        <li><a href="messages.php" class="nav"><ion-icon name="chatbox-outline"></ion-icon><span>Messages</span></a></li>
        <li><a href="settings.php" class="nav active"><ion-icon name="cog-outline"></ion-icon><span>Settings</span></a></li>
        <li><a href="../../config/API/endpoints/index.php?action=osa_logout" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
      </ul>
    </nav>

    <div class="maincontent">
      <div class="pagebar">
        <a class="back-btn" href="dashboard_final.php" aria-label="Back to dashboard">
          <ion-icon name="arrow-back-outline"></ion-icon>
        </a>
        <div class="pagebar-text">
          <h2>Settings</h2>
          <p>Manage your OSA account preferences and security</p>
        </div>
      </div>

      <div class="divider"></div>

      <?php if ($success_msg): ?>
        <div class="alert alert-success"><ion-icon name="checkmark-circle-outline"></ion-icon> <?= htmlspecialchars($success_msg) ?></div>
      <?php endif; ?>
      <?php if ($error_msg): ?>
        <div class="alert alert-error"><ion-icon name="alert-circle-outline"></ion-icon> <?= htmlspecialchars($error_msg) ?></div>
      <?php endif; ?>

      <div class="settings-grid">
        <div class="settings-card">
          <div class="settings-card-header">
            <ion-icon name="person-circle-outline"></ion-icon>
            <h3>Account Overview</h3>
          </div>
          <div class="settings-card-body">
            <div class="profile-avatar-row">
              <div class="avatar-circle"><?= strtoupper(substr($osa_name, 0, 1)) ?></div>
              <div class="avatar-info">
                <h4><?= $osa_name ?></h4>
                <p><?= $osa_email ?></p>
              </div>
            </div>
            <div class="info-tile">
              <span class="it-label">Role</span>
              <span class="it-value">OSA Administrator</span>
            </div>
            <div class="info-tile">
              <span class="it-label">Portal</span>
              <span class="it-value">NAAP OSA Portal</span>
            </div>
            <div class="info-tile">
              <span class="it-label">Status</span>
              <span class="it-value"><span class="badge-active">Active</span></span>
            </div>
            <div class="info-tile">
              <span class="it-label">Session ID</span>
              <span class="it-value session-id-value"><?= htmlspecialchars(session_id()) ?></span>
            </div>
          </div>
        </div>

        <div class="settings-card">
          <div class="settings-card-header">
            <ion-icon name="create-outline"></ion-icon>
            <h3>Edit Profile</h3>
          </div>
          <div class="settings-card-body">
            <form method="POST" action="settings.php">
              <input type="hidden" name="action" value="update_profile">
              <div class="form-group">
                <label for="settingName">Full Name</label>
                <input type="text" id="settingName" name="name" value="<?= $osa_name ?>" placeholder="Your full name" required>
              </div>
              <div class="form-group">
                <label for="settingEmail">Email Address</label>
                <input type="email" id="settingEmail" name="email" value="<?= $osa_email ?>" placeholder="your@email.com" required>
              </div>
              <button type="submit" class="save-btn">
                <ion-icon name="save-outline" class="btn-icon-prefix"></ion-icon> Save Changes
              </button>
            </form>
          </div>
        </div>

        <div class="settings-card">
          <div class="settings-card-header">
            <ion-icon name="lock-closed-outline"></ion-icon>
            <h3>Change Password</h3>
          </div>
          <div class="settings-card-body">
            <form method="POST" action="settings.php">
              <input type="hidden" name="action" value="change_password">
              <div class="form-group">
                <label for="currentPwd">Current Password</label>
                <input type="password" id="currentPwd" name="current_password" placeholder="••••••••">
              </div>
              <div class="form-group">
                <label for="newPwd">New Password</label>
                <input type="password" id="newPwd" name="new_password" placeholder="Min. 8 characters">
              </div>
              <div class="form-group">
                <label for="confirmPwd">Confirm New Password</label>
                <input type="password" id="confirmPwd" name="confirm_password" placeholder="Re-type new password">
              </div>
              <button type="submit" class="save-btn">
                <ion-icon name="key-outline" class="btn-icon-prefix"></ion-icon> Update Password
              </button>
            </form>
          </div>
        </div>

        <div class="settings-card">
          <div class="settings-card-header">
            <ion-icon name="information-circle-outline"></ion-icon>
            <h3>System Information</h3>
          </div>
          <div class="settings-card-body">
            <div class="info-tile">
              <span class="it-label">System</span>
              <span class="it-value">NAAP Org Management</span>
            </div>
            <div class="info-tile">
              <span class="it-label">PHP Version</span>
              <span class="it-value"><?= phpversion() ?></span>
            </div>
            <div class="info-tile">
              <span class="it-label">Database</span>
              <span class="it-value">naap_org_system (MariaDB)</span>
            </div>
            <div class="info-tile">
              <span class="it-label">Server Time</span>
              <span class="it-value"><?= date('M j, Y  h:i A') ?></span>
            </div>
            <div class="info-tile">
              <span class="it-label">Timezone</span>
              <span class="it-value"><?= date_default_timezone_get() ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/admin/settings.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
