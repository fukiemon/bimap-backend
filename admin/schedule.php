<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Collection Schedule';
$active_nav = 'schedule';

$success = '';
$error = '';
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// Create
if (isset($_POST['create_schedule'])) {
    $barangay  = trim($_POST['barangay'] ?? '');
    $day       = $_POST['day_of_week'] ?? '';
    $timeRange = trim($_POST['time_range'] ?? '');
    $wasteType = trim($_POST['waste_type'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($barangay && in_array($day, $days, true)) {
        $stmt = $conn->prepare(
            "INSERT INTO schedule (barangay, day_of_week, time_range, waste_type, notes) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('sssss', $barangay, $day, $timeRange, $wasteType, $notes);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO activity_log (action) VALUES ('Schedule added: " . $conn->real_escape_string($barangay) . " ($day)')");
            $success = 'Schedule entry added.';
        } else {
            $error = 'Failed to add schedule entry.';
        }
    } else {
        $error = 'Barangay and day of week are required.';
    }
}

// Update
if (isset($_POST['update_schedule'])) {
    $id        = (int)($_POST['schedule_id'] ?? 0);
    $barangay  = trim($_POST['barangay'] ?? '');
    $day       = $_POST['day_of_week'] ?? '';
    $timeRange = trim($_POST['time_range'] ?? '');
    $wasteType = trim($_POST['waste_type'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($id && $barangay && in_array($day, $days, true)) {
        $stmt = $conn->prepare(
            "UPDATE schedule SET barangay=?, day_of_week=?, time_range=?, waste_type=?, notes=? WHERE id=?"
        );
        $stmt->bind_param('sssssi', $barangay, $day, $timeRange, $wasteType, $notes, $id);
        if ($stmt->execute()) {
            $success = 'Schedule entry updated.';
        } else {
            $error = 'Failed to update schedule entry.';
        }
    } else {
        $error = 'Barangay and day of week are required.';
    }
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM schedule WHERE id=$id");
    $success = 'Schedule entry deleted.';
}

// Filter
$barangay_filter = trim($_GET['barangay'] ?? '');
$where = [];
if ($barangay_filter !== '') {
    $where[] = "barangay LIKE '%" . $conn->real_escape_string($barangay_filter) . "%'";
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$dayOrderSql = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$entries = $conn->query("SELECT * FROM schedule $where_sql ORDER BY barangay, $dayOrderSql")->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <form method="GET" style="display:flex;gap:12px;">
    <input type="text" name="barangay" class="form-control" placeholder="Filter by barangay..." value="<?= htmlspecialchars($barangay_filter) ?>" style="max-width:240px;margin:0;">
    <button type="submit" class="btn btn-primary">Filter</button>
  </form>
  <button class="btn btn-primary" onclick="openModal()">+ Add Schedule Entry</button>
</div>

<div class="card" style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="text-align:left;border-bottom:2px solid #e0e8f4;">
        <th style="padding:12px 16px;font-size:12px;color:#6b7a99;">Barangay</th>
        <th style="padding:12px 16px;font-size:12px;color:#6b7a99;">Day</th>
        <th style="padding:12px 16px;font-size:12px;color:#6b7a99;">Time</th>
        <th style="padding:12px 16px;font-size:12px;color:#6b7a99;">Waste Type</th>
        <th style="padding:12px 16px;font-size:12px;color:#6b7a99;">Notes</th>
        <th style="padding:12px 16px;font-size:12px;color:#6b7a99;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($entries)): ?>
      <tr><td colspan="6" style="text-align:center;padding:40px;color:#aaa;">No schedule entries yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($entries as $e): ?>
      <tr style="border-bottom:1px solid #f0f4f8;">
        <td style="padding:12px 16px;font-weight:700;"><?= htmlspecialchars($e['barangay']) ?></td>
        <td style="padding:12px 16px;"><?= htmlspecialchars($e['day_of_week']) ?></td>
        <td style="padding:12px 16px;"><?= htmlspecialchars($e['time_range'] ?? '—') ?></td>
        <td style="padding:12px 16px;"><?= htmlspecialchars($e['waste_type'] ?? '—') ?></td>
        <td style="padding:12px 16px;color:#666;"><?= htmlspecialchars($e['notes'] ?? '') ?></td>
        <td style="padding:12px 16px;white-space:nowrap;">
          <button class="btn btn-sm" style="background:#f0f4f8;color:#333;" onclick="editEntry(<?= htmlspecialchars(json_encode($e)) ?>)">Edit</button>
          <a href="?delete=<?= $e['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this schedule entry?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- CREATE / EDIT MODAL -->
<div class="modal-overlay" id="scheduleModal">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header" id="modalTitle">Add Schedule Entry</div>
    <form method="POST" id="scheduleForm">
      <input type="hidden" name="schedule_id" id="scheduleId">

      <div class="modal-body">
        <div class="form-group">
          <label>Barangay <span class="req">*</span></label>
          <input type="text" name="barangay" id="fBarangay" class="form-control" placeholder="e.g. Poblacion" required>
        </div>
        <div class="form-group">
          <label>Day of Week <span class="req">*</span></label>
          <select name="day_of_week" id="fDay" class="form-control" required>
            <?php foreach ($days as $d): ?>
              <option value="<?= $d ?>"><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Time Range</label>
          <input type="text" name="time_range" id="fTime" class="form-control" placeholder="e.g. 7:00 AM - 9:00 AM">
        </div>
        <div class="form-group">
          <label>Waste Type</label>
          <input type="text" name="waste_type" id="fWaste" class="form-control" placeholder="e.g. Household, Recyclables">
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" id="fNotes" class="form-control" placeholder="Optional notes"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:#f0f4f8;color:#333;" onclick="closeModal()">Cancel</button>
        <button type="submit" name="create_schedule" id="submitBtn" class="btn btn-primary">Add Entry</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal() {
  document.getElementById('modalTitle').textContent = 'Add Schedule Entry';
  document.getElementById('submitBtn').textContent   = 'Add Entry';
  document.getElementById('submitBtn').name          = 'create_schedule';
  document.getElementById('scheduleId').value        = '';
  document.getElementById('fBarangay').value         = '';
  document.getElementById('fDay').value               = 'Monday';
  document.getElementById('fTime').value              = '';
  document.getElementById('fWaste').value             = '';
  document.getElementById('fNotes').value             = '';
  document.getElementById('scheduleModal').classList.add('open');
}
function closeModal() {
  document.getElementById('scheduleModal').classList.remove('open');
}
function editEntry(e) {
  document.getElementById('modalTitle').textContent = 'Edit Schedule Entry';
  document.getElementById('submitBtn').textContent   = 'Update Entry';
  document.getElementById('submitBtn').name          = 'update_schedule';
  document.getElementById('scheduleId').value        = e.id;
  document.getElementById('fBarangay').value         = e.barangay;
  document.getElementById('fDay').value               = e.day_of_week;
  document.getElementById('fTime').value              = e.time_range || '';
  document.getElementById('fWaste').value             = e.waste_type || '';
  document.getElementById('fNotes').value             = e.notes || '';
  document.getElementById('scheduleModal').classList.add('open');
}
document.getElementById('scheduleModal').addEventListener('click', function(ev) {
  if (ev.target === this) closeModal();
});
</script>

<?php include '../includes/admin_footer.php'; ?>
