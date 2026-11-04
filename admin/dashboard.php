
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$adminName = htmlspecialchars(
    $_SESSION["full_name"] ?? "Administrator",
    ENT_QUOTES,
    "UTF-8"
);

$adminInitial = strtoupper(substr($_SESSION["full_name"] ?? "A", 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Secure Voting System</title>

    <style>
        :root {
            --navy: #142b4a;
            --navy-light: #203e63;
            --blue: #2563eb;
            --teal: #0d9488;
            --background: #edf3fb;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2eaf4;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .layout {
            min-height: 100vh;
        }

        .main {
            min-width: 0;
        }

        /* Shared navy administration header */
        .topbar {
            min-height: 82px;
            background: var(--navy);
            color: white;
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            border-bottom: 4px solid var(--teal);
            box-shadow: 0 5px 18px rgba(20, 43, 74, 0.12);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, #3b82f6, #0d9488);
            color: white;
            font-size: 23px;
            font-weight: 800;
        }

        .brand h1 {
            font-size: 19px;
            line-height: 1.3;
            font-weight: 750;
        }

        .brand p {
            font-size: 12px;
            color: #c5d8ee;
            margin-top: 3px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px 7px 7px;
            border: 1px solid rgba(255,255,255,.15);
            background: rgba(255,255,255,.09);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 650;
        }

        .avatar {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 11px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 15px;
            font-weight: 800;
        }

        .topbar-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 750;
            white-space: nowrap;
            transition: .2s ease;
        }

        .topbar-logout:hover {
            background: #be123c;
            border-color: #be123c;
            color: white;
            transform: translateY(-1px);
        }

        .content {
            width: 100%;
            max-width: 1380px;
            padding: 34px 30px;
            margin: 0 auto;
        }

        /* Colourful welcome banner */
        .welcome-banner {
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            padding: 34px;
            margin-bottom: 30px;
            border-radius: 20px;
            color: white;
            background: linear-gradient(115deg, #142b4a 0%, #2563eb 72%, #0d9488 135%);
            box-shadow: 0 12px 30px rgba(37, 99, 235, .16);
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            width: 250px;
            height: 250px;
            right: -70px;
            top: -130px;
            border: 38px solid rgba(255,255,255,.08);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-block;
            padding: 5px 11px;
            margin-bottom: 13px;
            border: 1px solid rgba(204,251,241,.25);
            border-radius: 30px;
            background: rgba(13,148,136,.3);
            color: #d7f8f4;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .welcome-banner h2 {
            font-size: clamp(24px, 3vw, 32px);
            line-height: 1.3;
            margin-bottom: 9px;
        }

        .welcome-banner p.description {
            max-width: 650px;
            color: #e0ecff;
            font-size: 14px;
            line-height: 1.8;
        }

        .welcome-art {
            position: relative;
            z-index: 1;
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.24);
            border-radius: 26px;
            background: rgba(255,255,255,.13);
        }

        .welcome-art svg {
            width: 53px;
            height: 53px;
        }

        /* Quick summary strip */
        .overview-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 34px;
        }

        .overview-item {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 0;
            padding: 19px 21px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 18px rgba(20,43,74,.04);
        }

        .overview-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 14px;
        }

        .overview-icon svg {
            width: 24px;
            height: 24px;
        }

        .overview-icon.blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .overview-icon.teal {
            background: #ccfbf1;
            color: #0f766e;
        }

        .overview-icon.purple {
            background: #ede9fe;
            color: #6d28d9;
        }

        .overview-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 650;
        }

        .overview-value {
            color: var(--navy);
            font-size: 16px;
            font-weight: 800;
            line-height: 1.4;
        }

        /* Section heading */
        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 19px;
        }

        .section-heading h2 {
            color: var(--navy);
            font-size: 22px;
            font-weight: 800;
        }

        .section-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }

        .section-label {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 9px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .4px;
        }

        /* Management cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 230px;
            padding: 24px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: white;
            box-shadow: 0 5px 18px rgba(20,43,74,.04);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .card::before {
            content: "";
            position: absolute;
            height: 4px;
            top: 0;
            left: 0;
            right: 0;
            background: var(--card-accent, #2563eb);
            opacity: .95;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: #c7d8f4;
            box-shadow: 0 14px 30px rgba(20,43,74,.09);
        }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 21px;
        }

        .card-icon {
            width: 53px;
            height: 53px;
            display: grid;
            place-items: center;
            border-radius: 15px;
        }

        .card-icon svg {
            width: 26px;
            height: 26px;
        }

        .card-icon.blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .card-icon.green {
            background: #d1fae5;
            color: #047857;
        }

        .card-icon.purple {
            background: #ede9fe;
            color: #6d28d9;
        }

        .card-icon.orange {
            background: #ffedd5;
            color: #c2410c;
        }

        .card-icon.teal {
            background: #ccfbf1;
            color: #0f766e;
        }

        .card-icon.cyan {
            background: #cffafe;
            color: #0e7490;
        }

        .arrow-icon {
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #f1f5f9;
            color: #64748b;
            transition: .2s ease;
        }

        .arrow-icon svg {
            width: 18px;
            height: 18px;
        }

        .card:hover .arrow-icon {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .card h3 {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 17px;
            font-weight: 800;
        }

        .card p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.75;
        }

        .card-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: auto;
            padding-top: 19px;
            color: var(--blue);
            font-size: 12px;
            font-weight: 800;
        }

        .card-action svg {
            width: 15px;
            height: 15px;
            transition: transform .2s ease;
        }

        .card:hover .card-action svg {
            transform: translateX(3px);
        }

        .footer {
            margin-top: 34px;
            padding: 21px 0 7px;
            border-top: 1px solid #dce5f1;
            color: #718096;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 1050px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .overview-strip {
                gap: 12px;
            }

            .overview-item {
                padding: 16px;
            }
        }

        @media (max-width: 700px) {
            .topbar {
                padding: 15px 18px;
                flex-wrap: wrap;
            }

            .topbar-actions {
                width: 100%;
                justify-content: space-between;
            }

            .content {
                padding: 23px 17px;
            }

            .welcome-banner {
                padding: 25px;
                margin-bottom: 23px;
            }

            .welcome-art {
                display: none;
            }

            .overview-strip {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 28px;
            }

            .overview-item {
                padding: 16px 18px;
            }

            .cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .card {
                min-height: 205px;
            }

            .section-heading {
                align-items: flex-start;
            }
        }

        @media (max-width: 420px) {
            .brand h1 {
                font-size: 16px;
            }

            .brand p {
                font-size: 11px;
            }

            .admin-badge {
                padding: 5px;
                gap: 7px;
            }

            .admin-name {
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .topbar-logout {
                padding: 9px 11px;
            }

            .welcome-banner {
                padding: 21px;
            }

            .welcome-banner h2 {
                font-size: 23px;
            }

            .section-heading h2 {
                font-size: 20px;
            }

            .section-label {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>
<div class="layout">
    <main class="main">

        <header class="topbar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">S</div>
                <div>
                    <h1>Secure Voting System</h1>
                    <p>Administration Portal</p>
                </div>
            </div>

            <div class="topbar-actions">
                <div class="admin-badge">
                    <div class="avatar"><?= $adminInitial ?></div>
                    <span class="admin-name"><?= $adminName ?></span>
                </div>

                <a href="../logout.php" class="topbar-logout" title="Logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         width="17" height="17" aria-hidden="true">
                        <path d="M10 17l5-5-5-5"/>
                        <path d="M15 12H3"/>
                        <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <div class="content">

            <section class="welcome-banner">
                <div class="welcome-content">
                    <span class="eyebrow">Election Administration</span>

                    <h2>Welcome back, <?= $adminName ?>!</h2>

                    <p class="description">
                        Manage voters, candidates, election positions and results
                        from your central administration dashboard.
                    </p>
                </div>

                <div class="welcome-art" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </div>
            </section>

            <section class="overview-strip" aria-label="Dashboard overview">

                <div class="overview-item">
                    <div class="overview-icon blue" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div>
                        <div class="overview-label">Voter Accounts</div>
                        <div class="overview-value">Voter management</div>
                    </div>
                </div>

                <div class="overview-item">
                    <div class="overview-icon teal" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v18"/>
                            <path d="M17 8H9.5a3.5 3.5 0 0 0 0 7H14a3 3 0 0 1 0 6H6"/>
                            <path d="M6 4h12"/>
                        </svg>
                    </div>
                    <div>
                        <div class="overview-label">Election Process</div>
                        <div class="overview-value">Manage elections</div>
                    </div>
                </div>

                <div class="overview-item">
                    <div class="overview-icon purple" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="m19 9-5 5-4-4-5 5"/>
                        </svg>
                    </div>
                    <div>
                        <div class="overview-label">Election Reporting</div>
                        <div class="overview-value">Review results</div>
                    </div>
                </div>

            </section>

            <section>
                <div class="section-heading">
                    <div>
                        <h2>System Management</h2>
                        <p>Choose a section to manage your voting system.</p>
                    </div>

                    <span class="section-label">ADMINISTRATION</span>
                </div>

                <div class="cards">

                    <a href="voters.php" class="card" style="--card-accent:#2563eb">
                        <div class="card-top">
                            <div class="card-icon blue" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <span class="arrow-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17 17 7M7 7h10v10"/>
                                </svg>
                            </span>
                        </div>

                        <h3>Voter Management</h3>
                        <p>Register voters, review voter details and manage account access.</p>

                        <span class="card-action">
                            Manage voters
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                    <a href="candidates.php" class="card" style="--card-accent:#059669">
                        <div class="card-top">
                            <div class="card-icon green" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="8" r="4"/>
                                    <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                                    <path d="M19 8h3M20.5 6.5v3"/>
                                </svg>
                            </div>
                            <span class="arrow-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17 17 7M7 7h10v10"/>
                                </svg>
                            </span>
                        </div>

                        <h3>Candidate Management</h3>
                        <p>Manage candidate profiles, photos, manifestos and election assignments.</p>

                        <span class="card-action">
                            Manage candidates
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                    <a href="positions.php" class="card" style="--card-accent:#7c3aed">
                        <div class="card-top">
                            <div class="card-icon purple" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                                    <path d="M8 7h8M8 12h8M8 17h5"/>
                                </svg>
                            </div>
                            <span class="arrow-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17 17 7M7 7h10v10"/>
                                </svg>
                            </span>
                        </div>

                        <h3>Election Positions</h3>
                        <p>View the positions available for candidates in your elections.</p>

                        <span class="card-action">
                            View positions
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                    <a href="elections.php" class="card" style="--card-accent:#ea580c">
                        <div class="card-top">
                            <div class="card-icon orange" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                                    <path d="m9 12 2 2 4-4"/>
                                </svg>
                            </div>
                            <span class="arrow-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17 17 7M7 7h10v10"/>
                                </svg>
                            </span>
                        </div>

                        <h3>Election Management</h3>
                        <p>Create elections, configure eligibility and manage election status.</p>

                        <span class="card-action">
                            Manage elections
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                    <a href="results.php" class="card" style="--card-accent:#0d9488">
                        <div class="card-top">
                            <div class="card-icon teal" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3v18h18"/>
                                    <path d="M18 17V9M13 17V5M8 17v-3"/>
                                </svg>
                            </div>
                            <span class="arrow-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17 17 7M7 7h10v10"/>
                                </svg>
                            </span>
                        </div>

                        <h3>Election Results</h3>
                        <p>Review vote counts and view results for individual elections.</p>

                        <span class="card-action">
                            View results
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                </div>
            </section>

            <footer class="footer">
                Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
            </footer>

        </div>
    </main>
</div>
</body>
</html>
