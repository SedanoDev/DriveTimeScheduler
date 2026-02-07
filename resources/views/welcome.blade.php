<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>DriveTime</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="antialiased bg-gray-50 flex items-center justify-center min-h-screen">
        <div class="text-center space-y-6">
            <h1 class="text-4xl font-bold text-gray-900 tracking-tight">DriveTime Scheduler</h1>
            <p class="text-lg text-gray-600">Simple booking for your driving lessons.</p>

            <div class="flex justify-center gap-4">
                <form action="/login" method="GET">
                    <x-primary-button>
                        Student Login
                    </x-primary-button>
                </form>
            </div>
        </div>
    </body>
</html>
