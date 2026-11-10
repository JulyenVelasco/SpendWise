
<?php
session_start();
require_once "db.php";

// CHECK IF USER IS LOGGED IN
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

// GET ONLY THE LOGGED-IN USER'S EXPENSES
$stmt = $conn->prepare(
    "SELECT category, description, amount, expense_date
     FROM expenses
     WHERE user_id = ?
     ORDER BY expense_date DESC, id DESC"
);

$stmt->execute([$user_id]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SET DOWNLOAD HEADERS
header("Content-Type: text/csv; charset=UTF-8");
header(
    "Content-Disposition: attachment; filename=SpendWise_Expenses_" .
    date("Y-m-d") . ".csv"
);

// OPEN OUTPUT STREAM
$output = fopen("php://output", "w");

// PROTECT TEXT AGAINST SPREADSHEET FORMULA INJECTION
function safeCSVText($value) {

    $value = (string) $value;

    if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

// ADD COLUMN HEADINGS
fputcsv($output, [
    "Category",
    "Description",
    "Amount (PHP)",
    "Expense Date"
]);

// ADD EXPENSE RECORDS
foreach ($expenses as $expense) {

    fputcsv($output, [
        safeCSVText($expense["category"]),
        safeCSVText($expense["description"]),
        number_format((float) $expense["amount"], 2, ".", ""),
        $expense["expense_date"]
    ]);

}

// CLOSE OUTPUT STREAM
fclose($output);

exit();
?>
