<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$voter_id = (int)($_GET["id"] ?? 0);

if ($voter_id <= 0) {
    header("Location: voters.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, full_name, student_id
    FROM users
    WHERE id = :id
    AND role = 'voter'
    LIMIT 1
");

$stmt->execute([
    "id" => $voter_id
]);

$voter = $stmt->fetch();

if (!$voter) {
    die("Voter not found.");
}

/*
|--------------------------------------------------------------------------
| Generate temporary password
|--------------------------------------------------------------------------
*/

$temporary_password = "Vote@" . random_int(1000, 9999);

/*
|--------------------------------------------------------------------------
| Hash password before saving
|--------------------------------------------------------------------------
*/

$hashed_password = password_hash(
    $temporary_password,
    PASSWORD_DEFAULT
);

/*
|--------------------------------------------------------------------------
| Update voter password
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE users
    SET password = :password,
        must_change_pass = 1
    WHERE id = :id
    AND role = 'voter'
");

$stmt->execute([
    "password" => $hashed_password,
    "id" => $voter_id
]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Password Reset</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #172033;
        }

        .card {
            width: 90%;
            max-width: 520px;
            background: #ffffff;
            padding: 40px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
            text-align: center;
        }

        .icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        h1 {
            margin-bottom: 10px;
        }

        .info {
            color: #64748b;
            line-height: 1.6;
        }

        .password-box {
            margin: 25px 0;
            padding: 18px;
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            border-radius: 10px;
        }

        .password {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1d4ed8;
            margin-top: 8px;
        }

        .warning {
            font-size: 13px;
            color: #b45309;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            background: #2563eb;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="card">

    <div class="icon">
        🔑
    </div>

    <h1>Password Reset</h1>

    <p class="info">
        A new temporary password has been generated for
        <strong><?= htmlspecialchars($voter["full_name"]) ?></strong>.
    </p>

    <div class="password-box">

        <div>
            Temporary Password
        </div>

        <div class="password">
            <?= htmlspecialchars($temporary_password) ?>
        </div>

    </div>

    <p class="warning">
        Save or copy this password before leaving this page.
        It will not be displayed again.
    </p>

    <a href="voters.php" class="btn">
        Back to Voters
    </a>

</div>

</body>

</html>