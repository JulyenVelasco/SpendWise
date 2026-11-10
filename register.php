
<?php
require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if ($full_name === "" || $email === "" || $password === "") {
        $message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (full_name, email, password)
                    VALUES (:full_name, :email, :password)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ":full_name" => $full_name,
                ":email" => $email,
                ":password" => $hashed_password
            ]);

            $message = "Account created successfully!";

        } catch (PDOException $e) {
            if ($e->getCode() == "23000") {
                $message = "This email is already registered.";
            } else {
                $message = "Something went wrong. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | SpendWise</title>

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
            background: white;
            width: 350px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h1 {
            color: #2878C8;
            text-align: center;
        }

        p {
            text-align: center;
            color: #666;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #7041c5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #1D65A8;
        }

        .message {
            text-align: center;
            margin-bottom: 15px;
            color: #7041c5;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>SpendWise</h1>
    <p>Create your student account</p>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label>Full Name</label>
        <input type="text" name="full_name"
               placeholder="Enter your full name" required>

        <label>Email Address</label>
        <input type="email" name="email"
               placeholder="Enter your email" required>

        <label>Password</label>
        <input type="password" name="password"
               placeholder="At least 8 characters"
               minlength="8" required>

        <button type="submit">Create Account</button>

    </form>

</div>

</body>
</html>
