
<?php

require_once __DIR__ . "/session_guard.php";

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function formatReviewDate($value): string
{
    if (empty($value)) {
        return "Not set";
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false
        ? "Not set"
        : date("M d, Y · h:i A", $timestamp);
}

/*
 * Token for the final vote-submission form.
 * submit_votes.php must validate this token before inserting votes.
 */
if (empty($_SESSION["submit_votes_token"])) {
    $_SESSION["submit_votes_token"] = bin2hex(random_bytes(32));
}

if (
    empty($_SESSION["vote_selections"]) ||
    empty($_SESSION["vote_election_id"]) ||
    !is_array($_SESSION["vote_selections"])
) {
    header("Location: elections.php");
    exit;
}

$election_id = (int)$_SESSION["vote_election_id"];
$voter_id = (int)$_SESSION["user_id"];
$selections = $_SESSION["vote_selections"];

if ($election_id <= 0) {
    header("Location: elections.php");
    exit;
}

/* Confirm the election is active and within its scheduled voting period. */
$stmt = $pdo->prepare("
    SELECT
        id,
        election_name,
        description,
        start_date,
        end_date,
        eligibility_type,
        eligibility_department,
        status
    FROM elections
    WHERE id = :id
      AND status = 'active'
      AND NOW() BETWEEN start_date AND end_date
    LIMIT 1
");

$stmt->execute(["id" => $election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$election) {
    unset(
        $_SESSION["vote_selections"],
        $_SESSION["vote_election_id"]
    );

    http_response_code(403);
    exit("This election is no longer active or is outside its voting period.");
}

/* Confirm that the voter account is still active. */
$stmt = $pdo->prepare("
    SELECT department, status
    FROM users
    WHERE id = :id
      AND role = 'voter'
    LIMIT 1
");

$stmt->execute(["id" => $voter_id]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voter || ($voter["status"] ?? "") !== "active") {
    http_response_code(403);
    exit("Your voter account is not active. Please contact the administrator.");
}

/* Enforce the election's eligibility rules on the review page. */
$voter_department = trim((string)($voter["department"] ?? ""));
$eligibility_type = (string)($election["eligibility_type"] ?? "");
$is_eligible = false;

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
    http_response_code(403);
    exit("You are not eligible to vote in this election.");
}

/* Do not permit another review session after votes have been recorded. */
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
    unset(
        $_SESSION["vote_selections"],
        $_SESSION["vote_election_id"]
    );

    header("Location: vote_success.php");
    exit;
}

/* Load active positions in their configured display order. */
$stmt = $pdo->query("
    SELECT id, position_name, display_order
    FROM positions
    WHERE status = 'active'
    ORDER BY display_order ASC
");

$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$positions) {
    http_response_code(403);
    exit("No active voting positions are currently configured.");
}

/* Validate every saved selection against the database. */
$review = [];
$invalidSelections = false;
$reviewedPositionIds = [];

foreach ($positions as $index => $position) {
    $position_id = (int)$position["id"];
    $reviewedPositionIds[] = $position_id;

    if (!array_key_exists($position_id, $selections)) {
        continue;
    }

    $rawCandidateId = $selections[$position_id];

    if (!is_scalar($rawCandidateId)) {
        $invalidSelections = true;
        continue;
    }

    $candidate_id = filter_var(
        (string)$rawCandidateId,
        FILTER_VALIDATE_INT
    );

    if (
        $candidate_id === false ||
        $candidate_id === null ||
        $candidate_id <= 0
    ) {
        $invalidSelections = true;
        continue;
    }

    $stmt = $pdo->prepare("
        SELECT
            p.id AS position_id,
            p.position_name,
            p.display_order,
            c.id AS candidate_id,
            c.full_name,
            c.student_id,
            c.department,
            c.photo
        FROM positions p
        INNER JOIN candidates c
            ON c.position_id = p.id
        WHERE p.id = :position_id
          AND p.status = 'active'
          AND c.id = :candidate_id
          AND c.election_id = :election_id
          AND c.status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        "position_id" => $position_id,
        "candidate_id" => (int)$candidate_id,
        "election_id" => $election_id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $row["position_index"] = $index;
        $review[] = $row;
    } else {
        $invalidSelections = true;
    }
}

/*
 * Mark a selection as invalid if the session contains a position
 * that is no longer active.
 */
foreach (array_keys($selections) as $savedPositionId) {
    if (
        !is_scalar($savedPositionId) ||
        !ctype_digit((string)$savedPositionId) ||
        !in_array(
            (int)$savedPositionId,
            $reviewedPositionIds,
            true
        )
    ) {
        $invalidSelections = true;
    }
}

usort($review, function ($a, $b) {
    return (int)$a["display_order"] <=> (int)$b["display_order"];
});

$totalPositions = count($positions);
$totalSelections = count($review);
$missingCount = max(0, $totalPositions - $totalSelections);

$canSubmit = (
    $totalSelections === $totalPositions &&
    !$invalidSelections
);

/* Find the first missing position so the voter can continue there. */
$nextMissingIndex = 0;
$selectedByPosition = [];

foreach ($review as $item) {
    $selectedByPosition[(int)$item["position_id"]] = true;
}

foreach ($positions as $index => $position) {
    if (!isset($selectedByPosition[(int)$position["id"]])) {
        $nextMissingIndex = $index;
        break;
    }

    $nextMissingIndex = min($index + 1, $totalPositions - 1);
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
    <title>Review Your Votes | Secure Voting System</title>

    <style>
        :root {
            --navy: #142b4a;
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
            --orange: #85500d;
            --orange-bg: #fff0dc;
            --red: #b42318;
            --red-bg: #fff1f0;
            --shadow: 0 8px 25px rgba(20,43,74,.06);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        a, button { font: inherit; }
        a { text-decoration: none; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .brand-title {
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
            gap: 9px;
        }

        .header-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .container {
            width: min(950px, calc(100% - 40px));
            margin: 30px auto 0;
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
            align-items: center;
            justify-content: space-between;
            gap: 22px;
            padding: 27px 29px;
            margin-bottom: 22px;
            border-radius: 16px;
            background: linear-gradient(115deg,#142b4a 0%,#1d4775 68%,#0d9488 140%);
            box-shadow: 0 12px 30px rgba(20,43,74,.13);
            color: white;
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
            letter-spacing: 1.1px;
        }

        .welcome h1 {
            margin: 0 0 8px;
            color: white;
            font-size: clamp(24px,3vw,30px);
            line-height: 1.35;
        }

        .welcome p {
            margin: 0;
            color: #e0ecff;
            font-size: 13px;
        }

        .election-name {
            flex: 0 1 280px;
            min-width: 0;
            padding: 14px 16px;
            border: 1px solid rgba(255,255,255,.24);
            border-radius: 12px;
            background: rgba(255,255,255,.1);
            color: white;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .election-name span {
            display: block;
            margin-bottom: 5px;
            color: #d5e6f7;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .summary-panel {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 13px;
            margin-bottom: 25px;
        }

        .summary-card {
            padding: 17px 19px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
            box-shadow: var(--shadow);
        }

        .summary-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .summary-value {
            margin-top: 3px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
            line-height: 1.4;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin: 28px 0 16px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 19px;
        }

        .section-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .review-list {
            display: grid;
            gap: 14px;
        }

        .review-card {
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 0;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            box-shadow: var(--shadow);
            transition: box-shadow .2s, transform .2s;
        }

        .review-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(20,43,74,.09);
        }

        .photo-wrap {
            width: 95px;
            height: 112px;
            flex: 0 0 95px;
            overflow: hidden;
            border-radius: 10px;
            background: #e4edf8;
        }

        .photo {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 20%;
        }

        .photo-placeholder {
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
            color: #8fa2bc;
        }

        .photo-placeholder svg {
            width: 43px;
            height: 43px;
        }

        .details {
            flex: 1;
            min-width: 0;
        }

        .position-line {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
            margin-bottom: 7px;
        }

        .position {
            color: var(--blue);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .selected-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 30px;
            background: var(--green-bg);
            color: var(--green);
            font-size: 10px;
            font-weight: 800;
        }

        .name {
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .student {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            margin-top: 23px;
            border: 1px solid #f3d19c;
            border-radius: 10px;
            background: var(--orange-bg);
            color: var(--orange);
            font-size: 12px;
            line-height: 1.8;
        }

        .notice-icon {
            display: grid;
            place-items: center;
            width: 25px;
            height: 25px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #ffe3bd;
            color: var(--orange);
            font-weight: 900;
        }

        .notice strong { color: #75450b; }

        .notice-error {
            border-color: #fecaca;
            background: var(--red-bg);
            color: var(--red);
        }

        .notice-error .notice-icon {
            background: #fee2e2;
            color: var(--red);
        }

        .notice-error strong { color: var(--red); }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 13px;
            margin-top: 23px;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
            box-shadow: var(--shadow);
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            min-height: 44px;
            padding: 12px 17px;
            border: 0;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .btn-back {
            border: 1px solid var(--border);
            background: #edf2f8;
            color: var(--navy);
        }

        .btn-back:hover { background: #dfe8f3; }

        .btn-confirm {
            background: var(--blue);
            color: white;
        }

        .btn-confirm:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
        }

        .btn-confirm:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        .confirm-form { margin: 0; }

        .footer {
            margin-top: 32px;
            padding: 22px 15px 28px;
            border-top: 1px solid #d8e3f0;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 13px 14px;
                gap: 9px;
            }

            .brand { gap: 8px; }
            .brand-title { font-size: 13px; }
            .brand-subtitle { font-size: 10px; }
            .brand-mark { width: 36px; height: 36px; }
            .header-actions { gap: 6px; }
            .header-link { padding: 8px 9px; font-size: 11px; }

            .container {
                width: calc(100% - 28px);
                margin-top: 22px;
            }

            .welcome {
                align-items: flex-start;
                flex-direction: column;
                padding: 22px;
            }

            .election-name {
                width: 100%;
                flex-basis: auto;
            }

            .summary-panel {
                grid-template-columns: 1fr;
                gap: 9px;
            }

            .summary-card {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 13px 16px;
            }

            .summary-value {
                margin-top: 0;
                font-size: 21px;
            }

            .review-card {
                align-items: flex-start;
                gap: 13px;
                padding: 14px;
            }

            .photo-wrap {
                width: 76px;
                height: 92px;
                flex-basis: 76px;
            }

            .name { font-size: 15px; }
            .student { font-size: 11px; }

            .actions {
                align-items: stretch;
                flex-direction: column-reverse;
                padding: 15px;
            }

            .actions .btn,
            .confirm-form,
            .confirm-form .btn {
                width: 100%;
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

    <nav class="header-actions" aria-label="Navigation">
        <a href="dashboard.php" class="header-link">Dashboard</a>
        <a href="../logout.php" class="header-link">Log out</a>
    </nav>
</header>

<main class="container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;›&nbsp;
        <a href="elections.php">Elections</a>
        &nbsp;›&nbsp;
        <strong>Review Votes</strong>
    </div>

    <section class="welcome">
        <div>
            <div class="eyebrow">Final review</div>
            <h1>Review Your Selections</h1>
            <p>
                Hello, <?= e($fullName) ?>. Confirm that each position has the
                candidate you intended to select.
            </p>
        </div>

        <div class="election-name">
            <span>Election</span>
            <?= e($election["election_name"] ?? "Election") ?>
        </div>
    </section>

    <section class="summary-panel" aria-label="Review summary">
        <article class="summary-card">
            <div class="summary-label">Positions selected</div>
            <div class="summary-value"><?= $totalSelections ?></div>
        </article>

        <article class="summary-card">
            <div class="summary-label">Total active positions</div>
            <div class="summary-value"><?= $totalPositions ?></div>
        </article>

        <article class="summary-card">
            <div class="summary-label">Selections remaining</div>
            <div class="summary-value"><?= $missingCount ?></div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <h2>Your Candidate Choices</h2>
                <p>Review the details below before submitting.</p>
            </div>
        </div>

        <?php if ($totalSelections > 0): ?>
            <div class="review-list">
                <?php foreach ($review as $item): ?>
                    <?php
                    $photoFile = !empty($item["photo"])
                        ? basename((string)$item["photo"])
                        : "";
                    ?>

                    <article class="review-card">
                        <div class="photo-wrap">
                            <?php if ($photoFile !== ""): ?>
                                <img
                                    src="../uploads/candidates/<?= rawurlencode($photoFile) ?>"
                                    class="photo"
                                    alt="Photograph of <?= e($item["full_name"]) ?>"
                                    onerror="this.hidden=true;this.nextElementSibling.hidden=false;"
                                >
                                <div class="photo-placeholder" hidden>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="details">
                            <div class="position-line">
                                <span class="position">
                                    <?= e($item["position_name"]) ?>
                                </span>
                                <span class="selected-pill">✓ Selected</span>
                            </div>

                            <div class="name"><?= e($item["full_name"]) ?></div>

                            <div class="student">
                                <strong>Student ID:</strong>
                                <?= e($item["student_id"] ?? "Not provided") ?>
                                <br>
                                <strong>Department:</strong>
                                <?= e($item["department"] ?: "Not specified") ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($missingCount > 0 || $invalidSelections): ?>
            <div class="notice notice-error" role="alert">
                <span class="notice-icon">!</span>
                <div>
                    <strong>Your selections are incomplete.</strong>
                    <?php if ($missingCount > 0): ?>
                        You have selected candidates for <?= $totalSelections ?>
                        of <?= $totalPositions ?> active positions.
                    <?php endif; ?>
                    <?php if ($invalidSelections): ?>
                        One or more selections are no longer valid. Please review them again.
                    <?php endif; ?>
                    Return to the voting page to complete or correct your selections.
                </div>
            </div>
        <?php else: ?>
            <div class="notice" role="note">
                <span class="notice-icon">!</span>
                <div>
                    <strong>Final submission.</strong>
                    Check every choice before confirming. After submission,
                    your votes may not be changeable.
                </div>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a
                href="vote.php?election=<?= $election_id ?>&amp;position=<?= $nextMissingIndex ?>"
                class="btn btn-back"
            >
                ← Review Selections
            </a>

            <form
                action="submit_votes.php"
                method="POST"
                class="confirm-form"
                id="confirmForm"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION["submit_votes_token"]) ?>"
                >

                <button
                    type="submit"
                    class="btn btn-confirm"
                    <?= !$canSubmit ? "disabled" : "" ?>
                >
                    Confirm &amp; Submit Votes →
                </button>
            </form>
        </div>
    </section>
</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Student Election Portal
</footer>

<script>
const confirmForm = document.getElementById("confirmForm");

if (confirmForm) {
    confirmForm.addEventListener("submit", function (event) {
        const confirmed = window.confirm(
            "Are you sure you want to submit your votes?\n\n" +
            "Review your choices carefully. Continue with final submission?"
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
}
</script>
</body>
</html>
