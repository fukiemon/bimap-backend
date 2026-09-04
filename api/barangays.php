<?php
// GET /api/barangays.php -> distinct list of barangay names seen in the system
// so far (used to populate a dropdown on registration/report forms).
//
// No fixed barangay list exists anywhere in this codebase, so this is
// derived from whatever data already exists. If you have an official list
// of Malita's barangays, replace this endpoint with a static array instead.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

$rows = $conn->query(
    "SELECT DISTINCT barangay FROM resident WHERE barangay IS NOT NULL AND barangay <> ''
     UNION
     SELECT DISTINCT barangay FROM complaint WHERE barangay IS NOT NULL AND barangay NOT IN ('', 'Not specified')
     ORDER BY barangay ASC"
)->fetch_all(MYSQLI_ASSOC);

$barangays = array_column($rows, 'barangay');

json_ok(['barangays' => $barangays]);
