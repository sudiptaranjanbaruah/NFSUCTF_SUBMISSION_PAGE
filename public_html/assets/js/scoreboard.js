// Scoreboard: solve-distribution chart (% teams vs % tasks solved) and 30s refresh.
(function () {
  'use strict';
  const { api, esc, icon, chartColors, applyChartTheme } = window.CTF;
  const canvas = document.getElementById('sb-chart');
  if (!canvas || !window.Chart) return;
  applyChartTheme();
  let chart;
  let last = JSON.parse(canvas.dataset.distribution || 'null');

  // One marker shape per series, like a classic line-with-markers chart.
  const MARKERS = ['rectRot', 'rect', 'triangle', 'crossRot', 'circle', 'star'];
  const pct = (v) => `${v}%`;

  function draw(dist) {
    if (!dist) return;
    last = dist;
    const colors = chartColors();
    const datasets = dist.series.map((s, i) => ({
      label: s.name,
      data: s.values,
      meta: s,
      borderColor: colors[i % colors.length],
      backgroundColor: colors[i % colors.length],
      borderWidth: i === 0 ? 2.5 : 2,
      pointStyle: MARKERS[i % MARKERS.length],
      pointRadius: i === 0 ? 5 : 4,
      pointHoverRadius: 7,
      pointBorderWidth: 2,
      tension: 0,
    }));
    if (chart) chart.destroy();
    chart = new Chart(canvas, {
      type: 'line',
      data: { labels: dist.labels.map(pct), datasets },
      options: {
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: {
            title: { display: true, text: '% Tasks solved', padding: { top: 8 } },
            grid: { display: false },
          },
          y: {
            min: 0,
            max: 100,
            ticks: { stepSize: 20, callback: pct },
            title: { display: true, text: '% Teams participated' },
          },
        },
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 14, padding: 16 } },
          tooltip: {
            usePointStyle: true,
            callbacks: {
              title: (items) => `Solved at least ${items[0].label} of tasks`,
              label: (i) => `${i.dataset.label}: ${i.parsed.y}% of ${i.dataset.meta.teams} teams`,
            },
          },
        },
      },
    });
  }

  async function refresh() {
    try {
      const data = await api('/api/scoreboard' + location.search);
      draw(data.distribution);
      const tbody = document.querySelector('#sb-table tbody');
      const meId = document.querySelector('#sb-table tr.is-me a')?.getAttribute('href');
      if (tbody) {
        tbody.innerHTML = data.rows.map((t) => `<tr class="${meId === `/teams/${t.id}` ? 'is-me' : ''}">
          <td><span class="rank ${t.rank <= 3 ? `rank-${t.rank}` : ''}">${t.rank}</span></td>
          <td><a class="rowlink" href="/teams/${t.id}">${esc(t.name)}</a>${t.affiliation ? `<div class="subtle xs">${esc(t.affiliation)}</div>` : ''}</td>
          <td class="r num">${t.solves}</td>
          <td class="r num">${t.bloods ? `<span class="badge badge-blood">${icon('droplet')}${t.bloods}</span>` : '<span class="subtle">0</span>'}</td>
          <td class="r small">${t.last ? `<time datetime="${t.last}" data-fmt="ago"></time>` : '<span class="subtle">—</span>'}</td>
          <td class="r score">${t.score.toLocaleString()}</td></tr>`).join('');
        CTF.localizeTimes(tbody);
      }
      const u = document.querySelector('[data-sb-updated]');
      if (u) u.textContent = `Updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    } catch (_) { /* keep last good render */ }
  }

  CTF.charts.push(() => draw(last));
  draw(last);
  setInterval(() => { if (!document.hidden) refresh(); }, 30000);
})();
