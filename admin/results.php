
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

/* Load elections */
$stmt = $pdo->query("
    SELECT id, election_name, description, start_date, end_date, status
    FROM elections
    ORDER BY id DESC
");
$elections = $stmt->fetchAll();

$selected_election = null;
$selected_election_id = 0;
$results = [];
$total_votes = 0;
$candidate_total = 0;

if (!empty($elections)) {
    $requested_id = filter_input(INPUT_GET, "election", FILTER_VALIDATE_INT);

    $selected_election_id = $requested_id
        ? (int)$requested_id
        : (int)$elections[0]["id"];

    $stmt = $pdo->prepare("
        SELECT *
        FROM elections
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(["id" => $selected_election_id]);
    $selected_election = $stmt->fetch();

    if (!$selected_election) {
        $selected_election_id = (int)$elections[0]["id"];
        $stmt->execute(["id" => $selected_election_id]);
        $selected_election = $stmt->fetch();
    }

    /* Load candidates and vote counts for the selected election */
    $stmt = $pdo->prepare("
        SELECT
            p.id AS position_id,
            p.position_name,
            p.display_order,
            c.id AS candidate_id,
            c.full_name,
            c.student_id,
            c.department,
            c.photo,
            COUNT(v.id) AS vote_count
        FROM positions p
        LEFT JOIN candidates c
            ON c.position_id = p.id
            AND c.election_id = :candidate_election_id
            AND c.status = 'active'
        LEFT JOIN votes v
            ON v.candidate_id = c.id
            AND v.position_id = p.id
            AND v.election_id = :vote_election_id
        WHERE p.status = 'active'
        GROUP BY
            p.id,
            p.position_name,
            p.display_order,
            c.id,
            c.full_name,
            c.student_id,
            c.department,
            c.photo
        ORDER BY
            p.display_order ASC,
            vote_count DESC,
            c.full_name ASC
    ");

    $stmt->execute([
        "candidate_election_id" => $selected_election_id,
        "vote_election_id" => $selected_election_id
    ]);

    $result_rows = $stmt->fetchAll();

    /* Group candidates under their positions */
    foreach ($result_rows as $row) {
        $position_id = (int)$row["position_id"];

        if (!isset($results[$position_id])) {
            $results[$position_id] = [
                "position_name" => $row["position_name"],
                "candidates" => [],
                "total_votes" => 0
            ];
        }

        if ($row["candidate_id"] !== null) {
            $vote_count = (int)$row["vote_count"];

            $results[$position_id]["candidates"][] = [
                "id" => (int)$row["candidate_id"],
                "full_name" => $row["full_name"],
                "student_id" => $row["student_id"],
                "department" => $row["department"],
                "photo" => $row["photo"],
                "vote_count" => $vote_count
            ];

            $results[$position_id]["total_votes"] += $vote_count;
            $candidate_total++;
        }
    }

    /* Count submitted vote records for this election */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM votes
        WHERE election_id = :election_id
    ");
    $stmt->execute(["election_id" => $selected_election_id]);
    $total_votes = (int)$stmt->fetchColumn();
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function resultDate($value): string
{
    if (empty($value)) {
        return "Not set";
    }

    $timestamp = strtotime((string)$value);

    return $timestamp
        ? date("d M Y, g:i A", $timestamp)
        : (string)$value;
}

function resultStatus($status): string
{
    $status = strtolower((string)$status);

    if ($status === "active") {
        return "active";
    }

    if ($status === "completed" || $status === "ended") {
        return "completed";
    }

    return "inactive";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results | Secure Voting</title>

    <style>
        :root {
            --navy: #142b4a;
            --navy-light: #203e64;
            --blue: #2563eb;
            --teal: #0d9488;
            --background: #edf3fb;
            --white: #ffffff;
            --text: #172b4d;
            --muted: #718096;
            --border: #e0e8f2;
            --green: #15803d;
            --green-bg: #dcfce7;
            --shadow: 0 8px 26px rgba(20, 43, 74, .06);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        a {
            color: inherit;
        }

        .topbar {
            background: var(--navy);
            color: white;
            padding: 0 5%;
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 0;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255,255,255,.12);
            display: grid;
            place-items: center;
            color: #7de4d5;
            font-size: 23px;
            font-weight: 800;
        }

        .brand-name {
            font-size: 17px;
            font-weight: 750;
            letter-spacing: .2px;
        }

        .brand-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #c3d2e7;
        }

        .top-links {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .top-links a {
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            padding: 10px 13px;
            border-radius: 9px;
            color: #e4edf9;
        }

        .top-links a:hover {
            background: rgba(255,255,255,.1);
        }

        .top-links .current {
            background: white;
            color: var(--navy);
        }

        .page {
            width: min(1240px, 100%);
            margin: auto;
            padding: 34px 24px 55px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .eyebrow {
            color: var(--teal);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 9px;
        }

        h1 {
            margin: 0;
            font-size: clamp(26px, 3vw, 34px);
            letter-spacing: -.8px;
            color: var(--navy);
        }

        .subtitle {
            color: var(--muted);
            line-height: 1.65;
            margin: 9px 0 0;
            font-size: 14px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--navy);
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            white-space: nowrap;
        }

        .button:hover {
            background: var(--navy-light);
        }

        .button-outline {
            background: white;
            color: var(--navy);
            border: 1px solid var(--border);
        }

        .button-outline:hover {
            background: #f5f8fc;
        }

        .panel,
        .stat,
        .position-card,
        .empty {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .panel {
            padding: 24px;
            margin-bottom: 23px;
        }

        .panel-heading {
            margin: 0 0 7px;
            font-size: 17px;
            color: var(--navy);
        }

        .panel-description {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: end;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 750;
        }

        select {
            width: 100%;
            min-height: 47px;
            padding: 12px 14px;
            border: 1px solid #cfdaea;
            border-radius: 10px;
            background: #fafdff;
            color: var(--text);
            font-size: 14px;
            outline: none;
        }

        select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        .election-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 19px;
            padding-top: 17px;
            border-top: 1px solid var(--border);
        }

        .info-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 11px;
            border-radius: 8px;
            background: #f0f5fc;
            color: #526681;
            font-size: 12px;
        }

        .info-chip strong {
            color: var(--navy);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status.active {
            color: #087f5b;
            background: #d9f8eb;
        }

        .status.completed {
            color: #475569;
            background: #e8edf4;
        }

        .status.inactive {
            color: #a16207;
            background: #fef3c7;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat {
            position: relative;
            padding: 22px;
            overflow: hidden;
        }

        .stat::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: var(--blue);
        }

        .stat:nth-child(2)::after {
            background: var(--teal);
        }

        .stat:nth-child(3)::after {
            background: #8b5cf6;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 650;
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            border-radius: 11px;
            background: #e8f0ff;
            color: var(--blue);
            font-size: 19px;
            font-weight: 800;
        }

        .stat:nth-child(2) .stat-icon {
            background: #d9f8f1;
            color: var(--teal);
        }

        .stat:nth-child(3) .stat-icon {
            background: #f0e9ff;
            color: #7c3aed;
        }

        .stat-value {
            margin-top: 14px;
            font-size: 32px;
            line-height: 1.1;
            font-weight: 800;
            color: var(--navy);
        }

        .stat-note {
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 16px;
            margin: 32px 0 17px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 21px;
            color: var(--navy);
        }

        .section-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .position-card {
            margin-bottom: 21px;
            overflow: hidden;
        }

        .position-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 20px 23px;
            background: linear-gradient(105deg, #e4efff, #e7f8f5);
            border-bottom: 1px solid var(--border);
        }

        .position-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .position-number {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--navy);
            color: white;
            font-size: 14px;
            font-weight: 800;
        }

        .position-header h3 {
            margin: 0;
            font-size: 17px;
            color: var(--navy);
        }

        .position-total {
            margin-top: 5px;
            color: #63758e;
            font-size: 12px;
        }

        .candidate-list {
            padding: 4px 23px 10px;
        }

        .candidate-row {
            display: grid;
            grid-template-columns: 54px minmax(0, 1fr) 82px 90px;
            align-items: center;
            gap: 16px;
            padding: 18px 0;
            border-bottom: 1px solid #edf1f7;
        }

        .candidate-row:last-child {
            border-bottom: none;
        }

        .candidate-photo,
        .photo-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            object-fit: cover;
            background: #e7eef8;
        }

        .photo-placeholder {
            display: grid;
            place-items: center;
            color: #527097;
            font-size: 19px;
            font-weight: 800;
        }

        .candidate-name {
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .candidate-details {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .winner {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: 5px 9px;
            border-radius: 20px;
            background: var(--green-bg);
            color: var(--green);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .vote-number {
            text-align: center;
            color: var(--navy);
            font-size: 20px;
            font-weight: 800;
        }

        .vote-number small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 500;
        }

        .percentage-wrap {
            text-align: right;
        }

        .percentage {
            color: var(--blue);
            font-size: 16px;
            font-weight: 800;
        }

        .progress-track {
            height: 5px;
            margin-top: 8px;
            border-radius: 10px;
            background: #e8eef7;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--blue), var(--teal));
        }

        .no-candidates {
            padding: 30px 18px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .empty {
            padding: 48px 24px;
            text-align: center;
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            margin: 0 auto 17px;
            border-radius: 18px;
            background: #e5efff;
            color: var(--blue);
            font-size: 28px;
            font-weight: 800;
        }

        .empty h2 {
            margin: 0;
            color: var(--navy);
            font-size: 22px;
        }

        .empty p {
            max-width: 450px;
            margin: 10px auto 22px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .footer {
            text-align: center;
            margin-top: 35px;
            padding-top: 22px;
            border-top: 1px solid #dce5f0;
            color: #8391a6;
            font-size: 12px;
        }

        @media (max-width: 760px) {
            .topbar {
                padding: 0 18px 12px;
            }

            .top-links {
                width: 100%;
            }

            .top-links a {
                padding: 9px 10px;
            }

            .page {
                padding: 25px 15px 40px;
            }

            .page-heading {
                flex-direction: column;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid .button {
                width: 100%;
            }

            .stats {
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .stat {
                padding: 19px;
            }

            .position-header {
                align-items: flex-start;
            }

            .candidate-list {
                padding: 4px 16px 8px;
            }

            .candidate-row {
                grid-template-columns: 46px minmax(0, 1fr);
                gap: 11px;
            }

            .candidate-photo,
            .photo-placeholder {
                width: 44px;
                height: 44px;
                border-radius: 12px;
            }

            .vote-number {
                grid-column: 2;
                text-align: left;
                font-size: 16px;
            }

            .vote-number small {
                display: inline;
                margin-left: 4px;
            }

            .percentage-wrap {
                grid-column: 2;
                text-align: left;
                max-width: 180px;
            }

            .position-number {
                width: 36px;
                height: 36px;
            }

            .position-header h3 {
                font-size: 15px;
            }
        }

        @media print {
            .topbar,
            .page-heading .button,
            .filter-grid .button,
            .footer {
                display: none;
            }

            body {
                background: white;
            }

            .page {
                width: 100%;
                padding: 0;
            }

            .panel,
            .stat,
            .position-card {
                box-shadow: none;
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="brand">
        <div class="brand-icon" aria-hidden="true">S</div>
        <div>
            <div class="brand-name">Secure Voting System</div>
            <div class="brand-subtitle">Administration Portal</div>
        </div>
    </div>

    <nav class="top-links" aria-label="Admin navigation">
        <a href="dashboard.php">Dashboard</a>
        <a href="voters.php">Voters</a>
        <a href="candidates.php">Candidates</a>
        <a href="positions.php">Positions</a>
        <a href="elections.php">Elections</a>
        <a href="results.php" class="current">Results</a>
    </nav>
</header>

<main class="page">
    <div class="page-heading">
        <div>
            <div class="eyebrow">Election management</div>
            <h1>Election Results</h1>
            <p class="subtitle">
                Review vote counts, candidate standings, and results by position.
            </p>
        </div>

        <a href="dashboard.php" class="button button-outline">
            &#8592; Dashboard
        </a>
    </div>

    <?php if (empty($elections)): ?>
        <section class="empty">
            <div class="empty-icon" aria-hidden="true">&#9776;</div>
            <h2>No Elections Yet</h2>
            <p>
                Create an election before viewing candidate vote counts and results.
            </p>
            <a href="elections.php" class="button">Manage Elections</a>
        </section>
    <?php else: ?>

        <section class="panel">
            <h2 class="panel-heading">Choose an Election</h2>
            <p class="panel-description">
                Select an election to view its candidate vote counts.
            </p>

            <form method="GET" action="results.php" class="filter-grid">
                <div>
                    <label for="election">Election</label>
                    <select
                        name="election"
                        id="election"
                        onchange="this.form.submit()"
                    >
                        <?php foreach ($elections as $election): ?>
                            <option
                                value="<?= (int)$election["id"] ?>"
                                <?= (int)$election["id"] === $selected_election_id
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= e($election["election_name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="button">
                    View Results
                </button>
            </form>

            <?php if ($selected_election): ?>
                <div class="election-info">
                    <span class="info-chip">
                        <strong>Status:</strong>
                        <span class="status <?= e(resultStatus($selected_election["status"])) ?>">
                            <?= e($selected_election["status"]) ?>
                        </span>
                    </span>

                    <span class="info-chip">
                        <strong>Starts:</strong>
                        <?= e(resultDate($selected_election["start_date"])) ?>
                    </span>

                    <span class="info-chip">
                        <strong>Ends:</strong>
                        <?= e(resultDate($selected_election["end_date"])) ?>
                    </span>
                </div>
            <?php endif; ?>
        </section>

        <section class="stats" aria-label="Election statistics">
            <div class="stat">
                <div class="stat-top">
                    <div class="stat-label">Total Vote Records</div>
                    <div class="stat-icon" aria-hidden="true">&#10003;</div>
                </div>
                <div class="stat-value">
                    <?= number_format($total_votes) ?>
                </div>
                <div class="stat-note">
                    Submitted vote records for this election
                </div>
            </div>

            <div class="stat">
                <div class="stat-top">
                    <div class="stat-label">Positions</div>
                    <div class="stat-icon" aria-hidden="true">&#9776;</div>
                </div>
                <div class="stat-value">
                    <?= number_format(count($results)) ?>
                </div>
                <div class="stat-note">
                    Active positions displayed below
                </div>
            </div>

            <div class="stat">
                <div class="stat-top">
                    <div class="stat-label">Candidates</div>
                    <div class="stat-icon" aria-hidden="true">&#9823;</div>
                </div>
                <div class="stat-value">
                    <?= number_format($candidate_total) ?>
                </div>
                <div class="stat-note">
                    Active candidates in this election
                </div>
            </div>
        </section>

        <div class="section-heading">
            <div>
                <h2>Results by Position</h2>
                <p>
                    Candidate vote counts and percentage of votes for each position.
                </p>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <section class="empty">
                <div class="empty-icon" aria-hidden="true">&#9776;</div>
                <h2>No Results Available</h2>
                <p>No active positions were found for this election.</p>
            </section>
        <?php else: ?>

            <?php $position_number = 0; ?>

            <?php foreach ($results as $position): ?>
                <?php $position_number++; ?>

                <section class="position-card">
                    <div class="position-header">
                        <div class="position-heading">
                            <div class="position-number">
                                <?= str_pad((string)$position_number, 2, "0", STR_PAD_LEFT) ?>
                            </div>

                            <div>
                                <h3><?= e($position["position_name"]) ?></h3>
                                <div class="position-total">
                                    <?= number_format($position["total_votes"]) ?>
                                    vote(s) recorded for this position
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="candidate-list">
                        <?php if (empty($position["candidates"])): ?>
                            <div class="no-candidates">
                                No active candidates have been added for this position
                                in this election.
                            </div>
                        <?php else: ?>

                            <?php
                            $highest_votes = max(array_column(
                                $position["candidates"],
                                "vote_count"
                            ));
                            ?>

                            <?php foreach ($position["candidates"] as $candidate): ?>
                                <?php
                                $vote_count = (int)$candidate["vote_count"];

                                $percentage = $position["total_votes"] > 0
                                    ? ($vote_count / $position["total_votes"]) * 100
                                    : 0;

                                $is_leading = (
                                    $vote_count > 0 &&
                                    $vote_count === $highest_votes
                                );

                                $photo_name = !empty($candidate["photo"])
                                    ? basename($candidate["photo"])
                                    : "";
                                ?>

                                <div class="candidate-row">
                                    <?php if ($photo_name !== ""): ?>
                                        <img
                                            src="../uploads/candidates/<?= rawurlencode($photo_name) ?>"
                                            alt="<?= e($candidate["full_name"]) ?>"
                                            class="candidate-photo"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <div class="photo-placeholder" aria-hidden="true">
                                            <?= e(strtoupper(substr(
                                                (string)$candidate["full_name"],
                                                0,
                                                1
                                            ))) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <div class="candidate-name">
                                            <?= e($candidate["full_name"]) ?>
                                        </div>

                                        <div class="candidate-details">
                                            <?= e($candidate["student_id"]) ?>

                                            <?php if (!empty($candidate["department"])): ?>
                                                &middot;
                                                <?= e($candidate["department"]) ?>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($is_leading): ?>
                                            <span class="winner">
                                                &#10003; Leading
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="vote-number">
                                        <?= number_format($vote_count) ?>
                                        <small>vote(s)</small>
                                    </div>

                                    <div class="percentage-wrap">
                                        <div class="percentage">
                                            <?= number_format($percentage, 1) ?>%
                                        </div>
                                        <div
                                            class="progress-track"
                                            role="progressbar"
                                            aria-label="<?= e($candidate["full_name"]) ?> vote percentage"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-valuenow="<?= number_format($percentage, 1, ".", "") ?>"
                                        >
                                            <div
                                                class="progress-fill"
                                                style="width: <?= max(0, min(100, $percentage)) ?>%;"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

        <?php endif; ?>
    <?php endif; ?>

    <footer class="footer">
        Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
    </footer>
</main>
</body>
</html>
