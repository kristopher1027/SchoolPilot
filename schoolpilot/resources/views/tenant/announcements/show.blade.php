@extends('tenant.layouts.app')
@section('content')
<div class="mb-6"><a href="{{ url('/school/' . request()->route('school') . '/announcements') }}" class="text-sm text-blue-700">← Noticeboard</a></div>
<article class="mx-auto max-w-3xl rounded-lg bg-white p-6 shadow md:p-8">@if($announcement->status === 'draft')<span class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">Draft</span>@endif
<div class="mt-2 flex flex-wrap items-center gap-2"><span class="rounded bg-blue-50 px-2 py-1 text-xs capitalize text-blue-800">{{ $announcement->audience === 'all' ? 'Everyone' : $announcement->audience }}</span></div>
<h3 class="mt-3 text-2xl font-bold text-gray-800">{{ $announcement->title }}</h3><p class="mt-2 text-xs text-gray-500">By {{ $announcement->author?->name }} · {{ $announcement->published_at?->format('d M Y, h:i A') ?? 'Draft' }}</p>
<div class="mt-6 whitespace-pre-line text-sm leading-7 text-gray-700">{{ $announcement->body }}</div>
@if($announcement->expires_at)<p class="mt-6 border-t pt-4 text-xs text-gray-500">This announcement expires {{ $announcement->expires_at->format('d M Y, h:i A') }}.</p>@endif
</article>
@endsection