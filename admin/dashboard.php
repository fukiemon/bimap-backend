<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Admin Dashboard';
$active_nav = 'dashboard';

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

    $_SESSION['success'] = 'Complaint marked as resolved.';
    header("Location: dashboard.php");
    exit;
}

$total_complaints    = $conn->query("SELECT COUNT(*) as c FROM complaint")->fetch_assoc()['c'] ?? 0;
$pending_complaints  = $conn->query("SELECT COUNT(*) as c FROM complaint WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$resolved_complaints = $conn->query("SELECT COUNT(*) as c FROM complaint WHERE status='Resolved'")->fetch_assoc()['c'] ?? 0;
$total_announcements = $conn->query("SELECT COUNT(*) as c FROM announcement")->fetch_assoc()['c'] ?? 0;

$complaints   = $conn->query("SELECT * FROM complaint ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
$activities   = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$announcements = $conn->query("SELECT * FROM announcement ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:#e3f2fd;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#1a73e8"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/></svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $total_complaints ?></div>
      <div class="lbl">Total Complaints</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fff8e1;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#f9a825"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/></svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $pending_complaints ?></div>
      <div class="lbl">Pending Complaints</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f5e9;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#2e7d32"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $resolved_complaints ?></div>
      <div class="lbl">Resolved Complaints</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fce4ec;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#c2185b"><path d="M18 11v2H6v-2H4v2c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-2h-2zM12 2L6.5 8H10v7h4V8h3.5L12 2z"/></svg>
    </div>
    <div class="stat-info">
      <div class="num"><?= $total_announcements ?></div>
      <div class="lbl">Total Announcements</div>
    </div>
  </div>
</div>

<!-- COMPLAINTS + ACTIVITIES -->
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;margin-bottom:20px;">

  <!-- Recent Complaints -->
  <div class="card">
    <div class="card-header">
      <h2>Recent Complaints</h2>
      <a href="complaints.php" class="btn btn-primary btn-sm">View All Complaints</a>
    </div>
    <div class="card-body">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Status</th>
            <th>Resident</th>
            <th>Location</th>
            <th>Concern</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($complaints as $c):
            $concern = $c['concern'] ?? '';
            preg_match('/\[Location:\s*([^\]]+)\]/i', $concern, $loc_match);
            $location = $loc_match[1] ?? 'Not specified';

            $concern_clean = preg_replace('/\[\s*[^]]+?\s*:\s*[^]]+?\s*\]\s*/i', '', $concern);
            $concern_clean = trim(preg_replace('/\s+/', ' ', $concern_clean));
            if (empty($concern_clean)) $concern_clean = $concern;

            $max_length = 50;
            if (strlen($concern_clean) > $max_length) {
              $preview = substr($concern_clean, 0, $max_length);
              $last_space = strrpos($preview, ' ');
              $concern_preview = ($last_space !== false) ? substr($preview, 0, $last_space) . '...' : $preview . '...';
            } else {
              $concern_preview = $concern_clean;
            }

            $has_video = preg_match('/\[Video:\s*([^\]]+)\]/i', $concern);
          ?>
          <tr>
            <td><strong>#<?= htmlspecialchars($c['id'] ?? '—') ?></strong></td>
            <td>
              <span class="badge <?= ($c['status'] ?? 'Pending') === 'Pending' ? 'badge-pending' : 'badge-resolved' ?>">
                <?= htmlspecialchars($c['status'] ?? 'Pending') ?>
              </span>
            </td>
            <td>
              <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($c['resident_name'] ?? 'Unknown Resident') ?></div>
              <div style="font-size:12.5px;color:#555;"><?= htmlspecialchars($c['resident_phone'] ?? '—') ?></div>
            </td>
            <td style="font-size:13.5px;"><?= htmlspecialchars($location) ?></td>
            <td style="max-width:230px;line-height:1.45;">
              <?= htmlspecialchars($concern_preview) ?>
              <?php if ($has_video): ?>
                <span style="display:inline-block;margin-left:4px;background:#ede9fe;color:#7c3aed;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;">🎥 Video</span>
              <?php endif; ?>
            </td>
            <td style="font-size:13px;color:#555;"><?= date('M d, Y', strtotime($c['created_at'] ?? 'now')) ?></td>
            <td>
              <button class="btn btn-sm btn-primary" onclick="viewComplaint(<?= htmlspecialchars(json_encode($c)) ?>)">View</button>
              <?php if (($c['status'] ?? '') === 'Pending'): ?>
                <button class="btn btn-sm btn-success" style="margin-left:4px;"
                        onclick="resolveComplaint(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['resident_name'] ?? 'Resident')) ?>')">
                  Resolve
                </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Activities -->
  <div class="card">
    <div class="card-header"><h2>Recent Activities</h2></div>
    <div class="card-body">
      <?php foreach ($activities as $a): ?>
      <div class="activity-item">
        <div class="activity-bell">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="#1a73e8"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
        </div>
        <div style="flex:1">
          <div class="activity-text"><?= htmlspecialchars($a['action'] ?? 'System activity') ?></div>
          <div class="activity-time"><?= timeAgo($a['created_at'] ?? date('Y-m-d H:i:s')) ?></div>
        </div>
        <a href="complaints.php" class="btn-view">View →</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ANNOUNCEMENTS -->
<div class="card">
  <div class="card-header">
    <h2>Recent Announcements</h2>
    <button class="btn btn-primary btn-sm" onclick="openAnnouncementModal()">+ Create Announcement</button>
  </div>
  <div class="card-body">
    <?php foreach ($announcements as $ann): ?>
    <div class="activity-item">
      <div class="activity-bell" style="background:#fff3e0;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="#f57c00"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      </div>
      <div style="flex:1">
        <div class="activity-text"><?= htmlspecialchars($ann['title'] ?? 'Untitled') ?></div>
        <div class="activity-time"><?= htmlspecialchars(substr($ann['message'] ?? '', 0, 65)) ?>...</div>
        <div style="margin-top:3px;">
          <span class="badge badge-<?= $ann['target_audience'] ?? 'all' ?>">
            <?= ucfirst($ann['target_audience'] ?? 'all') ?>
          </span>
        </div>
      </div>
      <div style="font-size:11px;color:#aaa;text-align:right;">
        <?= timeAgo($ann['created_at'] ?? date('Y-m-d H:i:s')) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- VIEW COMPLAINT MODAL -->
<div class="modal-overlay" id="viewModal">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header" id="viewModalTitle">Complaint Details</div>
    <div class="modal-body" id="viewModalBody" style="max-height:75vh;overflow-y:auto;"></div>
    <div class="modal-footer">
      <button type="button" class="btn" onclick="closeViewModal()">Close</button>
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
        <button type="button" class="btn" onclick="closeResolveModal()">Cancel</button>
        <button type="submit" class="btn btn-success">Mark as Resolved</button>
      </div>
    </form>
  </div>
</div>

<!-- ANNOUNCEMENT MODAL -->
<div class="modal-overlay" id="announcementModal">
  <div class="modal">
    <div class="modal-header">Create Announcement</div>
    <form method="POST" action="announcements.php">
      <div class="modal-body">
        <div id="modalAlert"></div>
        <div class="form-group">
          <label>Title <span class="req">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="Enter announcement title..." required>
        </div>
        <div class="form-group">
          <label>Message <span class="req">*</span></label>
          <textarea name="message" class="form-control" placeholder="Enter announcement message..." required></textarea>
        </div>
        <div class="form-group">
          <label>Target Audience</label>
          <div class="radio-group">
            <label class="radio-label"><input type="radio" name="target_audience" value="residents"> All Residents</label>
            <label class="radio-label"><input type="radio" name="target_audience" value="drivers"> All Drivers</label>
            <label class="radio-label"><input type="radio" name="target_audience" value="all" checked> All Users</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeAnnouncementModal()">Cancel</button>
        <button type="submit" name="create_announcement" class="btn btn-primary">Publish Announcement</button>
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

  // Photo HTML
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

  // Video HTML
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
  // Pause video when closing to stop audio playing in background
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

document.querySelectorAll('.modal-overlay').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) {
      // Pause video if closing view modal by clicking overlay
      const video = this.querySelector('video');
      if (video) { video.pause(); video.currentTime = 0; }
      this.classList.remove('open');
    }
  });
});

function openAnnouncementModal()  { document.getElementById('announcementModal').classList.add('open'); }
function closeAnnouncementModal() { document.getElementById('announcementModal').classList.remove('open'); }
</script>

<?php include '../includes/admin_footer.php'; ?>