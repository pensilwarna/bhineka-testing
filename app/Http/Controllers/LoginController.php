<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.lo');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->is_banned) {
            throw ValidationException::withMessages([
                'email' => __('Maaf, akun Anda telah ditangguhkan. Silakan hubungi administrator.'),
            ]);
        }

        if (Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard'); 
        }

        throw ValidationException::withMessages([
            'email' => __('Email atau password salah.'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/dashboard');
    }

    protected function authenticated(Request $request, $user)
    {
        // Check if user intended to view a specific ticket
        if (session()->has('intended_ticket')) {
            $encryptedId = session('intended_ticket');
            session()->forget('intended_ticket');
            
            return redirect()->route('tickets.redirect', $encryptedId);
        }
        
        // Default redirect behavior
        return redirect()->intended($this->redirectPath());
    }


}
