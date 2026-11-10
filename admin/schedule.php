<?php
session_start();
require_once '../includes/db.php';
requireAdminLogin(); // before ANY handling below

$page_title = 'Collection Schedule';
$active_nav = 'schedule';

$TYPES = [
    'residual'          => 'Residual',
    'biodegradable'     => 'Biodegradable (Malata)',
    'non_biodegradable' => 'Non-Biodegradable (Dili Malata)',
    'recyclable'        => 'Recyclable (Magamit pa)',
    'special'           => 'Special (Makahilo)',
];
$TYPE_COLORS = [
    'residual'          => ['#efebe9', '#5d4037'],
    'biodegradable'     => ['#e8f5e9', '#2e7d32'],
    'non_biodegradable' => ['#eceff1', '#455a64'],
    'recyclable'        => ['#e3f2fd', '#1565c0'],
    'special'           => ['#fff3e0', '#e65100'],
];
$DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// ---------------------------------------------------------------- helpers
function log_activity($conn, $text) {
    $s = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
    $s->bind_param('s', $text);
    $s->execute();
}

// Stamps the driver so their app shows the "schedule updated" badge.
function notify_driver($conn, $driverId) {
    if (!$driverId) return;
    $s = $conn->prepare("UPDATE driver SET schedule_updated_at = NOW() WHERE id = ?");
    $s->bind_param('i', $driverId);
    $s->execute();
}

// Name of the logged-in admin, saved on the assignment so the driver app can
// show "Assigned by ...". If your admin login stores the name under another
// session key, add that key to this list.
function current_admin_name() {
    foreach (['admin_name', 'admin_username', 'username', 'full_name', 'name'] as $k) {
        if (!empty($_SESSION[$k])) return mb_substr((string)$_SESSION[$k], 0, 100);
    }
    return 'Admin';
}

function read_form($conn, $TYPES, $DAYS) {
    $driverId = (int)($_POST['driver_id'] ?? 0);
    if ($driverId) {
        $s = $conn->prepare("SELECT id FROM driver WHERE id = ? AND is_verified = 1 LIMIT 1");
        $s->bind_param('i', $driverId);
        $s->execute();
        if (!$s->get_result()->fetch_assoc()) return [null, 'Selected driver was not found.'];
    } else {
        $driverId = null;
    }

    $type = $_POST['collection_type'] ?? '';
    if (!isset($TYPES[$type])) return [null, 'Choose a collection type.'];

    $clusterId = (int)($_POST['cluster_id'] ?? 0);
    if ($clusterId) {
        $s = $conn->prepare("SELECT id FROM cluster WHERE id = ? LIMIT 1");
        $s->bind_param('i', $clusterId);
        $s->execute();
        if (!$s->get_result()->fetch_assoc()) return [null, 'Selected cluster was not found.'];
    } else {
        $clusterId = null;
    }

    $weeks = array_filter(array_map('intval', (array)($_POST['weeks'] ?? [])), fn($w) => $w >= 1 && $w <= 4);
    $weeks = array_values(array_unique($weeks));
    sort($weeks);
    if (!$weeks) return [null, 'Pick at least one week.'];

    $posted = (array)($_POST['days'] ?? []);
    $days = array_values(array_filter($DAYS, fn($d) => in_array($d, $posted, true)));
    if (!$days) return [null, 'Pick at least one day (Monday to Friday).'];

    $lines = preg_split('/\R/', (string)($_POST['places'] ?? ''));
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    if (!$lines) return [null, 'Enter at least one place (one per line).'];

    return [[
        'driver_id' => $driverId,
        'type'      => $type,
        'cluster_id'=> $clusterId,
        'weeks'     => implode(',', $weeks),
        'days'      => implode(',', $days),
        'time'      => mb_substr(trim($_POST['time_range'] ?? ''), 0, 50),
        'places'    => implode("\n", $lines),
        'notes'     => trim($_POST['notes'] ?? ''),
    ], null];
}

