<?php require 'config/init.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiagnoSys Lab</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <!-- Navbar -->
    <nav class="p-4 bg-white shadow flex justify-between items-center">
        <a href="index.php" class="font-bold text-xl text-blue-600">DiagnoSys 🏥</a>
        <div>
            <?php if(isLoggedIn()): ?>
                <a href="logout.php" class="text-gray-600 hover:text-red-500">Logout</a>
            <?php else: ?>
                <a href="login.php" class="mr-4 text-gray-600 hover:text-blue-500">Login</a>
                <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto p-10">
        <!-- Display Alert Messages if any -->
        <?php alertMessage(); ?>

        <div class="text-center mt-10">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">World Class Diagnostics</h1>
            <p class="text-gray-600 text-lg mb-8">Book your appointment today without standing in queues.</p>
            <a href="register.php" class="bg-green-500 text-white text-xl px-8 py-3 rounded-full hover:bg-green-600 shadow-lg">Book Appointment Now</a>
        </div>
    </div>
</body>
</html>