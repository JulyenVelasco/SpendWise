
<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

// Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Your session has expired. Please log in again."
    ]);
    exit();
}

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit();
}

$user_id = (int) $_SESSION["user_id"];

// Verify CSRF token
$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["csrf_token"] ?? "";

if (
    $sessionToken === "" ||
    $submittedToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request. Please refresh the page and try again."
    ]);
    exit();
}

$action = $_POST["action"] ?? "";

// ADD CUSTOM CATEGORY
if ($action === "add_category") {

    $categoryName = trim($_POST["category_name"] ?? "");

    if ($categoryName === "") {
        echo json_encode([
            "success" => false,
            "message" => "Please enter a category name."
        ]);
        exit();
    }

    if (strlen($categoryName) > 50) {
        echo json_encode([
            "success" => false,
            "message" => "Category name must not exceed 50 characters."
        ]);
        exit();
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO categories (user_id, category_name)
             VALUES (?, ?)"
        );

        $stmt->execute([$user_id, $categoryName]);

        echo json_encode([
            "success" => true,
            "message" => "New category added successfully!",
            "category" => $categoryName
        ]);
        exit();

    } catch (PDOException $e) {
        if ($e->getCode() === "23000") {
            echo json_encode([
                "success" => false,
                "message" => "This category already exists."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Unable to add category. Please try again."
            ]);
        }
        exit();
    }
}

// ADD NEW EXPENSE
if ($action === "add_expense") {

    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $amount = trim($_POST["amount"] ?? "");
    $expenseDate = trim($_POST["expense_date"] ?? "");

    // Check whether the category belongs to this user
    $stmt = $conn->prepare(
        "SELECT COUNT(*)
         FROM categories
         WHERE user_id = ? AND category_name = ?"
    );

    $stmt->execute([$user_id, $category]);
    $validCategory = $stmt->fetchColumn() > 0;

    if (!$validCategory) {
        echo json_encode([
            "success" => false,
            "message" => "Please select a valid category."
        ]);
        exit();
    }

    if ($description === "") {
        echo json_encode([
            "success" => false,
            "message" => "Please enter an expense description."
        ]);
        exit();
    }

    if (strlen($description) > 255) {
        echo json_encode([
            "success" => false,
            "message" => "Description must not exceed 255 characters."
        ]);
        exit();
    }

    if (
        !is_numeric($amount) ||
        (float) $amount <= 0 ||
        (float) $amount > 99999999.99 ||
        round((float) $amount, 2) != (float) $amount
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Please enter a valid amount with up to two decimal places."
        ]);
        exit();
    }

    // Validate the date
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate) ||
        !checkdate(
            (int) substr($expenseDate, 5, 2),
            (int) substr($expenseDate, 8, 2),
            (int) substr($expenseDate, 0, 4)
        )
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Please select a valid expense date."
        ]);
        exit();
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO expenses
             (user_id, category, description, amount, expense_date)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $user_id,
            $category,
            $description,
            $amount,
            $expenseDate
        ]);

        $expenseId = (int) $conn->lastInsertId();

        echo json_encode([
            "success" => true,
            "message" => "Expense added successfully!",
            "expense" => [
                "id" => $expenseId,
                "category" => $category,
                "description" => $description,
                "amount" => number_format((float) $amount, 2, ".", ""),
                "expense_date" => $expenseDate
            ]
        ]);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Unable to add expense. Please try again."
        ]);
        exit();
    }
}

// Invalid action
http_response_code(400);
echo json_encode([
    "success" => false,
    "message" => "Invalid action."
]);
