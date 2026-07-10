<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Embed — <?php echo e($publicId); ?></title>
<link rel="stylesheet" href="/css/dashboard-builder.css">
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
</head>
<body>
<div id="embed-app">
    <div class="embed-header">
        <h2 id="embed-title">Loading...</h2>
        <span id="embed-meta"></span>
    </div>
    <div class="dashboard-grid" id="embed-grid"></div>
    <div id="embed-error" style="display:none;color:#f87171;padding:20px;"></div>
</div>
<script>
var hash = '<?php echo e($publicId); ?>';
var url = '/api/dashboard/' + hash;
var charts = {};

function renderCard(c) {
    var el = document.createElement('div');
    el.className = 'dashboard-card';
    var span4 = Math.ceil(Math.min(c.w || c.colspan || 3, 6) * 4 / 6);
    el.style.gridColumn = 'span ' + Math.min(span4, 4);

    if (c.type === 'header' || c.type === 'title' || c.type === 'section') {
        el.innerHTML = '<div class="card-title">' + (c.title || '') + '</div>';
    } else if (c.type === 'kpi' || c.type === 'stat') {
        el.innerHTML = '<div class="card-label">' + (c.id || '') + '</div>' +
            '<div class="card-title">' + (c.title || '') + '</div>' +
            '<div class="stat-value">\u2014</div>';
    } else if (['line','bar','donut','pie','funnel','gauge','heatmap','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
        el.innerHTML = '<div class="card-label">' + (c.id || '') + '</div>' +
            '<div class="card-title">' + (c.title || '') + '</div>' +
            '<div class="card-chart" id="chart-container-' + c.id + '"></div>';
    } else {
        el.innerHTML = '<div class="card-label">' + (c.id || '') + '</div>' +
            '<div class="card-title">' + (c.title || '') + '</div>';
    }
    return el;
}

function renderChart(c) {
    var el = document.getElementById('chart-container-' + c.id);
    if (!el) return;
    var chart = echarts.init(el, 'dark');
    charts[c.id] = chart;
    var labels = ['Jan','Feb','Mar','Apr','May','Jun'];
    var values = [120, 200, 150, 80, 70, 110];
    var opt = { tooltip: { trigger: 'axis' }, grid: { containLabel: true, left: 10, right: 10, top: 20, bottom: 20 } };
    if (c.type === 'line') {
        opt.xAxis = { type: 'category', data: labels };
        opt.yAxis = { type: 'value' };
        opt.series = [{ type: 'line', smooth: true, data: values, areaStyle: { opacity: 0.2 }, lineStyle: { color: '#FB923C', width: 2 } }];
    } else if (c.type === 'bar') {
        opt.xAxis = { type: 'category', data: labels };
        opt.yAxis = { type: 'value' };
        opt.series = [{ type: 'bar', data: values, itemStyle: { color: '#4ade80' }, barWidth: '50%' }];
    } else if (c.type === 'donut' || c.type === 'pie') {
        opt.tooltip = { trigger: 'item', formatter: '{b}: {c} ({d}%)' };
        var pieData = labels.map(function(l, i) { return { name: l, value: values[i] || 0 }; });
        opt.series = [{ type: 'pie', radius: ['40%','65%'], data: pieData, label: { show: true, position: 'outside', fontSize: 10, color: '#94a3b8' } }];
    } else {
        opt.xAxis = { type: 'category', data: labels };
        opt.yAxis = { type: 'value' };
        opt.series = [{ type: c.type, data: values, smooth: true }];
    }
    chart.setOption(opt);
}

fetch(url)
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(d) {
        var dash = d.dashboard || d;
        document.getElementById('embed-title').textContent = dash.title || 'Dashboard';
        document.getElementById('embed-meta').textContent = (dash.cards ? dash.cards.length : 0) + ' cards';
        var grid = document.getElementById('embed-grid');
        (dash.cards || []).forEach(function(c) {
            grid.appendChild(renderCard(c));
        });
        setTimeout(function() {
            (dash.cards || []).forEach(function(c) {
                if (['line','bar','donut','pie','funnel','gauge','heatmap','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
                    renderChart(c);
                }
            });
        }, 100);
    })
    .catch(function(e) {
        document.getElementById('embed-error').style.display = '';
        document.getElementById('embed-error').textContent = 'Failed to load dashboard: ' + e.message;
    });

window.addEventListener('resize', function() {
    Object.values(charts).forEach(function(ch) { if (ch && !ch.isDisposed()) ch.resize(); });
});
</script>
<style>
body { background: #0f172a; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; }
.embed-header { margin-bottom: 16px; }
.embed-header h2 { font-size: 18px; color: #FB923C; }
.embed-header #embed-meta { font-size: 12px; color: #64748b; }
</style>
</body>
</html>
<?php /**PATH C:\laragon\www\dashboard-builder\resources\views/dashboard-builder/embed-public.blade.php ENDPATH**/ ?>