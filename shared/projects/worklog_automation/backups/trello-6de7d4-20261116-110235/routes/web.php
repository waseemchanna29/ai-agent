<?php

use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorklogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectForController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

Route::get('/worklog/{uuid}', [WorklogController::class, 'create'])->name('worklog.create');

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/worklogs', [WorklogController::class, 'store'])->name('worklog.store');
    Route::post('/tasks', [TaskController::class, 'quickStore'])->name('task.store');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except('show');
            Route::resource('project-fors', ProjectForController::class)
                ->except('show')
                ->parameters(['project-fors' => 'projectFor']);
            Route::resource('projects', ProjectController::class)->except('show');
            Route::resource('tasks', TaskController::class)->except('show');
        });
    });
});
