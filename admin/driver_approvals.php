<?php
session_start();
require_once '../includes/db.php';
requireAdminLogin();

$page_title = 'Driver Approvals';
$active_nav = 'driver_approvals';

// Column on the `driver` table that marks a suspended account (1 = suspended).
// If your table uses a different column, change it here.
$SUSPEND_COL = 'is_suspended';

$colCheck    = $conn->query("SHOW COLUMNS FROM driver LIKE '" . $conn->real_escape_string($SUSPEND_COL) . "'");
$has_suspend = $colCheck && $colCheck->num_rows > 0;

$valid_tabs = ['pending', 'approved', 'suspended'];

/* ------------------------------------------------------------------
   Image resolver: handles data URIs, raw base64, BLOBs, URLs and file
   paths (tries several likely upload folders and checks they exist).
   Returns [url|null, note].
------------------------------------------------------------------- */
function resolveImage($val) {
    if ($val === null || $val === '') return [null, ''];

    // Binary BLOB stored directly in the column
    if (preg_match('/[^\x20-\x7E\r\n\t]/', substr($val, 0, 64))) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($val) ?: 'image/jpeg';
        return ['data:' . $mime . ';base64,' . base64_encode($val), ''];
    }

    $val = trim($val);

    if (strpos($val, 'data:') === 0)               return [$val, ''];
    if (preg_match('#^https?://#i', $val))         return [$val, ''];

    // Raw base64 without the "data:" prefix
    if (strlen($val) > 200 && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $val)) {
        $bin  = base64_decode($val, true);
        $mime = $bin ? ((new finfo(FILEINFO_MIME_TYPE))->buffer($bin) ?: 'image/jpeg') : 'image/jpeg';
        return ['data:' . $mime . ';base64,' . $val, ''];
    }

    // File path: try the common upload locations
    $clean = ltrim(str_replace('\\', '/', $val), '/');
    $candidates = [
        '../' . $clean,
        '../uploads/' . $clean,
        '../uploads/licenses/' . $clean,
        '../uploads/drivers/' . $clean,
        '../uploads/driver_licenses/' . $clean,
        '../driver/' . $clean,
        $clean,
    ];
    foreach ($candidates as $rel) {
        if (file_exists(__DIR__ . '/' . $rel)) return [$rel, ''];
    }
    // Absolute path under the web root
    if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $clean)) {
        return ['/' . $clean, ''];
    }

    return [null, 'File not found on server. Stored value: ' . mb_substr($val, 0, 80)];
}

/* ---------------------------- Actions ---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['driver_id'], $_POST['action'])) {
    $id     = (int)$_POST['driver_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT * FROM driver WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $drv = $stmt->get_result()->fetch_assoc();

    if ($drv) {
        $name = trim(($drv['first_name'] ?? '') . ' ' . ($drv['last_name'] ?? ''));
        if ($name === '') $name = $drv['name'] ?? ('Driver #' . $id);
        $log = null;

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE driver SET is_verified=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $log = "Driver $name was approved";
            $_SESSION['success'] = "$name has been approved.";

        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("DELETE FROM driver WHERE id=? AND is_verified=0");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $log = "Driver $name registration was rejected";
            $_SESSION['success'] = "$name's registration was rejected.";

        } elseif ($action === 'suspend' && $has_suspend) {
            $stmt = $conn->prepare("UPDATE driver SET `$SUSPEND_COL`=1 WHERE id=? AND is_verified=1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $log = "Driver $name was suspended";
            $_SESSION['success'] = "$name has been suspended.";

        } elseif ($action === 'reactivate' && $has_suspend) {
            $stmt = $conn->prepare("UPDATE driver SET `$SUSPEND_COL`=0 WHERE id=? AND is_verified=1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $log = "Driver $name was reactivated";
            $_SESSION['success'] = "$name has been reactivated.";
        }

        if ($log) {
            $stmt = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
            $stmt->bind_param("s", $log);
            $stmt->execute();
        }
    }

    $back = in_array($_POST['tab'] ?? '', $valid_tabs, true) ? $_POST['tab'] : 'pending';
    header("Location: driver_approvals.php?tab=" . $back);
    exit;
}

/* ----------------------------- Data ------------------------------ */
$tab = in_array($_GET['tab'] ?? '', $valid_tabs, true) ? $_GET['tab'] : 'pending';
if ($tab === 'suspended' && !$has_suspend) $tab = 'approved';

$notSusp = $has_suspend ? "AND (`$SUSPEND_COL`=0 OR `$SUSPEND_COL` IS NULL)" : '';

$pending_count   = (int)($conn->query("SELECT COUNT(*) c FROM driver WHERE is_verified=0")->fetch_assoc()['c'] ?? 0);
$approved_count  = (int)($conn->query("SELECT COUNT(*) c FROM driver WHERE is_verified=1 $notSusp")->fetch_assoc()['c'] ?? 0);
$suspended_count = $has_suspend
    ? (int)($conn->query("SELECT COUNT(*) c FROM driver WHERE is_verified=1 AND `$SUSPEND_COL`=1")->fetch_assoc()['c'] ?? 0)
    : 0;

if ($tab === 'pending')        $where = "is_verified=0";
elseif ($tab === 'suspended')  $where = "is_verified=1 AND `$SUSPEND_COL`=1";
else                           $where = "is_verified=1 $notSusp";

