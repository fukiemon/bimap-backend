<?php
session_start();
require_once '../includes/db.php';
requireAdminLogin(); // must run BEFORE any POST/GET handling below

$page_title = 'Driver Approvals';
$active_nav = 'driver_approvals';

// Turns a stored photo value into something usable in <img src>.
// Old rows may hold a base64 data URI; newer ones hold a relative file path.
function photo_src($v) {
    $v = (string)$v;
    if ($v === '') return null;
    if (strncmp($v, 'data:', 5) === 0) return $v;
    return '../' . ltrim($v, '/');
}

function log_activity($conn, $text) {
    $s = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
    $s->bind_param('s', $text);
    $s->execute();
}

// ---- JSON endpoint: driver details (used by the "view details" modal) ----
if (isset($_GET['detail'])) {
    $id = (int)$_GET['detail'];
    $stmt = $conn->prepare(
        "SELECT id, first_name, last_name, email, phone, barangay, municipality, province,
                household_address, location, driver_license, license_number, license_photo,
                profile_pic, created_at, is_verified, is_suspended, suspended_at, suspended_reason
         FROM driver WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    header('Content-Type: application/json; charset=utf-8');
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Driver not found']);
        exit;
    }
    $row['license_no']  = $row['driver_license'] ?: $row['license_number'];
    $row['license_src'] = photo_src($row['license_photo']);
    $row['pic_src']     = photo_src($row['profile_pic']);
    unset($row['license_photo'], $row['profile_pic']);
    echo json_encode($row, JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- Handle approve / reject / suspend / reactivate ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['driver_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $flash  = null;

    if ($id && $action === 'approve') {
        $s = $conn->prepare("UPDATE driver SET is_verified = 1 WHERE id = ?");
        $s->bind_param('i', $id); $s->execute();
        log_activity($conn, "Driver ID $id was approved by admin");
        $flash = 'Driver approved.';

    } elseif ($id && $action === 'reject') {
        $s = $conn->prepare("DELETE FROM driver WHERE id = ? AND is_verified = 0");
        $s->bind_param('i', $id); $s->execute();
        log_activity($conn, "Driver ID $id registration was rejected");
        $flash = 'Application rejected.';

    } elseif ($id && $action === 'suspend') {
        $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 200);
        $reason = $reason !== '' ? $reason : null;
        $s = $conn->prepare(
            "UPDATE driver SET is_suspended = 1, suspended_at = NOW(), suspended_reason = ?
             WHERE id = ? AND is_verified = 1"
        );
        $s->bind_param('si', $reason, $id); $s->execute();
        log_activity($conn, "Driver ID $id was suspended by admin" . ($reason ? " (Reason: $reason)" : ''));
        $flash = 'Driver suspended.';

    } elseif ($id && $action === 'reactivate') {
        $s = $conn->prepare(
            "UPDATE driver SET is_suspended = 0, suspended_at = NULL, suspended_reason = NULL WHERE id = ?"
        );
        $s->bind_param('i', $id); $s->execute();
        log_activity($conn, "Driver ID $id was reactivated by admin");
        $flash = 'Driver reactivated.';
    }

    if ($flash) $_SESSION['flash'] = $flash;
    header('Location: driver_approvals.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Pending drivers
$pending = $conn->query("SELECT * FROM driver WHERE is_verified=0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Approved drivers (suspended ones listed last)
$approved = $conn->query(
    "SELECT id, first_name, last_name, email, phone, location, driver_license, license_number,
            is_suspended, suspended_reason, suspended_at, created_at
     FROM driver WHERE is_verified=1
     ORDER BY is_suspended ASC, created_at DESC LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<style>
.approval-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
@media(max-width:768px){.approval-grid{grid-template-columns:1fr}}

.driver-card{background:white;border-radius:12px;border:1.5px solid #e0e8f4;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05)}
.driver-card.clickable{cursor:pointer;transition:box-shadow .15s,transform .1s,border-color .15s}
.driver-card.clickable:hover{box-shadow:0 6px 20px rgba(0,0,0,0.1);transform:translateY(-2px);border-color:#1a73e8}
.driver-card.is-suspended{background:#fff8f8;border-color:#ffcdd2}
.dc-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.dc-avatar{width:50px;height:50px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.dc-info{flex:1}
.dc-name{font-size:16px;font-weight:800;color:#0d1b2a;margin-bottom:2px}
.dc-meta{font-size:12px;color:#6b7a99;font-weight:600}
.dc-location{font-size:13px;font-weight:600;color:#0d1b2a;margin-bottom:3px}
.dc-date{font-size:11px;color:#9baabb;font-weight:600}
.dc-license{font-size:13px;font-weight:700;color:#0d1b2a;margin:8px 0 4px}
.dc-reason{font-size:12px;color:#c62828;font-weight:700;margin-top:8px}
.license-photo{width:100%;max-height:180px;object-fit:contain;border:2px solid #e0e8f4;border-radius:10px;margin-top:8px;cursor:pointer}
.license-photo:hover{opacity:0.9}

.dc-actions{display:flex;gap:8px;margin-top:16px}
.btn-approve{padding:9px 18px;background:#00897b;color:white;border:none;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer}
.btn-approve:hover{background:#00695c}
.btn-reject{padding:9px 18px;background:#fff;color:#e53935;border:1.5px solid #ffcdd2;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer}
.btn-reject:hover{background:#fff0f0}
.btn-suspend{padding:8px 16px;background:#fff;color:#e53935;border:1.5px solid #ffcdd2;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer}
.btn-suspend:hover{background:#fff0f0}
.btn-reactivate{padding:8px 16px;background:#00897b;color:white;border:none;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer}
.btn-reactivate:hover{background:#00695c}
.btn-cancel{padding:9px 18px;background:#eef1f6;color:#333;border:none;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer}

.pending-badge{display:inline-flex;align-items:center;gap:4px;background:#fff8e1;color:#f57c00;border:1px solid #ffe082;border-radius:20px;font-size:11px;font-weight:800;padding:3px 10px;margin-bottom:14px}
.approved-badge{display:inline-flex;align-items:center;gap:4px;background:#e0f2f1;color:#00695c;border:1px solid #b2dfdb;border-radius:20px;font-size:11px;font-weight:800;padding:3px 10px}
.suspended-badge{display:inline-flex;align-items:center;gap:4px;background:#ffebee;color:#c62828;border:1px solid #ffcdd2;border-radius:20px;font-size:11px;font-weight:800;padding:3px 10px}

.empty-card{background:white;border-radius:12px;border:1.5px solid #e0e8f4;padding:32px;text-align:center;color:#9baabb}
.empty-card .ei{font-size:48px;margin-bottom:8px;opacity:0.4}
.section-title{font-size:18px;font-weight:900;color:#0d1b2a;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.count-badge{background:#1a73e8;color:white;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:800}
.count-badge.orange{background:#ff6d00}
.hint{font-size:12px;color:#9baabb;font-weight:600;margin:-8px 0 14px}

/* Details modal */
.dm-wide{max-width:560px;max-height:90vh;overflow-y:auto}
.dm-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
.dm-pic{width:64px;height:64px;border-radius:50%;background:#e8f0fe;display:flex;align-items:center;justify-content:center;font-size:30px;overflow:hidden;flex-shrink:0}
.dm-pic img{width:100%;height:100%;object-fit:cover}
.dm-name{font-size:20px;font-weight:900;color:#0d1b2a}
.dm-rows{display:grid;grid-template-columns:130px 1fr;gap:8px 12px;font-size:14px}
.dm-rows .k{color:#6b7a99;font-weight:800}
.dm-rows .v{color:#0d1b2a;font-weight:700;word-break:break-word}
.dm-lic{width:100%;max-height:240px;object-fit:contain;border:2px solid #e0e8f4;border-radius:10px;margin-top:14px;cursor:pointer}
.dm-susp{background:#ffebee;border:1px solid #ffcdd2;color:#c62828;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:700;margin-bottom:16px}
</style>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="section-title">⏳ Pending Approval <span class="count-badge orange"><?= count($pending) ?></span></div>

<?php if (empty($pending)): ?>
    <div class="empty-card">
        <div class="ei">✅</div>
        <p style="font-size:14px;font-weight:700">No pending driver applications</p>
    </div>
<?php else: ?>
    <div class="approval-grid">
    <?php foreach ($pending as $d):
        $licNo  = $d['driver_license'] ?: ($d['license_number'] ?? '');
        $licImg = photo_src($d['license_photo'] ?? '');
    ?>
        <div class="driver-card">
            <div class="pending-badge">⏳ Awaiting Approval</div>

            <div class="dc-top">
                <div class="dc-avatar">🚛</div>
                <div class="dc-info">
                    <div class="dc-name"><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></div>
                    <div class="dc-meta"><?= htmlspecialchars($d['phone'] ?? $d['email'] ?? '—') ?></div>
                </div>
            </div>

            <div class="dc-location">📍 <?= htmlspecialchars($d['location'] ?? '—') ?></div>
            <div class="dc-date">Registered: <?= date('M j, Y g:i A', strtotime($d['created_at'])) ?></div>

            <?php if (!empty($licNo)): ?>
                <div class="dc-license">Driver's License: <strong><?= htmlspecialchars($licNo) ?></strong></div>
            <?php endif; ?>

            <?php if ($licImg): ?>
                <img src="<?= htmlspecialchars($licImg) ?>" class="license-photo" alt="Driver's License"
                     onclick="openImage(this.src)">
            <?php else: ?>
                <p style="color:#e53935;font-size:13px;margin-top:8px;">No license photo uploaded</p>
            <?php endif; ?>

            <div class="dc-actions">
                <form method="POST" style="display:contents">
                    <input type="hidden" name="driver_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-approve" onclick="return confirm('Approve this driver?')">✅ Approve</button>
                </form>
                <form method="POST" style="display:contents">
                    <input type="hidden" name="driver_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-reject" onclick="return confirm('Reject and delete this application?')">✗ Reject</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Approved Drivers -->
<div class="section-title" style="margin-top:30px">✅ Approved Drivers <span class="count-badge"><?= count($approved) ?></span></div>
<?php if (!empty($approved)): ?><p class="hint">Click a driver to view their full details.</p><?php endif; ?>

<?php if (empty($approved)): ?>
    <div class="empty-card">
        <div class="ei">🚛</div>
        <p style="font-size:14px;font-weight:700">No approved drivers yet</p>
    </div>
<?php else: ?>
    <div class="approval-grid">
    <?php foreach ($approved as $d):
        $susp  = (int)$d['is_suspended'] === 1;
        $licNo = $d['driver_license'] ?: ($d['license_number'] ?? '');
        $fullName = $d['first_name'] . ' ' . $d['last_name'];
    ?>
        <div class="driver-card clickable <?= $susp ? 'is-suspended' : '' ?>"
             onclick="openDetails(<?= (int)$d['id'] ?>)">
            <?php if ($susp): ?>
                <span class="suspended-badge">⛔ Suspended</span>
            <?php else: ?>
                <span class="approved-badge">✅ Approved</span>
            <?php endif; ?>

            <div class="dc-top" style="margin-top:8px">
                <div class="dc-avatar">🚛</div>
                <div class="dc-info">
                    <div class="dc-name"><?= htmlspecialchars($fullName) ?></div>
                    <div class="dc-meta"><?= htmlspecialchars($d['phone'] ?? $d['email'] ?? '—') ?></div>
                </div>
            </div>
            <div class="dc-location">📍 <?= htmlspecialchars($d['location'] ?? '—') ?></div>

            <?php if (!empty($licNo)): ?>
                <div style="margin-top:8px;font-size:13px;">License: <strong><?= htmlspecialchars($licNo) ?></strong></div>
            <?php endif; ?>

            <?php if ($susp && !empty($d['suspended_reason'])): ?>
                <div class="dc-reason">Reason: <?= htmlspecialchars($d['suspended_reason']) ?></div>
            <?php endif; ?>

            <div class="dc-actions">
                <?php if ($susp): ?>
                    <form method="POST" style="display:contents" onclick="event.stopPropagation()">
                        <input type="hidden" name="driver_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="action" value="reactivate">
                        <button type="submit" class="btn-reactivate"
                                onclick="event.stopPropagation(); return confirm('Reactivate this driver?')">♻ Reactivate</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn-suspend"
                            onclick="event.stopPropagation(); openSuspend(<?= (int)$d['id'] ?>, <?= htmlspecialchars(json_encode($fullName), ENT_QUOTES) ?>)">
                        ⛔ Suspend
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Suspend modal -->
<div class="modal-overlay" id="suspendModal" onclick="if(event.target===this)closeModals()">
    <div class="modal">
        <div class="modal-header">Suspend Driver</div>
        <form method="POST">
            <div class="modal-body">
                <p style="font-size:14px;font-weight:700;margin-bottom:14px">
                    Suspend <span id="suspendName"></span>? They will be logged out of the app and
                    won't be able to log in until you reactivate them.
                </p>
                <input type="hidden" name="driver_id" id="suspendId">
                <input type="hidden" name="action" value="suspend">
                <div class="form-group">
                    <label>Reason (optional)</label>
                    <textarea class="form-control" name="reason" maxlength="200" style="min-height:80px"
                              placeholder="e.g. Multiple missed collections"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn-reject" style="background:#e53935;color:white;border-color:#e53935">Suspend</button>
            </div>
        </form>
    </div>
</div>

<!-- Details modal -->
<div class="modal-overlay" id="detailModal" onclick="if(event.target===this)closeModals()">
    <div class="modal dm-wide">
        <div class="modal-header">Driver Details</div>
        <div class="modal-body" id="detailBody">Loading…</div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModals()">Close</button>
        </div>
    </div>
</div>

<script>
function closeModals() {
    document.getElementById('suspendModal').classList.remove('open');
    document.getElementById('detailModal').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModals(); });

function openImage(src) {
    // data: URIs can't be opened via window.open in most browsers, so use a modal-free fallback
    const w = window.open();
    if (w) { w.document.write('<img src="' + src + '" style="max-width:100%">'); }
}

function openSuspend(id, name) {
    document.getElementById('suspendId').value = id;
    document.getElementById('suspendName').textContent = name;
    document.getElementById('suspendModal').classList.add('open');
}

function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
}

function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d) ? s : d.toLocaleString([], {year:'numeric', month:'short', day:'numeric', hour:'numeric', minute:'2-digit'});
}

async function openDetails(id) {
    const body = document.getElementById('detailBody');
    body.textContent = 'Loading…';
    document.getElementById('detailModal').classList.add('open');
    try {
        const res = await fetch('driver_approvals.php?detail=' + encodeURIComponent(id));
        if (!res.ok) throw new Error('Request failed');
        const d = await res.json();
        body.textContent = '';

        if (Number(d.is_suspended) === 1) {
            let t = '⛔ Suspended on ' + fmtDate(d.suspended_at);
            if (d.suspended_reason) t += ' — ' + d.suspended_reason;
            body.appendChild(el('div', 'dm-susp', t));
        }

        const head = el('div', 'dm-head');
        const pic = el('div', 'dm-pic');
        if (d.pic_src) { const img = document.createElement('img'); img.src = d.pic_src; pic.appendChild(img); }
        else pic.textContent = '🚛';
        head.appendChild(pic);
        head.appendChild(el('div', 'dm-name', (d.first_name || '') + ' ' + (d.last_name || '')));
        body.appendChild(head);

        const rows = [
            ['Email', d.email], ['Phone', d.phone],
            ['Barangay', d.barangay], ['Municipality', d.municipality], ['Province', d.province],
            ['Household address', d.household_address], ['Location', d.location],
            ['License number', d.license_no],
            ['Status', Number(d.is_suspended) === 1 ? 'Suspended' : (Number(d.is_verified) === 1 ? 'Approved' : 'Pending')],
            ['Registered', fmtDate(d.created_at)]
        ];
        const grid = el('div', 'dm-rows');
        rows.forEach(([k, v]) => { grid.appendChild(el('div', 'k', k)); grid.appendChild(el('div', 'v', v || '—')); });
        body.appendChild(grid);

        if (d.license_src) {
            body.appendChild(el('div', 'dm-rows k', "Driver's license photo")).style.cssText = 'margin-top:16px;font-weight:800;color:#6b7a99;font-size:14px';
            const img = document.createElement('img');
            img.src = d.license_src; img.className = 'dm-lic'; img.alt = "Driver's license";
            img.onclick = () => openImage(img.src);
            body.appendChild(img);
        }
    } catch (e) {
        body.textContent = 'Could not load driver details.';
    }
}
</script>

<?php include '../includes/admin_footer.php'; ?>
