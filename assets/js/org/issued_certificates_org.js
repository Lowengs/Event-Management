/* ── Extracted from organization/issued_certificates_org.php ── */
function viewCert(src) {
    document.getElementById('viewerImg').src = src;
    document.getElementById('viewerOverlay').classList.add('open');
}
function closeViewer() {
    document.getElementById('viewerOverlay').classList.remove('open');
    setTimeout(() => { document.getElementById('viewerImg').src = ''; }, 200);
}