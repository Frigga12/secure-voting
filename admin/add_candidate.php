
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

$stmt = $pdo->query("
    SELECT id, election_name, status
    FROM elections
    WHERE status IN ('draft', 'active')
    ORDER BY id DESC
");
$elections = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT id, position_name
    FROM positions
    WHERE status = 'active'
    ORDER BY display_order ASC
");
$positions = $stmt->fetchAll();

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Candidate | Secure Voting</title>

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
            --border: #dfe7f2;
            --shadow: 0 12px 32px rgba(20, 43, 74, .07);
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

        .topbar {
            min-height: 76px;
            padding: 14px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            background: var(--navy);
            color: white;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: rgba(255,255,255,.12);
            color: #7de4d5;
            font-size: 23px;
            font-weight: 800;
        }

        .brand-name {
            font-size: 17px;
            font-weight: 750;
        }

        .brand-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #c3d2e7;
        }

        .topbar-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 15px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 9px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .topbar-link:hover {
            background: rgba(255,255,255,.1);
        }

        .page {
            width: min(1050px, 100%);
            margin: auto;
            padding: 34px 22px 55px;
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

        .heading {
            margin-bottom: 25px;
        }

        .eyebrow {
            margin-bottom: 9px;
            color: var(--teal);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            color: var(--navy);
            font-size: clamp(27px, 4vw, 35px);
            letter-spacing: -.7px;
        }

        .heading p {
            margin: 10px 0 0;
            max-width: 620px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .form-card {
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 23px 28px;
            background: linear-gradient(110deg, #e3edff, #e5f8f4);
            border-bottom: 1px solid var(--border);
        }

        .card-heading-icon {
            width: 45px;
            height: 45px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: var(--navy);
            color: white;
            font-size: 25px;
        }

        .card-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 18px;
        }

        .card-heading p {
            margin: 5px 0 0;
            color: #61748d;
            font-size: 12px;
            line-height: 1.5;
        }

        .form-content {
            padding: 28px;
        }

        .section-label {
            margin: 0 0 19px;
            padding-bottom: 12px;
            border-bottom: 1px solid #edf1f7;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 20px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 9px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 750;
        }

        .required {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 47px;
            padding: 12px 14px;
            border: 1px solid #ced9e8;
            border-radius: 10px;
            outline: none;
            background: #fbfdff;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
            background: white;
        }

        input::placeholder,
        textarea::placeholder {
            color: #9aa8ba;
        }

        textarea {
            min-height: 145px;
            resize: vertical;
            line-height: 1.65;
        }

        .help-text {
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .photo-box {
            padding: 18px;
            border: 1px dashed #b8c9df;
            border-radius: 12px;
            background: #f7faff;
        }

        .photo-preview {
            display: none;
            width: 115px;
            height: 115px;
            margin-bottom: 14px;
            border: 3px solid white;
            border-radius: 14px;
            object-fit: cover;
            box-shadow: 0 3px 12px rgba(20,43,74,.12);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
            padding-top: 23px;
            border-top: 1px solid #edf1f7;
        }

        .form-note {
            max-width: 350px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 11px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer;
        }

        .submit-button {
            background: var(--navy);
            color: white;
        }

        .submit-button:hover {
            background: var(--navy-light);
        }

        .cancel-button {
            border: 1px solid var(--border);
            background: white;
            color: var(--navy);
        }

        .cancel-button:hover {
            background: #f2f6fc;
        }

        .empty-options {
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid #f5d99a;
            border-radius: 10px;
            background: #fffbeb;
            color: #92400e;
            font-size: 13px;
            line-height: 1.6;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dce5f0;
            color: #8391a6;
            text-align: center;
            font-size: 12px;
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 14px 18px;
            }

            .page {
                padding: 25px 14px 40px;
            }

            .card-heading {
                padding: 19px;
            }

            .form-content {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 19px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .action-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .button {
                padding: 12px 10px;
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

    <a href="candidates.php" class="topbar-link">
        &#8592; Back to Candidates
    </a>
</header>

<main class="page">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp; / &nbsp;
        <a href="candidates.php">Candidates</a>
        &nbsp; / &nbsp;
        Add Candidate
    </div>

    <div class="heading">
        <div class="eyebrow">Candidate management</div>
        <h1>Add Candidate</h1>
        <p>
            Register a candidate and assign them to an election and the
            position they intend to contest.
        </p>
    </div>

    <section class="form-card">
        <div class="card-heading">
            <div class="card-heading-icon" aria-hidden="true">+</div>
            <div>
                <h2>Candidate Information</h2>
                <p>Complete the details below before saving the candidate.</p>
            </div>
        </div>

        <div class="form-content">
            <?php if (empty($elections)): ?>
                <div class="empty-options">
                    No draft or active elections are available. Create an
                    election before adding a candidate.
                    <br>
                    <a href="elections.php">Go to election management</a>
                </div>
            <?php endif; ?>

            <?php if (empty($positions)): ?>
                <div class="empty-options">
                    No active positions are available. Activate or create
                    a position before adding a candidate.
                    <br>
                    <a href="positions.php">Go to position management</a>
                </div>
            <?php endif; ?>

            <form
                action="save_candidate.php"
                method="POST"
                enctype="multipart/form-data"
            >
                <h3 class="section-label">Election Assignment</h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="election_id">
                            Election <span class="required">*</span>
                        </label>

                        <select id="election_id" name="election_id" required>
                            <option value="">Select an election</option>

                            <?php foreach ($elections as $election): ?>
                                <option value="<?= (int)$election["id"] ?>">
                                    <?= e($election["election_name"]) ?>
                                    (<?= e(strtoupper($election["status"])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="help-text">
                            Select the election this candidate will contest in.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="position_id">
                            Election Position <span class="required">*</span>
                        </label>

                        <select id="position_id" name="position_id" required>
                            <option value="">Select a position</option>

                            <?php foreach ($positions as $position): ?>
                                <option value="<?= (int)$position["id"] ?>">
                                    <?= e($position["position_name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="help-text">
                            Choose the office the candidate is contesting for.
                        </div>
                    </div>
                </div>

                <h3 class="section-label" style="margin-top: 30px;">
                    Personal Information
                </h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">
                            Candidate Full Name <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter candidate full name"
                            maxlength="150"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="student_id">
                            Student ID <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            placeholder="Enter candidate student ID"
                            maxlength="30"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <label for="department">
                            Department <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="department"
                            name="department"
                            placeholder="e.g. Computer Engineering"
                            maxlength="150"
                            required
                        >
                    </div>
                </div>

                <h3 class="section-label" style="margin-top: 30px;">
                    Candidate Profile
                </h3>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="photo">Candidate Photo</label>

                        <div class="photo-box">
                            <img
                                id="photo_preview"
                                class="photo-preview"
                                alt="Candidate photo preview"
                            >

                            <input
                                type="file"
                                id="photo"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <div class="help-text">
                                Optional. Choose a JPG, PNG, or WebP image.
                                The server must validate the uploaded file.
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="manifesto">
                            Manifesto / Description
                        </label>

                        <textarea
                            id="manifesto"
                            name="manifesto"
                            placeholder="Enter the candidate's manifesto or short description..."
                        ></textarea>

                        <div class="help-text">
                            Describe the candidate's plans and priorities for
                            the position, if available.
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <div class="form-note">
                        Fields marked with <span class="required">*</span>
                        are required. Review the details before saving.
                    </div>

                    <div class="action-buttons">
                        <a href="candidates.php" class="button cancel-button">
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="button submit-button"
                            <?= empty($elections) || empty($positions) ? "disabled" : "" ?>
                        >
                            Save Candidate &nbsp; &#8594;
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <footer class="footer">
        Secure Voting System &copy; <?= date("Y") ?> · Administration Portal
    </footer>
</main>

<script>
const photoInput = document.getElementById("photo");
const photoPreview = document.getElementById("photo_preview");

photoInput.addEventListener("change", function () {
    const file = this.files && this.files[0];

    if (!file) {
        photoPreview.removeAttribute("src");
        photoPreview.style.display = "none";
        return;
    }

    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG, or WebP image.");
        this.value = "";
        photoPreview.removeAttribute("src");
        photoPreview.style.display = "none";
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        photoPreview.src = event.target.result;
        photoPreview.style.display = "block";
    };

    reader.readAsDataURL(file);
});
</script>
</body>
</html>
