// ============================================
// INFO / RULES PAGE
// ============================================
registerRoute('/info', renderInfoPage);

async function renderInfoPage() {
    const app = document.getElementById('app');
    showLoading(app);

    try {
        const data = await apiGet('/info');
        const rules = data.content?.rules || 'Rules not available';
        const info = data.content?.info || '';

        app.innerHTML = `
      <div class="page-header">
        <h1 class="page-title">Rules & Information</h1>
        <p class="page-subtitle">Please read carefully before competing</p>
      </div>
      ${info ? `<div class="card mb-24"><div class="markdown-content">${renderMarkdown(info)}</div></div>` : ''}
      <div class="card">
        <div class="markdown-content">${renderMarkdown(rules)}</div>
      </div>
    `;
    } catch (err) {
        app.innerHTML = `<div class="error-page"><div class="error-message">${escapeHtml(err.message)}</div></div>`;
    }
}
