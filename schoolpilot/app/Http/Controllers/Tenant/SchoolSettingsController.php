<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolSettingsController extends Controller
{
    public function edit()
    {
        $settings = DB::table('settings')->pluck('value', 'key');
        return view('tenant.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'motto' => ['nullable', 'string', 'max:180'],
            'school_type' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $now = now();
        foreach ($validated as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value === null ? null : trim($value), 'updated_at' => $now, 'created_at' => $now],
            );
        }

        return redirect()->to('/school/' . $request->route('school') . '/settings')
            ->with('success', 'School profile updated successfully.');
    }
}
