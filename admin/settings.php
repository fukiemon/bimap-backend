<?php
session_start();
require_once '../includes/db.php';

$page_title = 'Settings';
$active_nav = 'settings';

$success = '';
$error = '';

// Change password
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new_pw = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $admin = $conn->query("SELECT password FROM admin WHERE id=" . (int)$_SESSION['admin_id'])->fetch_assoc();
    if (!password_verify($current, $admin['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $conn->query("UPDATE admin SET password='$hash' WHERE id=" . (int)$_SESSION['admin_id']);
        $success = 'Password updated successfully!';
    }
}

// Update profile picture
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
            $uploadDir = '../uploads/admin_profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'admin_' . (int)$_SESSION['admin_id'] . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Clean up the old picture file, if any
                $old = $conn->query("SELECT profile_pic FROM admin WHERE id=" . (int)$_SESSION['admin_id'])->fetch_assoc();
                if (!empty($old['profile_pic']) && is_file('../' . $old['profile_pic'])) {
                    @unlink('../' . $old['profile_pic']);
                }

                $relativePath = 'uploads/admin_profiles/' . $filename;
                $stmt = $conn->prepare("UPDATE admin SET profile_pic=? WHERE id=?");
                $stmt->bind_param("si", $relativePath, $_SESSION['admin_id']);
                $stmt->execute();

                $_SESSION['admin_pic'] = $relativePath;
                $success = 'Profile picture updated successfully!';
            } else {
                $error = 'Could not save the uploaded image. Please try again.';
            }
        }
    }
}

// Update name
if (isset($_POST['update_name'])) {
    $name = trim($_POST['admin_name'] ?? '');
    if ($name) {
        $conn->query("UPDATE admin SET name='" . $conn->real_escape_string($name) . "' WHERE id=" . (int)$_SESSION['admin_id']);
        $_SESSION['admin_name'] = $name;
        $success = 'Profile updated successfully!';
    }
}

// Create new admin account
if (isset($_POST['create_admin'])) {
    $new_name     = trim($_POST['new_admin_name'] ?? '');
    $new_username = trim($_POST['new_admin_username'] ?? '');
    $new_email    = trim($_POST['new_admin_email'] ?? '');
    $new_pw       = $_POST['new_admin_password'] ?? '';
    $new_confirm  = $_POST['new_admin_confirm_password'] ?? '';

    if (empty($new_name) || empty($new_username) || empty($new_email) || empty($new_pw)) {
        $error = 'Name, username, email, and password are required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($new_pw) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($new_pw !== $new_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $escaped_username = $conn->real_escape_string($new_username);
        $escaped_email    = $conn->real_escape_string($new_email);

        // Check if username already exists
        $check_user  = $conn->query("SELECT id FROM admin WHERE username='$escaped_username'");
        // Check if email already exists
        $check_email = $conn->query("SELECT id FROM admin WHERE email='$escaped_email'");

        if ($check_user && $check_user->num_rows > 0) {
            $error = 'Username already exists. Please choose a different one.';
        } elseif ($check_email && $check_email->num_rows > 0) {
            $error = 'Email already exists. Please use a different email.';
        } else {
            $escaped_name = $conn->real_escape_string($new_name);
            $hashed_pw    = password_hash($new_pw, PASSWORD_DEFAULT);

            $result = $conn->query(
                "INSERT INTO admin (name, username, email, password, created_at)
                 VALUES ('$escaped_name', '$escaped_username', '$escaped_email', '$hashed_pw', NOW())"
            );

            if ($result) {
                $success = "Admin account for '$new_name' created successfully!";
            } else {
                $error = 'Failed to create admin account. Please try again.';
            }
        }
    }
}

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
          <img src="../<?= htmlspecialchars($_SESSION['admin_pic']) ?>" alt="Profile picture" style="width:100%;height:100%;object-fit:cover;">
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
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
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
                 required>
        </div>

        <div class="form-group">
          <label>Email Address <span style="color:red;">*</span></label>
          <input type="email" name="new_admin_email" class="form-control"
                 placeholder="e.g. juan@example.com"
                 value="<?= htmlspecialchars($_POST['new_admin_email'] ?? '') ?>"
                 required>
        </div>

        <!-- Spacer to keep grid aligned -->
        <div></div>

        <div class="form-group">
          <label>Password <span style="color:red;">*</span></label>
          <input type="password" name="new_admin_password" class="form-control"
                 placeholder="Min. 6 characters"
                 required>
        </div>

        <div class="form-group">
          <label>Confirm Password <span style="color:red;">*</span></label>
          <input type="password" name="new_admin_confirm_password" class="form-control"
                 placeholder="Re-enter password"
                 required>
        </div>

      </div>

      <div style="margin-top:8px;">
        <button type="submit" name="create_admin" class="btn btn-primary">Create Admin Account</button>
      </div>
    </form>
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