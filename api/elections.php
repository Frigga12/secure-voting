<?php

require_once __DIR__ . "/response.php";
require_once __DIR__ . "/../config/database.php";

// Only allow GET requests
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");

    sendResponse(
        405,
        false,
        "Method not allowed. Use GET."
    );
}

try {
    $stmt = $pdo->query("
        SELECT
            id,
            election_name,
            description,
            start_date,
            end_date,
            status
        FROM elections
        ORDER BY id DESC
    ");

    $elections = $stmt->fetchAll();

    sendResponse(
        200,
        true,
        "Elections retrieved successfully.",
        [
            "count" => count($elections),
            "elections" => $elections
        ]
    );

} catch (PDOException $e) {
    error_log("Elections API error: " . $e->getMessage());

    sendResponse(
        500,
        false,
        "Unable to retrieve elections."
    );
}