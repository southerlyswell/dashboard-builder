<?php $__env->startSection('page_title', 'Dashboard Builder'); ?>
<?php $__env->startSection('page_slug', 'dashboard-builder'); ?>

<?php $__env->startSection('styles'); ?>
<link rel="stylesheet" href="/css/dashboard-builder.css">
<style>
    body { overflow: auto; }
    .home-page { max-width: 1200px; margin: 0 auto; padding: 24px; }

    /* Header */
    .home-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .home-header h1 { font-size: 24px; color: #f1f5f9; font-weight: 700; margin: 0; }
    .home-header .actions { display: flex; gap: 10px; }
    .btn-primary { background: #FB923C; color: #0f172a; border: none; padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-primary:hover { background: #f97316; }
    .btn-outline { background: transparent; color: #94a3b8; border: 1px solid #334155; padding: 8px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
    .btn-outline:hover { border-color: #FB923C; color: #FB923C; }

    /* Client Grid */
    .clients-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 18px; }

    /* Client Card */
    .client-card {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 14px;
        padding: 0;
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .client-card:hover { border-color: #FB923C; box-shadow: 0 0 0 1px rgba(251, 146, 60, 0.15); }

    .client-card-top {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 18px 20px 12px;
    }
    .client-name { font-size: 18px; font-weight: 700; color: #f1f5f9; margin-bottom: 3px; }
    .client-category {
        display: inline-block; font-size: 10px; letter-spacing: 0.8px; text-transform: uppercase;
        color: #FB923C; background: rgba(251,146,60,0.1);
        padding: 2px 10px; border-radius: 10px; font-weight: 600;
    }
    .client-status {
        font-size: 10px; padding: 4px 12px; border-radius: 12px; font-weight: 600;
        letter-spacing: 0.5px; text-transform: uppercase;
    }
    .client-status.active { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
    .client-status.inactive { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
    .client-status.archived { background: rgba(248, 113, 113, 0.12); color: #f87171; }

    /* Client Details — two columns */
    .client-details {
        display: grid; grid-template-columns: 1fr 1fr;
        padding: 0 20px 14px; gap: 6px 20px;
        font-size: 12px; color: #94a3b8;
    }
    .client-detail-item {
        display: flex; align-items: center; gap: 6px;
    }
    .client-detail-item .icon {
        color: #475569; font-size: 12px; width: 16px; text-align: center; flex-shrink: 0;
    }
    .client-detail-item .value {
        color: #cbd5e1; font-weight: 500;
    }
    .client-detail-item .label-text {
        color: #475569; min-width: 55px;
    }

    /* Client Meta */
    .client-meta {
        display: flex; gap: 20px;
        padding: 10px 20px;
        border-top: 1px solid #1e293b;
        background: rgba(15, 23, 42, 0.5);
        font-size: 11px; color: #64748b;
    }
    .client-meta-item strong { color: #e2e8f0; font-weight: 600; }

    /* Action Buttons */
    .client-actions {
        display: flex; gap: 0;
        border-top: 1px solid #334155;
    }
    .client-actions button, .client-actions a {
        flex: 1; text-align: center; padding: 10px 8px;
        background: none; border: none; border-right: 1px solid #334155;
        color: #94a3b8; font-size: 11px; font-weight: 500; cursor: pointer;
        text-decoration: none; display: inline-flex; align-items: center;
        justify-content: center; gap: 4px; transition: all 0.15s;
    }
    .client-actions button:last-child, .client-actions a:last-child { border-right: none; }
    .client-actions button:hover, .client-actions a:hover {
        color: #FB923C; background: rgba(251,146,60,0.06);
    }
    .client-actions .action-danger { color: #f87171; }
    .client-actions .action-danger:hover { background: rgba(248,113,113,0.06); }
    .client-actions .action-green { color: #4ade80; }
    .client-actions .action-green:hover { background: rgba(74,222,128,0.06); }

    /* Empty State */
    .empty-state { text-align: center; padding: 80px 20px; }
    .empty-state h3 { font-size: 20px; color: #94a3b8; margin-bottom: 8px; }
    .empty-state p { color: #64748b; margin-bottom: 20px; }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 100; }
    .modal-box { background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 28px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; }
    .modal-box h2 { font-size: 18px; color: #FB923C; margin-bottom: 18px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-group label { display: block; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .form-group input, .form-group select { width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 8px 12px; color: #e2e8f0; font-size: 13px; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #FB923C; }
    .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    [x-cloak] { display: none !important; }
    .modal-overlay { display: none; }
    .modal-overlay[style*="display: flex"], .modal-overlay[style*="display:flex"] { display: flex !important; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="home-page" x-data="clientsPage()" x-init="init()" x-cloak>
    <div class="home-header">
        <h1>Clients</h1>
        <div class="actions">
            <a href="/dashboard-builder?client=0" class="btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Dashboard Builder
            </a>
            <button class="btn-primary" @click="showAddClient = true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Client
            </button>
        </div>
    </div>

    <!-- Client Cards -->
    <div class="clients-grid" x-show="clients.length > 0">
        <template x-for="client in clients" :key="client.id">
            <div class="client-card">
                <!-- Top: Name + Status -->
                <div class="client-card-top">
                    <div>
                        <div class="client-name" x-text="client.name"></div>
                        <span class="client-category" x-text="client.industry || 'General'"></span>
                    </div>
                    <span class="client-status" :class="client.is_active ? 'active' : 'inactive'" x-text="client.is_active ? 'Active' : 'Inactive'"></span>
                </div>

                <!-- Details: Two Columns -->
                <div class="client-details">
                    <div class="client-detail-item" x-show="client.contact_name">
                        <span class="icon">&#128100;</span>
                        <span class="value" x-text="client.contact_name"></span>
                    </div>
                    <div class="client-detail-item" x-show="client.contact_phone">
                        <span class="icon">&#128222;</span>
                        <span class="value" x-text="client.contact_phone"></span>
                    </div>
                    <div class="client-detail-item" x-show="client.contact_email">
                        <span class="icon">&#9993;</span>
                        <span class="value" x-text="client.contact_email" style="font-size:11px;"></span>
                    </div>
                    <div class="client-detail-item" x-show="client.city">
                        <span class="icon">&#128205;</span>
                        <span class="value" x-text="client.city + (client.province ? ', ' + client.province : '')" style="font-size:11px;"></span>
                    </div>
                </div>

                <!-- Meta -->
                <div class="client-meta">
                    <span class="client-meta-item">&#128202; <strong x-text="client.dashboard_count"></strong> dashboards</span>
                    <span class="client-meta-item">&#128197; Added <strong x-text="client.created_at"></strong></span>
                </div>

                <!-- Action Buttons -->
                <div class="client-actions">
                    <a :href="'/dashboard-builder/clients/' + client.id" class="action-edit">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                    </a>
                    <a :href="'/dashboard-builder?client=' + client.id" class="action-new">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        New
                    </a>
                    <button @click="deleteClient(client.id)" class="action-danger">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Archive
                    </button>
                    <a :href="'#'" @click.prevent="sendToClient(client)" class="action-green">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Send
                    </a>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div class="empty-state" x-show="clients.length === 0 && !loading">
        <h3>No clients yet</h3>
        <p>Add your first client to start building dashboards</p>
        <button class="btn-primary" @click="showAddClient = true">+ Add Client</button>
    </div>

    <!-- Add Client Modal -->
    <div class="modal-overlay" x-show="showAddClient" @click.self="showAddClient = false" x-cloak>
        <div class="modal-box">
            <h2>Add New Client</h2>
            <form @submit.prevent="saveClient()">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Company Name *</label>
                        <input type="text" x-model="form.name" required placeholder="Acme Corp">
                    </div>
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" x-model="form.contact_name" placeholder="John Smith">
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <select x-model="form.industry">
                            <option value="">Select...</option>
                            <option>Retail</option>
                            <option>Education</option>
                            <option>Healthcare</option>
                            <option>Finance</option>
                            <option>Manufacturing</option>
                            <option>Non-Profit</option>
                            <option>Government</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" x-model="form.contact_email" placeholder="john@acme.co.za">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" x-model="form.contact_phone" placeholder="011 123 4567">
                    </div>
                    <div class="form-group full">
                        <label>Address Line 1</label>
                        <input type="text" x-model="form.address_line1" placeholder="123 Main Street">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" x-model="form.city" placeholder="Johannesburg">
                    </div>
                    <div class="form-group">
                        <label>Province</label>
                        <input type="text" x-model="form.province" placeholder="Gauteng">
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" x-model="form.postal_code" placeholder="2000">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-outline" @click="showAddClient = false">Cancel</button>
                    <button type="submit" class="btn-primary">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="/js/clients.js?v=3"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\dashboard-builder\resources\views/dashboard-builder/projects.blade.php ENDPATH**/ ?>