// Live updates without WebSockets: poll for first bloods, awards and announcements.
(function () {
  'use strict';
  const { api, toast } = window.CTF;
  let since = Math.floor(Date.now() / 1000);
  const me = document.querySelector('meta[name="csrf-token"]') ? true : false;
  if (!me) return;

  async function tick() {
    if (document.hidden) return;
    try {
      const r = await api(`/api/poll?since=${since}`);
      since = r.now;
      r.events.forEach((e) => {
        if (e.kind === 'blood') toast(`${e.team} drew first blood on ${e.subject}.`, 'blood', 'First blood');
        else toast(`${e.team} earned +${e.amount}: ${e.reason}`, 'success', 'Bonus awarded');
      });
      r.announcements.forEach((a) => toast(a.title, 'info', 'New announcement'));
      const bell = document.querySelector('.bell');
      if (bell && r.unread > 0 && !bell.querySelector('.dot')) bell.insertAdjacentHTML('beforeend', '<span class="dot"></span>');
    } catch (_) { /* offline or signed out; try again next tick */ }
  }
  setInterval(tick, 20000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
})();
