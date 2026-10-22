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
        <?php include '_osa_sidebar.php'; ?>

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
                        <option value="">All Organizations</option>
                        <option value="Office of Student Affairs">Office of Student Affairs (OSA)</option>
                        <?php foreach ($organizations as $org): ?>
                            <option value="<?= htmlspecialchars($org['OrgName']) ?>"><?= htmlspecialchars($org['OrgName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="catFilter">
                        <ion-icon name="pricetag-outline"></ion-icon>
                        Category
                    </label>
                    <select id="catFilter">
                        <option value="">All Categories</option>
                        <option value="General Notice">General Notice</option>
                        <option value="Event Announcement">Event Announcement</option>
                        <option value="Meeting / Assembly">Meeting / Assembly</option>
                        <option value="Deadline / Requirement">Deadline / Requirement</option>
                        <option value="Emergency / Advisory">Emergency / Advisory</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="statusFilter">
                        <ion-icon name="funnel-outline"></ion-icon>
                        Status
                    </label>
                    <select id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending Review</option>
                        <option value="approved">Approved</option>
                        <option value="declined">Declined</option>
                    </select>
                </div>

                <div class="filter-item search-block">
                    <label for="searchAnnouncement">
                        <ion-icon name="search-outline"></ion-icon>
                        Search
                    </label>
                    <div class="search-input">
                        <ion-icon name="search-outline"></ion-icon>
                        <input id="searchAnnouncement" type="text" placeholder="Search by title, content, or publisher...">
                    </div>
                </div>
            </section>

            <section class="announcement-table-wrap">
                <div class="table-meta-bar">
                    <div class="meta-left">
                        <strong>Announcements Record</strong> &bull; Review &amp; Dispatch Pipeline
                    </div>
                    <div class="meta-right">
                        Showing <strong id="annVisibleCount"><?= count($announcements) ?></strong> of <?= count($announcements) ?> announcements
                    </div>
                </div>

                <table class="announcement-table">
                    <thead>
                        <tr>
                            <th style="width: 44px; text-align: center;">#</th>
                            <th>Announcement Details</th>
                            <th>Category</th>
                            <th>Publisher</th>
                            <th>Audience</th>
                            <th>Date Posted</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 20px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="announcementTableBody">
                    <?php if (empty($announcements)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 2.5rem; color: #64748b;">No announcements found in the database.</td></tr>
                    <?php else: ?>
                        <?php 
                        $rowIdx = 1;
                        foreach($announcements as $ann): 
                            $datePosted = !empty($ann['DatePosted']) ? new DateTime($ann['DatePosted']) : null;
                            $dateStr = $datePosted ? $datePosted->format('M d, Y') : 'N/A';
                            
                            $descSnippet = strlen($ann['Body'] ?? '') > 55 ? substr($ann['Body'], 0, 55) . '...' : (empty($ann['Body']) ? 'No preview text provided' : $ann['Body']);
                            $audienceCode = strtolower(trim($ann['Audience'] ?? 'all_org'));
                            $audienceLabel = match ($audienceCode) {
                                'by_org' => 'By Organization',
                                'all_org' => 'All Organizations',
                                'students' => 'Students Only',
                                'all' => 'Public / Campus',
                                default => $ann['Audience'] ?? 'All Organizations',
                            };

                            $rawStatus = strtolower($ann['Status'] ?? 'pending');
                            if ($rawStatus === 'approved') {
                                $statusClass = 'approved';
                                $statusLabel = 'Approved';
                            } elseif (in_array($rawStatus, ['rejected', 'failed', 'declined'])) {
                                $statusClass = 'declined';
                                $statusLabel = 'Declined';
                            } else {
                                $statusClass = 'pending';
                                $statusLabel = 'Pending';
                            }

                            $categoryName = !empty($ann['Category']) ? $ann['Category'] : 'General Notice';
                            $orgDisplayName = !empty($ann['OrgName']) ? $ann['OrgName'] : 'Office of Student Affairs';

                            // Search index tokens
                            $searchTokens = strtolower($ann['Title'] . ' ' . ($ann['Body'] ?? '') . ' ' . $categoryName . ' ' . $orgDisplayName . ' ' . $audienceLabel . ' ' . $statusLabel);
                        ?>
                        <tr class="announcement-row" 
                            data-search="<?= htmlspecialchars($searchTokens) ?>" 
                            data-org="<?= htmlspecialchars(strtolower($orgDisplayName)) ?>"
                            data-category="<?= htmlspecialchars(strtolower($categoryName)) ?>"
                            data-status="<?= htmlspecialchars($statusClass) ?>">
                            <td class="ann-num"><?= $rowIdx++ ?></td>
                            <td>
                                <div class="ann-details">
                                    <span class="title-main"><?= htmlspecialchars($ann['Title']) ?></span>
                                    <span class="title-sub"><?= htmlspecialchars(trim($descSnippet)) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="cat-pill">
                                    <ion-icon name="pricetag-outline"></ion-icon>
                                    <?= htmlspecialchars($categoryName) ?>
                                </span>
                            </td>
                            <td>
                                <span class="org-chip" title="<?= htmlspecialchars($orgDisplayName) ?>">
                                    <ion-icon name="business-outline"></ion-icon>
                                    <?= htmlspecialchars($orgDisplayName) ?>
                                </span>
                            </td>
                            <td>
                                <span class="audience-pill">
                                    <ion-icon name="people-outline"></ion-icon>
                                    <?= htmlspecialchars($audienceLabel) ?>
                                </span>
                            </td>
                            <td>
                                <span class="date-main">
                                    <ion-icon name="calendar-outline"></ion-icon>
                                    <?= $dateStr ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= $statusClass ?>">
                                    <span class="status-dot"></span>
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-set" style="justify-content: flex-end; padding-right: 4px;">
                                    <button type="button" class="action-btn view" title="View details" aria-label="View announcement" onclick='viewAnnouncement(<?= json_encode($ann) ?>)'>
                                        <ion-icon name="eye-outline"></ion-icon>
                                    </button>
                                    <?php if ($statusClass === 'pending'): ?>
                                    <button type="button" class="action-btn approve" title="Approve announcement" aria-label="Approve announcement" onclick="updateStatus(<?= (int)$ann['AnnouncementId'] ?>, 'approved')">
                                        <ion-icon name="checkmark-outline"></ion-icon>
                                    </button>
                                    <button type="button" class="action-btn reject" title="Decline announcement" aria-label="Decline announcement" onclick="updateStatus(<?= (int)$ann['AnnouncementId'] ?>, 'rejected')">
                                        <ion-icon name="close-outline"></ion-icon>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="action-btn delete" title="Delete announcement" aria-label="Delete announcement" onclick="deleteAnnouncement(<?= (int)$ann['AnnouncementId'] ?>)">
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="emptyFilterRow" style="display: none;">
                            <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: #64748b;">
                                <ion-icon name="search-outline" style="font-size: 2rem; color: #94a3b8; display: block; margin: 0 auto 8px;"></ion-icon>
                                <strong>No matching announcements found.</strong>
                                <p style="font-size: 12.5px; margin: 4px 0 0 0; color: #94a3b8;">Try clearing or adjusting your search keyword and filter criteria.</p>
                            </td>
                        </tr>
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

    <script>
    // Live announcement search and multi-criteria filter
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchAnnouncement');
        const orgFilter = document.getElementById('orgFilter');
        const catFilter = document.getElementById('catFilter');
        const statusFilter = document.getElementById('statusFilter');
        const countEl = document.getElementById('annVisibleCount');
        const emptyRow = document.getElementById('emptyFilterRow');
        const rows = document.querySelectorAll('.announcement-row');

        function applyAnnouncementFilters() {
            const query = (searchInput?.value || '').toLowerCase().trim();
            const selectedOrg = (orgFilter?.value || '').toLowerCase().trim();
            const selectedCat = (catFilter?.value || '').toLowerCase().trim();
            const selectedStatus = (statusFilter?.value || '').toLowerCase().trim();

            let visibleCount = 0;
            rows.forEach(row => {
                const searchData = (row.getAttribute('data-search') || '').toLowerCase();
                const orgData = (row.getAttribute('data-org') || '').toLowerCase();
                const catData = (row.getAttribute('data-category') || '').toLowerCase();
                const statusData = (row.getAttribute('data-status') || '').toLowerCase();

                const matchQuery = !query || searchData.includes(query);
                const matchOrg = !selectedOrg || orgData.includes(selectedOrg);
                const matchCat = !selectedCat || catData.includes(selectedCat);
                const matchStatus = !selectedStatus || statusData === selectedStatus;

                if (matchQuery && matchOrg && matchCat && matchStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countEl) countEl.textContent = visibleCount;
            if (emptyRow) {
                emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyAnnouncementFilters);
        if (orgFilter) orgFilter.addEventListener('change', applyAnnouncementFilters);
        if (catFilter) catFilter.addEventListener('change', applyAnnouncementFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyAnnouncementFilters);
    });
    </script>
</body>
</html>
