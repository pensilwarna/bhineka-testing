<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Closure;

class EnsureUserIsNotBanned
{

    public function handle(Request $request, Closure $next)
    {
        $user = User::where('email', $request->email)->first();

        if ($user && $user->is_banned) {
            throw ValidationException::withMessages([
                'email' => __('Maaf, akun Anda telah ditangguhkan. Silakan hubungi administrator.'),
            ]);
        }

        return $next($request);
    }
}