// Same driver, same week AND same day as another assignment -> warn (not block).
function find_overlaps($conn, $driverId, $weeksCsv, $daysCsv, $excludeId) {
    if (!$driverId) return [];
    $s = $conn->prepare(
        "SELECT a.id, a.collection_type, a.weeks, a.days, c.name AS cluster_name
         FROM schedule_assignment a LEFT JOIN cluster c ON c.id = a.cluster_id
         WHERE a.driver_id = ? AND a.id <> ?"
    );
    $s->bind_param('ii', $driverId, $excludeId);
    $s->execute();
    $mine = [explode(',', $weeksCsv), explode(',', $daysCsv)];
    $out = [];
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        if (array_intersect(explode(',', $r['weeks']), $mine[0]) && array_intersect(explode(',', $r['days']), $mine[1])) {
            $out[] = ($r['cluster_name'] ?: 'No cluster') . ' (' . $r['collection_type'] . ')';
        }
    }
    return $out;
}

function summary($TYPES, $d, $clusterName) {
    return $TYPES[$d['type']] . ($clusterName ? " - $clusterName" : '') . " ({$d['days']}; week {$d['weeks']})";
}

function cluster_name($conn, $id) {
    if (!$id) return null;
    $s = $conn->prepare("SELECT name FROM cluster WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r['name'] ?? null;
}

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        [$d, $err] = read_form($conn, $TYPES, $DAYS);
        if ($err) {
            $_SESSION['flash_err'] = $err;
            header('Location: schedule.php'); exit;
        }
        $cName = cluster_name($conn, $d['cluster_id']);
        $by = current_admin_name();

        if ($action === 'create') {
            $s = $conn->prepare(
                "INSERT INTO schedule_assignment (driver_id, collection_type, cluster_id, weeks, days, time_range, places, notes, assigned_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $s->bind_param('isissssss', $d['driver_id'], $d['type'], $d['cluster_id'], $d['weeks'], $d['days'], $d['time'], $d['places'], $d['notes'], $by);
            $s->execute();
            $newId = $conn->insert_id;
            notify_driver($conn, $d['driver_id']);
            log_activity($conn, 'Schedule assignment added: ' . summary($TYPES, $d, $cName));
            $_SESSION['flash'] = 'Assignment added.' . ($d['driver_id'] ? ' The driver will be notified.' : '');
            $overlaps = find_overlaps($conn, $d['driver_id'], $d['weeks'], $d['days'], $newId);
        } else {
            $id = (int)($_POST['assignment_id'] ?? 0);
            $o = $conn->prepare("SELECT driver_id FROM schedule_assignment WHERE id = ?");
            $o->bind_param('i', $id);
            $o->execute();
            $old = $o->get_result()->fetch_assoc();
            if (!$old) {
                $_SESSION['flash_err'] = 'Assignment not found.';
                header('Location: schedule.php'); exit;
            }
            $s = $conn->prepare(
                "UPDATE schedule_assignment SET driver_id=?, collection_type=?, cluster_id=?, weeks=?, days=?, time_range=?, places=?, notes=?, assigned_by=?
                 WHERE id=?"
            );
            $s->bind_param('isissssssi', $d['driver_id'], $d['type'], $d['cluster_id'], $d['weeks'], $d['days'], $d['time'], $d['places'], $d['notes'], $by, $id);
            $s->execute();
            // Notify the new driver AND the previous one (if it moved).
            notify_driver($conn, $d['driver_id']);
            if ($old['driver_id'] && (int)$old['driver_id'] !== (int)$d['driver_id']) notify_driver($conn, (int)$old['driver_id']);
            log_activity($conn, 'Schedule assignment updated: ' . summary($TYPES, $d, $cName));
            $_SESSION['flash'] = 'Assignment updated.' . ($d['driver_id'] ? ' The driver will be notified.' : '');
            $overlaps = find_overlaps($conn, $d['driver_id'], $d['weeks'], $d['days'], $id);
        }
        if (!empty($overlaps)) {
            $_SESSION['flash_warn'] = 'Heads up: this driver already has an assignment on the same day and week: ' . implode(', ', $overlaps) . '.';
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['assignment_id'] ?? 0);
        $o = $conn->prepare("SELECT driver_id FROM schedule_assignment WHERE id = ?");
        $o->bind_param('i', $id);
        $o->execute();
        $old = $o->get_result()->fetch_assoc();
        if ($old) {
            $s = $conn->prepare("DELETE FROM schedule_assignment WHERE id = ?");
            $s->bind_param('i', $id);
            $s->execute();
            notify_driver($conn, (int)$old['driver_id']);
            log_activity($conn, "Schedule assignment #$id deleted");
            $_SESSION['flash'] = 'Assignment deleted.';
        }
    }
    header('Location: schedule.php' . (isset($_POST['keep_filter']) && $_POST['keep_filter'] !== '' ? '?driver=' . urlencode($_POST['keep_filter']) : ''));
    exit;
}

