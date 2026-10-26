<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('school/greenfield')->group(function () {
    
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('tenant.login');
    
    // The login POST will STILL redirect, but we will catch it in the dashboard
    Route::post('/login', [LoginController::class, 'login']);
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('tenant.logout');

    // THE MAGIC BULLET: We handle login straight in the dashboard route if session drops
    Route::any('/dashboard', function (\Illuminate\Http\Request $request) {
        
        // 1. If they are submitting the login form on this exact route, log them in
        if ($request->isMethod('post') && $request->has('email')) {
            $dbPath = database_path('tenants/greenfield.sqlite');
            Config::set('database.connections.tenant_sqlite.database', $dbPath);
            DB::purge('tenant_sqlite');
            
            $user = \App\Models\Tenant\User::on('tenant_sqlite')->where('email', $request->email)->first();
            
            if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                Auth::login($user);
                session(['tenant_id' => 'greenfield']);
            } else {
                return back()->withErrors(['email' => 'Invalid credentials']);
            }
        }

        // 2. If they are just visiting the page, check auth
        if (!Auth::check()) {
            return redirect('/school/greenfield/login');
        }

        // 3. Set up the DB for the dashboard
        Config::set('database.connections.tenant_sqlite.database', database_path('tenants/greenfield.sqlite'));
        DB::purge('tenant_sqlite');
        Config::set('database.default', 'tenant_sqlite');

        return view('tenant.dashboard');
        
    })->name('tenant.dashboard');
});