
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

/* ── Extracted inline scripts ── */
function openStudentModal(id, name, course, year, section, email, phone, org, status, photoStr) {
        document.getElementById('modalStudentId').textContent      = id      || '—';
        document.getElementById('modalStudentName').textContent    = name    || '—';
        document.getElementById('modalStudentCourse').textContent  = course  || '—';
        document.getElementById('modalStudentYear').textContent    = year    || '—';
        document.getElementById('modalStudentSection').textContent = section || '—';
        document.getElementById('modalStudentEmail').textContent   = email   || '—';
        document.getElementById('modalStudentContact').textContent = phone   || '—';
        document.getElementById('modalStudentOrg').textContent     = org     || 'None';
        
        const photoEl = document.getElementById('modalStudentPhoto');
        if (photoEl) {
            photoEl.src = photoStr ? '../../' + photoStr : '../../assets/img/philsca.png';
        }
        
        document.getElementById('studentModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeStudentModalFunc() {
        document.getElementById('studentModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    document.getElementById('closeStudentModal').addEventListener('click', closeStudentModalFunc);
    document.getElementById('modalCloseStudentBtn').addEventListener('click', closeStudentModalFunc);
    document.getElementById('studentModal').addEventListener('click', function(e) {
        if (e.target === this) closeStudentModalFunc();
    });