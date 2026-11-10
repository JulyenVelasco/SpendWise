
<?php
session_start();
require_once "db.php";

$message = "";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

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
        body {
            font-family: Arial, sans-serif;
            background: #EAF5FF;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background: #FFFFFF;
            width: 350px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(40, 120, 200, 0.12);
        }

        h1 {
            color: #2878C8;
            text-align: center;
        }

        p {
            text-align: center;
            color: #64748B;
        }

        label {
            display: block;
            margin-top: 12px;
            color: #34495E;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #C9DFF5;
            border-radius: 6px;
            box-sizing: border-box;
            outline: none;
        }

        input:focus {
            border-color: #6AADEB;
            box-shadow: 0 0 4px rgba(106, 173, 235, 0.4);
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2878C8;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            transition: 0.3s;
        }

        button:hover {
            background: #1D65A8;
        }

        .message {
            text-align: center;
            margin-bottom: 15px;
            color: #C0392B;
        }

        .register-link {
            margin-top: 20px;
            font-size: 14px;
        }

        a {
            color: #2878C8;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>SpendWise</h1>
    <p>Welcome back! Please log in.</p>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
        >

        <button type="submit">Log In</button>

    </form>

    <p class="register-link">
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

</body>
</html>
