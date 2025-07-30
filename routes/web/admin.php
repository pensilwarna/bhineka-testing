<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminPanelController;

Route::prefix('admin')->name('admin.')->group(function () {
    // Roles
    Route::get('roles', [AdminPanelController::class, 'rolesIndex'])->name('roles.index');
    Route::get('roles/create', [AdminPanelController::class, 'rolesCreate'])->name('roles.create');
    Route::post('roles', [AdminPanelController::class, 'rolesStore'])->name('roles.store');
    Route::get('roles/{role}', [AdminPanelController::class, 'rolesShow'])->name('roles.show');
    Route::get('roles/{role}/edit', [AdminPanelController::class, 'rolesEdit'])->name('roles.edit');
    Route::put('roles/{role}', [AdminPanelController::class, 'rolesUpdate'])->name('roles.update');
    Route::delete('roles/{role}', [AdminPanelController::class, 'rolesDestroy'])->name('roles.destroy');
    Route::post('roles/{role}/sync-permissions', [AdminPanelController::class, 'syncPermissions'])->name('roles.syncPermissions');

    // Permissions
    Route::get('permissions', [AdminPanelController::class, 'permissionsIndex'])->name('permissions.index');
    Route::get('permissions/create', [AdminPanelController::class, 'permissionsCreate'])->name('permissions.create');
    Route::post('permissions', [AdminPanelController::class, 'permissionsStore'])->name('permissions.store');
    Route::get('permissions/{permission}', [AdminPanelController::class, 'permissionsShow'])->name('permissions.show');
    Route::get('permissions/{permission}/edit', [AdminPanelController::class, 'permissionsEdit'])->name('permissions.edit');
    Route::put('permissions/{permission}', [AdminPanelController::class, 'permissionsUpdate'])->name('permissions.update');
    Route::delete('permissions/{permission}', [AdminPanelController::class, 'permissionsDestroy'])->name('permissions.destroy');
});