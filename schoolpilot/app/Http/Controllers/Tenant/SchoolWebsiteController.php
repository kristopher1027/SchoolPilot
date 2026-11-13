<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolWebsiteController extends Controller
{
    public function publicHome(string $school)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->orderBy('title')->get();
        $posts = DB::table('school_website_posts')->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at')->limit(6)->get();
        $events = DB::table('school_events')->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(6)->get();
        return view('public-school.home', compact('school', 'settings', 'pages', 'posts', 'events'));
    }

    public function publicPage(string $school, string $slug)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $page = DB::table('school_website_pages')->where('slug', $slug)->where('status', 'published')->first();
        abort_unless($page, 404);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->orderBy('title')->get();
        return view('public-school.page', compact('school', 'settings', 'page', 'pages'));
    }

    public function publicNews(string $school)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $posts = DB::table('school_website_posts')->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at')->paginate(12);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->get();
        return view('public-school.news', compact('school', 'settings', 'posts', 'pages'));
    }

    public function publicPost(string $school, string $slug)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $post = DB::table('school_website_posts')->where('slug', $slug)->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->first();
        abort_unless($post, 404);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->get();
        return view('public-school.post', compact('school', 'settings', 'post', 'pages'));
    }

    public function publicGallery(string $school)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $gallery = DB::table('school_website_gallery_items')->where('status', 'published')->orderBy('album')->orderBy('sort_order')->orderByDesc('id')->paginate(24);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->get();
        return view('public-school.gallery', compact('school', 'settings', 'gallery', 'pages'));
    }

    public function publicEvents(string $school)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $events = DB::table('school_events')->where('status', 'published')->orderBy('starts_at')->paginate(12);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->get();
        return view('public-school.events', compact('school', 'settings', 'events', 'pages'));
    }

    public function publicEvent(string $school, string $slug)
    {
        $settings = DB::table('school_website_settings')->first();
        abort_unless($settings && $settings->is_published, 404);
        $event = DB::table('school_events')->where('slug', $slug)->where('status', 'published')->first();
        abort_unless($event, 404);
        $pages = DB::table('school_website_pages')->where('status', 'published')->orderBy('sort_order')->get();
        return view('public-school.event', compact('school', 'settings', 'event', 'pages'));
    }

    public function registerForEvent(Request $request, string $school, int $event)
    {
        $data = $request->validate([
            'attendee_name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:60'],
            'attendee_type' => ['required', Rule::in(['parent', 'student', 'staff', 'guest'])],
        ]);
        $eventRow = DB::table('school_events')->where('id', $event)->where('status', 'published')->first();
        abort_unless($eventRow, 404);
        if (!$eventRow->registration_open || \Illuminate\Support\Carbon::parse($eventRow->starts_at)->isPast()) {
            throw ValidationException::withMessages(['event' => 'Registration for this event is closed.']);
        }
        if ($eventRow->capacity !== null) {
            $count = DB::table('school_event_registrations')->where('event_id', $event)->where('status', 'registered')->count();
            if ($count >= $eventRow->capacity) throw ValidationException::withMessages(['event' => 'This event has reached its registration capacity.']);
        }
        if (!empty($data['email']) && DB::table('school_event_registrations')->where('event_id', $event)->where('email', $data['email'])->where('status', 'registered')->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already registered for the event.']);
        }
        DB::table('school_event_registrations')->insert($data + [
            'event_id' => $event, 'status' => 'registered', 'attendance_status' => 'not_marked',
            'registered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Your event registration has been received.');
    }

    public function adminIndex()
    {
        $settings = DB::table('school_website_settings')->first();
        $pages = DB::table('school_website_pages')->orderBy('sort_order')->orderByDesc('id')->get();
        $posts = DB::table('school_website_posts')->orderByDesc('id')->limit(30)->get();
        $galleryItems = DB::table('school_website_gallery_items')->orderBy('album')->orderBy('sort_order')->orderByDesc('id')->limit(60)->get();
        $events = DB::table('school_events')->orderByDesc('starts_at')->limit(30)->get();
        $clubs = DB::table('school_clubs')->orderBy('name')->get();
        $registrations = DB::table('school_event_registrations as r')->join('school_events as e', 'e.id', '=', 'r.event_id')->select('r.*', 'e.title as event_title')->orderByDesc('r.registered_at')->limit(50)->get();
        $members = DB::table('school_club_members as m')->join('school_clubs as c', 'c.id', '=', 'm.club_id')->join('students as s', 's.id', '=', 'm.student_id')->select('m.*', 'c.name as club_name', 's.first_name', 's.last_name', 's.admission_no')->orderBy('c.name')->limit(100)->get();
        $students = DB::table('students')->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get();
        $staff = DB::table('staff as s')->join('users as u', 'u.id', '=', 's.user_id')->where('u.is_active', true)->select('s.id', 'u.name')->orderBy('u.name')->get();
        return view('tenant.school-website.index', compact('settings', 'pages', 'posts', 'galleryItems', 'events', 'clubs', 'registrations', 'members', 'students', 'staff'));
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['nullable', 'string', 'max:180'], 'tagline' => ['nullable', 'string', 'max:220'],
            'about' => ['nullable', 'string', 'max:10000'], 'logo_url' => ['nullable', 'url', 'max:2048'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'], 'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:60'], 'address' => ['nullable', 'string', 'max:2000'],
            'primary_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'is_published' => ['nullable', 'boolean'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $existing = DB::table('school_website_settings')->first();
        if ($existing) DB::table('school_website_settings')->where('id', $existing->id)->update($data + ['updated_at' => now()]);
        else DB::table('school_website_settings')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->audit('website.settings.updated', 'school_website_settings', (int) ($existing->id ?? DB::getPdo()->lastInsertId()), null, $data);
        return $this->back($request, 'Website settings saved.');
    }

    public function storePage(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'alpha_dash', 'max:180', 'unique:school_website_pages,slug'],
            'content' => ['required', 'string', 'max:50000'], 'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        if (DB::table('school_website_pages')->where('slug', $data['slug'])->exists()) throw ValidationException::withMessages(['slug' => 'A page already uses this URL slug.']);
        $id = DB::table('school_website_pages')->insertGetId($data + ['sort_order' => $data['sort_order'] ?? 0, 'created_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('website.page.created', 'school_website_page', $id, null, $data);
        return $this->back($request, 'Website page created.');
    }

    public function storePost(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'alpha_dash', 'max:180', 'unique:school_website_posts,slug'],
            'category' => ['nullable', 'string', 'max:80'], 'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:50000'], 'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published'])], 'published_at' => ['nullable', 'date'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        if (DB::table('school_website_posts')->where('slug', $data['slug'])->exists()) throw ValidationException::withMessages(['slug' => 'A news post already uses this URL slug.']);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now()) : null;
        $id = DB::table('school_website_posts')->insertGetId($data + ['author_id' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('website.post.created', 'school_website_post', $id, null, $data);
        return $this->back($request, 'News post saved.');
    }

    public function updatePageStatus(Request $request, int $page)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published'])]]);
        $old = DB::table('school_website_pages')->where('id', $page)->first(); abort_unless($old, 404);
        DB::table('school_website_pages')->where('id', $page)->update($data + ['updated_at' => now()]);
        $this->audit('website.page.status_changed', 'school_website_page', $page, ['status' => $old->status], $data);
        return $this->back($request, 'Page status updated.');
    }

    public function updatePostStatus(Request $request, int $post)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published'])]]);
        $old = DB::table('school_website_posts')->where('id', $post)->first(); abort_unless($old, 404);
        DB::table('school_website_posts')->where('id', $post)->update($data + ['published_at' => $data['status'] === 'published' ? ($old->published_at ?: now()) : null, 'updated_at' => now()]);
        $this->audit('website.post.status_changed', 'school_website_post', $post, ['status' => $old->status], $data);
        return $this->back($request, 'News status updated.');
    }

    public function storeGalleryItem(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'album' => ['nullable', 'string', 'max:120'],
            'image_url' => ['required', 'url', 'max:2048'], 'caption' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $id = DB::table('school_website_gallery_items')->insertGetId($data + [
            'sort_order' => $data['sort_order'] ?? 0, 'created_by' => Auth::guard('school')->id(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('website.gallery_item.created', 'school_website_gallery_item', $id, null, $data);
        return $this->back($request, 'Gallery item saved.');
    }

    public function updateGalleryStatus(Request $request, int $item)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published'])]]);
        $old = DB::table('school_website_gallery_items')->where('id', $item)->first(); abort_unless($old, 404);
        DB::table('school_website_gallery_items')->where('id', $item)->update($data + ['updated_at' => now()]);
        $this->audit('website.gallery_item.status_changed', 'school_website_gallery_item', $item, ['status' => $old->status], $data);
        return $this->back($request, 'Gallery item status updated.');
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'alpha_dash', 'max:180', 'unique:school_events,slug'],
            'description' => ['required', 'string', 'max:10000'], 'venue' => ['nullable', 'string', 'max:180'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'], 'registration_open' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        if (DB::table('school_events')->where('slug', $data['slug'])->exists()) throw ValidationException::withMessages(['slug' => 'An event already uses this URL slug.']);
        $data['registration_open'] = $request->boolean('registration_open');
        $id = DB::table('school_events')->insertGetId($data + ['created_by' => Auth::guard('school')->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('website.event.created', 'school_event', $id, null, $data);
        return $this->back($request, 'Event saved.');
    }

    public function updateEvent(Request $request, int $event)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
            'registration_open' => ['nullable', 'boolean'],
        ]);
        $old = DB::table('school_events')->where('id', $event)->first(); abort_unless($old, 404);
        $data['registration_open'] = $request->boolean('registration_open');
        DB::table('school_events')->where('id', $event)->update($data + ['updated_at' => now()]);
        $this->audit('website.event.updated', 'school_event', $event, ['status' => $old->status, 'registration_open' => $old->registration_open], $data);
        return $this->back($request, 'Event status updated.');
    }

    public function storeClub(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'], 'description' => ['nullable', 'string', 'max:5000'],
            'meeting_schedule' => ['nullable', 'string', 'max:180'], 'patron_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);
        $slug = Str::slug($data['name']);
        if (DB::table('school_clubs')->where('slug', $slug)->exists()) throw ValidationException::withMessages(['name' => 'A club with this name already exists.']);
        $id = DB::table('school_clubs')->insertGetId($data + ['slug' => $slug, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('website.club.created', 'school_club', $id, null, $data);
        return $this->back($request, 'Club created.');
    }

    public function storeClubMember(Request $request)
    {
        $data = $request->validate(['club_id' => ['required', 'integer', 'exists:school_clubs,id']]);
        return $this->addClubMember($request, (int) $data['club_id']);
    }

    public function addClubMember(Request $request, int $club)
    {
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:students,id'], 'joined_at' => ['required', 'date']]);
        abort_unless(DB::table('school_clubs')->where('id', $club)->where('status', 'active')->exists(), 404);
        if (DB::table('school_club_members')->where('club_id', $club)->where('student_id', $data['student_id'])->where('status', 'active')->exists()) throw ValidationException::withMessages(['student_id' => 'This student is already an active club member.']);
        DB::table('school_club_members')->updateOrInsert(
            ['club_id' => $club, 'student_id' => $data['student_id']],
            ['joined_at' => $data['joined_at'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );
        $this->audit('website.club.member_added', 'school_club', $club, null, $data);
        return $this->back($request, 'Student added to club.');
    }

    public function endClubMember(Request $request, int $club, int $member)
    {
        $old = DB::table('school_club_members')->where('club_id', $club)->where('id', $member)->first(); abort_unless($old, 404);
        DB::table('school_club_members')->where('id', $member)->update(['status' => 'ended', 'updated_at' => now()]);
        $this->audit('website.club.member_ended', 'school_club_member', $member, ['status' => $old->status], ['status' => 'ended']);
        return $this->back($request, 'Club membership ended.');
    }

    public function updateRegistration(Request $request, int $registration)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['registered', 'cancelled'])], 'attendance_status' => ['required', Rule::in(['not_marked', 'attended', 'absent'])]]);
        $old = DB::table('school_event_registrations')->where('id', $registration)->first(); abort_unless($old, 404);
        DB::table('school_event_registrations')->where('id', $registration)->update($data + ['updated_at' => now()]);
        $this->audit('website.event.registration_updated', 'school_event_registration', $registration, ['status' => $old->status, 'attendance_status' => $old->attendance_status], $data);
        return $this->back($request, 'Event registration updated.');
    }

    private function audit(string $action, string $type, int $id, ?array $old, ?array $new): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::guard('school')->id(), 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_value' => $old ? json_encode($old) : null, 'new_value' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function back(Request $request, string $message)
    {
        return redirect('/school/' . $request->route('school') . '/website')->with('success', $message);
    }
}
