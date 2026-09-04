<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Reports';
$active_nav = 'reports';

// ── Date range filter ─────────────────────────────────────────────
// Supported values: all | week | month | year | custom
function resolveDateRange(): array {
    $range = $_GET['range'] ?? 'all';
    $allowed = ['all', 'week', 'month', 'year', 'custom'];
    if (!in_array($range, $allowed, true)) $range = 'all';

    $start = null; $end = null;
    $now = new DateTime();

    switch ($range) {
        case 'week':
            $start = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
            $end   = (clone $now)->setTime(23, 59, 59);
            break;
        case 'month':
            $start = new DateTime($now->format('Y-m-01') . ' 00:00:00');
            $end   = (clone $now)->setTime(23, 59, 59);
            break;
        case 'year':
            $start = new DateTime($now->format('Y-01-01') . ' 00:00:00');
            $end   = (clone $now)->setTime(23, 59, 59);
            break;
        case 'custom':
            $s = $_GET['start_date'] ?? '';
            $e = $_GET['end_date'] ?? '';
            $sDate = DateTime::createFromFormat('Y-m-d', $s);
            $eDate = DateTime::createFromFormat('Y-m-d', $e);
            if ($sDate && $eDate) {
                $start = $sDate->setTime(0, 0, 0);
                $end   = $eDate->setTime(23, 59, 59);
            } else {
                $range = 'all'; // invalid/missing custom dates -> fall back
            }
            break;
    }

    return [$range, $start, $end];
}

[$selectedRange, $rangeStart, $rangeEnd] = resolveDateRange();
$hasDateFilter = $rangeStart !== null && $rangeEnd !== null;

