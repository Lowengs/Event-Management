
        function openDrawer(studentId, fullName) {
    console.log("Drawer opening for:", studentId, fullName);
    // Update the new Student Modal
    document.getElementById('modalStudentId').innerText = studentId;
    document.getElementById('modalStudentName').innerText = fullName;
    
    // Mock data for the modal display
    document.getElementById('modalStudentCourse').innerText = "Computer Science";
    document.getElementById('modalStudentYear').innerText = "2nd Year";
    document.getElementById('modalStudentSection').innerText = "CS-2A";
    document.getElementById('modalStudentEmail').innerText = fullName.toLowerCase().replace(/ /g, '.') + "@university.edu";
    document.getElementById('modalStudentContact').innerText = "+63 912 345 6789";

    const modal = document.getElementById('studentModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        console.log("Modal shown");
    } else {
        console.error("Modal not found in DOM");
    }
}

function closeDrawer() {
    const modal = document.getElementById('studentModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        console.log("Modal closed");
    }
}

// Make them available globally
window.openDrawer = openDrawer;
window.closeDrawer = closeDrawer;

document.addEventListener('DOMContentLoaded', () => {
    const studentModal = document.getElementById('studentModal');
    const closeBtn1 = document.getElementById('closeStudentModal');
    const closeBtn2 = document.getElementById('modalCloseStudentBtn');

    if(closeBtn1) closeBtn1.addEventListener('click', closeDrawer);
    if(closeBtn2) closeBtn2.addEventListener('click', closeDrawer);

    window.addEventListener('click', (e) => {
        if (e.target === studentModal) {
            closeDrawer();
        }
    });

    console.log("Students JS initialized");
});

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
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };

    set('modalStudentName', fullName);
    set('modalStudentUsername', s.username ? `@${s.username}` : '@' + fullName.toLowerCase().replace(/\s+/g, ''));
    set('modalStudentId', sId);
    set('modalStudentCourse', s.course || '—');
    set('modalStudentYearSection', `${s.year_level || '—'} - Section ${s.section || '—'}`);
    set('modalStudentOrg', s.OrgName || s.org || 'None');
    set('modalStudentOfficer', s.Position || s.officer_role || (s.is_officer == 1 ? 'Officer' : 'Student Member'));
    set('modalStudentEmail', s.Email || s.email || '—');
    set('modalStudentContact', s.phone || s.contact || '—');
    set('modalStudentAddress', s.Address || s.address || '—');
    set('modalStudentAiScore', s.ai_verification_score !== undefined && s.ai_verification_score !== null ? `${s.ai_verification_score}/100` : 'Not Evaluated');

    if (s.created_at) {
        const d = new Date(s.created_at);
        set('modalStudentJoined', d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
    } else {
        set('modalStudentJoined', '—');
    }

    // Status Badge
    const stBadge = document.getElementById('modalStudentStatusBadge');
    if (stBadge) {
        const st = (s.status || 'active').toLowerCase();
        stBadge.textContent = st.charAt(0).toUpperCase() + st.slice(1);
        stBadge.style.background = st === 'active' ? '#dcfce7' : '#fee2e2';
        stBadge.style.color = st === 'active' ? '#15803d' : '#b91c1c';
    }

    // Verification Badge
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

    // COR Status & Embedded Viewer
    const corStatusEl = document.getElementById('modalStudentCorStatus');
    const vs = (s.verification_status || 'pending').toLowerCase();
    if (corStatusEl) {
        if (vs === 'approved' || vs === 'ai_verified') {
            corStatusEl.innerHTML = '<span style="color:#15803d;font-weight:700;">Verified Enrollment</span>';
        } else if (vs === 'rejected') {
            corStatusEl.innerHTML = '<span style="color:#dc2626;font-weight:700;">Rejected Document</span>';
        } else {
            corStatusEl.innerHTML = '<span style="color:#b45309;font-weight:700;">Pending Review</span>';
        }
    }

    const corNone = document.getElementById('modalStudentCorNone');
    const corFrameWrap = document.getElementById('modalStudentCorFrameWrap');
    const corFrame = document.getElementById('modalStudentCorFrame');
    const corImg = document.getElementById('modalStudentCorImg');

    if (s.cor_document) {
        const corPath = s.cor_document.startsWith('http') || s.cor_document.startsWith('../../') ? s.cor_document : '../../' + s.cor_document.replace(/^\/+/, '');
        if (corNone) corNone.style.display = 'none';
        if (corFrameWrap) corFrameWrap.style.display = 'block';

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
        } catch(e) {
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