// ============================================
// ERROR & MAINTENANCE PAGES
// ============================================
registerRoute('/404', render404Page);

function render404Page() {
    document.getElementById('app').innerHTML = `
    <div class="error-page">
      <div class="error-code">404</div>
      <div class="error-message">Page not found</div>
      <a href="#/dashboard" class="btn btn-primary mt-24">Back to Dashboard</a>
    </div>
  `;
}
