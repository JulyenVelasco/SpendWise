
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// GET USER'S CUSTOM CATEGORIES
$stmt = $conn->prepare(
    "SELECT category_name FROM categories
     WHERE user_id = ?
     ORDER BY category_name ASC"
);
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// GET EXPENSE HISTORY
$stmt = $conn->prepare(
    "SELECT id, category, description, amount, expense_date
     FROM expenses
     WHERE user_id = ?
     ORDER BY expense_date DESC, id DESC"
);
$stmt->execute([$user_id]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CALCULATE TOTAL EXPENSES
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(amount), 0)
     FROM expenses
     WHERE user_id = ?"
);
$stmt->execute([$user_id]);
$totalExpenses = (float) $stmt->fetchColumn();

// ESCAPE OUTPUT
function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Expenses | SpendWise</title>

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

            --input-bg: #FFFFFF;
            --table-even: #FAFCFF;
            --table-hover: #EDF6FF;

            --success-bg: #D9F5E5;
            --success-text: #218653;
            --error-bg: #FFE0E0;
            --error-text: #C0392B;

            --delete-bg: #E74C3C;
            --delete-hover: #C0392B;
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

            --input-bg: #183550;
            --table-even: #172F49;
            --table-hover: #203E5D;

            --success-bg: #174B3C;
            --success-text: #A1EAC8;
            --error-bg: #582D3B;
            --error-text: #FFB5C2;

            --delete-bg: #C44758;
            --delete-hover: #A93649;
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

        /* GENERAL CARDS */

        .card {
            background: var(--surface);
            padding: 27px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 23px;
            min-width: 0;
            transition: background 0.25s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
        }

        h2 {
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

        /* TOTAL EXPENSES CARD */

        .total-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            border: none;
            padding: 25px 28px;
        }

        .total-card::after {
            content: "";
            position: absolute;
            width: 170px;
            height: 170px;
            right: -45px;
            top: -85px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
        }

        .total-card h2 {
            color: white;
            font-size: 15px;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .total {
            font-size: 30px;
            font-weight: 800;
            color: white;
            overflow-wrap: anywhere;
            position: relative;
            z-index: 1;
        }

        .total-description {
            color: rgba(255, 255, 255, 0.85);
            font-size: 12px;
            margin-top: 8px;
            position: relative;
            z-index: 1;
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

        input, select {
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

        input:focus, select:focus {
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

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .submit-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
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

        .add-category-button {
            width: 39px;
            height: 39px;
            padding: 0;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 23px;
            cursor: pointer;
            flex-shrink: 0;
            transition: 0.2s ease;
        }

        .add-category-button:hover {
            transform: translateY(-2px);
            background: #124E9B;
        }

        .category-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 9px;
        }

        .category-heading label {
            margin: 0;
        }

        .category-input-box {
            display: none;
            background: var(--surface-secondary);
            border: 1px solid var(--border);
            padding: 18px;
            border-radius: 11px;
            margin: 10px 0 18px;
        }

        .category-input-box.show {
            display: block;
        }

        .category-save-button {
            margin-top: 12px;
            padding: 11px 16px;
            background: var(--deep-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        .category-save-button:hover {
            background: #124E9B;
        }

        .manage-link {
            display: inline-block;
            margin-top: 11px;
            color: var(--primary);
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
        }

        .manage-link:hover {
            text-decoration: underline;
        }

        /* NOTIFICATIONS */

        .message {
            display: none;
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .message.show {
            display: block;
        }

        .success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        /* EXPORT BUTTON */

        .export-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            text-decoration: none;
            border-radius: 9px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 20px;
            transition: 0.2s ease;
        }

        .export-button:hover {
            background: #124E9B;
            transform: translateY(-2px);
        }

        /* SEARCH AND FILTERS */

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }

        .filter-grid label {
            margin-top: 0;
        }

        .clear-button {
            margin-top: 15px;
            padding: 10px 15px;
            background: var(--surface-secondary);
            color: var(--primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .clear-button:hover {
            background: var(--surface-panel);
        }

        .filter-results {
            color: var(--muted);
            font-size: 12px;
            margin-top: 16px;
            margin-bottom: 15px;
        }

        /* EXPENSE TABLE */

        .table-container {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--table-border);
            font-size: 12px;
        }

        th {
            color: var(--primary);
            background: var(--surface-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
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

        .edit-button {
            display: inline-block;
            padding: 8px 11px;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-size: 11px;
            font-weight: bold;
            transition: 0.2s ease;
        }

        .edit-button:hover {
            background: #124E9B;
        }

        .delete-button {
            padding: 8px 11px;
            background: var(--delete-bg);
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .delete-button:hover {
            background: var(--delete-hover);
        }

        .empty-message {
            color: var(--muted);
            text-align: center;
            padding: 22px;
        }

        /* FOCUS ACCESSIBILITY */

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

        @media (max-width: 950px) {

            .navbar {
                padding-left: 25px;
                padding-right: 25px;
            }

            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-grid > div:first-child {
                grid-column: 1 / -1;
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

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid > div:first-child {
                grid-column: auto;
            }

            .export-button {
                width: 100%;
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

        <!-- PAGE HEADING -->
        <section class="page-heading">

            <h1>Expense Management</h1>

            <p>
                Record, organize, and monitor your daily expenses.
            </p>

        </section>

        <div
            id="notification"
            class="message"
            role="status"
            aria-live="polite"
        ></div>

        <!-- TOTAL EXPENSES -->
        <section class="card total-card">

            <h2>Total Recorded Expenses</h2>

            <div class="total" id="totalExpenses">
                ₱<?php echo number_format($totalExpenses, 2); ?>
            </div>

            <p class="total-description">
                Your total recorded expenses across all dates.
            </p>

        </section>

        <!-- ADD EXPENSE -->
        <section class="card">

            <h2>Add New Expense</h2>

            <p class="section-subtitle">
                Enter the details of your purchase or payment below.
            </p>

            <form id="expenseForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escape($_SESSION["csrf_token"]); ?>"
                >

                <input type="hidden" name="action" value="add_expense">

                <!-- CUSTOM CATEGORY -->
                <div class="category-heading">

                    <label for="category">
                        Expense Category
                    </label>

                    <button
                        type="button"
                        class="add-category-button"
                        id="toggleCategoryButton"
                        title="Add a new category"
                        aria-label="Add a new category"
                    >
                        +
                    </button>

                </div>

                <div
                    class="category-input-box"
                    id="categoryInputBox"
                >

                    <label for="category_name">
                        New Category Name
                    </label>

                    <input
                        type="text"
                        id="category_name"
                        placeholder="Example: Laundry, Rent, Electricity"
                        maxlength="50"
                    >

                    <button
                        type="button"
                        class="category-save-button"
                        id="saveCategoryButton"
                    >
                        Save Category
                    </button>

                </div>

                <!-- CATEGORY DROPDOWN -->
                <div class="form-group">

                    <label for="category">Select Category</label>

                    <select id="category" name="category" required>

                        <option value="">Select a category</option>

                        <?php foreach ($categories as $category): ?>

                            <option value="<?php echo escape($category); ?>">
                                <?php echo escape($category); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <a href="categories.php" class="manage-link">
                        Manage or remove your categories →
                    </a>

                </div>

                <!-- DESCRIPTION -->
                <div class="form-group">

                    <label for="description">Description</label>

                    <input
                        type="text"
                        id="description"
                        name="description"
                        placeholder="Example: Lunch at school"
                        maxlength="255"
                        required
                    >

                </div>

                <!-- AMOUNT -->
                <div class="form-group">

                    <label for="amount">Amount (₱)</label>

                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        min="0.01"
                        max="99999999.99"
                        step="0.01"
                        placeholder="Enter expense amount"
                        required
                    >

                </div>

                <!-- DATE -->
                <div class="form-group">

                    <label for="expense_date">Expense Date</label>

                    <input
                        type="date"
                        id="expense_date"
                        name="expense_date"
                        value="<?php echo date('Y-m-d'); ?>"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="submit-button"
                    id="submitExpenseButton"
                >
                    Add Expense
                </button>

            </form>

        </section>

        <!-- EXPENSE HISTORY -->
        <section class="card">

            <h2>Expense History</h2>

            <p class="section-subtitle">
                View, search, filter, and export your recorded expenses.
            </p>

            <!-- EXPORT TO CSV -->
            <a href="export_expenses.php" class="export-button">
                ↓ Export to CSV
            </a>

            <!-- SEARCH AND FILTERS -->
            <div class="filter-grid">

                <div>

                    <label for="searchExpense">
                        Search Expenses
                    </label>

                    <input
                        type="search"
                        id="searchExpense"
                        placeholder="Search description or category..."
                    >

                </div>

                <div>

                    <label for="filterCategory">
                        Filter by Category
                    </label>

                    <select id="filterCategory">

                        <option value="">All Categories</option>

                        <?php foreach ($categories as $category): ?>

                            <option value="<?php echo escape($category); ?>">
                                <?php echo escape($category); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div>

                    <label for="filterDate">
                        Filter by Date
                    </label>

                    <input type="date" id="filterDate">

                </div>

            </div>

            <button
                type="button"
                class="clear-button"
                id="clearFiltersButton"
            >
                Clear Filters
            </button>

            <p class="filter-results" id="filterResults">
                Showing <?php echo count($expenses); ?> expense(s).
            </p>

            <!-- EXPENSE TABLE -->
            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody id="expenseTableBody">

                        <?php if (count($expenses) > 0): ?>

                            <?php foreach ($expenses as $expense): ?>

                                <tr
                                    data-date="<?php echo escape($expense["expense_date"]); ?>"
                                    data-category="<?php echo escape($expense["category"]); ?>"
                                    data-description="<?php echo escape($expense["description"]); ?>"
                                >

                                    <td>
                                        <?php
                                        echo date(
                                            "M d, Y",
                                            strtotime($expense["expense_date"])
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo escape($expense["category"]); ?>
                                    </td>

                                    <td>
                                        <?php echo escape($expense["description"]); ?>
                                    </td>

                                    <td>
                                        ₱<?php echo number_format((float) $expense["amount"], 2); ?>
                                    </td>

                                    <td>

                                        <a
                                            href="edit_expense.php?id=<?php echo (int) $expense["id"]; ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="delete_expense.php"
                                            method="POST"
                                            style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this expense?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $expense["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete-button"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr id="noExpensesRow">
                                <td colspan="5" class="empty-message">
                                    No expenses recorded yet.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <footer class="footer">
        SpendWise · Student Expense and Allowance Tracker
    </footer>

    <script>

        // GET PAGE ELEMENTS
        const expenseForm = document.getElementById("expenseForm");
        const notification = document.getElementById("notification");
        const totalExpensesElement = document.getElementById("totalExpenses");
        const expenseTableBody = document.getElementById("expenseTableBody");

        const categoryInputBox = document.getElementById("categoryInputBox");
        const categoryInput = document.getElementById("category_name");
        const categoryDropdown = document.getElementById("category");

        const toggleCategoryButton = document.getElementById("toggleCategoryButton");
        const saveCategoryButton = document.getElementById("saveCategoryButton");
        const submitExpenseButton = document.getElementById("submitExpenseButton");

        // FILTER ELEMENTS
        const searchExpense = document.getElementById("searchExpense");
        const filterCategory = document.getElementById("filterCategory");
        const filterDate = document.getElementById("filterDate");
        const clearFiltersButton = document.getElementById("clearFiltersButton");
        const filterResults = document.getElementById("filterResults");

        let currentTotal = <?php echo json_encode($totalExpenses); ?>;

        // USER-SPECIFIC FILTER STORAGE KEY
        const filterStorageKey =
            "spendwise_expense_filters_<?php echo $user_id; ?>";

        // SAVE EXPENSE FILTER PREFERENCES
        function saveExpenseFilters() {

            try {

                const filters = {
                    search: searchExpense.value,
                    category: filterCategory.value,
                    date: filterDate.value
                };

                localStorage.setItem(
                    filterStorageKey,
                    JSON.stringify(filters)
                );

            } catch (error) {

                console.warn("Unable to save filter preferences.", error);

            }

        }

        // RESTORE EXPENSE FILTER PREFERENCES
        function restoreExpenseFilters() {

            try {

                const savedFilters = localStorage.getItem(filterStorageKey);

                if (!savedFilters) {
                    return;
                }

                const filters = JSON.parse(savedFilters);

                if (!filters || typeof filters !== "object") {
                    return;
                }

                searchExpense.value =
                    typeof filters.search === "string"
                        ? filters.search
                        : "";

                const categoryExists = Array.from(filterCategory.options).some(
                    option => option.value === filters.category
                );

                filterCategory.value = categoryExists
                    ? filters.category
                    : "";

                filterDate.value =
                    typeof filters.date === "string" &&
                    /^\d{4}-\d{2}-\d{2}$/.test(filters.date)
                        ? filters.date
                        : "";

            } catch (error) {

                console.warn("Unable to restore filter preferences.", error);

            }

        }

        // FORMAT AMOUNTS AS PHILIPPINE PESOS
        function formatCurrency(amount) {

            return "₱" + Number(amount).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

        }

        // SHOW NOTIFICATIONS
        function showNotification(message, type) {

            notification.textContent = message;
            notification.className = "message show " + type;

            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });

        }

        // SEND REQUEST TO PHP
        async function sendRequest(formData) {

            const response = await fetch("expense_actions.php", {
                method: "POST",
                body: formData
            });

            const result = await response.json();

            if (!response.ok && !result.message) {
                throw new Error("Something went wrong. Please try again.");
            }

            return result;

        }

        // TOGGLE CATEGORY FORM
        toggleCategoryButton.addEventListener("click", function() {

            categoryInputBox.classList.toggle("show");

            if (categoryInputBox.classList.contains("show")) {
                categoryInput.focus();
            }

        });

        // ADD CUSTOM CATEGORY WITHOUT RELOADING
        saveCategoryButton.addEventListener("click", async function() {

            const name = categoryInput.value.trim();

            if (name === "") {

                showNotification("Please enter a category name.", "error");
                categoryInput.focus();
                return;

            }

            if (name.length > 50) {

                showNotification("Category name must not exceed 50 characters.", "error");
                return;

            }

            const formData = new FormData();

            formData.append(
                "csrf_token",
                expenseForm.elements["csrf_token"].value
            );

            formData.append("action", "add_category");
            formData.append("category_name", name);

            saveCategoryButton.disabled = true;
            saveCategoryButton.textContent = "Saving...";

            try {

                const result = await sendRequest(formData);

                if (result.success) {

                    // ADD CATEGORY TO EXPENSE FORM
                    const option = document.createElement("option");

                    option.value = result.category;
                    option.textContent = result.category;

                    categoryDropdown.appendChild(option);
                    categoryDropdown.value = result.category;

                    // ADD CATEGORY TO FILTER DROPDOWN
                    const filterOption = document.createElement("option");

                    filterOption.value = result.category;
                    filterOption.textContent = result.category;

                    filterCategory.appendChild(filterOption);

                    categoryInput.value = "";
                    categoryInputBox.classList.remove("show");

                    showNotification(result.message, "success");

                } else {

                    showNotification(result.message, "error");

                }

            } catch (error) {

                showNotification(error.message, "error");

            } finally {

                saveCategoryButton.disabled = false;
                saveCategoryButton.textContent = "Save Category";

            }

        });

        // ADD EXPENSE WITHOUT RELOADING
        expenseForm.addEventListener("submit", async function(event) {

            event.preventDefault();

            if (!expenseForm.reportValidity()) {
                return;
            }

            const formData = new FormData(expenseForm);

            submitExpenseButton.disabled = true;
            submitExpenseButton.textContent = "Adding...";

            try {

                const result = await sendRequest(formData);

                if (result.success) {

                    const expense = result.expense;

                    // UPDATE TOTAL EXPENSES
                    currentTotal += Number(expense.amount);

                    totalExpensesElement.textContent =
                        formatCurrency(currentTotal);

                    // REMOVE EMPTY MESSAGE
                    const noExpensesRow =
                        document.getElementById("noExpensesRow");

                    if (noExpensesRow) {
                        noExpensesRow.remove();
                    }

                    // CREATE NEW TABLE ROW
                    const row = document.createElement("tr");

                    row.dataset.date = expense.expense_date;
                    row.dataset.category = expense.category;
                    row.dataset.description = expense.description;

                    // FORMAT DATE
                    const dateParts = expense.expense_date.split("-");

                    const dateObject = new Date(
                        Number(dateParts[0]),
                        Number(dateParts[1]) - 1,
                        Number(dateParts[2])
                    );

                    const dateCell = document.createElement("td");

                    dateCell.textContent = dateObject.toLocaleDateString(
                        "en-US",
                        {
                            month: "short",
                            day: "2-digit",
                            year: "numeric"
                        }
                    );

                    const categoryCell = document.createElement("td");
                    categoryCell.textContent = expense.category;

                    const descriptionCell = document.createElement("td");
                    descriptionCell.textContent = expense.description;

                    const amountCell = document.createElement("td");

                    amountCell.textContent =
                        formatCurrency(expense.amount);

                    const actionsCell = document.createElement("td");

                    // EDIT BUTTON
                    const editLink = document.createElement("a");

                    editLink.href = "edit_expense.php?id=" +
                        encodeURIComponent(expense.id);

                    editLink.className = "edit-button";
                    editLink.textContent = "Edit";

                    // DELETE FORM
                    const deleteForm = document.createElement("form");

                    deleteForm.action = "delete_expense.php";
                    deleteForm.method = "POST";
                    deleteForm.style.display = "inline";

                    deleteForm.addEventListener("submit", function(deleteEvent) {

                        if (!confirm("Are you sure you want to delete this expense?")) {
                            deleteEvent.preventDefault();
                        }

                    });

                    const idInput = document.createElement("input");

                    idInput.type = "hidden";
                    idInput.name = "id";
                    idInput.value = expense.id;

                    const deleteButton = document.createElement("button");

                    deleteButton.type = "submit";
                    deleteButton.className = "delete-button";
                    deleteButton.textContent = "Delete";

                    deleteForm.appendChild(idInput);
                    deleteForm.appendChild(deleteButton);

                    actionsCell.appendChild(editLink);
                    actionsCell.appendChild(document.createTextNode(" "));
                    actionsCell.appendChild(deleteForm);

                    row.appendChild(dateCell);
                    row.appendChild(categoryCell);
                    row.appendChild(descriptionCell);
                    row.appendChild(amountCell);
                    row.appendChild(actionsCell);

                    // INSERT NEW EXPENSE IN DATE ORDER
                    const existingRows = Array.from(
                        expenseTableBody.querySelectorAll("tr[data-date]")
                    );

                    const nextRow = existingRows.find(function(existingRow) {

                        return existingRow.dataset.date < expense.expense_date;

                    });

                    if (nextRow) {

                        expenseTableBody.insertBefore(row, nextRow);

                    } else {

                        expenseTableBody.appendChild(row);

                    }

                    // RESET FORM
                    expenseForm.reset();

                    const today = new Date();

                    const year = today.getFullYear();
                    const month = String(today.getMonth() + 1).padStart(2, "0");
                    const day = String(today.getDate()).padStart(2, "0");

                    document.getElementById("expense_date").value =
                        year + "-" + month + "-" + day;

                    // APPLY CURRENT FILTERS
                    applyExpenseFilters();

                    showNotification(result.message, "success");

                } else {

                    showNotification(result.message, "error");

                }

            } catch (error) {

                showNotification(error.message, "error");

            } finally {

                submitExpenseButton.disabled = false;
                submitExpenseButton.textContent = "Add Expense";

            }

        });

        // SEARCH AND FILTER EXPENSES
        function applyExpenseFilters() {

            const searchValue = searchExpense.value.trim().toLowerCase();
            const selectedCategory = filterCategory.value.toLowerCase();
            const selectedDate = filterDate.value;

            const rows = expenseTableBody.querySelectorAll("tr[data-date]");

            let visibleCount = 0;

            rows.forEach(function(row) {

                const description = row.dataset.description.toLowerCase();
                const category = row.dataset.category.toLowerCase();
                const date = row.dataset.date;

                const matchesSearch =
                    description.includes(searchValue) ||
                    category.includes(searchValue);

                const matchesCategory =
                    selectedCategory === "" ||
                    category === selectedCategory;

                const matchesDate =
                    selectedDate === "" ||
                    date === selectedDate;

                if (matchesSearch && matchesCategory && matchesDate) {

                    row.style.display = "";
                    visibleCount++;

                } else {

                    row.style.display = "none";

                }

            });

            // SHOW MESSAGE WHEN NO EXPENSE MATCHES
            let noResultsRow = document.getElementById("noResultsRow");

            if (visibleCount === 0 && rows.length > 0) {

                if (!noResultsRow) {

                    noResultsRow = document.createElement("tr");
                    noResultsRow.id = "noResultsRow";

                    const cell = document.createElement("td");

                    cell.colSpan = 5;
                    cell.className = "empty-message";
                    cell.textContent = "No matching expenses found.";

                    noResultsRow.appendChild(cell);
                    expenseTableBody.appendChild(noResultsRow);

                }

                noResultsRow.style.display = "";

            } else if (noResultsRow) {

                noResultsRow.remove();

            }

            filterResults.textContent =
                "Showing " + visibleCount + " of " + rows.length + " expense(s).";

        }

        // RUN FILTERS AND SAVE PREFERENCES
        searchExpense.addEventListener("input", function() {

            applyExpenseFilters();
            saveExpenseFilters();

        });

        filterCategory.addEventListener("change", function() {

            applyExpenseFilters();
            saveExpenseFilters();

        });

        filterDate.addEventListener("change", function() {

            applyExpenseFilters();
            saveExpenseFilters();

        });

        // CLEAR ALL FILTERS
        clearFiltersButton.addEventListener("click", function() {

            searchExpense.value = "";
            filterCategory.value = "";
            filterDate.value = "";

            applyExpenseFilters();
            saveExpenseFilters();

        });

        // RESTORE SAVED FILTER PREFERENCES
        restoreExpenseFilters();

        // INITIALIZE FILTER COUNT
        applyExpenseFilters();

        // UNIVERSAL DARK MODE
        const themeToggle = document.getElementById("themeToggle");
        const themeIcon = document.getElementById("themeIcon");
        const themeLabel = document.getElementById("themeLabel");

        // SAME STORAGE KEY USED BY DASHBOARD AND ALLOWANCE
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

        // LOAD THE CURRENT THEME
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
