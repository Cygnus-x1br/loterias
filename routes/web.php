<?php

use App\Livewire\Dashboard;
use App\Livewire\LotofacilAnalysis;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Dashboard::class)
        ->name('dashboard');

    Route::get('/dashboard/analises-lotofacil', LotofacilAnalysis::class)
        ->name('lotofacil.analysis');
});

Route::middleware('auth')
    ->prefix('apostas')
    ->name('bets.')
    ->group(function () {
        Volt::route('/', 'pages.bets.index')
            ->name('index');

        Volt::route('/nova', 'pages.bets.create')
            ->name('create');
    });

Route::middleware('auth')
    ->prefix('fechamentos')
    ->name('closings.')
    ->group(function () {
        Volt::route('/', 'pages.closings.index')
            ->name('index');
        Volt::route('/novo', 'pages.closings.create')
            ->name('create');
        Volt::route('/{closing}', 'pages.closings.show')
            ->name('show');
    });

Route::middleware('auth')
    ->prefix('sorteios')
    ->name('results.')
    ->group(function () {
        Volt::route('/', 'pages.results.index')
            ->name('index');
        Volt::route('/novo', 'pages.results.create')
            ->name('create');
        Volt::route('/{result}/editar', 'pages.results.edit')
            ->name('edit');
    });

require __DIR__.'/auth.php';
