<?php
session_start();
require_once '../includes/db.php';
requireAdminLogin(); // before ANY handling below

$page_title = 'Settings';
$active_nav = 'settings';

$success = '';
$error = '';
$adminId = (int)$_SESSION['admin_id'];

// Label from minutes since last activity (NULL = never seen)
if (!function_exists('lastSeenLabel')) {
    function lastSeenLabel($mins) {
        if ($mins === null) return 'Never';
        $mins = (int)$mins;
        if ($mins <= 5)   return 'Online';
        if ($mins < 60)   return $mins . ' min ago';
        if ($mins < 1440) return floor($mins / 60) . 'h ago';
        return floor($mins / 1440) . 'd ago';
    }
}

// Change password
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new_pw = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $s = $conn->prepare("SELECT password FROM admin WHERE id = ?");
    $s->bind_param('i', $adminId);
    $s->execute();
    $admin = $s->get_result()->fetch_assoc();

    if (!$admin || !password_verify($current, $admin['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_pw !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        $u->bind_param('si', $hash, $adminId);
        $u->execute();
        $success = 'Password updated successfully!';
    }
}

// Update profile picture
//
// The image is stored as a base64 data URI in the database (not on disk),
// because the container filesystem on Render is not persistent.
if (isset($_POST['update_pic']) && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please choose an image first.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
    } else {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $imgInfo = @getimagesize($file['tmp_name']);

        if (!isset($allowed[$ext]) || $imgInfo === false) {
            $error = 'Please upload a valid image file (JPG, PNG, GIF, or WEBP).';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $error = 'Image must be smaller than 3MB.';
        } else {
            $mimeType = $allowed[$ext];
            $imageBytes = file_get_contents($file['tmp_name']);

            if ($imageBytes === false) {
                $error = 'Could not read the uploaded image. Please try again.';
            } else {
                $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($imageBytes);

                $stmt = $conn->prepare("UPDATE admin SET profile_pic_data=? WHERE id=?");
                $stmt->bind_param("si", $dataUri, $adminId);

                if ($stmt->execute()) {
                    $_SESSION['admin_pic'] = $dataUri;
                    $success = 'Profile picture updated successfully!';
                } else {
                    $error = 'Could not save the uploaded image. Please try again.';
                }
            }
        }
    }
}

// Update name
if (isset($_POST['update_name'])) {
    $name = trim($_POST['admin_name'] ?? '');
    if ($name !== '') {
        $u = $conn->prepare("UPDATE admin SET name = ? WHERE id = ?");
        $u->bind_param('si', $name, $adminId);
        $u->execute();
        $_SESSION['admin_name'] = $name;
        $success = 'Profile updated successfully!';
    }
}

// Create new admin account (username + password, no email)
if (isset($_POST['create_admin'])) {
    $new_name     = trim($_POST['new_admin_name'] ?? '');
    $new_username = trim($_POST['new_admin_username'] ?? '');
    $new_pw       = $_POST['new_admin_password'] ?? '';
    $new_confirm  = $_POST['new_admin_confirm_password'] ?? '';

    if ($new_name === '' || $new_username === '' || $new_pw === '') {
        $error = 'Name, username, and password are required.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{4,30}$/', $new_username)) {
        $error = 'Username must be 4-30 characters: letters, numbers, dot, dash or underscore.';
    } elseif (strlen($new_pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pw !== $new_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $check->bind_param('s', $new_username);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = 'Username already exists. Please choose a different one.';
        } else {
            $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
            $ins = $conn->prepare(
                "INSERT INTO admin (name, username, password, status, created_at) VALUES (?, ?, ?, 'active', NOW())"
            );
            $ins->bind_param('sss', $new_name, $new_username, $hashed_pw);

            if ($ins->execute()) {
                $success = "Admin account for '$new_name' created successfully!";
            } else {
                $error = 'Failed to create admin account. Please try again.';
            }
        }
    }
}

