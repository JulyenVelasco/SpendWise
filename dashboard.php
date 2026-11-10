
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "Student";
$today = date("Y-m-d");

// GET ACTIVE ALLOWANCE
$sql = "SELECT *
        FROM allowances
        WHERE user_id = :user_id
        AND start_date <= :today
        AND end_date >= :today
        ORDER BY start_date DESC
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ":user_id" => $user_id,
    ":today" => $today
]);

$allowance = $stmt->fetch(PDO::FETCH_ASSOC);

// SET ALLOWANCE PERIOD
if ($allowance) {
    $allowanceAmount = (float) $allowance["allowance_amount"];
    $startDate = $allowance["start_date"];
    $endDate = $allowance["end_date"];
} else {
    $allowanceAmount = 0;
    $startDate = date("Y-m-01");
    $endDate = date("Y-m-t");
}

// CALCULATE TOTAL EXPENSES DURING THE PERIOD
$sql = "SELECT COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE user_id = :user_id
        AND expense_date BETWEEN :start_date AND :end_date";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ":user_id" => $user_id,
    ":start_date" => $startDate,
    ":end_date" => $endDate
]);

$totalExpenses = (float) $stmt->fetchColumn();

// CALCULATE REMAINING BALANCE
$remainingBalance = $allowanceAmount - $totalExpenses;

// CALCULATE PERCENTAGE SPENT
$percentageSpent = 0;

if ($allowanceAmount > 0) {
    $percentageSpent = ($totalExpenses / $allowanceAmount) * 100;
}

$progressWidth = min(max($percentageSpent, 0), 100);

// CALCULATE DAYS AND DAILY SPENDING GUIDE
$periodStart = new DateTime($startDate);
$periodEnd = new DateTime($endDate);
$currentDate = new DateTime($today);

$totalPeriodDays = $periodStart->diff($periodEnd)->days + 1;

$elapsedDays = $periodStart->diff($currentDate)->days + 1;
$elapsedDays = max(1, min($elapsedDays, $totalPeriodDays));

$daysRemaining = $currentDate->diff($periodEnd)->days + 1;
$daysRemaining = max(1, $daysRemaining);

$averageDailySpending = $totalExpenses / $elapsedDays;

$dailyBudget = 0;

if ($allowance && $remainingBalance > 0) {
    $dailyBudget = $remainingBalance / $daysRemaining;
}

// DETERMINE BUDGET STATUS
if (!$allowance) {
    $budgetStatus = "No Active Allowance";
    $statusClass = "neutral";
} elseif ($remainingBalance < 0) {
    $budgetStatus = "Budget Exceeded";
    $statusClass = "danger";
} elseif ($percentageSpent >= 80) {
    $budgetStatus = "Approaching Budget Limit";
    $statusClass = "warning";
} else {
    $budgetStatus = "On Track";
    $statusClass = "success";
}

// STUDENT SPENDING ADVICE
if (!$allowance) {
    $studentTip = "Set your allowance to start tracking your student budget.";
} elseif ($remainingBalance < 0) {
    $studentTip = "You have exceeded your allowance. Review your expenses and avoid unnecessary spending.";
} elseif ($percentageSpent >= 80) {
    $studentTip = "You have used most of your allowance. Prioritize essential expenses such as food, transportation, and school needs.";
} elseif ($percentageSpent >= 50) {
    $studentTip = "Keep monitoring your spending. Try to save part of your remaining allowance for unexpected school expenses.";
} else {
    $studentTip = "Great job! Keep managing your allowance wisely and set aside money for future school needs.";
}

// CALCULATE TODAY'S EXPENSES
$sql = "SELECT COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE user_id = :user_id
        AND expense_date = :today";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ":user_id" => $user_id,
    ":today" => $today
]);

$todayExpenses = (float) $stmt->fetchColumn();

// GET EXPENSES BY CATEGORY
$sql = "SELECT category, SUM(amount) AS category_total
        FROM expenses
        WHERE user_id = :user_id
        AND expense_date BETWEEN :start_date AND :end_date
        GROUP BY category
        ORDER BY category_total DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ":user_id" => $user_id,
    ":start_date" => $startDate,
    ":end_date" => $endDate
]);

$categoryExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET RECENT FIVE EXPENSES
$sql = "SELECT category, description, amount, expense_date
        FROM expenses
        WHERE user_id = :user_id
        AND expense_date BETWEEN :start_date AND :end_date
        ORDER BY expense_date DESC, id DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ":user_id" => $user_id,
    ":start_date" => $startDate,
    ":end_date" => $endDate
]);

$recentExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PREPARE COLORS FOR SPENDING CHART
$chartColors = [
    "#1769D2",
    "#2196E8",
    "#18B5D1",
    "#5268D8",
    "#69B9F4",
    "#3157B7",
    "#51C4DB",
    "#879BF0"
];

$chartSegments = [];
$chartPosition = 0;

foreach ($categoryExpenses as $index => $category) {

    $categoryAmount = (float) $category["category_total"];

    if ($totalExpenses > 0 && $categoryAmount > 0) {

        $categoryPercentage = ($categoryAmount / $totalExpenses) * 100;
        $color = $chartColors[$index % count($chartColors)];

        $chartSegments[] = $color . " " .
            $chartPosition . "% " .
            ($chartPosition + $categoryPercentage) . "%";

        $chartPosition += $categoryPercentage;
    }
}

$chartBackground = count($chartSegments) > 0
    ? "conic-gradient(" . implode(", ", $chartSegments) . ")"
    : "#E8F1FC";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | SpendWise</title>

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
            --table-border: #EDF2F9;

            --primary: #1769D2;
            --primary-light: #318DE5;
            --deep-blue: #1555A5;

            --shadow: 0 8px 25px rgba(29, 91, 157, 0.07);
            --shadow-hover: 0 12px 28px rgba(29, 91, 157, 0.13);

            --table-even: #FAFCFF;
            --table-hover: #EDF6FF;

            --donut-center: #FFFFFF;
            --progress-track: #E9F1FC;

            --tip-bg: #F0F7FF;
            --tip-border: #D8EAFA;

            --daily-bg: #EAF4FF;
            --daily-border: #D8EAFE;
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
            --table-border: #29435D;

            --primary: #65B3FF;
            --primary-light: #3F9BEF;
            --deep-blue: #8CC8FF;

            --shadow: 0 8px 25px rgba(0, 0, 0, 0.18);
            --shadow-hover: 0 12px 28px rgba(0, 0, 0, 0.28);

            --table-even: #172F49;
            --table-hover: #203E5D;

            --donut-center: #122A43;
            --progress-track: #263F5A;

            --tip-bg: #193650;
            --tip-border: #315A7A;

            --daily-bg: #183A59;
            --daily-border: #315A7A;
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

        /* NAVIGATION */

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

        /* DARK MODE TOGGLE */

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
            background: var(--daily-bg);
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

        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 27px;
        }

        .welcome h1 {
            color: var(--heading);
            font-size: 29px;
            letter-spacing: -0.8px;
            margin: 0 0 10px;
        }

        .welcome p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .period {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 15px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--primary);
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        /* CARDS */

        .card {
            background: var(--surface);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: background 0.25s ease, box-shadow 0.2s ease;
            min-width: 0;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
        }

        h2 {
            color: var(--heading);
            font-size: 19px;
            margin: 0 0 9px;
        }

        h3 {
            color: var(--heading);
        }

        .section-subtitle {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            margin: 0 0 18px;
        }

        /* SUMMARY CARDS */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 19px;
            margin-bottom: 25px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            padding: 22px;
            min-height: 145px;
            border-radius: 15px;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--stat-color);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            width: 115px;
            height: 115px;
            border-radius: 50%;
            background: var(--stat-bg);
            right: -42px;
            bottom: -55px;
            opacity: 0.65;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
            margin-bottom: 15px;
        }

        .stat-title {
            color: var(--muted);
            font-size: 13px;
            font-weight: bold;
        }

        .stat-icon {
            width: 39px;
            height: 39px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 11px;
            background: var(--stat-bg);
            color: var(--stat-color);
            font-size: 19px;
        }

        .stat-value {
            font-size: 25px;
            font-weight: 800;
            color: var(--stat-color);
            overflow-wrap: anywhere;
            position: relative;
            z-index: 1;
        }

        .stat-note {
            color: var(--muted);
            font-size: 11px;
            margin-top: 7px;
            position: relative;
            z-index: 1;
        }

        /* MAIN GRID */

        .content-grid {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 22px;
            margin-bottom: 25px;
        }

        /* BUDGET STATUS */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            margin: 4px 0 19px;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .success {
            background: #D9F5E5;
            color: #218653;
        }

        .warning {
            background: #FFF0CC;
            color: #A66A00;
        }

        .danger {
            background: #FFE0E0;
            color: #C0392B;
        }

        .neutral {
            background: #E8EEF5;
            color: #566575;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--muted);
        }

        .progress-label strong {
            color: var(--primary);
            font-size: 13px;
        }

        .progress-container {
            width: 100%;
            height: 12px;
            background: var(--progress-track);
            border-radius: 20px;
            overflow: hidden;
            margin: 12px 0 17px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #1769D2, #36A5F2, #42C4DC);
            border-radius: 20px;
            transition: width 0.5s ease;
        }

        .spent-description {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .spent-description strong {
            color: var(--primary);
        }

        /* DAILY BUDGET */

        .daily-budget {
            background: var(--daily-bg);
            border: 1px solid var(--daily-border);
            padding: 20px;
            border-radius: 13px;
            margin-top: 20px;
        }

        .daily-budget h3 {
            font-size: 14px;
            margin: 0 0 10px;
        }

        .daily-amount {
            color: var(--primary);
            font-size: 29px;
            font-weight: 800;
            margin: 8px 0;
        }

        .daily-budget p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
            margin: 8px 0;
        }

        .daily-budget strong {
            color: var(--primary);
        }

        /* STUDENT TIP */

        .student-tip {
            background: var(--tip-bg);
            border: 1px solid var(--tip-border);
            border-left: 4px solid #3198E7;
            padding: 16px 18px;
            border-radius: 10px;
            margin-top: 18px;
        }

        .tip-heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: bold;
            color: var(--primary);
        }

        .student-tip p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
            margin: 9px 0 0;
        }

        /* CATEGORY DONUT CHART */

        .category-chart {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 24px 0 28px;
        }

        .donut {
            width: 205px;
            height: 205px;
            border-radius: 50%;
            background: <?php echo htmlspecialchars($chartBackground, ENT_QUOTES, "UTF-8"); ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 22px rgba(29, 91, 157, 0.10);
        }

        .donut-center {
            width: 128px;
            height: 128px;
            background: var(--donut-center);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .donut-center strong {
            font-size: 20px;
            color: var(--heading);
            overflow-wrap: anywhere;
            max-width: 110px;
        }

        .donut-center span {
            font-size: 11px;
            color: var(--muted);
            margin-top: 6px;
        }

        /* CATEGORY BREAKDOWN */

        .category-item {
            margin: 19px 0;
        }

        .category-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 9px;
            font-size: 12px;
        }

        .category-name {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
            overflow-wrap: anywhere;
        }

        .category-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .category-info > span {
            font-weight: bold;
            color: var(--primary);
            white-space: nowrap;
        }

        .category-bar {
            height: 9px;
            background: var(--progress-track);
            border-radius: 20px;
            overflow: hidden;
        }

        .category-fill {
            height: 100%;
            border-radius: 20px;
            transition: width 0.5s ease;
        }

        .category-item small {
            display: block;
            color: var(--muted);
            margin-top: 7px;
            font-size: 10px;
        }

        /* QUICK ACTIONS */

        .quick-actions {
            margin-bottom: 25px;
        }

        .quick-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 17px;
        }

        .quick-links a {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 9px;
            font-weight: bold;
            font-size: 12px;
            transition: 0.2s ease;
        }

        .quick-links a:hover {
            background: linear-gradient(135deg, #124E9B, #1769D2);
            transform: translateY(-3px);
        }

        /* RECENT EXPENSES */

        .table-container {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 580px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--table-border);
            font-size: 12px;
        }

        th {
            background: var(--surface-secondary);
            color: var(--primary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: var(--muted);
        }

        tbody tr:nth-child(even) {
            background: var(--table-even);
        }

        tbody tr:hover {
            background: var(--table-hover);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        td:last-child {
            color: var(--primary);
            font-weight: bold;
        }

        .view-all {
            display: inline-block;
            margin-top: 17px;
            font-size: 12px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .empty {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            padding: 12px 0;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: var(--muted);
            font-size: 11px;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 950px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
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

            .welcome {
                flex-direction: column;
                align-items: flex-start;
            }

            .welcome h1 {
                font-size: 24px;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 11px;
            }

            .stat-card {
                padding: 16px;
                min-height: 135px;
            }

            .stat-value {
                font-size: 19px;
            }

            .stat-icon {
                width: 33px;
                height: 33px;
                font-size: 16px;
            }

            .stat-title {
                font-size: 11px;
            }

            .card {
                padding: 20px;
                border-radius: 13px;
            }

            .quick-links {
                flex-direction: column;
            }

            .quick-links a {
                width: 100%;
            }

            .donut {
                width: 180px;
                height: 180px;
            }

            .donut-center {
                width: 112px;
                height: 112px;
            }

        }

        @media (max-width: 380px) {

            .stats {
                grid-template-columns: 1fr;
            }

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

            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="allowance.php">Allowance</a>
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

        <!-- WELCOME SECTION -->
        <section class="welcome">

            <div>

                <h1>
                    Welcome, <?php echo htmlspecialchars($full_name, ENT_QUOTES, "UTF-8"); ?>!
                </h1>

                <p>
                    Your student budget at a glance. Stay on track with your allowance
                    and keep your spending under control.
                </p>

            </div>

            <div class="period">

                <span aria-hidden="true">📅</span>

                Allowance Period:

                <?php echo date("M d, Y", strtotime($startDate)); ?>

                -

                <?php echo date("M d, Y", strtotime($endDate)); ?>

            </div>

        </section>

        <!-- SUMMARY CARDS -->
        <section class="stats" aria-label="Budget summary">

            <article class="stat-card" style="--stat-color: #1769D2; --stat-bg: #E6F2FF;">

                <div class="stat-top">
                    <div class="stat-title">Total Allowance</div>
                    <div class="stat-icon" aria-hidden="true">💰</div>
                </div>

                <div class="stat-value">
                    ₱<?php echo number_format($allowanceAmount, 2); ?>
                </div>

                <div class="stat-note">Your available budget</div>

            </article>

            <article class="stat-card" style="--stat-color: #2589C9; --stat-bg: #E3F6FC;">

                <div class="stat-top">
                    <div class="stat-title">Total Expenses</div>
                    <div class="stat-icon" aria-hidden="true">🧾</div>
                </div>

                <div class="stat-value">
                    ₱<?php echo number_format($totalExpenses, 2); ?>
                </div>

                <div class="stat-note">Your recorded spending</div>

            </article>

            <article class="stat-card" style="--stat-color: <?php echo $remainingBalance < 0 ? '#C43D53' : '#1686B3'; ?>; --stat-bg: <?php echo $remainingBalance < 0 ? '#FFE7EA' : '#E3F6FC'; ?>;">

                <div class="stat-top">
                    <div class="stat-title">Remaining Balance</div>
                    <div class="stat-icon" aria-hidden="true">💳</div>
                </div>

                <div class="stat-value">
                    ₱<?php echo number_format($remainingBalance, 2); ?>
                </div>

                <div class="stat-note">Money left from your allowance</div>

            </article>

            <article class="stat-card" style="--stat-color: #5268D8; --stat-bg: #ECEEFF;">

                <div class="stat-top">
                    <div class="stat-title">Today's Expenses</div>
                    <div class="stat-icon" aria-hidden="true">📊</div>
                </div>

                <div class="stat-value">
                    ₱<?php echo number_format($todayExpenses, 2); ?>
                </div>

                <div class="stat-note">Your spending today</div>

            </article>

        </section>

        <!-- BUDGET OVERVIEW AND SPENDING CHART -->
        <section class="content-grid">

            <!-- STUDENT BUDGET -->
            <article class="card">

                <h2>My Student Budget</h2>

                <p class="section-subtitle">
                    Monitor your allowance and spending progress.
                </p>

                <span class="status <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($budgetStatus, ENT_QUOTES, "UTF-8"); ?>
                </span>

                <?php if ($allowance): ?>

                    <div class="progress-label">
                        <span>Allowance Used</span>

                        <strong>
                            <?php echo number_format($percentageSpent, 1); ?>%
                        </strong>
                    </div>

                    <div
                        class="progress-container"
                        role="progressbar"
                        aria-label="Allowance used"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="<?php echo (int) min($progressWidth, 100); ?>"
                    >
                        <div
                            class="progress-bar"
                            style="width: <?php echo $progressWidth; ?>%;"
                        ></div>
                    </div>

                    <p class="spent-description">

                        You have spent

                        <strong>₱<?php echo number_format($totalExpenses, 2); ?></strong>

                        out of your

                        <strong>₱<?php echo number_format($allowanceAmount, 2); ?></strong>

                        allowance.

                    </p>

                    <!-- DAILY BUDGET -->
                    <div class="daily-budget">

                        <h3>✦ Suggested Daily Spending Budget</h3>

                        <?php if ($remainingBalance > 0): ?>

                            <div class="daily-amount">
                                ₱<?php echo number_format($dailyBudget, 2); ?>
                            </div>

                            <p>
                                This is your suggested daily spending limit
                                for the remaining <?php echo $daysRemaining; ?>
                                day(s) of your allowance period.
                            </p>

                        <?php else: ?>

                            <div class="daily-amount" style="color: #E66A79;">
                                ₱0.00
                            </div>

                            <p>
                                Your allowance has been used up.
                                Avoid additional unnecessary expenses.
                            </p>

                        <?php endif; ?>

                        <p>
                            <strong>Days remaining:</strong>
                            <?php echo $daysRemaining; ?> day(s)
                        </p>

                        <p>
                            <strong>Average daily spending:</strong>
                            ₱<?php echo number_format($averageDailySpending, 2); ?>
                        </p>

                    </div>

                <?php else: ?>

                    <p class="empty">
                        You do not have an active allowance.
                        Set your allowance to start monitoring your student budget.
                    </p>

                    <div class="quick-links">
                        <a href="allowance.php">+ Set Allowance</a>
                    </div>

                <?php endif; ?>

                <!-- STUDENT BUDGET TIP -->
                <div class="student-tip">

                    <div class="tip-heading">
                        <span aria-hidden="true">💡</span>
                        Student Budget Tip
                    </div>

                    <p>
                        <?php echo htmlspecialchars($studentTip, ENT_QUOTES, "UTF-8"); ?>
                    </p>

                </div>

            </article>

            <!-- EXPENSES BY CATEGORY -->
            <article class="card">

                <h2>Where Does Your Money Go?</h2>

                <p class="section-subtitle">
                    See how your allowance is distributed across your expenses.
                </p>

                <?php if (count($categoryExpenses) > 0 && $totalExpenses > 0): ?>

                    <div
                        class="category-chart"
                        role="img"
                        aria-label="Donut chart showing expense totals by category"
                    >

                        <div class="donut">

                            <div class="donut-center">

                                <strong>
                                    ₱<?php echo number_format($totalExpenses, 0); ?>
                                </strong>

                                <span>Total Spent</span>

                            </div>

                        </div>

                    </div>

                    <!-- CATEGORY BREAKDOWN -->
                    <?php foreach ($categoryExpenses as $index => $category): ?>

                        <?php
                            $categoryAmount = (float) $category["category_total"];
                            $categoryPercentage = ($categoryAmount / $totalExpenses) * 100;
                            $color = $chartColors[$index % count($chartColors)];
                        ?>

                        <div class="category-item">

                            <div class="category-info">

                                <strong class="category-name">

                                    <span
                                        class="category-dot"
                                        style="background: <?php echo $color; ?>;"
                                        aria-hidden="true"
                                    ></span>

                                    <?php echo htmlspecialchars($category["category"], ENT_QUOTES, "UTF-8"); ?>

                                </strong>

                                <span>
                                    ₱<?php echo number_format($categoryAmount, 2); ?>
                                </span>

                            </div>

                            <div class="category-bar">

                                <div
                                    class="category-fill"
                                    style="
                                        width: <?php echo min($categoryPercentage, 100); ?>%;
                                        background: <?php echo $color; ?>;
                                    "
                                ></div>

                            </div>

                            <small>
                                <?php echo number_format($categoryPercentage, 1); ?>%
                                of total expenses
                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty">
                        No expenses recorded for this period yet.
                        Add your first expense to see your spending breakdown.
                    </p>

                <?php endif; ?>

            </article>

        </section>

        <!-- QUICK ACTIONS -->
        <section class="card quick-actions">

            <h2>Quick Actions</h2>

            <p class="section-subtitle">
                Manage your student finances easily.
            </p>

            <div class="quick-links">

                <a href="expenses.php">
                    <span aria-hidden="true">＋</span>
                    Add Expense
                </a>

                <a href="allowance.php">
                    <span aria-hidden="true">💰</span>
                    Manage Allowance
                </a>

                <a href="categories.php">
                    <span aria-hidden="true">📁</span>
                    Manage Categories
                </a>

            </div>

        </section>

        <!-- RECENT EXPENSES -->
        <section class="card">

            <h2>Recent Expenses</h2>

            <p class="section-subtitle">
                Keep track of your latest purchases and payments.
            </p>

            <?php if (count($recentExpenses) > 0): ?>

                <div class="table-container">

                    <table>

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($recentExpenses as $expense): ?>

                                <tr>

                                    <td>
                                        <?php echo date("M d, Y", strtotime($expense["expense_date"])); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($expense["category"], ENT_QUOTES, "UTF-8"); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($expense["description"], ENT_QUOTES, "UTF-8"); ?>
                                    </td>

                                    <td>
                                        ₱<?php echo number_format((float) $expense["amount"], 2); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <a href="expenses.php" class="view-all">
                    View All Expenses →
                </a>

            <?php else: ?>

                <p class="empty">
                    No expenses recorded for this period yet.
                </p>

            <?php endif; ?>

        </section>

    </main>

    <footer class="footer">
        SpendWise · Student Expense and Allowance Tracker
    </footer>

    <!-- DARK MODE AND WEB STORAGE -->
    <script>

        const themeToggle = document.getElementById("themeToggle");
        const themeIcon = document.getElementById("themeIcon");
        const themeLabel = document.getElementById("themeLabel");

        const themeStorageKey = "spendwise_theme_<?php echo $user_id; ?>";

        // UPDATE THEME AND BUTTON
        function applyTheme(theme, savePreference = true) {

            const isDark = theme === "dark";

            document.documentElement.setAttribute(
                "data-theme",
                isDark ? "dark" : "light"
            );

            themeIcon.textContent = isDark ? "☀" : "☾";
            themeLabel.textContent = isDark ? "Light Mode" : "Dark Mode";

            themeToggle.setAttribute(
                "aria-pressed",
                String(isDark)
            );

            if (savePreference) {

                try {
                    localStorage.setItem(themeStorageKey, theme);
                } catch (error) {
                    console.warn("Unable to save theme preference.", error);
                }

            }

        }

        // SHOW THE CURRENTLY SAVED THEME
        const currentTheme =
            document.documentElement.getAttribute("data-theme") || "light";

        applyTheme(currentTheme, false);

        // SWITCH BETWEEN LIGHT MODE AND DARK MODE
        themeToggle.addEventListener("click", () => {

            const activeTheme =
                document.documentElement.getAttribute("data-theme");

            const newTheme =
                activeTheme === "dark" ? "light" : "dark";

            applyTheme(newTheme);

        });

    </script>

</body>
</html>