$flash     = $_SESSION['flash'] ?? null;
$flashWarn = $_SESSION['flash_warn'] ?? null;
$flashErr  = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_warn'], $_SESSION['flash_err']);

// ---------------------------------------------------------------- data
$drivers  = $conn->query("SELECT id, first_name, last_name, is_suspended FROM driver WHERE is_verified = 1 ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$clusters = $conn->query("SELECT id, name, barangays FROM cluster ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$clusterMap = [];
foreach ($clusters as $c) $clusterMap[$c['id']] = ['name' => $c['name'], 'barangays' => $c['barangays']];

$filter = $_GET['driver'] ?? '';
$where = '';
if ($filter === 'none') $where = 'WHERE a.driver_id IS NULL';
elseif (ctype_digit($filter) && (int)$filter > 0) $where = 'WHERE a.driver_id = ' . (int)$filter;

$rows = $conn->query(
    "SELECT a.*, c.name AS cluster_name, d.first_name, d.last_name
     FROM schedule_assignment a
     LEFT JOIN cluster c ON c.id = a.cluster_id
     LEFT JOIN driver d ON d.id = a.driver_id
     $where
     ORDER BY (a.driver_id IS NULL), d.first_name, a.cluster_id, a.id"
)->fetch_all(MYSQLI_ASSOC);

function day_abbr($csv) {
    return implode(', ', array_map(fn($d) => substr($d, 0, 3), explode(',', $csv)));
}
function weeks_label($csv) {
    return $csv === '1,2,3,4' ? 'Every week' : 'Week ' . str_replace(',', ', ', $csv);
}

include '../includes/admin_header.php';
?>

<style>
.type-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;white-space:nowrap}
.unassigned{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;background:#fff3cd;color:#856404}
.places-preview{font-size:12px;color:#666;max-width:260px}
.checks{display:flex;flex-wrap:wrap;gap:10px 16px}
.checks label{display:flex;align-items:center;gap:6px;font-weight:700;font-size:13px;margin:0;cursor:pointer}
.checks input{width:16px;height:16px;accent-color:#1a73e8}
.hint{font-size:11px;color:#9baabb;font-weight:600;margin-top:4px}
.alert-warn{background:#fff8e1;color:#7a5b00;border:1px solid #ffe082}
.th{padding:12px 16px;font-size:12px;color:#6b7a99}
.td{padding:12px 16px;vertical-align:top}
</style>

<?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($flashWarn): ?><div class="alert alert-warn"><?= htmlspecialchars($flashWarn) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert alert-error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
  <form method="GET" style="display:flex;gap:12px;">
    <select name="driver" class="form-control" style="max-width:260px;margin:0;">
      <option value="">All drivers</option>
      <option value="none" <?= $filter === 'none' ? 'selected' : '' ?>>Unassigned</option>
      <?php foreach ($drivers as $d): ?>
        <option value="<?= (int)$d['id'] ?>" <?= $filter === (string)$d['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
  </form>
  <button class="btn btn-primary" onclick="openModal(null)">+ Add Assignment</button>
</div>

<div class="card" style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="text-align:left;border-bottom:2px solid #e0e8f4;">
        <th class="th">Driver</th>
        <th class="th">Collection</th>
        <th class="th">Cluster</th>
        <th class="th">Weeks</th>
        <th class="th">Days</th>
        <th class="th">Places</th>
        <th class="th">Assigned by</th>
        <th class="th">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:#aaa;">No assignments yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r):
          $colors = $TYPE_COLORS[$r['collection_type']] ?? ['#eee', '#333'];
          $places = array_values(array_filter(array_map('trim', preg_split('/\R/', $r['places']))));
          $payload = [
              'id' => (int)$r['id'],
              'driver_id' => $r['driver_id'] ? (int)$r['driver_id'] : null,
              'collection_type' => $r['collection_type'],
              'cluster_id' => $r['cluster_id'] ? (int)$r['cluster_id'] : null,
              'weeks' => array_map('intval', explode(',', $r['weeks'])),
              'days' => explode(',', $r['days']),
              'time_range' => $r['time_range'] ?? '',
              'places' => implode("\n", $places),
              'notes' => $r['notes'] ?? '',
          ];
      ?>
      <tr style="border-bottom:1px solid #f0f4f8;">
        <td class="td" style="font-weight:700;">
          <?php if ($r['driver_id']): ?>
            <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
          <?php else: ?>
            <span class="unassigned">Unassigned</span>
          <?php endif; ?>
        </td>
        <td class="td"><span class="type-badge" style="background:<?= $colors[0] ?>;color:<?= $colors[1] ?>;"><?= htmlspecialchars($TYPES[$r['collection_type']] ?? $r['collection_type']) ?></span></td>
        <td class="td"><?= htmlspecialchars($r['cluster_name'] ?? '—') ?></td>
        <td class="td"><?= htmlspecialchars(weeks_label($r['weeks'])) ?></td>
        <td class="td"><?= htmlspecialchars(day_abbr($r['days'])) ?></td>
        <td class="td">
          <div class="places-preview" title="<?= htmlspecialchars(implode(', ', $places)) ?>">
            <strong><?= count($places) ?> place<?= count($places) === 1 ? '' : 's' ?></strong><br>
            <?= htmlspecialchars(implode(', ', array_slice($places, 0, 3))) ?><?= count($places) > 3 ? '…' : '' ?>
          </div>
        </td>
        <td class="td"><?= htmlspecialchars($r['assigned_by'] ?? '—') ?></td>
        <td class="td" style="white-space:nowrap;">
          <button class="btn btn-sm" style="background:#f0f4f8;color:#333;"
                  onclick='openModal(<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>)'>Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this assignment?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="assignment_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="keep_filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal-overlay" id="scheduleModal">
  <div class="modal" style="max-width:560px;max-height:92vh;overflow-y:auto;">
    <div class="modal-header" id="modalTitle">Add Assignment</div>
    <form method="POST" id="scheduleForm" onsubmit="return validateForm()">
      <input type="hidden" name="action" id="fAction" value="create">
      <input type="hidden" name="assignment_id" id="fId">
      <input type="hidden" name="keep_filter" value="<?= htmlspecialchars($filter) ?>">

      <div class="modal-body">
        <div class="form-group">
          <label>Driver</label>
          <select name="driver_id" id="fDriver" class="form-control">
            <option value="">Unassigned (not visible to any driver)</option>
            <?php foreach ($drivers as $d): ?>
              <option value="<?= (int)$d['id'] ?>">
                <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?><?= $d['is_suspended'] ? ' (suspended)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Collection type <span class="req">*</span></label>
          <select name="collection_type" id="fType" class="form-control" required>
            <?php foreach ($TYPES as $k => $label): ?>
              <option value="<?= $k ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Cluster</label>
          <select name="cluster_id" id="fCluster" class="form-control" onchange="onClusterChange(this)">
            <option value="">No cluster</option>
            <?php foreach ($clusters as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Picking a cluster fills in its barangays below. You can still edit them.</div>
        </div>

        <div class="form-group">
          <label>Weeks <span class="req">*</span></label>
          <div class="checks">
            <label><input type="checkbox" id="everyWeek" onchange="toggleEvery(this)"> Every week</label>
            <?php for ($w = 1; $w <= 4; $w++): ?>
              <label><input type="checkbox" name="weeks[]" value="<?= $w ?>" onchange="syncEvery()"> Week <?= $w ?></label>
            <?php endfor; ?>
          </div>
          <div class="hint">Week 1 = days 1-7, Week 2 = 8-14, Week 3 = 15-21, Week 4 = 22 to end of month.</div>
        </div>

        <div class="form-group">
          <label>Days <span class="req">*</span></label>
          <div class="checks">
            <?php foreach ($DAYS as $d): ?>
              <label><input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Places <span class="req">*</span> (one per line, in the order they are visited)</label>
          <textarea name="places" id="fPlaces" class="form-control" style="min-height:150px;" placeholder="Market&#10;Quezon St.&#10;Highway"></textarea>
        </div>

        <div class="form-group">
          <label>Time range</label>
          <input type="text" name="time_range" id="fTime" class="form-control" placeholder="e.g. 7:00 AM - 11:00 AM" maxlength="50">
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" id="fNotes" class="form-control" style="min-height:70px;" placeholder="Optional notes for the driver"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn" style="background:#f0f4f8;color:#333;" onclick="closeModal()">Cancel</button>
        <button type="submit" id="submitBtn" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const CLUSTERS = <?= json_encode($clusterMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let lastAuto = '';
const $ = id => document.getElementById(id);

function setChecks(name, values) {
  document.querySelectorAll('input[name="' + name + '[]"]').forEach(c => c.checked = values.includes(c.value));
}
function syncEvery() {
  const all = [...document.querySelectorAll('input[name="weeks[]"]')];
  $('everyWeek').checked = all.every(c => c.checked);
}
function toggleEvery(box) {
  document.querySelectorAll('input[name="weeks[]"]').forEach(c => c.checked = box.checked);
}
function onClusterChange(sel) {
  const c = CLUSTERS[sel.value];
  if (!c) return;
  const ta = $('fPlaces');
  if (ta.value.trim() === '' || ta.value === lastAuto) {
    ta.value = c.barangays;
    lastAuto = c.barangays;
  }
}
function openModal(e) {
  const edit = !!e;
  $('modalTitle').textContent = edit ? 'Edit Assignment' : 'Add Assignment';
  $('submitBtn').textContent = edit ? 'Update' : 'Add Assignment';
  $('fAction').value = edit ? 'update' : 'create';
  $('fId').value = edit ? e.id : '';
  $('fDriver').value = edit && e.driver_id ? e.driver_id : '';
  $('fType').value = edit ? e.collection_type : 'residual';
  $('fCluster').value = edit && e.cluster_id ? e.cluster_id : '';
  setChecks('weeks', edit ? e.weeks.map(String) : []);
  setChecks('days', edit ? e.days : []);
  syncEvery();
  $('fTime').value = edit ? e.time_range : '';
  $('fPlaces').value = edit ? e.places : '';
  $('fNotes').value = edit ? e.notes : '';
  const c = edit && e.cluster_id ? CLUSTERS[e.cluster_id] : null;
  lastAuto = c && c.barangays === (edit ? e.places : '') ? c.barangays : '';
  $('scheduleModal').classList.add('open');
}
function closeModal() { $('scheduleModal').classList.remove('open'); }
function validateForm() {
  if (!document.querySelector('input[name="weeks[]"]:checked')) { alert('Pick at least one week.'); return false; }
  if (!document.querySelector('input[name="days[]"]:checked')) { alert('Pick at least one day.'); return false; }
  if ($('fPlaces').value.trim() === '') { alert('Enter at least one place.'); return false; }
  return true;
}
$('scheduleModal').addEventListener('click', ev => { if (ev.target === ev.currentTarget) closeModal(); });
document.addEventListener('keydown', ev => { if (ev.key === 'Escape') closeModal(); });
</script>

<?php include '../includes/admin_footer.php'; ?>
