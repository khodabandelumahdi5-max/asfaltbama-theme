<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'ایمیل یا رمز عبور اشتباه است.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel.dashboard'));
    }

    public function showRegister(Request $request)
    {
        return view('auth.register', ['role' => $request->query('role', User::ROLE_BUYER)]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_BUYER, User::ROLE_SUPPLIER])],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required_if:role,supplier', 'nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:60'],
        ], ['phone.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);

            if ($user->isSupplier()) {
                $user->company()->create([
                    'name' => $data['company_name'],
                    'slug' => Company::uniqueSlug($data['company_name']),
                    'city' => $data['city'] ?? null,
                    'phone' => $data['phone'],
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.dashboard')->with('status', 'خوش آمدید! حساب شما ساخته شد.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
