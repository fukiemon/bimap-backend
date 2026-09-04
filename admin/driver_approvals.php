<?php
session_start();
require_once '../includes/db.php';
$page_title = 'Driver Approvals';
$active_nav = 'driver_approvals';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['driver_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && $action === 'approve') {
        $conn->query("UPDATE driver SET is_verified=1 WHERE id=$id");
        $conn->query("INSERT INTO activity_log (action) VALUES ('Driver ID $id was approved by admin')");
    } elseif ($id && $action === 'reject') {
        $conn->query("DELETE FROM driver WHERE id=$id AND is_verified=0");
        $conn->query("INSERT INTO activity_log (action) VALUES ('Driver ID $id registration was rejected')");
    }
    header('Location: driver_approvals.php');
    exit;
}

// Fetch pending drivers
$pending = $conn->query("SELECT * FROM driver WHERE is_verified=0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch approved drivers
$approved = $conn->query("SELECT * FROM driver WHERE is_verified=1 ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<style>
.approval-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
@media(max-width:768px){.approval-grid{grid-template-columns:1fr}}

.driver-card{background:white;border-radius:12px;border:1.5px solid #e0e8f4;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05)}
.dc-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.dc-avatar{width:50px;height:50px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.dc-info{flex:1}
.dc-name{font-size:16px;font-weight:800;color:#0d1b2a;margin-bottom:2px}
.dc-meta{font-size:12px;color:#6b7a99;font-weight:600}
.dc-location{font-size:13px;font-weight:600;color:#0d1b2a;margin-bottom:3px}
.dc-date{font-size:11px;color:#9baabb;font-weight:600}
.dc-license{font-size:13px;font-weight:700;color:#0d1b2a;margin:8px 0 4px}
.license-photo{width:100%;max-height:180px;object-fit:contain;border:2px solid #e0e8f4;border-radius:10px;margin-top:8px;cursor:pointer}
.license-photo:hover{opacity:0.9}

.dc-actions{display:flex;gap:8px;margin-top:16px}
.btn-approve{padding:9px 18px;background:#00897b;color:white;border:none;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer}
.btn-approve:hover{background:#00695c}
.btn-reject{padding:9px 18px;background:#fff;color:#e53935;border:1.5px solid #ffcdd2;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer}
.btn-reject:hover{background:#fff0f0}

.pending-badge{display:inline-flex;align-items:center;gap:4px;background:#fff8e1;color:#f57c00;border:1px solid #ffe082;border-radius:20px;font-size:11px;font-weight:800;padding:3px 10px;margin-bottom:14px}
.approved-badge{display:inline-flex;align-items:center;gap:4px;background:#e0f2f1;color:#00695c;border:1px solid #b2dfdb;border-radius:20px;font-size:11px;font-weight:800;padding:3px 10px}

.empty-card{background:white;border-radius:12px;border:1.5px solid #e0e8f4;padding:32px;text-align:center;color:#9baabb}
.empty-card .ei{font-size:48px;margin-bottom:8px;opacity:0.4}
.section-title{font-size:18px;font-weight:900;color:#0d1b2a;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.count-badge{background:#1a73e8;color:white;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:800}
.count-badge.orange{background:#ff6d00}
</style>

<div class="section-title">⏳ Pending Approval <span class="count-badge orange"><?= count($pending) ?></span></div>

<?php if (empty($pending)): ?>
    <div class="empty-card">
        <div class="ei">✅</div>
        <p style="font-size:14px;font-weight:700">No pending driver applications</p>
    </div>
<?php else: ?>
    <div class="approval-grid">
    <?php foreach ($pending as $d): ?>
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

            <!-- Driver's License Info -->
            <?php if (!empty($d['driver_license'])): ?>
                <div class="dc-license">
                    Driver's License: <strong><?= htmlspecialchars($d['driver_license']) ?></strong>
                </div>
            <?php endif; ?>

            <!-- License Photo -->
            <?php if (!empty($d['license_photo'])): ?>
                <img src="../<?= htmlspecialchars($d['license_photo']) ?>" 
                     class="license-photo" 
                     alt="Driver's License"
                     onclick="window.open(this.src, '_blank')">
            <?php else: ?>
                <p style="color:#e53935;font-size:13px;margin-top:8px;">No license photo uploaded</p>
            <?php endif; ?>

            <div class="dc-actions">
                <form method="POST" style="display:contents">
                    <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-approve" onclick="return confirm('Approve this driver?')">✅ Approve</button>
                </form>
                <form method="POST" style="display:contents">
                    <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-reject" onclick="return confirm('Reject and delete this application?')">✗ Reject</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Approved Drivers Section -->
<div class="section-title" style="margin-top:30px">✅ Approved Drivers <span class="count-badge"><?= count($approved) ?></span></div>

<?php if (empty($approved)): ?>
    <div class="empty-card">
        <div class="ei">🚛</div>
        <p style="font-size:14px;font-weight:700">No approved drivers yet</p>
    </div>
<?php else: ?>
    <div class="approval-grid">
    <?php foreach ($approved as $d): ?>
        <div class="driver-card">
            <span class="approved-badge">✅ Approved</span>
            <div class="dc-top" style="margin-top:8px">
                <div class="dc-avatar">🚛</div>
                <div class="dc-info">
                    <div class="dc-name"><?= htmlspecialchars($d['first_name'].' '.$d['last_name']) ?></div>
                    <div class="dc-meta"><?= htmlspecialchars($d['phone']??$d['email']??'—') ?></div>
                </div>
            </div>
            <div class="dc-location">📍 <?= htmlspecialchars($d['location']??'—') ?></div>
            
            <?php if (!empty($d['driver_license'])): ?>
                <div style="margin-top:8px;font-size:13px;">
                    License: <strong><?= htmlspecialchars($d['driver_license']) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>