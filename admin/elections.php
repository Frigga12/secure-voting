
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

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escapeHtml($value): string
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

$stmt = $pdo->query("
    SELECT *
    FROM elections
    ORDER BY created_at DESC
");
$elections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalElections = count($elections);
$activeElections = 0;
$draftElections = 0;
$endedElections = 0;

foreach ($elections as $election) {
    $status = strtolower((string)($election["status"] ?? ""));

    if ($status === "active") {
        $activeElections++;
    } elseif ($status === "draft") {
        $draftElections++;
    } elseif ($status === "ended") {
        $endedElections++;
    }
}

$adminName = trim((string)($_SESSION["full_name"] ?? "Administrator"));
$adminInitial = strtoupper(substr($adminName !== "" ? $adminName : "A", 0, 1));
$deleteStatus = $_GET["delete"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Management | Secure Voting System</title>

    <style>
        :root {
            --navy: #142b4a;
            --blue: #2563eb;
            --teal: #0d9488;
            --background: #edf3fb;
            --white: #fff;
            --text: #182b45;
            --muted: #687991;
            --border: #dce5f0;
            --shadow: 0 8px 25px rgba(20,43,74,.07);
            --green-bg: #ddf7ec;
            --green: #087a55;
            --orange-bg: #fff0dc;
            --orange: #a85a08;
            --grey-bg: #e9eef5;
            --grey: #53647a;
            --red-bg: #fee2e2;
            --red: #b91c1c;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.5;
        }

        .topbar {
            background: var(--navy);
            color: white;
            padding: 15px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 4px 16px rgba(20,43,74,.16);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
            font-size: 19px;
            font-weight: 800;
        }

        .brand-title { font-size: 15px; font-weight: 750; }
        .brand-subtitle { margin-top: 2px; font-size: 12px; color: #c7d7eb; }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .admin-label { color: #d7e4f5; font-size: 13px; }

        .avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #d9f4f0;
            color: #087e75;
            font-weight: 800;
        }

        .logout {
            text-decoration: none;
            color: white;
            border: 1px solid #59718f;
            padding: 9px 13px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 650;
        }

        .logout:hover { background: #274567; }

        .page {
            width: min(1440px,100%);
            margin: auto;
            padding: 30px 28px 42px;
        }

        .breadcrumbs {
            display: flex;
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
            font-size: clamp(25px,3vw,32px);
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
            gap: 8px;
            padding: 12px 17px;
            border: 0;
            border-radius: 10px;
            background: var(--blue);
            color: white;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: 0 5px 12px rgba(37,99,235,.18);
        }

        .primary-button:hover { background: #1d4ed8; }

        .notice {
            padding: 14px 16px;
            margin-bottom: 20px;
            border: 1px solid;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        .notice-success { background: var(--green-bg); border-color: #a7e8cf; color: var(--green); }
        .notice-warning { background: var(--orange-bg); border-color: #ffd9a1; color: var(--orange); }
        .notice-error { background: var(--red-bg); border-color: #fecaca; color: var(--red); }

        .welcome-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 25px 28px;
            margin-bottom: 23px;
            border-radius: 17px;
            color: white;
            background: linear-gradient(115deg,#142b4a 0%,#1d4775 62%,#0d9488 130%);
            box-shadow: 0 10px 25px rgba(20,43,74,.12);
        }

        .welcome-banner h2 { font-size: 19px; margin: 0 0 5px; }
        .welcome-banner p { color: #d5e6f7; font-size: 13px; max-width: 650px; }

        .banner-count {
            flex-shrink: 0;
            text-align: center;
            padding: 12px 20px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 13px;
            background: rgba(255,255,255,.09);
        }

        .banner-count strong { display: block; font-size: 25px; line-height: 1.2; }
        .banner-count span { display: block; margin-top: 4px; font-size: 11px; color: #d5e6f7; }

        .stats {
            display: grid;
            grid-template-columns: repeat(4,minmax(0,1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
            box-shadow: var(--shadow);
        }

        .stat-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            font-size: 20px;
            font-weight: 800;
        }

        .icon-blue { background: #e5eeff; color: #2563eb; }
        .icon-green { background: #ddf7ec; color: #087a55; }
        .icon-orange { background: #fff0dc; color: #a85a08; }
        .icon-grey { background: #e9eef5; color: #53647a; }

        .stat-number { font-size: 24px; line-height: 1.2; color: var(--navy); font-weight: 800; }
        .stat-label { margin-top: 4px; color: var(--muted); font-size: 12px; }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin: 27px 0 17px;
        }

        .section-heading h2 { color: var(--navy); font-size: 19px; }
        .section-heading p { margin-top: 4px; color: var(--muted); font-size: 12px; }

        .section-count {
            flex-shrink: 0;
            background: #e0eafe;
            color: #2457b5;
            padding: 7px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 750;
        }

        .election-grid {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 20px;
        }

        .election-card {
            min-width: 0;
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow);
            transition: transform .2s, box-shadow .2s;
        }

        .election-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 27px rgba(20,43,74,.1);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 17px;
        }

        .card-mark {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            margin-bottom: 13px;
            border-radius: 12px;
            background: #e5eeff;
            color: #2457b5;
            font-size: 18px;
            font-weight: 800;
        }

        .election-card h3 {
            color: var(--navy);
            font-size: 19px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            padding: 7px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .status-active { background: var(--green-bg); color: var(--green); }
        .status-draft { background: var(--orange-bg); color: var(--orange); }
        .status-ended { background: var(--grey-bg); color: var(--grey); }
        .status-other { background: #e5eeff; color: #2457b5; }

        .description {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
            margin-bottom: 18px;
            overflow-wrap: anywhere;
        }

        .dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 19px;
        }

        .date-box {
            min-width: 0;
            padding: 13px;
            border: 1px solid #e5ecf5;
            border-radius: 10px;
            background: #f7f9fd;
        }

        .date-label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .date-value {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
            padding-top: 16px;
            border-top: 1px solid #e8eef6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 13px;
            border: 0;
            border-radius: 9px;
            font: inherit;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s;
        }

        .edit { background: #e5eeff; color: #2457b5; }
        .edit:hover { background: #d5e4ff; }
        .activate { background: #ddf7ec; color: #087a55; }
        .activate:hover { background: #c7f0df; }
        .end { background: #fff0dc; color: #a85a08; }
        .end:hover { background: #ffe3bd; }
        .delete { background: #fee2e2; color: #b91c1c; }
        .delete:hover { background: #fecaca; }

        .delete-form { display: inline-flex; margin: 0; }

        .empty {
            padding: 55px 25px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: var(--shadow);
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 17px;
            border-radius: 16px;
            background: #e5eeff;
            color: var(--blue);
            font-size: 25px;
            font-weight: 800;
        }

        .empty h3 { color: var(--navy); font-size: 20px; margin-bottom: 8px; }
        .empty p { max-width: 440px; margin: 0 auto; color: var(--muted); font-size: 13px; line-height: 1.8; }
        .empty .primary-button { margin-top: 20px; }

        .back-link {
            display: inline-flex;
            gap: 8px;
            margin-top: 25px;
            color: var(--blue);
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
        }

        .back-link:hover { text-decoration: underline; }

        .site-footer {
            margin-top: 35px;
            padding-top: 18px;
            border-top: 1px solid #d8e3f0;
            color: #72839a;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 1000px) {
            .stats { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .election-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .topbar { padding: 13px 15px; }
            .brand-title { font-size: 13px; }
            .brand-subtitle, .admin-label { display: none; }
            .topbar-right { gap: 8px; }
            .logout { padding: 8px 10px; font-size: 12px; }
            .page { padding: 22px 14px 30px; }
            .page-heading { align-items: flex-start; flex-direction: column; }
            .page-heading .primary-button { width: 100%; }
            .welcome-banner { align-items: flex-start; flex-direction: column; padding: 21px; }
            .banner-count { display: flex; align-items: center; gap: 10px; text-align: left; }
            .banner-count span { margin-top: 0; }
            .stats { gap: 10px; }
            .stat-card { padding: 13px; gap: 9px; }
            .stat-icon { width: 37px; height: 37px; }
            .stat-number { font-size: 21px; }
            .stat-label { font-size: 11px; }
            .election-card { padding: 17px; }
            .card-top { gap: 9px; }
            .election-card h3 { font-size: 17px; }
            .dates { grid-template-columns: 1fr; }
            .actions .btn { flex: 1; }
            .delete-form { flex: 1; }
            .delete-form .btn { width: 100%; }
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
        <span>Election Management</span>
    </div>

    <section class="page-heading">
        <div>
            <div class="eyebrow">Election administration</div>
            <h1>Election Management</h1>
            <p class="heading-description">
                Create elections, review schedules and manage election status.
            </p>
        </div>

        <a href="add_election.php" class="primary-button">
            <span aria-hidden="true">+</span> Create Election
        </a>
    </section>

    <?php if ($deleteStatus === "success"): ?>
        <div class="notice notice-success" role="status">
            The election was permanently deleted. Its candidate records were removed.
        </div>
    <?php elseif ($deleteStatus === "has_votes"): ?>
        <div class="notice notice-warning" role="alert">
            This election has voting records and cannot be deleted. The results and votes have been preserved.
        </div>
    <?php elseif ($deleteStatus === "active"): ?>
        <div class="notice notice-warning" role="alert">
            An active election cannot be deleted. End it first, then try again if it has no votes.
        </div>
    <?php elseif ($deleteStatus === "failed"): ?>
        <div class="notice notice-error" role="alert">
            The election could not be deleted. The database prevented the operation. No successful deletion was recorded.
        </div>
    <?php elseif ($deleteStatus === "not_found"): ?>
        <div class="notice notice-error" role="alert">
            The selected election could not be found.
        </div>
    <?php elseif ($deleteStatus === "invalid"): ?>
        <div class="notice notice-error" role="alert">
            The request could not be verified. Refresh the page and try again.
        </div>
    <?php endif; ?>

    <section class="welcome-banner">
        <div>
            <h2>Manage your election schedule</h2>
            <p>
                Review election dates and current statuses. Permanently remove only
                unused elections that have no recorded votes.
            </p>
        </div>

        <div class="banner-count">
            <strong><?= $totalElections ?></strong>
            <span>Total elections</span>
        </div>
    </section>

    <section class="stats" aria-label="Election statistics">
        <article class="stat-card">
            <div class="stat-icon icon-blue">E</div>
            <div>
                <div class="stat-number"><?= $totalElections ?></div>
                <div class="stat-label">All Elections</div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon icon-green">✓</div>
            <div>
                <div class="stat-number"><?= $activeElections ?></div>
                <div class="stat-label">Active</div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon icon-orange">◷</div>
            <div>
                <div class="stat-number"><?= $draftElections ?></div>
                <div class="stat-label">Draft</div>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon icon-grey">✓</div>
            <div>
                <div class="stat-number"><?= $endedElections ?></div>
                <div class="stat-label">Ended</div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <h2>All Elections</h2>
                <p>Review and manage each election below.</p>
            </div>
            <span class="section-count"><?= $totalElections ?> records</span>
        </div>

        <?php if (!$elections): ?>
            <div class="empty">
                <div class="empty-icon">+</div>
                <h3>No election created yet</h3>
                <p>Create your first election to begin configuring the voting process.</p>
                <a href="add_election.php" class="primary-button">Create First Election</a>
            </div>
        <?php else: ?>
            <div class="election-grid">
                <?php foreach ($elections as $election): ?>
                    <?php
                    $status = strtolower((string)($election["status"] ?? ""));
                    $statusClass = match ($status) {
                        "active" => "status-active",
                        "draft" => "status-draft",
                        "ended" => "status-ended",
                        default => "status-other"
                    };
                    ?>

                    <article class="election-card">
                        <div class="card-top">
                            <div>
                                <div class="card-mark">E</div>
                                <h3><?= escapeHtml($election["election_name"] ?? "Untitled Election") ?></h3>
                            </div>

                            <span class="status <?= $statusClass ?>">
                                <?= escapeHtml($status !== "" ? $status : "Unknown") ?>
                            </span>
                        </div>

                        <?php if (!empty($election["description"])): ?>
                            <div class="description">
                                <?= nl2br(escapeHtml($election["description"])) ?>
                            </div>
                        <?php endif; ?>

                        <div class="dates">
                            <div class="date-box">
                                <span class="date-label">Starts</span>
                                <span class="date-value">
                                    <?= escapeHtml(formatElectionDate($election["start_date"] ?? null)) ?>
                                </span>
                            </div>

                            <div class="date-box">
                                <span class="date-label">Ends</span>
                                <span class="date-value">
                                    <?= escapeHtml(formatElectionDate($election["end_date"] ?? null)) ?>
                                </span>
                            </div>
                        </div>

                        <div class="actions">
                            <a
                                href="edit_election.php?id=<?= (int)$election["id"] ?>"
                                class="btn edit"
                            >Edit Election</a>

                            <?php if ($status === "draft"): ?>
                                <a
                                    href="toggle_election.php?id=<?= (int)$election["id"] ?>&amp;action=activate"
                                    class="btn activate"
                                    onclick="return confirm('Activate this election? Please verify its dates and settings first.');"
                                >Activate</a>
                            <?php elseif ($status === "active"): ?>
                                <a
                                    href="toggle_election.php?id=<?= (int)$election["id"] ?>&amp;action=end"
                                    class="btn end"
                                    onclick="return confirm('Are you sure you want to end this election?');"
                                >End Election</a>
                            <?php endif; ?>

                            <?php if (in_array($status, ["draft", "ended"], true)): ?>
                                <form
                                    action="delete_election.php"
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="return confirm('PERMANENT DELETION\n\nThis will permanently remove this election and its associated candidates. Elections with votes cannot be deleted. This action cannot be undone. Continue?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="election_id"
                                        value="<?= (int)$election["id"] ?>"
                                    >
                                    <button type="submit" class="btn delete">
                                        Delete Permanently
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <a href="dashboard.php" class="back-link">
        <span aria-hidden="true">←</span> Back to Dashboard
    </a>

    <footer class="site-footer">
        Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
    </footer>
</main>
</body>
</html>
