<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SiS4 SCHOOL</title>
    <!-- เรียกใช้งาน Tailwind CSS ผ่าน CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- เรียกใช้งานฟอนต์และไอคอน (ตัวอย่างใช้ FontAwesome แทน Lucide) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4 font-sans text-gray-900">

    <div class="w-full max-w-md bg-white border border-gray-200 rounded-xl shadow-sm p-6 sm:p-8">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold tracking-tight">
                <span class="text-blue-600">SiS4</span>
                <span class="text-gray-900">SCHOOL</span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">ระบบช่วยเหลืองานธุระการคุณครู</p>
        </div>

        <!-- Form -->
        <form class="space-y-5">
            
            <!-- Role Selection -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                    Role
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <!-- Student (Active) -->
                    <button type="button" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border transition-colors text-xs font-medium border-blue-600 bg-blue-50 text-blue-700 ring-1 ring-blue-600">
                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                        นักเรียน
                    </button>
                    <!-- Teacher -->
                    <button type="button" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border transition-colors text-xs font-medium border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-300">
                        <i class="fa-solid fa-users text-lg"></i>
                        ครู / อาจารย์
                    </button>
                    <!-- Admin -->
                    <button type="button" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border transition-colors text-xs font-medium border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-300">
                        <i class="fa-solid fa-shield-halved text-lg"></i>
                        ผู้ดูแลระบบ
                    </button>
                </div>
            </div>

            <!-- Username Input -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                    Username
                </label>
                <div class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600 transition-shadow">
                    <i class="fa-solid fa-user text-gray-400"></i>
                    <input type="text" placeholder="กรอกรหัสประจำตัว" required
                        class="flex-1 bg-transparent outline-none text-sm placeholder:text-gray-400" autocomplete="username">
                </div>
            </div>

            <!-- Password Input -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                    Password
                </label>
                <div class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600 transition-shadow">
                    <i class="fa-solid fa-lock text-gray-400"></i>
                    <input type="password" placeholder="กรอกรหัสผ่าน" required
                        class="flex-1 bg-transparent outline-none text-sm placeholder:text-gray-400" autocomplete="current-password">
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <!-- Extra Options -->
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                    เข้าระบบอัตโนมัติ
                </label>
                <span class="text-blue-600 hover:text-blue-700 hover:underline cursor-pointer font-medium">
                    ลืมรหัสผ่าน?
                </span>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors">
                เข้าสู่ระบบ
                <i class="fa-solid fa-arrow-right"></i>
            </button>

            <!-- Footer Link -->
            <div class="text-center text-sm text-gray-500 pt-4 border-t border-gray-100">
                ผู้ปกครองลงทะเบียนใหม่? 
                <a href="/register" class="text-blue-600 hover:text-blue-700 hover:underline font-medium">
                    คลิกที่นี่
                </a>
            </div>
        </form>
    </div>

</body>
</html>