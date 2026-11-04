
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

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function datetimeLocal($date): string
{
    if (empty($date)) {
        return "";
    }

    $timestamp = strtotime((string)$date);

    return $timestamp === false ? "" : date("Y-m-d\TH:i", $timestamp);
}

$election_id = (int)($_GET["id"] ?? $_POST["election_id"] ?? 0);

if ($election_id <= 0) {
    header("Location: elections.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM elections
    WHERE id = :id
    LIMIT 1
");
$stmt->execute(["id" => $election_id]);
$election = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$election) {
    header("Location: elections.php");
    exit;
}

$error = "";
$election_name = (string)($election["election_name"] ?? "");
$description = (string)($election["description"] ?? "");
$start_date = datetimeLocal($election["start_date"] ?? null);
$end_date = datetimeLocal($election["end_date"] ?? null);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $election_name = trim($_POST["election_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $start_date = trim($_POST["start_date"] ?? "");
    $end_date = trim($_POST["end_date"] ?? "");

    if (
        $election_name === "" ||
        $start_date === "" ||
        $end_date === ""
    ) {
        $error = "Please complete the election name, start date and end date.";
    } else {
        $startObject = DateTime::createFromFormat("!Y-m-d\TH:i", $start_date);
        $startValid = $startObject &&
            $startObject->format("Y-m-d\TH:i") === $start_date;

        $endObject = DateTime::createFromFormat("!Y-m-d\TH:i", $end_date);
        $endValid = $endObject &&
            $endObject->format("Y-m-d\TH:i") === $end_date;

        if (!$startValid || !$endValid) {
            $error = "Please enter valid start and end dates.";
        } elseif ($endObject <= $startObject) {
            $error = "The election end date and time must be later than the start date and time.";
        } else {
            try {
                $update = $pdo->prepare("
                    UPDATE elections
                    SET
                        election_name = :election_name,
                        description = :description,
                        start_date = :start_date,
                        end_date = :end_date
                    WHERE id = :id
                ");

                $update->execute([
                    "election_name" => $election_name,
                    "description" => $description !== "" ? $description : null,
                    "start_date" => $startObject->format("Y-m-d H:i:s"),
                    "end_date" => $endObject->format("Y-m-d H:i:s"),
                    "id" => $election_id
                ]);

                header("Location: elections.php?updated=1");
                exit;
            } catch (PDOException $exception) {
                error_log("Election update failed: " . $exception->getMessage());
                $error = "The election could not be updated. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Election | Secure Voting</title>

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
            --border: #dbe5f1;
            --shadow: 0 12px 35px rgba(20,43,74,.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .topbar {
            background: var(--navy);
            color: white;
            padding: 17px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 4px 16px rgba(20,43,74,.12);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 750;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: white;
            background: linear-gradient(135deg,#3b82f6,#0d9488);
            font-size: 20px;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            color: #c7d7eb;
            font-size: 12px;
            font-weight: 400;
        }

        .top-link {
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
            padding: 10px 14px;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 9px;
        }

        .top-link:hover { background: rgba(255,255,255,.1); }

        .container {
            width: min(100% - 32px, 900px);
            margin: 32px auto 50px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--blue);
            font-weight: 650;
            text-decoration: none;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 9px;
            color: var(--navy);
            font-size: clamp(25px,4vw,32px);
            letter-spacing: -.5px;
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .heading-icon {
            width: 54px;
            height: 54px;
            flex: 0 0 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: #dce9ff;
            color: var(--blue);
            font-size: 25px;
        }

        .form-card {
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .card-heading {
            padding: 23px 28px;
            background: linear-gradient(110deg,#e3edff,#e2f6f2);
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 18px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .form-content { padding: 28px; }

        .notice-error {
            display: flex;
            gap: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
            background: #fff1f0;
            border: 1px solid #fecaca;
            border-radius: 11px;
            color: #b42318;
            font-size: 13px;
            line-height: 1.6;
        }

        .section-title {
            margin: 0 0 20px;
            padding-bottom: 12px;
            color: var(--navy);
            border-bottom: 1px solid var(--border);
            font-size: 15px;
            font-weight: 750;
        }

        .form-group { margin-bottom: 24px; }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .required { color: #dc2626; }

        input, textarea {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid #cbd8e8;
            border-radius: 10px;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 14px;
            transition: border-color .15s, box-shadow .15s;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
            line-height: 1.6;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }

        .help-text {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .dates {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 18px;
        }

        .date-card {
            min-width: 0;
            padding: 17px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #f7f9fd;
        }

        .date-card .form-group { margin: 0; }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 8px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 18px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, transform .15s;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #e8eef7;
            color: var(--navy);
        }

        .btn-secondary:hover { background: #dbe5f1; }

        .footer {
            margin-top: 24px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 650px) {
            .topbar { padding: 14px 16px; }
            .brand { font-size: 14px; }
            .brand-mark { width: 36px; height: 36px; }
            .brand-subtitle { font-size: 11px; }
            .top-link { padding: 9px 10px; font-size: 12px; }
            .container { margin-top: 24px; }
            .form-content { padding: 20px; }
            .card-heading { padding: 20px; }
            .dates { grid-template-columns: 1fr; gap: 0; }
            .page-heading { align-items: flex-start; }
            .actions { flex-direction: column-reverse; }
            .actions .btn { width: 100%; }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">S</div>
        <div>
            Secure Voting System
            <span class="brand-subtitle">Administration Portal</span>
        </div>
    </div>

    <a class="top-link" href="elections.php">← Back to Elections</a>
</header>

<main class="container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;/&nbsp;
        <a href="elections.php">Elections</a>
        &nbsp;/&nbsp;
        Edit Election
    </div>

    <div class="page-heading">
        <div>
            <h1>Edit Election</h1>
            <p>Update the election's name, description and scheduled dates.</p>
        </div>
        <div class="heading-icon" aria-hidden="true">✎</div>
    </div>

    <section class="form-card">
        <div class="card-heading">
            <h2>Election Information</h2>
            <p>Update the details below and save your changes. Required fields are marked with <span class="required">*</span>.</p>
        </div>

        <div class="form-content">
            <?php if ($error !== ""): ?>
                <div class="notice-error" role="alert">
                    <strong>!</strong>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="editElectionForm">
                <input type="hidden" name="election_id" value="<?= (int)$election_id ?>">

                <h3 class="section-title">Basic Information</h3>

                <div class="form-group">
                    <label for="election_name">Election Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="election_name"
                        name="election_name"
                        value="<?= e($election_name) ?>"
                        maxlength="200"
                        placeholder="e.g. NACOMES Election 2026"
                        required
                    >
                    <span class="help-text">Use a clear name that identifies this election.</span>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        placeholder="Enter a short description of the election..."
                    ><?= e($description) ?></textarea>
                    <span class="help-text">This field is optional.</span>
                </div>

                <h3 class="section-title">Election Schedule</h3>

                <div class="dates">
                    <div class="date-card">
                        <div class="form-group">
                            <label for="start_date">Start Date &amp; Time <span class="required">*</span></label>
                            <input
                                type="datetime-local"
                                id="start_date"
                                name="start_date"
                                value="<?= e($start_date) ?>"
                                required
                            >
                            <span class="help-text">When the election is scheduled to begin.</span>
                        </div>
                    </div>

                    <div class="date-card">
                        <div class="form-group">
                            <label for="end_date">End Date &amp; Time <span class="required">*</span></label>
                            <input
                                type="datetime-local"
                                id="end_date"
                                name="end_date"
                                value="<?= e($end_date) ?>"
                                required
                            >
                            <span class="help-text">Must be later than the start date and time.</span>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <a href="elections.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes &nbsp; →</button>
                </div>
            </form>
        </div>
    </section>

    <div class="footer">Secure Voting System · Administration Portal</div>
</main>

<script>
const editForm = document.getElementById("editElectionForm");
const startInput = document.getElementById("start_date");
const endInput = document.getElementById("end_date");

function updateEndMinimum() {
    if (startInput.value) {
        endInput.min = startInput.value;
    } else {
        endInput.removeAttribute("min");
    }
}

startInput.addEventListener("change", updateEndMinimum);

editForm.addEventListener("submit", function (event) {
    if (
        startInput.value &&
        endInput.value &&
        endInput.value <= startInput.value
    ) {
        event.preventDefault();
        alert("The election end date and time must be later than the start date and time.");
        endInput.focus();
    }
});

updateEndMinimum();
</script>
</body>
</html>
