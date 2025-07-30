<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function toggleTheme(Request $request, $theme)
    {
        if (!in_array($theme, ['dark', 'light'])) {
            return response()->json(['error' => 'Invalid theme'], 400);
        }

        if (auth()->check()) {
            $user = auth()->user();
            $user->theme = $theme;
            $user->save();
        } else {
            // Simpan tema di session jika belum login
            session(['theme' => $theme]);
        }

        return response()->json(['status' => 'Theme updated']);
    }
    
}
