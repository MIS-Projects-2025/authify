<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSO Login</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
      @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

      body {
          font-family: 'Poppins', sans-serif;
      }
    </style>
</head>
<body class=" flex items-center justify-center h-screen">
    <form method="POST" action="{{ route('login') }}" class="w-[450px]">

        <input type="hidden" name="redirect" value="{{ $redirect }}">

        <p class="text-[70pt] font-bold mb-0 text-center"><span class="text-blue-600">auth</span>ify</p>
        <p class="text-[12pt] font-normal mb-6 mt-0 text-center">A seamless Single Sign-On experience, allowing users to log in once and gain instant access accross multiple applications.</p>

        @if (request('status'))
        <div class="text-center mb-4 p-2 bg-red-600 text-white rounded">
           {{ base64_decode(request('status')) }}
        </div>
        @endif
        
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1" for="employeeID">Employee ID</label>
            <input type="text" name="employeeID" id="employeeID" class="w-full px-4 py-2 border rounded" value="{{ old('employeeID') }}" required>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium mb-1" for="password">Password</label>
            <input type="password" name="password" id="password" class="w-full px-4 py-2 border rounded" required>
        </div>

        <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">
            Login
        </button>
    </form>
</body>
</html>
