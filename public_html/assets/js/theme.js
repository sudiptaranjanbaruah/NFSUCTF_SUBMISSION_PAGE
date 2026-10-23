// Runs before paint to avoid a theme flash. Follows the OS unless the user picked a theme.
(function () {
  var root = document.documentElement;
  var stored = null;
  try { stored = localStorage.getItem('theme'); } catch (e) {}
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  function apply() {
    var t = stored === 'light' || stored === 'dark' ? stored : (mq && mq.matches ? 'dark' : 'light');
    root.setAttribute('data-theme', t);
  }
  apply();
  if (mq && mq.addEventListener) {
    mq.addEventListener('change', function () {
      if (!stored) { apply(); document.dispatchEvent(new CustomEvent('themechange')); }
    });
  }
})();
