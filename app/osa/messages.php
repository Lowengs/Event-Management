<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

$osa_id = $_SESSION['osa_id'] ?? 1;

$_GET['action'] = 'get_osa_messages';
ob_start();
require_once __DIR__ . '/../../config/API/endpoints/index.php';
$msgApiRes       = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$conversations   = $msgApiRes['conversations']     ?? [];
$selectedOrgId   = (int)($msgApiRes['selected_org_id'] ?? 0);
$selectedOrgName = $msgApiRes['selected_org_name'] ?? '';
$thread          = $msgApiRes['thread']            ?? [];
$total_unread    = (int)($msgApiRes['total_unread']    ?? 0);

// Default to first organization if available and no org selected
if ($selectedOrgId === 0 && !empty($conversations)) {
    $selectedOrgId   = (int)$conversations[0]['OrgId'];
    $selectedOrgName = $conversations[0]['OrgName'] ?? '';
    $_GET['org_id']  = $selectedOrgId;
    ob_start();
    require __DIR__ . '/../../config/API/endpoints/index.php';
    $msgApiRes       = json_decode(ob_get_clean() ?: '[]', true) ?: [];
    $thread          = $msgApiRes['thread'] ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $to_org  = (int)($_POST['to_org_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body']    ?? '');
        $hasAtt  = !empty($_FILES['attachment']['name']);
        if ($to_org > 0 && ($body !== '' || $hasAtt)) {
            $_POST['org_id'] = $to_org;
            $_POST['message'] = $body !== '' ? $body : ($hasAtt ? 'Attachment: ' . htmlspecialchars($_FILES['attachment']['name']) : '');
            $_GET['action'] = 'send_osa_message';
            ob_start();
            require __DIR__ . '/../../config/API/endpoints/index.php';
            ob_end_clean();
        }
        header("Location: messages.php?org_id=$to_org");
        exit;
    }
}

function orgInitials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    $init = '';
    foreach (array_slice($words, 0, 2) as $w) $init .= strtoupper($w[0] ?? '');
    return $init ?: '?';
}

$avatarColors = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#22c55e','#ef4444','#06b6d4','#6366f1','#f59e0b','#14b8a6'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Messages</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/messages.css?<?= time() ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />

  <link rel="icon" href="../../assets/img/philsca.png">
  <style>
    .messages-container {
      display: flex;
      flex-direction: column;
      height: calc(100vh - 40px);
      width: 100%;
    }
    .messages-main {
      flex: 1;
      height: 100%;
      min-height: 0;
      overflow: hidden;
    }
    .messages-main-grid {
      display: grid;
      grid-template-columns: 360px 1fr;
      flex: 1;
      height: 100%;
      min-height: 0;
      overflow: hidden;
    }
    .conversations-pane {
      border-right: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      background: #ffffff;
      overflow: hidden;
    }
    .thread-pane {
      display: flex;
      flex-direction: column;
      background: #f8fafc;
      overflow: hidden;
    }
    .message-item.active-conv {
      background: #eff6ff;
      border-color: #3b82f6;
    }
    .mobile-back-btn {
      display: none;
    }
    .mobile-org-picker-wrap {
      display: none;
    }
    .mobile-pane-header {
      display: none;
    }

    @media (max-width: 900px) {
      .messages-main-grid {
        grid-template-columns: 1fr;
      }
      .mobile-back-btn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #003366;
        font-size: 1.25rem;
        cursor: pointer;
        flex-shrink: 0;
        margin-right: 8px;
      }
      .mobile-back-btn:hover {
        background: #e2e8f0;
      }
      .mobile-org-picker-wrap {
        display: flex !important;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
        width: 100%;
        background: #f8fafc;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
      }
      .mobile-org-picker-wrap label {
        font-size: 0.78rem;
        font-weight: 700;
        color: #003366;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
      }
      .mobile-org-picker-wrap select {
        flex: 1;
        padding: 6px 10px;
        border-radius: 6px;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
        font-weight: 600;
        font-size: 0.85rem;
        outline: none;
      }
      .mobile-pane-header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
      }
      .mobile-view-thread-btn {
        background: #003366;
        color: #ffffff;
        border: none;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
      }
      .messages-main-grid.mobile-show-list .conversations-pane {
        display: flex !important;
      }
      .messages-main-grid.mobile-show-list .thread-pane {
        display: none !important;
      }
      .messages-main-grid.mobile-show-thread .conversations-pane {
        display: none !important;
      }
      .messages-main-grid.mobile-show-thread .thread-pane {
        display: flex !important;
      }
      .conversations-pane {
        display: <?= ($selectedOrgId > 0 && empty($_GET['view'])) ? 'none' : 'flex' ?>;
      }
      .thread-pane {
        display: <?= ($selectedOrgId > 0 && empty($_GET['view'])) ? 'flex' : 'none' ?>;
      }
    }
  </style>
