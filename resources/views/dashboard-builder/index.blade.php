<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Builder — {{ $activeClient?->name ?? 'ACFS' }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
    <link rel="stylesheet" href="/css/dashboard-builder.css">
</head>
<body class="builder-body">
<div class="app" x-data="dashboardBuilder()">

    <!-- SIDEBAR: AI Chat -->
    <div class="sidebar" x-ref="sidebar" x-show="chatVisible" :style="'width:' + sidebarWidth + 'px'" @mouseup="stopResize()" @mouseleave="stopResize()" @mousemove="doResize($event)">
        <div class="sidebar-resizer" @mousedown="startResize($event)" @dblclick="sidebarWidth = 420"></div>
        <div class="sidebar-header">
            <h2>Dashboard Builder <span style="color:#64748b;font-size:11px;font-weight:400;">[rev 13]</span></h2>
            <p>AI-powered, zero code</p>
            <select class="client-select" x-model="activeClient" @change="switchClient()">
                <option value="">Select a client...</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ ($activeClient && $activeClient->id === $client->id) ? 'selected' : '' }}>
                        {{ $client->name }}
                    </option>
                @endforeach
            </select>
            <div class="ai-status-bar">
                <span class="ai-dot" :class="aiChecking ? 'grey' : (aiOnline ? 'green' : 'red')"></span>
                <span class="ai-label" :class="aiChecking ? 'grey' : (aiOnline ? 'green' : 'red')" x-text="aiStatusText()"></span>
            </div>
        </div>

        <div class="chat-area">
            <div class="chat-messages" x-ref="chatBox" id="chatBox">
                <template x-for="msg in messages" :key="msg.id">
                    <div class="msg" :class="msg.role">
                        <div class="bubble" x-html="formatMessage(msg.content)"></div>
                    </div>
                </template>
                <div x-show="loading" class="chat-loading">
                    <span class="loading"></span>
                    <span class="loading-text">AI is thinking...</span>
                </div>
            </div>

            <div class="quick-actions">
                <button class="quick-btn" @click="toggleSchema()">Schema</button>
                <button class="quick-btn" @click="sendQuick('Build a management overview dashboard with KPIs, trends, and breakdowns')">Overview</button>
                <button class="quick-btn" @click="sendQuick('Show me the JSON for this dashboard')">JSON</button>
            </div>

            <form class="chat-input" @submit.prevent="sendMessage()">
                <textarea x-model="chatInput" placeholder="Describe the dashboard you want..." @keydown.enter.prevent="sendMessage()" :disabled="loading" rows="2"></textarea>
                <button type="submit" :disabled="loading || !chatInput">Send</button>
            </form>
        </div>
    </div>

    <!-- MAIN: Preview -->
    <div class="main-content">
        <div class="main-header">
            <h2 x-text="showSchema ? 'Database Schema' : (dashboard?.title || 'Dashboard Preview')"></h2>
            <div class="actions">
                <button class="btn btn-outline" @click="showSchema = false" x-show="showSchema">Back</button>
                <button class="btn btn-outline" @click="toggleChat()" x-show="dashboard && !showSchema" x-text="chatVisible ? 'Chat' : 'Chat'"></button>
                <button class="btn btn-outline" @click="undoDashboard()" x-show="dashboard && !showSchema && revisions.length > 1">Undo</button>
                <button class="btn btn-outline" @click="showRevisions()" x-show="dashboard && !showSchema && revisions.length > 1">Revisions</button>
                <span x-show="revisionInfo" style="color:#FB923C;font-size:11px;margin-left:8px;" x-text="revisionInfo"></span>
                <button class="btn btn-outline" @click="toggleJSON()" x-show="dashboard && !showSchema" x-text="showJSONPanel ? 'Close JSON' : 'JSON'"></button>
                <a class="btn btn-outline" href="/dashboard-builder/projects" style="text-decoration:none;">Projects</a>
                <button class="btn-save" @click="saveDashboard()" :disabled="saveStatus === 'saving'" x-show="dashboard && !showSchema">
                    <span x-show="saveStatus !== 'saving' && saveStatus !== 'saved' && saveStatus !== 'error'">Save</span>
                    <span x-show="saveStatus === 'saving'">Saving...</span>
                    <span x-show="saveStatus === 'saved'" style="color:#4ade80;">&#10003; Saved</span>
                    <span x-show="saveStatus === 'error'" style="color:#f87171;">&#10007; Failed</span>
                </button>
            </div>
        </div>

        <div class="preview-area">
            <!-- Schema view -->
            <div x-show="showSchema" class="schema-view">
                <div x-show="schemaLoading" style="color:#FB923C;padding:20px;">Loading schema...</div>
                <template x-if="!schemaLoading && schemaData && schemaData.error">
                <div style="color:#f87171;padding:20px;">
                    <strong>Unable to load schema:</strong> <span x-text="typeof schemaData.error === 'string' ? schemaData.error : 'Unknown error'"></span>
                </div>
                </template>
                <template x-if="!schemaLoading && schemaData && schemaData.tables">
                <div style="padding:16px;overflow-y:auto;max-height:calc(100vh - 120px);">
                    <template x-for="table in schemaData.tables" :key="table.name">
                        <div style="margin-bottom:12px;background:#1e293b;border:1px solid #334155;border-radius:8px;overflow:hidden;">
                            <div style="padding:8px 14px;background:#334155;color:#FB923C;font-weight:600;font-size:13px;" x-text="table.name"></div>
                            <div style="padding:6px 14px;">
                                <template x-for="col in table.columns" :key="col">
                                    <div style="color:#94a3b8;font-size:12px;padding:2px 0;" x-text="col"></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                </template>
                <div x-show="!schemaLoading && !schemaData" style="color:#64748b;padding:20px;">Click "Schema" to view the client database structure.</div>
            </div>

            <!-- Empty state -->
            <div class="empty-state" x-show="!dashboard && !showSchema">
                <h3>No dashboard yet</h3>
                <p>Use the chat on the left to describe what you want. The AI will build it here.</p>
                <p class="empty-hint">Try: "Build me an attendance dashboard with KPI cards, a line chart of daily attendance, and branch breakdowns"</p>
            </div>

            <!-- Filter cluster -->
            <!-- Progress overlay -->
            <div x-show="loading" class="progress-overlay">
                <div class="loading loading-lg"></div>
                <p class="progress-title">Building your dashboard</p>
                <p class="progress-status" x-text="aiStatus"></p>
            </div>

            <!-- Dashboard grid -->
            <div class="dashboard-grid-wrap" x-ref="gridContainer" style="display:none;" id="gridContainer"></div>
        </div>
    </div>

    <!-- Card Edit Modal -->
    <div x-show="cardEditModal" @keydown.escape.window="cardEditModal = null" class="modal-overlay" x-cloak @mousemove="doModalDrag($event)" @mouseup="stopModalDrag()" @mouseleave="stopModalDrag()">
        <div @click.stop class="modal-card" :style="'transform: translate(' + modalX + 'px, ' + modalY + 'px);'">
            <div class="modal-header" style="position:relative;cursor:grab;" @mousedown="startModalDrag($event)" :class="{'cursor-grabbing': modalDrag}">
                <button @click="cardEditModal = null" style="position:absolute;top:12px;right:14px;background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;line-height:1;padding:0 4px;">&times;</button>
                <div class="modal-type" x-text="cardEditModal?.type || 'Card'"></div>
                <div class="modal-title" x-text="cardEditModal?.title || cardEditModal?.id || 'Untitled'"></div>
                <div class="modal-id" x-text="'ID: ' + (cardEditModal?.id || '—')"></div>
            </div>
            <div class="modal-body">
                <!-- Deleted state -->
                <div x-show="cardEditModal?._deleted" style="text-align:center;padding:20px 0;color:#64748b;">
                    <div style="font-size:14px;color:#f87171;margin-bottom:8px;">This card has been deleted</div>
                    <div style="font-size:12px;">Click any visible card to edit it</div>
                </div>
                <!-- Normal controls -->
                <div x-show="!cardEditModal?._deleted">
                    <div class="modal-section-label">Width (columns)</div>
                    <div class="modal-width-row">
                    <template x-for="n in [1,2,3,4]">
                        <button @click="resizeCardWidth(n)" class="width-btn" :class="(cardEditModal?.w || cardEditModal?.colspan || 2) === n ? 'width-btn-active' : ''" x-text="n"></button>
                    </template>
                </div>
                <div class="modal-section-label">Height (rows × 50px)</div>
                <div class="modal-height-row">
                    <template x-for="n in [1,2,3,4,5,6,7,8]">
                        <button @click="resizeCardHeight(n)" class="height-btn" :class="(cardEditModal?.h || cardEditModal?.rowspan || 3) === n ? 'height-btn-active' : ''" x-text="n"></button>
                    </template>
                </div>
                <div class="modal-section-label">Move</div>
                <div class="modal-move-row">
                    <button @click="moveToTop()" class="move-btn-sm" title="Move to top">⏫</button>
                    <button @click="moveCard('up')" class="move-btn" title="Move up">↑</button>
                    <button @click="moveCard('down')" class="move-btn" title="Move down">↓</button>
                    <button @click="moveToBottom()" class="move-btn-sm" title="Move to bottom">⏬</button>
                </div>
                <div class="modal-move-row" style="margin-top:6px;">
                    <button @click="moveCard('left')" class="move-btn" title="Move left">←</button>
                    <button @click="moveCard('right')" class="move-btn" title="Move right">→</button>
                </div>
                <div class="modal-row-input-row">
                    <label class="modal-row-label">Row</label>
                    <input type="number" min="0" max="999" class="modal-row-input"
                        x-model.number="cardEditModal.row"
                        @change="moveToRow(cardEditModal.row)">
                </div>
                <div class="modal-action-row">
                    <button @click="deleteCard()" class="btn-delete">Delete Card</button>
                    <button @click="cardEditModal = null" class="btn-close">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JSON Editor Overlay -->
    <div x-show="showJSONPanel" @click.self="showJSONPanel = false" @keydown.escape.window="showJSONPanel = false" class="json-overlay" x-cloak>
        <div @click.stop class="json-panel">
            <div class="json-header">
                <span class="json-title">Dashboard JSON</span>
                <div class="json-actions">
                    <button @click="applyJSONEdit()" class="btn btn-green btn-sm">Apply</button>
                    <button @click="showJSONPanel = false" class="btn btn-outline btn-sm">Close</button>
                </div>
            </div>
            <textarea x-model="jsonEditorText" class="json-editor" spellcheck="false"></textarea>
            <div x-show="jsonError" style="color:#f87171;padding:8px 14px;font-size:12px;" x-text="jsonError"></div>
            <div class="json-footer">Edit the JSON, then click Apply to update the dashboard preview</div>
        </div>
    </div>

</div>

<script src="/js/dashboard-builder.js?v=21"></script>
<script>window.ACFS_CONFIG = { clientId: '{{ $activeClient?->id }}' };</script>
</body>
</html>
