@extends('layouts.app')

@section('page_title', $client->name)

@section('content')
<div style="max-width:1200px;margin:0 auto;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <a href="{{ route('dashbuilder.projects') }}" class="btn-ghost">&#8592; All Clients</a>
        <a href="/dashboard-builder?client={{ $client->id }}" class="btn-orange">&#10011; New Dashboard</a>
    </div>

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
                <span>&#9679; {{ $dashboard->updated_at?->format('Y-m-d') }}</span>
                @if($dashboard->is_published)<span style="color:#4ade80;">&#9679; Embedded</span>@endif
            </div>
            <div class="db-card-actions">
                <a href="/dashboard-builder?project={{ $dashboard->id }}">&#9998; Load &amp; Edit</a>
                <a href="/embed/{{ $dashboard->public_id }}" target="_blank">&#9679; View</a>
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
@endsection
