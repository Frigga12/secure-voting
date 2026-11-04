
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$candidate_id = (int)($_GET["id"] ?? 0);

if ($candidate_id <= 0) {
    header("Location: candidates.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    http_response_code(404);
    exit("Candidate not found.");
}

$positionStmt = $pdo->query("
    SELECT id, position_name
    FROM positions
    WHERE status = 'active'
    ORDER BY display_order ASC
");
$positions = $positionStmt->fetchAll();

$error = "";
$full_name = $candidate["full_name"] ?? "";
$student_id = $candidate["student_id"] ?? "";
$department = $candidate["department"] ?? "";
$position_id = (int)($candidate["position_id"] ?? 0);
$manifesto = $candidate["manifesto"] ?? "";
$status = $candidate["status"] ?? "active";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $student_id = trim($_POST["student_id"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $position_id = (int)($_POST["position_id"] ?? 0);
    $manifesto = trim($_POST["manifesto"] ?? "");
    $status = $_POST["status"] ?? "active";

    if (
        $full_name === "" ||
        $student_id === "" ||
        $department === "" ||
        $position_id <= 0
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($status, ["active", "inactive"], true)) {
        $error = "Please select a valid candidate status.";
    } else {
        $positionCheck = $pdo->prepare("
            SELECT id FROM positions
            WHERE id = :id AND status = 'active'
            LIMIT 1
        ");
        $positionCheck->execute(["id" => $position_id]);

        if (!$positionCheck->fetch()) {
            $error = "Please select an available position.";
        } else {
            $duplicate = $pdo->prepare("
                SELECT id
                FROM candidates
                WHERE student_id = :student_id
                  AND position_id = :position_id
                  AND id != :id
                LIMIT 1
            ");
            $duplicate->execute([
                "student_id" => $student_id,
                "position_id" => $position_id,
                "id" => $candidate_id
            ]);

            if ($duplicate->fetch()) {
                $error = "This candidate is already registered for this position.";
            } else {
                $photoName = $candidate["photo"] ?? "";
                $newPhotoPath = null;
                $oldPhotoPath = null;

                if (
                    isset($_FILES["photo"]) &&
                    $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
                ) {
                    if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
                        $error = "There was a problem uploading the photo.";
                    } else {
                        $uploadDirectory = dirname(__DIR__) . "/uploads/candidates/";

                        if (!is_dir($uploadDirectory) &&
                            !mkdir($uploadDirectory, 0755, true) &&
                            !is_dir($uploadDirectory)) {
                            $error = "The candidate photo folder could not be created.";
                        } else {
                            $fileSize = (int)$_FILES["photo"]["size"];
                            $tmpName = $_FILES["photo"]["tmp_name"];

                            $imageInfo = @getimagesize($tmpName);
                            $fileType = function_exists("mime_content_type")
                                ? mime_content_type($tmpName)
                                : "";

                            $allowedTypes = [
                                "image/jpeg" => "jpg",
                                "image/png"  => "png",
                                "image/webp" => "webp"
                            ];

                            if (
                                !$imageInfo ||
                                !isset($allowedTypes[$fileType])
                            ) {
                                $error = "Only valid JPG, PNG and WebP images are allowed.";
                            } elseif ($fileSize > 5 * 1024 * 1024) {
                                $error = "Photo must not exceed 5MB.";
                            } else {
                                $extension = $allowedTypes[$fileType];
                                $newPhotoName = "candidate_" .
                                    time() . "_" .
                                    bin2hex(random_bytes(5)) . "." . $extension;

                                $destination = $uploadDirectory . $newPhotoName;

                                if (move_uploaded_file($tmpName, $destination)) {
                                    $newPhotoPath = $destination;
                                    $photoName = $newPhotoName;

                                    if (!empty($candidate["photo"])) {
                                        $oldPhotoPath = $uploadDirectory .
                                            basename($candidate["photo"]);
                                    }
                                } else {
                                    $error = "Unable to save the new candidate photo.";
                                }
                            }
                        }
                    }
                }

                if ($error === "") {
                    try {
                        $update = $pdo->prepare("
                            UPDATE candidates
                            SET full_name = :full_name,
                                student_id = :student_id,
                                department = :department,
                                position_id = :position_id,
                                photo = :photo,
                                manifesto = :manifesto,
                                status = :status
                            WHERE id = :id
                        ");

                        $update->execute([
                            "full_name" => $full_name,
                            "student_id" => $student_id,
                            "department" => $department,
                            "position_id" => $position_id,
                            "photo" => $photoName,
                            "manifesto" => $manifesto,
                            "status" => $status,
                            "id" => $candidate_id
                        ]);

                        // Remove the old image only after the database update succeeds.
                        if (
                            $newPhotoPath !== null &&
                            $oldPhotoPath !== null &&
                            is_file($oldPhotoPath)
                        ) {
                            @unlink($oldPhotoPath);
                        }

                        header("Location: candidates.php?updated=1");
                        exit;
                    } catch (PDOException $exception) {
                        // Keep the existing image if the update fails.
                        if ($newPhotoPath !== null && is_file($newPhotoPath)) {
                            @unlink($newPhotoPath);
                        }

                        $error = "The candidate could not be updated. Please check the details and try again.";
                    }
                }
            }
        }
    }
}

$currentPhoto = !empty($candidate["photo"])
    ? "../uploads/candidates/" . rawurlencode(basename($candidate["photo"]))
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Candidate | Secure Voting</title>

    <style>
        :root {
            --navy: #142b4a;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --teal: #0d9488;
            --bg: #edf3fb;
            --white: #ffffff;
            --text: #182b45;
            --muted: #64748b;
            --border: #dbe5f1;
            --danger: #b42318;
            --danger-bg: #fff1f0;
            --shadow: 0 12px 35px rgba(20, 43, 74, .08);
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
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--blue);
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
            width: min(100% - 32px, 1000px);
            margin: 32px auto 50px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 20px;
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
            box-shadow: var(--shadow);
        }

        .card-heading {
            background: linear-gradient(110deg, #e3edff, #e2f6f2);
            padding: 22px 28px;
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            margin: 0 0 6px;
            color: var(--navy);
            font-size: 18px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .form-content { padding: 28px; }

        .error {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 14px 16px;
            margin-bottom: 24px;
            border: 1px solid #fecaca;
            border-radius: 11px;
            background: var(--danger-bg);
            color: var(--danger);
            font-size: 14px;
            line-height: 1.5;
        }

        .section-title {
            font-size: 15px;
            font-weight: 750;
            color: var(--navy);
            padding-bottom: 12px;
            margin: 0 0 20px;
            border-bottom: 1px solid var(--border);
        }

        .photo-layout {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 28px;
            padding: 18px;
            border-radius: 14px;
            background: #f6f9fe;
            border: 1px solid var(--border);
        }

        .photo-frame {
            width: 130px;
            height: 155px;
            flex: 0 0 130px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 12px;
            background: #e1eaf6;
            color: var(--muted);
            text-align: center;
            font-size: 12px;
        }

        .photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }

        .photo-info { min-width: 0; }
        .photo-info h3 { margin: 0 0 8px; color: var(--navy); font-size: 15px; }
        .photo-info p { margin: 0 0 14px; color: var(--muted); font-size: 13px; line-height: 1.6; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 21px;
        }

        .form-group { margin-bottom: 21px; min-width: 0; }

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
            color: var(--text);
            background: white;
            border: 1px solid #cbd8e8;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            transition: border-color .15s, box-shadow .15s;
        }

        input[type="file"] {
            min-height: auto;
            background: white;
            padding: 10px;
        }

        textarea { min-height: 145px; resize: vertical; line-height: 1.6; }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .help-text {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 10px;
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

        .btn-primary { color: white; background: var(--blue); }
        .btn-primary:hover { background: var(--blue-dark); transform: translateY(-1px); }
        .btn-secondary { color: var(--navy); background: #e8eef7; }
        .btn-secondary:hover { background: #dbe5f1; }

        .footer {
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            margin-top: 24px;
        }

        @media (max-width: 650px) {
            .topbar { padding: 14px 16px; }
            .brand { font-size: 14px; }
            .brand-mark { width: 36px; height: 36px; }
            .top-link { font-size: 12px; padding: 9px 10px; }
            .container { margin-top: 24px; }
            .page-heading { align-items: center; }
            .form-content { padding: 20px; }
            .card-heading { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; gap: 0; }
            .photo-layout { align-items: flex-start; gap: 15px; padding: 13px; }
            .photo-frame { width: 105px; height: 130px; flex-basis: 105px; }
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
    <a class="top-link" href="candidates.php">← Back to Candidates</a>
</header>

<main class="container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        &nbsp;/&nbsp;
        <a href="candidates.php">Candidates</a>
        &nbsp;/&nbsp;
        Edit Candidate
    </div>

    <div class="page-heading">
        <div>
            <h1>Edit Candidate</h1>
            <p>Update candidate details, position, photograph and election status.</p>
        </div>
        <div class="heading-icon" aria-hidden="true">✎</div>
    </div>

    <section class="form-card">
        <div class="card-heading">
            <h2>Candidate Information</h2>
            <p>Fields marked with <span class="required">*</span> are required.</p>
        </div>

        <div class="form-content">
            <?php if ($error !== ""): ?>
                <div class="error" role="alert">
                    <strong>!</strong>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <h3 class="section-title">Candidate Photograph</h3>

                <div class="photo-layout">
                    <div class="photo-frame" id="photoFrame">
                        <?php if ($currentPhoto): ?>
                            <img id="photoPreview" src="<?= e($currentPhoto) ?>" alt="Current candidate photo">
                        <?php else: ?>
                            <span id="photoPlaceholder">No photo uploaded</span>
                            <img id="photoPreview" src="" alt="New candidate photo preview" hidden>
                        <?php endif; ?>
                    </div>

                    <div class="photo-info">
                        <h3>Candidate photo</h3>
                        <p>Choose a clear, recent photograph. JPG, PNG and WebP images up to 5MB are supported.</p>
                        <input
                            type="file"
                            name="photo"
                            id="photoInput"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >
                        <span class="help-text">Leave this field empty to keep the current photograph.</span>
                    </div>
                </div>

                <h3 class="section-title">Personal and Election Details</h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Candidate Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= e($full_name) ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="student_id">Student ID <span class="required">*</span></label>
                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            value="<?= e($student_id) ?>"
                            maxlength="50"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="department">Department <span class="required">*</span></label>
                        <input
                            type="text"
                            id="department"
                            name="department"
                            value="<?= e($department) ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="position_id">Position <span class="required">*</span></label>
                        <select id="position_id" name="position_id" required>
                            <option value="">Select a position</option>
                            <?php foreach ($positions as $position): ?>
                                <option
                                    value="<?= (int)$position["id"] ?>"
                                    <?= (int)$position["id"] === $position_id ? "selected" : "" ?>
                                >
                                    <?= e($position["position_name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($positions)): ?>
                            <span class="help-text">No active positions are available. Activate a position before editing.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="manifesto">Candidate Manifesto</label>
                    <textarea id="manifesto" name="manifesto" placeholder="Enter the candidate's manifesto..."><?= e($manifesto) ?></textarea>
                    <span class="help-text">Describe the candidate's plans and priorities for the position.</span>
                </div>

                <div class="form-group">
                    <label for="status">Candidate Status <span class="required">*</span></label>
                    <select id="status" name="status" required>
                        <option value="active" <?= $status === "active" ? "selected" : "" ?>>Active</option>
                        <option value="inactive" <?= $status === "inactive" ? "selected" : "" ?>>Inactive</option>
                    </select>
                    <span class="help-text">Inactive candidates will not appear in the active candidate list for voting.</span>
                </div>

                <div class="actions">
                    <a href="candidates.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes &nbsp; →</button>
                </div>
            </form>
        </div>
    </section>

    <div class="footer">Secure Voting System · Administration Portal</div>
</main>

<script>
const photoInput = document.getElementById("photoInput");
const photoPreview = document.getElementById("photoPreview");
const photoPlaceholder = document.getElementById("photoPlaceholder");

photoInput.addEventListener("change", function () {
    const file = this.files && this.files[0];

    if (!file) {
        return;
    }

    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG or WebP image.");
        this.value = "";
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("The photo must not exceed 5MB.");
        this.value = "";
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        photoPreview.src = event.target.result;
        photoPreview.hidden = false;

        if (photoPlaceholder) {
            photoPlaceholder.hidden = true;
        }
    };

    reader.readAsDataURL(file);
});
</script>

</body>
</html>
