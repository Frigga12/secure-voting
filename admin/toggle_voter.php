<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$voter_id = (int)($_GET["id"] ?? 0);
$action = $_GET["action"] ?? "";

if ($voter_id <= 0) {
    header("Location: voters.php");
    exit;
}

if (!in_array($action, ["activate", "deactivate"])) {
    header("Location: voters.php");
    exit;
}

$newStatus = $action === "activate" ? "active" : "inactive";

$stmt = $pdo->prepare("
    UPDATE users
    SET status = :status
    WHERE id = :id
    AND role = 'voter'
");

$stmt->execute([
    "status" => $newStatus,
    "id" => $voter_id
]);

header("Location: voters.php");
exit;