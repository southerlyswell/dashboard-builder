@extends('layouts.app')

@section('page_title', 'Clients')

@section('content')
<div x-data="clientsPage()" x-init="init()" x-cloak style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <div style="font-size:24px;color:#f1f5f9;font-weight:700;margin-bottom:4px;">Dashboard Builder</div>
            <div style="font-size:13px;color:#64748b;" x-text="clients.length + ' client' + (clients.length !== 1 ? 's' : '')"></div>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="/dashboard-builder?client=0" class="btn-ghost">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Dashboard Builder
            </a>
            <button class="btn-orange" @click="showAddClient = true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Client
            </button>
        </div>
    </div>

    <div class="clients-grid" x-show="clients.length > 0">
        <template x-for="client in clients" :key="client.id">
            <div class="client-card">
                <div class="card-top">
                    <div>
                        <div class="card-name" x-text="client.name"></div>
                        <span class="card-cat" x-text="client.industry || 'General'"></span>
                    </div>
                    <span class="card-status" :class="client.is_active ? 'active' : 'inactive'" x-text="client.is_active ? 'Active' : 'Inactive'"></span>
                </div>
                <div class="card-details">
                    <div class="detail-row" x-show="client.contact_name">
                        <span class="ico">&#9679;</span><span class="val" x-text="client.contact_name"></span>
                    </div>
                    <div class="detail-row" x-show="client.contact_phone">
                        <span class="ico">&#9743;</span><span class="val" x-text="client.contact_phone"></span>
                    </div>
                    <div class="detail-row" x-show="client.contact_email">
                        <span class="ico">&#9993;</span><span class="val" x-text="client.contact_email"></span>
                    </div>
                    <div class="detail-row" x-show="client.city">
                        <span class="ico">&#9679;</span><span class="val" x-text="client.city + (client.province ? ', ' + client.province : '')"></span>
                    </div>
                </div>
                <div class="card-meta">
                    <span>&#9679; <strong x-text="client.dashboard_count"></strong> dashboards</span>
                    <span>&#9679; Added <strong x-text="client.created_at"></strong></span>
                    <span>&#9679; Updated <strong x-text="client.updated_at"></strong></span>
                </div>
                <div class="card-actions">
                    <a :href="'/dashboard-builder/clients/' + client.id">&#9998; View</a>
                    <a :href="'/dashboard-builder?client=' + client.id">&#10011; New</a>
                    <button class="action-danger" @click="deleteClient(client.id)">&#10007; Archive</button>
                    <a href="#" @click.prevent="sendToClient(client)" class="action-green">&#9993; Send</a>
                </div>
            </div>
        </template>
    </div>

    <div class="empty-state" x-show="clients.length === 0 && !loading">
        <h3>No clients yet</h3>
        <p>Add your first client to start building dashboards</p>
        <button class="btn-orange" @click="showAddClient = true">+ Add Client</button>
    </div>

    <!-- Add Client Modal -->
    <div class="modal-overlay" x-show="showAddClient" @click.self="showAddClient = false" x-cloak style="display:none;">
        <div class="modal-box">
            <h2>Add New Client</h2>
            <form @submit.prevent="saveClient()">
                <div class="form-grid">
                    <div class="form-group full"><label>Company Name *</label><input type="text" x-model="form.name" required placeholder="Acme Corp"></div>
                    <div class="form-group"><label>Contact Person</label><input type="text" x-model="form.contact_name" placeholder="John Smith"></div>
                    <div class="form-group"><label>Industry</label>
                        <select x-model="form.industry">
                            <option value="">Select...</option>
                            <option>Education</option><option>Healthcare</option><option>Finance</option>
                            <option>Retail</option><option>Manufacturing</option><option>Non-Profit</option>
                            <option>Government</option><option>Other</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Email</label><input type="email" x-model="form.contact_email" placeholder="john@acme.co.za"></div>
                    <div class="form-group"><label>Phone</label><input type="text" x-model="form.contact_phone" placeholder="011 123 4567"></div>
                    <div class="form-group full"><label>Address</label><input type="text" x-model="form.address_line1" placeholder="123 Main Street"></div>
                    <div class="form-group"><label>City</label><input type="text" x-model="form.city" placeholder="Johannesburg"></div>
                    <div class="form-group"><label>Province</label><input type="text" x-model="form.province" placeholder="Gauteng"></div>
                    <div class="form-group"><label>Postal Code</label><input type="text" x-model="form.postal_code" placeholder="2000"></div>

                    <!-- Database Connection -->
                    <div class="form-group full" style="margin-top:8px;padding-top:14px;border-top:1px solid #334155;">
                        <label style="color:#FB923C;font-size:12px;">Database Connection (optional — for live querying)</label>
                    </div>
                    <div class="form-group"><label>DB Host</label><input type="text" x-model="form.db_host" placeholder="127.0.0.1"></div>
                    <div class="form-group"><label>DB Port</label><input type="number" x-model="form.db_port" placeholder="3306"></div>
                    <div class="form-group full"><label>Database Name</label><input type="text" x-model="form.db_database" placeholder="my_database"></div>
                    <div class="form-group"><label>DB Username</label><input type="text" x-model="form.db_username" placeholder="root"></div>
                    <div class="form-group"><label>DB Password</label><input type="password" x-model="form.db_password" placeholder="(password)"></div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-ghost" @click="showAddClient = false">Cancel</button>
                    <button type="submit" class="btn-orange">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="/js/clients.js?v=3"></script>
@endsection
