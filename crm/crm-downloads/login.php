<?php
include "includes/db.php";
session_start();

$msg = '';

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE user_login='$username' AND user_password='$password' AND user_login_status='1' AND user_ban_status='0'";
    $result = mysqli_query($con, $query);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['user_id'] = $row["user_id"];
        $_SESSION['user_type'] = $row["user_type"];
        $_SESSION['username'] = $row["user_login"];
        $_SESSION['fullname'] = $row["user_name"];
        $_SESSION['user_email'] = $row["user_email"];
        $_SESSION['LAST_ACTIVITY'] = time();  // Initialize activity timer

        // Redirect after login
        $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
        unset($_SESSION['redirect_after_login']); // Clear redirect

        header("Location: $redirect");
        exit();
    } else {
        $msg .= "<div class='alert alert-danger'><b>Invalid username or password!</b></div>";
    }
}

// Preserve redirect if coming from timeout
if (isset($_GET['timeout'])) {
    // Use HTTP referrer as redirect target
    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? 'index.php';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Pro - Login</title>
    <meta name="title" content="Syed Professional Services">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Syed Professional Services - Login" />
    <meta name="keywords" content="Syed Professional Services" />
    <meta name="author" content="Syed Professional Services" />

    <link rel="preload" href="Uploads/img/layer1.webp" as="image" fetchpriority="high">
    <link rel="preload" href="Uploads/img/layer2.webp" as="image" fetchpriority="high">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sp-navy: #2b2566;
            --sp-navy-dark: #211d51;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
        }

        body.sp-login {
            font-family: 'Roboto', "Segoe UI", Helvetica, Arial, sans-serif;
            min-height: 100vh;
            background: #fff;
            overflow-x: hidden;
        }

        /* Wavy background layers */
        .sp-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .sp-wave-top {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 42vh;
            background: url('Uploads/img/layer1.webp') no-repeat center center;
            background-size: cover;
            opacity: .55;
            -webkit-mask-image: linear-gradient(to bottom, #000 65%, transparent 100%);
            mask-image: linear-gradient(to bottom, #000 65%, transparent 100%);
        }

        .sp-wave-bottom {
            position: absolute;
            bottom: 0;
            left: -30%;
            width: 160%;
            height: 55vh;
            background: url('Uploads/img/layer2.webp') no-repeat center bottom;
            background-size: cover;
            opacity: .8;
            -webkit-mask-image: linear-gradient(to top, #000 70%, transparent 100%);
            mask-image: linear-gradient(to top, #000 70%, transparent 100%);
        }

        /* Floating icons */
        .sp-icon {
            position: absolute;
            width: clamp(55px, 7vw, 105px);
            height: auto;
            filter: drop-shadow(0 10px 18px rgba(33, 29, 81, .18));
            will-change: transform;
            animation: sp-float 6s ease-in-out infinite;
        }

        .sp-icon:nth-of-type(odd)  { animation-duration: 7s; }
        .sp-icon:nth-of-type(3n)   { animation-delay: -2s; }
        .sp-icon:nth-of-type(3n+1) { animation-delay: -4s; }

        @keyframes sp-float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .sp-icon { animation: none; }
        }

        .sp-icon.i-tax      { top: 5%;  left: 21%; }
        .sp-icon.i-calc     { top: 13%; left: 5%;  }
        .sp-icon.i-1040     { top: 38%; left: 11%; }
        .sp-icon.i-check    { top: 62%; left: 8%;  }
        .sp-icon.i-bank     { bottom: 4%; left: 36%; }
        .sp-icon.i-passport { top: 8%;  right: 7%;  }
        .sp-icon.i-visa     { top: 37%; right: 9%;  }
        .sp-icon.i-globe    { bottom: 8%; right: 16%; }

        /* Center content */
        .sp-login-wrap {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .sp-login-box {
            width: 100%;
            max-width: 340px;
            text-align: center;
        }

        .sp-logo {
            width: 230px;
            max-width: 80%;
            height: auto;
            margin-bottom: 10px;
        }

        .sp-login-box h1 {
            font-size: 30px;
            font-weight: 400;
            color: #1f1f1f;
            margin: 0 0 26px;
            letter-spacing: normal;
        }

        .sp-login-box form {
            text-align: left;
        }

        .sp-field {
            margin-bottom: 18px;
        }

        .sp-field label {
            display: block;
            font-size: 15px;
            color: #444;
            margin-bottom: 7px;
        }

        .sp-field input {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            color: #333;
            background: #fff;
            border: 1px solid #c7c7c7;
            border-radius: 6px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .sp-field input::placeholder {
            color: #9aa0a6;
        }

        .sp-field input:focus {
            border-color: var(--sp-navy);
            box-shadow: 0 0 0 3px rgba(43, 37, 102, .12);
        }

        .sp-btn {
            width: 100%;
            margin-top: 8px;
            padding: 14px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            background: var(--sp-navy);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background .15s ease;
        }

        .sp-btn:hover {
            background: var(--sp-navy-dark);
        }

        .sp-alert {
            text-align: center;
            font-size: 14px;
            padding: 11px 14px;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .sp-alert-danger {
            color: #842029;
            background: #f8d7da;
            border: 1px solid #f1aeb5;
        }

        .sp-alert-warning {
            color: #664d03;
            background: #fff3cd;
            border: 1px solid #ffe69c;
        }

        @media (max-width: 767px) {
            .sp-icon.i-tax,
            .sp-icon.i-1040,
            .sp-icon.i-check,
            .sp-icon.i-bank,
            .sp-icon.i-visa {
                display: none;
            }

            .sp-icon {
                width: 48px;
                opacity: .8;
            }
        }

        @media (max-width: 575px) {
            .sp-login-box h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body class="sp-login">
    <div class="sp-bg">
        <div class="sp-wave-top"></div>
        <div class="sp-wave-bottom"></div>
        <img class="sp-icon i-tax" src="Uploads/img/tax_document.png" alt="">
        <img class="sp-icon i-calc" src="Uploads/img/calculator.png" alt="">
        <img class="sp-icon i-1040" src="Uploads/img/form_1040.png" alt="">
        <img class="sp-icon i-check" src="Uploads/img/checklist.png" alt="">
        <img class="sp-icon i-bank" src="Uploads/img/bank.png" alt="">
        <img class="sp-icon i-passport" src="Uploads/img/passport.png" alt="">
        <img class="sp-icon i-visa" src="Uploads/img/visa.png" alt="">
        <img class="sp-icon i-globe" src="Uploads/img/globe.png" alt="">
    </div>

    <div class="sp-login-wrap">
        <div class="sp-login-box">
            <img class="sp-logo" src="Uploads/img/login_logo.png" alt="Syed Professional Services">
            <?php
            if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
                echo "<div class='sp-alert sp-alert-warning'><b>Logged out due to 15 minutes of inactivity. Please log in again to proceed.</b></div>";
            }
            if (!empty($msg)) {
                echo "<div class='sp-alert sp-alert-danger'><b>Invalid username or password!</b></div>";
            }
            ?>
            <h1>LOGIN </h1>
            <form method="POST" id="loginForm">
                <div class="sp-field">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" required placeholder="Enter Your Username"
                        autofocus
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div class="sp-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required placeholder="Enter Your Password">
                </div>
                <button type="submit" class="sp-btn" name="login">LOGIN</button>
            </form>
        </div>
    </div>
</body>

</html>
