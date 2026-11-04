
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Election | Secure Voting</title>

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
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 17px;
            font-weight: 750;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            background: var(--blue);
            border-radius: 12px;
            font-size: 20px;
        }

        .top-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
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
            text-decoration: none;
            font-weight: 650;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: var(--navy);
            font-size: clamp(25px, 4vw, 32px);
            margin: 0 0 9px;
        }

        .page-heading p {
            color: var(--muted);
            margin: 0;
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
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(20,43,74,.08);
        }

        .card-heading {
            background: linear-gradient(110deg, #e3edff, #e2f6f2);
            padding: 23px 28px;
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            color: var(--navy);
            font-size: 18px;
            margin: 0 0 7px;
        }

        .card-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
        }

        .form-content { padding: 28px; }

        .section-title {
            color: var(--navy);
            font-size: 15px;
            font-weight: 750;
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .form-group { margin-bottom: 23px; }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .required { color: #dc2626; }

        input, select, textarea {
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
            min-height: 125px;
            resize: vertical;
            line-height: 1.6;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }

        .help-text {
            display: block;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
            margin-top: 7px;
        }

        .eligibility-box {
            background: #f6f9fe;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .eligibility-box .section-title { margin-bottom: 17px; }

        .eligibility-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #e5f6f3;
            border: 1px solid #b8e9df;
            color: #11675f;
            padding: 13px 14px;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.6;
            margin-top: 14px;
        }

        .dates {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .date-card {
            background: #f8faff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
        }

        .date-card label { color: var(--navy); }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 24px;
            margin-top: 8px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 18px;
            border: none;
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
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 650px) {
            .topbar { padding: 14px 16px; }
            .brand { font-size: 14px; }
            .brand-mark { width: 36px; height: 36px; }
            .top-link { padding: 9px 10px; font-size: 12px; }
            .container { margin-top: 24px; }
            .form-content { padding: 20px; }
            .card-heading { padding: 20px; }
            .dates { grid-template-columns: 1fr; gap: 0; }
            .date-card { margin-bottom: 14px; }
            .actions { flex-direction: column-reverse; }
            .actions .btn { width: 100%; }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">✓</div>
        <span>Secure Voting <span style="font-weight:400;color:#cbd8e8;">/ Admin</span></span>
    </div>
    <a class="top-link" href="elections.php">← Back to Elections</a>
</header>

<main class="container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;/&nbsp;
        <a href="elections.php">Elections</a>
        &nbsp;/&nbsp;
        Create Election
    </div>

    <div class="page-heading">
        <div>
            <h1>Create Election</h1>
            <p>Configure a new election, choose eligible voters and set its schedule.</p>
        </div>
        <div class="heading-icon" aria-hidden="true">＋</div>
    </div>

    <section class="form-card">
        <div class="card-heading">
            <h2>Election Configuration</h2>
            <p>Complete the information below to register an election in the system.</p>
        </div>

        <div class="form-content">
            <form action="save_election.php" method="POST" id="electionForm">
                <h3 class="section-title">Basic Information</h3>

                <div class="form-group">
                    <label for="election_name">Election Name <span class="required">*</span></label>
                    <input
                        type="text"
                        name="election_name"
                        id="election_name"
                        placeholder="e.g. NACOMES Election 2026"
                        maxlength="200"
                        required
                    >
                    <span class="help-text">Use a clear name that identifies the election.</span>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea
                        name="description"
                        id="description"
                        placeholder="Enter a short description of the election..."
                    ></textarea>
                    <span class="help-text">Optional information about the election and its purpose.</span>
                </div>

                <div class="eligibility-box">
                    <h3 class="section-title">Voter Eligibility</h3>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="eligibility_type">Eligible Voters <span class="required">*</span></label>
                        <select
                            name="eligibility_type"
                            id="eligibility_type"
                            required
                        >
                            <option value="all">All Students</option>
                            <option value="department">Specific Department</option>
                            <option value="engineering">Engineering Students</option>
                        </select>
                        <span class="help-text">Choose which group of students should be eligible for this election.</span>
                    </div>

                    <div class="form-group" id="department-field" style="display:none; margin-top:20px; margin-bottom:0;">
                        <label for="eligibility_department">Department <span class="required">*</span></label>
                        <input
                            type="text"
                            name="eligibility_department"
                            id="eligibility_department"
                            placeholder="e.g. Computer Engineering"
                        >
                    </div>

                    <div class="eligibility-note">
                        <strong aria-hidden="true">i</strong>
                        <span>Eligibility selection only works as intended if the election-saving and voter-validation logic enforces the selected group.</span>
                    </div>
                </div>

                <h3 class="section-title">Election Schedule</h3>

                <div class="dates">
                    <div class="date-card">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="start_date">Start Date &amp; Time <span class="required">*</span></label>
                            <input
                                type="datetime-local"
                                name="start_date"
                                id="start_date"
                                required
                            >
                            <span class="help-text">When the election is scheduled to begin.</span>
                        </div>
                    </div>

                    <div class="date-card">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="end_date">End Date &amp; Time <span class="required">*</span></label>
                            <input
                                type="datetime-local"
                                name="end_date"
                                id="end_date"
                                required
                            >
                            <span class="help-text">When the election is scheduled to end.</span>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <a href="elections.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Election &nbsp; →</button>
                </div>
            </form>
        </div>
    </section>

    <div class="footer">Secure Voting System · Administration Portal</div>
</main>

<script>
const eligibilityType = document.getElementById("eligibility_type");
const departmentField = document.getElementById("department-field");
const departmentInput = document.getElementById("eligibility_department");
const startDate = document.getElementById("start_date");
const endDate = document.getElementById("end_date");
const electionForm = document.getElementById("electionForm");

function toggleDepartmentField() {
    const needsDepartment = eligibilityType.value === "department";

    departmentField.style.display = needsDepartment ? "block" : "none";
    departmentInput.required = needsDepartment;

    if (!needsDepartment) {
        departmentInput.value = "";
    }
}

eligibilityType.addEventListener("change", toggleDepartmentField);
toggleDepartmentField();

startDate.addEventListener("change", function () {
    if (startDate.value) {
        endDate.min = startDate.value;

        if (endDate.value && endDate.value <= startDate.value) {
            endDate.value = "";
        }
    }
});

electionForm.addEventListener("submit", function (event) {
    if (startDate.value && endDate.value && endDate.value <= startDate.value) {
        event.preventDefault();
        alert("The election end date and time must be later than the start date and time.");
        endDate.focus();
    }
});
</script>
</body>
</html>
