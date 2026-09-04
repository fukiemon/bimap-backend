<?php
// GET    /api/announcements.php          -> list announcements visible to this resident
// DELETE /api/announcements.php?id=5     -> hide one announcement for this resident only
//                                            (announcements are shared/admin-owned, so we
//                                             never actually delete the row - see resident_hidden_announcement)
//
// Requires: Authorization: Bearer <token> (resident)

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
if ($auth['user_type'] !== 'resident') {
    json_error('Only residents can use this endpoint.', 403);
}
$residentId = (int) $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $stmt = $conn->prepare(
        "SELECT a.* FROM announcement a
         WHERE a.target_audience IN ('residents', 'all')
           AND a.id NOT IN (
               SELECT announcement_id FROM resident_hidden_announcement WHERE resident_id = ?
           )
         ORDER BY a.created_at DESC"
    );
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    json_ok(['announcements' => $rows]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) json_error('Missing "id" query parameter.');

    $check = $conn->prepare("SELECT id FROM announcement WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) json_error('Announcement not found.', 404);

    $stmt = $conn->prepare(
        "INSERT INTO resident_hidden_announcement (resident_id, announcement_id) VALUES (?, ?)"
    );
    $stmt->bind_param('ii', $residentId, $id);
    $stmt->execute();

    json_ok(['message' => 'Announcement hidden.']);

} else {
    json_error('Method not allowed. Use GET or DELETE.', 405);
}
