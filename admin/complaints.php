<?php
session_start();
require_once '../includes/db.php';
requireAdminLogin(); // before ANY handling below

$page_title = 'Complaints';
$active_nav = 'complaints';

// ---------------------------------------------------------------------
// MEDIA ENDPOINTS
//   complaints.php?media=ID  -> the complaint photo
//   complaints.php?video=ID  -> the complaint video
// Photos/videos live in the database as base64 data URIs (photo_data,
// video_data). Older rows may hold a file path instead, so both work.
// ---------------------------------------------------------------------
function send_media_from_value($value, $mimePattern) {
    $value = trim((string)$value);
    if ($value === '') { http_response_code(404); exit('No file.'); }

    // Stored in the database as a data URI
    if (preg_match('#^data:(' . $mimePattern . ');base64,#i', substr($value, 0, 80), $m)) {
        $bytes = base64_decode(substr($value, strpos($value, ',') + 1), true);
        if ($bytes === false) { http_response_code(404); exit('Bad file.'); }

        $total = strlen($bytes);
        $start = 0;
        $end   = $total - 1;

        // Range support so videos can be played and skipped through
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $r)) {
            if ($r[1] !== '') {
                $start = (int)$r[1];
                if ($r[2] !== '') $end = min((int)$r[2], $end);
            } elseif ($r[2] !== '') {
                $start = max(0, $total - (int)$r[2]);
            }
            if ($start > $end || $start >= $total) {
                http_response_code(416);
                header("Content-Range: bytes */$total");
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$total");
        }

        header('Content-Type: ' . strtolower($m[1]));
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        echo substr($bytes, $start, $end - $start + 1);
        exit;
    }

    // Full URL (for example cloud storage)
    if (preg_match('#^https?://#i', $value)) {
        header('Location: ' . $value);
        exit;
    }

    // Legacy: relative file path on the server
    header('Location: ../' . ltrim($value, '/'));
    exit;
}

