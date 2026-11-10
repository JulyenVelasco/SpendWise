
<?php
session_start();
require_once "db.php";

// CHECK IF USER IS LOGGED IN
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$expense_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$expense_id) {
    header("Location: expenses.php");
    exit();
}

$message = "";
$messageType = "";

// CREATE CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// GET CATEGORIES BELONGING TO THE LOGGED-IN USER
$stmt = $conn->prepare(
    "SELECT category_name
     FROM categories
     WHERE user_id = :user_id
     ORDER BY category_name ASC"
);

$stmt->execute([
    ":user_id" => $user_id
]);

$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// GET THE SELECTED EXPENSE BELONGING TO THE LOGGED-IN USER
$stmt = $conn->prepare(
    "SELECT *
     FROM expenses
     WHERE id = :id AND user_id = :user_id"
);

$stmt->execute([
    ":id" => $expense_id,
    ":user_id" => $user_id
]);

$expense = $stmt->fetch(PDO::FETCH_ASSOC);

// CHECK IF EXPENSE EXISTS
if (!$expense) {
    header("Location: expenses.php");
    exit();
}

// KEEP ORIGINAL CATEGORY AVAILABLE IF IT WAS DELETED
if (!in_array($expense["category"], $categories, true)) {
    $categories[] = $expense["category"];
}

// HANDLE EXPENSE UPDATE
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $amount = $_POST["amount"] ?? "";
    $expense_date = $_POST["expense_date"] ?? "";

    // VERIFY CSRF TOKEN
    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {

        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";

    } elseif (!in_array($category, $categories, true)) {

        $message = "Please select a valid category.";
        $messageType = "error";

    } elseif ($description === "") {

        $message = "Please enter a description.";
        $messageType = "error";

    } elseif (strlen($description) > 255) {

        $message = "Description must not exceed 255 characters.";
        $messageType = "error";

    } elseif (
        !is_numeric($amount) ||
        (float) $amount <= 0 ||
        (float) $amount > 99999999.99
    ) {

        $message = "Please enter a valid expense amount.";
        $messageType = "error";

    } elseif (
        $expense_date === "" ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date) ||
        !checkdate(
            (int) substr($expense_date, 5, 2),
            (int) substr($expense_date, 8, 2),
            (int) substr($expense_date, 0, 4)
        )
    ) {

        $message = "Please select a valid expense date.";
        $messageType = "error";

    } else {

        // CHECK THAT THE SELECTED CATEGORY BELONGS TO THIS USER
        $stmt = $conn->prepare(
            "SELECT COUNT(*)
             FROM categories
             WHERE user_id = :user_id
             AND category_name = :category"
        );

        $stmt->execute([
            ":user_id" => $user_id,
            ":category" => $category
        ]);

        $categoryExists = (int) $stmt->fetchColumn() > 0;

        // ALLOW THE ORIGINAL CATEGORY IF IT IS UNCHANGED
        $originalCategory = $expense["category"];

        if (!$categoryExists && $category !== $originalCategory) {

            $message = "Please select one of your existing categories.";
            $messageType = "error";

        } else {

            try {

                // UPDATE EXPENSE
                $sql = "UPDATE expenses
                        SET category = :category,
                            description = :description,
                            amount = :amount,
                            expense_date = :expense_date
                        WHERE id = :id
                        AND user_id = :user_id";

                $stmt = $conn->prepare($sql);

                $stmt->execute([
                    ":category" => $category,
                    ":description" => $description,
                    ":amount" => $amount,
                    ":expense_date" => $expense_date,
                    ":id" => $expense_id,
                    ":user_id" => $user_id
                ]);

                // RETURN TO EXPENSE HISTORY AFTER SUCCESSFUL UPDATE
                header("Location: expenses.php");
                exit();

            } catch (PDOException $e) {

                error_log($e->getMessage());

                $message = "Unable to update the expense. Please try again.";
                $messageType = "error";

            }

        }

    }

    // KEEP ENTERED VALUES IF VALIDATION FAILS
    $expense["category"] = $category;
    $expense["description"] = $description;
    $expense["amount"] = $amount;
    $expense["expense_date"] = $expense_date;

}

