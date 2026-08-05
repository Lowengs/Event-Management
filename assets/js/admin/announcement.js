window.updateStatus = function(id, st) {
    if (!confirm('Are you sure you want to ' + (st === 'approved' ? 'approve' : 'reject') + ' this announcement?')) return;
    
    const fd = new FormData();
    fd.append('AnnouncementId', id);
    fd.append('Status', st);

    fetch('../../config/API/endpoints/index.php?action=osa_update_announcement_status', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showModal('Announcement ' + st + ' successfully.', 'success', 'Announcement Status', () => {
                location.reload();
            });
        } else {
            showModal('Action failed: ' + (data.message || 'Unknown error'), 'error', 'Error');
        }
    })
    .catch(err => {
        console.error(err);
        showModal('An error occurred while updating announcement status.', 'error', 'Error');
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const announcementModal = document.getElementById('announcementModal');
    const createAnnouncementModal = document.getElementById('createAnnouncementModal');
    const openCreateAnnouncementModal = document.getElementById('openCreateAnnouncementModal');
    const closeCreateAnnouncementModal = document.getElementById('closeCreateAnnouncementModal');
    const cancelCreateAnnouncement = document.getElementById('cancelCreateAnnouncement');
    const audienceSelect = document.getElementById('createAnnouncementAudience');
    const orgWrap = document.getElementById('createAnnouncementOrgWrap');
    const closeBtn1 = document.getElementById('closeAnnouncementModal');
    const closeBtn2 = document.getElementById('modalCloseBtn');

    function openCreateModal() {
        if (!createAnnouncementModal) return;
        createAnnouncementModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        if (!createAnnouncementModal) return;
        createAnnouncementModal.classList.remove('show');
        document.body.style.overflow = '';
    }

    function syncAudienceFields() {
        if (!audienceSelect || !orgWrap) return;
        const orgSelect = document.getElementById('createAnnouncementOrg');
        const needsOrg = audienceSelect.value === 'by_org';

        orgWrap.classList.toggle('hidden-field', !needsOrg);
        if (orgSelect) {
            orgSelect.disabled = !needsOrg;
            if (!needsOrg) {
                orgSelect.value = '';
            }
        }
    }

    if (openCreateAnnouncementModal) openCreateAnnouncementModal.addEventListener('click', openCreateModal);
    if (closeCreateAnnouncementModal) closeCreateAnnouncementModal.addEventListener('click', closeCreateModal);
    if (cancelCreateAnnouncement) cancelCreateAnnouncement.addEventListener('click', closeCreateModal);
    if (audienceSelect) {
        audienceSelect.addEventListener('change', syncAudienceFields);
        syncAudienceFields();
    }

    const createAnnouncementForm = document.getElementById('createAnnouncementForm');
    if (createAnnouncementForm) {
        createAnnouncementForm.addEventListener('submit', (event) => {
            event.preventDefault();

            const title = document.getElementById('createAnnouncementTitle')?.value.trim() || '';
            const body = document.getElementById('createAnnouncementBody')?.value.trim() || '';
            const audience = audienceSelect ? audienceSelect.value : 'all_org';
            const orgId = document.getElementById('createAnnouncementOrg')?.value || '';

            if (!title || !body) {
                showModal('Title and content are required.', 'warning', 'Validation Error');
                return;
            }

            if (audience === 'by_org' && !orgId) {
                showModal('Please choose an organization.', 'warning', 'Validation Error');
                return;
            }

            const fd = new FormData();
            fd.append('Title', title);
            fd.append('Body', body);
            fd.append('Audience', audience);
            fd.append('OrgId', orgId);

            fetch('../../config/API/endpoints/index.php?action=create_osa_announcement', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showModal(data.message || 'Announcement created successfully.', 'success', 'Success', () => {
                        closeCreateModal();
                        createAnnouncementForm.reset();
                        syncAudienceFields();
                        location.reload();
                    });
                } else {
                    showModal(data.message || 'Unable to create announcement.', 'error', 'Error');
                }
            })
            .catch(err => {
                console.error(err);
                showModal('An error occurred while creating the announcement.', 'error', 'Error');
            });
        });
    }
    
    window.viewAnnouncement = function(ann) {
        document.getElementById('modalAnnouncementTitle').innerText = ann.Title || 'No Title';
        document.getElementById('modalAnnouncementCategory').innerText = ann.Category || 'General Notice';
        document.getElementById('modalAnnouncementContent').innerText = ann.Body || 'No Content';
        document.getElementById('modalOrgName').innerText = ann.OrgName || 'Unknown Org';
        document.getElementById('modalAuthor').innerText = 'N/A'; // Or use author if available
        document.getElementById('modalAudience').innerText = ann.Audience || 'All Students';
        
        let pillClass = 'pending';
        let statusClass = (ann.Status || 'pending').toLowerCase();
        if (statusClass === 'approved') pillClass = 'approved';
        else if (statusClass === 'rejected' || statusClass === 'failed') pillClass = 'declined';
        
        document.getElementById('modalStatus').innerHTML = '<span class="pill status ' + pillClass + '">' + (statusClass.charAt(0).toUpperCase() + statusClass.slice(1)) + '</span>';
        document.getElementById('modalSubmitDate').innerText = ann.DatePosted || 'N/A';
        document.getElementById('modalExpiryDate').innerText = ann.ExpirationDate || 'N/A';
        document.getElementById('modalPriority').innerHTML = '<span class="pill priority medium">MEDIUM</span>';

        announcementModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    function closeModal() {
        announcementModal.classList.remove('show');
        document.body.style.overflow = '';
    }

    if(closeBtn1) closeBtn1.addEventListener('click', closeModal);
    if(closeBtn2) closeBtn2.addEventListener('click', closeModal);

    window.addEventListener('click', (e) => {
        if (e.target === announcementModal) {
            closeModal();
        }

        if (e.target === createAnnouncementModal) {
            closeCreateModal();
        }
    });
});

window.deleteAnnouncement = function(id) {
    if (!confirm('Delete this announcement? This action cannot be undone.')) return;

    const fd = new FormData();
    fd.append('AnnouncementId', id);

    fetch('../../config/API/endpoints/index.php?action=delete_osa_announcement', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                if (typeof showModal === 'function') {
                    showModal(d.message || 'Deleted successfully.', 'success', 'Success', () => {
                        window.location.reload();
                    });
                } else {
                    alert(d.message || 'Deleted successfully.');
                    window.location.reload();
                }
            } else {
                if (typeof showModal === 'function') {
                    showModal(d.message || 'Delete failed', 'error', 'Error');
                } else {
                    alert('Delete failed: ' + (d.message || 'Unknown error'));
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (typeof showModal === 'function') {
                showModal('Delete failed due to network or server error.', 'error', 'Error');
            } else {
                alert('Delete failed due to network or server error.');
            }
        });
};