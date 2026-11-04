
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

// Get the election ID from the URL
$election_id = filter_input(
    INPUT_GET,
    "election_id",
    FILTER_VALIDATE_INT
);

if (!$election_id || $election_id < 1) {
    sendResponse(
        400,
        false,
        "A valid election_id is required."
    );
}

try {
    // Confirm that the election exists
    $electionStmt = $pdo->prepare("
        SELECT id, election_name
        FROM elections
        WHERE id = :election_id
    ");

    $electionStmt->execute([
        "election_id" => $election_id
    ]);

    $election = $electionStmt->fetch();

    if (!$election) {
        sendResponse(
            404,
            false,
            "Election not found."
        );
    }

    // Retrieve candidates belonging to this election
    $candidateStmt = $pdo->prepare("
        SELECT
            c.id,
            c.full_name,
            c.department,
            c.student_id,
            c.photo,
            c.manifesto,
            c.position_id,
            p.position_name
        FROM candidates c
        INNER JOIN positions p
            ON c.position_id = p.id
        WHERE c.election_id = :election_id
          AND c.status = 'active'
        ORDER BY p.display_order ASC, c.full_name ASC
    ");

    $candidateStmt->execute([
        "election_id" => $election_id
    ]);

    $candidates = $candidateStmt->fetchAll();

    sendResponse(
        200,
        true,
        "Candidates retrieved successfully.",
        [
            "election" => $election,
            "count" => count($candidates),
            "candidates" => $candidates
        ]
    );

} catch (PDOException $e) {
    error_log("Candidates API error: " . $e->getMessage());

    sendResponse(
        500,
        false,
        "Unable to retrieve candidates."
    );
}