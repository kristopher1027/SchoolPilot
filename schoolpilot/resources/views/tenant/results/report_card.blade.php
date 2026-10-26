<!DOCTYPE html>
<html>
<head>
    <title>Report Card - {{ $student->first_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased p-8">

    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-lg">
        
        <!-- Print Button -->
        <div class="no-print mb-6 flex justify-between">
            <a href="javascript:history.back()" class="text-blue-600 hover:underline">&larr; Back to Results</a>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded shadow">Print / Save as PDF</button>
        </div>

        <!-- Header -->
        <div class="text-center border-b-2 border-gray-800 pb-4 mb-6">
            <h1 class="text-3xl font-bold text-gray-800">GREENFIELD ACADEMY</h1>
            <p class="text-sm text-gray-500">123 School Road, Lagos, Nigeria</p>
            <h2 class="text-xl font-semibold text-gray-700 mt-2">TERM REPORT CARD</h2>
        </div>

        <!-- Student Info -->
        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
            <div>
                <p><strong>Student Name:</strong> {{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</p>
                <p><strong>Admission No:</strong> {{ $student->admission_no }}</p>
                <p><strong>Class:</strong> {{ $class->name }}</p>
            </div>
            <div class="text-right">
                <p><strong>Session:</strong> {{ $session->name }}</p>
                <p><strong>Term:</strong> {{ $term->name }}</p>
                <p><strong>Date:</strong> {{ date('d M Y') }}</p>
            </div>
        </div>

        <!-- Subject Table -->
        <table class="min-w-full divide-y divide-gray-300 border border-gray-300 mb-6">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Subject</th>
                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-700 uppercase">Total Score</th>
                    <th class="px-4 py-2 text-center text-xs font-bold text-gray-700 uppercase">Grade</th>
                    <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Remark</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($subjectScores as $sub)
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-800">{{ $sub['subject'] }}</td>
                        <td class="px-4 py-2 text-center text-sm font-semibold text-gray-800">{{ $sub['total'] }}</td>
                        <td class="px-4 py-2 text-center text-sm font-bold text-blue-700">{{ $sub['grade'] }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $sub['remark'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-300">
                <tr>
                    <td class="px-4 py-2 text-right text-sm font-bold text-gray-800">Total Score:</td>
                    <td class="px-4 py-2 text-center text-sm font-bold text-blue-800">{{ $totalScore }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures -->
        <div class="mt-12 flex justify-between text-sm text-gray-500">
            <div class="border-t border-gray-400 pt-2 w-40 text-center">Class Teacher</div>
            <div class="border-t border-gray-400 pt-2 w-40 text-center">Principal</div>
        </div>

    </div>
</body>
</html>