// ============================================
// Client-Side Hash Router
// ============================================
const routes = {};
let currentRoute = null;

function registerRoute(path, handler) {
    routes[path] = handler;
}

function navigateTo(path) {
    window.location.hash = '#' + path;
}

function getCurrentHash() {
    return window.location.hash.slice(1) || '/login';
}

async function handleRoute() {
    const hash = getCurrentHash();
    const path = hash.split('?')[0];

    // Find matching route
    let handler = routes[path];
    let params = {};

    if (!handler) {
        // Try pattern matching
        for (const [pattern, h] of Object.entries(routes)) {
            const regex = new RegExp('^' + pattern.replace(/:(\w+)/g, '(?<$1>[^/]+)') + '$');
            const match = path.match(regex);
            if (match) {
                handler = h;
                params = match.groups || {};
                break;
            }
        }
    }

    if (!handler) {
        handler = routes['/404'] || (() => {
            document.getElementById('app').innerHTML = '<div class="error-page"><div class="error-code">404</div><div class="error-message">Page not found</div></div>';
        });
    }

    // Check authentication for protected routes
    const publicRoutes = ['/login', '/admin/login', '/info', '/404'];
    const user = getUser();
    const token = getToken();

    if (!publicRoutes.includes(path) && !token) {
        navigateTo('/login');
        return;
    }

    // Toggle sidebar visibility
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const mobileToggle = document.getElementById('mobile-toggle');

    if (publicRoutes.includes(path)) {
        sidebar?.classList.add('hidden');
        mainContent?.classList.add('no-sidebar');
        mobileToggle?.classList.add('hidden');
    } else {
        sidebar?.classList.remove('hidden');
        mainContent?.classList.remove('no-sidebar');
        mobileToggle?.classList.remove('hidden');
        updateSidebarUser(user);
        updateActiveNavLink(path);
    }

    currentRoute = path;

    try {
        await handler(params);
    } catch (err) {
        console.error('Route error:', err);
    }
}

function updateSidebarUser(user) {
    if (!user) return;
    const nameEl = document.getElementById('sidebar-user-name');
    const roleEl = document.getElementById('sidebar-user-role');
    const avatarEl = document.getElementById('sidebar-user-avatar');
    if (nameEl) nameEl.textContent = user.name;
    if (roleEl) roleEl.textContent = user.role;
    if (avatarEl) avatarEl.textContent = user.name?.charAt(0)?.toUpperCase() || '?';
}

function updateActiveNavLink(path) {
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('data-route');
        link.classList.toggle('active', href === path);
    });
}

// Initialize router
window.addEventListener('hashchange', handleRoute);
window.addEventListener('DOMContentLoaded', () => {
    if (!window.location.hash) {
        window.location.hash = '#/login';
    }
    handleRoute();
});