if (isset($_GET['media'])) {
    $id = (int)$_GET['media'];
    $s = $conn->prepare("SELECT * FROM complaint WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) { http_response_code(404); exit('Not found.'); }
    $photoVal = !empty($row['photo_data']) ? $row['photo_data'] : ($row['photo'] ?? '');
    send_media_from_value($photoVal, 'image/(?:jpeg|png|gif|webp)');
}

if (isset($_GET['video'])) {
    $id = (int)$_GET['video'];
    $s = $conn->prepare("SELECT * FROM complaint WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) { http_response_code(404); exit('Not found.'); }

    $videoVal = !empty($row['video_data']) ? $row['video_data'] : '';
    // Legacy rows kept the video path inside the concern text as [Video: path]
    if ($videoVal === '' && preg_match('/\[Video:\s*([^\]]+)\]/i', (string)($row['concern'] ?? ''), $vm)) {
        $videoVal = trim($vm[1]);
    }
    send_media_from_value($videoVal, 'video/(?:mp4|webm|quicktime|3gpp)');
}

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

// Filter (columns are prefixed with c. / r. because complaint is joined to resident)
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = [];
if ($status_filter !== 'all') $where[] = "c.status = ?";
if ($search) {
    $where[] = "(c.resident_name LIKE ? OR c.concern LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$params = [];
$types = '';

if ($status_filter !== 'all') { $params[] = $status_filter; $types .= 's'; }
if ($search) {
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$total_stmt = $conn->prepare(
    "SELECT COUNT(*) as c
     FROM complaint c
     LEFT JOIN resident r ON r.id = c.resident_id
     $where_sql"
);
if (!empty($params)) $total_stmt->bind_param($types, ...$params);
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['c'];
$pages = ceil($total / $per_page);
$offset = ($page - 1) * $per_page;

// The list never loads the heavy photo/video data. It only asks the database
// whether each one exists (has_photo, has_photo_data, has_video_data).
$BLOBS = ['photo', 'photo_data', 'video_data'];
$allCols = [];
$colRes = $conn->query("SHOW COLUMNS FROM complaint");
while ($cr = $colRes->fetch_assoc()) $allCols[] = $cr['Field'];

$select = [];
foreach ($allCols as $col) {
    if (!in_array($col, $BLOBS, true)) $select[] = "c.`$col`";
}
foreach (array_intersect($BLOBS, $allCols) as $b) {
    $select[] = "(c.`$b` IS NOT NULL AND c.`$b` <> '') AS `has_$b`";
}

// Joined with the resident's saved profile (password is NOT selected).
$stmt = $conn->prepare(
    "SELECT " . implode(', ', $select) . ",
            r.first_name         AS r_first_name,
            r.last_name          AS r_last_name,
            r.email              AS r_email,
            r.phone              AS r_phone,
            r.barangay           AS r_barangay,
            r.household_address  AS r_household_address
     FROM complaint c
     LEFT JOIN resident r ON r.id = c.resident_id
     $where_sql
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?"
);
$limit_types = $types . 'ii';
$limit_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($limit_types, ...$limit_params);
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
      <span style="margin-left:auto;font-size:13px;color:#888;font-weight:700;"><?= (int)$total ?> complaint(s) found</span>
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
          <th>Barangay</th>
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
            $concern = $c['concern'] ?? '';

            // barangay and waste_type are real columns on `complaint`. Fall back to the
            // old [Location:]/[Type:] tags for legacy rows that predate those columns.
            preg_match('/\[Location:\s*([^\]]+)\]/i', $concern, $loc_match);
            preg_match('/\[Type:\s*([^\]]+)\]/i', $concern, $type_match);

            $barangay   = !empty($c['barangay'])   ? $c['barangay']   : ($loc_match[1]  ?? 'Not specified');
            $waste_type = !empty($c['waste_type']) ? $c['waste_type'] : ($type_match[1] ?? '—');

            $has_video = !empty($c['has_video_data']) || (bool) preg_match('/\[Video:\s*([^\]]+)\]/i', $concern);
            $has_photo = !empty($c['has_photo_data']) || !empty($c['has_photo']);

            $description = preg_replace('/\[Location:[^\]]*\]\s*/i', '', $concern);
            $description = preg_replace('/\[Type:[^\]]*\]\s*/i', '', $description);
            $description = preg_replace('/\[Video:[^\]]*\]\s*/i', '', $description);
            $description = trim($description);

            // Prefer the resident's saved profile; fall back to what was stored on the complaint.
            $profile_name = trim(($c['r_first_name'] ?? '') . ' ' . ($c['r_last_name'] ?? ''));
            $display_name = $profile_name !== '' ? $profile_name : ($c['resident_name'] ?? 'Unknown');
            $display_phone = !empty($c['r_phone']) ? $c['r_phone'] : ($c['resident_phone'] ?? '—');

            // Data sent to the View popup (the photo and video are loaded
            // separately from complaints.php?media=ID and ?video=ID).
            $payload = $c;
            unset($payload['has_photo_data'], $payload['has_video_data']);
            $payload['concern']    = $description;
            $payload['barangay']   = $barangay === 'Not specified' ? '' : $barangay;
            $payload['waste_type'] = $waste_type === '—' ? '' : $waste_type;
            $payload['has_photo']  = $has_photo;
            $payload['has_video']  = $has_video;
        ?>
        <tr>
          <td style="color:#aaa;font-size:12px;">#<?= (int)$c['id'] ?></td>
          <td>
            <span class="badge <?= $c['status'] === 'Pending' ? 'badge-pending' : 'badge-resolved' ?>">
              <?= htmlspecialchars($c['status']) ?>
            </span>
          </td>
          <td>
            <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($display_name) ?></div>
            <div style="font-size:12px;color:#888;"><?= htmlspecialchars($display_phone) ?></div>
          </td>
          <td style="font-size:13px;"><?= htmlspecialchars($barangay) ?></td>
          <td style="font-size:13px;"><?= htmlspecialchars($waste_type) ?></td>
          <td style="font-size:13px;max-width:260px;" title="<?= htmlspecialchars($description) ?>">
            <?= htmlspecialchars(mb_substr($description, 0, 70)) ?><?= mb_strlen($description) > 70 ? '...' : '' ?>
            <?php if ($has_photo): ?>
              <span style="display:inline-block;margin-left:4px;background:#e0f2fe;color:#0369a1;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;">📷 Photo</span>
            <?php endif; ?>
            <?php if ($has_video): ?>
              <span style="display:inline-block;margin-left:4px;background:#ede9fe;color:#7c3aed;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;">🎥 Video</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#888;white-space:nowrap;">
            <?= date('M d, Y', strtotime($c['created_at'])) ?>
          </td>
          <td>
            <button class="btn-view" onclick="viewComplaint(<?= htmlspecialchars(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES) ?>)">View</button>
            <?php if ($c['status'] === 'Pending'): ?>
            <button class="btn btn-success btn-sm" style="margin-left:4px;"
                    onclick="resolveComplaint(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($display_name, JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES) ?>)">
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
// Escapes text before it is placed into HTML (prevents injected markup from running).
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, ch =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

function viewComplaint(c) {
  const barangay    = c.barangay    || '—';
  const wasteType   = c.waste_type  || '—';
  const description = (c.concern || '').trim();

  const fullName = [c.r_first_name, c.r_last_name].filter(Boolean).join(' ')
                   || c.resident_name || '—';

  const residentHTML = `
    <div style="background:#f4f8ff;padding:16px;border-radius:10px;border:1px solid #dbe7ff;">
      <strong>Resident Details</strong>
      <div style="margin-top:8px;display:grid;gap:4px;font-size:14px;">
        <div><strong>Name:</strong> ${esc(fullName)}</div>
        <div><strong>Phone:</strong> ${esc(c.r_phone || c.resident_phone || '—')}</div>
        <div><strong>Email:</strong> ${esc(c.r_email || '—')}</div>
        <div><strong>Home Barangay:</strong> ${esc(c.r_barangay || '—')}</div>
        <div><strong>Household Address:</strong> ${esc(c.r_household_address || '—')}</div>
      </div>
    </div>`;

  const mediaUrl = 'complaints.php?media=' + encodeURIComponent(c.id);
  const videoUrl = 'complaints.php?video=' + encodeURIComponent(c.id);

  let photoHTML = '';
  if (c.has_photo) {
    photoHTML = `
      <div style="margin:15px 0;">
        <strong>📷 Attached Photo:</strong><br>
        <img src="${mediaUrl}"
             style="max-width:100%;border-radius:10px;border:2px solid #e0e8f4;margin-top:8px;cursor:pointer;"
             onclick="window.open('${mediaUrl}', '_blank')"
             onerror="this.outerHTML='<div style=&quot;color:#b91c1c;font-size:13px;margin-top:8px;&quot;>The photo could not be loaded (the file may no longer exist on the server).</div>'"
             alt="Complaint Photo">
      </div>`;
  }

  let videoHTML = '';
  if (c.has_video) {
    videoHTML = `
      <div style="margin:15px 0;">
        <strong>🎥 Attached Video:</strong><br>
        <video controls playsinline preload="metadata"
               style="max-width:100%;width:100%;border-radius:10px;border:2px solid #ede9fe;margin-top:8px;background:#000;">
          <source src="${videoUrl}">
          Your browser does not support the video tag.
        </video>
        <div style="margin-top:6px;">
          <a href="${videoUrl}" target="_blank" download
             style="font-size:12px;color:#7c3aed;font-weight:700;text-decoration:none;">
            ⬇️ Download Video
          </a>
        </div>
      </div>`;
  }

  document.getElementById('viewModalTitle').textContent = `Complaint #${c.id} — ${c.status}`;

  document.getElementById('viewModalBody').innerHTML = `
    <div style="display:grid;gap:14px;line-height:1.6;">
      ${residentHTML}
      <div><strong>Barangay:</strong> ${esc(barangay)}</div>
      <div><strong>Waste Type:</strong> ${esc(wasteType)}</div>
      <div><strong>Status:</strong>
        <span class="badge ${c.status === 'Pending' ? 'badge-pending' : 'badge-resolved'}">${esc(c.status)}</span>
      </div>
      <div><strong>Date Filed:</strong> ${esc(c.created_at)}</div>
      ${photoHTML}
      ${videoHTML}
      <div style="background:#f8faff;padding:16px;border-radius:10px;border:1px solid #e3ecff;">
        <strong>Description:</strong><br>
        <span style="font-size:14.5px;line-height:1.65;white-space:pre-wrap;">${esc(description) || '—'}</span>
      </div>
      ${c.admin_notes ? `
      <div style="background:#f0fff4;padding:16px;border-radius:10px;border:1px solid #c3e6cb;">
        <strong>Admin Notes:</strong><br>
        <span style="white-space:pre-wrap;">${esc(c.admin_notes)}</span>
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
