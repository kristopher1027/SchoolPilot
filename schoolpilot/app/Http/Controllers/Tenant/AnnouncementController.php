<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index()
    {
        $user = Auth::guard('school')->user();
        $query = Announcement::with('author')->visibleTo($user);
        if (in_array($user->role, ['school_admin', 'principal'], true)) {
            $query->orWhere(function ($drafts) use ($user) {
                $drafts->where('status', 'draft')->where('author_id', $user->id);
            });
        }
        $announcements = $query->latest('published_at')->latest()->paginate(15);

        $canManage = in_array($user->role, ['school_admin', 'principal'], true);

        return view('tenant.announcements.index', compact('announcements', 'canManage'));
    }

    public function create()
    {
        return view('tenant.announcements.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:30000'],
            'audience' => ['required', Rule::in(['all', 'staff', 'teachers', 'parents', 'students'])],
            'publish_now' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $publish = $request->boolean('publish_now');
        $announcement = Announcement::create([
            'author_id' => Auth::guard('school')->id(),
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? now() : null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return redirect('/school/' . $request->route('school') . '/announcements')
            ->with('success', $publish ? 'Announcement published.' : 'Announcement saved as a draft.');
    }

    public function show(Announcement $announcement)
    {
        $user = Auth::guard('school')->user();
        $canView = Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists();
        $canViewOwnDraft = $announcement->status === 'draft'
            && $announcement->author_id === $user->id
            && in_array($user->role, ['school_admin', 'principal'], true);
        if (! $canView && ! $canViewOwnDraft) {
            abort(404);
        }

        $announcement->load('author');

        return view('tenant.announcements.show', compact('announcement'));
    }

    public function publish(Request $request, Announcement $announcement)
    {
        if ($announcement->status !== 'draft') {
            throw ValidationException::withMessages(['announcement' => 'Only draft announcements can be published.']);
        }

        $announcement->update(['status' => 'published', 'published_at' => now()]);

        return redirect('/school/' . $request->route('school') . '/announcements/' . $announcement->id)
            ->with('success', 'Announcement published.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $announcement->delete();

        return redirect('/school/' . $request->route('school') . '/announcements')
            ->with('success', 'Announcement deleted.');
    }
}
