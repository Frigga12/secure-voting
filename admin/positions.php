
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

function escapePosition($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$sql = "
    SELECT id, position_name, display_order, status
    FROM positions
    ORDER BY display_order ASC, id ASC
";

$stmt = $pdo->query($sql);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPositions = count($positions);
$activePositions = 0;
$inactivePositions = 0;

foreach ($positions as $position) {
    if (strtolower(trim((string)$position["status"])) === "active") {
        $activePositions++;
    } else {
        $inactivePositions++;
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
    <title>Manage Positions | Secure Voting System</title>

    <style>
        :root {
            --navy: #142b4a;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --teal: #0d9488;
            --background: #edf3fb;
            --white: #fff;
            --text: #182b45;
            --muted: #687991;
            --border: #dce5f0;
            --shadow: 0 8px 25px rgba(20,43,74,.07);
            --green-bg: #ddf7ec;
            --green: #087a55;
            --red-bg: #fee2e2;
            --red: #b91c1c;
            --grey-bg: #e9eef5;
            --grey: #53647a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        a { text-decoration: none; }

        .topbar {
            background: var(--navy);
            color: white;
            padding: 15px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 4px solid var(--teal);
            box-shadow: 0 5px 18px rgba(20,43,74,.14);
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
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
            color: white;
            font-size: 20px;
            font-weight: 800;
        }

        .brand-title {
            font-size: 15px;
            font-weight: 750;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            color: #c7d7eb;
            font-size: 12px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-label {
            color: #d7e4f5;
            font-size: 13px;
        }

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
            color: white;
            border: 1px solid #59718f;
            padding: 8px 12px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
        }

        .logout:hover { background: #274567; }

        .main {
            max-width: 1300px;
            margin: 0 auto;
            padding: 30px 25px 20px;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumbs a {
            color: var(--blue);
            font-weight: 650;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 23px;
        }

        .eyebrow {
            margin-bottom: 7px;
            color: var(--teal);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.3px;
        }

        h1 {
            color: var(--navy);
            font-size: clamp(25px,3vw,32px);
            line-height: 1.25;
            letter-spacing: -.5px;
        }

        .heading-description {
            margin-top: 8px;
            color: var(--muted);
            font-size: 14px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 15px;
            border-radius: 10px;
            background: var(--blue);
            color: white;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .back-button:hover { background: var(--blue-dark); }

        .welcome-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
            padding: 27px 29px;
            margin-bottom: 23px;
            border-radius: 18px;
            color: white;
            background: linear-gradient(115deg,#142b4a 0%,#2563eb 72%,#0d9488 130%);
            box-shadow: 0 12px 30px rgba(37,99,235,.14);
        }

        .welcome-content { min-width: 0; }

        .welcome-panel h2 {
            margin-bottom: 7px;
            font-size: 23px;
            line-height: 1.35;
        }

        .welcome-panel p {
            color: #e0ecff;
            font-size: 13px;
            max-width: 620px;
        }

        .welcome-icon {
            width: 74px;
            height: 74px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 20px;
            background: rgba(255,255,255,.12);
            font-size: 32px;
            font-weight: 800;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 16px;
            margin-bottom: 26px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 0;
            padding: 20px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(20,43,74,.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 13px;
            font-size: 22px;
            font-weight: 800;
        }

        .stat-blue .stat-icon { background: #dbeafe; color: #1d4ed8; }
        .stat-teal .stat-icon { background: #ccfbf1; color: #0f766e; }
        .stat-grey .stat-icon { background: #e9eef5; color: #53647a; }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .stat-value {
            margin-top: 3px;
            color: var(--navy);
            font-size: 27px;
            line-height: 1.3;
            font-weight: 800;
        }

        .content-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: white;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 25px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(100deg,#fff,#f1f7ff);
        }

        .card-header h2 {
            color: var(--navy);
            font-size: 18px;
            font-weight: 750;
        }

        .card-header p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .count-pill {
            flex-shrink: 0;
            padding: 7px 12px;
            border-radius: 9px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 750;
        }

        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 17px 22px;
            border-bottom: 1px solid #edf1f7;
        }

        .search-wrap {
            width: min(100%,370px);
            position: relative;
        }

        .search-wrap input {
            width: 100%;
            min-height: 42px;
            padding: 10px 13px 10px 39px;
            border: 1px solid #d5dfec;
            border-radius: 10px;
            background: #fbfdff;
            font: inherit;
            font-size: 13px;
            color: var(--text);
        }

        .search-wrap input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        .search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 16px;
            pointer-events: none;
        }

        .results-label {
            color: var(--muted);
            font-size: 12px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }

        thead { background: #f1f5fb; }

        th {
            padding: 15px 21px;
            text-align: left;
            color: #52647d;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .65px;
            font-weight: 800;
            white-space: nowrap;
        }

        td {
            padding: 17px 21px;
            border-bottom: 1px solid #edf1f7;
            color: #334155;
            font-size: 13px;
        }

        tbody tr { transition: background .18s; }
        tbody tr:hover { background: #f2f8ff; }
        tbody tr:last-child td { border-bottom: none; }

        .id-badge {
            display: inline-grid;
            place-items: center;
            min-width: 34px;
            min-height: 31px;
            padding: 4px 8px;
            border-radius: 9px;
            background: #e8efff;
            color: #3159b8;
            font-size: 12px;
            font-weight: 750;
        }

        .position-name {
            color: var(--navy);
            font-weight: 750;
        }

        .order-badge {
            display: inline-block;
            min-width: 32px;
            padding: 5px 9px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #475569;
            text-align: center;
            font-weight: 700;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 11px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-active { background: #d1fae5; color: #047857; }
        .status-inactive { background: var(--red-bg); color: var(--red); }
        .status-other { background: var(--grey-bg); color: var(--grey); }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            margin: 0 auto 16px;
            border-radius: 18px;
            background: #dbeafe;
            color: var(--blue);
            font-size: 27px;
            font-weight: 800;
        }

        .empty-state h3 {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 18px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 13px;
        }

        .no-results {
            display: none;
            padding: 24px;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .footer {
            padding: 22px 15px 30px;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 800px) {
            .main { padding: 25px 18px; }
            .stats-grid { gap: 12px; }
            .stat-card { padding: 16px; gap: 10px; }
            .stat-icon { width: 42px; height: 42px; }
            .stat-value { font-size: 24px; }
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 14px 16px;
                flex-wrap: wrap;
            }

            .brand-title { font-size: 14px; }
            .brand-subtitle, .admin-label { display: none; }
            .main { padding: 22px 14px; }

            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .back-button { width: 100%; }
            .welcome-panel { padding: 22px; }
            .welcome-panel h2 { font-size: 21px; }
            .welcome-icon { display: none; }
            .stats-grid { grid-template-columns: 1fr; gap: 10px; }
            .stat-card { padding: 17px; }
            .card-header { padding: 18px; }
            .table-toolbar { align-items: flex-start; flex-direction: column; padding: 15px; }
            .search-wrap { width: 100%; }
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
        <div class="brand-mark" aria-hidden="true">S</div>
        <div>
            <div class="brand-title">Secure Voting System</div>
            <div class="brand-subtitle">Administration Portal</div>
        </div>
    </div>

    <div class="topbar-right">
        <span class="admin-label">Administrator</span>
        <div class="avatar" title="<?= escapePosition($adminName) ?>">
            <?= escapePosition($adminInitial) ?>
        </div>
        <a href="../logout.php" class="logout">Log out</a>
    </div>
</header>

<main class="main">
    <div class="breadcrumbs">
        <a href="dashboard.php">Dashboard</a>
        <span>›</span>
        <span>Election Positions</span>
    </div>

    <section class="page-heading">
        <div>
            <div class="eyebrow">Election administration</div>
            <h1>Manage Positions</h1>
            <p class="heading-description">
                View the election positions, their display order and current status.
            </p>
        </div>

        <a href="dashboard.php" class="back-button">
            <span aria-hidden="true">←</span> Back to Dashboard
        </a>
    </section>

    <section class="welcome-panel">
        <div class="welcome-content">
            <h2>Election Positions</h2>
            <p>
                Manage your overview of available positions. Active positions can
                be selected when registering candidates.
            </p>
        </div>

        <div class="welcome-icon" aria-hidden="true">#</div>
    </section>

    <section class="stats-grid" aria-label="Position statistics">
        <article class="stat-card stat-blue">
            <div class="stat-icon" aria-hidden="true">#</div>
            <div>
                <div class="stat-label">Total Positions</div>
                <div class="stat-value"><?= $totalPositions ?></div>
            </div>
        </article>

        <article class="stat-card stat-teal">
            <div class="stat-icon" aria-hidden="true">✓</div>
            <div>
                <div class="stat-label">Active Positions</div>
                <div class="stat-value"><?= $activePositions ?></div>
            </div>
        </article>

        <article class="stat-card stat-grey">
            <div class="stat-icon" aria-hidden="true">–</div>
            <div>
                <div class="stat-label">Inactive / Other</div>
                <div class="stat-value"><?= $inactivePositions ?></div>
            </div>
        </article>
    </section>

    <section class="content-card">
        <div class="card-header">
            <div>
                <h2>Election Positions</h2>
                <p>Positions currently stored in your system.</p>
            </div>
            <span class="count-pill"><?= $totalPositions ?> total</span>
        </div>

        <?php if ($totalPositions === 0): ?>
            <div class="empty-state">
                <div class="empty-icon" aria-hidden="true">#</div>
                <h3>No positions available</h3>
                <p>No election positions have been created yet.</p>
            </div>
        <?php else: ?>
            <div class="table-toolbar">
                <div class="search-wrap">
                    <span class="search-icon" aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        id="positionSearch"
                        placeholder="Search by position, ID or status..."
                        aria-label="Search positions"
                    >
                </div>
                <span class="results-label" id="resultsLabel">
                    Showing <?= $totalPositions ?> positions
                </span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Position Name</th>
                            <th>Display Order</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody id="positionsTable">
                    <?php foreach ($positions as $position): ?>
                        <?php
                        $status = strtolower(trim((string)($position["status"] ?? "")));

                        if ($status === "active") {
                            $statusClass = "status-active";
                        } elseif (in_array($status, ["inactive", "disabled"], true)) {
                            $statusClass = "status-inactive";
                        } else {
                            $statusClass = "status-other";
                        }
                        ?>

                        <tr class="position-row">
                            <td>
                                <span class="id-badge">
                                    <?= escapePosition($position["id"]) ?>
                                </span>
                            </td>

                            <td class="position-name">
                                <?= escapePosition($position["position_name"]) ?>
                            </td>

                            <td>
                                <span class="order-badge">
                                    <?= escapePosition($position["display_order"]) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= escapePosition($position["status"]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="no-results" id="noResults">
                    No positions match your search.
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
</footer>

<script>
const positionSearch = document.getElementById("positionSearch");

if (positionSearch) {
    const rows = Array.from(document.querySelectorAll(".position-row"));
    const resultsLabel = document.getElementById("resultsLabel");
    const noResults = document.getElementById("noResults");

    function filterPositions() {
        const query = positionSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const matches = row.textContent.toLowerCase().includes(query);

            row.style.display = matches ? "" : "none";

            if (matches) {
                visibleCount++;
            }
        });

        resultsLabel.textContent =
            "Showing " + visibleCount + " of " + rows.length + " positions";

        noResults.style.display = visibleCount === 0 ? "block" : "none";
    }

    positionSearch.addEventListener("input", filterPositions);
}
</script>
</body>
</html>