// Admin management actions: disable / enable / reset password / delete
if (isset($_POST['admin_action'])) {
    $action   = $_POST['admin_action'];
    $targetId = (int)($_POST['target_id'] ?? 0);

    if ($targetId === $adminId) {
        $error = 'You cannot do this to your own account.';
    } else {
        try {
            if ($action === 'disable' || $action === 'enable') {
                $newStatus = ($action === 'disable') ? 'disabled' : 'active';
                $st = $conn->prepare("UPDATE admin SET status = ? WHERE id = ?");
                $st->bind_param('si', $newStatus, $targetId);
                $st->execute();
                $success = 'Admin account ' . ($action === 'disable' ? 'disabled' : 'enabled') . '.';

            } elseif ($action === 'reset_password') {
                $pw = $_POST['reset_password'] ?? '';
                if (strlen($pw) < 8) {
                    $error = 'New password must be at least 8 characters.';
                } else {
                    $hash = password_hash($pw, PASSWORD_DEFAULT);
                    $st = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                    $st->bind_param('si', $hash, $targetId);
                    $st->execute();
                    $success = 'Password reset successfully.';
                }

            } elseif ($action === 'delete') {
                $st = $conn->prepare("DELETE FROM admin WHERE id = ?");
                $st->bind_param('i', $targetId);
                $st->execute();
                $success = 'Admin account deleted.';
            }
        } catch (Throwable $e) {
            $error = 'Action failed. If this admin has linked records, disable the account instead of deleting it.';
        }
    }
}

