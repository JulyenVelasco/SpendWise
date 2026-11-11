
<?php
session_start();
require_once "db.php";

$message = "";
$email = "";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $message = "Please enter your email and password.";
    } else {
        $sql = "SELECT id, full_name, email, password
                FROM users
                WHERE email = :email
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([":email" => $email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["full_name"];

            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | SpendWise</title>

    <style>
        :root {
            --navy: #102b5c;
            --royal-blue: #2563eb;
            --blue: #2878c8;
            --sky-blue: #38bdf8;
            --cyan: #67e8f9;
            --text: #19345a;
            --muted: #7183a0;
            --border: #dce8f7;
            --background: #f1f7ff;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at 5% 15%,
                    rgba(125, 211, 252, 0.28), transparent 30%),
                radial-gradient(circle at 95% 85%,
                    rgba(96, 165, 250, 0.22), transparent 30%),
                var(--background);
            color: var(--text);
        }

        a {
            text-decoration: none;
        }

        .page-wrapper {
            width: min(1160px, calc(100% - 48px));
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* TOP BRAND HEADER */

        .topbar {
            padding: 28px 0 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-size: 24px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.8px;
        }

        .brand-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                var(--royal-blue),
                var(--sky-blue)
            );
            color: white;
            font-size: 23px;
            font-weight: 800;
            box-shadow: 0 5px 14px rgba(37, 99, 235, 0.2);
        }

        .topbar-note {
            font-size: 13px;
            color: var(--muted);
            font-weight: 500;
        }

        /* MAIN TWO-COLUMN LAYOUT */

        .auth-container {
            flex: 1;
            display: grid;
            grid-template-columns: 1.04fr 1fr;
            min-height: 575px;
            background: #ffffff;
            border: 1px solid #e2edfb;
            border-radius: 26px;
            overflow: hidden;
            box-shadow:
                0 20px 65px rgba(32, 78, 145, 0.11);
        }

        /* LEFT INFORMATION PANEL */

        .intro-panel {
            position: relative;
            overflow: hidden;
            background: linear-gradient(
                145deg,
                #112d68 0%,
                #2055b2 54%,
                #3089e8 100%
            );
            color: #ffffff;
            padding: 52px 46px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .intro-panel::before {
            content: "";
            position: absolute;
            width: 360px;
            height: 360px;
            top: -155px;
            right: -130px;
            border: 60px solid rgba(255, 255, 255, 0.055);
            border-radius: 50%;
            pointer-events: none;
        }

        .intro-panel::after {
            content: "";
            position: absolute;
            width: 270px;
            height: 270px;
            bottom: -155px;
            left: -115px;
            background: rgba(103, 232, 249, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }

        .intro-content {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 15px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 25px;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            background: var(--cyan);
            border-radius: 50%;
        }

        .intro-panel h1 {
            font-size: clamp(34px, 3.4vw, 49px);
            line-height: 1.16;
            letter-spacing: -1.7px;
            margin: 0 0 20px;
            max-width: 450px;
        }

        .intro-panel h1 span {
            color: #a8ebff;
        }

        .intro-description {
            color: #dceaff;
            font-size: 15px;
            line-height: 1.9;
            max-width: 440px;
            margin: 0 0 27px;
        }

        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 32px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 500;
            color: #eef6ff;
        }

        .feature-check {
            width: 25px;
            height: 25px;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 8px;
            background: rgba(103, 232, 249, 0.17);
            color: #b7f5ff;
            font-weight: bold;
        }

        /* DECORATIVE FEATURE CARD */

        .preview-card {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.19);
            border-radius: 18px;
            padding: 19px 21px;
            backdrop-filter: blur(10px);
            max-width: 390px;
        }

        .preview-heading {
            font-size: 11px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #bfdeff;
            margin-bottom: 9px;
        }

        .preview-title {
            font-size: 18px;
            font-weight: 750;
            margin-bottom: 7px;
        }

        .preview-text {
            font-size: 12px;
            line-height: 1.7;
            color: #dceaff;
        }

        .preview-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .preview-tags span {
            background: rgba(255, 255, 255, 0.14);
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 11px;
            color: white;
        }

        /* RIGHT LOGIN PANEL */

        .form-panel {
            padding: 48px clamp(30px, 5vw, 70px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .form-heading {
            margin-bottom: 29px;
        }

        .form-label {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 40px;
            background: #eaf3ff;
            color: #2563eb;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .form-heading h2 {
            font-size: 33px;
            font-weight: 800;
            letter-spacing: -1px;
            color: var(--navy);
            margin: 0 0 10px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
            margin: 0;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 650;
            color: var(--text);
            margin-bottom: 9px;
        }

        .input-group input {
            display: block;
            width: 100%;
            padding: 14px 15px;
            border: 1.5px solid var(--border);
            border-radius: 11px;
            background: #f9fcff;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .input-group input::placeholder {
            color: #9aaac0;
        }

        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .login-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(
                110deg,
                #2055cf,
                #328cec
            );
            color: #ffffff;
            border: none;
            border-radius: 11px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 750;
            cursor: pointer;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.19);
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 9px 22px rgba(37, 99, 235, 0.25);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:focus-visible,
        a:focus-visible {
            outline: 3px solid #60a5fa;
            outline-offset: 3px;
        }

        .error-message {
            display: flex;
            gap: 9px;
            align-items: flex-start;
            padding: 13px 15px;
            background: #fff2f2;
            color: #b42331;
            border: 1px solid #ffd6d6;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        .register-prompt {
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            margin: 25px 0 0;
        }

        .register-prompt a {
            color: var(--royal-blue);
            font-weight: 750;
        }

        .register-prompt a:hover {
            text-decoration: underline;
        }

        .secure-note {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid #ebf1fa;
            font-size: 11px;
            color: #8696aa;
            text-align: center;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #8a9db6;
            padding: 20px 0;
            line-height: 1.6;
        }

        /* TABLET RESPONSIVENESS */

        @media (max-width: 900px) {
            .intro-panel {
                padding: 38px 30px;
            }

            .form-panel {
                padding: 38px 30px;
            }

            .intro-panel h1 {
                font-size: 35px;
            }

            .form-heading h2 {
                font-size: 29px;
            }
        }

        /* MOBILE RESPONSIVENESS */

        @media (max-width: 680px) {
            .page-wrapper {
                width: min(100% - 28px, 480px);
                min-height: 100vh;
            }

            .topbar {
                padding: 18px 0;
            }

            .brand {
                font-size: 20px;
            }

            .brand-icon {
                width: 36px;
                height: 36px;
                border-radius: 11px;
                font-size: 20px;
            }

            .topbar-note {
                display: none;
            }

            .auth-container {
                grid-template-columns: 1fr;
                min-height: 0;
                border-radius: 20px;
            }

            .intro-panel {
                padding: 25px 24px 22px;
            }

            .eyebrow {
                padding: 6px 11px;
                font-size: 10px;
                margin-bottom: 12px;
            }

            .intro-panel h1 {
                font-size: 27px;
                letter-spacing: -0.8px;
                margin-bottom: 10px;
            }

            .intro-description {
                font-size: 12px;
                line-height: 1.65;
                margin: 0;
            }

            .feature-list,
            .preview-card {
                display: none;
            }

            .form-panel {
                padding: 27px 22px 28px;
            }

            .form-heading {
                margin-bottom: 20px;
            }

            .form-label {
                margin-bottom: 11px;
                font-size: 10px;
            }

            .form-heading h2 {
                font-size: 26px;
            }

            .form-heading p {
                font-size: 12px;
            }

            .input-group {
                margin-bottom: 15px;
            }

            .input-group input {
                padding: 12px 13px;
                font-size: 13px;
            }

            .login-button {
                padding: 13px;
            }

            .register-prompt {
                margin-top: 18px;
            }

            .secure-note {
                margin-top: 19px;
                padding-top: 16px;
            }

            .footer {
                padding: 14px 0 18px;
                font-size: 11px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .login-button,
            .input-group input {
                transition: none;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <!-- BRAND HEADER -->

    <header class="topbar">

        <a class="brand" href="index.php">
            <span class="brand-icon" aria-hidden="true">₱</span>
            <span>SpendWise</span>
        </a>

        <span class="topbar-note">
            Your smarter way to manage student expenses.
        </span>

    </header>

    <!-- MAIN CONTENT -->

    <main class="auth-container">

        <!-- LEFT INTRODUCTION SECTION -->

        <section class="intro-panel">

            <div class="intro-content">

                <div class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    MADE FOR STUDENTS
                </div>

                <h1>
                    Take control of your
                    <span>money, one expense at a time.</span>
                </h1>

                <p class="intro-description">
                    SpendWise can help you manage your expenses and allowance
                    a tracker built and designed to help students
                    manage their daily spending, organize
                    expenses, and stay within their budget.
                </p>

                <div class="feature-list">

                    <div class="feature">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Track your daily expenses effortlessly</span>
                    </div>

                    <div class="feature">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Monitor your allowance and remaining balance</span>
                    </div>

                    <div class="feature">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Understand spending with visual insights</span>
                    </div>

                </div>

                <div class="preview-card">

                    <div class="preview-heading">
                        WHY SPENDWISE?
                    </div>

                    <div class="preview-title">
                        A clearer view of your finances.
                    </div>

                    <div class="preview-text">
                        From school supplies to daily meals,
                        keep your spending organized in one
                        simple dashboard.
                    </div>

                    <div class="preview-tags">
                        <span>Allowance Tracking</span>
                        <span>Expense Categories</span>
                        <span>Budget Monitoring</span>
                    </div>

                </div>

            </div>

        </section>

        <!-- RIGHT LOGIN SECTION -->

        <section class="form-panel">

            <div class="form-heading">

                <span class="form-label">
                    YOUR ACCOUNT
                </span>

                <h2>Welcome back!</h2>

                <p>
                    Log in to your account to access your
                    SpendWise dashboard and manage your finances.
                </p>

            </div>

            <?php if ($message !== ""): ?>

                <div class="error-message" role="alert">
                    <span aria-hidden="true">!</span>
                    <span>
                        <?php
                        echo htmlspecialchars(
                            $message,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </span>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <div class="input-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        value="<?php
                            echo htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        required
                    >

                </div>

                <div class="input-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="login-button"
                >
                    Log In to SpendWise
                </button>

            </form>

            <p class="register-prompt">
                New to SpendWise?
                <a href="register.php">
                    Create an account
                </a>
            </p>

            <div class="secure-note">
                <span aria-hidden="true">🔒</span>
                <span>
                    Account access protected by password verification.
                </span>
            </div>

        </section>

    </main>

    <footer class="footer">
        SpendWise — A Web-Based Student Expense and Allowance Tracker
    </footer>

</div>

</body>
</html>