// Human-readable label for the currently selected period (shown on screen + print)
$rangeLabels = ['all' => 'All Time', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year', 'custom' => 'Custom Range'];
$periodLabel = $rangeLabels[$selectedRange] ?? 'All Time';
if ($hasDateFilter) {
    $periodLabel .= ' (' . $rangeStart->format('M j, Y') . ' – ' . $rangeEnd->format('M j, Y') . ')';
}

// Helper: COUNT(*) on a table, with optional extra WHERE clause and optional date filter on created_at
function filteredCount(mysqli $conn, string $table, string $extraWhere = '', ?DateTime $start = null, ?DateTime $end = null): int {
    $where = [];
    $params = [];
    $types = '';
    if ($extraWhere !== '') $where[] = $extraWhere;
    if ($start && $end) {
        $where[] = 'created_at BETWEEN ? AND ?';
        $params[] = $start->format('Y-m-d H:i:s');
        $params[] = $end->format('Y-m-d H:i:s');
        $types .= 'ss';
    }
    $sql = "SELECT COUNT(*) as c FROM `$table`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

// ── Summary stats ─────────────────────────────────────────────────
// Residents/Drivers totals stay all-time (org totals); complaint & feedback
// figures respect the selected date filter.
$stats = [
    'total_residents'  => $conn->query("SELECT COUNT(*) as c FROM resident")->fetch_assoc()['c'],
    'total_drivers'    => $conn->query("SELECT COUNT(*) as c FROM driver")->fetch_assoc()['c'],
    'total_complaints' => filteredCount($conn, 'complaint', '', $rangeStart, $rangeEnd),
    'pending'          => filteredCount($conn, 'complaint', "status='Pending'", $rangeStart, $rangeEnd),
    'resolved'         => filteredCount($conn, 'complaint', "status='Resolved'", $rangeStart, $rangeEnd),
    'total_feedbacks'  => filteredCount($conn, 'feedback', '', $rangeStart, $rangeEnd),
    'feedback_yes'     => filteredCount($conn, 'feedback', "collected='yes'", $rangeStart, $rangeEnd),
    'feedback_no'      => filteredCount($conn, 'feedback', "collected='no'", $rangeStart, $rangeEnd),
];

// ── Resolution rate ───────────────────────────────────────────────
$resolution_rate = $stats['total_complaints'] > 0
    ? round(($stats['resolved'] / $stats['total_complaints']) * 100, 1)
    : 0;

// ── All 30 official barangays ─────────────────────────────────────
$allBarangays = [
    'Bito','Bolila','Buhangin','Culaman','Datu Danwata',
    'Demoloc','Felis','Fishing Village','Kibalatong','Kidalapong',
    'Kilalag','Kinangan','Lacaron','Lagumit','Lais',
    'Little Baguio','Macol','Mana','Manuel Peralta','New Argao',
    'Pangaleon','Pangian','Pinalpalan','Poblacion','Sangay',
    'Talogoy','Tical','Ticulon','Tingolo','Tubalan',
];

// ── Complaints grouped by barangay ───────────────────────────────
$sql = "SELECT barangay, COUNT(*) as total,
               SUM(status='Pending')  as pending,
               SUM(status='Resolved') as resolved
        FROM complaint";
$params = []; $types = '';
if ($hasDateFilter) {
    $sql .= " WHERE created_at BETWEEN ? AND ?";
    $params = [$rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')];
    $types = 'ss';
}
$sql .= " GROUP BY barangay";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$rawComplaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$complaintMap = [];
foreach ($rawComplaints as $rc) $complaintMap[$rc['barangay']] = $rc;

$byBarangay = [];
foreach ($allBarangays as $b) {
    $byBarangay[] = [
        'barangay' => $b,
        'total'    => $complaintMap[$b]['total']    ?? 0,
        'pending'  => $complaintMap[$b]['pending']  ?? 0,
        'resolved' => $complaintMap[$b]['resolved'] ?? 0,
    ];
}

// Sort by total descending for the chart (top 10)
$sortedBarangays = $byBarangay;
usort($sortedBarangays, fn($a, $b) => $b['total'] <=> $a['total']);
$top10 = array_slice($sortedBarangays, 0, 10);

// ── Complaints trend — last 6 months ─────────────────────────────
$trendRaw = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as total,
           SUM(status='Resolved') as resolved,
           SUM(status='Pending')  as pending
    FROM complaint
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

$trendData = [];
for ($i = 5; $i >= 0; $i--) {
    $key   = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $found = null;
    foreach ($trendRaw as $row) {
        if ($row['month'] === $key) { $found = $row; break; }
    }
    $trendData[] = [
        'label'    => $label,
        'total'    => $found['total']    ?? 0,
        'resolved' => $found['resolved'] ?? 0,
        'pending'  => $found['pending']  ?? 0,
    ];
}

// ── Feedback collection rate by barangay (top 8) ─────────────────
$sql = "SELECT barangay,
               COUNT(*) as total,
               SUM(collected='yes') as collected_yes,
               SUM(collected='no')  as collected_no
        FROM feedback";
$params = []; $types = '';
if ($hasDateFilter) {
    $sql .= " WHERE created_at BETWEEN ? AND ?";
    $params = [$rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')];
    $types = 'ss';
}
$sql .= " GROUP BY barangay ORDER BY total DESC LIMIT 8";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$feedbackByBarangay = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Complaint type breakdown ──────────────────────────────────────
$sql = "SELECT
            COALESCE(NULLIF(TRIM(waste_type), ''), 'Other') AS complaint_type,
            COUNT(*) as total
        FROM complaint";
$params = []; $types = '';
if ($hasDateFilter) {
    $sql .= " WHERE created_at BETWEEN ? AND ?";
    $params = [$rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')];
    $types = 'ss';
}
$sql .= " GROUP BY complaint_type ORDER BY total DESC LIMIT 6";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$typeBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Monthly registration trend (residents + drivers) ─────────────
$regTrendRaw = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, 'resident' as type FROM resident
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    UNION ALL
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, 'driver' as type FROM driver
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
")->fetch_all(MYSQLI_ASSOC);

$regData = [];
for ($i = 5; $i >= 0; $i--) {
    $key   = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $regData[$key] = ['label' => $label, 'residents' => 0, 'drivers' => 0];
}
foreach ($regTrendRaw as $row) {
    if (isset($regData[$row['month']])) {
        $regData[$row['month']][$row['type'] === 'driver' ? 'drivers' : 'residents']++;
    }
}
$regData = array_values($regData);

// ── Average resolution time (days) ───────────────────────────────
$sql = "SELECT ROUND(AVG(DATEDIFF(updated_at, created_at)), 1) as avg_days
        FROM complaint
        WHERE status = 'Resolved' AND updated_at IS NOT NULL";
$params = []; $types = '';
if ($hasDateFilter) {
    $sql .= " AND created_at BETWEEN ? AND ?";
    $params = [$rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')];
    $types = 'ss';
}
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$avgResolutionRow = $stmt->get_result()->fetch_assoc();
$avg_resolution_days = $avgResolutionRow['avg_days'] ?? 0;

// ── Top 5 most active barangays (by complaints filed) ─────────────
$top5Active = array_slice($sortedBarangays, 0, 5);

// ── Type icon helper ─────────────────────────────────────────────
function wasteTypeIcon(string $type): string {
    if (str_contains($type, 'Missed Pickup'))   return '🚛';
    if (str_contains($type, 'Overflowing'))      return '🗑️';
    if (str_contains($type, 'Illegal Dumping'))  return '⚠️';
    if (str_contains($type, 'Hazardous'))        return '☣️';
    if (str_contains($type, 'Recyclables'))      return '♻️';
    return '📋';
}

// ── Type color helper ─────────────────────────────────────────────
function wasteTypeColor(string $type): string {
    if (str_contains($type, 'Missed Pickup'))   return '#1a73e8';
    if (str_contains($type, 'Overflowing'))      return '#e53935';
    if (str_contains($type, 'Illegal Dumping'))  return '#fb8c00';
    if (str_contains($type, 'Hazardous'))        return '#6a1b9a';
    if (str_contains($type, 'Recyclables'))      return '#43a047';
    return '#78909c';
}

include '../includes/admin_header.php';
?>

<style>
  /* ── Page wrapper ─────────────────────────────────────────────── */
  .reports-page {
    padding: 28px 32px;
    max-width: 1400px;
    margin: 0 auto;
  }

  /* ── Filter bar ───────────────────────────────────────────────── */
  .filter-bar {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    padding: 18px 24px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
  }
  .filter-bar form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    flex: 1;
  }
  .filter-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .filter-chip {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #e0e7ef;
    background: #fff;
    color: #555;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    white-space: nowrap;
  }
  .filter-chip:hover { background: #f0f6ff; border-color: #1a73e8; color: #1a73e8; }
  .filter-chip.active { background: #1a73e8; border-color: #1a73e8; color: #fff; }
  .filter-dates {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .filter-dates input[type="date"] {
    padding: 7px 10px;
    border-radius: 8px;
    border: 1px solid #e0e7ef;
    font-size: 13px;
    color: #333;
  }
  .filter-apply-btn {
    padding: 8px 18px;
    border-radius: 8px;
    border: none;
    background: #1a73e8;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
  }
  .filter-apply-btn:hover { background: #1558b0; }
  .filter-period-label {
    font-size: 12px;
    color: #888;
    font-weight: 600;
    margin-left: auto;
  }
  .print-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    border: 1px solid #e0e7ef;
    background: #1a1a2e;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
  }
  .print-btn:hover { background: #2a2a45; }

  /* Print-only header (hidden on screen, shown when printing) */
  .print-only { display: none; }

  /* ── Print styles ─────────────────────────────────────────────── */
  @media print {
    .topbar, .sidebar, .page-header, .filter-bar { display: none !important; }
    .main-content, .page-body, .reports-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    body { background: #fff !important; }
    .rpt-card { box-shadow: none !important; border: 1px solid #ddd; break-inside: avoid; }
    .analytics-grid { grid-template-columns: 1fr 1fr !important; }
    .print-only {
      display: block !important;
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 2px solid #1a1a2e;
    }
    .print-only h1 { font-size: 20px; margin: 0 0 4px 0; }
    .print-only p { font-size: 12px; color: #555; margin: 0; }
  }

  /* ── Card base ───────────────────────────────────────────────── */
  .rpt-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
  }
  .rpt-card-header {
    padding: 22px 28px 0 28px;
  }
  .rpt-card-body {
    padding: 20px 28px 28px 28px;
  }

  /* ── Section accent header ───────────────────────────────────── */
  .section-accent {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
  }
  .section-accent h2 {
    font-size: 15px;
    font-weight: 700;
    color: #222;
    margin: 0;
    letter-spacing: -.01em;
  }
  .section-accent .dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #1a73e8;
    flex-shrink: 0;
  }

  /* ── Summary stat cards ──────────────────────────────────────── */
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 28px;
  }
  .stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    display: flex;
    align-items: center;
    gap: 18px;
  }
  .stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: #e8f0fe;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .stat-info .num {
    font-size: 30px;
    font-weight: 800;
    color: #1a1a1a;
    line-height: 1;
  }
  .stat-info .lbl {
    font-size: 12px;
    color: #888;
    margin-top: 5px;
    font-weight: 500;
  }

  /* ── KPI strip ───────────────────────────────────────────────── */
  .kpi-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 28px;
  }
  .kpi-card {
    background: #fff;
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    border-left: 5px solid var(--kpi-color, #1a73e8);
  }
  .kpi-card .kpi-num {
    font-size: 30px;
    font-weight: 800;
    color: var(--kpi-color, #1a73e8);
    line-height: 1;
  }
  .kpi-card .kpi-lbl {
    font-size: 12px;
    color: #777;
    margin-top: 6px;
    font-weight: 600;
  }
  .kpi-card .kpi-sub {
    font-size: 11px;
    color: #aaa;
    margin-top: 4px;
  }

  /* ── Analytics 2-col grid ────────────────────────────────────── */
  .analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 28px;
  }
  .analytics-grid.full {
    grid-template-columns: 1fr;
  }

  /* ── Bar chart rows ──────────────────────────────────────────── */
  .bar-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
  }
  .bar-label {
    width: 120px;
    font-size: 12px;
    font-weight: 600;
    color: #444;
    flex-shrink: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .bar-track {
    flex: 1;
    background: #f0f2f5;
    border-radius: 8px;
    height: 16px;
    overflow: hidden;
  }
  .bar-fill {
    height: 100%;
    border-radius: 8px;
    transition: width .6s ease;
  }
  .bar-val {
    font-size: 13px;
    font-weight: 700;
    color: #444;
    width: 32px;
    text-align: right;
    flex-shrink: 0;
  }

  /* ── Trend chart ─────────────────────────────────────────────── */
  .trend-labels {
    display: flex;
    justify-content: space-between;
    padding: 0 4px;
    margin-top: 8px;
  }
  .trend-labels span {
    font-size: 10px;
    color: #aaa;
  }
  .chart-legend {
    display: flex;
    gap: 20px;
    margin-top: 16px;
  }
  .legend-line {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: #555;
    font-weight: 500;
  }
  .legend-line .line-swatch {
    display: inline-block;
    width: 24px;
    height: 3px;
    border-radius: 2px;
  }

  /* ── Donut chart ─────────────────────────────────────────────── */
  .donut-wrap {
    display: flex;
    align-items: center;
    gap: 32px;
  }
  .donut-legend {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
  }
  .legend-dot {
    width: 13px;
    height: 13px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  /* ── Resolution progress bar ─────────────────────────────────── */
  .progress-wrap {
    margin-top: 28px;
  }
  .progress-header {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #888;
    margin-bottom: 8px;
    font-weight: 500;
  }
  .progress-track {
    height: 10px;
    background: #fce4e4;
    border-radius: 6px;
    overflow: hidden;
  }
  .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #43a047, #66bb6a);
    border-radius: 6px;
    transition: width .6s;
  }

  /* ── Feedback meters ─────────────────────────────────────────── */
  .feedback-item {
    margin-bottom: 20px;
  }
  .feedback-item:last-child {
    margin-bottom: 0;
  }
  .feedback-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
  }
  .feedback-name {
    font-size: 13px;
    font-weight: 700;
    color: #333;
  }
  .feedback-pct {
    font-size: 13px;
    font-weight: 700;
  }
  .feedback-meter {
    height: 12px;
    border-radius: 6px;
    background: #f0f2f5;
    overflow: hidden;
  }
  .feedback-meter-fill {
    height: 100%;
    border-radius: 6px;
    transition: width .6s;
  }
  .feedback-meta {
    display: flex;
    gap: 12px;
    margin-top: 5px;
  }
  .feedback-meta span {
    font-size: 11px;
    color: #bbb;
  }

  /* ── Full-width table card ───────────────────────────────────── */
  .table-scroll {
    max-height: 380px;
    overflow-y: auto;
  }
  .table-scroll table {
    width: 100%;
    border-collapse: collapse;
  }
  .table-scroll th {
    position: sticky;
    top: 0;
    background: #fafbfc;
    font-size: 12px;
    font-weight: 700;
    color: #888;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .table-scroll td {
    padding: 13px 16px;
    font-size: 13px;
    border-bottom: 1px solid #f5f5f5;
  }
  .table-scroll tr:last-child td {
    border-bottom: none;
  }
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
  }
  .badge-blue    { background: #e3f2fd; color: #1565c0; }
  .badge-pending { background: #ffebee; color: #c62828; }
  .badge-resolved{ background: #e8f5e9; color: #2e7d32; }

  /* ── Responsive ──────────────────────────────────────────────── */
  @media (max-width: 900px) {
    .summary-grid,
    .kpi-strip,
    .analytics-grid { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 600px) {
    .summary-grid,
    .kpi-strip,
    .analytics-grid { grid-template-columns: 1fr; }
    .reports-page { padding: 16px; }
  }
</style>

<div class="reports-page">

  <!-- ── Print-only header (appears only on the printed/PDF version) ── -->
  <div class="print-only">
    <h1>BiMAP — Reports</h1>
    <p>Report Period: <?= htmlspecialchars($periodLabel) ?> &nbsp;•&nbsp; Generated: <?= date('M j, Y g:i A') ?></p>
  </div>

  <!-- ── Date filter bar ──────────────────────────────────────────── -->
  <div class="filter-bar">
    <form method="get" id="filterForm">
      <div class="filter-chip-group">
        <a href="?range=all" class="filter-chip <?= $selectedRange === 'all' ? 'active' : '' ?>">All Time</a>
        <a href="?range=week" class="filter-chip <?= $selectedRange === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="?range=month" class="filter-chip <?= $selectedRange === 'month' ? 'active' : '' ?>">This Month</a>
        <a href="?range=year" class="filter-chip <?= $selectedRange === 'year' ? 'active' : '' ?>">This Year</a>
        <a href="#" class="filter-chip <?= $selectedRange === 'custom' ? 'active' : '' ?>" id="customChip">Custom Range</a>
      </div>
      <input type="hidden" name="range" id="rangeInput" value="custom">
      <div class="filter-dates" id="customDates" style="<?= $selectedRange === 'custom' ? '' : 'display:none;' ?>">
        <input type="date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" required>
        <span style="color:#aaa;">to</span>
        <input type="date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" required>
        <button type="submit" class="filter-apply-btn">Apply</button>
      </div>
    </form>
    <span class="filter-period-label">Showing: <?= htmlspecialchars($periodLabel) ?></span>
    <button type="button" class="print-btn" onclick="window.print()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
      Print / Save as PDF
    </button>
  </div>

  <!-- ── Summary KPI cards ─────────────────────────────────────── -->
  <div class="summary-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e8f0fe;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#1a73e8">
          <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
        </svg>
      </div>
      <div class="stat-info">
        <div class="num"><?= $stats['total_residents'] ?></div>
        <div class="lbl">Total Residents</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:#f3e5f5;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#6a1b9a">
          <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/>
        </svg>
      </div>
      <div class="stat-info">
        <div class="num"><?= $stats['total_drivers'] ?></div>
        <div class="lbl">Total Drivers</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:#fff8e1;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#f9a825">
          <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/>
        </svg>
      </div>
      <div class="stat-info">
        <div class="num"><?= $stats['total_complaints'] ?></div>
        <div class="lbl">Total Complaints</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:#e8f5e9;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#2e7d32">
          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
        </svg>
      </div>
      <div class="stat-info">
        <div class="num"><?= $stats['feedback_yes'] ?></div>
        <div class="lbl">Collections Confirmed</div>
      </div>
    </div>
  </div>

  <!-- ── Analytics KPI strip ───────────────────────────────────── -->
  <div class="kpi-strip">
    <div class="kpi-card" style="--kpi-color:#1a73e8;">
      <div class="kpi-num"><?= $resolution_rate ?>%</div>
      <div class="kpi-lbl">Complaint Resolution Rate</div>
      <div class="kpi-sub"><?= $stats['resolved'] ?> of <?= $stats['total_complaints'] ?> resolved</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#e53935;">
      <div class="kpi-num"><?= $stats['pending'] ?></div>
      <div class="kpi-lbl">Open / Pending Complaints</div>
      <div class="kpi-sub">Awaiting resolution</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#43a047;">
      <div class="kpi-num"><?= $avg_resolution_days ?? '—' ?></div>
      <div class="kpi-lbl">Avg. Resolution Time</div>
      <div class="kpi-sub">Days from filed to resolved</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#fb8c00;">
      <?php
        $collection_rate = $stats['total_feedbacks'] > 0
          ? round(($stats['feedback_yes'] / $stats['total_feedbacks']) * 100, 1) : 0;
      ?>
      <div class="kpi-num"><?= $collection_rate ?>%</div>
      <div class="kpi-lbl">Garbage Collection Rate</div>
      <div class="kpi-sub"><?= $stats['feedback_yes'] ?> yes / <?= $stats['feedback_no'] ?> no</div>
    </div>
  </div>

  <!-- ── Row 1: Complaint Trend + Barangay Bar Chart ───────────── -->
  <div class="analytics-grid">

    <!-- Complaints Trend -->
    <div class="rpt-card">
      <div class="rpt-card-header">
        <div class="section-accent">
          <div class="dot" style="background:#1a73e8;"></div>
          <h2>Complaint Trend — Last 6 Months</h2>
        </div>
      </div>
      <div class="rpt-card-body">
        <?php
          $maxTrend = max(array_column($trendData, 'total') ?: [1]);
          $points_total = []; $points_resolved = [];
          $svgW = 400; $svgH = 140; $padL = 30; $padR = 12; $padT = 12; $padB = 12;
          $n = count($trendData);
          for ($i = 0; $i < $n; $i++) {
              $x  = $padL + ($i / max($n-1,1)) * ($svgW - $padL - $padR);
              $yT = $padT + (1 - ($maxTrend > 0 ? $trendData[$i]['total']    / $maxTrend : 0)) * ($svgH - $padT - $padB);
              $yR = $padT + (1 - ($maxTrend > 0 ? $trendData[$i]['resolved'] / $maxTrend : 0)) * ($svgH - $padT - $padB);
              $points_total[]    = "$x,$yT";
              $points_resolved[] = "$x,$yR";
          }
          $polyT = implode(' ', $points_total);
          $polyR = implode(' ', $points_resolved);
          $lastX    = $padL + (($n-1) / max($n-1,1)) * ($svgW - $padL - $padR);
          $baseY    = $padT + ($svgH - $padT - $padB);
          $areaPath = "M $padL $baseY L " . implode(' L ', $points_total) . " L $lastX $baseY Z";
        ?>
        <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:150px;overflow:visible;">
          <defs>
            <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%"   stop-color="#1a73e8" stop-opacity=".18"/>
              <stop offset="100%" stop-color="#1a73e8" stop-opacity="0"/>
            </linearGradient>
          </defs>
          <?php for ($g = 0; $g <= 4; $g++): $gy = $padT + ($g/4)*($svgH-$padT-$padB); ?>
            <line x1="<?= $padL ?>" y1="<?= $gy ?>" x2="<?= $svgW-$padR ?>" y2="<?= $gy ?>" stroke="#f0f0f0" stroke-width="1"/>
            <text x="<?= $padL-6 ?>" y="<?= $gy+4 ?>" font-size="9" fill="#ccc" text-anchor="end"><?= round($maxTrend*(1-$g/4)) ?></text>
          <?php endfor; ?>
          <path d="<?= $areaPath ?>" fill="url(#areaGrad)"/>
          <polyline points="<?= $polyT ?>" fill="none" stroke="#1a73e8" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
          <polyline points="<?= $polyR ?>" fill="none" stroke="#43a047" stroke-width="2" stroke-dasharray="5,3" stroke-linejoin="round" stroke-linecap="round"/>
          <?php foreach ($trendData as $i => $td):
            $x  = $padL + ($i / max($n-1,1)) * ($svgW - $padL - $padR);
            $yT = $padT + (1 - ($maxTrend > 0 ? $td['total']    / $maxTrend : 0)) * ($svgH - $padT - $padB);
            $yR = $padT + (1 - ($maxTrend > 0 ? $td['resolved'] / $maxTrend : 0)) * ($svgH - $padT - $padB);
          ?>
            <circle cx="<?= $x ?>" cy="<?= $yT ?>" r="4" fill="#1a73e8" stroke="#fff" stroke-width="2"/>
            <circle cx="<?= $x ?>" cy="<?= $yR ?>" r="3.5" fill="#43a047" stroke="#fff" stroke-width="2"/>
          <?php endforeach; ?>
        </svg>
        <div class="trend-labels">
          <?php foreach ($trendData as $td): ?>
            <span><?= htmlspecialchars($td['label']) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="chart-legend">
          <div class="legend-line">
            <span class="line-swatch" style="background:#1a73e8;"></span> Total Filed
          </div>
          <div class="legend-line">
            <span class="line-swatch" style="background:#43a047;border-top:2px dashed #43a047;"></span> Resolved
          </div>
        </div>
      </div>
    </div>

    <!-- Top 10 Barangays by Complaints -->
    <div class="rpt-card">
      <div class="rpt-card-header">
        <div class="section-accent">
          <div class="dot" style="background:#e53935;"></div>
          <h2>Top Barangays by Complaints</h2>
        </div>
      </div>
      <div class="rpt-card-body">
        <?php
          $maxBar = max(array_column($top10, 'total') ?: [1]);
          foreach ($top10 as $row):
            $pctTotal = $maxBar > 0 ? round($row['total'] / $maxBar * 100) : 0;
        ?>
        <div class="bar-wrap" title="Resolved: <?= $row['resolved'] ?> / Pending: <?= $row['pending'] ?>">
          <div class="bar-label"><?= htmlspecialchars($row['barangay']) ?></div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pctTotal ?>%;background:linear-gradient(90deg,#1a73e8,#42a5f5);"></div>
          </div>
          <div class="bar-val"><?= $row['total'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty(array_filter(array_column($top10,'total')))): ?>
          <p style="color:#aaa;text-align:center;padding:30px 0;">No complaint data yet.</p>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ── Row 2: Status Donut + Type Breakdown ───────────────────── -->
  <div class="analytics-grid">

    <!-- Complaint Status Donut -->
    <div class="rpt-card">
      <div class="rpt-card-header">
        <div class="section-accent">
          <div class="dot" style="background:#fb8c00;"></div>
          <h2>Complaint Status Breakdown</h2>
        </div>
      </div>
      <div class="rpt-card-body">
        <?php
          $total    = $stats['total_complaints'];
          $resolved = $stats['resolved'];
          $pending  = $stats['pending'];
          $other    = max(0, $total - $resolved - $pending);
          $cx = 75; $cy = 75; $r = 58; $strokeW = 24;
          $circumference = 2 * M_PI * $r;
          $segments = [];
          if ($total > 0) {
              $segments = [
                  ['val' => $resolved, 'color' => '#43a047', 'label' => 'Resolved'],
                  ['val' => $pending,  'color' => '#e53935', 'label' => 'Pending'],
                  ['val' => $other,    'color' => '#fb8c00', 'label' => 'Other'],
              ];
          }
          $offset = 0; $dashes = [];
          foreach ($segments as $seg) {
              $pct  = $total > 0 ? $seg['val'] / $total : 0;
              $dash = $pct * $circumference;
              $dashes[] = ['dash' => $dash, 'offset' => $circumference - $offset, 'color' => $seg['color'], 'val' => $seg['val'], 'label' => $seg['label']];
              $offset += $dash;
          }
        ?>
        <div class="donut-wrap">
          <svg width="150" height="150" viewBox="0 0 150 150" style="flex-shrink:0;">
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#f0f2f5" stroke-width="<?= $strokeW ?>"/>
            <?php foreach ($dashes as $d): ?>
              <?php if ($d['dash'] > 0): ?>
              <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none"
                stroke="<?= $d['color'] ?>" stroke-width="<?= $strokeW ?>"
                stroke-dasharray="<?= $d['dash'] ?> <?= $circumference - $d['dash'] ?>"
                stroke-dashoffset="<?= $d['offset'] ?>"
                transform="rotate(-90 <?= $cx ?> <?= $cy ?>)"
                stroke-linecap="butt"/>
              <?php endif; ?>
            <?php endforeach; ?>
            <text x="<?= $cx ?>" y="<?= $cy - 7 ?>" text-anchor="middle" font-size="24" font-weight="800" fill="#1a1a1a"><?= $total ?></text>
            <text x="<?= $cx ?>" y="<?= $cy + 13 ?>" text-anchor="middle" font-size="11" fill="#aaa" font-weight="500">Total</text>
          </svg>
          <div class="donut-legend">
            <?php foreach ($segments as $seg): ?>
            <div class="legend-item">
              <div class="legend-dot" style="background:<?= $seg['color'] ?>;"></div>
              <span style="font-weight:800;color:#222;font-size:14px;"><?= $seg['val'] ?></span>
              <span style="color:#888;"><?= $seg['label'] ?></span>
              <?php if ($total > 0): ?>
                <span style="color:#bbb;font-size:11px;">(<?= round($seg['val']/$total*100) ?>%)</span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($total === 0): ?>
              <p style="color:#aaa;">No complaints yet.</p>
            <?php endif; ?>
          </div>
        </div>
        <div class="progress-wrap">
          <div class="progress-header">
            <span>Resolution Progress</span>
            <span><?= $resolution_rate ?>%</span>
          </div>
          <div class="progress-track">
            <div class="progress-fill" style="width:<?= $resolution_rate ?>%;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Complaint Type Breakdown -->
    <div class="rpt-card">
      <div class="rpt-card-header">
        <div class="section-accent">
          <div class="dot" style="background:#6a1b9a;"></div>
          <h2>Complaints by Type</h2>
        </div>
      </div>
      <div class="rpt-card-body">
        <?php if (empty($typeBreakdown)): ?>
          <p style="color:#aaa;text-align:center;padding:40px 0;">No type data yet.</p>
        <?php else:
          $maxType = max(array_column($typeBreakdown, 'total') ?: [1]);
          foreach ($typeBreakdown as $tb):
            $pct   = $maxType > 0 ? round($tb['total'] / $maxType * 100) : 0;
            $color = wasteTypeColor($tb['complaint_type']);
            $icon  = wasteTypeIcon($tb['complaint_type']);
        ?>
          <div class="bar-wrap" style="margin-bottom:14px;">
            <div class="bar-label" style="width:140px;display:flex;align-items:center;gap:6px;">
              <span style="font-size:15px;"><?= $icon ?></span>
              <span><?= htmlspecialchars($tb['complaint_type']) ?></span>
            </div>
            <div class="bar-track">
              <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;opacity:0.85;"></div>
            </div>
            <div class="bar-val"><?= $tb['total'] ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>

  <!-- ── Row 3: Feedback by Barangay + Registration Trend ─────────── -->
  <div class="analytics-grid">

    <!-- Collection Rate by Barangay -->
    <div class="rpt-card">
      <div class="rpt-card-header">
        <div class="section-accent">
          <div class="dot" style="background:#43a047;"></div>
          <h2>Collection Rate by Barangay</h2>
        </div>
      </div>
      <div class="rpt-card-body">
        <?php if (empty($feedbackByBarangay)): ?>
          <p style="color:#aaa;text-align:center;padding:40px 0;">No feedback data yet.</p>
        <?php else: ?>
          <?php foreach ($feedbackByBarangay as $fb):
            $rate  = $fb['total'] > 0 ? round($fb['collected_yes'] / $fb['total'] * 100) : 0;
            $color = $rate >= 75 ? '#43a047' : ($rate >= 50 ? '#fb8c00' : '#e53935');
            $grad  = $rate >= 75
              ? 'linear-gradient(90deg,#43a047,#66bb6a)'
              : ($rate >= 50 ? 'linear-gradient(90deg,#fb8c00,#ffa726)' : 'linear-gradient(90deg,#e53935,#ef5350)');
          ?>
          <div class="feedback-item">
            <div class="feedback-header">
              <span class="feedback-name"><?= htmlspecialchars($fb['barangay'] ?? '—') ?></span>
              <span class="feedback-pct" style="color:<?= $color ?>;"><?= $rate ?>%</span>
            </div>
            <div class="feedback-meter">
              <div class="feedback-meter-fill" style="width:<?= $rate ?>%;background:<?= $grad ?>;"></div>
            </div>
            <div class="feedback-meta">
              <span>✓ <?= $fb['collected_yes'] ?> collected</span>
              <span>✗ <?= $fb['collected_no'] ?> missed</span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Monthly Registrations Trend -->
    <div class="rpt-card">
      <div class="rpt-card-header">
        <div class="section-accent">
          <div class="dot" style="background:#6a1b9a;"></div>
          <h2>Monthly Registrations Trend</h2>
        </div>
      </div>
      <div class="rpt-card-body">
        <?php
          $maxReg  = max(array_merge(array_column($regData,'residents'), array_column($regData,'drivers')) ?: [1]);
          $svgW2 = 400; $svgH2 = 140; $padL2 = 28; $padR2 = 12; $padT2 = 12; $padB2 = 12;
          $n2 = count($regData);
          $pts_res = []; $pts_drv = [];
          for ($i = 0; $i < $n2; $i++) {
              $x  = $padL2 + ($i / max($n2-1,1)) * ($svgW2 - $padL2 - $padR2);
              $yR = $padT2 + (1 - ($maxReg > 0 ? $regData[$i]['residents'] / $maxReg : 0)) * ($svgH2 - $padT2 - $padB2);
              $yD = $padT2 + (1 - ($maxReg > 0 ? $regData[$i]['drivers']   / $maxReg : 0)) * ($svgH2 - $padT2 - $padB2);
              $pts_res[] = "$x,$yR";
              $pts_drv[] = "$x,$yD";
          }
        ?>
        <svg viewBox="0 0 <?= $svgW2 ?> <?= $svgH2 ?>" style="width:100%;height:150px;overflow:visible;">
          <?php for ($g = 0; $g <= 3; $g++): $gy = $padT2 + ($g/3)*($svgH2-$padT2-$padB2); ?>
            <line x1="<?= $padL2 ?>" y1="<?= $gy ?>" x2="<?= $svgW2-$padR2 ?>" y2="<?= $gy ?>" stroke="#f0f0f0" stroke-width="1"/>
            <text x="<?= $padL2-6 ?>" y="<?= $gy+4 ?>" font-size="9" fill="#ccc" text-anchor="end"><?= round($maxReg*(1-$g/3)) ?></text>
          <?php endfor; ?>
          <polyline points="<?= implode(' ',$pts_res) ?>" fill="none" stroke="#1a73e8" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
          <polyline points="<?= implode(' ',$pts_drv) ?>" fill="none" stroke="#6a1b9a" stroke-width="2" stroke-dasharray="5,3" stroke-linejoin="round" stroke-linecap="round"/>
          <?php for ($i = 0; $i < $n2; $i++):
            $x  = $padL2 + ($i / max($n2-1,1)) * ($svgW2 - $padL2 - $padR2);
            $yR = $padT2 + (1 - ($maxReg > 0 ? $regData[$i]['residents'] / $maxReg : 0)) * ($svgH2 - $padT2 - $padB2);
            $yD = $padT2 + (1 - ($maxReg > 0 ? $regData[$i]['drivers']   / $maxReg : 0)) * ($svgH2 - $padT2 - $padB2);
          ?>
            <circle cx="<?= $x ?>" cy="<?= $yR ?>" r="4" fill="#1a73e8" stroke="#fff" stroke-width="2"/>
            <circle cx="<?= $x ?>" cy="<?= $yD ?>" r="3.5" fill="#6a1b9a" stroke="#fff" stroke-width="2"/>
          <?php endfor; ?>
        </svg>
        <div class="trend-labels">
          <?php foreach ($regData as $rd): ?>
            <span><?= htmlspecialchars($rd['label']) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="chart-legend">
          <div class="legend-line">
            <span class="line-swatch" style="background:#1a73e8;"></span> Residents
          </div>
          <div class="legend-line">
            <span class="line-swatch" style="background:#6a1b9a;"></span> Drivers
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Row 4: Full Barangay Complaints Table ─────────────────── -->
  <div class="rpt-card">
    <div class="rpt-card-header">
      <div class="section-accent">
        <div class="dot" style="background:#f9a825;"></div>
        <h2>Complaints by Barangay — All 30</h2>
      </div>
    </div>
    <div class="rpt-card-body" style="padding-top:0;">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Barangay</th>
              <th style="text-align:center;">Total</th>
              <th style="text-align:center;">Pending</th>
              <th style="text-align:center;">Resolved</th>
              <th style="text-align:center;">Resolution %</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($byBarangay as $row):
              $rowRate = $row['total'] > 0 ? round($row['resolved'] / $row['total'] * 100) : 0;
            ?>
            <tr style="<?= $row['total'] == 0 ? 'opacity:0.4;' : '' ?>">
              <td style="font-size:13px;font-weight:700;"><?= htmlspecialchars($row['barangay']) ?></td>
              <td style="text-align:center;">
                <?php if ($row['total'] > 0): ?>
                  <span class="badge badge-blue"><?= $row['total'] ?></span>
                <?php else: ?>
                  <span style="font-size:12px;color:#ccc;">0</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php if ($row['pending'] > 0): ?>
                  <span class="badge badge-pending"><?= $row['pending'] ?></span>
                <?php else: ?>
                  <span style="font-size:12px;color:#ccc;">0</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php if ($row['resolved'] > 0): ?>
                  <span class="badge badge-resolved"><?= $row['resolved'] ?></span>
                <?php else: ?>
                  <span style="font-size:12px;color:#ccc;">0</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php if ($row['total'] > 0): ?>
                  <span style="font-size:12px;font-weight:700;color:<?= $rowRate >= 75 ? '#43a047' : ($rowRate >= 50 ? '#fb8c00' : '#e53935') ?>;">
                    <?= $rowRate ?>%
                  </span>
                <?php else: ?>
                  <span style="font-size:12px;color:#ccc;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.reports-page -->

<script>
  // Reveal the custom date-range inputs when "Custom Range" chip is clicked
  document.getElementById('customChip').addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('customDates').style.display = 'flex';
  });
</script>

<?php include '../includes/admin_footer.php'; ?>