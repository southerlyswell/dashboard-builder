@extends('layouts.app')

@section('page_title', $client->name)

@section('content')
<div style="max-width:1200px;margin:0 auto;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <a href="{{ route('dashbuilder.projects') }}" class="btn-ghost">&#8592; All Clients</a>
        <div style="display:flex;gap:10px;">
            <button class="btn-ghost" onclick="document.getElementById('edit-client-modal').style.display='flex'">&#9998; Edit Client</button>
            <a href="/dashboard-builder?client={{ $client->id }}" class="btn-orange">&#10011; New Dashboard</a>
        </div>
    </div>

    <!-- Client Info + Database Connection — side by side -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">

        <!-- Client Info -->
        <div class="client-info">
            <div class="client-info-top">
                <div>
                    <div class="client-info-name">{{ $client->name }}</div>
                    <span class="client-info-cat">{{ $client->industry ?? 'General' }}</span>
                </div>
                <span class="client-info-status">{{ $client->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <div class="client-info-grid">
                @if($client->contact_name)<div class="info-item"><div class="lbl">Contact</div><div class="val">{{ $client->contact_name }}</div></div>@endif
                @if($client->contact_email)<div class="info-item"><div class="lbl">Email</div><div class="val">{{ $client->contact_email }}</div></div>@endif
                @if($client->contact_phone)<div class="info-item"><div class="lbl">Phone</div><div class="val">{{ $client->contact_phone }}</div></div>@endif
                @if($client->city)<div class="info-item"><div class="lbl">Location</div><div class="val">{{ $client->city }}{{ $client->province ? ', ' . $client->province : '' }}</div></div>@endif
            </div>
        </div>

        <!-- Database Connection -->
        @if($client->db_database)
        <div class="client-info">
            <div class="client-info-top">
                <div>
                    <div class="client-info-name" style="font-size:16px;">&#128451; Database</div>
                    <span class="client-info-cat">MySQL</span>
                </div>
                <span style="font-size:11px;color:#4ade80;">&#9679; Connected</span>
            </div>
            <div class="client-info-grid">
                <div class="info-item"><div class="lbl">Host</div><div class="val">{{ $client->db_host }}</div></div>
                <div class="info-item"><div class="lbl">Port</div><div class="val">{{ $client->db_port }}</div></div>
                <div class="info-item"><div class="lbl">Database</div><div class="val" style="color:#FB923C;font-family:monospace;">{{ $client->db_database }}</div></div>
                <div class="info-item"><div class="lbl">User</div><div class="val" style="font-family:monospace;">{{ $client->db_username }}</div></div>
            </div>
            @if($client->schema_snapshot)
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #334155;">
                <div style="font-size:10px;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                    {{ count($client->schema_snapshot) }} table{{ count($client->schema_snapshot) !== 1 ? 's' : '' }}
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                    @foreach(array_keys($client->schema_snapshot) as $table)
                    <span style="font-size:10px;padding:2px 8px;background:#0f172a;border:1px solid #334155;border-radius:4px;color:#94a3b8;font-family:monospace;">{{ $table }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @else
        <div class="client-info" style="display:flex;align-items:center;justify-content:center;color:#64748b;">
            <div style="text-align:center;">
                <div style="font-size:24px;margin-bottom:8px;">&#128451;</div>
                <div style="font-size:13px;">No database configured</div>
                <div style="font-size:11px;margin-top:4px;">Click Edit Client to add connection details</div>
            </div>
        </div>
        @endif
    </div>

    <!-- Dashboards -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="font-size:16px;color:#f1f5f9;font-weight:600;">Dashboards</h2>
        <span style="font-size:12px;color:#64748b;">{{ $dashboards->count() }} dashboard{{ $dashboards->count() !== 1 ? 's' : '' }}</span>
    </div>

    @if($dashboards->count() > 0)
    <div class="dashboards-grid">
        @foreach($dashboards as $dashboard)
        <div class="db-card">
            <div class="db-card-title">{{ $dashboard->name }}</div>
            <div class="db-card-meta">
                <span>&#9679; {{ $dashboard->card_count }} cards</span>
                <span>&#9679; {{ $dashboard->updated_at?->format('Y-m-d H:i') }}</span>
                @if($dashboard->is_published)<span style="color:#4ade80;">&#9679; Embedded</span>@endif
            </div>
            <div class="db-card-actions">
                <a href="/dashboard-builder?client={{ $dashboard->client_id }}&project={{ $dashboard->id }}">&#9998; Load &amp; Edit</a>
                <a href="/embed/{{ $dashboard->public_id }}" target="_blank">&#9679; View</a>
                <a href="#" onclick="duplicateDashboard(event, {{ $dashboard->id }})" style="cursor:pointer;">&#128427; Duplicate</a>
                <a href="#" data-name="{{ $dashboard->name }}" onclick="renameDashboard(event, {{ $dashboard->id }}, this)" style="cursor:pointer;">&#128221; Rename</a>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="empty-state">
        <h3>No dashboards yet</h3>
        <a href="/dashboard-builder?client={{ $client->id }}" style="color:#FB923C;text-decoration:none;">Create one with AI &#8594;</a>
    </div>
    @endif
</div>

<!-- Edit Client Modal -->
<div id="edit-client-modal" class="modal-hidden" style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:110;background:rgba(0,0,0,0.5);display:none;justify-content:center;align-items:center;" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-box">
    <h2>Edit {{ $client->name }}</h2>
    <form id="edit-client-form" onsubmit="saveClientEdit(event, {{ $client->id }})">
        @csrf
        <div class="form-grid">
            <div class="form-group full"><label>Company Name *</label><input type="text" name="name" value="{{ $client->name }}" required></div>
            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_name" value="{{ $client->contact_name }}"></div>
            <div class="form-group"><label>Industry</label>
                <select name="industry">
                    <option value="">Select...</option>
                    @foreach(['Education','Healthcare','Finance','Retail','Manufacturing','Non-Profit','Government','Other'] as $ind)
                    <option value="{{ $ind }}" {{ $client->industry === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Email</label><input type="email" name="contact_email" value="{{ $client->contact_email }}"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="contact_phone" value="{{ $client->contact_phone }}"></div>
            <div class="form-group full"><label>Address</label><input type="text" name="address_line1" value="{{ $client->address_line1 }}"></div>
            <div class="form-group"><label>City</label><input type="text" name="city" value="{{ $client->city }}"></div>
            <div class="form-group"><label>Province</label><input type="text" name="province" value="{{ $client->province }}"></div>
            <div class="form-group"><label>Postal Code</label><input type="text" name="postal_code" value="{{ $client->postal_code }}"></div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active"><option value="1" {{ $client->is_active ? 'selected' : '' }}>Active</option><option value="0" {{ !$client->is_active ? 'selected' : '' }}>Inactive</option></select>
            </div>

            <!-- Database Connection -->
            <div class="form-group full" style="margin-top:8px;padding-top:14px;border-top:1px solid #334155;">
                <label style="color:#FB923C;font-size:12px;">Database Connection</label>
            </div>
            <div class="form-group"><label>DB Host</label><input type="text" name="db_host" value="{{ $client->db_host ?? '127.0.0.1' }}"></div>
            <div class="form-group"><label>DB Port</label><input type="number" name="db_port" value="{{ $client->db_port ?? 3306 }}"></div>
            <div class="form-group full"><label>Database Name</label><input type="text" name="db_database" value="{{ $client->db_database }}"></div>
            <div class="form-group"><label>DB Username</label><input type="text" name="db_username" value="{{ $client->db_username }}"></div>
            <div class="form-group"><label>DB Password</label><input type="password" name="db_password" placeholder="Leave blank to keep current"></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-ghost" onclick="document.getElementById('edit-client-modal').style.display='none'">Cancel</button>
            <button type="submit" class="btn-orange">Save Changes</button>
        </div>
    </form>
</div>
</div>

<script>
async function duplicateDashboard(e, id) {
    e.preventDefault();
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        const r = await fetch('/dashboard-builder/dashboards/' + id + '/duplicate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        });
        if (r.ok) {
            const data = await r.json();
            alert('Duplicated! New dashboard: ' + data.name);
            location.reload();
        } else {
            alert('Duplicate failed: ' + (await r.text()).substring(0, 200));
        }
    } catch(e) { alert('Error: ' + e.message); }
}

async function renameDashboard(e, id, el) {
    e.preventDefault();
    const currentName = el?.dataset?.name || '';
    const newName = prompt('Enter a new name for this dashboard:', currentName);
    if (newName === null) return; // user cancelled
    const name = newName.trim();
    if (!name) { alert('Name cannot be empty.'); return; }
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        const r = await fetch('/dashboard-builder/dashboards/' + id, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ name: name })
        });
        if (r.ok) {
            const data = await r.json();
            alert('Renamed! Dashboard is now: ' + data.name);
            location.reload();
        } else {
            let msg = (await r.text()).substring(0, 300);
            try {
                const err = JSON.parse(msg);
                msg = (err.errors && err.errors.name) ? err.errors.name.join(', ') : (err.message || msg);
            } catch (_) {}
            alert('Rename failed: ' + msg);
        }
    } catch (err) { alert('Error: ' + err.message); }
}

async function saveClientEdit(e, id) {
    e.preventDefault();
    const form = document.getElementById('edit-client-form');
    const data = {};
    new FormData(form).forEach((v, k) => data[k] = v);
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        const r = await fetch('/dashboard-builder/clients/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(data)
        });
        if (r.ok) { location.reload(); }
        else { alert('Save failed: ' + (await r.text()).substring(0, 200)); }
    } catch(e) { alert('Error: ' + e.message); }
}
</script>
@endsection
