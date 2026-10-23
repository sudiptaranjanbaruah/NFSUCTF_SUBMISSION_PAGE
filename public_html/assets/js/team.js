// Team page: cumulative score chart.
(function () {
  'use strict';
  const canvas = document.getElementById('team-chart');
  if (!canvas || !window.Chart) return;
  const { applyChartTheme, chartColors, timeTick } = window.CTF;
  const raw = JSON.parse(canvas.dataset.series || '[]');
  if (!raw.some(([, y]) => y !== 0)) {
    canvas.parentElement.outerHTML = '<div class="empty" style="padding:var(--s-8)"><p>The chart fills in as the team scores.</p></div>';
    return;
  }
  let chart;
  function draw() {
    applyChartTheme();
    const color = chartColors()[0];
    const pts = raw.map(([x, y]) => ({ x, y }));
    if (pts.length) pts.push({ x: Date.now(), y: pts[pts.length - 1].y });
    if (chart) chart.destroy();
    chart = new Chart(canvas, {
      type: 'line',
      data: { datasets: [{ label: 'Score', data: pts, stepped: true, borderColor: color, backgroundColor: color + '22', fill: true, pointRadius: 0, borderWidth: 2 }] },
      options: {
        parsing: false,
        scales: { x: { type: 'linear', ticks: { callback: timeTick, maxTicksLimit: 5 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { title: (i) => timeTick(i[0].parsed.x) } } },
      },
    });
  }
  CTF.charts.push(draw);
  draw();
})();
