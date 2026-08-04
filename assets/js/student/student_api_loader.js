document.addEventListener('DOMContentLoaded', () => {

    // ── Student Profile Dashboard ─────────────────────────────────────
    if (window.location.pathname.includes('profile-dashboard.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_student_profile')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const p = data.profile;
                const st = data.stats;
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

                // ── Stats ──
                set('studentRegCount',  st.registrations ?? 0);
                set('studentAttCount',  st.attendance ?? 0);
                set('studentCertCount', st.certificates ?? 0);

                // ── Profile picture in sidebar & topbar ──
                const photoUrl = p.profile_photo_url ?? '';
                if (photoUrl) {
                    document.querySelectorAll('[data-student-photo]').forEach(el => {
                        if (el.tagName === 'IMG') {
                            el.src = photoUrl;
                        } else {
                            el.innerHTML = `<img src="${photoUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="Profile">`;
                        }
                    });
                }
            });
    }

    // ── Student Events page ───────────────────────────────────────────
    // Events are still server-rendered for SEO; API available if needed.

    // ── Student Organizations page ────────────────────────────────────
    // Organizations are still server-rendered; API available if needed.
});
