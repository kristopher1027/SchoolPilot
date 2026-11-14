<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchoolPilot - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen min-h-0 bg-gray-100">
        
        <!-- Sidebar -->
        <aside class="w-64 shrink-0 bg-[#102a56] text-white flex-col hidden md:flex shadow-xl">
            <div class="h-16 shrink-0 flex items-center justify-center border-b border-blue-700 bg-[#0b2145]">
                <h1 class="text-xl font-bold tracking-wider">SchoolPilot</h1>
            </div>
                       <nav class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 space-y-2 text-sm text-white">
                <a href="/school/{{ request()->route('school') }}/announcements" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Noticeboard</a>

                {{-- Dashboard is for everyone --}}
                <a href="/school/greenfield/dashboard" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Dashboard</a>

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean']))
                    <a href="/school/{{ request()->route('school') }}/admissions" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Admissions</a>
                @endif

                {{-- Admin, Principal, Dean can see Students --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean']))
                    <a href="/school/greenfield/students" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Students</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean']))
                    <a href="/school/{{ request()->route('school') }}/parents" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Parents & Guardians</a>
                @endif

                {{-- Only Admin and Principal can see Staff --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal']))
                    <a href="/school/{{ request()->route('school') }}/hr/staff-attendance" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Staff Attendance</a>
                @endif
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/payroll" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Payroll Management</a>
                @endif
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/payroll/payslips" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">My Payslips</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/hr/leave" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Leave Management</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal']))
                    <a href="/school/greenfield/staff" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Staff</a>
                    <a href="/school/greenfield/academic" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Classes & Subjects</a>
                    <a href="/school/greenfield/assignments" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Teacher Assignments</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'bursar', 'librarian']))
                    <a href="/school/{{ request()->route('school') }}/library" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Library</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/fees" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Fees & Payments</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/inventory" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Inventory & Assets</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/transport" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Transport Management</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'vice_principal', 'dean', 'hod', 'bursar']))
                    <a href="/school/{{ request()->route('school') }}/reports" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Reports & Analytics</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal']))
                    <a href="/school/{{ request()->route('school') }}/website/manage" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">School Website & Events</a>
                @endif
                @if(Auth::user()->role === 'parent')
                    <a href="/school/{{ request()->route('school') }}/transport/my-children" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">My Children's Transport</a>
                @endif

                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'vice_principal', 'dean', 'hod', 'class_teacher', 'subject_teacher']))
                    <a href="/school/{{ request()->route('school') }}/timetable" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Timetable</a>
                @endif

                {{-- Attendance for school leadership and class teachers --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean', 'hod', 'class_teacher']))
                    <a href="/school/{{ request()->route('school') }}/attendance" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Attendance</a>
                @endif

                {{-- Teachers, HOD, Dean, Admin, Principal can enter scores --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean', 'hod', 'class_teacher', 'subject_teacher']))
                    <a href="/school/greenfield/scores" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Score Entry</a>
                @endif

                {{-- Everyone except students can see Results (we will restrict further later) --}}
                @if(in_array(Auth::user()->role, ['school_admin', 'principal', 'dean', 'hod', 'class_teacher']))
                    <a href="/school/greenfield/results" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Results</a>
                @endif

                {{-- Settings only for Admin --}}
                @if(Auth::user()->role === 'school_admin')
                    <a href="/school/{{ request()->route('school') }}/settings" class="block px-4 py-2 text-white font-medium opacity-100 bg-transparent hover:bg-blue-700 focus:bg-blue-700 focus:outline-none rounded-md transition-colors duration-150">Settings</a>
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