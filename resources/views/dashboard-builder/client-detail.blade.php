@extends('layouts.app')

@section('page_title', $client->name . ' — Dashboards')
@section('page_slug', 'dashboard-builder')

@section('styles')
<link rel="stylesheet" href="/css/dashboard-builder.css">
<style>
    body { overflow: auto; }
    .client-detail-page { max-width: 1200px; margin: 0 auto; padding: 24px; }
    .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .detail-header h1 { font-size: 24px; color: #f1f5f9; }
    .detail-header .actions { display: flex; gap: 10px; }
    .btn-primary { background: #FB923C; color: #0f172a; border: none; padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-primary:hover { background: #f97316; }
    .btn-outline { background: transparent; color: #94a3b8; border: 1px solid #334155; padding: 8px 16px; border-radius: 8px; font-size: 13px; text-decoration: none; }
    .btn-outline:hover { border-color: #FB923C; color: #FB923C; }

    .client-info { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
    .client-info-row { display: flex; gap: 24px; flex-wrap: wrap; }
    .client-info-item { font-size: 13px; color: #94a3b8; }
    .client-info-item .label { color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; display: block; margin-bottom: 2px; }
    .client-info-item .value { color: #e2e8f0; font-weight: 500; }

    .dashboards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
    .dashboard-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 16px; transition: border-color 0.15s; }
    .dashboard-card:hover { border-color: #FB923C; }
    .dashboard-name { font-size: 16px; font-weight: 600; color: #f1f5f9; margin-bottom: 4px; }
    .dashboard-meta { font-size: 11px; color: #64748b; display: flex; gap: 12px; margin-bottom: 12px; }
    .dashboard-actions { display: flex; gap: 8px; }
    .dashboard-actions a, .dashboard-actions button { font-size: 12px; padding: 6px 14px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #94a3b8; cursor: pointer; text-decoration: none; }
    .dashboard-actions a:hover, .dashboard-actions button:hover { border-color: #FB923C; color: #FB923C; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state h3 { font-size: 18px; color: #94a3b8; margin-bottom: 8px; }
    .empty-state a { color: #FB923C; text-decoration: none; }
</style>
@endsection

@section('content')
<div class="client-detail-page">
    <div class="detail-header">
        <h1>{{ $client->name }}</h1>
        <div class="actions">
            <a href="{{ route('dashbuilder.projects') }}" class="btn-outline">← All Clients</a>
            <a href="/dashboard-builder?client={{ $client->id }}" class="btn-primary">+ New Dashboard</a>
        </div>
    </div>

    <div class="client-info">
        <div class="client-info-row">
            @if($client->contact_name)
            <div class="client-info-item">
                <span class="label">Contact</span>
                <span class="value">{{ $client->contact_name }}</span>
            </div>
            @endif
            @if($client->contact_email)
            <div class="client-info-item">
                <span class="label">Email</span>
                <span class="value">{{ $client->contact_email }}</span>
            </div>
            @endif
            @if($client->contact_phone)
            <div class="client-info-item">
                <span class="label">Phone</span>
                <span class="value">{{ $client->contact_phone }}</span>
            </div>
            @endif
            @if($client->city)
            <div class="client-info-item">
                <span class="label">Location</span>
                <span class="value">{{ $client->city }}{{ $client->province ? ', ' . $client->province : '' }}</span>
            </div>
            @endif
            @if($client->industry)
            <div class="client-info-item">
                <span class="label">Industry</span>
                <span class="value">{{ $client->industry }}</span>
            </div>
            @endif
        </div>
    </div>

    @if($dashboards->count() > 0)
    <div class="dashboards-grid">
        @foreach($dashboards as $dashboard)
        <div class="dashboard-card">
            <div class="dashboard-name">{{ $dashboard->name }}</div>
            <div class="dashboard-meta">
                <span>📋 {{ $dashboard->card_count }} cards</span>
                <span>🕐 {{ $dashboard->updated_at?->format('Y-m-d') }}</span>
            </div>
            <div class="dashboard-actions">
                <a href="/dashboard-builder?project={{ $dashboard->id }}">Load & Edit</a>
                <a href="/embed/{{ $dashboard->public_id }}" target="_blank">View</a>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="empty-state">
        <h3>No dashboards yet</h3>
        <p><a href="/dashboard-builder?client={{ $client->id }}">Create one with AI →</a></p>
    </div>
    @endif
</div>
@endsection
