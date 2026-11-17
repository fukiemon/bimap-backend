<?php
session_start();
require_once '../includes/db.php';
requireAdminLogin();

$page_title = 'Driver Approvals';
$active_nav = 'driver_approvals';

// ---------- Handle approve / reject ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['driver_id'], $_POST['action'])) {
    $id     = (int)$_POST['driver_id'];
    $action = $_POST['action'];

    // Get the driver's name for the activity log
    $stmt = $conn->prepare("SELECT * FROM driver WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $drv = $stmt->get_result()->fetch_assoc();

    if ($drv) {
        $name = trim(($drv['first_name'] ?? '') . ' ' . ($drv['last_name'] ?? ''));
        if ($name === '') $name = $drv['name'] ?? ('Driver #' . $id);

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE driver SET is_verified=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $log = "Driver $name was approved";
            $stmt = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
            $stmt->bind_param("s", $log);
            $stmt->execute();

            $_SESSION['success'] = "$name has been approved.";
        } elseif ($action === 'reject') {
            // Only pending drivers can be rejected (approved ones are never deleted here)
            $stmt = $conn->prepare("DELETE FROM driver WHERE id=? AND is_verified=0");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $log = "Driver $name registration was rejected";
            $stmt = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
            $stmt->bind_param("s", $log);
            $stmt->execute();

            $_SESSION['success'] = "$name's registration was rejected.";
        }
    }

    // Redirect to THIS page (not dashboard.php)
    $tab = ($_POST['tab'] ?? 'pending') === 'approved' ? 'approved' : 'pending';
    header("Location: driver_approvals.php?tab=" . $tab);
    exit;
}

// ---------- Load data ----------
$tab = ($_GET['tab'] ?? 'pending') === 'approved' ? 'approved' : 'pending';

$pending_count  = (int)($conn->query("SELECT COUNT(*) c FROM driver WHERE is_verified=0")->fetch_assoc()['c'] ?? 0);
$approved_count = (int)($conn->query("SELECT COUNT(*) c FROM driver WHERE is_verified=1")->fetch_assoc()['c'] ?? 0);

$verified = $tab === 'approved' ? 1 : 0;
$stmt = $conn->prepare("SELECT * FROM driver WHERE is_verified=? ORDER BY id DESC");
$stmt->bind_param("i", $verified);
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * Fields shown on each driver card: 'db_column' => 'Label'.
 * Columns that don't exist in your table are skipped automatically.
 * Edit this list to match your `driver` table.
 */
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

// Columns that may hold an uploaded image/document path
$image_fields = [
    'license_photo' => 'Driver License',
    'license_image' => 'Driver License',
    'photo'         => 'Photo',
    'profile_pic'   => 'Profile Picture',
];

include '../includes/admin_header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
  <a href="driver_approvals.php?tab=pending"
     class="btn <?= $tab === 'pending' ? 'btn-primary' : '' ?>"
     style="<?= $tab !== 'pending' ? 'background:white;color:#333;border:1px solid #e0e7ef;' : '' ?>">
    Pending (<?= $pending_count ?>)
  </a>
  <a href="driver_approvals.php?tab=approved"
     class="btn <?= $tab === 'approved' ? 'btn-primary' : '' ?>"
     style="<?= $tab !== 'approved' ? 'background:white;color:#333;border:1px solid #e0e7ef;' : '' ?>">
    Approved (<?= $approved_count ?>)
  </a>
</div>

<?php if (!$drivers): ?>
  <div class="card">
    <div class="card-body" style="padding:40px;text-align:center;color:#888;font-weight:700;">
      <?= $tab === 'pending' ? 'No pending driver registrations.' : 'No approved drivers yet.' ?>
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
      <span class="badge <?= $tab === 'pending' ? 'badge-pending' : 'badge-resolved' ?>">
        <?= $tab === 'pending' ? 'Pending' : 'Approved' ?>
      </span>
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
          if (empty($d[$col])) continue;
          $src = $d[$col];
          // Base64 data URIs are used as-is; file paths get the "../" prefix
          $isData = strpos($src, 'data:') === 0;
          $url = $isData ? $src : '../' . ltrim($src, '/');
      ?>
        <div style="margin-top:14px;">
          <strong style="font-size:13px;"><?= htmlspecialchars($label) ?>:</strong><br>
          <img src="<?= htmlspecialchars($url) ?>" alt="<?= htmlspecialchars($label) ?>"
               style="max-width:100%;height:auto;max-height:200px;border-radius:10px;border:2px solid #e0e8f4;margin-top:6px;cursor:pointer;"
               onclick="window.open(this.src, '_blank')">
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'pending'): ?>
    <form method="POST" class="dc-actions" style="display:flex;gap:8px;padding:0 22px 20px;">
      <input type="hidden" name="driver_id" value="<?= (int)$d['id'] ?>">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <button type="submit" name="action" value="approve" class="btn btn-success"
              onclick="return confirm('Approve this driver?')">Approve</button>
      <button type="submit" name="action" value="reject" class="btn btn-danger"
              onclick="return confirm('Reject and delete this registration? This cannot be undone.')">Reject</button>
    </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<?php include '../includes/admin_footer.php'; ?>
