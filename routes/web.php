<?php

use App\Http\Controllers\DashboardBuilderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashbuilder.projects');
});

// Public API routes (no auth — used by embedded dashboards and health checks)
Route::get('/dashboard-builder/ai/health', [DashboardBuilderController::class, 'aiHealth'])->name('dashbuilder.ai.health');
Route::post('/dashboard-builder/ai/chat', [DashboardBuilderController::class, 'aiChat'])->name('dashbuilder.ai.chat');
Route::post('/dashboard-builder/batch-query', [DashboardBuilderController::class, 'batchQuery'])->name('dashbuilder.query.batch');
Route::post('/dashboard-builder/query', [DashboardBuilderController::class, 'query'])->name('dashbuilder.query');
Route::get('/api/dashboard/{publicId}', [DashboardBuilderController::class, 'publicDashboard'])->name('dashbuilder.public-dashboard');

// Authenticated routes
Route::middleware('auth')->prefix('dashboard-builder')->name('dashbuilder.')->group(function () {
    Route::get('/', [DashboardBuilderController::class, 'index'])->name('index');
    Route::get('/connect', [DashboardBuilderController::class, 'connect'])->name('connect');
    Route::post('/connect', [DashboardBuilderController::class, 'storeConnection']);
    Route::get('/schema/{client}', [DashboardBuilderController::class, 'clientSchema'])->name('schema');
    Route::get('/clients', [DashboardBuilderController::class, 'clients'])->name('clients');
    Route::get('/clients/{client}', [DashboardBuilderController::class, 'clientDetail'])->name('client-detail');
    Route::post('/ai/generate-dashboard', [DashboardBuilderController::class, 'aiGenerateDashboard'])->name('ai.generate');
    Route::post('/dashboards', [DashboardBuilderController::class, 'storeDashboard'])->name('dashboard.store');
    Route::post('/dashboards/history', [DashboardBuilderController::class, 'dashboardHistory'])->name('dashboard.history');
    Route::post('/dashboards/revision', [DashboardBuilderController::class, 'loadRevision'])->name('dashboard.revision');

    // Projects repository
    Route::get('/projects', [DashboardBuilderController::class, 'projects'])->name('projects');
    Route::get('/projects/{id}/load', [DashboardBuilderController::class, 'loadProject'])->name('projects.load');
    Route::delete('/projects/{id}', [DashboardBuilderController::class, 'deleteProject'])->name('projects.delete');

    // Client management
    Route::post('/clients', [DashboardBuilderController::class, 'storeClient'])->name('clients.store');
    Route::put('/clients/{client}', [DashboardBuilderController::class, 'updateClient'])->name('clients.update');
    Route::delete('/clients/{client}', [DashboardBuilderController::class, 'deleteClient'])->name('clients.delete');
});

// Public embed route (no auth — the iframe target)
Route::get('/embed/{publicId}', [DashboardBuilderController::class, 'publicEmbed'])->name('embed.public');

// Auth routes (login, logout, register)
require __DIR__.'/auth.php';
