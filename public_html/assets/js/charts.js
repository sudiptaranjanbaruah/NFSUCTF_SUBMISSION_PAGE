// Shared Chart.js theme that follows the CSS tokens (light and dark).
(function () {
  'use strict';
  const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

  // Ordered so neighbouring lines stay distinguishable; the accent leads.
  const PALETTE_DARK = ['#8B8BF5', '#3FB950', '#F5A524', '#60A5FA', '#F2555A', '#2DD4BF', '#E879F9', '#FB923C', '#A3E635', '#A1A1AA'];
  const PALETTE_LIGHT = ['#5B5BD6', '#16A34A', '#D97706', '#2563EB', '#DC2626', '#0D9488', '#C026D3', '#EA580C', '#65A30D', '#71717A'];

  window.CTF = window.CTF || {};
  CTF.chartColors = () => (document.documentElement.getAttribute('data-theme') === 'light' ? PALETTE_LIGHT : PALETTE_DARK);

  CTF.applyChartTheme = function () {
    if (!window.Chart) return;
    const C = window.Chart;
    C.defaults.font.family = css('--font-sans');
    C.defaults.font.size = 12;
    C.defaults.color = css('--text-2');
    C.defaults.borderColor = css('--line');
    C.defaults.plugins.legend.labels.boxWidth = 10;
    C.defaults.plugins.legend.labels.boxHeight = 10;
    C.defaults.plugins.tooltip.backgroundColor = css('--bg-raised');
    C.defaults.plugins.tooltip.titleColor = css('--text');
    C.defaults.plugins.tooltip.bodyColor = css('--text-2');
    C.defaults.plugins.tooltip.borderColor = css('--line-strong');
    C.defaults.plugins.tooltip.borderWidth = 1;
    C.defaults.plugins.tooltip.padding = 10;
    C.defaults.plugins.tooltip.cornerRadius = 6;
    C.defaults.maintainAspectRatio = false;
    C.defaults.animation.duration = 300;
  };

  CTF.timeTick = (v) => new Date(v).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

  // Re-render charts when the theme changes.
  CTF.charts = [];
  document.addEventListener('themechange', () => {
    CTF.applyChartTheme();
    CTF.charts.forEach((fn) => fn());
  });
})();
