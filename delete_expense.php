
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: expenses.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$expense_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if ($expense_id) {
    $sql = "DELETE FROM expenses
            WHERE id = :id AND user_id = :user_id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":id" => $expense_id,
        ":user_id" => $user_id
    ]);
}

header("Location: expenses.php");
exit();
?>
