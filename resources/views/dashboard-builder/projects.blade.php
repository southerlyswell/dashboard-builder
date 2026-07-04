@extends('layouts.app')

@section('page_title', 'Dashboard Builder')
@section('page_slug', 'dashboard-builder')

@section('styles')
<link rel="stylesheet" href="/css/dashboard-builder.css">
<style>
    body { overflow: auto; }
    .home-page { max-width: 1200px; margin: 0 auto; padding: 24px; }
    .home-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .home-header h1 { font-size: 24px; color: #f1f5f9; font-weight: 700; }
    .home-header .actions { display: flex; gap: 10px; }
    .btn-primary { background: #FB923C; color: #0f172a; border: none; padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-primary:hover { background: #f97316; }
    .btn-outline { background: transparent; color: #94a3b8; border: 1px solid #334155; padding: 8px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; cursor: pointer; }
    .btn-outline:hover { border-color: #FB923C; color: #FB923C; }

    .clients-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
    .client-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; transition: border-color 0.15s; }
    .client-card:hover { border-color: #FB923C; }
    .client-card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
    .client-name { font-size: 17px; font-weight: 700; color: #f1f5f9; margin-bottom: 2px; }
    .client-industry { font-size: 12px; color: #FB923C; text-transform: uppercase; letter-spacing: 0.5px; }
    .client-status { font-size: 10px; padding: 3px 8px; border-radius: 12px; font-weight: 600; }
    .client-status.active { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
    .client-status.inactive { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
    .client-contact { font-size: 12px; color: #94a3b8; margin-bottom: 8px; display: flex; flex-direction: column; gap: 3px; }
    .client-contact span { display: flex; align-items: center; gap: 6px; }
    .client-contact .label { color: #475569; width: 60px; }
    .client-meta { display: flex; gap: 16px; padding-top: 10px; border-top: 1px solid #334155; margin-top: 10px; }
    .client-meta-item { font-size: 12px; color: #64748b; }
    .client-meta-item strong { color: #e2e8f0; font-weight: 600; }
    .client-actions { display: flex; gap: 8px; margin-top: 14px; }
    .client-actions a, .client-actions button { font-size: 12px; padding: 6px 14px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #94a3b8; cursor: pointer; text-decoration: none; }
    .client-actions a:hover, .client-actions button:hover { border-color: #FB923C; color: #FB923C; }
    .client-actions .btn-danger:hover { border-color: #f87171; color: #f87171; }

    .empty-state { text-align: center; padding: 80px 20px; }
    .empty-state h3 { font-size: 20px; color: #94a3b8; margin-bottom: 8px; }
    .empty-state p { color: #64748b; margin-bottom: 20px; }

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
@endsection

@section('content')
<div class="home-page" x-data="clientsPage()" x-init="init()" x-cloak>
    <div class="home-header">
        <h1>Dashboard Builder</h1>
        <div class="actions">
            <a href="/dashboard-builder?client=0" class="btn-outline">Open Builder</a>
            <button class="btn-primary" @click="showAddClient = true">+ Add Client</button>
        </div>
    </div>

    <div class="clients-grid" x-show="clients.length > 0">
        <template x-for="client in clients" :key="client.id">
            <div class="client-card">
                <div class="client-card-header">
                    <div>
                        <div class="client-name" x-text="client.name"></div>
                        <div class="client-industry" x-text="client.industry || 'General'"></div>
                    </div>
                    <span class="client-status" :class="client.is_active ? 'active' : 'inactive'" x-text="client.is_active ? 'Active' : 'Inactive'"></span>
                </div>
                <div class="client-contact">
                    <span x-show="client.contact_name"><span class="label">Contact</span> <span x-text="client.contact_name"></span></span>
                    <span x-show="client.contact_email"><span class="label">Email</span> <span x-text="client.contact_email"></span></span>
                    <span x-show="client.contact_phone"><span class="label">Phone</span> <span x-text="client.contact_phone"></span></span>
                    <span x-show="client.city"><span class="label">Location</span> <span x-text="client.city + (client.province ? ', ' + client.province : '')"></span></span>
                </div>
                <div class="client-meta">
                    <span class="client-meta-item"><strong x-text="client.dashboard_count"></strong> dashboards</span>
                    <span class="client-meta-item">Added <strong x-text="client.created_at"></strong></span>
                </div>
                <div class="client-actions">
                    <a :href="'/dashboard-builder/clients/' + client.id" class="btn-view">View Dashboards</a>
                    <a :href="'/dashboard-builder?client=' + client.id" class="btn-new">New Dashboard</a>
                    <button class="btn-danger" @click="deleteClient(client.id)">Delete</button>
                </div>
            </div>
        </template>
    </div>

    <div class="empty-state" x-show="clients.length === 0 && !loading">
        <h3>No clients yet</h3>
        <p>Add your first client to start building dashboards</p>
        <button class="btn-primary" @click="showAddClient = true">+ Add Client</button>
    </div>

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
<script src="/js/clients.js?v=2"></script>
@endsection
