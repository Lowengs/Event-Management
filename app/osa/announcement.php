<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

ob_start();
$_GET['action'] = 'get_osa_announcements';
require __DIR__ . '/../../config/API/endpoints/index.php';
$annApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$announcements = $annApiRes['data'] ?? [];

ob_start();
$_GET['action'] = 'get_osa_organizations';
require __DIR__ . '/../../config/API/endpoints/index.php';
$orgApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$organizations = $orgApiRes['data'] ?? [];

header('Content-Type: text/html; charset=UTF-8');

$total_announcements = count($announcements);
$pending_count  = count(array_filter($announcements, function($a){ return strtolower($a['Status']??'') === 'pending'; }));
$approved_count = count(array_filter($announcements, function($a){ return strtolower($a['Status']??'') === 'approved'; }));
$declined_count = count(array_filter($announcements, function($a){ return in_array(strtolower($a['Status']??''), ['rejected', 'failed', 'declined']); }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP OSA Portal - Announcements</title>
    <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
    <link rel="stylesheet" href="../../assets/css/admin/announcement.css?<?= time() ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
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
                <li><a href="announcement.php" class="nav active"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
                <li><a href="reports.php" class="nav"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
                <li><a href="audit-trail.php" class="nav"><ion-icon name="analytics-outline"></ion-icon><span>Audit Trail</span></a></li>
                <li><a href="messages.php" class="nav"><ion-icon name="chatbox-outline"></ion-icon><span>Messages</span></a></li>
                <li><a href="settings.php" class="nav"><ion-icon name="cog-outline"></ion-icon><span>Settings</span></a></li>
                <li><a href="../../config/API/endpoints/index.php?action=osa_logout" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
            </ul>
        </nav>

        <div class="maincontent">
            <div class="pagebar">
                <a class="back-btn" href="dashboard_final.php" aria-label="Back to dashboard">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                </a>

                <div class="pagebar-text">
                    <h2>Announcements Management</h2>
                    <p>Review organization submissions before publishing to students</p>
                </div>

                <div class="pagebar-actions">
                    <button type="button" class="add-announcement-btn" id="openCreateAnnouncementModal" aria-label="Add announcement">
                        <ion-icon name="add-outline"></ion-icon>
                        Add Announcement
                    </button>
                </div>
            </div>

            <div class="divider"></div>

            <section class="stats-grid">
                <article class="stat-tile">
                    <div>
                        <p class="stat-label">Total Announcements</p>
                        <p class="stat-value"><?= number_format($total_announcements) ?></p>
                    </div>
                    <div class="tile-icon total">
                        <ion-icon name="megaphone-outline"></ion-icon>
                    </div>
                </article>

                <article class="stat-tile">
                    <div>
                        <p class="stat-label">Pending Review</p>
                        <p class="stat-value pending"><?= number_format($pending_count) ?></p>
                    </div>
                    <div class="tile-icon pending">
                        <ion-icon name="time-outline"></ion-icon>
                    </div>
                </article>

                <article class="stat-tile">
                    <div>
                        <p class="stat-label">Approved</p>
                        <p class="stat-value approved"><?= number_format($approved_count) ?></p>
                    </div>
                    <div class="tile-icon approved">
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                    </div>
                </article>

                <article class="stat-tile">
                    <div>
                        <p class="stat-label">Declined</p>
                        <p class="stat-value declined"><?= number_format($declined_count) ?></p>
                    </div>
                    <div class="tile-icon declined">
                        <ion-icon name="close-circle-outline"></ion-icon>
                    </div>
                </article>
            </section>

            <section class="filter-panel">
                <div class="filter-item">
                    <label for="orgFilter">
                        <ion-icon name="business-outline"></ion-icon>
                        Organization
                    </label>
                    <select id="orgFilter">
                        <option>All Organizations</option>
                        <option>Technology Society</option>
                        <option>Red Cross Youth</option>
                        <option>Career Services</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="statusFilter">
                        <ion-icon name="funnel-outline"></ion-icon>
                        Status
                    </label>
                    <select id="statusFilter">
                        <option>All Status</option>
                        <option>Pending</option>
                        <option>Approved</option>
                        <option>Declined</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="priorityFilter">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        Priority
                    </label>
                    <select id="priorityFilter">
                        <option>All Priority</option>
                        <option>High</option>
                        <option>Medium</option>
                        <option>Low</option>
                    </select>
                </div>

                <div class="filter-item search-block">
                    <label for="searchAnnouncement">
                        <ion-icon name="search-outline"></ion-icon>
                        Search
                    </label>
                    <div class="search-input">
                        <ion-icon name="search-outline"></ion-icon>
                        <input id="searchAnnouncement" type="text" placeholder="Search announcements...">
                    </div>
                </div>
            </section>

            <section class="announcement-table-wrap">
                <table class="announcement-table">
                    <thead>
                        <tr>
                            <th>Announcement Title <ion-icon name="chevron-down-outline"></ion-icon></th>
                            <th>Organization <ion-icon name="chevron-down-outline"></ion-icon></th>
                            <th>Priority <ion-icon name="chevron-down-outline"></ion-icon></th>
                            <th>Submitted Date <ion-icon name="chevron-down-outline"></ion-icon></th>
                            <th>Target Audience</th>
                            <th>Status <ion-icon name="chevron-down-outline"></ion-icon></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($announcements)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2rem;">No announcements found.</td></tr>
                    <?php else: ?>
                        <?php foreach($announcements as $ann): 
                            $datePosted = $ann['DatePosted'] ? new DateTime($ann['DatePosted']) : null;
                            $dateStr = $datePosted ? $datePosted->format('Y-m-d') : 'N/A';
                            
                            $descSnippet = strlen($ann['Body']) > 40 ? substr($ann['Body'], 0, 40) . '...' : (empty($ann['Body']) ? 'No description available' : $ann['Body']);
                            $audienceCode = strtolower(trim($ann['Audience'] ?? 'all_org'));
                            $audienceLabel = match ($audienceCode) {
                                'by_org' => 'By Organization',
                                'all_org' => 'All Organizations',
                                'students' => 'Students',
                                'all' => 'All',
                                default => $ann['Audience'] ?? 'All Organizations',
                            };
                        ?>
                        <tr>
                            <td>
                                <p class="title-main"><?= htmlspecialchars($ann['Title']) ?></p>
                                <p class="title-sub"><?= htmlspecialchars(trim($descSnippet)) ?></p>
                                <p class="title-meta"><?= htmlspecialchars($ann['Category'] ?? 'General Notice') ?></p>
                            </td>
                            <td>
                                <p class="org-main"><?= htmlspecialchars($ann['OrgName'] ?? 'Unknown Org') ?></p>
                            </td>
                            <td><span class="pill priority medium">MEDIUM</span></td>
                            <td>
                                <p class="date-main"><ion-icon name="calendar-outline"></ion-icon> <?= $dateStr ?></p>
                            </td>
                            <td>
                                <p class="audience"><ion-icon name="people-outline"></ion-icon> <?= htmlspecialchars($audienceLabel) ?></p>
                            </td>
                            <td>
                                <?php 
                                    $statusClass = strtolower($ann['Status'] ?? 'pending');
                                    $statusText = ucfirst($statusClass);
                                    if ($statusClass == 'approved') $pillClass = 'approved';
                                    elseif ($statusClass == 'rejected' || $statusClass == 'failed') $pillClass = 'declined';
                                    else $pillClass = 'pending';
                                ?>
                                <span class="pill status <?= $pillClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <div class="action-set">
                                    <button class="action-btn view" title="View" aria-label="View announcement" onclick='viewAnnouncement(<?= json_encode($ann) ?>)'>
                                        <ion-icon name="eye-outline"></ion-icon>
                                    </button>
                                    <button class="action-btn delete" title="Delete" aria-label="Delete announcement" onclick="deleteAnnouncement(<?= (int)$ann['AnnouncementId'] ?>)" style="color: #ef4444; border: 1px solid #ef4444; border-radius: 6px; background: transparent; padding: 6px;">
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                    <?php if ($statusClass == 'pending' || $statusClass == 'draft'): ?>
                                    <button class="action-btn approve" title="Approve" onclick="updateStatus(<?= $ann['AnnouncementId'] ?>, 'approved')" style="color: #10b981; border: 1px solid #10b981; border-radius: 6px; background: transparent; padding: 6px;">
                                        <ion-icon name="checkmark-outline"></ion-icon>
                                    </button>
                                    <button class="action-btn reject" title="Reject" onclick="updateStatus(<?= $ann['AnnouncementId'] ?>, 'rejected')" style="color: #ef4444; border: 1px solid #ef4444; border-radius: 6px; background: transparent; padding: 6px;">
                                        <ion-icon name="close-outline"></ion-icon>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>

    </main>

    
    <div id="announcementModal" class="announcement-modal">
        <div class="announcement-modal-content">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h2 id="modalAnnouncementTitle">Announcement Title</h2>
                    <p class="modal-subtitle" id="modalAnnouncementCategory">Category</p>
                </div>
                <button class="close-modal" id="closeAnnouncementModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="modal-section">
                    <h3><ion-icon name="information-circle-outline"></ion-icon> Details</h3>
                    <div class="modal-grid">
                        <div class="modal-grid-item full-width">
                            <span class="item-label">Description / Content</span>
                            <div class="item-value content-box" id="modalAnnouncementContent" style="padding: 12px; background: var(--surface, #f8fafc); border-radius: 6px; border: 1px solid var(--border, #e2e8f0); margin-top: 4px; min-height: 80px;">Content goes here...</div>
                        </div>
                        <div class="modal-grid-item">
                            <span class="item-label">Organization</span>
                            <span class="item-value" id="modalOrgName">Org name</span>
                        </div>
                        <div class="modal-grid-item">
                            <span class="item-label">Submitted By</span>
                            <span class="item-value" id="modalAuthor">Author name</span>
                        </div>
                        <div class="modal-grid-item">
                            <span class="item-label">Target Audience</span>
                            <span class="item-value" id="modalAudience">Audience</span>
                        </div>
                        <div class="modal-grid-item">
                            <span class="item-label">Status</span>
                            <span class="item-value" id="modalStatus">Status</span>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <h3><ion-icon name="time-outline"></ion-icon> Schedule & Priority</h3>
                    <div class="modal-grid">
                        <div class="modal-grid-item">
                            <span class="item-label">Submitted Date</span>
                            <span class="item-value" id="modalSubmitDate">Date</span>
                        </div>
                        <div class="modal-grid-item">
                            <span class="item-label">Expiry Date</span>
                            <span class="item-value" id="modalExpiryDate">Date</span>
                        </div>
                        <div class="modal-grid-item">
                            <span class="item-label">Priority Level</span>
                            <span class="item-value" id="modalPriority">Priority</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="modal-btn outline" id="modalCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <div id="createAnnouncementModal" class="announcement-modal">
        <div class="announcement-modal-content announcement-form-modal">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h2>Create Announcement</h2>
                    <p class="modal-subtitle">Choose the audience and prepare the announcement for publication</p>
                </div>
                <button class="close-modal" id="closeCreateAnnouncementModal" type="button">&times;</button>
            </div>

            <form id="createAnnouncementForm" class="announcement-form">
                <div class="modal-body">
                    <div class="modal-section">
                        <h3><ion-icon name="create-outline"></ion-icon> Announcement Details</h3>
                        <div class="modal-grid announcement-form-grid">
                            <div class="modal-grid-item full-width">
                                <label class="item-label" for="createAnnouncementTitle">Title</label>
                                <input id="createAnnouncementTitle" class="form-input" type="text" placeholder="Enter announcement title">
                            </div>
                            <div class="modal-grid-item full-width">
                                <label class="item-label" for="createAnnouncementBody">Content</label>
                                <textarea id="createAnnouncementBody" class="form-input form-textarea" rows="5" placeholder="Write the announcement content here..."></textarea>
                            </div>
                            <div class="modal-grid-item">
                                <label class="item-label" for="createAnnouncementAudience">Audience</label>
                                <select id="createAnnouncementAudience" class="form-input">
                                    <option value="by_org">By Organization</option>
                                    <option value="all_org">All Organizations</option>
                                    <option value="students">Students</option>
                                    <option value="all">For All</option>
                                </select>
                            </div>
                            <div class="modal-grid-item" id="createAnnouncementOrgWrap">
                                <label class="item-label" for="createAnnouncementOrg">Organization</label>
                                <select id="createAnnouncementOrg" class="form-input">
                                    <option value="">Select organization</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option value="<?= (int)$org['OrgId'] ?>"><?= htmlspecialchars($org['OrgName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="modal-btn outline" type="button" id="cancelCreateAnnouncement">Cancel</button>
                    <button class="modal-btn primary" type="submit">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/admin/announcement.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/admin/dashboard.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/logout_confirm.js" defer></script>
    
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
