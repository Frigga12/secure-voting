
<?php

require_once __DIR__ . "/session_guard.php";

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function showVotingMessage(
    string $title,
    string $message,
    string $link = "elections.php"
): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | Secure Voting</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 22px;
                background: #edf3fb;
                color: #182b45;
                font-family: "Segoe UI", Arial, sans-serif;
            }
            .message-card {
                width: min(100%, 540px);
                padding: 32px;
                text-align: center;
                background: white;
                border: 1px solid #dbe5f1;
                border-radius: 16px;
                box-shadow: 0 12px 35px rgba(20,43,74,.08);
            }
            .message-icon {
                width: 54px;
                height: 54px;
                display: grid;
                place-items: center;
                margin: 0 auto 18px;
                border-radius: 14px;
                background: #e5eeff;
                color: #2563eb;
                font-size: 23px;
                font-weight: 800;
            }
            h1 {
                margin: 0 0 12px;
                color: #142b4a;
                font-size: 24px;
            }
            p {
                color: #64748b;
                font-size: 14px;
                line-height: 1.8;
                overflow-wrap: anywhere;
            }
            a {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 18px;
                border-radius: 9px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                font-size: 13px;
                font-weight: 750;
            }
            a:hover { background: #1d4ed8; }
        </style>
    </head>
    <body>
        <main class="message-card">
            <div class="message-icon" aria-hidden="true">!</div>
            <h1><?= e($title) ?></h1>
            <p><?= e($message) ?></p>
            <a href="<?= e($link) ?>">Return to Elections</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

/* Validate the election ID from the URL. */
$election_id = filter_input(INPUT_GET, "election", FILTER_VALIDATE_INT);

if (!$election_id || $election_id <= 0) {
    header("Location: elections.php");
    exit;
}

/* Confirm the election is open during its scheduled period. */
$stmt = $pdo->prepare("
    SELECT *
    FROM elections
    WHERE id = :election_id
      AND status = 'active'
      AND NOW() BETWEEN start_date AND end_date
    LIMIT 1
");

$stmt->execute(["election_id" => $election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$election) {
    showVotingMessage(
        "Election Unavailable",
        "This election is not currently active or is outside its scheduled voting period."
    );
}

$voter_id = (int)$_SESSION["user_id"];

/* Check that the voter account remains active. */
$stmt = $pdo->prepare("
    SELECT department, status
    FROM users
    WHERE id = :id
      AND role = 'voter'
    LIMIT 1
");

$stmt->execute(["id" => $voter_id]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voter) {
    showVotingMessage(
        "Account Not Found",
        "Your voter account could not be found. Please contact the administrator."
    );
}

if (($voter["status"] ?? "") !== "active") {
    showVotingMessage(
        "Account Inactive",
        "Your voter account is not active. Please contact the administrator."
    );
}

/* Check whether this voter is eligible for the election. */
$voter_department = trim((string)($voter["department"] ?? ""));
$is_eligible = false;
$eligibility_type = (string)($election["eligibility_type"] ?? "");

if ($eligibility_type === "all") {
    $is_eligible = true;

} elseif ($eligibility_type === "department") {
    $required_department = trim(
        (string)($election["eligibility_department"] ?? "")
    );

    $is_eligible = (
        $required_department !== "" &&
        $voter_department !== "" &&
        strcasecmp($required_department, $voter_department) === 0
    );

} elseif ($eligibility_type === "engineering") {
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
            $voter_department !== "" &&
            strcasecmp($department, $voter_department) === 0
        ) {
            $is_eligible = true;
            break;
        }
    }
}

if (!$is_eligible) {
    showVotingMessage(
        "Access Denied",
        "You are not eligible to vote in this election. Your registered department is: " .
        ($voter_department !== "" ? $voter_department : "Not specified") . "."
    );
}

/* Prevent starting another voting session after a vote is recorded. */
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

if ((int)$stmt->fetchColumn() > 0) {
    showVotingMessage(
        "You Have Already Voted",
        "Your vote has already been recorded for this election. You cannot start another voting session.",
        "dashboard.php"
    );
}

/* Load active positions in display order. */
$stmt = $pdo->query("
    SELECT id, position_name, display_order
    FROM positions
    WHERE status = 'active'
    ORDER BY display_order ASC
");

$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$positions) {
    showVotingMessage(
        "No Positions Available",
        "No active voting positions have been configured. Please contact the administrator."
    );
}

/* Determine the current position. */
$position_index = max(0, (int)($_GET["position"] ?? 0));

if ($position_index >= count($positions)) {
    $position_index = count($positions) - 1;
}

$current_position = $positions[$position_index];
$position_id = (int)$current_position["id"];

/* Load active candidates for this election and position. */
$stmt = $pdo->prepare("
    SELECT id, full_name, student_id, department, photo, manifesto
    FROM candidates
    WHERE election_id = :election_id
      AND position_id = :position_id
      AND status = 'active'
    ORDER BY full_name ASC
");

$stmt->execute([
    "election_id" => $election_id,
    "position_id" => $position_id
]);

$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_positions = count($positions);
$current_number = $position_index + 1;
$progress = ($current_number / $total_positions) * 100;

/* Restore temporary selections for this election. */
$saved_selections = (
    (int)($_SESSION["vote_election_id"] ?? 0) === $election_id &&
    isset($_SESSION["vote_selections"]) &&
    is_array($_SESSION["vote_selections"])
)
    ? $_SESSION["vote_selections"]
    : [];

$saved_candidate_id = (int)($saved_selections[$position_id] ?? 0);

/*
 * Create a session-bound token for candidate selection submissions.
 * save_vote_selection.php must validate this token before saving.
 */
if (empty($_SESSION["vote_selection_token"])) {
    $_SESSION["vote_selection_token"] = bin2hex(random_bytes(32));
}

$fullName = trim((string)($_SESSION["full_name"] ?? "Voter"));

if ($fullName === "") {
    $fullName = "Voter";
}

$initial = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($current_position["position_name"]) ?> | Secure Voting</title>

    <style>
        :root {
            --navy: #142b4a;
            --navy-light: #1d4775;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --teal: #0d9488;
            --bg: #edf3fb;
            --white: #fff;
            --text: #182b45;
            --muted: #64748b;
            --line: #dce5f0;
            --shadow: 0 8px 25px rgba(20,43,74,.06);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        a, button, input { font: inherit; }
        a { text-decoration: none; }

        .topbar {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 15px 5%;
            background: var(--navy);
            border-bottom: 4px solid var(--teal);
            color: white;
            box-shadow: 0 5px 18px rgba(20,43,74,.13);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
        }

        .brand-name {
            color: white;
            font-size: 16px;
            font-weight: 800;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            color: #c7d7eb;
            font-size: 12px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 13px;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 9px;
            background: #274567;
            color: white;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .header-link:hover { background: #365b85; }

        .header-avatar {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #d9f4f0;
            color: #087e75;
            font-weight: 800;
        }

        .page {
            width: min(1200px, calc(100% - 40px));
            margin: 31px auto 50px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--blue);
            font-weight: 650;
        }

        .breadcrumb strong { color: var(--navy); }

        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 22px;
            padding: 27px 29px;
            margin-bottom: 22px;
            border-radius: 18px;
            background: linear-gradient(115deg,#142b4a 0%,#1d4775 68%,#0d9488 140%);
            box-shadow: 0 12px 30px rgba(20,43,74,.13);
            color: white;
        }

        .welcome-content { min-width: 0; }

        .eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            padding: 5px 10px;
            border: 1px solid rgba(204,251,241,.25);
            border-radius: 30px;
            background: rgba(13,148,136,.25);
            color: #d7f8f4;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .welcome h1 {
            margin: 0 0 9px;
            color: white;
            font-size: clamp(24px,3vw,31px);
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .welcome p {
            margin: 0;
            color: #e0ecff;
            font-size: 13px;
        }

        .election-tag {
            flex: 0 1 320px;
            min-width: 0;
            padding: 14px 16px;
            border: 1px solid rgba(255,255,255,.24);
            border-radius: 12px;
            background: rgba(255,255,255,.1);
            color: #e5efff;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .election-tag strong {
            display: block;
            margin-bottom: 4px;
            color: white;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .progress-panel {
            padding: 21px 23px;
            margin-bottom: 31px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: white;
            box-shadow: var(--shadow);
        }

        .progress-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 12px;
            font-weight: 750;
        }

        .progress-count { color: var(--navy); }
        .progress-percent { color: var(--blue); }

        .progress-track {
            height: 9px;
            overflow: hidden;
            border-radius: 20px;
            background: #e6edf6;
        }

        .progress-fill {
            height: 100%;
            width: <?= (float)$progress ?>%;
            border-radius: inherit;
            background: linear-gradient(90deg,var(--blue),var(--teal));
            transition: width .25s ease;
        }

        .position-heading {
            margin: 0 auto 27px;
            text-align: center;
        }

        .position-number {
            display: inline-block;
            margin-bottom: 12px;
            padding: 6px 12px;
            border: 1px solid #cdddf5;
            border-radius: 30px;
            background: #e5eeff;
            color: #2457b5;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .6px;
        }

        .position-heading h2 {
            margin: 0 0 8px;
            color: var(--navy);
            font-size: clamp(24px,3vw,30px);
            line-height: 1.35;
        }

        .position-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .candidate-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            align-items: stretch;
            gap: 22px;
        }

        .candidate-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            border: 2px solid transparent;
            border-radius: 15px;
            background: white;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: border-color .2s, transform .2s, box-shadow .2s;
        }

        .candidate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 13px 30px rgba(20,43,74,.1);
        }

        .candidate-card.selected {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.12), var(--shadow);
        }

        .photo-wrap {
            position: relative;
            height: 290px;
            overflow: hidden;
            background: #e5edf7;
        }

        .candidate-photo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 20%;
            transition: transform .25s;
        }

        .candidate-card:hover .candidate-photo {
            transform: scale(1.025);
        }

        .photo-placeholder {
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
            color: #8fa2bc;
            background: linear-gradient(145deg,#e8eff9,#dbe7f5);
        }

        .photo-placeholder svg {
            width: 66px;
            height: 66px;
        }

        .selected-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            display: none;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 30px;
            background: var(--blue);
            color: white;
            box-shadow: 0 4px 12px rgba(20,43,74,.18);
            font-size: 10px;
            font-weight: 800;
        }

        .candidate-card.selected .selected-badge {
            display: inline-flex;
        }

        .candidate-info {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-width: 0;
            padding: 20px;
        }

        .candidate-name {
            margin-bottom: 11px;
            color: var(--navy);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .candidate-detail {
            margin: 3px 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .candidate-detail strong { color: #40536f; }

        .manifesto {
            margin-top: 15px;
            padding-top: 14px;
            border-top: 1px solid #e8eef6;
            color: #52617a;
            font-size: 12px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .manifesto-title {
            display: block;
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .select-area {
            margin-top: auto;
            padding-top: 19px;
        }

        .select-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #f8faff;
            color: #33496e;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
        }

        .candidate-card.selected .select-label {
            border-color: #b8cdfc;
            background: #eaf1ff;
            color: #2457b5;
        }

        .select-label input {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin: 0;
            accent-color: var(--blue);
            cursor: pointer;
        }

        .empty-state {
            padding: 45px 24px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 61px;
            height: 61px;
            margin: 0 auto 16px;
            border-radius: 17px;
            background: #e5eeff;
            color: var(--blue);
        }

        .empty-icon svg {
            width: 30px;
            height: 30px;
        }

        .empty-state h3 {
            margin-bottom: 9px;
            color: var(--navy);
            font-size: 20px;
        }

        .empty-state p {
            max-width: 510px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .navigation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 29px;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 46px;
            padding: 12px 19px;
            border: 0;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .previous {
            border: 1px solid var(--line);
            background: white;
            color: var(--navy);
        }

        .previous:hover { background: #e8eef7; }

        .next {
            background: var(--blue);
            color: white;
        }

        .next:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
        }

        .security-note {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 9px;
            max-width: 650px;
            margin: 25px auto 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
            text-align: center;
        }

        .security-note svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 2px;
            color: var(--teal);
        }

        .footer {
            margin-top: 35px;
            padding: 22px 15px 28px;
            border-top: 1px solid #d8e3f0;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 950px) {
            .candidate-grid {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }

            .photo-wrap { height: 280px; }
        }

        @media (max-width: 620px) {
            .topbar {
                padding: 13px 14px;
                gap: 10px;
            }

            .brand { gap: 8px; }
            .brand-name { font-size: 13px; }
            .brand-subtitle { font-size: 10px; }
            .brand-mark { width: 36px; height: 36px; }
            .header-actions { gap: 6px; }
            .header-link { padding: 8px 9px; font-size: 11px; }
            .header-avatar { display: none; }

            .page {
                width: calc(100% - 28px);
                margin-top: 23px;
            }

            .welcome {
                align-items: flex-start;
                flex-direction: column;
                padding: 22px;
                border-radius: 15px;
            }

            .election-tag {
                width: 100%;
                flex-basis: auto;
            }

            .progress-panel {
                padding: 18px;
                margin-bottom: 26px;
            }

            .candidate-grid {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .photo-wrap { height: 330px; }
            .candidate-info { padding: 19px; }

            .navigation { gap: 9px; }

            .nav-btn {
                min-width: 0;
                flex: 1;
                padding: 12px 9px;
                text-align: center;
                font-size: 11px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12.5 10 17l9-10"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">Secure Voting System</div>
            <div class="brand-subtitle">Student Election Portal</div>
        </div>
    </div>

    <nav class="header-actions" aria-label="Navigation">
        <div class="header-avatar" title="<?= e($fullName) ?>">
            <?= e($initial) ?>
        </div>
        <a href="dashboard.php" class="header-link">Dashboard</a>
        <a href="../logout.php" class="header-link">Log out</a>
    </nav>
</header>

<main class="page">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;›&nbsp;
        <a href="elections.php">Elections</a>
        &nbsp;›&nbsp;
        <strong>Candidate Selection</strong>
    </div>

    <section class="welcome">
        <div class="welcome-content">
            <div class="eyebrow">Candidate selection</div>
            <h1>Welcome, <?= e($fullName) ?></h1>
            <p>
                Select one candidate for the position below. You can review your
                selections before final submission.
            </p>
        </div>

        <div class="election-tag">
            <strong>Election</strong>
            <?= e($election["election_name"] ?? "Election") ?>
        </div>
    </section>

    <section class="progress-panel" aria-label="Voting progress">
        <div class="progress-top">
            <span class="progress-count">
                Position <?= $current_number ?> of <?= $total_positions ?>
            </span>
            <span class="progress-percent">
                <?= (int)round($progress) ?>% of positions viewed
            </span>
        </div>

        <div
            class="progress-track"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="<?= (int)round($progress) ?>"
            aria-label="Current position progress"
        >
            <div class="progress-fill"></div>
        </div>
    </section>

    <section class="position-heading">
        <span class="position-number">
            POSITION <?= $current_number ?> / <?= $total_positions ?>
        </span>
        <h2><?= e($current_position["position_name"]) ?></h2>
        <p>Review the candidate information and select your preferred candidate.</p>
    </section>

    <?php if (!$candidates): ?>
        <section class="empty-state">
            <div class="empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="8" r="4"/>
                    <path d="M2 21v-2a7 7 0 0 1 14 0v2"/>
                    <path d="M17 8h5M19.5 5.5v5"/>
                </svg>
            </div>
            <h3>No Active Candidates</h3>
            <p>
                There are currently no active candidates for this position.
                Please contact the election administrator before continuing.
            </p>
        </section>

        <div class="navigation">
            <?php if ($position_index > 0): ?>
                <a
                    class="nav-btn previous"
                    href="vote.php?election=<?= (int)$election_id ?>&amp;position=<?= $position_index - 1 ?>"
                >← Previous Position</a>
            <?php else: ?>
                <a class="nav-btn previous" href="elections.php">← Elections</a>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <form method="POST" action="save_vote_selection.php" id="voteSelectionForm">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION["vote_selection_token"]) ?>">
            <input type="hidden" name="election_id" value="<?= (int)$election_id ?>">
            <input type="hidden" name="position_id" value="<?= (int)$position_id ?>">
            <input type="hidden" name="position_index" value="<?= (int)$position_index ?>">

            <div class="candidate-grid">
                <?php foreach ($candidates as $candidate): ?>
                    <?php
                    $is_selected = (
                        $saved_candidate_id === (int)$candidate["id"]
                    );

                    $photoFile = !empty($candidate["photo"])
                        ? basename((string)$candidate["photo"])
                        : "";
                    ?>

                    <label class="candidate-card<?= $is_selected ? " selected" : "" ?>">
                        <div class="photo-wrap">
                            <?php if ($photoFile !== ""): ?>
                                <img
                                    src="../uploads/candidates/<?= rawurlencode($photoFile) ?>"
                                    alt="Photograph of <?= e($candidate["full_name"]) ?>"
                                    class="candidate-photo"
                                    onerror="this.hidden=true;this.nextElementSibling.hidden=false;"
                                >
                                <div class="photo-placeholder" hidden>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <span class="selected-badge">
                                <span aria-hidden="true">✓</span> Selected
                            </span>
                        </div>

                        <div class="candidate-info">
                            <div class="candidate-name">
                                <?= e($candidate["full_name"]) ?>
                            </div>

                            <div class="candidate-detail">
                                <strong>Student ID:</strong>
                                <?= e($candidate["student_id"] ?? "Not provided") ?>
                            </div>

                            <div class="candidate-detail">
                                <strong>Department:</strong>
                                <?= e($candidate["department"] ?: "Not specified") ?>
                            </div>

                            <?php if (!empty($candidate["manifesto"])): ?>
                                <div class="manifesto">
                                    <span class="manifesto-title">Candidate Manifesto</span>
                                    <?= nl2br(e($candidate["manifesto"])) ?>
                                </div>
                            <?php endif; ?>

                            <div class="select-area">
                                <div class="select-label">
                                    <input
                                        type="radio"
                                        name="candidate_id"
                                        value="<?= (int)$candidate["id"] ?>"
                                        <?= $is_selected ? "checked" : "" ?>
                                        required
                                    >
                                    Select this candidate
                                </div>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="navigation">
                <?php if ($position_index > 0): ?>
                    <a
                        class="nav-btn previous"
                        href="vote.php?election=<?= (int)$election_id ?>&amp;position=<?= $position_index - 1 ?>"
                    >← Previous Position</a>
                <?php else: ?>
                    <a class="nav-btn previous" href="elections.php">← Elections</a>
                <?php endif; ?>

                <button type="submit" class="nav-btn next">
                    <?= $position_index === $total_positions - 1
                        ? "Continue to Review →"
                        : "Save & Continue →" ?>
                </button>
            </div>
        </form>
    <?php endif; ?>

    <div class="security-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <rect x="5" y="10" width="14" height="11" rx="2"/>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
        </svg>
        <span>
            Your selection remains temporary until you review and confirm your votes.
        </span>
    </div>
</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Student Election Portal
</footer>

<script>
document.querySelectorAll(".candidate-card").forEach(function (card) {
    const radio = card.querySelector('input[type="radio"]');

    if (!radio) {
        return;
    }

    radio.addEventListener("change", function () {
        document.querySelectorAll(".candidate-card").forEach(function (item) {
            item.classList.remove("selected");
        });

        if (radio.checked) {
            card.classList.add("selected");
        }
    });
});
</script>

</body>
</html>