// Admin list (runs after the handlers so it shows the latest data).
// mins_ago is computed in SQL so PHP/MySQL timezone differences don't matter.
$admins = $conn->query("
    SELECT id, name, username, status, created_at,
           TIMESTAMPDIFF(MINUTE, last_active, NOW()) AS mins_ago
    FROM admin
    ORDER BY (status = 'active') DESC, last_active DESC, created_at ASC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/admin_header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Profile Picture -->
<div class="card" style="max-width:900px;margin-bottom:20px;">
  <div class="card-header"><h2>Profile Picture</h2></div>
  <div class="card-body" style="padding:20px;">
    <form method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
      <div style="width:84px;height:84px;border-radius:50%;overflow:hidden;background:#e0e7ef;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:3px solid #f0f4f8;">
        <?php if (!empty($_SESSION['admin_pic'])): ?>
          <img src="<?= htmlspecialchars($_SESSION['admin_pic']) ?>" alt="Profile picture" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <svg width="44" height="44" viewBox="0 0 24 24" fill="#888"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:220px;">
        <div class="form-group" style="margin-bottom:10px;">
          <label>Choose Image</label>
          <input type="file" name="profile_pic" accept="image/png,image/jpeg,image/gif,image/webp" class="form-control" required>
        </div>
        <button type="submit" name="update_pic" class="btn btn-primary">Upload Picture</button>
        <span style="display:block;margin-top:8px;font-size:12px;color:#888;font-weight:600;">JPG, PNG, GIF, or WEBP — max 3MB</span>
      </div>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">

  <!-- Update Profile -->
  <div class="card">
    <div class="card-header"><h2>Update Profile</h2></div>
    <div class="card-body" style="padding:20px;">
      <form method="POST">
        <div class="form-group">
          <label>Display Name</label>
          <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>" required>
        </div>
        <button type="submit" name="update_name" class="btn btn-primary">Update Name</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><h2>Change Password</h2></div>
    <div class="card-body" style="padding:20px;">
      <form method="POST">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" autocomplete="new-password" placeholder="Min. 8 characters" required>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
        </div>
        <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
      </form>
    </div>
  </div>
</div>

<!-- Create Admin Account -->
<div class="card" style="margin-top:20px;max-width:900px;">
  <div class="card-header"><h2>Create Admin Account</h2></div>
  <div class="card-body" style="padding:20px;">
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

        <div class="form-group">
          <label>Full Name <span style="color:red;">*</span></label>
          <input type="text" name="new_admin_name" class="form-control"
                 placeholder="e.g. Juan dela Cruz"
                 value="<?= htmlspecialchars($_POST['new_admin_name'] ?? '') ?>"
                 required>
        </div>

        <div class="form-group">
          <label>Username <span style="color:red;">*</span></label>
          <input type="text" name="new_admin_username" class="form-control"
                 placeholder="e.g. juandc"
                 value="<?= htmlspecialchars($_POST['new_admin_username'] ?? '') ?>"
                 autocomplete="off" required>
        </div>

        <div class="form-group">
          <label>Password <span style="color:red;">*</span></label>
          <input type="password" name="new_admin_password" class="form-control"
                 placeholder="Min. 8 characters" autocomplete="new-password"
                 required>
        </div>

        <div class="form-group">
          <label>Confirm Password <span style="color:red;">*</span></label>
          <input type="password" name="new_admin_confirm_password" class="form-control"
                 placeholder="Re-enter password" autocomplete="new-password"
                 required>
        </div>

      </div>

      <div style="margin-top:8px;">
        <button type="submit" name="create_admin" class="btn btn-primary">Create Admin Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Manage Admins -->
<div class="card" style="margin-top:20px;max-width:900px;">
  <div class="card-header"><h2>Manage Admins</h2></div>
  <div class="card-body" style="padding:20px;overflow-x:auto;">
    <table class="data-table" style="width:100%;">
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Status</th>
          <th>Activity</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($admins as $a):
        $isSelf   = (int)$a['id'] === $adminId;
        $isActive = $a['status'] === 'active';
        $online   = $isActive && $a['mins_ago'] !== null && (int)$a['mins_ago'] <= 5;
      ?>
        <tr style="<?= $isActive ? '' : 'opacity:.6;' ?>">
          <td style="font-weight:700;font-size:14px;">
            <?= htmlspecialchars($a['name']) ?><?= $isSelf ? ' <span style="color:#888;font-weight:600;">(You)</span>' : '' ?>
          </td>
          <td style="font-size:13.5px;"><?= htmlspecialchars($a['username'] ?? '') ?></td>
          <td>
            <?php if ($isActive): ?>
              <span style="background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;">Active</span>
            <?php else: ?>
              <span style="background:#fdecea;color:#c62828;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;">Disabled</span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px;white-space:nowrap;">
            <span style="display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;background:<?= $online ? '#2e7d32' : '#bbb' ?>;"></span>
            <?= htmlspecialchars(lastSeenLabel($a['mins_ago'])) ?>
          </td>
          <td style="font-size:13px;color:#555;white-space:nowrap;"><?= date('M d, Y', strtotime($a['created_at'] ?? 'now')) ?></td>
          <td>
            <?php if ($isSelf): ?>
              <span style="color:#888;font-size:12px;">This is you</span>
            <?php else: ?>
              <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">

                <!-- Reset password (own form so pressing Enter can't trigger Disable) -->
                <form method="POST" style="display:flex;gap:6px;">
                  <input type="hidden" name="target_id" value="<?= (int)$a['id'] ?>">
                  <input type="password" name="reset_password" class="form-control"
                         placeholder="New password" autocomplete="new-password" style="width:140px;">
                  <button type="submit" name="admin_action" value="reset_password" class="btn btn-sm btn-primary">Reset</button>
                </form>

                <!-- Disable / Enable -->
                <form method="POST">
                  <input type="hidden" name="target_id" value="<?= (int)$a['id'] ?>">
                  <?php if ($isActive): ?>
                    <button type="submit" name="admin_action" value="disable" class="btn btn-sm"
                            onclick="return confirm('Disable this admin? They will no longer be able to log in.');">Disable</button>
                  <?php else: ?>
                    <button type="submit" name="admin_action" value="enable" class="btn btn-sm btn-success">Enable</button>
                  <?php endif; ?>
                </form>

                <!-- Delete -->
                <form method="POST">
                  <input type="hidden" name="target_id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" name="admin_action" value="delete" class="btn btn-sm"
                          style="background:#c62828;color:#fff;"
                          onclick="return confirm('Permanently delete this admin account?');">Delete</button>
                </form>

              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- System Info -->
<div class="card" style="margin-top:20px;max-width:900px;">
  <div class="card-header"><h2>System Info</h2></div>
  <div class="card-body" style="padding:20px;">
    <table style="max-width:400px;">
      <tr><td style="padding:8px 0;font-weight:700;color:#888;font-size:13px;">System</td><td style="font-size:13px;">BiMAP Admin Panel</td></tr>
      <tr><td style="padding:8px 0;font-weight:700;color:#888;font-size:13px;">Version</td><td style="font-size:13px;">1.0.0</td></tr>
      <tr><td style="padding:8px 0;font-weight:700;color:#888;font-size:13px;">PHP Version</td><td style="font-size:13px;"><?= phpversion() ?></td></tr>
      <tr><td style="padding:8px 0;font-weight:700;color:#888;font-size:13px;">MySQL</td><td style="font-size:13px;"><?= $conn->server_info ?></td></tr>
    </table>
  </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
