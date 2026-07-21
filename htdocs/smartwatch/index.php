<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HealthWatch Dashboard</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #0d1117;
    color: #e6edf3;
    min-height: 100vh;
    padding: 24px;
  }

  header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;
  }

  header h1 {
    font-size: 20px;
    font-weight: 600;
    letter-spacing: 0.3px;
  }

  .dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #3fb950;
    box-shadow: 0 0 6px #3fb950;
    animation: pulse 1.5s infinite;
  }

  .dot.offline { background: #f85149; box-shadow: 0 0 6px #f85149; animation: none; }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
  }

  .cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 28px;
  }

  .card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 12px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    transition: border-color 0.3s;
  }

  .card.warn  { border-color: #d29922; }
  .card.danger { border-color: #f85149; animation: flash 0.8s infinite alternate; }

  @keyframes flash {
    from { border-color: #f85149; }
    to   { border-color: #ff726b; box-shadow: 0 0 12px rgba(248,81,73,0.3); }
  }

  .card-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #8b949e;
    margin-bottom: 8px;
  }

  .card-value {
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
    color: #e6edf3;
  }

  .card-unit {
    font-size: 14px;
    font-weight: 400;
    color: #8b949e;
    margin-left: 4px;
  }

  .card-status {
    font-size: 11px;
    margin-top: 8px;
    font-weight: 500;
  }

  .card-status.ok     { color: #3fb950; }
  .card-status.warn   { color: #d29922; }
  .card-status.danger { color: #f85149; }

  /* BPM icon bar */
  .bpm-bar {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 3px;
    background: #21262d;
  }
  .bpm-bar-fill {
    height: 100%;
    background: #f85149;
    transition: width 0.5s ease;
  }

  /* Alerts */
  #alerts { margin-bottom: 24px; display: flex; flex-direction: column; gap: 8px; }

  .alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    animation: slideIn 0.3s ease;
  }

  @keyframes slideIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .alert.warn   { background: rgba(210,153,34,0.15); border: 1px solid #d29922; color: #e3b341; }
  .alert.danger { background: rgba(248,81,73,0.15);  border: 1px solid #f85149; color: #ff7b72; }

  .alert-icon { font-size: 16px; }

  /* Chart */
  .chart-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
  }

  .chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
  }

  .chart-title {
    font-size: 14px;
    font-weight: 600;
    color: #c9d1d9;
  }

  .chart-meta {
    font-size: 11px;
    color: #8b949e;
  }

  .chart-wrap {
    position: relative;
    height: 220px;
  }

  /* Last updated */
  #last-update {
    font-size: 11px;
    color: #484f58;
    text-align: right;
    margin-top: 8px;
  }

  /* No data */
  .no-data {
    text-align: center;
    padding: 60px 20px;
    color: #484f58;
    font-size: 14px;
  }
</style>
</head>
<body>

<header>
  <div class="dot offline" id="status-dot"></div>
  <h1>HealthWatch Live Dashboard</h1>
</header>

<div class="cards">
  <div class="card" id="card-bpm">
    <div class="card-label">Heart Rate</div>
    <div class="card-value" id="val-bpm">--<span class="card-unit">BPM</span></div>
    <div class="card-status ok" id="st-bpm">Waiting for data</div>
    <div class="bpm-bar"><div class="bpm-bar-fill" id="bpm-bar" style="width:0%"></div></div>
  </div>

  <div class="card" id="card-temp">
    <div class="card-label">Temperature</div>
    <div class="card-value" id="val-temp">--<span class="card-unit">°C</span></div>
    <div class="card-status ok" id="st-temp">Waiting for data</div>
  </div>

  <div class="card" id="card-hum">
    <div class="card-label">Humidity</div>
    <div class="card-value" id="val-hum">--<span class="card-unit">%</span></div>
    <div class="card-status ok" id="st-hum">Waiting for data</div>
  </div>

  <div class="card" id="card-pres">
    <div class="card-label">Pressure</div>
    <div class="card-value" id="val-pres">--<span class="card-unit">hPa</span></div>
    <div class="card-status ok" id="st-pres">Normal range</div>
  </div>
</div>

<div id="alerts"></div>

<div class="chart-card">
  <div class="chart-header">
    <span class="chart-title">Heart Rate — Live (last 2 min)</span>
    <span class="chart-meta" id="chart-range">No data yet</span>
  </div>
  <div class="chart-wrap">
    <canvas id="bpmChart" role="img" aria-label="Live heart rate chart">Live BPM readings.</canvas>
  </div>
</div>

<div class="chart-card">
  <div class="chart-header">
    <span class="chart-title">Temperature & Humidity</span>
    <span class="chart-meta">Last 2 minutes</span>
  </div>
  <div class="chart-wrap">
    <canvas id="envChart" role="img" aria-label="Temperature and humidity chart">Env readings.</canvas>
  </div>
</div>

<div id="last-update">Never updated</div>

<script>
// ── Thresholds ────────────────────────────────────────────────
const T = {
  bpm:  { warnHigh: 120, dangerHigh: 150, warnLow: 50, dangerLow: 40 },
  temp: { warnHigh: 35,  dangerHigh: 38,  warnLow: 10, dangerLow: 5  },
  hum:  { warnHigh: 80,  dangerHigh: 90,  warnLow: 20, dangerLow: 10 },
};

// ── Chart setup ───────────────────────────────────────────────
const chartOpts = (yLabel, yMin, yMax) => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: { duration: 200 },
  plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
  scales: {
    x: { ticks: { color: '#484f58', font: { size: 10 }, maxTicksLimit: 8 }, grid: { color: '#21262d' } },
    y: { ticks: { color: '#8b949e', font: { size: 11 } }, grid: { color: '#21262d' },
         title: { display: true, text: yLabel, color: '#484f58', font: { size: 10 } },
         min: yMin, max: yMax }
  }
});

const bpmChart = new Chart(document.getElementById('bpmChart'), {
  type: 'line',
  data: {
    labels: [],
    datasets: [{
      label: 'BPM',
      data: [],
      borderColor: '#f85149',
      backgroundColor: 'rgba(248,81,73,0.08)',
      borderWidth: 2,
      pointRadius: 0,
      pointHoverRadius: 4,
      tension: 0.4,
      fill: true,
    }]
  },
  options: chartOpts('BPM', 30, 180)
});

const envChart = new Chart(document.getElementById('envChart'), {
  type: 'line',
  data: {
    labels: [],
    datasets: [
      {
        label: 'Temp °C',
        data: [],
        borderColor: '#ff7b72',
        backgroundColor: 'rgba(255,123,114,0.05)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.4,
        fill: false,
        yAxisID: 'yTemp',
      },
      {
        label: 'Humidity %',
        data: [],
        borderColor: '#58a6ff',
        backgroundColor: 'rgba(88,166,255,0.05)',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.4,
        fill: false,
        yAxisID: 'yHum',
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 200 },
    plugins: {
      legend: { display: true, labels: { color: '#8b949e', font: { size: 11 }, boxWidth: 12 } },
      tooltip: { mode: 'index', intersect: false }
    },
    scales: {
      x:     { ticks: { color: '#484f58', font: { size: 10 }, maxTicksLimit: 8 }, grid: { color: '#21262d' } },
      yTemp: { type: 'linear', position: 'left',  ticks: { color: '#ff7b72', font: { size: 10 } }, grid: { color: '#21262d' }, title: { display: true, text: '°C', color: '#484f58', font: {size:10} } },
      yHum:  { type: 'linear', position: 'right', ticks: { color: '#58a6ff', font: { size: 10 } }, grid: { drawOnChartArea: false }, title: { display: true, text: '%', color: '#484f58', font: {size:10} } }
    }
  }
});

// ── Helper: classify a value ──────────────────────────────────
function classify(val, th) {
  if (val >= th.dangerHigh || val <= th.dangerLow) return 'danger';
  if (val >= th.warnHigh   || val <= th.warnLow)   return 'warn';
  return 'ok';
}

const statusText = {
  bpm:  { ok: 'Normal range', warn: 'Elevated / Low — monitor closely', danger: '⚠ Critical — seek attention' },
  temp: { ok: 'Normal', warn: 'Uncomfortable range', danger: '⚠ Dangerous temperature' },
  hum:  { ok: 'Comfortable', warn: 'Outside comfort zone', danger: '⚠ Dangerous humidity' },
};

function setCard(id, value, unit, level, stText) {
  const card = document.getElementById('card-' + id);
  const val  = document.getElementById('val-'  + id);
  const st   = document.getElementById('st-'   + id);
  card.className = 'card ' + (level === 'ok' ? '' : level);
  val.innerHTML  = value + '<span class="card-unit">' + unit + '</span>';
  st.className   = 'card-status ' + level;
  st.textContent = stText;
}

// ── Alert builder ─────────────────────────────────────────────
const alertMessages = {
  bpm_danger_high: '❤️ Heart rate critically HIGH — possible tachycardia',
  bpm_danger_low:  '❤️ Heart rate critically LOW — possible bradycardia',
  bpm_warn_high:   '❤️ Heart rate elevated above 120 BPM at rest',
  bpm_warn_low:    '❤️ Heart rate below 50 BPM',
  temp_danger_high:'🌡️ Temperature dangerously HIGH — risk of heat stroke',
  temp_danger_low: '🌡️ Temperature dangerously LOW — risk of hypothermia',
  temp_warn_high:  '🌡️ Temperature above 35°C — heat stress risk',
  temp_warn_low:   '🌡️ Temperature below 10°C — cold stress risk',
  hum_danger_high: '💧 Humidity critically HIGH — breathing difficulty risk',
  hum_danger_low:  '💧 Humidity critically LOW — dehydration risk',
  hum_warn_high:   '💧 Humidity above 80% — discomfort and overheating risk',
  hum_warn_low:    '💧 Humidity below 20% — dry air, dehydration risk',
};

function buildAlerts(bpm, temp, hum) {
  const active = [];

  function check(key, val, th) {
    if (val >= th.dangerHigh) active.push({ level: 'danger', msg: alertMessages[key + '_danger_high'] });
    else if (val <= th.dangerLow) active.push({ level: 'danger', msg: alertMessages[key + '_danger_low'] });
    else if (val >= th.warnHigh) active.push({ level: 'warn',   msg: alertMessages[key + '_warn_high'] });
    else if (val <= th.warnLow)  active.push({ level: 'warn',   msg: alertMessages[key + '_warn_low'] });
  }

  if (bpm > 0) check('bpm', bpm, T.bpm);
  check('temp', temp, T.temp);
  check('hum',  hum,  T.hum);

  const container = document.getElementById('alerts');
  container.innerHTML = '';
  active.forEach(a => {
    const div = document.createElement('div');
    div.className = 'alert ' + a.level;
    div.innerHTML = '<span class="alert-icon">' + (a.level === 'danger' ? '🔴' : '🟡') + '</span>' + a.msg;
    container.appendChild(div);
  });
}

// ── Format timestamp label ────────────────────────────────────
function fmtTime(ts) {
  const d = new Date(ts * 1000);
  return d.getHours().toString().padStart(2,'0') + ':' +
         d.getMinutes().toString().padStart(2,'0') + ':' +
         d.getSeconds().toString().padStart(2,'0');
}

// ── Poll API ──────────────────────────────────────────────────
async function poll() {
  try {
    const res  = await fetch('api.php');
    const data = await res.json();

    if (!data || data.length === 0) return;

    const dot  = document.getElementById('status-dot');
    const last = data[data.length - 1];
    const now  = Date.now() / 1000;
    const age  = now - last.ts;

    // Online/offline indicator (stale if > 5 seconds)
    if (age < 5) {
      dot.className = 'dot';
    } else {
      dot.className = 'dot offline';
    }

    // ── Latest values for cards ───────────────────────────────
    const bpm  = last.finger ? last.bpm : 0;
    const temp = last.temp;
    const hum  = last.hum;
    const pres = last.pres;

    // BPM card
    const bpmLevel = bpm > 0 ? classify(bpm, T.bpm) : 'ok';
    setCard('bpm', bpm > 0 ? bpm : '--', 'BPM', bpmLevel,
            bpm > 0 ? statusText.bpm[bpmLevel] : 'No finger detected');
    // BPM progress bar (0–200 range)
    document.getElementById('bpm-bar').style.width = bpm > 0 ? Math.min(bpm / 200 * 100, 100) + '%' : '0%';

    // Temp card
    const tempLevel = classify(temp, T.temp);
    setCard('temp', temp.toFixed(1), '°C', tempLevel, statusText.temp[tempLevel]);

    // Humidity card
    const humLevel = classify(hum, T.hum);
    setCard('hum', Math.round(hum), '%', humLevel, statusText.hum[humLevel]);

    // Pressure card (just informational)
    setCard('pres', Math.round(pres), 'hPa', 'ok', 'Normal range');

    // ── Alerts ────────────────────────────────────────────────
    buildAlerts(bpm, temp, hum);

    // ── Charts ────────────────────────────────────────────────
    const labels   = data.map(d => fmtTime(d.ts));
    const bpmData  = data.map(d => d.finger && d.bpm > 0 ? d.bpm : null);
    const tempData = data.map(d => d.temp);
    const humData  = data.map(d => d.hum);

    bpmChart.data.labels                = labels;
    bpmChart.data.datasets[0].data      = bpmData;
    bpmChart.update('none');

    envChart.data.labels                = labels;
    envChart.data.datasets[0].data      = tempData;
    envChart.data.datasets[1].data      = humData;
    envChart.update('none');

    // Range label
    if (data.length >= 2) {
      document.getElementById('chart-range').textContent =
        fmtTime(data[0].ts) + ' → ' + fmtTime(last.ts);
    }

    document.getElementById('last-update').textContent =
      'Last update: ' + new Date().toLocaleTimeString();

  } catch (e) {
    console.error('Poll error:', e);
    document.getElementById('status-dot').className = 'dot offline';
  }
}

poll();
setInterval(poll, 1000);
</script>
</body>
</html>