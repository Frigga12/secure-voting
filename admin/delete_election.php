
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

function returnToElections(string $status): void
{
    header("Location: elections.php?delete=" . rawurlencode($status));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    returnToElections("invalid");
}

$sessionToken = (string)($_SESSION["csrf_token"] ?? "");
$postedToken = (string)($_POST["csrf_token"] ?? "");

if (
    $sessionToken === "" ||
    $postedToken === "" ||
    !hash_equals($sessionToken, $postedToken)
) {
    returnToElections("invalid");
}

$electionId = filter_var(
    $_POST["election_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$electionId) {
    returnToElections("not_found");
}

try {
    $pdo->beginTransaction();

    // Lock the election record while performing the checks.
    $stmt = $pdo->prepare("
        SELECT id, election_name, status
        FROM elections
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute(["id" => $electionId]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$election) {
        $pdo->rollBack();
        returnToElections("not_found");
    }

    $status = strtolower((string)$election["status"]);

    // Active elections must be ended first.
    if ($status === "active") {
        $pdo->rollBack();
        returnToElections("active");
    }

    // Only draft and ended elections are eligible.
    if (!in_array($status, ["draft", "ended"], true)) {
        $pdo->rollBack();
        returnToElections("failed");
    }

    // Never permanently delete an election that has votes.
    $voteCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM votes
        WHERE election_id = :id
    ");
    $voteCheck->execute(["id" => $electionId]);

    if ((int)$voteCheck->fetchColumn() > 0) {
        $pdo->rollBack();
        returnToElections("has_votes");
    }

    // Remember candidate photos before deleting the candidate records.
    $photoStmt = $pdo->prepare("
        SELECT photo
        FROM candidates
        WHERE election_id = :id
          AND photo IS NOT NULL
          AND photo <> ''
    ");
    $photoStmt->execute(["id" => $electionId]);
    $candidatePhotos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete the associated candidates first.
    $deleteCandidates = $pdo->prepare("
        DELETE FROM candidates
        WHERE election_id = :id
    ");
    $deleteCandidates->execute(["id" => $electionId]);

    // Permanently delete the election record.
    $deleteElection = $pdo->prepare("
        DELETE FROM elections
        WHERE id = :id
    ");
    $deleteElection->execute(["id" => $electionId]);

    if ($deleteElection->rowCount() !== 1) {
        throw new RuntimeException("Election deletion was not completed.");
    }

    $pdo->commit();

    // Remove image files only if no remaining candidate uses them.
    $photoDirectory = __DIR__ . "/../uploads/candidates/";

    $photoInUse = $pdo->prepare("
        SELECT COUNT(*)
        FROM candidates
        WHERE photo = :photo
    ");

    foreach ($candidatePhotos as $photo) {
        $safePhoto = basename((string)$photo);

        if ($safePhoto === "" || $safePhoto === "." || $safePhoto === "..") {
            continue;
        }

        $photoInUse->execute(["photo" => $safePhoto]);

        if (
            (int)$photoInUse->fetchColumn() === 0 &&
            is_file($photoDirectory . $safePhoto)
        ) {
            @unlink($photoDirectory . $safePhoto);
        }
    }

    returnToElections("success");

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Election deletion failed: " . $exception->getMessage());

    returnToElections("failed");
}
