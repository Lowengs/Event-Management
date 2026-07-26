 document.addEventListener('DOMContentLoaded', () => {
            const navItems = document.querySelectorAll('.nav-item');

            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = item.getAttribute('data-target');
                    switchTab(targetId);
                });
            });
        });

        function switchTab(targetId) {
            // Update active state on nav links
            document.querySelectorAll('.nav-item').forEach(nav => {
                if (nav.getAttribute('data-target') === targetId) {
                    nav.classList.add('active');
                } else {
                    nav.classList.remove('active');
                }
            });

            // Update active state on content sections
            document.querySelectorAll('.content-section').forEach(section => {
                if (section.id === targetId) {
                    section.classList.add('active');
                } else {
                    section.classList.remove('active');
                }
            });
        }

let eventDetailsBodyOverflow = '';

        function openEventDetailsModal(data) {
            document.getElementById('detailsModalTitle').textContent = data.title || 'Event';
            document.getElementById('detailsModalOrg').textContent = data.org || 'NAAP';
            document.getElementById('detailsModalStatus').textContent = data.status || 'Upcoming';
            document.getElementById('detailsModalDate').textContent = data.date || 'TBA';
            document.getElementById('detailsModalTime').textContent = data.time || 'TBA';
            document.getElementById('detailsModalLocation').textContent = data.location || 'TBA';
            document.getElementById('detailsModalDescription').textContent = data.description || 'No event description available.';

            const modal = document.getElementById('eventDetailsModal');
            eventDetailsBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeEventDetailsModal() {
            const modal = document.getElementById('eventDetailsModal');
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = eventDetailsBodyOverflow;
        }

        document.getElementById('eventDetailsModal')?.addEventListener('click', function (e) {
            if (e.target === this) closeEventDetailsModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('eventDetailsModal')?.classList.contains('show')) {
                closeEventDetailsModal();
            }
        });