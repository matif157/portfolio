<!--<!DOCTYPE html>
<html>
<head>
    <title>CRM - Register</title>
</head>
<body>
    <h2>Create an Account</h2>
    <form action="register_logic.php" method="POST">
        <input type="text" name="username" placeholder="Username" required><br><br>
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Sign Up</button>
        <button type="submit">Register</button>
</form>

<hr>
<p>Already have an account? <a href="login.php" style="font-weight: bold; color: blue;">Sign In Here</a></p>
    </form>
</body>
</html> -->'


<!DOCTYPE html>
<html>
<head>
    <title>Register - My CRM</title>
</head>
<body>
    <h2>Create an Account</h2>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'taken'): ?>
        <p style="color: red; font-weight: bold;">Error: That username or email is already taken. Please try another one.</p>
    <?php endif; ?>

    <form action="register_logic.php" method="POST">
        <input type="text" name="username" placeholder="Choose Username" required>
        <br><br>
        <input type="email" name="email" placeholder="Your Email" required>
        <br><br>
        <input type="password" name="password" placeholder="Choose Password" required>
        <br><br>
        <button type="submit">Register</button>
    </form>
    
    <hr>
    <p>Already have an account? <a href="login.php" style="font-weight: bold; color: blue;">Sign In Here</a></p>
</body>
</html>