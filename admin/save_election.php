<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: add_election.php");
    exit;
}

$election_name = trim($_POST["election_name"] ?? "");
$description   = trim($_POST["description"] ?? "");
$start_date    = $_POST["start_date"] ?? "";
$end_date      = $_POST["end_date"] ?? "";

$eligibility_type = $_POST["eligibility_type"] ?? "all";
$eligibility_department = trim(
    $_POST["eligibility_department"] ?? ""
);


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (
    $election_name === "" ||
    $start_date === "" ||
    $end_date === ""
) {
    die("Election name, start date and end date are required.");
}


/*
|--------------------------------------------------------------------------
| Validate eligibility
|--------------------------------------------------------------------------
*/

$allowed_eligibility_types = [
    "all",
    "department",
    "engineering"
];

if (!in_array($eligibility_type, $allowed_eligibility_types, true)) {
    die("Invalid voter eligibility type.");
}


/*
|--------------------------------------------------------------------------
| Department is required for specific department elections
|--------------------------------------------------------------------------
*/

if (
    $eligibility_type === "department" &&
    $eligibility_department === ""
) {
    die("Please specify the eligible department.");
}


/*
|--------------------------------------------------------------------------
| Clear department when not needed
|--------------------------------------------------------------------------
*/

if ($eligibility_type !== "department") {
    $eligibility_department = null;
}


/*
|--------------------------------------------------------------------------
| Validate dates
|--------------------------------------------------------------------------
*/

$start_timestamp = strtotime($start_date);
$end_timestamp   = strtotime($end_date);

if ($start_timestamp === false || $end_timestamp === false) {
    die("Invalid election date.");
}

if ($end_timestamp <= $start_timestamp) {
    die("The election end date must be after the start date.");
}


/*
|--------------------------------------------------------------------------
| Insert Election
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO elections
    (
        election_name,
        description,
        start_date,
        end_date,
        status,
        eligibility_type,
        eligibility_department
    )
    VALUES
    (
        :election_name,
        :description,
        :start_date,
        :end_date,
        'draft',
        :eligibility_type,
        :eligibility_department
    )
");

$stmt->execute([
    "election_name" => $election_name,
    "description" => $description !== ""
        ? $description
        : null,

    "start_date" => date(
        "Y-m-d H:i:s",
        $start_timestamp
    ),

    "end_date" => date(
        "Y-m-d H:i:s",
        $end_timestamp
    ),

    "eligibility_type" => $eligibility_type,

    "eligibility_department" =>
        $eligibility_department !== ""
        ? $eligibility_department
        : null
]);


header("Location: elections.php");
exit;