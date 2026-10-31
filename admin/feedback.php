<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Collection Feedback';
$active_nav = 'feedback';

/* ------------------------------------------------------------------
   CONFIG
   APP_TZ = the timezone the admin sees ("Today" follows this one).
   DB_TZ  = the timezone your database stores feedback.created_at in.
            Render databases are normally 'UTC'. If you find the saved
            times already match Philippine time, change this to 'Asia/Manila'.
------------------------------------------------------------------- */
$APP_TZ   = 'Asia/Manila';
$DB_TZ    = 'UTC';
$PER_PAGE = 20;

/* ---------- helpers ---------- */
function valid_date($s) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return $d && $d->format('Y-m-d') === $s;
}
// local day start (Y-m-d) -> DB timezone datetime string
function to_db($localDate, $appTz, $dbTz) {
    $d = new DateTime($localDate . ' 00:00:00', new DateTimeZone($appTz));
    $d->setTimezone(new DateTimeZone($dbTz));
    return $d->format('Y-m-d H:i:s');
}
// DB datetime string -> local display string
function to_local($dbTime, $appTz, $dbTz) {
    $d = new DateTime($dbTime, new DateTimeZone($dbTz));
    $d->setTimezone(new DateTimeZone($appTz));
    return $d->format('M d, Y · g:i A');
}
// run a prepared SELECT and return all rows
function fetch_rows($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ---------- date range ---------- */
$tz        = new DateTimeZone($APP_TZ);
$now       = new DateTime('now', $tz);
$today     = $now->format('Y-m-d');
$yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
$week_from = (clone $now)->modify('-6 days')->format('Y-m-d');
$month_from = $now->format('Y-m-01');

$range = $_GET['range'] ?? '';
if ($range === 'today') {
    $from = $to = $today;
} elseif ($range === 'yesterday') {
    $from = $to = $yesterday;
} elseif ($range === '7days') {
    $from = $week_from; $to = $today;
} elseif ($range === 'month') {
    $from = $month_from; $to = $today;
} else {
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';
    if (!valid_date($from) && !valid_date($to)) {
        $from = $to = $today;              // default: today
    } else {
        if (!valid_date($from)) $from = $to;
        if (!valid_date($to))   $to   = $from;
    }
}
if ($from > $to) { [$from, $to] = [$to, $from]; }

// range is [start, end) in DB time so created_at can use its index
$start = to_db($from, $APP_TZ, $DB_TZ);
$end   = to_db((new DateTime($to, $tz))->modify('+1 day')->format('Y-m-d'), $APP_TZ, $DB_TZ);

/* ---------- other filters ---------- */
$barangay = trim($_GET['barangay'] ?? '');
$tab      = (($_GET['tab'] ?? 'no') === 'yes') ? 'yes' : 'no';
$page     = max(1, (int)($_GET['page'] ?? 1));

$barangays = fetch_rows($conn,
    "SELECT DISTINCT barangay FROM feedback WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay");

/* ---------- stat cards (date range + barangay filter) ---------- */
$where  = "created_at >= ? AND created_at < ?";
$types  = "ss";
$params = [$start, $end];
if ($barangay !== '') {
    $where  .= " AND barangay = ?";
    $types  .= "s";
    $params[] = $barangay;
}

$counts = fetch_rows($conn,
    "SELECT collected, COUNT(*) AS c FROM feedback WHERE $where GROUP BY collected", $types, $params);
$yes_total = 0; $no_total = 0;
foreach ($counts as $row) {
    if ($row['collected'] === 'yes') $yes_total = (int)$row['c'];
    if ($row['collected'] === 'no')  $no_total  = (int)$row['c'];
}
$total = $yes_total + $no_total;
$rate  = $total > 0 ? round($yes_total / $total * 100) : 0;

/* ---------- by-barangay summary (date range only, so all barangays show) ---------- */
$by_barangay = fetch_rows($conn,
    "SELECT barangay,
            SUM(collected = 'yes') AS yes_c,
            SUM(collected = 'no')  AS no_c,
            COUNT(*)               AS total_c
       FROM feedback
      WHERE created_at >= ? AND created_at < ?
        AND barangay IS NOT NULL AND barangay <> ''
      GROUP BY barangay
      ORDER BY no_c DESC, total_c DESC, barangay ASC",
    "ss", [$start, $end]);

/* ---------- list for the active tab ---------- */
$tab_total = ($tab === 'yes') ? $yes_total : $no_total;
$pages     = max(1, (int)ceil($tab_total / $PER_PAGE));
$page      = min($page, $pages);
$offset    = ($page - 1) * $PER_PAGE;

$list = fetch_rows($conn,
    "SELECT id, resident_name, barangay, created_at
       FROM feedback
      WHERE collected = ? AND $where
      ORDER BY created_at DESC
      LIMIT $PER_PAGE OFFSET $offset",
    "s" . $types, array_merge([$tab], $params));

/* ---------- url helper (keeps filters) ---------- */
function url_with($overrides = []) {
    global $from, $to, $barangay, $tab, $page;
    $q = array_merge([
        'from' => $from, 'to' => $to, 'barangay' => $barangay,
        'tab'  => $tab,  'page' => 1,
    ], $overrides);
    if ($q['barangay'] === '') unset($q['barangay']);
    return 'feedback.php?' . http_build_query($q);
}

$is_today     = ($from === $today      && $to === $today);
$is_yesterday = ($from === $yesterday  && $to === $yesterday);
$is_week      = ($from === $week_from  && $to === $today);
$is_month     = ($from === $month_from && $to === $today);

include '../includes/admin_header.php';
?>

<style>
  .fb-filters { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; }
  .fb-filters .form-group { margin:0; }
  .fb-filters label { display:block; font-size:12.5px; font-weight:700; margin-bottom:5px; color:#555; }
  .fb-quick { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
  .fb-quick a { text-decoration:none; }
  .fb-quick .on { background:#1a73e8; color:#fff; border-color:#1a73e8; }
  .fb-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:16px; margin-bottom:20px; }
  .fb-tabs { display:flex; gap:6px; border-bottom:2px solid #e8eef8; margin-bottom:16px; }
  .fb-tabs a { padding:10px 16px; font-weight:700; font-size:14px; text-decoration:none; color:#667; border-bottom:3px solid transparent; margin-bottom:-2px; }
  .fb-tabs a.active-no  { color:#c62828; border-color:#c62828; }
  .fb-tabs a.active-yes { color:#2e7d32; border-color:#2e7d32; }
  .fb-count { display:inline-block; min-width:22px; padding:1px 8px; margin-left:6px; border-radius:10px; background:#eef2f9; font-size:12px; color:#445; text-align:center; }
  .fb-flag { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11.5px; font-weight:700; background:#ffebee; color:#c62828; }
  .fb-ok   { color:#888; font-size:12px; }
  .fb-bar  { height:8px; border-radius:4px; background:#fde0e0; overflow:hidden; min-width:90px; }
  .fb-bar span { display:block; height:100%; background:#43a047; }
  .fb-empty { text-align:center; padding:36px 10px; color:#889; font-size:14px; }
  .fb-pager { display:flex; justify-content:space-between; align-items:center; margin-top:16px; font-size:13px; color:#667; }
  .fb-pager a, .fb-pager span.btn { text-decoration:none; }
  .fb-scroll { overflow-x:auto; }
  .fb-scroll-y { max-height:340px; overflow-y:auto; }
</style>

<!-- FILTERS -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><h2>Collection Feedback</h2></div>
  <div class="card-body">
    <form method="GET" action="feedback.php">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="fb-filters">
        <div class="form-group">
          <label for="from">From</label>
          <input type="date" id="from" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="form-group">
          <label for="to">To</label>
          <input type="date" id="to" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="form-group">
          <label for="barangay">Barangay</label>
          <select id="barangay" name="barangay" class="form-control">
            <option value="">All barangays</option>
            <?php foreach ($barangays as $b): ?>
              <option value="<?= htmlspecialchars($b['barangay']) ?>" <?= $b['barangay'] === $barangay ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['barangay']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary">Apply</button>
          <a href="feedback.php" class="btn">Reset</a>
        </div>
      </div>
    </form>

    <div class="fb-quick">
      <a class="btn btn-sm <?= $is_today ? 'on' : '' ?>"     href="<?= htmlspecialchars(url_with(['range'=>'today','from'=>null,'to'=>null])) ?>">Today</a>
      <a class="btn btn-sm <?= $is_yesterday ? 'on' : '' ?>" href="<?= htmlspecialchars(url_with(['range'=>'yesterday','from'=>null,'to'=>null])) ?>">Yesterday</a>
      <a class="btn btn-sm <?= $is_week ? 'on' : '' ?>"      href="<?= htmlspecialchars(url_with(['range'=>'7days','from'=>null,'to'=>null])) ?>">Last 7 days</a>
      <a class="btn btn-sm <?= $is_month ? 'on' : '' ?>"     href="<?= htmlspecialchars(url_with(['range'=>'month','from'=>null,'to'=>null])) ?>">This month</a>
    </div>
  </div>
</div>

<!-- STATS -->
<div class="fb-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:#e3f2fd;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#1a73e8"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
    </div>
    <div class="stat-info"><div class="num"><?= $total ?></div><div class="lbl">Total Responses</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f5e9;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#2e7d32"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    </div>
    <div class="stat-info"><div class="num"><?= $yes_total ?></div><div class="lbl">Collected (Yes)</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#ffebee;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#c62828"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
    </div>
    <div class="stat-info"><div class="num"><?= $no_total ?></div><div class="lbl">Not Collected (No)</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fff8e1;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#f9a825"><path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/></svg>
    </div>
    <div class="stat-info"><div class="num"><?= $rate ?>%</div><div class="lbl">Collection Rate</div></div>
  </div>
</div>

<!-- BY BARANGAY -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><h2>By Barangay</h2></div>
  <div class="card-body">
    <?php if (!$by_barangay): ?>
      <div class="fb-empty">No feedback in this date range.</div>
    <?php else: ?>
    <div class="fb-scroll fb-scroll-y">
      <table class="data-table">
        <thead>
          <tr>
            <th>Barangay</th>
            <th>Yes</th>
            <th>No</th>
            <th>Total</th>
            <th>Collection Rate</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($by_barangay as $r):
            $y = (int)$r['yes_c']; $n = (int)$r['no_c']; $t = (int)$r['total_c'];
            $pct = $t > 0 ? round($y / $t * 100) : 0;
            $review = ($n >= 3 && ($n / $t) >= 0.30);
          ?>
          <tr>
            <td style="font-weight:700;">
              <a href="<?= htmlspecialchars(url_with(['barangay' => $r['barangay'], 'tab' => 'no'])) ?>"
                 style="color:inherit;text-decoration:none;"><?= htmlspecialchars($r['barangay']) ?></a>
            </td>
            <td><?= $y ?></td>
            <td><strong><?= $n ?></strong></td>
            <td><?= $t ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="fb-bar" style="flex:1;"><span style="width:<?= $pct ?>%;"></span></div>
                <span style="font-size:12.5px;min-width:36px;"><?= $pct ?>%</span>
              </div>
            </td>
            <td><?= $review ? '<span class="fb-flag">Needs review</span>' : '<span class="fb-ok">OK</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- YES / NO LISTS -->
<div class="card">
  <div class="card-body">
    <div class="fb-tabs">
      <a href="<?= htmlspecialchars(url_with(['tab'=>'no'])) ?>"  class="<?= $tab === 'no'  ? 'active-no'  : '' ?>">Not Collected<span class="fb-count"><?= $no_total ?></span></a>
      <a href="<?= htmlspecialchars(url_with(['tab'=>'yes'])) ?>" class="<?= $tab === 'yes' ? 'active-yes' : '' ?>">Collected<span class="fb-count"><?= $yes_total ?></span></a>
    </div>

    <?php if (!$list): ?>
      <div class="fb-empty">
        <?= $tab === 'no' ? 'No "not collected" reports for this range.' : 'No "collected" confirmations for this range.' ?>
      </div>
    <?php else: ?>
    <div class="fb-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Resident</th>
            <th>Barangay</th>
            <th>Answer</th>
            <th>Date &amp; Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $f): ?>
          <tr>
            <td><strong>#<?= (int)$f['id'] ?></strong></td>
            <td style="font-weight:700;font-size:14px;"><?= htmlspecialchars($f['resident_name'] ?: 'Unknown Resident') ?></td>
            <td style="font-size:13.5px;"><?= htmlspecialchars($f['barangay'] ?: 'Not specified') ?></td>
            <td>
              <span class="badge <?= $tab === 'no' ? 'badge-pending' : 'badge-resolved' ?>">
                <?= $tab === 'no' ? 'No' : 'Yes' ?>
              </span>
            </td>
            <td style="font-size:13px;color:#555;white-space:nowrap;"><?= htmlspecialchars(to_local($f['created_at'], $APP_TZ, $DB_TZ)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="fb-pager">
      <div>
        Showing <?= $offset + 1 ?>–<?= $offset + count($list) ?> of <?= $tab_total ?>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <?php if ($page > 1): ?>
          <a class="btn btn-sm" href="<?= htmlspecialchars(url_with(['page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
          <a class="btn btn-sm" href="<?= htmlspecialchars(url_with(['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
