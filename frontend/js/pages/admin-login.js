// ============================================
// ADMIN LOGIN PAGE
// ============================================
registerRoute('/admin/login', renderAdminLoginPage);

async function renderAdminLoginPage() {
  const app = document.getElementById('app');
  app.innerHTML = `
    <div class="login-page">
      <div class="login-card">
        <div class="login-logo">
          <div class="login-logo-icon" style="background: linear-gradient(135deg, #ff4757, #ffa502);">⚙</div>
          <h1>Admin Panel</h1>
          <p>CTF Administration Dashboard</p>
        </div>
        <form id="admin-login-form">
          <div class="form-group">
            <label class="form-label">Admin Username</label>
            <input type="text" class="form-input" id="admin-username" placeholder="Enter admin username" autocomplete="username" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" class="form-input" id="admin-password" placeholder="Enter password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-full" id="admin-login-btn">Admin Login</button>
          <p class="text-center text-muted mt-16 text-sm">
            <a href="#/login">← Team Login</a>
          </p>
        </form>
      </div>
    </div>
  `;

  document.getElementById('admin-login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('admin-login-btn');
    btn.disabled = true;
    btn.textContent = 'Logging in...';

    try {
      const username = document.getElementById('admin-username').value.trim();
      const password = document.getElementById('admin-password').value;
      const data = await apiPost('/admin/login', { username, password });
      setUser(data.team);
      showToast('Admin login successful!', 'success');
      navigateTo('/admin/dashboard');
    } catch (err) {
      showToast(err.message || 'Login failed', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Admin Login';
    }
  });
}
