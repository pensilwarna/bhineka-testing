// app/Providers/AuthServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Fortify\Fortify; // Pastikan ini di-import

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // ... (kode lain jika ada)

        Fortify::routes(); // <<< PASTIKAN BARIS INI ADA DI SINI

        // ... (kode lain jika ada)
    }
}