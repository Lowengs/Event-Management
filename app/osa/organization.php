<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';


require_once '../../config/img_helpers.php';
function orgImgUrl(string $p): string { return imgPathForDepth($p, 2, '../../assets/img/philsca.png'); }

$organizations = [];
if ($conn) {
    $q = "
        SELECT 
            o.*,
            (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId) AS members_count,
            (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId AND u.officer_role IS NOT NULL AND u.officer_role != '') AS officers_count,
            (SELECT CONCAT(u.first_name,' ',u.last_name) FROM user u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%president%' AND LOWER(u.officer_role) NOT LIKE '%vice%' LIMIT 1) AS president_name,
            (SELECT CONCAT(u.first_name,' ',u.last_name) FROM user u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%vice%president%' LIMIT 1) AS vp_name,
            (SELECT GROUP_CONCAT(t.Type SEPARATOR ', ') FROM orgtype t WHERE t.OrgId = o.OrgId) AS org_type
        FROM organization o
        ORDER BY o.OrgName ASC
    ";
    $r = $conn->query($q);
    if ($r) {
        while ($row = $r->fetch_assoc()) $organizations[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Organization</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/organization.css?<?= time() ?>" />

  
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
        <li><a href="organization.php" class="nav active"><ion-icon name="business-outline"></ion-icon><span>Organization</span></a></li>
        <li><a href="calendar.php" class="nav"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
        <li><a href="events.php" class="nav"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
        <li><a href="students.php" class="nav"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
        <li><a href="announcement.php" class="nav"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
        <li><a href="reports.php" class="nav"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
        <li><a href="audit-trail.php" class="nav"><ion-icon name="analytics-outline"></ion-icon><span>Audit Trail</span></a></li>
        <li><a href="messages.php" class="nav"><ion-icon name="chatbox-outline"></ion-icon><span>Messages</span></a></li>
        <li><a href="settings.php" class="nav"><ion-icon name="cog-outline"></ion-icon><span>Settings</span></a></li>
        <li><a href="../../config/API/osa_logout.php" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
      </ul>
    </nav>

    <div class="maincontent">
      <div class="pagebar">
        <a class="back-btn" href="dashboard_final.php" aria-label="Back to dashboard">
          <ion-icon name="arrow-back-outline"></ion-icon>
        </a>
        <div class="pagebar-text">
          <h2>Organization Management</h2>
          <p>Manage and monitor all student organizations</p>
        </div>
      </div>

      <div class="divider"></div>

      <?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
        <div style="background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;font-size:.85rem;font-weight:500;">
          <ion-icon name="checkmark-circle-outline"></ion-icon> Organization created successfully!
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;font-size:.85rem;font-weight:500;">
          <ion-icon name="alert-circle-outline"></ion-icon> <?= htmlspecialchars($_GET['error'] === 'missing_name' ? 'Organization name is required.' : 'Failed to create organization. Please try again.') ?>
        </div>
      <?php endif; ?>

      
      <section class="filter-panel">
        <div class="filter-grid" style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
          <div style="display:flex;gap:1rem;flex-wrap:wrap;flex:1;">
            <div class="filter-block" style="flex:1;min-width:200px;">
              <div class="filter-title">
                <ion-icon name="search-outline"></ion-icon>
                <h4>Search Organization</h4>
              </div>
              <div class="input-wrap">
                <ion-icon name="search-outline" class="input-ico"></ion-icon>
                <input type="text" id="orgSearch" placeholder="Search by organization name..." oninput="filterOrgs()" />
              </div>
            </div>

            <div class="filter-block" style="flex:1;min-width:160px;">
              <div class="filter-title">
                <ion-icon name="funnel-outline"></ion-icon>
                <h4>Filter Status</h4>
              </div>
              <div class="input-wrap">
                <ion-icon name="options-outline" class="input-ico"></ion-icon>
                <select id="statusFilter" aria-label="Status filter" onchange="filterOrgs()">
                  <option value="all">All</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
                </select>
              </div>
            </div>
          </div>

          <button onclick="document.getElementById('createOrgModal').style.display='flex'"
            style="display:flex;align-items:center;gap:6px;padding:.6rem 1.1rem;background:#003366;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;white-space:nowrap;height:42px;">
            <ion-icon name="add-circle-outline" style="font-size:1.1rem;"></ion-icon>
            Create New Organization
          </button>
        </div>
      </section>

      
      <div class="view-toggle" style="justify-content: flex-end; padding: 0 0.25rem;">
        <button class="view-toggle-btn active-view" id="cardsViewBtn" onclick="setView('cards')">
          <ion-icon name="grid-outline"></ion-icon> Cards
        </button>
        <button class="view-toggle-btn" id="tableViewBtn" onclick="setView('table')">
          <ion-icon name="list-outline"></ion-icon> Table
        </button>
      </div>

      
      <div id="cardsView">
        <?php if (empty($organizations)): ?>
          <div style="text-align: center; padding: 3rem; color: #64748b;">
            <ion-icon name="business-outline" style="font-size: 3rem; opacity: 0.4;"></ion-icon>
            <p style="margin-top: 1rem;">No organizations found.</p>
          </div>
        <?php else: ?>
          <div class="org-card-grid" id="orgCardGrid">
            <?php foreach($organizations as $org):
              $status     = $org['Status'] ?: 'Active';
              $statusCls  = strtolower($status) === 'active' ? 'active' : (strtolower($status) === 'inactive' ? 'inactive' : 'suspended');
              $initials   = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $org['OrgName'])));
              $orgLogoSrc   = orgImgUrl($org['OrgPicture']   ?? '');
              $orgBannerSrc = orgImgUrl($org['OrgBanner']    ?? '');
              $hasPic       = !empty($orgLogoSrc);
            ?>
            <div class="org-card"
              data-name="<?= strtolower(htmlspecialchars($org['OrgName'])) ?>"
              data-status="<?= strtolower($status) ?>"
              data-orgid="<?= (int)$org['OrgId'] ?>"
              data-logo="<?= htmlspecialchars($orgLogoSrc) ?>"
              data-banner="<?= htmlspecialchars($orgBannerSrc) ?>"
              data-desc="<?= htmlspecialchars($org['Description'] ?? '') ?>"
              data-type="<?= htmlspecialchars($org['org_type'] ?? '') ?>"
              data-adviser="<?= htmlspecialchars($org['Adviser'] ?? '') ?>"
              data-registered="<?= htmlspecialchars(!empty($org['DateRegistered']) ? date('M j, Y', strtotime($org['DateRegistered'])) : '') ?>"
              data-president="<?= htmlspecialchars($org['president_name'] ?? '') ?>"
              data-vp="<?= htmlspecialchars($org['vp_name'] ?? '') ?>"
              data-members="<?= (int)$org['members_count'] ?>"
              data-officers="<?= (int)$org['officers_count'] ?>">
              <div class="org-card-banner" style="<?= $orgBannerSrc ? 'background-image:url(' . htmlspecialchars($orgBannerSrc) . ');background-size:cover;background-position:center;' : '' ?>">
                <img src="<?= htmlspecialchars($orgLogoSrc ?: '../../assets/img/philsca.png') ?>" alt="<?= htmlspecialchars($org['OrgName']) ?> logo" class="org-card-logo"
                     onerror="this.onerror=null; this.src='../../assets/img/philsca.png';">
              </div>
              <div class="org-card-body">
                <div class="org-card-header">
                  <div>
                    <h3 class="org-card-name"><?= htmlspecialchars($org['OrgName']) ?></h3>
                    <p class="org-card-type"><?= htmlspecialchars($org['org_type'] ?: 'Organization') ?></p>
                  </div>
                  <span class="org-status-pill <?= $statusCls ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>

                <p class="org-card-desc"><?= htmlspecialchars($org['Description'] ?: 'No description available.') ?></p>

                <div class="org-card-officers">
                  <?php if (!empty($org['president_name'])): ?>
                  <div class="org-officer-row">
                    <span class="org-officer-label">President</span>
                    <span class="org-officer-value"><?= htmlspecialchars($org['president_name']) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($org['vp_name'])): ?>
                  <div class="org-officer-row">
                    <span class="org-officer-label">Vice President</span>
                    <span class="org-officer-value"><?= htmlspecialchars($org['vp_name']) ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if (empty($org['president_name']) && empty($org['vp_name'])): ?>
                    <p style="font-size: 0.75rem; color: #94a3b8; text-align: center; margin: 0;">No officers assigned yet</p>
                  <?php endif; ?>
                </div>

                <div class="org-card-stats">
                  <div class="org-stat-box">
                    <span class="org-stat-val"><?= (int)$org['members_count'] ?></span>
                    <span class="org-stat-lab">Members</span>
                  </div>
                  <div class="org-stat-box">
                    <span class="org-stat-val"><?= (int)$org['officers_count'] ?></span>
                    <span class="org-stat-lab">Officers</span>
                  </div>
                </div>
              </div>
              <div class="org-card-footer">
                <div class="org-adviser">
                  <?php if (!empty($org['Adviser'])): ?>
                    Adviser: <strong><?= htmlspecialchars($org['Adviser']) ?></strong>
                  <?php else: ?>
                    <span style="color: #94a3b8;">No adviser assigned</span>
                  <?php endif; ?>
                </div>
                <button class="org-view-btn" onclick="viewOrgDetails(<?= $org['OrgId'] ?>)">View Details</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      
      <div id="tableView" style="display:none;">
        <section class="org-table">
          <div class="table-card table-modern">
            <div class="table-wrap">
              <table class="data-table" id="orgDataTable">
                <thead>
                  <tr>
                    <th>Organization</th>
                    <th class="type-col">Type</th>
                    <th>President</th>
                    <th>Members</th>
                    <th>Officers</th>
                    <th>Adviser</th>
                    <th>Registered</th>
                    <th class="center-align">Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (empty($organizations)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 2rem;">No organizations found.</td></tr>
                <?php else: ?>
                    <?php foreach($organizations as $org):
                        $status     = $org['Status'] ?: 'Active';
                        $statusCls  = strtolower($status) === 'active' ? 'active' : 'suspended';
                        $hasPic     = !empty($org['OrgPicture']);
                        $orgLogoSrc = orgImgUrl($org['OrgPicture'] ?? '');
                        $dateReg    = !empty($org['DateRegistered']) ? date('M j, Y', strtotime($org['DateRegistered'])) : 'N/A';
                    ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($org['OrgName'])) ?>" data-status="<?= strtolower($status) ?>">
                      <td>
                        <div class="orgCell">
                          <div class="orgLogo">
                            <img src="<?= $orgLogoSrc ?: '../../assets/img/philsca.png' ?>" alt="Logo"
                                 onerror="this.src='../../assets/img/philsca.png'">
                          </div>
                          <div class="orgInfo">
                              <a class="orgName" href="#" onclick="viewOrgDetails(<?= $org['OrgId'] ?>); return false;"><?= htmlspecialchars($org['OrgName']) ?></a>
                          </div>
                        </div>
                      </td>
                      <td class="muted type-col"><?= htmlspecialchars($org['org_type'] ?: 'Academic') ?></td>
                      <td><?= htmlspecialchars($org['president_name'] ?: '—') ?></td>
                      <td>
                        <div class="miniStat">
                          <ion-icon name="people-outline"></ion-icon>
                          <span class="miniVal"><?= (int)$org['members_count'] ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="miniStat">
                          <ion-icon name="person-outline"></ion-icon>
                          <span class="miniVal"><?= (int)$org['officers_count'] ?></span>
                        </div>
                      </td>
                      <td class="wrap"><div><?= htmlspecialchars($org['Adviser'] ?: '—') ?></div></td>
                      <td class="muted"><?= $dateReg ?></td>
                      <td class="center-align"><span class="statusPill <?= $statusCls ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>

    </div>
  </main>

  <script src="../../assets/js/admin/dashboard.js"></script>
  
  
  <div id="orgDetailModal" style="
    display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5);
    z-index:9999; align-items:center; justify-content:center;
  ">
    <div style="background:#fff; border-radius:16px; width:90%; max-width:560px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.25);">
      <div style="background:linear-gradient(135deg,#003366,#0a5eb0); padding:1.5rem; display:flex; align-items:center; gap:1rem;">
        <img id="mdOrgLogo" src="" alt="Logo" style="width:64px;height:64px;border-radius:50%;border:3px solid #fff;object-fit:cover;">
        <div>
          <h3 id="mdOrgName" style="color:#fff;margin:0;font-size:1.2rem;"></h3>
          <p  id="mdOrgType"   style="color:rgba(255,255,255,0.75);font-size:0.8rem;margin:2px 0 0;"></p>
        </div>
        <button id="closeOrgModal" style="margin-left:auto;background:none;border:none;color:#fff;font-size:1.6rem;cursor:pointer;line-height:1;">&times;</button>
      </div>
      <div style="padding:1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">Status</p>
          <p id="mdOrgStatus" style="font-weight:700;font-size:.9rem;color:#0f172a;margin:0;"></p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">Registered</p>
          <p id="mdOrgRegistered" style="font-weight:700;font-size:.9rem;color:#0f172a;margin:0;"></p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">President</p>
          <p id="mdOrgPresident" style="font-weight:700;font-size:.9rem;color:#0f172a;margin:0;"></p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">Vice President</p>
          <p id="mdOrgVp" style="font-weight:700;font-size:.9rem;color:#0f172a;margin:0;"></p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">Members</p>
          <p id="mdOrgMembers" style="font-weight:700;font-size:1.2rem;color:#003366;margin:0;"></p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">Officers</p>
          <p id="mdOrgOfficers" style="font-weight:700;font-size:1.2rem;color:#003366;margin:0;"></p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.75rem;grid-column:1/-1;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">Adviser</p>
          <p id="mdOrgAdviser" style="font-weight:600;font-size:.9rem;color:#0f172a;margin:0;"></p>
        </div>
        <div style="grid-column:1/-1;background:#f8fafc;border-radius:8px;padding:.75rem;">
          <p style="font-size:.7rem;color:#64748b;margin:0 0 2px;">About</p>
          <p id="mdOrgDesc" style="font-size:.83rem;color:#334155;margin:0;line-height:1.5;"></p>
        </div>
      </div>
      
      <div style="border-top:1px solid #f1f5f9;padding:.75rem 1.5rem;display:flex;justify-content:flex-end;">
        <button id="closeOrgModalBottom" style="padding:.5rem 1.25rem;background:#003366;color:#fff;border:none;border-radius:8px;font-family:inherit;font-weight:600;cursor:pointer;font-size:.85rem;">Close</button>
      </div>
      </div>
    </div>
  </div>

  
  <div id="createOrgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:92%;max-width:600px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.28);max-height:90vh;overflow-y:auto;">
      <div style="background:linear-gradient(135deg,#003366,#0a5eb0);padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;">
        <h3 style="color:#fff;margin:0;font-size:1.1rem;">Create New Organization</h3>
        <button onclick="document.getElementById('createOrgModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:1.6rem;cursor:pointer;line-height:1;">&times;</button>
      </div>
      <form method="POST" action="../../config/API/create_org.php" enctype="multipart/form-data" style="padding:1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <input type="hidden" name="action" value="create_org">
        <div style="grid-column:1/-1;">
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Organization Name *</label>
          <input name="org_name" required placeholder="e.g. AISERS" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Email</label>
          <input name="email" type="email" placeholder="org@naap.edu.ph" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Adviser</label>
          <input name="adviser" placeholder="Faculty adviser name" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Date Registered</label>
          <input name="date_registered" type="date" min="<?= date('Y-m-d') ?>" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Status</label>
          <select name="status" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Username (for org login)</label>
          <input name="username" placeholder="org_username" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Password (for org login)</label>
          <input name="password" type="password" placeholder="Min. 8 characters" style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;box-sizing:border-box;">
        </div>
        <div style="grid-column:1/-1;">
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Description</label>
          <textarea name="description" rows="3" placeholder="Brief description of the organization..." style="width:100%;padding:.55rem .9rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="grid-column:1/-1;">
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Organization Logo</label>
          <input name="org_picture" type="file" accept="image/*" style="width:100%;padding:.4rem;font-family:inherit;font-size:.85rem;">
        </div>
        <div style="grid-column:1/-1;">
          <label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Organization Banner <span style="color:#94a3b8;font-weight:400;">— wide cover photo</span></label>
          <input name="org_banner" type="file" accept="image/*" style="width:100%;padding:.4rem;font-family:inherit;font-size:.85rem;">
        </div>
        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:.75rem;padding-top:.5rem;">
          <button type="button" onclick="document.getElementById('createOrgModal').style.display='none'" style="padding:.55rem 1.2rem;border:1.5px solid #e2e8f0;background:#fff;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;color:#334155;cursor:pointer;">Cancel</button>
          <button type="submit" style="padding:.55rem 1.4rem;background:#003366;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;">Create Organization</button>
        </div>
      </form>
    </div>
  </div>


  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <script src="../../assets/js/admin/organization.js"></script>
</body>
</html>


