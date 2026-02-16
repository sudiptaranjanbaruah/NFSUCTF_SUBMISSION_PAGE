// ============================================
// API Client
// ============================================
const API_BASE = '/api';

// Auth is now cookie-based (httpOnly). We only store user info for UI display.
function isLoggedIn() {
    return !!getUser();
}

function clearAuth() {
    localStorage.removeItem('ctf_user');
}

function getUser() {
    try {
        return JSON.parse(localStorage.getItem('ctf_user'));
    } catch { return null; }
}

function setUser(user) {
    localStorage.setItem('ctf_user', JSON.stringify(user));
}

async function api(endpoint, options = {}) {
    const headers = { ...options.headers };
    // Only set Content-Type for non-FormData bodies
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const config = { ...options, headers, credentials: 'same-origin' };

    try {
        const res = await fetch(`${API_BASE}${endpoint}`, config);

        if (res.status === 401) {
            clearAuth();
            if (!window.location.hash.includes('login')) {
                showToast('Session expired. Please login again.', 'warning');
                setTimeout(() => { window.location.hash = '#/login'; }, 1000);
            }
            throw new Error('Unauthorized');
        }

        // Handle CSV downloads
        if (res.headers.get('content-type')?.includes('text/csv')) {
            return { csv: await res.text(), ok: true };
        }

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || `HTTP ${res.status}`);
        }
        return data;
    } catch (err) {
        if (err.message !== 'Unauthorized') {
            console.error('API Error:', err);
        }
        throw err;
    }
}

async function apiGet(endpoint) {
    return api(endpoint, { method: 'GET' });
}

async function apiPost(endpoint, body) {
    return api(endpoint, { method: 'POST', body: JSON.stringify(body) });
}

async function apiPut(endpoint, body) {
    return api(endpoint, { method: 'PUT', body: JSON.stringify(body) });
}

async function apiDelete(endpoint) {
    return api(endpoint, { method: 'DELETE' });
}

async function apiUpload(endpoint, formData) {
    return api(endpoint, { method: 'POST', body: formData });
}

