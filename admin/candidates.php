
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

// Get all candidates with their election and position details.
$sql = "
    SELECT
        c.id,
        c.full_name,
        c.department,
        c.student_id,
        c.photo,
        c.manifesto,
        c.status,
        c.election_id,
        e.election_name,
        p.position_name,
        p.display_order
    FROM candidates c
    LEFT JOIN elections e ON c.election_id = e.id
    LEFT JOIN positions p ON c.position_id = p.id
    ORDER BY
        c.election_id DESC,
        p.display_order ASC,
        c.full_name ASC
";

$candidates = $pdo->query($sql)->fetchAll();

$elections = $pdo->query("
    SELECT id, election_name
    FROM elections
    ORDER BY id DESC
")->fetchAll();

$positions = $pdo->query("
    SELECT DISTINCT p.position_name, p.display_order
    FROM positions p
    INNER JOIN candidates c ON c.position_id = p.id
    ORDER BY p.display_order ASC
")->fetchAll();

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$totalCandidates = count($candidates);
$activeCandidates = 0;
$inactiveCandidates = 0;

foreach ($candidates as $candidate) {
    if (strtolower((string)($candidate["status"] ?? "")) === "active") {
        $activeCandidates++;
    } else {
        $inactiveCandidates++;
    }
}

$adminName = trim((string)($_SESSION["full_name"] ?? "Administrator"));
$adminInitial = strtoupper(substr($adminName !== "" ? $adminName : "A", 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Management | Secure Voting System</title>

    <style>
        :root {
            --navy: #142B4A;
            --blue: #2563EB;
            --teal: #0D9488;
            --background: #EDF3FB;
            --white: #FFFFFF;
            --text: #182B45;
            --muted: #687991;
            --border: #DCE5F0;
            --green-bg: #DDF7EC;
            --green: #087A55;
            --red-bg: #FDE8E7;
            --red: #B42332;
            --shadow: 0 8px 25px rgba(20, 43, 74, .07);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.5;
        }

        a {
            color: inherit;
        }

        .topbar {
            background: var(--navy);
            color: var(--white);
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 16px rgba(20, 43, 74, .16);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, #3B82F6, #0D9488);
            font-size: 19px;
            font-weight: 800;
        }

        .brand-title {
            font-size: 15px;
            font-weight: 750;
            letter-spacing: .2px;
        }

        .brand-subtitle {
            margin-top: 2px;
            font-size: 12px;
            color: #C7D7EB;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .admin-label {
            color: #D7E4F5;
            font-size: 13px;
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #D9F4F0;
            color: #087E75;
            font-weight: 800;
        }

        .logout {
            text-decoration: none;
            color: white;
            border: 1px solid #59718F;
            padding: 9px 13px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
            transition: background .2s;
        }

        .logout:hover {
            background: #274567;
        }

        .page {
            width: min(1440px, 100%);
            margin: auto;
            padding: 30px 28px 42px;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 19px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumbs a {
            color: var(--blue);
            font-weight: 650;
            text-decoration: none;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 23px;
        }

        .eyebrow {
            color: var(--teal);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin-bottom: 7px;
        }

        h1 {
            color: var(--navy);
            font-size: clamp(25px, 3vw, 32px);
            line-height: 1.2;
            letter-spacing: -.7px;
        }

        .heading-description {
            margin-top: 9px;
            color: var(--muted);
            font-size: 14px;
        }

        .primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 17px;
            border: 0;
            border-radius: 10px;
            background: var(--blue);
            color: white;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 5px 12px rgba(37, 99, 235, .18);
            transition: transform .2s, background .2s;
        }

        .primary-button:hover {
            background: #1D4ED8;
            transform: translateY(-2px);
        }

        .welcome-banner {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 25px 28px;
            margin-bottom: 23px;
            border-radius: 17px;
            color: white;
            background: linear-gradient(115deg, #142B4A 0%, #1D4775 62%, #0D9488 130%);
            box-shadow: 0 10px 25px rgba(20, 43, 74, .12);
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            right: 45px;
            top: -130px;
            border: 30px solid rgba(255, 255, 255, .06);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-banner h2 {
            font-size: 19px;
            margin-bottom: 5px;
        }

        .welcome-banner p {
            color: #D5E6F7;
            font-size: 13px;
            max-width: 620px;
        }

        .banner-count {
            flex-shrink: 0;
            text-align: center;
            padding: 12px 20px;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 13px;
            background: rgba(255, 255, 255, .09);
        }

        .banner-count strong {
            display: block;
            font-size: 25px;
            line-height: 1.2;
        }

        .banner-count span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #D5E6F7;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
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
            flex-shrink: 0;
            width: 47px;
            height: 47px;
            border-radius: 13px;
            font-size: 21px;
            font-weight: 800;
        }

        .icon-blue {
            background: #E5EEFF;
            color: #2563EB;
        }

        .icon-teal {
            background: #DDF7F3;
            color: #0D9488;
        }

        .icon-purple {
            background: #F0E8FF;
            color: #7752C8;
        }

        .stat-number {
            font-size: 25px;
            line-height: 1.2;
            color: var(--navy);
            font-weight: 800;
        }

        .stat-label {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .panel {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow);
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 21px 22px 17px;
            border-bottom: 1px solid #E8EEF6;
        }

        .panel-heading h2 {
            color: var(--navy);
            font-size: 17px;
        }

        .panel-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .result-count {
            flex-shrink: 0;
            color: var(--navy);
            background: #EDF3FB;
            padding: 7px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 750;
        }

        .filters {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(150px, 220px) minmax(150px, 220px);
            gap: 12px;
            padding: 18px 22px;
            background: #F8FAFD;
            border-bottom: 1px solid #E8EEF6;
        }

        .field {
            min-width: 0;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            color: #4B5F78;
            font-size: 11px;
            font-weight: 750;
        }

        .field input,
        .field select {
            width: 100%;
            min-width: 0;
            height: 43px;
            padding: 0 12px;
            border: 1px solid #D3DEEB;
            border-radius: 9px;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 13px;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .candidate-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            padding: 22px;
        }

        .candidate-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 3px 12px rgba(20, 43, 74, .045);
            transition: transform .2s, box-shadow .2s, border-color .2s;
        }

        .candidate-card:hover {
            transform: translateY(-3px);
            border-color: #C5D7F2;
            box-shadow: 0 12px 27px rgba(20, 43, 74, .1);
        }

        .candidate-photo {
            position: relative;
            height: 270px;
            overflow: hidden;
            background: linear-gradient(145deg, #E3ECF8, #D8F0EC);
        }

        .candidate-photo img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 20%;
            transition: transform .35s;
        }

        .candidate-card:hover .candidate-photo img {
            transform: scale(1.025);
        }

        .no-photo {
            display: flex;
            width: 100%;
            height: 100%;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: #627892;
            font-size: 13px;
        }

        .no-photo svg {
            width: 54px;
            height: 54px;
            padding: 13px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .75);
            color: #56718F;
        }

        .candidate-details {
            padding: 19px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-bottom: 13px;
        }

        .badge {
            display: inline-block;
            max-width: 100%;
            overflow-wrap: anywhere;
            padding: 6px 9px;
            border-radius: 7px;
            background: #E5EEFF;
            color: #2457B5;
            font-size: 10px;
            font-weight: 800;
        }

        .badge-position {
            background: #DDF7F3;
            color: #087E75;
        }

        .candidate-name {
            margin-bottom: 14px;
            color: var(--navy);
            font-size: 19px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .candidate-info {
            display: grid;
            gap: 10px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 8px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .info-row strong {
            color: #405671;
            font-weight: 700;
        }

        .manifesto {
            margin-top: 15px;
            padding: 13px;
            border-radius: 9px;
            background: #F4F7FC;
            color: #52657D;
            font-size: 12px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .manifesto-label {
            display: block;
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .status-row {
            margin-top: 15px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-active {
            background: var(--green-bg);
            color: var(--green);
        }

        .status-inactive {
            background: var(--red-bg);
            color: var(--red);
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
            margin-top: 17px;
            padding-top: 15px;
            border-top: 1px solid #E8EEF6;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 8px;
            border-radius: 9px;
            text-decoration: none;
            text-align: center;
            font-size: 12px;
            font-weight: 750;
            transition: background .2s, transform .2s;
        }

        .edit-button {
            background: #E5EEFF;
            color: #2457B5;
        }

        .edit-button:hover {
            background: #D5E4FF;
        }

        .remove-button {
            background: #FDE8E7;
            color: #B42332;
        }

        .remove-button:hover {
            background: #FBD5D3;
        }

        .empty-state,
        .empty-filter {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 22px;
            border-radius: 13px;
            background: #F8FAFD;
            color: var(--muted);
        }

        .empty-state h3 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 19px;
        }

        .empty-state p,
        .empty-filter p {
            font-size: 13px;
            line-height: 1.7;
        }

        .empty-state .primary-button {
            margin-top: 17px;
        }

        .empty-filter {
            display: none;
            padding: 25px 15px;
        }

        .panel-footer {
            padding: 15px 22px;
            border-top: 1px solid #E8EEF6;
            color: var(--muted);
            font-size: 12px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 23px;
            color: var(--blue);
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .site-footer {
            margin-top: 35px;
            padding-top: 18px;
            border-top: 1px solid #D8E3F0;
            color: #72839A;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 1050px) {
            .candidate-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filters {
                grid-template-columns: 1fr 1fr;
            }

            .search-field {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 13px 16px;
            }

            .brand-title {
                font-size: 13px;
            }

            .brand-subtitle,
            .admin-label {
                display: none;
            }

            .topbar-right {
                gap: 9px;
            }

            .logout {
                padding: 8px 10px;
                font-size: 12px;
            }

            .page {
                padding: 22px 14px 30px;
            }

            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .primary-button {
                width: 100%;
            }

            .welcome-banner {
                align-items: flex-start;
                flex-direction: column;
                padding: 21px;
            }

            .banner-count {
                display: flex;
                align-items: center;
                gap: 10px;
                text-align: left;
            }

            .banner-count span {
                margin-top: 0;
            }

            .stats {
                grid-template-columns: 1fr;
                gap: 11px;
            }

            .stat-card {
                padding: 15px;
            }

            .filters {
                grid-template-columns: 1fr;
                padding: 15px;
            }

            .search-field {
                grid-column: auto;
            }

            .panel-heading {
                align-items: flex-start;
                padding: 18px 15px;
            }

            .candidate-grid {
                grid-template-columns: 1fr;
                padding: 14px;
                gap: 16px;
            }

            .candidate-photo {
                height: 310px;
            }

            .panel-footer {
                padding: 14px 15px;
            }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="brand">
        <div class="brand-mark">S</div>
        <div>
            <div class="brand-title">Secure Voting System</div>
            <div class="brand-subtitle">Administration Portal</div>
        </div>
    </div>

    <div class="topbar-right">
        <span class="admin-label">Administrator</span>
        <div class="avatar" title="<?= escapeHtml($adminName) ?>">
            <?= escapeHtml($adminInitial) ?>
        </div>
        <a class="logout" href="../logout.php">Log out</a>
    </div>
</header>

<main class="page">
    <div class="breadcrumbs">
        <a href="dashboard.php">Dashboard</a>
        <span>›</span>
        <span>Candidate Management</span>
    </div>

    <section class="page-heading">
        <div>
            <div class="eyebrow">Election administration</div>
            <h1>Candidate Management</h1>
            <p class="heading-description">
                Manage candidate profiles, positions and election participation.
            </p>
        </div>

        <a href="add_candidate.php" class="primary-button">
            <span aria-hidden="true">+</span>
            Add Candidate
        </a>
    </section>

    <section class="welcome-banner">
        <div>
            <h2>Your candidates, all in one place</h2>
            <p>
                Review candidate information, check their status and manage
                the candidates registered for each election.
            </p>
        </div>

        <div class="banner-count">
            <strong><?= $totalCandidates ?></strong>
            <span>Total candidates registered</span>
        </div>
    </section>

    <section class="stats" aria-label="Candidate statistics">
        <article class="stat-card">
            <div class="stat-icon icon-blue" aria-hidden="true">C</div>
            <div>
                <div class="stat-number"><?= $totalCandidates ?></div>
                <div class="stat-label">All Candidates</div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon icon-teal" aria-hidden="true">✓</div>
            <div>
                <div class="stat-number"><?= $activeCandidates ?></div>
                <div class="stat-label">Active Candidates</div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon icon-purple" aria-hidden="true">—</div>
            <div>
                <div class="stat-number"><?= $inactiveCandidates ?></div>
                <div class="stat-label">Inactive or Other Status</div>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Registered Candidates</h2>
                <p>Search and filter the candidates shown below.</p>
            </div>
            <span class="result-count" id="visibleCount">
                <?= $totalCandidates ?> candidates
            </span>
        </div>

        <div class="filters" aria-label="Candidate filters">
            <div class="field search-field">
                <label for="searchInput">Search candidates</label>
                <input
                    type="search"
                    id="searchInput"
                    placeholder="Name, student ID or department..."
                    autocomplete="off"
                >
            </div>

            <div class="field">
                <label for="positionFilter">Filter by position</label>
                <select id="positionFilter">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $position): ?>
                        <option value="<?= escapeHtml(mb_strtolower($position["position_name"] ?? "")) ?>">
                            <?= escapeHtml($position["position_name"] ?? "Unknown position") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="electionFilter">Filter by election</label>
                <select id="electionFilter">
                    <option value="">All Elections</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?= (int)$election["id"] ?>">
                            <?= escapeHtml($election["election_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="candidate-grid" id="candidateGrid">
            <?php if (empty($candidates)): ?>
                <div class="empty-state">
                    <h3>No candidates registered yet</h3>
                    <p>Add a candidate to begin building your election candidate list.</p>
                    <a href="add_candidate.php" class="primary-button">
                        + Add First Candidate
                    </a>
                </div>
            <?php else: ?>

                <?php foreach ($candidates as $candidate): ?>
                    <?php
                        $fullName = $candidate["full_name"] ?? "Unnamed Candidate";
                        $studentId = $candidate["student_id"] ?? "";
                        $department = $candidate["department"] ?? "";
                        $positionName = $candidate["position_name"] ?? "Position not linked";
                        $electionName = $candidate["election_name"] ?? "Election not linked";
                        $photo = basename((string)($candidate["photo"] ?? ""));
                        $status = strtolower((string)($candidate["status"] ?? ""));
                        $searchText = mb_strtolower(
                            $fullName . " " .
                            $studentId . " " .
                            $department . " " .
                            $positionName . " " .
                            $electionName
                        );
                    ?>

                    <article
                        class="candidate-card"
                        data-election="<?= (int)($candidate["election_id"] ?? 0) ?>"
                        data-position="<?= escapeHtml(mb_strtolower($positionName)) ?>"
                        data-search="<?= escapeHtml($searchText) ?>"
                    >
                        <div class="candidate-photo">
                            <?php if ($photo !== ""): ?>
                                <img
                                    src="../uploads/candidates/<?= rawurlencode($photo) ?>"
                                    alt="Photo of <?= escapeHtml($fullName) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >
                                <div class="no-photo" style="display:none;">
                                    <svg viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.7"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    </svg>
                                    Photo unavailable
                                </div>
                            <?php else: ?>
                                <div class="no-photo">
                                    <svg viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.7"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    </svg>
                                    No photo available
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="candidate-details">
                            <div class="badges">
                                <span class="badge">
                                    <?= escapeHtml($electionName) ?>
                                </span>
                                <span class="badge badge-position">
                                    <?= escapeHtml($positionName) ?>
                                </span>
                            </div>

                            <h3 class="candidate-name">
                                <?= escapeHtml($fullName) ?>
                            </h3>

                            <div class="candidate-info">
                                <div class="info-row">
                                    <strong>Student ID</strong>
                                    <span><?= escapeHtml($studentId !== "" ? $studentId : "Not provided") ?></span>
                                </div>
                                <div class="info-row">
                                    <strong>Department</strong>
                                    <span><?= escapeHtml($department !== "" ? $department : "Not provided") ?></span>
                                </div>
                            </div>

                            <?php if (!empty($candidate["manifesto"])): ?>
                                <div class="manifesto">
                                    <span class="manifesto-label">Manifesto</span>
                                    <?= nl2br(escapeHtml(
                                        mb_strimwidth(
                                            (string)$candidate["manifesto"],
                                            0,
                                            120,
                                            "..."
                                        )
                                    )) ?>
                                </div>
                            <?php endif; ?>

                            <div class="status-row">
                                <?php if ($status === "active"): ?>
                                    <span class="status status-active">Active</span>
                                <?php else: ?>
                                    <span class="status status-inactive">
                                        <?= escapeHtml($status !== "" ? $status : "Unknown") ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-actions">
                                <a
                                    href="edit_candidate.php?id=<?= (int)$candidate["id"] ?>"
                                    class="action-button edit-button"
                                >
                                    Edit Candidate
                                </a>

                                <a
                                    href="delete_candidate.php?id=<?= (int)$candidate["id"] ?>"
                                    class="action-button remove-button"
                                    onclick="return confirm('Are you sure you want to remove this candidate?');"
                                >
                                    Remove
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

                <div class="empty-filter" id="emptyFilter">
                    <p>No candidates match your search or filters.</p>
                    <p>Try another search or clear the filters.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-footer">
            Candidate information is displayed from the current database records.
        </div>
    </section>

    <a href="dashboard.php" class="back-link">
        <span aria-hidden="true">←</span>
        Back to Dashboard
    </a>

    <footer class="site-footer">
        Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
    </footer>
</main>

<script>
const searchInput = document.getElementById("searchInput");
const positionFilter = document.getElementById("positionFilter");
const electionFilter = document.getElementById("electionFilter");
const cards = document.querySelectorAll(".candidate-card");
const emptyFilter = document.getElementById("emptyFilter");
const visibleCount = document.getElementById("visibleCount");

function filterCandidates() {
    const searchValue = searchInput.value.toLowerCase().trim();
    const positionValue = positionFilter.value;
    const electionValue = electionFilter.value;
    let count = 0;

    cards.forEach(card => {
        const matchesSearch =
            (card.dataset.search || "").includes(searchValue);

        const matchesPosition =
            positionValue === "" ||
            card.dataset.position === positionValue;

        const matchesElection =
            electionValue === "" ||
            card.dataset.election === electionValue;

        const visible =
            matchesSearch && matchesPosition && matchesElection;

        card.style.display = visible ? "" : "none";

        if (visible) {
            count++;
        }
    });

    if (visibleCount) {
        visibleCount.textContent =
            count + (count === 1 ? " candidate" : " candidates");
    }

    if (emptyFilter) {
        emptyFilter.style.display =
            cards.length > 0 && count === 0 ? "block" : "none";
    }
}

searchInput.addEventListener("input", filterCandidates);
positionFilter.addEventListener("change", filterCandidates);
electionFilter.addEventListener("change", filterCandidates);
</script>
</body>
</html>
