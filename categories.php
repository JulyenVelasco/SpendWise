
<?php
session_start();
require_once "db.php";

// CHECK IF USER IS LOGGED IN
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

// HANDLE CATEGORY ACTIONS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // VERIFY CSRF TOKEN
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {

        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // ADD CATEGORY
        if ($action === "add") {

            $category_name = trim($_POST["category_name"] ?? "");

            if ($category_name === "") {

                $message = "Please enter a category name.";
                $messageType = "error";

            } elseif (strlen($category_name) > 50) {

                $message = "Category name must not exceed 50 characters.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $conn->prepare(
                        "INSERT INTO categories (user_id, category_name)
                         VALUES (?, ?)"
                    );

                    $stmt->execute([$user_id, $category_name]);

                    $message = "Category added successfully!";
                    $messageType = "success";

                } catch (PDOException $e) {

                    if ($e->getCode() == "23000") {
                        $message = "This category already exists.";
                    } else {
                        error_log($e->getMessage());
                        $message = "Something went wrong while adding the category.";
                    }

                    $messageType = "error";

                }

            }

        }

        // EDIT CATEGORY
        elseif ($action === "edit") {

            $category_id = filter_input(
                INPUT_POST,
                "category_id",
                FILTER_VALIDATE_INT
            );

            $category_name = trim($_POST["category_name"] ?? "");

            if (!$category_id) {

                $message = "Invalid category.";
                $messageType = "error";

            } elseif ($category_name === "") {

                $message = "Please enter a category name.";
                $messageType = "error";

            } elseif (strlen($category_name) > 50) {

                $message = "Category name must not exceed 50 characters.";
                $messageType = "error";

            } else {

                try {

                    $conn->beginTransaction();

                    // CONFIRM CATEGORY OWNERSHIP
                    $check = $conn->prepare(
                        "SELECT category_name
                         FROM categories
                         WHERE id = ? AND user_id = ?"
                    );

                    $check->execute([$category_id, $user_id]);

                    $oldCategory = $check->fetch(PDO::FETCH_ASSOC);

                    if (!$oldCategory) {
                        throw new Exception("Category not found.");
                    }

                    $oldName = $oldCategory["category_name"];

                    // UPDATE CATEGORY NAME
                    $update = $conn->prepare(
                        "UPDATE categories
                         SET category_name = ?
                         WHERE id = ? AND user_id = ?"
                    );

                    $update->execute([
                        $category_name,
                        $category_id,
                        $user_id
                    ]);

                    // UPDATE EXISTING EXPENSES
                    $updateExpenses = $conn->prepare(
                        "UPDATE expenses
                         SET category = ?
                         WHERE user_id = ? AND category = ?"
                    );

                    $updateExpenses->execute([
                        $category_name,
                        $user_id,
                        $oldName
                    ]);

                    $conn->commit();

                    $message = "Category updated successfully!";
                    $messageType = "success";

                } catch (PDOException $e) {

                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }

                    if ($e->getCode() == "23000") {
                        $message = "A category with that name already exists.";
                    } else {
                        error_log($e->getMessage());
                        $message = "Something went wrong while updating the category.";
                    }

                    $messageType = "error";

                } catch (Exception $e) {

                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }

                    $message = $e->getMessage();
                    $messageType = "error";

                }

            }

        }

        // DELETE CATEGORY
        elseif ($action === "delete") {

            $category_id = filter_input(
                INPUT_POST,
                "category_id",
                FILTER_VALIDATE_INT
            );

            if (!$category_id) {

                $message = "Invalid category.";
                $messageType = "error";

            } else {

                try {

                    // CONFIRM CATEGORY OWNERSHIP
                    $check = $conn->prepare(
                        "SELECT category_name
                         FROM categories
                         WHERE id = ? AND user_id = ?"
                    );

                    $check->execute([$category_id, $user_id]);

                    $category = $check->fetch(PDO::FETCH_ASSOC);

                    if (!$category) {

                        $message = "Category not found.";
                        $messageType = "error";

                    } else {

                        // CHECK IF CATEGORY IS BEING USED
                        $expenseCheck = $conn->prepare(
                            "SELECT COUNT(*)
                             FROM expenses
                             WHERE user_id = ? AND category = ?"
                        );

                        $expenseCheck->execute([
                            $user_id,
                            $category["category_name"]
                        ]);

                        $expenseCount = (int) $expenseCheck->fetchColumn();

                        if ($expenseCount > 0) {

                            $message = "You cannot delete this category because it is being used by " .
                                $expenseCount .
                                " expense record(s). Please reassign those expenses first.";

                            $messageType = "error";

                        } else {

                            $delete = $conn->prepare(
                                "DELETE FROM categories
                                 WHERE id = ? AND user_id = ?"
                            );

                            $delete->execute([
                                $category_id,
                                $user_id
                            ]);

                            $message = "Category deleted successfully!";
                            $messageType = "success";

                        }

                    }

                } catch (PDOException $e) {

                    error_log($e->getMessage());

                    $message = "Something went wrong while deleting the category.";
                    $messageType = "error";

                }

            }

        }

    }

}

