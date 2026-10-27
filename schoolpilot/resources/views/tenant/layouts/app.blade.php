<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchoolPilot - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        
        <!-- Sidebar -->
        <aside class="w-64 bg-blue-900 text-white flex-col hidden md:flex">
            <div class="h-16 flex items-center justify-center border-b border-blue-800">
                <h1 class="text-xl font-bold tracking-wider">SchoolPilot</h1>
            </div>
                       <nav class="flex-1 p-4 space-y-2 text-sm">
                {{-- Dashboard is for everyone --}}
                <a href="/school/greenfield/dashboard" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Dashboard</a>

                {{-- Admin, Principal, Dean can see Students --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean']))
                    <a href="/school/greenfield/students" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Students</a>
                @endif

                {{-- Only Admin and Principal can see Staff --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal']))
                    <a href="/school/greenfield/staff" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Staff</a>
                    <a href="/school/greenfield/academic" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Classes & Subjects</a>
                    <a href="/school/greenfield/assignments" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Teacher Assignments</a>
                @endif

                {{-- Teachers, HOD, Dean, Admin, Principal can enter scores --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean', 'hod', 'class_teacher', 'subject_teacher']))
                    <a href="/school/greenfield/scores" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Score Entry</a>
                @endif

                {{-- Everyone except students can see Results (we will restrict further later) --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean', 'hod', 'class_teacher']))
                    <a href="/school/greenfield/results" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Results</a>
                @endif

                {{-- Settings only for Admin --}}
                @if(Auth::user()->role === 'school_admin')
                    <a href="#" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Settings</a>
                @endif
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            
            <!-- Top Header -->
            <header class="h-16 bg-white shadow flex items-center justify-between px-6">
                <div class="flex items-center">
                    <!-- Hardcoded school name for now -->
                    <h2 class="text-xl font-semibold text-gray-800">Greenfield Academy</h2>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 hidden sm:block">Logged in as: <strong>{{ Auth::user()->name }}</strong></span>
                    <form method="POST" action="/school/greenfield/logout">
                        @csrf
                        <button type="submit" class="text-sm bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">Logout</button>
                    </form>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
            
        </div>
    </div>
</body>
</html>