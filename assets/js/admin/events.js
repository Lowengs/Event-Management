 document.addEventListener('DOMContentLoaded', () => {
      const mainModal = document.getElementById('eventModal');
      const closeBtns = [document.getElementById('closeEventModal'), document.getElementById('modalCloseBtn')];
      const viewButtons = document.querySelectorAll('.iconBtn.view');
      
      const docsModal = document.getElementById('docsModal');
      const closeDocsBtns = [document.getElementById('closeDocsModal'), document.getElementById('docsModalCloseBtn')];
      const docsButtons = document.querySelectorAll('.iconBtn.docs');
  
      viewButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
          const row = e.target.closest('tr');
          if(!row) return;
          
          // Extract data
          const eventName = row.querySelector('.eventName')?.innerText || '';
          const eventSub = row.querySelector('.eventSub')?.innerText || '';
          const orgName = row.querySelector('.orgCell span:last-child')?.innerText || '';
          
          const metaCells = row.querySelectorAll('.metaCell span');
          const date = metaCells[0] ? metaCells[0].innerText : '';
          const time = metaCells[1] ? metaCells[1].innerText : '';
          const location = metaCells[2] ? metaCells[2].innerText : '';
          
          const statusHTML = row.querySelector('td:nth-child(6)')?.innerHTML || '';
  
          // Set data to modal
          document.getElementById('modalEventTitle').innerText = eventName;
          document.getElementById('modalEventSub').innerText = eventSub;
          document.getElementById('modalOrgName').innerText = orgName;
          document.getElementById('modalDate').innerText = date;
          document.getElementById('modalTime').innerText = time;
          document.getElementById('modalLocation').innerText = location;
          document.getElementById('modalStatus').innerHTML = statusHTML;
  
          // Display modal
          mainModal.classList.add('show');
          document.body.style.overflow = 'hidden';
        });
      });
  
      closeBtns.forEach(btn => {
        if(btn) {
          btn.addEventListener('click', () => {
            mainModal.classList.remove('show');
            document.body.style.overflow = '';
          });
        }
      });
  
      docsButtons.forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const row = e.target.closest('tr');
          if(!row) return;

          const eventId = btn.getAttribute('data-event-id') || row.dataset.eventId || '';
          const eventName = row.querySelector('.eventName')?.innerText || 'Event';
          const eventSub = row.querySelector('.eventSub')?.innerText || 'Attachments';

          document.getElementById('docsModalTitle').innerText = eventName;
          document.getElementById('docsModalSub').innerText = eventSub + ' - Documentation';
          
          const docsList = document.getElementById('docsAttachmentList');
          if (docsList) {
             docsList.innerHTML = '<p style="color:var(--text-muted);font-size:0.9rem;">Loading documents...</p>';
          }

          if (eventId) {
            try {
               const res = await fetch(`../../config/API/get_event_documents.php?event_id=${eventId}`);
               const data = await res.json();
               if(data.success) {
                   if(data.files.length === 0) {
                      docsList.innerHTML = '<p style="color:var(--text-muted);font-size:0.9rem;">No documents submitted for this event.</p>';
                   } else {
                      let html = '';
                      data.files.forEach(f => {
                         const fName = f.FileName || f.DocumentType || 'Document';
                         const ext = f.FilePath.split('.').pop().toLowerCase();
                         let icon = 'document-text-outline';
                         if(ext==='pdf') icon = 'document-outline';
                         else if(['png','jpg','jpeg'].includes(ext)) icon = 'image-outline';
                         
                         let typeLabel = f.DocumentType==='EventProposal'?'Event Proposal' : (f.DocumentType==='EventProgramFlow'?'Program Flow' : 'Supporting Document');
                         
                         html += `
                           <div class="attachment-item docs-item" onclick="window.open('../../${f.FilePath}', '_blank')">
                             <div class="attachment-icon"><ion-icon name="${icon}"></ion-icon></div>
                             <div class="attachment-info">
                               <div class="attachment-name">${fName}</div>
                               <div class="attachment-size">${typeLabel}</div>
                             </div>
                             <button class="iconBtn" title="Download"><ion-icon name="download-outline"></ion-icon></button>
                           </div>
                         `;
                      });
                      docsList.innerHTML = html;
                   }
               } else {
                   docsList.innerHTML = `<p style="color:var(--danger);font-size:0.9rem;">Error: ${data.message}</p>`;
               }
            } catch(e) {
               docsList.innerHTML = '<p style="color:var(--danger);font-size:0.9rem;">Failed to load documents.</p>';
            }
          }

          docsModal.classList.add('show');
          document.body.style.overflow = 'hidden';
        });
      });

      closeDocsBtns.forEach(btn => {
        if(btn) {
          btn.addEventListener('click', () => {
            docsModal.classList.remove('show');
            document.body.style.overflow = '';
          });
        }
      });

      // Close when clicking outside of modal
      window.addEventListener('click', (e) => {
        if (e.target === mainModal) {
          mainModal.classList.remove('show');
          document.body.style.overflow = '';
        }
        if (e.target === docsModal) {
          docsModal.classList.remove('show');
          document.body.style.overflow = '';
        }
      });
    });