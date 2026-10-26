<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('tenant.login');
    }

        public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $dbPath = database_path('tenants/greenfield.sqlite');
        
        if (!file_exists($dbPath)) {
             return back()->withErrors(['email' => "Debug: Database file missing at $dbPath"]);
        }

        Config::set('database.connections.tenant_sqlite.database', $dbPath);
        DB::purge('tenant_sqlite');

        $user = \App\Models\Tenant\User::on('tenant_sqlite')->where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            
            Auth::login($user);
            $request->session()->regenerate();
            session(['tenant_id' => 'greenfield']);

                        // Use redirect() but relative to the current domain
            return redirect()->to('/school/greenfield/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/school/greenfield/login');
    }
}