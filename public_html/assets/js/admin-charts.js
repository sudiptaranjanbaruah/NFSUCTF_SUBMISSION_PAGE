// Admin charts: renders any canvas with data-line-chart / data-bar-chart JSON.
(function () {
  'use strict';
  if (!window.Chart) return;
  const { applyChartTheme, chartColors, timeTick } = window.CTF;
  const made = [];

  function render() {
    applyChartTheme();
    made.forEach((c) => c.destroy());
    made.length = 0;
    const colors = chartColors();

    document.querySelectorAll('canvas[data-line-chart]').forEach((cv) => {
      const series = JSON.parse(cv.dataset.lineChart);
      made.push(new Chart(cv, {
        type: 'line',
        data: { datasets: series.map((s, i) => ({ label: s.name, data: s.points.map(([x, y]) => ({ x, y })), stepped: true, borderColor: colors[i % colors.length], backgroundColor: colors[i % colors.length], pointRadius: 0, borderWidth: 2 })) },
        options: { parsing: false, interaction: { mode: 'nearest', intersect: false }, scales: { x: { type: 'linear', ticks: { callback: timeTick, maxTicksLimit: 6 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: series.length > 1, position: 'bottom' }, tooltip: { callbacks: { title: (i) => timeTick(i[0].parsed.x) } } } },
      }));
    });

    document.querySelectorAll('canvas[data-bar-chart]').forEach((cv) => {
      const d = JSON.parse(cv.dataset.barChart);
      const horizontal = cv.dataset.horizontal === '1';
      made.push(new Chart(cv, {
        type: 'bar',
        data: { labels: d.labels, datasets: d.series.map((s, i) => ({ label: s.name, data: s.values, backgroundColor: s.color === 'danger' ? getComputedStyle(document.documentElement).getPropertyValue('--danger').trim() : colors[i % colors.length], borderRadius: 3, maxBarThickness: 28 })) },
        options: { indexAxis: horizontal ? 'y' : 'x', scales: { x: { stacked: !!d.stacked, grid: { display: horizontal } , ticks: { precision: 0 } }, y: { stacked: !!d.stacked, beginAtZero: true, grid: { display: !horizontal }, ticks: { precision: 0 } } }, plugins: { legend: { display: d.series.length > 1, position: 'bottom' } } },
      }));
    });
  }
  CTF.charts.push(render);
  render();
})();
