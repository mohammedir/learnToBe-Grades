<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['current_password' => 'كلمة المرور الحالية', 'password' => 'كلمة المرور الجديدة']);

        $request->user()->update(['password' => $request->password]);

        return back()->with('status', 'تم تغيير كلمة المرور.');
    }
}
