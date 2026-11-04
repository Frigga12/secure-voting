<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$election_id = (int)($_GET["id"] ?? 0);
$action = $_GET["action"] ?? "";

if ($election_id <= 0) {
    header("Location: elections.php");
    exit;
}

if (!in_array($action, ["activate", "end"])) {
    header("Location: elections.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Activate Election
|--------------------------------------------------------------------------
*/

if ($action === "activate") {

    $stmt = $pdo->prepare("
        SELECT start_date, end_date, status
        FROM elections
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $election_id
    ]);

    $election = $stmt->fetch();

    if (!$election) {
        header("Location: elections.php");
        exit;
    }

    if ($election["status"] !== "draft") {
        die("Only draft elections can be activated.");
    }

    if (strtotime($election["end_date"]) <= time()) {
        die("This election has already passed its end date.");
    }

    $stmt = $pdo->prepare("
        UPDATE elections
        SET status = 'active'
        WHERE id = :id
    ");

    $stmt->execute([
        "id" => $election_id
    ]);
}


/*
|--------------------------------------------------------------------------
| End Election
|--------------------------------------------------------------------------
*/

if ($action === "end") {

    $stmt = $pdo->prepare("
        UPDATE elections
        SET status = 'ended'
        WHERE id = :id
        AND status = 'active'
    ");

    $stmt->execute([
        "id" => $election_id
    ]);
}


header("Location: elections.php");
exit;