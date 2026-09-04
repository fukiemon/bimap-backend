<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Announcements';
$active_nav = 'announcements';

$success = '';
$error = '';

// Create announcement
if (isset($_POST['create_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target = $_POST['target_audience'] ?? 'all';

    if ($title && $message) {
        $admin_id = $_SESSION['admin_id'];
        $stmt = $conn->prepare("INSERT INTO announcement (admin_id, title, message, target_audience) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $admin_id, $title, $message, $target);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO activity_log (action) VALUES ('Announcement published: " . $conn->real_escape_string($title) . "')");
            $success = 'Announcement published successfully!';
        } else {
            $error = 'Failed to publish announcement.';
        }
    } else {
        $error = 'Title and message are required.';
    }
}

// Update announcement
if (isset($_POST['update_announcement'])) {
    $id      = (int)($_POST['announcement_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target  = $_POST['target_audience'] ?? 'all';

    if ($id && $title && $message) {
        $stmt = $conn->prepare("UPDATE announcement SET title=?, message=?, target_audience=? WHERE id=?");
        $stmt->bind_param("sssi", $title, $message, $target, $id);
        if ($stmt->execute()) {
            $conn->query("INSERT INTO activity_log (action) VALUES ('Announcement updated: " . $conn->real_escape_string($title) . "')");
            $success = 'Announcement updated successfully!';
        } else {
            $error = 'Failed to update announcement.';
        }
    } else {
        $error = 'Title and message are required.';
    }
}

// Delete announcement
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM announcement WHERE id=$id");
    $success = 'Announcement deleted.';
}

// Filter
$target_filter = $_GET['target'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = [];
if ($target_filter !== 'all') $where[] = "target_audience = '" . $conn->real_escape_string($target_filter) . "'";
if ($search) $where[] = "(title LIKE '%" . $conn->real_escape_string($search) . "%' OR message LIKE '%" . $conn->real_escape_string($search) . "%')";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$announcements = $conn->query("SELECT a.*, ad.name as admin_name FROM announcement a LEFT JOIN admin ad ON a.admin_id = ad.id $where_sql ORDER BY a.created_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- HEADER ROW -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <form method="GET" style="display:flex;gap:12px;">
    <input type="text" name="search" class="form-control" placeholder="Search announcements..." value="<?= htmlspecialchars($search) ?>" style="max-width:240px;margin:0;">
    <select name="target" class="form-control" style="max-width:160px;margin:0;">
      <option value="all" <?= $target_filter === 'all' ? 'selected' : '' ?>>All Users</option>
      <option value="residents" <?= $target_filter === 'residents' ? 'selected' : '' ?>>Residents Only</option>
      <option value="drivers" <?= $target_filter === 'drivers' ? 'selected' : '' ?>>Drivers Only</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
  </form>
  <button class="btn btn-primary" onclick="openModal()">+ Create Announcement</button>
</div>

<!-- ANNOUNCEMENTS LIST -->
<div style="display:grid;gap:16px;">
  <?php if (empty($announcements)): ?>
  <div class="card" style="text-align:center;padding:40px;color:#aaa;">No announcements found.</div>
  <?php endif; ?>

  <?php foreach ($announcements as $ann): ?>
  <div class="card">
    <div style="padding:20px 24px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="width:38px;height:38px;background:#fff3e0;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="#f57c00"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
            </div>
            <div>
              <div style="font-size:16px;font-weight:900;"><?= htmlspecialchars($ann['title']) ?></div>
              <div style="font-size:11px;color:#aaa;font-weight:600;">
                <!-- data-time holds the UTC ISO timestamp; JS will render it as a live relative time -->
                <span class="time-ago" data-time="<?= htmlspecialchars($ann['created_at']) ?>"></span>
                by <?= htmlspecialchars($ann['admin_name'] ?? 'Admin') ?>
              </div>
            </div>
          </div>
          <p style="font-size:14px;color:#444;line-height:1.6;margin:10px 0 12px 48px;"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
          <div style="margin-left:48px;">
            <span class="badge badge-<?= htmlspecialchars($ann['target_audience']) ?>">
              <?php
              $labels = ['residents' => '👤 Residents Only', 'drivers' => '🚛 Drivers Only', 'all' => '👥 All Users'];
              echo $labels[$ann['target_audience']] ?? 'All Users';
              ?>
            </span>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;">
          <button class="btn btn-sm" style="background:#f0f4f8;color:#333;" onclick="editAnnouncement(<?= htmlspecialchars(json_encode($ann)) ?>)">Edit</button>
          <a href="?delete=<?= $ann['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this announcement?')">Delete</a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- CREATE / EDIT MODAL -->
<div class="modal-overlay" id="announcementModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header" id="modalTitle">Create Announcement</div>
    <form method="POST" id="announcementForm">
      <!-- Hidden fields: only one will be active at a time -->
      <input type="hidden" name="announcement_id" id="announcementId">
      <input type="hidden" name="form_mode" id="formMode" value="create">

      <div class="modal-body">
        <div class="form-group">
          <label>Title <span class="req">*</span></label>
          <input type="text" name="title" id="annTitle" class="form-control" placeholder="Enter announcement title..." required>
        </div>
        <div class="form-group">
          <label>Message <span class="req">*</span></label>
          <textarea name="message" id="annMessage" class="form-control" placeholder="Enter announcement message..." style="min-height:120px;" required></textarea>
        </div>
        <div class="form-group">
          <label>Target Audience</label>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" name="target_audience" id="radResidents" value="residents"> All Residents
            </label>
            <label class="radio-label">
              <input type="radio" name="target_audience" id="radDrivers" value="drivers"> All Drivers
            </label>
            <label class="radio-label">
              <input type="radio" name="target_audience" id="radAll" value="all" checked> All Users
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:#f0f4f8;color:#333;" onclick="closeModal()">Cancel</button>
        <button type="submit" name="create_announcement" id="submitBtn" class="btn btn-primary">Publish Announcement</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Modal helpers ────────────────────────────────────────────────
function openModal() {
  document.getElementById('modalTitle').textContent  = 'Create Announcement';
  document.getElementById('submitBtn').textContent   = 'Publish Announcement';
  document.getElementById('submitBtn').name          = 'create_announcement';
  document.getElementById('announcementId').value    = '';
  document.getElementById('annTitle').value          = '';
  document.getElementById('annMessage').value        = '';
  document.getElementById('radAll').checked          = true;
  document.getElementById('announcementModal').classList.add('open');
}

function closeModal() {
  document.getElementById('announcementModal').classList.remove('open');
}

function editAnnouncement(ann) {
  document.getElementById('modalTitle').textContent  = 'Edit Announcement';
  document.getElementById('submitBtn').textContent   = 'Update Announcement';
  // Switch the submit button name so PHP routes to the update block
  document.getElementById('submitBtn').name          = 'update_announcement';
  document.getElementById('announcementId').value    = ann.id;
  document.getElementById('annTitle').value          = ann.title;
  document.getElementById('annMessage').value        = ann.message;
  document.querySelector(`input[name=target_audience][value="${ann.target_audience}"]`).checked = true;
  document.getElementById('announcementModal').classList.add('open');
}

// Close modal when clicking outside
document.getElementById('announcementModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Real-time relative timestamps ────────────────────────────────
function timeAgo(dateStr) {
  // dateStr is a MySQL DATETIME string, e.g. "2025-04-21 08:30:00"
  // Treat it as local server time (or append 'Z' if your DB stores UTC)
  const date  = new Date(dateStr.replace(' ', 'T'));
  const now   = new Date();
  const secs  = Math.floor((now - date) / 1000);

  if (isNaN(secs) || secs < 0) return 'just now';
  if (secs < 60)   return secs + (secs === 1 ? ' second ago' : ' seconds ago');

  const mins = Math.floor(secs / 60);
  if (mins < 60)   return mins + (mins === 1 ? ' minute ago' : ' minutes ago');

  const hrs = Math.floor(mins / 60);
  if (hrs < 24)    return hrs + (hrs === 1 ? ' hour ago' : ' hours ago');

  const days = Math.floor(hrs / 24);
  if (days < 7)    return days + (days === 1 ? ' day ago' : ' days ago');

  const weeks = Math.floor(days / 7);
  if (weeks < 4)   return weeks + (weeks === 1 ? ' week ago' : ' weeks ago');

  const months = Math.floor(days / 30);
  if (months < 12) return months + (months === 1 ? ' month ago' : ' months ago');

  const years = Math.floor(days / 365);
  return years + (years === 1 ? ' year ago' : ' years ago');
}

function updateAllTimestamps() {
  document.querySelectorAll('.time-ago').forEach(function(el) {
    el.textContent = timeAgo(el.dataset.time);
  });
}

// Run immediately, then refresh every 30 seconds
updateAllTimestamps();
setInterval(updateAllTimestamps, 30000);
</script>

<?php include '../includes/admin_footer.php'; ?>