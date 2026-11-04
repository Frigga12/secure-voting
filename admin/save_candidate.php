<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: candidates.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get form data
|--------------------------------------------------------------------------
*/

$election_id = (int)($_POST["election_id"] ?? 0);
$full_name = trim($_POST["full_name"] ?? "");
$student_id = trim($_POST["student_id"] ?? "");
$department = trim($_POST["department"] ?? "");
$position_id = (int)($_POST["position_id"] ?? 0);
$manifesto = trim($_POST["manifesto"] ?? "");


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (
    $election_id <= 0 ||
    $full_name === "" ||
    $student_id === "" ||
    $department === "" ||
    $position_id <= 0
) {
    die("Please fill in all required fields.");
}


/*
|--------------------------------------------------------------------------
| Check election
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, election_name, status
    FROM elections
    WHERE id = :election_id
    AND status IN ('draft', 'active')
    LIMIT 1
");

$stmt->execute([
    "election_id" => $election_id
]);

$election = $stmt->fetch();

if (!$election) {
    die("The selected election is invalid.");
}


/*
|--------------------------------------------------------------------------
| Check position
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, position_name
    FROM positions
    WHERE id = :position_id
    AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    "position_id" => $position_id
]);

$position = $stmt->fetch();

if (!$position) {
    die("The selected position is invalid or inactive.");
}


/*
|--------------------------------------------------------------------------
| Check duplicate candidate
|--------------------------------------------------------------------------
|
| A student can contest the same position in different elections.
| But the same student cannot be registered twice for the same
| position in the same election.
|
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM candidates
    WHERE election_id = :election_id
    AND student_id = :student_id
    AND position_id = :position_id
    LIMIT 1
");

$stmt->execute([
    "election_id" => $election_id,
    "student_id" => $student_id,
    "position_id" => $position_id
]);

$existingCandidate = $stmt->fetch();

if ($existingCandidate) {
    die("This student is already registered as a candidate for this position in this election.");
}


/*
|--------------------------------------------------------------------------
| Handle candidate photo
|--------------------------------------------------------------------------
*/

$photoName = null;

if (
    isset($_FILES["photo"]) &&
    $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
        die("There was a problem uploading the candidate photo.");
    }

    $allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    $fileType = mime_content_type(
        $_FILES["photo"]["tmp_name"]
    );

    if (!in_array($fileType, $allowedTypes, true)) {
        die("Invalid photo type. Please upload a JPG, PNG, or WebP image.");
    }

    if ($_FILES["photo"]["size"] > 5 * 1024 * 1024) {
        die("Candidate photo must not be larger than 5MB.");
    }

    $uploadDirectory =
        "C:/laragon/www/secure-voting/uploads/candidates/";

    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    $extension = strtolower(
        pathinfo(
            $_FILES["photo"]["name"],
            PATHINFO_EXTENSION
        )
    );

    $photoName =
        uniqid("candidate_", true) . "." . $extension;

    $photoPath =
        $uploadDirectory . $photoName;

    if (
        !move_uploaded_file(
            $_FILES["photo"]["tmp_name"],
            $photoPath
        )
    ) {
        die("Unable to save the candidate photo.");
    }
}


/*
|--------------------------------------------------------------------------
| Save candidate
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO candidates
    (
        election_id,
        position_id,
        full_name,
        department,
        student_id,
        photo,
        manifesto,
        status
    )
    VALUES
    (
        :election_id,
        :position_id,
        :full_name,
        :department,
        :student_id,
        :photo,
        :manifesto,
        'active'
    )
");

$stmt->execute([
    "election_id" => $election_id,
    "position_id" => $position_id,
    "full_name" => $full_name,
    "department" => $department,
    "student_id" => $student_id,
    "photo" => $photoName,
    "manifesto" => $manifesto
]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Candidate Registered | Secure Voting</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #1f2937;
}

.container {
    max-width: 600px;
    margin: 100px auto;
    padding: 20px;
}

.success-card {
    background: white;
    padding: 40px;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    text-align: center;
}

.success-icon {
    font-size: 50px;
    margin-bottom: 20px;
}

h1 {
    margin-bottom: 12px;
}

p {
    color: #6b7280;
    margin-bottom: 25px;
    line-height: 1.6;
}

.button {
    display: inline-block;
    text-decoration: none;
    background: #1d3557;
    color: white;
    padding: 12px 20px;
    border-radius: 7px;
    margin: 5px;
}

.button.secondary {
    background: #457b9d;
}

</style>

</head>

<body>

<div class="container">

    <div class="success-card">

        <div class="success-icon">
            ✓
        </div>

        <h1>
            Candidate Registered Successfully
        </h1>

        <p>

            <?= htmlspecialchars($full_name) ?>

            has been registered for

            <strong>
                <?= htmlspecialchars($position["position_name"]) ?>
            </strong>

            in

            <strong>
                <?= htmlspecialchars($election["election_name"]) ?>
            </strong>.

        </p>

        <a
            href="add_candidate.php"
            class="button"
        >
            Register Another Candidate
        </a>

        <a
            href="candidates.php"
            class="button secondary"
        >
            View Candidates
        </a>

    </div>

</div>

</body>

</html>