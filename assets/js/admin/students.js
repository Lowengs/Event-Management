/**
 * NAAP System - Students Management Modal & Drawer Helper
 */
function openDrawer(studentId, fullName) {
    const elId = document.getElementById('modalStudentId');
    if (elId) elId.textContent = studentId;
    const elName = document.getElementById('modalStudentName');
    if (elName) elName.textContent = fullName;
    const modal = document.getElementById('studentModal');
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeDrawer() {
    const modal = document.getElementById('studentModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

window.openDrawer = openDrawer;
window.closeDrawer = closeDrawer;

function openStudentModal(dataOrId, name, course, year, section, email, phone, org, status, photoStr) {
    let s = {};
    if (typeof dataOrId === 'object' && dataOrId !== null) {
        s = dataOrId;
    } else {
        s = {
            student_id: dataOrId,
            name: name,
            course: course,
            year_level: year,
            section: section,
            Email: email,
            phone: phone,
            OrgName: org,
            status: status,
            profile_photo: photoStr
        };
    }

    const fullName = s.name || [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ') || 'Student';
    const sId = s.student_id || '—';

    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '—';
    };

    set('modalStudentName', fullName);
    set('modalStudentUsername', s.username ? `@${s.username}` : '@' + fullName.toLowerCase().replace(/\s+/g, ''));
    set('modalStudentId', sId);
    set('modalStudentCourse', s.course || '—');
    set('modalStudentYearSection', `${s.year_level || '—'} - Section ${s.section || '—'}`);
    set('modalStudentOrg', s.OrgName || s.org || 'None');
    set('modalStudentOfficer', s.Position || s.officer_role || (s.is_officer == 1 ? 'Officer' : 'Student Member'));
    set('modalStudentEmail', s.Email || s.email || '—');

    // Format phone to local standard 09XXXXXXXXX
    let rawPhone = s.phone || s.contact || '';
    let digits = rawPhone.replace(/\D/g, '');
    if (digits.startsWith('63')) digits = digits.slice(2);
    else if (digits.startsWith('0')) digits = digits.slice(1);
    const displayPhone = (digits.length === 10 && digits.startsWith('9')) ? ('0' + digits) : (rawPhone || '—');
    set('modalStudentContact', displayPhone);

    set('modalStudentAddress', s.Address || s.address || '—');
    const detailsStr = (typeof s.ai_verification_details === 'string' ? s.ai_verification_details : JSON.stringify(s.ai_verification_details || ''));
    const isManuallyApproved = (s.status === 'active' || s.verification_status === 'approved') && (
        detailsStr.toLowerCase().includes('manual') ||
        detailsStr.toLowerCase().includes('pending organization')
    );
    if (isManuallyApproved) {
        set('modalStudentAiScore', 'Manual Approval (Officer Reviewed)');
    } else {
        set('modalStudentAiScore', (s.ai_verification_score !== undefined && s.ai_verification_score !== null) ? `${s.ai_verification_score}/100` : 'Not Evaluated');
    }

    if (s.created_at) {
        const d = new Date(s.created_at);
        set('modalStudentJoined', d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
    } else {
        set('modalStudentJoined', '—');
    }

    // Status Badges
    const stBadge = document.getElementById('modalStudentStatusBadge');
    if (stBadge) {
        const vsRaw = (s.verification_status || '').toLowerCase();
        const stRaw = (s.status || '').toLowerCase();
        const isPending = (vsRaw === 'pending' || vsRaw === 'needs_org_review' || stRaw === 'pending');
        const st = isPending ? 'Pending' : (stRaw === 'active' ? 'Active' : (stRaw || 'Pending'));
        stBadge.textContent = st;
        stBadge.style.background = st === 'Active' ? '#dcfce7' : '#fef3c7';
        stBadge.style.color = st === 'Active' ? '#15803d' : '#b45309';
    }

    const verifBadge = document.getElementById('modalStudentVerifBadge');
    if (verifBadge) {
        const vs = (s.verification_status || 'pending').toLowerCase();
        verifBadge.textContent = vs.replace(/_/g, ' ').toUpperCase();
        verifBadge.style.background = (vs === 'approved' || vs === 'ai_verified') ? '#e0e7ff' : (vs === 'rejected' ? '#fee2e2' : '#fef3c7');
        verifBadge.style.color = (vs === 'approved' || vs === 'ai_verified') ? '#4338ca' : (vs === 'rejected' ? '#b91c1c' : '#92400e');
    }

    // Photo
    const photoEl = document.getElementById('modalStudentPhoto');
    if (photoEl) {
        const p = s.profile_photo || photoStr;
        photoEl.src = p ? (p.startsWith('http') || p.startsWith('../../') ? p : '../../' + p.replace(/^\/+/, '')) : '../../assets/img/philsca.png';
    }

    // COR Status
    const corStatusEl = document.getElementById('modalStudentCorStatus');
    const vs = (s.verification_status || 'pending').toLowerCase();
    if (corStatusEl) {
        if (vs === 'approved' || vs === 'ai_verified') {
            corStatusEl.innerHTML = '<span style="color:#15803d;font-weight:700;">Verified Enrollment</span>';
        } else if (vs === 'rejected') {
            corStatusEl.innerHTML = '<span style="color:#dc2626;font-weight:700;">Rejected Document</span>';
        } else if (vs === 'needs_org_review') {
            corStatusEl.innerHTML = '<span style="color:#b45309;font-weight:700;">Flagged for Officer Review</span>';
        } else {
            corStatusEl.innerHTML = '<span style="color:#b45309;font-weight:700;">Pending Review</span>';
        }
    }

    // COR Document Preview & Download Link
    const corNone = document.getElementById('modalStudentCorNone');
    const corFrameWrap = document.getElementById('modalStudentCorFrameWrap');
    const corFrame = document.getElementById('modalStudentCorFrame');
    const corImg = document.getElementById('modalStudentCorImg');
    const corLink = document.getElementById('modalStudentCorLink');

    if (s.cor_document) {
        const corPath = s.cor_document.startsWith('http') || s.cor_document.startsWith('../../') ? s.cor_document : '../../' + s.cor_document.replace(/^\/+/, '');
        if (corNone) corNone.style.display = 'none';
        if (corFrameWrap) corFrameWrap.style.display = 'block';
        if (corLink) {
            corLink.href = corPath;
            corLink.style.display = 'inline-flex';
        }

        const isPdf = corPath.toLowerCase().endsWith('.pdf');
        if (isPdf) {
            if (corFrame) { corFrame.src = corPath; corFrame.style.display = 'block'; }
            if (corImg) { corImg.src = ''; corImg.style.display = 'none'; }
        } else {
            if (corImg) { corImg.src = corPath; corImg.style.display = 'block'; }
            if (corFrame) { corFrame.src = ''; corFrame.style.display = 'none'; }
        }
    } else {
        if (corNone) corNone.style.display = 'inline';
        if (corFrameWrap) corFrameWrap.style.display = 'none';
        if (corFrame) corFrame.src = '';
        if (corImg) corImg.src = '';
        if (corLink) corLink.style.display = 'none';
    }

    // AI Details
    const aiDetailsWrap = document.getElementById('modalStudentAiDetailsWrap');
    const aiDetailsEl = document.getElementById('modalStudentAiDetails');
    if (s.ai_verification_details) {
        try {
            const parsed = JSON.parse(s.ai_verification_details);
            const text = Array.isArray(parsed) ? parsed.join(' • ') : String(parsed);
            if (aiDetailsEl) aiDetailsEl.textContent = text;
            if (aiDetailsWrap) aiDetailsWrap.style.display = 'block';
        } catch (e) {
            if (aiDetailsEl) aiDetailsEl.textContent = String(s.ai_verification_details);
            if (aiDetailsWrap) aiDetailsWrap.style.display = 'block';
        }
    } else {
        if (aiDetailsWrap) aiDetailsWrap.style.display = 'none';
    }

    const modal = document.getElementById('studentModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeStudentModalFunc() {
    const modal = document.getElementById('studentModal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

window.openStudentModal = openStudentModal;
window.closeStudentModalFunc = closeStudentModalFunc;

document.addEventListener('DOMContentLoaded', () => {
    const closeBtn1 = document.getElementById('closeStudentModal');
    const closeBtn2 = document.getElementById('modalCloseStudentBtn');
    const modal = document.getElementById('studentModal');
    if (closeBtn1) closeBtn1.addEventListener('click', closeStudentModalFunc);
    if (closeBtn2) closeBtn2.addEventListener('click', closeStudentModalFunc);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeStudentModalFunc();
        });
    }
});