
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";
$messageType = "";

// CREATE CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// PROCESS ALLOWANCE FORM
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $amount = $_POST["allowance_amount"] ?? "";
    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $csrf_token)) {

        $message = "Invalid form request. Please refresh the page and try again.";
        $messageType = "error";

    } elseif (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 99999999.99) {

        $message = "Please enter a valid allowance amount.";
        $messageType = "error";

    } elseif ($start_date === "" || $end_date === "") {

        $message = "Please select both dates.";
        $messageType = "error";

    } else {

        // VALIDATE DATE FORMAT
        $startDateObject = DateTime::createFromFormat("!Y-m-d", $start_date);
        $endDateObject = DateTime::createFromFormat("!Y-m-d", $end_date);

        $validStartDate = $startDateObject &&
            $startDateObject->format("Y-m-d") === $start_date;

        $validEndDate = $endDateObject &&
            $endDateObject->format("Y-m-d") === $end_date;

        if (!$validStartDate || !$validEndDate) {

            $message = "Please enter valid dates.";
            $messageType = "error";

        } elseif ($end_date < $start_date) {

            $message = "End date cannot be earlier than start date.";
            $messageType = "error";

        } else {

            try {

                $sql = "INSERT INTO allowances
                        (user_id, allowance_amount, start_date, end_date)
                        VALUES (:user_id, :amount, :start_date, :end_date)";

                $stmt = $conn->prepare($sql);

                $stmt->execute([
                    ":user_id" => $user_id,
                    ":amount" => $amount,
                    ":start_date" => $start_date,
                    ":end_date" => $end_date
                ]);

                $message = "Allowance saved successfully!";
                $messageType = "success";

            } catch (PDOException $e) {

                error_log($e->getMessage());

                $message = "Unable to save your allowance. Please try again.";
                $messageType = "error";

            }

        }

    }

}

// GET THE USER'S ALLOWANCE RECORDS
$sql = "SELECT allowance_amount, start_date, end_date
        FROM allowances
        WHERE user_id = :user_id
        ORDER BY start_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([":user_id" => $user_id]);

$allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FORMAT VALUES SAFELY
function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Allowance | SpendWise</title>

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
            --message-success-bg: #D9F5E5;
            --message-success-text: #218653;
            --message-error-bg: #FFE0E0;
            --message-error-text: #C0392B;
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
            --message-success-bg: #174B3C;
            --message-success-text: #A1EAC8;
            --message-error-bg: #582D3B;
            --message-error-text: #FFB5C2;
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
            max-width: 1250px;
            margin: 38px auto;
            padding: 0 25px 35px;
        }

        .page-heading {
            margin-bottom: 27px;
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

        /* CONTENT CARDS */

        .card {
            background: var(--surface);
            padding: 27px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 23px;
            transition: background 0.25s ease, box-shadow 0.2s ease;
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
            margin: 0 0 20px;
        }

        /* FORM */

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: var(--text);
            margin: 0 0 9px;
            font-weight: bold;
            font-size: 13px;
        }

        input {
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

        input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(49, 141, 229, 0.13);
        }

        input::placeholder {
            color: var(--muted);
            opacity: 0.8;
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
            box-shadow: 0 8px 17px rgba(23, 105, 210, 0.25);
        }

        .submit-button:focus-visible,
        .theme-toggle:focus-visible,
        .nav-links a:focus-visible {
            outline: 3px solid var(--primary-light);
            outline-offset: 3px;
        }

        /* SUCCESS AND ERROR MESSAGES */

        .message {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .success {
            background: var(--message-success-bg);
            color: var(--message-success-text);
        }

        .error {
            background: var(--message-error-bg);
            color: var(--message-error-text);
        }

        /* ALLOWANCE HISTORY */

        .history-list {
            display: grid;
            gap: 13px;
            margin-top: 18px;
        }

        .allowance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 18px;
            background: var(--surface-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: 0.2s ease;
        }

        .allowance-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .allowance-details {
            min-width: 0;
        }

        .amount {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            overflow-wrap: anywhere;
        }

        .allowance-period {
            font-size: 12px;
            color: var(--muted);
            margin-top: 7px;
            line-height: 1.6;
        }

        .history-badge {
            display: inline-block;
            padding: 7px 11px;
            background: var(--surface);
            color: var(--deep-blue);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .empty-state {
            background: var(--surface-secondary);
            color: var(--muted);
            padding: 22px;
            border-radius: 10px;
            text-align: center;
            font-size: 13px;
            line-height: 1.7;
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

            .allowance-item {
                align-items: flex-start;
                flex-direction: column;
            }

        }


/* EXTRA MOBILE RESPONSIVENESS */
@media (max-width: 380px) {
    .nav-links {
        gap: 2px;
    }

    .nav-links a {
        font-size: 10px;
        padding: 8px;
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

            <a href="allowance.php" class="active" aria-current="page">
                Allowance
            </a>

            <a href="expenses.php">Expenses</a>

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

        <a href="dashboard.php" class="breadcrumb">
            ← Back to Dashboard
        </a>

        <!-- PAGE HEADING -->
        <section class="page-heading">

            <h1>Allowance Management</h1>

            <p>
                Set and manage your student allowance. Keep track of your
                available budget and allowance periods.
            </p>

        </section>

        <!-- SET ALLOWANCE -->
        <section class="card">

            <h2>Set Your Allowance</h2>

            <p class="section-subtitle">
                Enter the amount you receive and the dates covered by your allowance.
            </p>

            <?php if ($message !== ""): ?>

                <div
                    class="message <?php echo $messageType; ?>"
                    role="status"
                    aria-live="polite"
                >
                    <?php echo escape($message); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="allowance.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escape($_SESSION["csrf_token"]); ?>"
                >

                <div class="form-group">

                    <label for="allowance_amount">
                        Allowance Amount (₱)
                    </label>

                    <input
                        type="number"
                        id="allowance_amount"
                        name="allowance_amount"
                        min="0.01"
                        max="99999999.99"
                        step="0.01"
                        placeholder="Example: 5000.00"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="start_date">
                        Start Date
                    </label>

                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="end_date">
                        End Date
                    </label>

                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        required
                    >

                </div>

                <button type="submit" class="submit-button">
                    <span aria-hidden="true">＋</span>
                    Save Allowance
                </button>

            </form>

        </section>

        <!-- ALLOWANCE HISTORY -->
        <section class="card">

            <h2>Allowance History</h2>

            <p class="section-subtitle">
                Review the allowance amounts and periods you have previously recorded.
            </p>

            <?php if (count($allowances) > 0): ?>

                <div class="history-list">

                    <?php foreach ($allowances as $index => $item): ?>

                        <article class="allowance-item">

                            <div class="allowance-details">

                                <div class="amount">
                                    ₱<?php echo number_format((float) $item["allowance_amount"], 2); ?>
                                </div>

                                <div class="allowance-period">

                                    <?php echo date("M d, Y", strtotime($item["start_date"])); ?>

                                    <span aria-hidden="true">→</span>

                                    <?php echo date("M d, Y", strtotime($item["end_date"])); ?>

                                </div>

                            </div>

                            <span class="history-badge">
                                Allowance Record #<?php echo count($allowances) - $index; ?>
                            </span>

                        </article>   

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    You haven't added an allowance yet.
                    Use the form above to record your first allowance.
                </div>

            <?php endif; ?>

        </section>

    </main>

    <footer class="footer">
        SpendWise · Student Expense and Allowance Tracker
    </footer>

    <!-- SHARED THEME PREFERENCE -->
    <script>

        const themeToggle = document.getElementById("themeToggle");
        const themeIcon = document.getElementById("themeIcon");
        const themeLabel = document.getElementById("themeLabel");

        // SAME USER-SPECIFIC STORAGE KEY USED BY THE DASHBOARD
        const themeStorageKey = "spendwise_theme_<?php echo $user_id; ?>";

        // APPLY THE SELECTED THEME
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

        // LOAD THE EXISTING THEME PREFERENCE
        const currentTheme =
            document.documentElement.getAttribute("data-theme") || "light";

        applyTheme(currentTheme, false);

        // SWITCH BETWEEN LIGHT MODE AND DARK MODE
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
