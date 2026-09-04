<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Complaints';
$active_nav = 'complaints';

$success = '';
$error = '';

// Resolve complaint
if (isset($_POST['resolve_id'])) {
    $id = (int)$_POST['resolve_id'];
    $notes = trim($_POST['admin_notes'] ?? '');

    $stmt = $conn->prepare("UPDATE complaint SET status='Resolved', admin_notes=? WHERE id=?");
    $stmt->bind_param("si", $notes, $id);
    $stmt->execute();

    $stmt2 = $conn->prepare("SELECT resident_name FROM complaint WHERE id=?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $complaint = $stmt2->get_result()->fetch_assoc();

    if ($complaint) {
        $action = $complaint['resident_name'] . " Complaint was resolved";
        $stmt3 = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
        $stmt3->bind_param("s", $action);
        $stmt3->execute();
    }

    $success = 'Complaint marked as resolved.';
}

// Filter
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = [];
if ($status_filter !== 'all') $where[] = "status = ?";
if ($search) $where[] = "(resident_name LIKE ? OR concern LIKE ?)";

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$params = [];
$types = '';

if ($status_filter !== 'all') { $params[] = $status_filter; $types .= 's'; }
if ($search) {
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$total_stmt = $conn->prepare("SELECT COUNT(*) as c FROM complaint $where_sql");
if (!empty($params)) $total_stmt->bind_param($types, ...$params);
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['c'];
$pages = ceil($total / $per_page);
$offset = ($page - 1) * $per_page;

$stmt = $conn->prepare("SELECT * FROM complaint $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$limit_types = $types . 'ii';
$limit_params = array_merge($params, [$per_page, $offset]);
if (!empty($limit_params)) $stmt->bind_param($limit_types, ...$limit_params);
$stmt->execute();
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- FILTERS -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="padding:16px 20px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
      <input type="text" name="search" class="form-control" placeholder="Search name or concern..."
             value="<?= htmlspecialchars($search) ?>" style="max-width:280px;margin:0;">
      <select name="status" class="form-control" style="max-width:160px;margin:0;">
        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
        <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="complaints.php" class="btn" style="background:#f0f4f8;color:#333;">Reset</a>
      <span style="margin-left:auto;font-size:13px;color:#888;font-weight:700;"><?= $total ?> complaint(s) found</span>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header"><h2>All Complaints</h2></div>
  <div class="card-body">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Status</th>
          <th>Resident</th>
          <th>Location</th>
          <th>Type</th>
          <th>Concern</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($complaints)): ?>
        <tr><td colspan="8" style="text-align:center;color:#aaa;padding:30px;">No complaints found.</td></tr>
        <?php endif; ?>

        <?php foreach ($complaints as $c):
            preg_match('/\[Location:\s*([^\]]+)\]/i', $c['concern'], $loc_match);
            preg_match('/\[Type:\s*([^\]]+)\]/i', $c['concern'], $type_match);

            $location   = $loc_match[1]  ?? '—';
            $waste_type = $type_match[1] ?? '—';

            $has_video = (bool) preg_match('/\[Video:\s*([^\]]+)\]/i', $c['concern']);

            $description = preg_replace('/\[Location:[^\]]*\]\s*/i', '', $c['concern']);
            $description = preg_replace('/\[Type:[^\]]*\]\s*/i', '', $description);
            $description = preg_replace('/\[Video:[^\]]*\]\s*/i', '', $description);
            $description = trim($description);
        ?>
        <tr>
          <td style="color:#aaa;font-size:12px;">#<?= $c['id'] ?></td>
          <td>
            <span class="badge <?= $c['status'] === 'Pending' ? 'badge-pending' : 'badge-resolved' ?>">
              <?= $c['status'] ?>
            </span>
          </td>
          <td>
            <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($c['resident_name'] ?? 'Unknown') ?></div>
            <div style="font-size:12px;color:#888;"><?= htmlspecialchars($c['resident_phone'] ?? '—') ?></div>
          </td>
          <td style="font-size:13px;"><?= htmlspecialchars($location) ?></td>
          <td style="font-size:13px;"><?= htmlspecialchars($waste_type) ?></td>
          <td style="font-size:13px;max-width:260px;" title="<?= htmlspecialchars($description) ?>">
            <?= htmlspecialchars(substr($description, 0, 70)) ?><?= strlen($description) > 70 ? '...' : '' ?>
            <?php if ($has_video): ?>
              <span style="display:inline-block;margin-left:4px;background:#ede9fe;color:#7c3aed;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;">🎥 Video</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#888;white-space:nowrap;">
            <?= date('M d, Y', strtotime($c['created_at'])) ?>
          </td>
          <td>
            <button class="btn-view" onclick="viewComplaint(<?= htmlspecialchars(json_encode($c)) ?>)">View</button>
            <?php if ($c['status'] === 'Pending'): ?>
            <button class="btn btn-success btn-sm" style="margin-left:4px;"
                    onclick="resolveComplaint(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['resident_name'] ?? 'Resident')) ?>')">
              Resolve
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div style="padding:16px 16px 0;">
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"
             class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- VIEW COMPLAINT MODAL -->
<div class="modal-overlay" id="viewModal">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header" id="viewModalTitle">Complaint Details</div>
    <div class="modal-body" id="viewModalBody" style="max-height:75vh;overflow-y:auto;"></div>
    <div class="modal-footer">
      <button type="button" class="btn" style="background:#f0f4f8;color:#333;" onclick="closeViewModal()">Close</button>
    </div>
  </div>
</div>

<!-- RESOLVE MODAL -->
<div class="modal-overlay" id="resolveModal">
  <div class="modal">
    <div class="modal-header">Resolve Complaint</div>
    <form method="POST">
      <div class="modal-body">
        <p style="margin-bottom:16px;font-size:14px;">Resolving complaint from <strong id="resolveName"></strong>.</p>
        <input type="hidden" name="resolve_id" id="resolveId">
        <div class="form-group">
          <label>Admin Notes (optional)</label>
          <textarea name="admin_notes" class="form-control" placeholder="Add notes about resolution..." rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background:#f0f4f8;color:#333;" onclick="closeResolveModal()">Cancel</button>
        <button type="submit" class="btn btn-success">Mark as Resolved</button>
      </div>
    </form>
  </div>
</div>

<script>
function viewComplaint(c) {
  const concernText = c.concern || '';

  const locMatch   = concernText.match(/\[Location:\s*([^\]]+)\]/i);
  const typeMatch  = concernText.match(/\[Type:\s*([^\]]+)\]/i);
  const videoMatch = concernText.match(/\[Video:\s*([^\]]+)\]/i);

  const location  = locMatch  ? locMatch[1]  : '—';
  const wasteType = typeMatch ? typeMatch[1] : '—';
  const videoPath = videoMatch ? videoMatch[1].trim() : null;

  let description = concernText
    .replace(/\[Location:[^\]]*\]\s*/i, '')
    .replace(/\[Type:[^\]]*\]\s*/i, '')
    .replace(/\[Video:[^\]]*\]\s*/i, '')
    .trim();

  // Photo
  let photoHTML = '';
  if (c.photo && c.photo.trim() !== '') {
    photoHTML = `
      <div style="margin:15px 0;">
        <strong>📷 Attached Photo:</strong><br>
        <img src="../${c.photo}"
             style="max-width:100%;border-radius:10px;border:2px solid #e0e8f4;margin-top:8px;cursor:pointer;"
             onclick="window.open('../${c.photo}', '_blank')"
             alt="Complaint Photo">
      </div>`;
  }

  // Video
  let videoHTML = '';
  if (videoPath) {
    videoHTML = `
      <div style="margin:15px 0;">
        <strong>🎥 Attached Video:</strong><br>
        <video controls playsinline
               style="max-width:100%;width:100%;border-radius:10px;border:2px solid #ede9fe;margin-top:8px;background:#000;">
          <source src="../${videoPath}">
          Your browser does not support the video tag.
        </video>
        <div style="margin-top:6px;">
          <a href="../${videoPath}" target="_blank" download
             style="font-size:12px;color:#7c3aed;font-weight:700;text-decoration:none;">
            ⬇️ Download Video
          </a>
        </div>
      </div>`;
  }

  document.getElementById('viewModalTitle').textContent = `Complaint #${c.id} — ${c.status}`;

  document.getElementById('viewModalBody').innerHTML = `
    <div style="display:grid;gap:14px;line-height:1.6;">
      <div><strong>Resident:</strong> ${c.resident_name} (${c.resident_phone || '—'})</div>
      <div><strong>Location:</strong> ${location}</div>
      <div><strong>Waste Type:</strong> ${wasteType}</div>
      <div><strong>Status:</strong>
        <span class="badge ${c.status === 'Pending' ? 'badge-pending' : 'badge-resolved'}">${c.status}</span>
      </div>
      <div><strong>Date Filed:</strong> ${c.created_at}</div>
      ${photoHTML}
      ${videoHTML}
      <div style="background:#f8faff;padding:16px;border-radius:10px;border:1px solid #e3ecff;">
        <strong>Description:</strong><br>
        <span style="font-size:14.5px;line-height:1.65;white-space:pre-wrap;">${description || '—'}</span>
      </div>
      ${c.admin_notes ? `
      <div style="background:#f0fff4;padding:16px;border-radius:10px;border:1px solid #c3e6cb;">
        <strong>Admin Notes:</strong><br>
        <span style="white-space:pre-wrap;">${c.admin_notes}</span>
      </div>` : ''}
    </div>
  `;

  document.getElementById('viewModal').classList.add('open');
}

function closeViewModal() {
  const video = document.querySelector('#viewModalBody video');
  if (video) { video.pause(); video.currentTime = 0; }
  document.getElementById('viewModal').classList.remove('open');
}

function resolveComplaint(id, name) {
  document.getElementById('resolveId').value = id;
  document.getElementById('resolveName').textContent = name;
  document.getElementById('resolveModal').classList.add('open');
}

function closeResolveModal() {
  document.getElementById('resolveModal').classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', function(e) {
    if (e.target === this) {
      const video = this.querySelector('video');
      if (video) { video.pause(); video.currentTime = 0; }
      this.classList.remove('open');
    }
  });
});
</script>

<?php include '../includes/admin_footer.php'; ?>