// GET THE USER'S CATEGORIES
$stmt = $conn->prepare(
    "SELECT id, category_name
     FROM categories
     WHERE user_id = ?
     ORDER BY category_name ASC"
);

$stmt->execute([$user_id]);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <title>Manage Categories | SpendWise</title>

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

            --delete-bg: #FDEAEA;
            --delete-text: #D34C4C;
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

            --delete-bg: #582D3B;
            --delete-text: #FFB5C2;
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

        /* CARDS */

        .card {
            background: var(--surface);
            padding: 27px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 23px;
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
            margin: 0 0 20px;
        }

        /* NOTIFICATIONS */

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

        /* ADD CATEGORY FORM */

        .add-form {
            display: flex;
            align-items: flex-end;
            gap: 12px;
        }

        .form-group {
            flex: 1;
            min-width: 0;
        }

        label {
            display: block;
            color: var(--text);
            margin-bottom: 9px;
            font-weight: bold;
            font-size: 13px;
        }

        input[type="text"] {
            width: 100%;
            min-width: 0;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;
            font-size: 14px;
            font-family: Arial, sans-serif;
            color: var(--text);
            background: var(--input-bg);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="text"]:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(49, 141, 229, 0.13);
        }

        input::placeholder {
            color: var(--muted);
            opacity: 0.8;
        }

        button {
            font-family: Arial, sans-serif;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: 0.2s ease;
        }

        .add-btn,
        .save-btn {
            padding: 13px 18px;
            background: linear-gradient(135deg, #1769D2, #318DE5);
            color: white;
            white-space: nowrap;
            box-shadow: 0 4px 10px rgba(23, 105, 210, 0.12);
        }

        .add-btn:hover,
        .save-btn:hover {
            background: #124E9B;
            transform: translateY(-2px);
        }

        /* CATEGORY LIST */

        .category-list {
            display: grid;
            gap: 13px;
            margin-top: 18px;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 18px;
            background: var(--surface-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: 0.2s ease;
        }

        .category-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .category-details {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .category-icon {
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--surface);
            color: var(--primary);
            font-size: 17px;
        }

        .category-name {
            font-weight: bold;
            color: var(--text);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .category-number {
            color: var(--muted);
            font-size: 10px;
            margin-top: 5px;
        }

        .category-actions {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* EDIT FORM */

        .edit-form {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .edit-form input {
            width: 175px;
        }

        .save-btn {
            padding: 10px 13px;
        }

        /* DELETE BUTTON */

        .delete-btn {
            padding: 10px 13px;
            background: var(--delete-bg);
            color: var(--delete-text);
        }

        .delete-btn:hover {
            background: #C44758;
            color: white;
        }

        /* EMPTY STATE */

        .empty-state {
            background: var(--surface-secondary);
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 22px;
            border-radius: 10px;
            text-align: center;
            font-size: 13px;
            line-height: 1.7;
        }

        /* NOTE */

        .note {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
            padding: 15px;
            border-radius: 9px;
            background: var(--surface-panel);
            border: 1px solid var(--border);
            margin-top: 20px;
        }

        /* ACCESSIBILITY */

        button:focus-visible,
        a:focus-visible,
        input:focus-visible {
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

            .category-item {
                align-items: flex-start;
            }

            .category-actions {
                max-width: 65%;
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

            .add-form {
                flex-direction: column;
                align-items: stretch;
            }

            .category-item {
                flex-direction: column;
                align-items: stretch;
            }

            .category-actions {
                max-width: none;
                justify-content: flex-start;
            }

            .edit-form {
                flex: 1;
                min-width: 0;
            }

            .edit-form input {
                width: 100%;
                min-width: 0;
            }

        }

        @media (max-width: 380px) {

            .nav-links {
                gap: 2px;
            }

            .nav-links a {
                font-size: 10px;
                padding: 8px;
            }

            .category-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .edit-form {
                flex-wrap: wrap;
            }

            .edit-form input {
                width: 100%;
            }

            .edit-form button,
            .category-actions form,
            .delete-btn {
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

            <a href="expenses.php">Expenses</a>

            <a href="categories.php" class="active" aria-current="page">
                Categories
            </a>

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

            <h1>Manage Categories</h1>

            <p>
                Add, edit, or remove categories to organize your student expenses.
            </p>

        </section>

        <!-- SUCCESS OR ERROR MESSAGE -->
        <?php if ($message !== ""): ?>

            <div
                class="message <?php echo escape($messageType); ?>"
                role="status"
                aria-live="polite"
            >
                <?php echo escape($message); ?>
            </div>

        <?php endif; ?>

        <!-- ADD CATEGORY -->
        <section class="card">

            <h2>Add New Category</h2>

            <p class="section-subtitle">
                Create custom categories that match your personal or dorm expenses.
            </p>

            <form method="POST" class="add-form" action="categories.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escape($_SESSION["csrf_token"]); ?>"
                >

                <input type="hidden" name="action" value="add">

                <div class="form-group">

                    <label for="category_name">
                        Category Name
                    </label>

                    <input
                        type="text"
                        id="category_name"
                        name="category_name"
                        placeholder="Example: Rent, Laundry, Electricity"
                        maxlength="50"
                        required
                    >

                </div>

                <button type="submit" class="add-btn">
                    + Add Category
                </button>

            </form>

        </section>

        <!-- CATEGORY LIST -->
        <section class="card">

            <h2>
                Your Categories
                (<?php echo count($categories); ?>)
            </h2>

            <p class="section-subtitle">
                Manage your saved categories. Renaming a category also updates
                the matching expense records.
            </p>

            <?php if (count($categories) > 0): ?>

                <div class="category-list">

                    <?php foreach ($categories as $index => $category): ?>

                        <article class="category-item">

                            <!-- CATEGORY DETAILS -->
                            <div class="category-details">

                                <div class="category-icon" aria-hidden="true">
                                    ◈
                                </div>

                                <div>

                                    <div class="category-name">
                                        <?php echo escape($category["category_name"]); ?>
                                    </div>

                                    <div class="category-number">
                                        Category #<?php echo $index + 1; ?>
                                    </div>

                                </div>

                            </div>

                            <!-- CATEGORY ACTIONS -->
                            <div class="category-actions">

                                <!-- EDIT CATEGORY -->
                                <form
                                    method="POST"
                                    action="categories.php"
                                    class="edit-form"
                                    onsubmit="return confirm('Save this category name?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo escape($_SESSION["csrf_token"]); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="edit"
                                    >

                                    <input
                                        type="hidden"
                                        name="category_id"
                                        value="<?php echo (int) $category["id"]; ?>"
                                    >

                                    <input
                                        type="text"
                                        name="category_name"
                                        value="<?php echo escape($category["category_name"]); ?>"
                                        maxlength="50"
                                        aria-label="Edit category name"
                                        required
                                    >

                                    <button type="submit" class="save-btn">
                                        Save
                                    </button>

                                </form>

                                <!-- DELETE CATEGORY -->
                                <form
                                    method="POST"
                                    action="categories.php"
                                    onsubmit="return confirm('Are you sure you want to delete this category?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo escape($_SESSION["csrf_token"]); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="category_id"
                                        value="<?php echo (int) $category["id"]; ?>"
                                    >

                                    <button type="submit" class="delete-btn">
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    You don't have any categories yet.

                    Add your first category using the form above.

                </div>

            <?php endif; ?>

            <div class="note">

                <strong>Important:</strong>

                Categories with existing expenses cannot be deleted.

                Rename a category to update its name in your existing expense records.

            </div>

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