$drivers = $conn->query("SELECT * FROM driver WHERE $where ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Fields shown on each card: 'db_column' => 'Label' (missing columns are skipped)
$fields = [
    'email'          => 'Email',
    'phone'          => 'Phone',
    'address'        => 'Address',
    'barangay'       => 'Barangay',
    'vehicle_type'   => 'Vehicle Type',
    'plate_number'   => 'Plate Number',
    'license_number' => 'License No.',
    'created_at'     => 'Registered',
];

// Columns that may hold an uploaded image
$image_fields = [
    'license_photo' => 'Driver License',
    'license_image' => 'Driver License',
    'license_pic'   => 'Driver License',
    'photo'         => 'Photo',
    'profile_pic'   => 'Profile Picture',
];

function tabStyle($active) {
    return $active ? '' : 'background:white;color:#333;border:1px solid #e0e7ef;';
}

include '../includes/admin_header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!$has_suspend): ?>
    <div class="alert alert-error">
        Suspension is disabled because the <code><?= htmlspecialchars($SUSPEND_COL) ?></code> column doesn't exist on the
        <code>driver</code> table. Run this in phpMyAdmin to enable it:<br>
        <code>ALTER TABLE driver ADD COLUMN <?= htmlspecialchars($SUSPEND_COL) ?> TINYINT(1) NOT NULL DEFAULT 0;</code>
    </div>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
  <a href="driver_approvals.php?tab=pending" class="btn <?= $tab === 'pending' ? 'btn-primary' : '' ?>"
     style="<?= tabStyle($tab === 'pending') ?>">Pending (<?= $pending_count ?>)</a>
  <a href="driver_approvals.php?tab=approved" class="btn <?= $tab === 'approved' ? 'btn-primary' : '' ?>"
     style="<?= tabStyle($tab === 'approved') ?>">Approved (<?= $approved_count ?>)</a>
  <?php if ($has_suspend): ?>
  <a href="driver_approvals.php?tab=suspended" class="btn <?= $tab === 'suspended' ? 'btn-primary' : '' ?>"
     style="<?= tabStyle($tab === 'suspended') ?>">Suspended (<?= $suspended_count ?>)</a>
  <?php endif; ?>
</div>

<?php if (!$drivers): ?>
  <div class="card">
    <div class="card-body" style="padding:40px;text-align:center;color:#888;font-weight:700;">
      <?php
        if ($tab === 'pending')        echo 'No pending driver registrations.';
        elseif ($tab === 'suspended')  echo 'No suspended drivers.';
        else                           echo 'No approved drivers yet.';
      ?>
    </div>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">
<?php foreach ($drivers as $d):
    $name = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
    if ($name === '') $name = $d['name'] ?? ('Driver #' . (int)$d['id']);
?>
  <div class="card">
    <div class="card-header">
      <h2><?= htmlspecialchars($name) ?></h2>
      <?php if ($tab === 'pending'): ?>
        <span class="badge badge-pending">Pending</span>
      <?php elseif ($tab === 'suspended'): ?>
        <span class="badge badge-no">Suspended</span>
      <?php else: ?>
        <span class="badge badge-resolved">Approved</span>
      <?php endif; ?>
    </div>

    <div style="padding:16px 22px;">
      <div class="dm-rows" style="display:grid;grid-template-columns:120px 1fr;gap:6px 12px;font-size:14px;word-break:break-word;">
        <?php foreach ($fields as $col => $label):
            if (!array_key_exists($col, $d) || $d[$col] === null || $d[$col] === '') continue;
            $val = $d[$col];
            if ($col === 'created_at') $val = date('M d, Y', strtotime($val));
        ?>
          <div class="k" style="font-weight:800;color:#888;"><?= htmlspecialchars($label) ?></div>
          <div><?= htmlspecialchars($val) ?></div>
        <?php endforeach; ?>
      </div>

      <?php foreach ($image_fields as $col => $label):
          if (!isset($d[$col]) || $d[$col] === '') continue;
          [$url, $note] = resolveImage($d[$col]);
      ?>
        <div style="margin-top:14px;">
          <strong style="font-size:13px;"><?= htmlspecialchars($label) ?>:</strong><br>
          <?php if ($url): ?>
            <img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($label) ?>"
                 style="max-width:100%;height:auto;max-height:200px;border-radius:10px;border:2px solid #e0e8f4;margin-top:6px;cursor:pointer;"
                 onclick="window.open(this.src, '_blank')">
          <?php else: ?>
            <div style="margin-top:6px;font-size:12px;color:#9b2226;word-break:break-all;"><?= htmlspecialchars($note) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" class="dc-actions" style="display:flex;gap:8px;padding:0 22px 20px;">
      <input type="hidden" name="driver_id" value="<?= (int)$d['id'] ?>">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

      <?php if ($tab === 'pending'): ?>
        <button type="submit" name="action" value="approve" class="btn btn-success"
                onclick="return confirm('Approve this driver?')">Approve</button>
        <button type="submit" name="action" value="reject" class="btn btn-danger"
                onclick="return confirm('Reject and delete this registration? This cannot be undone.')">Reject</button>

      <?php elseif ($tab === 'approved' && $has_suspend): ?>
        <button type="submit" name="action" value="suspend" class="btn btn-danger"
                onclick="return confirm('Suspend this driver account? They will not be able to log in.')">Suspend</button>

      <?php elseif ($tab === 'suspended'): ?>
        <button type="submit" name="action" value="reactivate" class="btn btn-success"
                onclick="return confirm('Reactivate this driver account?')">Reactivate</button>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>
</div>

<?php include '../includes/admin_footer.php'; ?>
