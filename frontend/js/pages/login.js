// ============================================
// LOGIN PAGE
// ============================================
registerRoute('/login', renderLoginPage);

async function renderLoginPage() {
  const app = document.getElementById('app');
  app.innerHTML = `
    <div class="login-page">
      <div class="login-card">
        <div class="login-logo">
          <div class="login-logo-icon">⚑</div>
          <h1>NFSU CTF</h1>
          <p>Capture The Flag Platform</p>
        </div>
        <form id="login-form">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" class="form-input" id="login-username" placeholder="Enter team username" autocomplete="username" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" class="form-input" id="login-password" placeholder="Enter password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-full" id="login-btn">Login</button>
          <p class="text-center text-muted mt-16 text-sm">
            <a href="#/admin/login">Admin Login →</a>
          </p>
        </form>
      </div>
    </div>
  `;

  document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.textContent = 'Logging in...';

    try {
      const username = document.getElementById('login-username').value.trim();
      const password = document.getElementById('login-password').value;
      const data = await apiPost('/login', { username, password });
      setUser(data.team);
      initWebSocket();
      showToast('Login successful!', 'success');
      navigateTo('/dashboard');
    } catch (err) {
      showToast(err.message || 'Login failed', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Login';
    }
  });
}
