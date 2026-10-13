<!--<!DOCTYPE html>
<html>
<head>
    <title>Login - My CRM</title>
</head>
<body>
    <h2>Login to Your CRM</h2>
    <form action="login_logic.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <br><br>
        <input type="password" name="password" placeholder="Password" required>
        <br><br>
        <button type="submit">Login</button>
    </form>
    <p>Need an account? <a href="register.php">Register here</a></p>
</body>
</html>-->

<!DOCTYPE html>
<html>
<head>
    <title>Login - My CRM</title>
</head>
<body>
    <h2>Login to Your CRM</h2>

   /* ?php if (isset($_GET['message']) && $_GET['message'] == 'registered'): ?>
        <p style="color: green; font-weight: bold;">Account created successfully! Please sign in below.</p>
    ?php endif; ?>*/

    <form action="login_logic.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <br><br>
        <input type="password" name="password" placeholder="Password" required>
        <br><br>
        <button type="submit">Login</button>
    </form>
    <p>Need an account? <a href="register.php">Register here</a></p>
</body>
</html>