// ESCAPE OUTPUT SAFELY
function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Expense | SpendWise</title>

    <!-- LOAD SAVED THEME BEFORE DISPLAYING THE PAGE -->
    <script>
        try {

            const savedTheme = localStorage.getItem(
                "spendwise_theme_<?php echo $user_id; ?>"
            );

            if (savedTheme === "dark") {
                document.documentElement.setAttribute("data-theme", "dark");
            }

        } catch (error) {

            console.warn("Unable to load saved theme.", error);

        }
    </script>

    <style>

        * {
            box-sizing: border-box;
        }

        /* LIGHT MODE COLORS */
        :root {
            color-scheme: light;

            --page-bg: #F0F6FF;
            --surface: #FFFFFF;
            --surface-secondary: #EAF3FF;
            --surface-panel: #EDF6FF;

            --text: #273D59;
            --heading: #123B70;
            --muted: #7B8DA5;

            --border: #DCEAFB;

            --primary: #1769D2;
            --primary-light: #318DE5;
            --deep-blue: #1555A5;

            --shadow: 0 8px 25px rgba(29, 91, 157, 0.07);
            --shadow-hover: 0 12px 28px rgba(29, 91, 157, 0.13);

            --input-bg: #FFFFFF;

            --success-bg: #D9F5E5;
            --success-text: #218653;

            --error-bg: #FFE0E0;
            --error-text: #C0392B;
        }

        /* DARK MODE COLORS */
        :root[data-theme="dark"] {
            color-scheme: dark;

            --page-bg: #09182B;
            --surface: #122A43;
            --surface-secondary: #1A3857;
            --surface-panel: #183550;

            --text: #E2ECF8;
            --heading: #F0F6FF;
            --muted: #A7BBD1;

            --border: #294662;

            --primary: #65B3FF;
            --primary-light: #3F9BEF;
            --deep-blue: #8CC8FF;

            --shadow: 0 8px 25px rgba(0, 0, 0, 0.18);
            --shadow-hover: 0 12px 28px rgba(0, 0, 0, 0.28);

            --input-bg: #183550;

            --success-bg: #174B3C;
            --success-text: #A1EAC8;

            --error-bg: #582D3B;
            --error-text: #FFB5C2;
        }

        body {
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at 8% 5%, rgba(89, 180, 242, 0.13), transparent 27%),
                var(--page-bg);
            margin: 0;
            color: var(--text);
            min-height: 100vh;
            transition: background 0.25s ease, color 0.25s ease;
        }

        /* NAVIGATION BAR */

        .navbar {
            background: var(--surface);
            padding: 17px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border);
        }

        .logo {
            color: var(--primary);
            font-size: 25px;
            font-weight: 800;
            text-decoration: none;
            letter-spacing: -0.8px;
        }

        .logo span {
            color: var(--heading);
        }

        .nav-links {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            padding: 10px 13px;
            border-radius: 8px;
            transition: 0.2s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary);
            background: var(--surface-secondary);
        }

        .nav-links .logout {
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            padding: 10px 17px;
        }

        .nav-links .logout:hover {
            background: #124E9B;
            color: white;
        }

        /* DARK MODE BUTTON */

        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-secondary);
            color: var(--primary);
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .theme-toggle:hover {
            background: var(--surface-panel);
            transform: translateY(-2px);
        }

        .theme-icon {
            font-size: 16px;
        }

        /* MAIN CONTENT */

        .main {
            max-width: 950px;
            margin: 38px auto;
            padding: 0 25px 35px;
        }

        .breadcrumb {
            display: inline-block;
            color: var(--primary);
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            margin-bottom: 15px;
        }

        .breadcrumb:hover {
            text-decoration: underline;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: var(--heading);
            font-size: 29px;
            letter-spacing: -0.8px;
            margin: 0 0 10px;
        }

        .page-heading p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        /* CARD */

        .card {
            background: var(--surface);
            padding: 30px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: background 0.25s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card h2 {
            color: var(--heading);
            font-size: 19px;
            margin: 0 0 9px;
        }

        .section-subtitle {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            margin: 0 0 22px;
        }

        /* FORM */

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            color: var(--text);
            margin: 0 0 9px;
            font-weight: bold;
            font-size: 13px;
        }

        input,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            color: var(--text);
            background: var(--input-bg);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(49, 141, 229, 0.13);
        }

        input::placeholder {
            color: var(--muted);
            opacity: 0.8;
        }

        /* CATEGORY NOTE */

        .category-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 10px;
            line-height: 1.6;
        }

        .category-note a {
            color: var(--primary);
            font-weight: bold;
            text-decoration: none;
        }

        .category-note a:hover {
            text-decoration: underline;
        }

        /* BUTTONS */

        button {
            font-family: Arial, sans-serif;
        }

        .submit-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            margin-top: 8px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 12px rgba(23, 105, 210, 0.17);
            transition: 0.2s ease;
        }

        .submit-button:hover {
            background: linear-gradient(135deg, #124E9B, #1769D2);
            transform: translateY(-2px);
        }

        .cancel-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            margin-top: 12px;
            padding: 13px 18px;
            background: var(--surface-secondary);
            color: var(--primary);
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .cancel-button:hover {
            background: var(--surface-panel);
        }

        /* ERROR MESSAGE */

        .message {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        /* ACCESSIBILITY */

        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 3px solid var(--primary-light);
            outline-offset: 3px;
        }

        /* FOOTER */

        .footer {
            text-align: center;
            padding: 20px;
            color: var(--muted);
            font-size: 11px;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 850px) {

            .navbar {
                padding-left: 25px;
                padding-right: 25px;
            }

        }

        @media (max-width: 650px) {

            .navbar {
                flex-direction: column;
                align-items: flex-start;
                padding: 17px 20px;
            }

            .nav-links {
                width: 100%;
                gap: 5px;
            }

            .nav-links a {
                padding: 9px 10px;
                font-size: 11px;
            }

            .theme-toggle {
                margin-top: 5px;
            }

            .main {
                margin-top: 27px;
                padding: 0 15px 25px;
            }

            .page-heading h1 {
                font-size: 24px;
            }

            .card {
                padding: 20px;
                border-radius: 13px;
            }

        }

    </style>

</head>

<body>

    <!-- NAVIGATION BAR -->
    <nav class="navbar">

        <a href="dashboard.php" class="logo">
            Spend<span>Wise</span>
        </a>

        <div class="nav-links">

            <a href="dashboard.php">Dashboard</a>

            <a href="allowance.php">Allowance</a>

            <a href="expenses.php" class="active" aria-current="page">
                Expenses
            </a>

            <a href="categories.php">Categories</a>

            <a href="logout.php" class="logout">Logout</a>

        </div>

        <!-- DARK MODE BUTTON -->
        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Switch between light and dark mode"
            aria-pressed="false"
        >
            <span class="theme-icon" id="themeIcon">☾</span>
            <span id="themeLabel">Dark Mode</span>
        </button>

    </nav>

    <main class="main">

        <a href="expenses.php" class="breadcrumb">
            ← Back to Expense History
        </a>

        <!-- PAGE HEADING -->
        <section class="page-heading">

            <h1>Edit Expense</h1>

            <p>
                Update your expense information and keep your spending records accurate.
            </p>

        </section>

        <!-- EDIT EXPENSE FORM -->
        <section class="card">

            <h2>Expense Information</h2>

            <p class="section-subtitle">
                Modify the information below and save your changes.
            </p>

            <?php if ($message !== ""): ?>

                <div
                    class="message <?php echo escape($messageType); ?>"
                    role="alert"
                    aria-live="assertive"
                >
                    <?php echo escape($message); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escape($_SESSION["csrf_token"]); ?>"
                >

                <!-- CATEGORY -->
                <div class="form-group">

                    <label for="category">
                        Expense Category
                    </label>

                    <select id="category" name="category" required>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?php echo escape($category); ?>"
                                <?php echo $expense["category"] === $category ? "selected" : ""; ?>
                            >
                                <?php echo escape($category); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <p class="category-note">
                        Need to add or manage categories?

                        <a href="categories.php">
                            Manage Categories
                        </a>
                    </p>

                </div>

                <!-- DESCRIPTION -->
                <div class="form-group">

                    <label for="description">
                        Description
                    </label>

                    <input
                        type="text"
                        id="description"
                        name="description"
                        maxlength="255"
                        placeholder="Example: Lunch at school"
                        value="<?php echo escape($expense["description"]); ?>"
                        required
                    >

                </div>

                <!-- AMOUNT -->
                <div class="form-group">

                    <label for="amount">
                        Amount (₱)
                    </label>

                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        min="0.01"
                        max="99999999.99"
                        step="0.01"
                        placeholder="Enter expense amount"
                        value="<?php echo escape($expense["amount"]); ?>"
                        required
                    >

                </div>

                <!-- EXPENSE DATE -->
                <div class="form-group">

                    <label for="expense_date">
                        Expense Date
                    </label>

                    <input
                        type="date"
                        id="expense_date"
                        name="expense_date"
                        value="<?php echo escape($expense["expense_date"]); ?>"
                        required
                    >

                </div>

                <!-- SAVE CHANGES -->
                <button type="submit" class="submit-button">
                    Save Changes
                </button>

                <a href="expenses.php" class="cancel-button">
                    Cancel and Return to Expenses
                </a>

            </form>

        </section>

    </main>

    <footer class="footer">
        SpendWise · Student Expense and Allowance Tracker
    </footer>

    <!-- UNIVERSAL DARK MODE -->
    <script>

        const themeToggle = document.getElementById("themeToggle");
        const themeIcon = document.getElementById("themeIcon");
        const themeLabel = document.getElementById("themeLabel");

        // SAME STORAGE KEY USED BY ALL OTHER PAGES
        const themeStorageKey =
            "spendwise_theme_<?php echo $user_id; ?>";

        // APPLY SELECTED THEME
        function applyTheme(theme, savePreference = true) {

            const isDark = theme === "dark";

            document.documentElement.setAttribute(
                "data-theme",
                isDark ? "dark" : "light"
            );

            themeIcon.textContent = isDark ? "☀" : "☾";

            themeLabel.textContent = isDark
                ? "Light Mode"
                : "Dark Mode";

            themeToggle.setAttribute(
                "aria-pressed",
                String(isDark)
            );

            if (savePreference) {

                try {

                    localStorage.setItem(
                        themeStorageKey,
                        theme
                    );

                } catch (error) {

                    console.warn(
                        "Unable to save theme preference.",
                        error
                    );

                }

            }

        }

        // LOAD CURRENTLY SAVED THEME
        const currentTheme =
            document.documentElement.getAttribute("data-theme") || "light";

        applyTheme(currentTheme, false);

        // SWITCH BETWEEN LIGHT AND DARK MODE
        themeToggle.addEventListener("click", function() {

            const activeTheme =
                document.documentElement.getAttribute("data-theme");

            const newTheme =
                activeTheme === "dark"
                    ? "light"
                    : "dark";

            applyTheme(newTheme);

        });

    </script>

</body>
</html>
