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
            <nav class="flex-1 p-4 space-y-2">
                <a href="/school/greenfield/dashboard" class="block px-4 py-2 bg-blue-800 rounded">Dashboard</a>
                <a href="#" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Students</a>
                <a href="#" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Staff</a>
                <a href="#" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Classes & Subjects</a>
                <a href="#" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Results</a>
                <a href="#" class="block px-4 py-2 hover:bg-blue-800 rounded transition">Settings</a>
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