
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$stmt = $pdo->query("
    SELECT id, student_id, full_name, department, level, status, created_at
    FROM users
    WHERE role = 'voter'
    ORDER BY created_at DESC
");
$voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Voter Management | Secure Voting</title>
<style>
*{box-sizing:border-box}
:root{--navy:#17365d;--blue:#2864b0;--bg:#f3f6fb;--border:#e3e9f2;--muted:#68778c}
body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:var(--bg);color:#1e293b}
a{text-decoration:none}
.topbar{background:var(--navy);color:white;padding:16px 5%;display:flex;justify-content:space-between;align-items:center;gap:16px}
.brand{font-size:20px;font-weight:750;letter-spacing:-.3px}
.topbar small{display:block;color:#dbe7f7;font-size:12px;font-weight:400;margin-top:3px}
.top-actions{display:flex;gap:10px;flex-wrap:wrap}
.top-actions a{padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700}
.back{border:1px solid #ffffff50;color:white}
.add{background:white;color:var(--navy)}
.container{max-width:1350px;width:92%;margin:32px auto 50px}
.heading{margin-bottom:24px}
.heading h1{margin:0 0 6px;color:#172d4b;font-size:28px}
.heading p{margin:0;color:var(--muted);font-size:14px}
.stats{display:flex;gap:14px;flex-wrap:wrap;margin:20px 0}
.stat{background:white;border:1px solid var(--border);border-radius:12px;padding:15px 20px;min-width:150px}
.stat span{display:block;color:var(--muted);font-size:12px}
.stat strong{display:block;font-size:23px;margin-top:4px;color:var(--navy)}
.panel{background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 5px 18px #1a2d4b08}
.toolbar{padding:18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--border)}
.toolbar h2{margin:0;font-size:17px;color:#172d4b}
.controls{display:flex;gap:10px;flex-wrap:wrap}
input,select{font:inherit;border:1px solid #cfd8e5;border-radius:8px;padding:10px 12px;background:white;color:#1e293b}
input{width:min(330px,70vw)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:920px}
th,td{padding:14px 16px;text-align:left;border-bottom:1px solid #edf0f5;font-size:13px}
th{background:#f8fafd;color:#52627a;font-size:11px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
th button{border:0;background:none;color:inherit;font:inherit;font-weight:800;cursor:pointer;padding:0;text-transform:inherit;letter-spacing:inherit}
tbody tr:hover{background:#f8fafd}
tbody tr:last-child td{border-bottom:0}
.name{font-weight:700;color:#1c3353}
.subtext{display:block;font-size:12px;color:var(--muted);margin-top:3px}
.badge{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:800}
.active{background:#e8f8ef;color:#167548}
.inactive{background:#fff0ee;color:#b42318}
.actions{display:flex;gap:7px;flex-wrap:wrap}
.action{border:0;border-radius:7px;padding:8px 10px;font-size:11px;font-weight:750;cursor:pointer;white-space:nowrap}
.deactivate{background:#fff0ee;color:#b42318}
.activate{background:#e8f8ef;color:#167548}
.reset{background:#eaf1fc;color:#2457a7}
.empty{padding:40px 20px;text-align:center;color:var(--muted)}
.pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:16px 18px;border-top:1px solid var(--border)}
.page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.page-buttons button{border:1px solid var(--border);background:white;border-radius:7px;padding:8px 11px;cursor:pointer;color:#344054}
.page-buttons button.current{background:var(--navy);color:white;border-color:var(--navy)}
.page-buttons button:disabled{opacity:.4;cursor:not-allowed}
#summary{font-size:12px;color:var(--muted)}
footer{text-align:center;padding:22px;color:#8490a1;font-size:12px}
@media(max-width:650px){.topbar{align-items:flex-start;flex-direction:column}.container{margin-top:24px}.heading h1{font-size:24px}.toolbar{align-items:flex-start;flex-direction:column}.controls{width:100%}input{width:100%}.pagination{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<header class="topbar">
    <div class="brand">✓ Secure Voting <small>Administration Portal</small></div>
    <nav class="top-actions">
        <a class="back" href="dashboard.php">← Dashboard</a>
        <a class="add" href="add_voter.php">＋ Register Voter</a>
    </nav>
</header>

<main class="container">
    <section class="heading">
        <h1>Voter Management</h1>
        <p>View and manage registered voter accounts.</p>
    </section>

    <?php
    $total = count($voters);
    $activeCount = 0;
    foreach ($voters as $v) {
        if (($v["status"] ?? "") === "active") $activeCount++;
    }
    ?>
    <section class="stats">
        <div class="stat"><span>Total voters</span><strong><?= $total ?></strong></div>
        <div class="stat"><span>Active accounts</span><strong><?= $activeCount ?></strong></div>
        <div class="stat"><span>Inactive accounts</span><strong><?= $total - $activeCount ?></strong></div>
    </section>

    <section class="panel">
        <div class="toolbar">
            <h2>Registered Voters</h2>
            <div class="controls">
                <input id="search" type="search" placeholder="Search name, ID, department..." aria-label="Search voters">
                <select id="pageSize" aria-label="Rows per page">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><button data-sort="student_id">Student ID ↕</button></th>
                        <th><button data-sort="full_name">Full name ↕</button></th>
                        <th><button data-sort="department">Department ↕</button></th>
                        <th><button data-sort="level">Level ↕</button></th>
                        <th><button data-sort="status">Status ↕</button></th>
                        <th><button data-sort="created_at">Date registered ↕</button></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="voterRows">
                <?php foreach ($voters as $voter): ?>
                    <tr
                        data-student_id="<?= e($voter["student_id"]) ?>"
                        data-full_name="<?= e($voter["full_name"]) ?>"
                        data-department="<?= e($voter["department"]) ?>"
                        data-level="<?= e($voter["level"]) ?>"
                        data-status="<?= e($voter["status"]) ?>"
                        data-created_at="<?= e($voter["created_at"]) ?>"
                    >
                        <td><?= e($voter["student_id"]) ?></td>
                        <td><span class="name"><?= e($voter["full_name"] ?: "Name not provided") ?></span></td>
                        <td><?= e($voter["department"] ?: "—") ?></td>
                        <td><?= e($voter["level"] ?: "—") ?></td>
                        <td>
                            <?php if (($voter["status"] ?? "") === "active"): ?>
                                <span class="badge active">Active</span>
                            <?php else: ?>
                                <span class="badge inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($voter["created_at"]) ?></td>
                        <td>
                            <div class="actions">
                            <?php if (($voter["status"] ?? "") === "active"): ?>
                                <a class="action deactivate" href="toggle_voter.php?id=<?= (int)$voter["id"] ?>&action=deactivate" onclick="return confirm('Deactivate this voter?')">Deactivate</a>
                            <?php else: ?>
                                <a class="action activate" href="toggle_voter.php?id=<?= (int)$voter["id"] ?>&action=activate" onclick="return confirm('Activate this voter?')">Activate</a>
                            <?php endif; ?>
                                <a class="action reset" href="reset_voter_password.php?id=<?= (int)$voter["id"] ?>" onclick="return confirm('Reset this voter’s password? A new temporary password will be generated.')">Reset password</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty" class="empty" hidden>No voters match your search.</div>
        </div>

        <div class="pagination">
            <span id="summary" aria-live="polite"></span>
            <div class="page-buttons" id="pageButtons"></div>
        </div>
    </section>
</main>

<footer>Secure Voting System &copy; <?= date("Y") ?> · Administration Portal</footer>

<script>
(() => {
    const tbody = document.getElementById("voterRows");
    const allRows = Array.from(tbody.querySelectorAll("tr"));
    const search = document.getElementById("search");
    const pageSize = document.getElementById("pageSize");
    const summary = document.getElementById("summary");
    const pageButtons = document.getElementById("pageButtons");
    const empty = document.getElementById("empty");

    let page = 1;
    let sortKey = "created_at";
    let sortDirection = -1;

    function visibleRows() {
        const term = search.value.trim().toLowerCase();
        return allRows.filter(row =>
            ["student_id", "full_name", "department", "level", "status"]
                .some(key => (row.dataset[key] || "").toLowerCase().includes(term))
        ).sort((a, b) => {
            let x = a.dataset[sortKey] || "";
            let y = b.dataset[sortKey] || "";
            if (sortKey === "level") {
                return (Number(x) - Number(y)) * sortDirection;
            }
            return x.localeCompare(y, undefined, {numeric:true, sensitivity:"base"}) * sortDirection;
        });
    }

    function render() {
        const rows = visibleRows();
        const size = Number(pageSize.value);
        const pages = Math.max(1, Math.ceil(rows.length / size));
        page = Math.min(page, pages);

        allRows.forEach(row => row.hidden = true);
        const start = (page - 1) * size;
        rows.slice(start, start + size).forEach(row => {
            row.hidden = false;
            tbody.appendChild(row);
        });

        empty.hidden = rows.length !== 0;
        summary.textContent = rows.length
            ? `Showing ${start + 1}–${Math.min(start + size, rows.length)} of ${rows.length} voters`
            : "0 voters found";

        pageButtons.replaceChildren();

        const addButton = (label, target, disabled, current = false) => {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = label;
            button.disabled = disabled;
            if (current) button.className = "current";
            button.addEventListener("click", () => { page = target; render(); });
            pageButtons.appendChild(button);
        };

        addButton("← Previous", page - 1, page === 1);
        for (let p = 1; p <= pages; p++) {
            if (pages > 7 && p !== 1 && p !== pages && Math.abs(p - page) > 1) {
                if (p === 2 || p === pages - 1) {
                    const dots = document.createElement("span");
                    dots.textContent = "…";
                    dots.style.padding = "8px 3px";
                    pageButtons.appendChild(dots);
                }
                continue;
            }
            addButton(String(p), p, false, p === page);
        }
        addButton("Next →", page + 1, page === pages);
    }

    search.addEventListener("input", () => { page = 1; render(); });
    pageSize.addEventListener("change", () => { page = 1; render(); });

    document.querySelectorAll("[data-sort]").forEach(button => {
        button.addEventListener("click", () => {
            const key = button.dataset.sort;
            sortDirection = sortKey === key ? -sortDirection : 1;
            sortKey = key;
            page = 1;
            render();
        });
    });

    render();
})();
</script>
</body>
</html>
