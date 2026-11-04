<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Location: candidates.php");
    exit;
}

$candidate_id = (int)($_GET["id"] ?? 0);

if ($candidate_id <= 0) {
    header("Location: candidates.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get candidate information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, full_name, photo
    FROM candidates
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    "id" => $candidate_id
]);

$candidate = $stmt->fetch();

if (!$candidate) {
    header("Location: candidates.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete candidate
|--------------------------------------------------------------------------
*/

$delete = $pdo->prepare("
    DELETE FROM candidates
    WHERE id = :id
");

$delete->execute([
    "id" => $candidate_id
]);

/*
|--------------------------------------------------------------------------
| Delete candidate photo from uploads folder
|--------------------------------------------------------------------------
*/

if (!empty($candidate["photo"])) {

    $photoPath = "C:/laragon/www/secure-voting/uploads/candidates/" . $candidate["photo"];

    if (file_exists($photoPath)) {
        unlink($photoPath);
    }
}

/*
|--------------------------------------------------------------------------
| Return to candidates page
|--------------------------------------------------------------------------
*/

header("Location: candidates.php");
exit;