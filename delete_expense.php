
<?php
session_start();
require_once "db.php";

// CHECK IF USER IS LOGGED IN
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// ONLY ALLOW POST REQUESTS
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: expenses.php");
    exit();
}

// VERIFY CSRF SECURITY TOKEN
$session_token = $_SESSION["csrf_token"] ?? "";
$submitted_token = $_POST["csrf_token"] ?? "";

if (
    !is_string($session_token) ||
    !is_string($submitted_token) ||
    $session_token === "" ||
    !hash_equals($session_token, $submitted_token)
) {
    http_response_code(403);
    echo "Invalid security token. Please return to the Expenses page, refresh, and try again.";
    exit();
}

// GET LOGGED-IN USER AND EXPENSE ID
$user_id = (int) $_SESSION["user_id"];

$expense_id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

// VALIDATE EXPENSE ID
if (!$expense_id || $expense_id <= 0) {
    header("Location: expenses.php");
    exit();
}

// DELETE ONLY THE LOGGED-IN USER'S EXPENSE
$sql = "DELETE FROM expenses
        WHERE id = :id AND user_id = :user_id";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ":id" => $expense_id,
    ":user_id" => $user_id
]);

// RETURN TO EXPENSE HISTORY
header("Location: expenses.php");
exit();
?>
