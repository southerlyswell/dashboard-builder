
function dashboardBuilder() {
    return {
        // ===== State =====
        activeClient: (window.ACFS_CONFIG && window.ACFS_CONFIG.clientId) || '',
        chatInput: '',
        messages: [
            { id: 1, role: 'assistant', content: 'Hi! I can build dashboards for you. Just describe what you want — I\'ll generate the layout, charts, and queries. What would you like to see?' }
        ],
        loading: false,
        aiStatus: '',
        aiOnline: false,
        aiChecking: true,
        dashboard: null,
        showSchema: false,
        msgId: 2,
        chatVisible: true,
        showJSONPanel: false,
        jsonEditorText: '',
        cardEditModal: null,
        revisions: [],
        revisionIndex: -1,
        activeFilters: {},
        saveStatus: '',
        dataLoading: false,
        schemaData: null,
        schemaLoading: false,
        dashboardId: null,
        publicUrl: '',
        jsonError: '',
        revisionInfo: '',
        sidebarWidth: 420,
        sidebarResizing: false,
        modalDrag: false,
        modalX: 0,
        modalY: 0,
        modalDragStartX: 0,
        modalDragStartY: 0,
        _chartInstances: {},
        _chartObservers: {},

        // ===== Lifecycle =====
        init() {
            var self = this;
            document.addEventListener('mouseup', function() { self.stopResize(); });
            this.checkAI();
            this.checkProjectLoad();
        },

        checkProjectLoad() {
            var params = new URLSearchParams(window.location.search);
            var projectId = params.get('project');
            if (!projectId) return;
            var self = this;
            this.aiStatus = 'Loading project...';
            fetch('/dashboard-builder/projects/' + projectId + '/load')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.layout) {
                        self.tryParseDashboard(JSON.stringify(data.layout));
                    }
                    self.aiStatus = '';
                })
                .catch(function(e) {
                    self.aiStatus = '';
                    console.error('Project load failed:', e);
                });
        },

        aiStatusText() {
            if (this.aiChecking) return 'Checking AI...';
            return this.aiOnline ? 'AI Online' : 'AI Offline';
        },

        async checkAI() {
            this.aiChecking = true;
            try {
                const r = await fetch('/dashboard-builder/ai/health');
                const d = await r.json();
                this.aiOnline = d.online;
            } catch(e) { this.aiOnline = false; }
            this.aiChecking = false;
        },

        // ===== Test Dashboard (no AI — raw render test) =====
        injectTestDashboard() {
            var testJson = '{"title":"Test Dashboard","theme":"dark","cards":[{"id":"title-1","type":"title","title":"Test Dashboard","w":4,"h":2},{"id":"kpi-1","type":"kpi","title":"Total Persons","w":1,"h":3,"query":"SELECT COUNT(*) as value FROM persons"},{"id":"kpi-2","type":"kpi","title":"Total Staff","w":1,"h":3,"query":"SELECT COUNT(*) as value FROM staff"},{"id":"kpi-3","type":"kpi","title":"Projects","w":1,"h":3,"query":"SELECT COUNT(*) as value FROM projects"},{"id":"kpi-4","type":"kpi","title":"Branches","w":1,"h":3,"query":"SELECT COUNT(*) as value FROM branches"},{"id":"div-1","type":"divider","title":"","w":4,"h":1},{"id":"hdr-1","type":"header","title":"Attendance Trends","w":4,"h":1},{"id":"line-1","type":"line","title":"Daily Attendance","w":4,"h":6,"query":"SELECT DATE(date) as d, COUNT(*) as c FROM attendance GROUP BY DATE(date) ORDER BY d LIMIT 30"},{"id":"sub-1","type":"subheader","title":"Breakdowns","w":4,"h":1},{"id":"donut-1","type":"donut","title":"Persons by Branch","w":2,"h":5,"query":"SELECT b.name as label, COUNT(*) as value FROM persons p JOIN branches b ON p.branch_id = b.id GROUP BY b.name ORDER BY value DESC LIMIT 8"},{"id":"bar-1","type":"bar","title":"Projects by Status","w":2,"h":5,"query":"SELECT status, COUNT(*) as count FROM projects GROUP BY status ORDER BY count DESC"}]}';
            console.log('INJECT TEST: calling tryParseDashboard');
            this.tryParseDashboard(testJson);
            console.log('INJECT TEST: tryParseDashboard returned');
            console.log('INJECT TEST: dashboard is', this.dashboard ? 'SET (' + this.dashboard.cards.length + ' cards)' : 'NULL');
            this.$nextTick(function() {
                console.log('INJECT TEST: renderGrid called');
            });
        },

        // ===== Chat =====
        async sendMessage() {
            const msg = this.chatInput.trim();
            if (!msg || this.loading) return;
            this.messages.push({ id: this.msgId++, role: 'user', content: msg });
            this.chatInput = '';
            this.loading = true;
            this.aiStatus = 'Connecting to AI...';
            this.scrollChat();

            var self = this;
            var t1 = setTimeout(function() { self.aiStatus = 'Analyzing database schema...'; }, 5000);
            var t2 = setTimeout(function() { self.aiStatus = 'Generating dashboard layout...'; }, 15000);
            var t3 = setTimeout(function() { self.aiStatus = 'Building charts...'; }, 30000);
            var t4 = setTimeout(function() { self.aiStatus = 'Almost done...'; }, 50000);

            try {
                const resp = await fetch('/dashboard-builder/ai/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({
                        message: msg,
                        client_id: this.activeClient || null,
                        context: this.messages.slice(-6).map(function(m) { return { role: m.role, content: m.content }; })
                    })
                });
                const data = await resp.json();
                if (data._render_dashboard) {
                    this.tryParseDashboard(JSON.stringify(data._render_dashboard));
                    this.messages.push({ id: this.msgId++, role: 'assistant', content: data.content || 'Dashboard rendered!' });
                } else {
                    this.messages.push({ id: this.msgId++, role: 'assistant', content: data.content || 'No response' });
                    this.tryParseDashboard(data.content);
                }
                delete data._render_dashboard;
            } catch (e) {
                this.messages.push({ id: this.msgId++, role: 'assistant', content: 'Connection error. Please try again.' });
            }

            this.aiStatus = 'Rendering...';
            setTimeout(function() { self.loading = false; self.aiStatus = ''; }, 1500);
            clearTimeout(t1); clearTimeout(t2); clearTimeout(t3); clearTimeout(t4);
            this.scrollChat();
        },

        sendQuick(msg) { this.chatInput = msg; this.sendMessage(); },

        scrollChat() {
            var box = this.$refs.chatBox;
            if (box) box.scrollTop = box.scrollHeight;
        },

        formatMessage(content) {
            if (typeof marked !== 'undefined') {
                var html = marked.parse(content);
                html = html.replace(/<pre><code class="language-json">([\s\S]*?)<\/code><\/pre>/g,
                    '<details style="margin:6px 0;"><summary style="cursor:pointer;color:#38bdf8;font-size:11px;">Show JSON</summary><pre><code>$1</code></pre></details>');
                return html;
            }
            return content.replace(/\n/g, '<br>');
        },

        // ===== Dashboard JSON Parsing =====
        tryParseDashboard(content) {
            var jsonMatch = content.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
            var jsonStr = jsonMatch ? jsonMatch[1] : null;

            if (!jsonStr) {
                var brace = content.indexOf('{"dashboard"');
                if (brace < 0) brace = content.indexOf('{"theme"');
                if (brace < 0) brace = content.indexOf('{"cards"');
                if (brace >= 0) {
                    var depth = 0, end = brace;
                    for (var i = brace; i < content.length; i++) {
                        if (content[i] === '{') depth++;
                        if (content[i] === '}') { depth--; if (depth === 0) { end = i + 1; break; } }
                    }
                    jsonStr = content.substring(brace, end);
                }
            }
            if (!jsonStr) return;

            try {
                var json = JSON.parse(jsonStr);
                if (json.dashboard) json = json.dashboard;
                if (!json.cards || !json.cards.length) return;

                var self = this;
                json.cards = json.cards.map(function(c, i) {
                    if (!c.id) c.id = 'card-' + i;
                    if (!c.w) c.w = c.width || c.colspan || c.colSpan || 3;
                    if (!c.h) c.h = c.height || c.rowspan || c.rowSpan || (c.type === 'divider' ? 1 : c.type === 'subheader' ? 1 : c.type === 'header' || c.type === 'title' || c.type === 'section' ? 2 : c.type === 'kpi' || c.type === 'stat' ? 3 : 6);
                    if (c.type === 'divider') { c.w = 4; c.h = 1; }
                    if (c.type === 'title') { c.w = 4; c.h = c.h || 2; }
                    if (c.type === 'header') { c.w = 4; c.h = c.h || 1; }
                    if (c.type === 'subheader') { c.w = 4; c.h = c.h || 1; }
                    if (c.type === 'section') { c.w = 4; c.h = c.h || 2; }
                    return c;
                });
                // Auto-place cards that don't have explicit col/row positions
                if (!json.cards.length || json.cards[0].col === undefined) {
                    self.autoPlaceCards(json.cards);
                }

                if (!json.title) json.title = 'Dashboard';
                if (this.dashboard && this.dashboard.cards && this.dashboard.cards.length > 0) {
                    this.addRevision(this.dashboard);
                }
                this.dashboard = json;
                this.addRevision(json);
                this.$nextTick(function() { self.renderGrid(); });
            } catch (e) {
                console.log('JSON parse failed:', e.message);
            }
        },

        // ===== Grid Rendering (CSS Grid) =====
        renderGrid() {
            var container = this.$refs.gridContainer;
            if (!container) return;
            if (!this.dashboard || !this.dashboard.cards || !this.dashboard.cards.length) return;

            container.style.display = '';
            container.innerHTML = '';
            var self = this;

            requestAnimationFrame(function() {
                try {
                    var grid = document.createElement('div');
                    grid.className = 'dashboard-grid';

                    // Click handler (attached once to container)
                    if (!self._gridClickBound) {
                        self._gridClickBound = true;
                        container.addEventListener('click', function(e) {
                            var card = e.target.closest('.dashboard-card');
                            if (card) self.editCard(card.getAttribute('data-card-id'));
                        });
                    }

                    // Build each card
                    self.dashboard.cards.forEach(function(c) {
                        var cardEl = document.createElement('div');
                        cardEl.className = 'dashboard-card';
                        var span = Math.min(c.w || c.colspan || 3, 4);
                        var rowSpan = Math.min(c.h || c.rowspan || 3, 12);
                        var colStart = (c.col || 0) + 1;
                        var rowStart = (c.row || 0) + 1;
                        cardEl.style.gridColumn = colStart + ' / span ' + span;
                        cardEl.style.gridRow = rowStart + ' / span ' + rowSpan;
                        if (c.type) cardEl.classList.add('card-type-' + c.type);
                        if (c.id) cardEl.setAttribute('data-card-id', c.id);
                        cardEl.innerHTML = self.cardHTML(c);
                        grid.appendChild(cardEl);
                    });

                    container.appendChild(grid);

                    // Render ECharts after DOM settles
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            self.dashboard.cards.forEach(function(c) {
                                if (['line','bar','donut','funnel','gauge','heatmap','pie','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
                                    self.renderChart(c);
                                }
                            });
                        });
                    });

                    // ResizeObserver for container resize -> re-render charts
                    if (!self._gridResizeHandler) {
                        self._gridResizeHandler = true;
                        var ro = new ResizeObserver(function() {
                            requestAnimationFrame(function() {
                                self.dashboard.cards.forEach(function(c) {
                                    if (['line','bar','donut','funnel','gauge','heatmap','pie','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
                                        self.renderChart(c);
                                    }
                                });
                            });
                        });
                        ro.observe(container);
                    }
                } catch(e) { console.error('Grid render error:', e); }
            });

            setTimeout(function() { self.fetchCardData(); }, 500);
            this._initVisibilityHandler();
        },

        // ===== Card HTML =====
        cardHTML(c) {
            var h = '';
            if (c.type === 'divider') {
                h = '<div class="divider-line"></div>' + (c.title ? '<div class="divider-label">' + c.title + '</div>' : '') + '<div class="divider-line"></div>';
            } else if (c.type === 'title') {
                h = '<div class="card-title">' + (c.title || '') + '</div>';
            } else if (c.type === 'header') {
                h = '<div class="card-title">' + (c.title || '') + '</div>';
            } else if (c.type === 'subheader') {
                h = '<div class="card-subheader">' + (c.title || '') + '</div>';
            } else if (c.type === 'section') {
                h = '<div class="card-title">' + (c.title || '') + '</div>';
            } else if (c.type === 'kpi' || c.type === 'stat') {
                var val = c.value || (c._data || {}).value || 0;
                var delta = (c._data || {}).delta || 0;
                var ds = delta > 0 ? '+' : '';
                var dc = delta > 0 ? 'up' : delta < 0 ? 'down' : '';
                var cc = (c._data || {}).threshold || (val > 1000 ? 'green' : val > 500 ? 'amber' : 'red');
                var alignStyle = ((c.viz_config || {}).align === 'center') ? ' style="text-align:center"' : '';
                h = '<div class="card-label"' + alignStyle + '>' + (c.id || '') + '</div>' +
                    '<div class="card-title"' + alignStyle + '>' + (c.title || '') + '</div>' +
                    '<div class="stat-value ' + cc + '"' + alignStyle + '>' + (val ? (val >= 1000 ? (val/1000).toFixed(1) + 'K' : val.toLocaleString()) : '\u2014') + '</div>' +
                    (delta !== 0 ? '<div class="stat-delta ' + dc + '"' + alignStyle + '>' + ds + delta + '% vs prior</div>' : '');
            } else if (['line','bar','donut','funnel','gauge','heatmap','pie','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
                h = '<div class="card-label">' + (c.id || '') + '</div>' +
                    '<div class="card-title">' + (c.title || '') + '</div>' +
                    '<div class="card-chart" id="chart-container-' + c.id + '"></div>';
            } else if (c.type === 'table') {
                var tblCols = c._data ? c._data.columns : (c.columns || []);
                var tblRows = c._data ? c._data.rows : [];
                h = '<div class="card-label">' + (c.id || '') + '</div>' +
                    '<div class="card-title">' + (c.title || '') + '</div>' +
                    '<div class="table-container" id="table-container-' + c.id + '"><table class="data-table"><thead><tr>' +
                    tblCols.map(function(col) { return '<th>' + (col || '') + '</th>'; }).join('') +
                    '</tr></thead><tbody>' +
                    (tblRows.length ? tblRows.map(function(row) { return '<tr>' + (row || []).map(function(cell) { return '<td>' + (cell !== null && cell !== undefined ? cell : '') + '</td>'; }).join('') + '</tr>'; }).join('') : '<tr><td colspan="' + (tblCols.length || 1) + '" style="text-align:center;color:#64748b;padding:20px;">No data</td></tr>') +
                    '</tbody></table></div>';
            } else {
                h = '<div class="card-label">' + (c.id || '') + '</div>' +
                    '<div class="card-title">' + (c.title || '') + '</div>' +
                    '<div style="margin-top:4px;font-size:11px;color:#64748b;">' + ((c.query || '').substring(0, 60)) + '...</div>';
            }
            return h;
        },

        // ===== Chart Rendering =====
        renderChart(c, qData) {
            var self = this;
            var el = document.getElementById('chart-container-' + c.id);
            if (!el) return;

            if (this._chartInstances[c.id]) {
                try { this._chartInstances[c.id].dispose(); } catch(e) {}
                delete this._chartInstances[c.id];
            }
            if (this._chartObservers[c.id]) {
                this._chartObservers[c.id].disconnect();
                delete this._chartObservers[c.id];
            }

            el.innerHTML = '';
            this._waitForDimensions(el, 20, 50).then(function() {
                var chart = echarts.init(el, 'dark');
                self._chartInstances[c.id] = chart;

                var useSample = !qData || !qData.labels || !qData.labels.length;
                var labels = useSample ? ['Jan','Feb','Mar','Apr','May','Jun'] : qData.labels;
                var values = useSample ? [120, 200, 150, 80, 70, 110] : qData.values;
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
                    opt.series = [{ type: 'pie', radius: ['40%', '65%'], data: pieData, label: { show: true, position: 'outside', fontSize: 10, color: '#94a3b8' } }];
                } else {
                    opt.xAxis = { type: 'category', data: labels };
                    opt.yAxis = { type: 'value' };
                    opt.series = [{ type: c.type, data: values, smooth: true }];
                }

                chart.setOption(opt);
                var ro = new ResizeObserver(function() { chart.resize(); });
                ro.observe(el);
                self._chartObservers[c.id] = ro;
            });
        },

        _waitForDimensions(el, retries, delayMs) {
            return new Promise(function(resolve) {
                function check(remaining) {
                    if (el.offsetWidth > 0 && el.offsetHeight > 0) { resolve(); return; }
                    if (remaining <= 0) { resolve(); return; }
                    setTimeout(function() { check(remaining - 1); }, delayMs);
                }
                check(retries);
            });
        },

        _initVisibilityHandler() {
            if (this._visibilityBound) return;
            this._visibilityBound = true;
            var self = this;
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden && self._chartInstances) {
                    setTimeout(function() {
                        Object.values(self._chartInstances).forEach(function(ch) {
                            if (ch && !ch.isDisposed()) ch.resize();
                        });
                    }, 200);
                }
            });
        },

        // ===== Table Rendering =====
        renderTable(c, data) {
            if (!data || !data.columns || !data.rows) {
                if (data && data.error) {
                    var container = document.getElementById('table-container-' + c.id);
                    if (container) container.innerHTML = '<div style="text-align:center;padding:20px;color:#f87171;font-size:12px;">' + data.error + '</div>';
                }
                return;
            }
            var container = document.getElementById('table-container-' + c.id);
            if (!container) return;
            container.innerHTML = '<table class="data-table"><thead><tr>' +
                data.columns.map(function(col) { return '<th>' + (col || '') + '</th>'; }).join('') +
                '</tr></thead><tbody>' +
                data.rows.map(function(row) { return '<tr>' + (row || []).map(function(cell) { return '<td>' + (cell !== null && cell !== undefined ? cell : '') + '</td>'; }).join('') + '</tr>'; }).join('') +
                '</tbody></table>' +
                '<div style="padding:6px 12px;font-size:11px;color:#64748b;border-top:1px solid #334155;">' + data.row_count + ' rows</div>';
        },

        // ===== Data Fetching =====
        fetchCardData() {
            if (!this.activeClient || !this.dashboard || !this.dashboard.cards) return;
            var cardsWithQueries = this.dashboard.cards.filter(function(c) { return c.query; });
            if (!cardsWithQueries.length) return;
            var self = this;
            this.dataLoading = true;

            fetch('/dashboard-builder/batch-query', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ client_id: this.activeClient, cards: cardsWithQueries, filters: this.activeFilters })
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                self.dataLoading = false;
                var data = resp.data || {};
                if (Array.isArray(data)) {
                    // Error path: array of {card_id, error} objects
                    data.forEach(function(item) {
                        var c = self.dashboard.cards.find(function(cc) { return cc.id === item.card_id; });
                        if (!c) return;
                        if (item.error) { c.dataError = item.error; return; }
                        c.dataError = null;
                        c._data = item;
                        if (c.type === 'kpi' || c.type === 'stat') {
                            var el = document.querySelector('[data-card-id="' + c.id + '"] .stat-value');
                            if (el && item.value !== undefined) {
                                var v = item.value;
                                el.textContent = v >= 1000 ? (v / 1000).toFixed(1) + 'K' : v.toLocaleString();
                                el.className = 'stat-value ' + (v > 1000 ? 'green' : v > 500 ? 'amber' : 'red');
                            }
                        } else if (['line','bar','donut','funnel','gauge','heatmap','pie','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
                            self.renderChart(c, item);
                        } else if (c.type === 'table') {
                            self.renderTable(c, item);
                        }
                    });
                } else {
                    // Success path: object keyed by card ID
                    Object.keys(data).forEach(function(key) {
                        var item = data[key];
                        item.card_id = key;
                        var c = self.dashboard.cards.find(function(cc) { return cc.id === key; });
                        if (!c) return;
                        if (item.error) { c.dataError = item.error; return; }
                        c.dataError = null;
                        c._data = item;
                        if (c.type === 'kpi' || c.type === 'stat') {
                            var el = document.querySelector('[data-card-id="' + c.id + '"] .stat-value');
                            if (el && item.value !== undefined) {
                                var v = item.value;
                                el.textContent = v >= 1000 ? (v / 1000).toFixed(1) + 'K' : v.toLocaleString();
                                el.className = 'stat-value ' + (v > 1000 ? 'green' : v > 500 ? 'amber' : 'red');
                            }
                        } else if (['line','bar','donut','funnel','gauge','heatmap','pie','combo','scatter','area','radar'].indexOf(c.type) >= 0) {
                            self.renderChart(c, item);
                        } else if (c.type === 'table') {
                            self.renderTable(c, item);
                        }
                    });
                }
            })
            .catch(function(e) {
                console.error('Query fetch failed:', e.message);
                self.dataLoading = false;
                cardsWithQueries.forEach(function(c) {
                    c.dataError = 'Failed to load data. Client database may not be configured.';
                });
            });
        },

        // ===== Auto-placement =====
        autoPlaceCards(cards) {
            // Track occupied cells in a 2D grid
            var occupied = {}; // key: "row,col"
            function isOccupied(r, c) { return occupied[r + ',' + c]; }
            function markOccupied(r, c, h, w) {
                for (var rr = r; rr < r + h; rr++) {
                    for (var cc = c; cc < c + w; cc++) {
                        occupied[rr + ',' + cc] = true;
                    }
                }
            }
            function findNextPosition(w, h) {
                var r = 0, c = 0;
                while (true) {
                    // Check if position (r, c) with size w×h fits
                    if (c + w > 4) { c = 0; r++; continue; }
                    var fits = true;
                    for (var rr = r; rr < r + h; rr++) {
                        for (var cc = c; cc < c + w; cc++) {
                            if (isOccupied(rr, cc)) { fits = false; break; }
                        }
                        if (!fits) break;
                    }
                    if (fits) return { row: r, col: c };
                    c++;
                    if (c >= 4) { c = 0; r++; }
                }
            }
            cards.forEach(function(c) {
                var pos = findNextPosition(c.w, c.h);
                c.col = pos.col;
                c.row = pos.row;
                markOccupied(pos.row, pos.col, c.h, c.w);
            });
        },

        // ===== Card Movement =====
        moveCard(dir) {
            if (!this.cardEditModal || !this.dashboard) return;
            var cards = this.dashboard.cards;
            var idx = cards.findIndex(function(c) { return c.id === this.cardEditModal.id; }.bind(this));
            if (idx < 0) return;
            var card = cards[idx];
            var newX = card.col || 0, newY = card.row || 0;
            if (dir === 'up') newY--;
            else if (dir === 'down') newY++;
            else if (dir === 'left') newX--;
            else if (dir === 'right') newX++;
            if (newX < 0 || newY < 0 || newX + card.w > 4) return;

            // Left/right: swap with occupying card
            if (dir === 'left' || dir === 'right') {
                var occupying = cards.find(function(c) {
                    return c.id !== card.id && c.col === newX && c.row === newY;
                });
                if (occupying) {
                    occupying.col = card.col;
                    occupying.row = card.row;
                }
            }
            // Down: find next empty row
            if (dir === 'down') {
                while (cards.find(function(c) { return c.id !== card.id && c.col === newX && c.row === newY; })) newY++;
            }
            // Up: find next empty row
            if (dir === 'up') {
                while (newY >= 0 && cards.find(function(c) { return c.id !== card.id && c.col === newX && c.row === newY; })) newY--;
                if (newY < 0) return; // no empty slot above, don't move
            }

            card.col = newX;
            card.row = newY;
            this.addRevision(this.dashboard);
            var self = this;
            this.$nextTick(function() { self.renderGrid(); });
        },

        // ===== Card Editing =====
        editCard(id) {
            var card = (this.dashboard.cards || []).find(function(c) { return c.id === id; });
            if (!card) return;
            this.cardEditModal = card;
        },

        resizeCardWidth(n) {
            if (!this.cardEditModal || !this.dashboard) return;
            var self = this;
            var idx = this.dashboard.cards.findIndex(function(c) { return c.id === self.cardEditModal.id; });
            if (idx < 0) return;
            var card = this.dashboard.cards[idx];
            card.w = n;
            card.colspan = n;
            this.cardEditModal.w = n;
            this.addRevision(this.dashboard);
            this.$nextTick(function() { self.renderGrid(); });
        },

        resizeCardHeight(n) {
            if (!this.cardEditModal || !this.dashboard) return;
            var self = this;
            var idx = this.dashboard.cards.findIndex(function(c) { return c.id === self.cardEditModal.id; });
            if (idx < 0) return;
            var card = this.dashboard.cards[idx];
            card.h = n;
            card.rowspan = n;
            this.cardEditModal.h = n;
            this.addRevision(this.dashboard);
            this.$nextTick(function() { self.renderGrid(); });
        },

        deleteCard() {
            if (!this.cardEditModal || !this.dashboard) return;
            var self = this;
            var idx = this.dashboard.cards.findIndex(function(c) { return c.id === self.cardEditModal.id; });
            if (idx < 0) return;
            var deletedId = this.cardEditModal.id;
            this.dashboard.cards.splice(idx, 1);
            this.cardEditModal = { id: deletedId, type: 'deleted', title: 'Card Deleted', _deleted: true };
            this.addRevision(this.dashboard);
            this.$nextTick(function() { self.renderGrid(); });
        },

        // ===== Revisions =====
        addRevision(dash) {
            this.revisions = this.revisions.slice(0, this.revisionIndex + 1);
            this.revisions.push(JSON.parse(JSON.stringify(dash)));
            this.revisionIndex = this.revisions.length - 1;
        },

        undoDashboard() {
            if (this.revisionIndex <= 0) return;
            var self = this;
            this.revisionIndex--;
            this.dashboard = JSON.parse(JSON.stringify(this.revisions[this.revisionIndex]));
            this.$nextTick(function() { self.renderGrid(); });
        },

        showRevisions() {
            this.revisionInfo = this.revisions.length + ' revisions available (index: ' + this.revisionIndex + ')';
            var self = this;
            setTimeout(function() { self.revisionInfo = ''; }, 4000);
        },

        // ===== Filters =====
        setFilter(id, value) {
            this.activeFilters[id] = value;
            if (this.dashboard) this.fetchCardData();
        },

        clearFilters() {
            this.activeFilters = {};
            if (this.dashboard) this.fetchCardData();
        },

        // ===== UI Toggles =====
        toggleChat() { this.chatVisible = !this.chatVisible; },

        toggleJSON() {
            if (this.showJSONPanel) { this.showJSONPanel = false; return; }
            if (!this.dashboard) {
                this.jsonEditorText = '{\n  "title": "New Dashboard",\n  "cards": []\n}';
            } else {
                this.jsonEditorText = JSON.stringify(this.dashboard, null, 2);
            }
            this.showJSONPanel = true;
        },

        toggleSchema() {
            this.showSchema = !this.showSchema;
            if (this.showSchema && !this.schemaData) {
                this.loadSchema();
            }
        },

        loadSchema() {
            if (!this.activeClient) return;
            var self = this;
            this.schemaLoading = true;
            fetch('/dashboard-builder/schema/' + this.activeClient)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    self.schemaData = data;
                    self.schemaLoading = false;
                })
                .catch(function(e) {
                    self.schemaData = { error: 'Failed to load schema' };
                    self.schemaLoading = false;
                });
        },

        applyJSONEdit() {
            try {
                var json = JSON.parse(this.jsonEditorText);
                if (json.dashboard) json = json.dashboard;
                if (!json.cards || !json.cards.length) { this.jsonError = 'JSON must have a "cards" array.'; return; }
                var self = this;
                json.cards = json.cards.map(function(c, i) {
                    if (!c.id) c.id = 'card-' + i;
                    if (!c.w) c.w = c.colspan || 3;
                    if (!c.h) c.h = c.rowspan || 3;
                    return c;
                });
                this.addRevision(this.dashboard);
                this.dashboard = json;
                this.addRevision(json);
                this.showJSONPanel = false;
                this.$nextTick(function() { self.renderGrid(); });
            } catch(e) { this.jsonError = 'Invalid JSON: ' + e.message; }
        },

        // ===== Save =====
        saveDashboard() {
            if (!this.dashboard) return;
            var self = this;
            this.saveStatus = 'saving';
            fetch('/dashboard-builder/dashboards', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ client_id: this.activeClient, dashboard: this.dashboard })
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.error) { self.saveStatus = 'error'; setTimeout(function() { self.saveStatus = ''; }, 3000); return; }
                self.saveStatus = 'saved';
                self.dashboardId = resp.id || self.dashboardId;
                self.publicUrl = resp.public_url || '';
                if (resp.id) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('project', resp.id);
                    window.history.replaceState({}, '', url.toString());
                }
                setTimeout(function() { self.saveStatus = ''; }, 3000);
            })
            .catch(function(e) {
                self.saveStatus = 'error';
                setTimeout(function() { self.saveStatus = ''; }, 3000);
            });
        },

        // ===== Client Switch =====
        switchClient() {
            window.location.href = '/dashboard-builder?client=' + this.activeClient;
        },

        // ===== Modal Drag =====
        startModalDrag(e) {
            this.modalDrag = true;
            this.modalDragStartX = e.clientX - this.modalX;
            this.modalDragStartY = e.clientY - this.modalY;
        },
        doModalDrag(e) {
            if (!this.modalDrag) return;
            this.modalX = e.clientX - this.modalDragStartX;
            this.modalY = e.clientY - this.modalDragStartY;
        },
        stopModalDrag() {
            this.modalDrag = false;
        },

        // ===== Sidebar Resize =====
        startResize(e) {
            this.sidebarResizing = true;
            this.sidebarStartX = e.clientX;
            this.sidebarStartWidth = this.sidebarWidth;
            e.preventDefault();
        },
        doResize(e) {
            if (!this.sidebarResizing) return;
            var newWidth = this.sidebarStartWidth + (e.clientX - this.sidebarStartX);
            if (newWidth < 280) newWidth = 280;
            if (newWidth > 700) newWidth = 700;
            this.sidebarWidth = newWidth;
        },
        stopResize() {
            this.sidebarResizing = false;
        }
    };
}