<script src="../../assets/js/security.js"></script>
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
        <li><a href="organization.php"   class="nav"><ion-icon name="business-outline"></ion-icon><span>Organization</span></a></li>
        <li><a href="calendar.php"       class="nav"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
        <li><a href="events.php"         class="nav"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
        <li><a href="students.php"       class="nav"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
        <li><a href="announcement.php"   class="nav"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
        <li><a href="reports.php"        class="nav"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
        <li><a href="audit-trail.php"    class="nav"><ion-icon name="analytics-outline"></ion-icon><span>Audit Trail</span></a></li>
        <li><a href="messages.php"       class="nav active"><ion-icon name="chatbox-outline"></ion-icon><span>Messages</span></a></li>
        <li><a href="settings.php"       class="nav"><ion-icon name="cog-outline"></ion-icon><span>Settings</span></a></li>
        <li><a href="../../config/API/endpoints/index.php?action=osa_logout" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
      </ul>
    </nav>

    <div class="maincontent">
      <div class="messages-container">

        <div class="messages-main">
          <div class="messages-main-grid">
            
            <!-- Left Pane: Organizations List -->
            <div class="conversations-pane">
              <div class="mobile-pane-header">
                <span><ion-icon name="list-outline"></ion-icon> Select Organization</span>
                <?php if ($selectedOrgId > 0): ?>
                <button type="button" class="mobile-view-thread-btn" onclick="toggleMobileView('thread')">
                  View Chat &rarr;
                </button>
                <?php endif; ?>
              </div>
              <div class="messages-header" style="display:flex; gap:10px; align-items:center;">
                <div class="search-bar" style="flex:1;">
                  <ion-icon name="search-outline"></ion-icon>
                  <input type="text" id="msgSearch" placeholder="Search organizations..." oninput="filterMessages()" />
                </div>
                <button type="button" class="action-btn primary" onclick="document.getElementById('composeModal').style.display='flex'" style="width:auto; padding:10px 16px; font-size:13px; margin:0; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; border-radius:10px;" title="Compose Message">
                  <ion-icon name="create-outline"></ion-icon>
                  <span>Compose</span>
                </button>
              </div>
              <div class="messages-list" id="messagesList">
                <?php if (empty($conversations)): ?>
                  <p style="padding:2rem;color:#64748b;text-align:center;">No organizations found.</p>
                <?php endif; ?>
                <?php foreach ($conversations as $i => $conv):
                  $color   = $avatarColors[$i % count($avatarColors)];
                  $initials = orgInitials($conv['OrgName'] ?? '?');
                  $lastMsg = !empty($conv['last_message']) ? substr($conv['last_message'], 0, 60) . (strlen($conv['last_message']) > 60 ? '...' : '') : 'No messages yet';
                  $rawTime = $conv['last_sent_at'] ?? $conv['last_time'] ?? '';
                  $lastTime = !empty($rawTime) ? date('M j, g:i A', strtotime($rawTime)) : '';
                  $unread = (int)($conv['unread_count'] ?? 0);
                  $isActiveConv = ((int)$conv['OrgId'] === $selectedOrgId);
                ?>
                <article class="message-item <?= $isActiveConv ? 'active-conv' : '' ?>" data-name="<?= strtolower(htmlspecialchars($conv['OrgName'])) ?>"
                  onclick="window.location.href='messages.php?org_id=<?= (int)$conv['OrgId'] ?>'">
                  <div class="message-avatar" style="background:<?= $color ?>;cursor:pointer;"><?= htmlspecialchars($initials) ?></div>
                  <div class="message-content">
                    <div class="message-header-row">
                      <div class="message-sender">
                        <h4 style="font-size:0.88rem;"><?= htmlspecialchars($conv['OrgName']) ?></h4>
                        <?php if ($unread > 0): ?>
                        <span class="msg-unread-badge"><?= $unread ?></span>
                        <?php endif; ?>
                      </div>
                      <span class="message-time" style="font-size:0.75rem;"><?= htmlspecialchars($lastTime) ?></span>
                    </div>
                    <?php if (!empty($conv['last_subject'])): ?>
                    <h5 class="message-subject" style="font-size:0.8rem;margin-bottom:2px;"><?= htmlspecialchars($conv['last_subject']) ?></h5>
                    <?php endif; ?>
                    <p class="message-preview" style="font-size:0.8rem;"><?= htmlspecialchars($lastMsg) ?></p>
                  </div>
                </article>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Right Pane: Message Thread -->
            <div class="thread-pane">
              <?php if ($selectedOrgId > 0): ?>
              <div class="thread-header" style="background:#fff; flex-direction:column; align-items:stretch; padding:12px 16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                  <div style="display:flex; align-items:center;">
                    <button type="button" class="mobile-back-btn" onclick="toggleMobileView('list')" title="Back to All Organizations">
                      <ion-icon name="arrow-back-outline"></ion-icon>
                    </button>
                    <div>
                      <h4 style="margin:0; font-size:1.05rem; font-weight:700; color:#0f172a;"><?= htmlspecialchars($selectedOrgName) ?></h4>
                      <p style="margin:2px 0 0; font-size:0.78rem; color:#64748b;">Conversation Thread</p>
                    </div>
                  </div>
                  <button type="button" class="action-btn primary" onclick="document.getElementById('composeModal').style.display='flex'" style="width:auto; padding:6px 14px; font-size:12px; margin:0; display:inline-flex;">
                    <ion-icon name="create-outline"></ion-icon> Compose
                  </button>
                </div>

                <!-- Responsive Quick Organization Selector Dropdown -->
                <div class="mobile-org-picker-wrap">
                  <label for="mobileOrgSelect"><ion-icon name="swap-horizontal-outline"></ion-icon> Org:</label>
                  <select id="mobileOrgSelect" onchange="if(this.value) window.location.href='messages.php?org_id='+this.value;">
                    <?php foreach ($conversations as $conv): ?>
                      <option value="<?= (int)$conv['OrgId'] ?>" <?= ((int)$conv['OrgId'] === $selectedOrgId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($conv['OrgName']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="message-thread" id="threadContainer" style="background:#f8fafc;padding:1rem;">
                <?php if (empty($thread)): ?>
                <div class="thread-empty">No messages yet with <?= htmlspecialchars($selectedOrgName) ?>. Start the conversation below.</div>
                <?php else: ?>
                <?php foreach ($thread as $msg):
                  $isOsa = ($msg['SenderType'] === 'osa');
                  $bubbleCls = $isOsa ? 'from-osa' : 'from-org';
                  $timeStr = !empty($msg['SentAt']) ? date('M j, Y g:i A', strtotime($msg['SentAt'])) : '';
                  $attPath = !empty($msg['AttachmentPath']) ? '../../' . ltrim($msg['AttachmentPath'], '/') : '';
                  $attName = htmlspecialchars($msg['AttachmentName'] ?? 'Attachment');
                  $attType = strtolower($msg['AttachmentType'] ?? '');
                ?>
                <div class="msg-row <?= $bubbleCls ?>" style="margin-bottom:12px;">
                  <?php if (!empty($msg['Subject']) && !$isOsa): ?>
                  <span style="font-size:.72rem;color:#64748b;margin-bottom:3px;font-weight:600;"><?= htmlspecialchars($msg['Subject']) ?></span>
                  <?php endif; ?>
                  <div class="msg-bubble <?= $bubbleCls ?>">
                    <?php if (!empty($msg['Message'])): ?>
                      <div><?= nl2br(htmlspecialchars($msg['Message'])) ?></div>
                    <?php endif; ?>
                    <?php if ($attPath): ?>
                      <div class="msg-attachment-wrap" style="margin-top:8px;">
                        <?php if ($attType === 'image'): ?>
                          <a href="<?= htmlspecialchars($attPath) ?>" target="_blank" style="display:block;border-radius:8px;overflow:hidden;max-width:240px;border:1px solid rgba(0,0,0,0.1);">
                            <img src="<?= htmlspecialchars($attPath) ?>" alt="<?= $attName ?>" style="width:100%;max-height:180px;object-fit:cover;display:block;">
                          </a>
                        <?php else: ?>
                          <a href="<?= htmlspecialchars($attPath) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;padding:8px 12px;background:<?= $isOsa ? 'rgba(255,255,255,0.18)' : '#ffffff' ?>;border:1px solid <?= $isOsa ? 'rgba(255,255,255,0.3)' : '#cbd5e1' ?>;border-radius:8px;text-decoration:none;color:inherit;font-size:0.82rem;font-weight:600;">
                            <ion-icon name="<?= $attType === 'pdf' ? 'document-text-outline' : 'document-outline' ?>" style="font-size:1.3rem;color:<?= $attType === 'pdf' ? '#ef4444' : '#2563eb' ?>;"></ion-icon>
                            <span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $attName ?></span>
                            <ion-icon name="download-outline" style="font-size:1.1rem;margin-left:4px;"></ion-icon>
                          </a>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <span class="msg-meta" style="font-size:0.7rem;color:#94a3b8;margin-top:3px;"><?= htmlspecialchars($msg['sender_label'] ?? ($isOsa ? 'OSA' : $selectedOrgName)) ?> · <?= htmlspecialchars($timeStr) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <form method="POST" action="messages.php?org_id=<?= $selectedOrgId ?>" style="background:#fff;padding:1rem;border-top:1px solid #e2e8f0;">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="to_org_id" value="<?= (int)$selectedOrgId ?>">
                <div class="compose-area" style="flex-direction:column;border:none;padding:0;">
                  <input type="text" name="subject" placeholder="Subject (optional)" style="width:100%;margin-bottom:8px;padding:.5rem .75rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;box-sizing:border-box;" />
                  <div style="display:flex;gap:.5rem;width:100%;">
                    <textarea name="body" placeholder="Type your message..." required style="flex:1;border:1.5px solid #e2e8f0;border-radius:8px;padding:.55rem .75rem;font-family:inherit;font-size:.85rem;height:60px;resize:none;box-sizing:border-box;"></textarea>
                    <button type="submit" style="padding:.5rem 1.2rem;background:#003366;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;"><ion-icon name="send-outline" style="font-size:1.2rem;"></ion-icon></button>
                  </div>
                </div>
              </form>
              <?php else: ?>
              <div style="display:flex;align-items:center;justify-content:center;flex:1;color:#64748b;font-size:0.95rem;">
                Select an organization from the left list to view conversation.
              </div>
              <?php endif; ?>
            </div>

          </div>
        </div>

      </div>
    </div>
  </main>

  <div id="composeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:92%;max-width:480px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.25);">
      <div style="background:linear-gradient(135deg,#003366,#0a5eb0);padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;">
        <h3 style="color:#fff;margin:0;font-size:1rem;">Compose New Message</h3>
        <button onclick="document.getElementById('composeModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;line-height:1;">&times;</button>
      </div>
      <form method="POST" action="messages.php" style="padding:1.25rem;display:flex;flex-direction:column;gap:.75rem;">
        <input type="hidden" name="action" value="send_message">
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;">To (Organization)</label>
          <select name="to_org_id" required style="width:100%;margin-top:4px;padding:.5rem .75rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;">
            <option value="">Select organization...</option>
            <?php foreach ($conversations as $conv): ?>
            <option value="<?= (int)$conv['OrgId'] ?>"><?= htmlspecialchars($conv['OrgName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;">Subject</label>
          <input type="text" name="subject" placeholder="Enter subject..." style="width:100%;margin-top:4px;padding:.5rem .75rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;">Message</label>
          <textarea name="body" rows="4" required placeholder="Write your message here..." style="width:100%;margin-top:4px;padding:.55rem .75rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:.5rem;">
          <button type="button" onclick="document.getElementById('composeModal').style.display='none'" style="padding:.5rem 1rem;border:1px solid #e2e8f0;background:#fff;border-radius:6px;cursor:pointer;font-weight:600;color:#334155;">Cancel</button>
          <button type="submit" style="padding:.5rem 1.2rem;background:#003366;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600;">Send</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/admin/dashboard.js"></script>
  
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <script src="../../assets/js/admin/messages.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>
  <script>
    function filterMessages() {
      const q = document.getElementById('msgSearch').value.toLowerCase().trim();
      document.querySelectorAll('#messagesList .message-item').forEach(el => {
        const name = el.getAttribute('data-name') || '';
        el.style.display = (!q || name.includes(q)) ? 'flex' : 'none';
      });
    }

    function toggleMobileView(view) {
      const grid = document.querySelector('.messages-main-grid');
      if (!grid) return;
      if (view === 'list') {
        grid.classList.add('mobile-show-list');
        grid.classList.remove('mobile-show-thread');
      } else {
        grid.classList.add('mobile-show-thread');
        grid.classList.remove('mobile-show-list');
      }
    }
  </script>
</body>
</html>
