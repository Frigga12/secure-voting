
<?php

session_start();

require_once __DIR__ . "/response.php";
require_once __DIR__ . "/../config/database.php";

// 1. Require an authenticated voter
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "voter"
) {
    sendResponse(
        401,
        false,
        "Please log in as a voter before submitting votes."
    );
}

// 2. Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");

    sendResponse(
        405,
        false,
        "Method not allowed. Use POST."
    );
}

// 3. Read the JSON request
$input = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($input)) {
    sendResponse(
        400,
        false,
        "A valid JSON request body is required."
    );
}

$election_id = filter_var(
    $input["election_id"] ?? null,
    FILTER_VALIDATE_INT
);

$selections = $input["selections"] ?? null;

if (
    !$election_id ||
    $election_id < 1 ||
    !is_array($selections) ||
    empty($selections)
) {
    sendResponse(
        400,
        false,
        "Provide a valid election_id and candidate selections."
    );
}

// 4. Validate selection IDs
$normalizedSelections = [];

foreach ($selections as $positionId => $candidateId) {
    $positionId = filter_var(
        $positionId,
        FILTER_VALIDATE_INT
    );

    $candidateId = filter_var(
        $candidateId,
        FILTER_VALIDATE_INT
    );

    if (
        !$positionId ||
        $positionId < 1 ||
        !$candidateId ||
        $candidateId < 1 ||
        isset($normalizedSelections[$positionId])
    ) {
        sendResponse(
            400,
            false,
            "Invalid position or candidate selection."
        );
    }

    $normalizedSelections[$positionId] = $candidateId;
}

$voter_id = (int) $_SESSION["user_id"];

try {

    // 5. Check that the election is active and within its dates
    $stmt = $pdo->prepare("
        SELECT
            id,
            eligibility_type,
            eligibility_department
        FROM elections
        WHERE id = :election_id
          AND status = 'active'
          AND NOW() BETWEEN start_date AND end_date
        LIMIT 1
    ");

    $stmt->execute([
        "election_id" => $election_id
    ]);

    $election = $stmt->fetch();

    if (!$election) {
        sendResponse(
            400,
            false,
            "This election is not currently open for voting."
        );
    }

    // 6. Get the active voter's department
    $voterStmt = $pdo->prepare("
        SELECT department
        FROM users
        WHERE id = :voter_id
          AND role = 'voter'
          AND status = 'active'
        LIMIT 1
    ");

    $voterStmt->execute([
        "voter_id" => $voter_id
    ]);

    $voter = $voterStmt->fetch();

    if (!$voter) {
        sendResponse(
            403,
            false,
            "Your voter account is not active."
        );
    }

    $voter_department = trim($voter["department"] ?? "");
    $is_eligible = false;

    // 7. Apply the same eligibility rules as the website
    if ($election["eligibility_type"] === "all") {

        $is_eligible = true;

    } elseif ($election["eligibility_type"] === "department") {

        $is_eligible = (
            $voter_department !== "" &&
            strcasecmp(
                trim($election["eligibility_department"] ?? ""),
                $voter_department
            ) === 0
        );

    } elseif ($election["eligibility_type"] === "engineering") {

        $engineering_departments = [
            "Computer Engineering",
            "Electrical Engineering",
            "Mechanical Engineering",
            "Civil Engineering",
            "Chemical Engineering",
            "Agricultural Engineering",
            "Electronics Engineering",
            "Mechatronics Engineering"
        ];

        foreach ($engineering_departments as $department) {
            if (
                strcasecmp(
                    trim($department),
                    $voter_department
                ) === 0
            ) {
                $is_eligible = true;
                break;
            }
        }
    }

    if (!$is_eligible) {
        sendResponse(
            403,
            false,
            "You are not eligible to vote in this election."
        );
    }

    // 8. Get all active positions
    $stmt = $pdo->query("
        SELECT id
        FROM positions
        WHERE status = 'active'
        ORDER BY display_order ASC
    ");

    $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredPositionIds = array_map(
        "intval",
        $positions
    );

    if (empty($requiredPositionIds)) {
        sendResponse(
            400,
            false,
            "No active voting positions are available."
        );
    }

    // 9. Require a selection for every active position
    $selectedPositionIds = array_map(
        "intval",
        array_keys($normalizedSelections)
    );

    sort($requiredPositionIds);
    sort($selectedPositionIds);

    if ($requiredPositionIds !== $selectedPositionIds) {
        sendResponse(
            400,
            false,
            "Select exactly one candidate for every active position."
        );
    }

    // 10. Verify each candidate belongs to the election and position
    $checkCandidate = $pdo->prepare("
        SELECT id
        FROM candidates
        WHERE id = :candidate_id
          AND election_id = :election_id
          AND position_id = :position_id
          AND status = 'active'
        LIMIT 1
    ");

    foreach ($normalizedSelections as $positionId => $candidateId) {
        $checkCandidate->execute([
            "candidate_id" => $candidateId,
            "election_id" => $election_id,
            "position_id" => $positionId
        ]);

        if (!$checkCandidate->fetch()) {
            sendResponse(
                400,
                false,
                "A selected candidate is invalid for this election or position."
            );
        }
    }

    // 11. Start transaction
    $pdo->beginTransaction();

    // Lock the voter row to help prevent simultaneous submissions
    $lockVoter = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :voter_id
          AND role = 'voter'
          AND status = 'active'
        FOR UPDATE
    ");

    $lockVoter->execute([
        "voter_id" => $voter_id
    ]);

    if (!$lockVoter->fetch()) {
        $pdo->rollBack();

        sendResponse(
            403,
            false,
            "Your voter account is not active."
        );
    }

    // 12. Prevent repeat voting in the same election
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM votes
        WHERE election_id = :election_id
          AND voter_id = :voter_id
    ");

    $stmt->execute([
        "election_id" => $election_id,
        "voter_id" => $voter_id
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->rollBack();

        sendResponse(
            409,
            false,
            "You have already voted in this election."
        );
    }

    // 13. Prepare vote insertion
    $insertVote = $pdo->prepare("
        INSERT INTO votes
        (
            election_id,
            voter_id,
            position_id,
            candidate_id
        )
        VALUES
        (
            :election_id,
            :voter_id,
            :position_id,
            :candidate_id
        )
    ");

    // 14. Insert all selected votes
    foreach ($normalizedSelections as $positionId => $candidateId) {
        $insertVote->execute([
            "election_id" => $election_id,
            "voter_id" => $voter_id,
            "position_id" => $positionId,
            "candidate_id" => $candidateId
        ]);
    }

    // 15. Commit all votes together
    $pdo->commit();

    sendResponse(
        201,
        true,
        "Votes submitted successfully.",
        [
            "election_id" => (int) $election_id,
            "positions_count" => count($normalizedSelections)
        ]
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Voting API error: " . $e->getMessage());

    if ($e->getCode() === "23000") {
        sendResponse(
            409,
            false,
            "A duplicate voting record was detected."
        );
    }

    sendResponse(
        500,
        false,
        "Unable to submit votes. Please try again."
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Voting API error: " . $e->getMessage());

    sendResponse(
        500,
        false,
        "An unexpected error occurred while submitting votes."
    );
}