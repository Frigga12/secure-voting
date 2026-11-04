
<?php

require_once __DIR__ . "/session_guard.php";

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function formatElectionDate($value): string
{
    if (empty($value)) {
        return "Not set";
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false
        ? "Not set"
        : date("M d, Y · h:i A", $timestamp);
}

$voter_id = (int)$_SESSION["user_id"];

$stmt = $pdo->prepare("
    SELECT department
    FROM users
    WHERE id = :id
      AND role = 'voter'
      AND status = 'active'
    LIMIT 1
");

$stmt->execute(["id" => $voter_id]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voter) {
    http_response_code(403);
    exit("Voter account not found or inactive.");
}

$voter_department = trim((string)($voter["department"] ?? ""));

$stmt = $pdo->prepare("
    SELECT
        id,
        election_name,
        description,
        start_date,
        end_date,
        eligibility_type,
        eligibility_department
    FROM elections
    WHERE status = 'active'
      AND NOW() BETWEEN start_date AND end_date
    ORDER BY start_date ASC
");

$stmt->execute();
$elections = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

foreach ($elections as &$election) {
    $type = (string)($election["eligibility_type"] ?? "");

    if ($type === "all") {
        $election["is_eligible"] = true;
    } elseif ($type === "department") {
        $election["is_eligible"] = (
            $voter_department !== "" &&
            strcasecmp(
                trim((string)($election["eligibility_department"] ?? "")),
                $voter_department
            ) === 0
        );
    } elseif ($type === "engineering") {
        $election["is_eligible"] = false;

        foreach ($engineering_departments as $department) {
            if (
                $voter_department !== "" &&
                strcasecmp($department, $voter_department) === 0
            ) {
                $election["is_eligible"] = true;
                break;
            }
        }
    } else {
        $election["is_eligible"] = false;
    }

    $voteStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM votes
        WHERE election_id = :election_id
          AND voter_id = :voter_id
    ");

    $voteStmt->execute([
        "election_id" => $election["id"],
        "voter_id" => $voter_id
    ]);

    $election["already_voted"] =
        (int)$voteStmt->fetchColumn() > 0;
}
unset($election);

$totalElections = count($elections);
$eligibleCount = 0;
$votedCount = 0;

foreach ($elections as $election) {
    if ($election["is_eligible"]) {
        $eligibleCount++;
    }

    if ($election["already_voted"]) {
        $votedCount++;
    }
}

$fullName = trim((string)($_SESSION["full_name"] ?? "Student"));

if ($fullName === "") {
    $fullName = "Student";
}

$initial = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Active Elections | Secure Voting System</title>

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
            --border: #dce5f0;
            --green: #087a55;
            --green-bg: #ddf7ec;
            --orange: #a85a08;
            --orange-bg: #fff0dc;
            --red: #b42318;
            --red-bg: #fff1f0;
            --grey: #53647a;
            --grey-bg: #e9eef5;
            --shadow: 0 8px 25px rgba(20,43,74,.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 15px 5%;
            background: var(--navy);
            color: white;
            border-bottom: 4px solid var(--teal);
            box-shadow: 0 5px 18px rgba(20,43,74,.13);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            border-radius: 12px;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
        }

        .brand-title {
            color: white;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -.3px;
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
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 9px;
            color: white;
            background: #274567;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: background .2s;
        }

        .header-link:hover {
            background: #365b85;
        }

        .container {
            width: min(1250px, calc(100% - 40px));
            margin: 32px auto 0;
        }

        .breadcrumb {
            margin-bottom: 21px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--blue);
            font-weight: 650;
        }

        .breadcrumb strong {
            color: var(--navy);
        }

        .welcome {
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            padding: 30px;
            margin-bottom: 26px;
            border-radius: 18px;
            color: white;
            background: linear-gradient(115deg,#142b4a 0%,#1d4775 65%,#0d9488 140%);
            box-shadow: 0 12px 30px rgba(20,43,74,.13);
        }

        .welcome::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            right: -90px;
            top: -135px;
            border: 34px solid rgba(255,255,255,.07);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

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
            margin-bottom: 9px;
            color: white;
            font-size: clamp(25px,3vw,33px);
            line-height: 1.3;
        }

        .welcome p {
            max-width: 650px;
            color: #e0ecff;
            font-size: 14px;
        }

        .summary-card {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
            padding: 16px 20px;
            border: 1px solid rgba(255,255,255,.23);
            border-radius: 13px;
            background: rgba(255,255,255,.1);
        }

        .summary-icon {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            border-radius: 12px;
            background: rgba(255,255,255,.15);
        }

        .summary-icon svg {
            width: 23px;
            height: 23px;
        }

        .summary-number {
            display: block;
            color: white;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
        }

        .summary-label {
            display: block;
            margin-top: 4px;
            color: #d5e6f7;
            font-size: 11px;
            white-space: nowrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
            box-shadow: var(--shadow);
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 13px;
            background: #e5eeff;
            color: var(--blue);
        }

        .stat-icon svg {
            width: 23px;
            height: 23px;
        }

        .stat-icon.teal {
            background: #ccfbf1;
            color: #0f766e;
        }

        .stat-icon.green {
            background: var(--green-bg);
            color: var(--green);
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .stat-value {
            margin-top: 2px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
            line-height: 1.3;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin: 28px 0 17px;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 20px;
        }

        .section-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }

        .count-pill {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 9px;
            background: #e0eafe;
            color: #2457b5;
            font-size: 11px;
            font-weight: 800;
        }

        .election-grid {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 20px;
        }

        .election-card {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow);
            transition: transform .2s, box-shadow .2s;
        }

        .election-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg,var(--blue),var(--teal));
        }

        .election-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 13px 30px rgba(20,43,74,.1);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 19px;
        }

        .election-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            border-radius: 13px;
            background: #e5eeff;
            color: var(--blue);
        }

        .election-icon svg {
            width: 25px;
            height: 25px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 30px;
            background: var(--green-bg);
            color: var(--green);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            flex-shrink: 0;
            border-radius: 50%;
            background: currentColor;
        }

        .election-card h3 {
            margin-bottom: 9px;
            color: var(--navy);
            font-size: 19px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .description {
            min-height: 52px;
            margin-bottom: 19px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .eligibility {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 12px;
            margin-bottom: 17px;
            border: 1px solid #e4ebf5;
            border-radius: 10px;
            background: #f6f9fe;
        }

        .eligibility svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
            margin-top: 2px;
            color: var(--blue);
        }

        .eligibility-label {
            display: block;
            margin-bottom: 3px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .eligibility-value {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .dates {
            padding: 15px;
            margin-bottom: 19px;
            border: 1px solid #e5ecf5;
            border-radius: 11px;
            background: #f8faff;
        }

        .dates-heading {
            margin-bottom: 12px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
        }

        .date-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e8eef6;
        }

        .date-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .date-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 650;
        }

        .date-value {
            color: var(--text);
            font-size: 11px;
            font-weight: 700;
            text-align: right;
        }

        .card-footer {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid #e8eef6;
        }

        .vote-button,
        .status-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 42px;
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 800;
        }

        .vote-button {
            background: var(--blue);
            color: white;
            transition: background .2s, transform .2s;
        }

        .vote-button:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
        }

        .vote-button svg {
            width: 17px;
            height: 17px;
        }

        .status-button {
            background: var(--grey-bg);
            color: var(--grey);
        }

        .status-button.complete {
            background: var(--green-bg);
            color: var(--green);
        }

        .status-button.denied {
            background: var(--red-bg);
            color: var(--red);
        }

        .empty {
            padding: 55px 24px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 65px;
            height: 65px;
            margin: 0 auto 17px;
            border-radius: 18px;
            background: #e5eeff;
            color: var(--blue);
        }

        .empty-icon svg {
            width: 31px;
            height: 31px;
        }

        .empty h3 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 20px;
        }

        .empty p {
            max-width: 440px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding: 10px 14px;
            border-radius: 9px;
            background: var(--navy);
            color: white;
            font-size: 12px;
            font-weight: 750;
        }

        .info-strip {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 24px;
            padding: 17px 19px;
            border: 1px solid #cdddf5;
            border-radius: 12px;
            background: #e6efff;
            color: #345274;
            font-size: 12px;
            line-height: 1.8;
        }

        .info-icon {
            display: grid;
            place-items: center;
            width: 23px;
            height: 23px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #d1e2ff;
            color: #2457b5;
            font-weight: 800;
        }

        .footer {
            margin-top: 35px;
            padding: 22px 15px 28px;
            border-top: 1px solid #d8e3f0;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 850px) {
            .election-grid {
                grid-template-columns: 1fr;
            }

            .welcome {
                align-items: flex-start;
            }

            .summary-card {
                flex-shrink: 0;
            }
        }

        @media (max-width: 580px) {
            .topbar {
                padding: 13px 14px;
                gap: 9px;
            }

            .brand {
                gap: 8px;
            }

            .brand-title {
                font-size: 13px;
            }

            .brand-subtitle {
                font-size: 10px;
            }

            .brand-mark {
                width: 36px;
                height: 36px;
            }

            .header-actions {
                gap: 6px;
            }

            .header-link {
                padding: 8px;
                font-size: 11px;
            }

            .container {
                width: calc(100% - 28px);
                margin-top: 23px;
            }

            .welcome {
                flex-direction: column;
                padding: 23px;
                border-radius: 15px;
            }

            .summary-card {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .section-heading {
                align-items: flex-start;
            }

            .election-card {
                padding: 21px 17px;
            }

            .description {
                min-height: 0;
            }

            .card-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .vote-button,
            .status-button {
                width: 100%;
            }

            .date-row {
                flex-direction: column;
                gap: 3px;
            }

            .date-value {
                text-align: left;
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
            <div class="brand-title">Secure Voting System</div>
            <div class="brand-subtitle">Student Election Portal</div>
        </div>
    </div>

    <nav class="header-actions" aria-label="Main navigation">
        <a href="dashboard.php" class="header-link">Dashboard</a>
        <a href="../logout.php" class="header-link">Log out</a>
    </nav>
</header>

<main class="container">
    <div class="breadcrumb">
        Student Portal &nbsp;›&nbsp;
        <strong>Active Elections</strong>
    </div>

    <section class="welcome">
        <div class="welcome-content">
            <div class="eyebrow">Voter Portal</div>
            <h1>Active Elections</h1>
            <p>
                Welcome, <?= e($fullName) ?>. Review the currently open elections
                and participate in those for which you are eligible.
            </p>
        </div>

        <div class="summary-card">
            <div class="summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                    <path d="M8 8h8M8 12h5"/>
                    <path d="m14 16 2 2 4-4"/>
                </svg>
            </div>
            <div>
                <span class="summary-number"><?= $totalElections ?></span>
                <span class="summary-label">
                    Open election<?= $totalElections === 1 ? "" : "s" ?>
                </span>
            </div>
        </div>
    </section>

    <section class="stats-grid" aria-label="Election summary">
        <article class="stat-card">
            <div class="stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                     stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                    <path d="M8 8h8M8 12h8M8 16h4"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">Open Elections</div>
                <div class="stat-value"><?= $totalElections ?></div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon teal" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                     stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="m8 12 2.5 2.5L16 9"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">Eligible Elections</div>
                <div class="stat-value"><?= $eligibleCount ?></div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon green" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"
                     stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12.5 10 17l9-10"/>
                    <circle cx="12" cy="12" r="10"/>
                </svg>
            </div>
            <div>
                <div class="stat-label">With Recorded Votes</div>
                <div class="stat-value"><?= $votedCount ?></div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <h2>Available Elections</h2>
                <p>Select an eligible election to continue to the voting page.</p>
            </div>

            <span class="count-pill"><?= $totalElections ?> found</span>
        </div>

        <?php if (!$elections): ?>
            <div class="empty">
                <div class="empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                        <path d="M16 3v4M8 3v4M3 10h18"/>
                        <path d="M9 15h6"/>
                    </svg>
                </div>

                <h3>No Active Elections</h3>
                <p>
                    There are currently no elections open within their scheduled
                    voting periods. Please check again when an election becomes available.
                </p>

                <a href="dashboard.php" class="back-button">
                    ← Back to Dashboard
                </a>
            </div>
        <?php else: ?>

            <div class="election-grid">
                <?php foreach ($elections as $election): ?>
                    <?php
                    $type = (string)($election["eligibility_type"] ?? "");

                    if ($type === "all") {
                        $eligibilityLabel = "All Students";
                    } elseif ($type === "department") {
                        $eligibilityLabel = trim(
                            (string)($election["eligibility_department"] ?? "")
                        );

                        if ($eligibilityLabel === "") {
                            $eligibilityLabel = "Specific Department";
                        }
                    } elseif ($type === "engineering") {
                        $eligibilityLabel = "Engineering Students";
                    } else {
                        $eligibilityLabel = "Eligibility not configured";
                    }
                    ?>

                    <article class="election-card">
                        <div class="card-top">
                            <div class="election-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                                    <path d="M8 8h8M8 12h5"/>
                                    <path d="m14 16 2 2 4-4"/>
                                </svg>
                            </div>

                            <span class="status">Open for Voting</span>
                        </div>

                        <h3><?= e($election["election_name"] ?? "Untitled Election") ?></h3>

                        <p class="description">
                            <?php if (!empty($election["description"])): ?>
                                <?= nl2br(e($election["description"])) ?>
                            <?php else: ?>
                                Participate in this election by selecting your
                                preferred candidates for the available positions.
                            <?php endif; ?>
                        </p>

                        <div class="eligibility">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <circle cx="9" cy="8" r="4"/>
                                <path d="M2 21v-2a7 7 0 0 1 14 0v2"/>
                                <path d="m16 11 2 2 4-4"/>
                            </svg>

                            <div>
                                <span class="eligibility-label">Eligible voter group</span>
                                <span class="eligibility-value">
                                    <?= e($eligibilityLabel) ?>
                                </span>
                            </div>
                        </div>

                        <div class="dates">
                            <div class="dates-heading">Election Schedule</div>

                            <div class="date-row">
                                <span class="date-label">Opening date</span>
                                <span class="date-value">
                                    <?= e(formatElectionDate($election["start_date"] ?? null)) ?>
                                </span>
                            </div>

                            <div class="date-row">
                                <span class="date-label">Closing date</span>
                                <span class="date-value">
                                    <?= e(formatElectionDate($election["end_date"] ?? null)) ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-footer">
                            <?php if (!$election["is_eligible"]): ?>
                                <span class="status-button denied">
                                    Not Eligible
                                </span>
                            <?php elseif ($election["already_voted"]): ?>
                                <span class="status-button complete">
                                    ✓ Vote Recorded
                                </span>
                            <?php else: ?>
                                <a
                                    href="vote.php?election=<?= (int)$election["id"] ?>"
                                    class="vote-button"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <path d="M5 12.5 10 17l9-10"/>
                                    </svg>
                                    Vote in This Election
                                    <span aria-hidden="true">→</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="info-strip">
        <span class="info-icon" aria-hidden="true">i</span>
        <p>
            <strong>Security reminder:</strong>
            Keep your login details private. Only vote in elections for which
            you are eligible, and log out when you finish using a shared computer.
        </p>
    </div>
</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Student Election Portal
</footer>

</body>
</html>
