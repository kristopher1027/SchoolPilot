<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SchoolSettingsController extends Controller
{
    public function edit()
    {
        $settings = DB::table('settings')->pluck('value', 'key');
        $logoUrl = $settings->get('logo_path') ? Storage::disk('public')->url($settings->get('logo_path')) : null;

        return view('tenant.settings.edit', compact('settings', 'logoUrl'));
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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $schoolSlug = (string) $request->route('school');
        $oldLogoPath = DB::table('settings')->where('key', 'logo_path')->value('value');
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->storePubliclyAs(
                'schools/' . $schoolSlug,
                'logo.' . $request->file('logo')->extension(),
                'public',
            );

            if (! $newLogoPath) {
                return back()->withInput()->withErrors(['logo' => 'The school logo could not be saved. Please try again.']);
            }
        }

        $now = now();
        foreach ($validated as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value === null ? null : trim($value), 'updated_at' => $now, 'created_at' => $now],
            );
        }

        if ($newLogoPath) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'logo_path'],
                ['value' => $newLogoPath, 'updated_at' => $now, 'created_at' => $now],
            );

            if (is_string($oldLogoPath) && $oldLogoPath !== $newLogoPath && str_starts_with($oldLogoPath, 'schools/' . $schoolSlug . '/')) {
                Storage::disk('public')->delete($oldLogoPath);
            }
        }

        return redirect()->to('/school/' . $schoolSlug . '/settings')
            ->with('success', 'School profile updated successfully.');
    }
}
