<?php
session_start();

// If already logged in, skip straight to the dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php'); exit;
}

// Default entry point: show the public landing page.
// Only render the login form when explicitly requested via ?login=1
// (this is the link already used throughout landing.php, e.g. "Admin Login").
if (!isset($_GET['login'])) {
    include 'landing.php';
    exit;
}

require_once 'includes/db.php';

$error = '';
$MAX_TRIES    = 5;
$LOCK_SECONDS = 300; // 5 minutes

if (($_SESSION['login_locked_until'] ?? 0) > time()) {
    $wait = (int)ceil(($_SESSION['login_locked_until'] - time()) / 60);
    $error = "Too many failed attempts. Try again in $wait minute(s).";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password, profile_pic_data FROM admin WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_pic']  = $admin['profile_pic_data'];
        header('Location: admin/dashboard.php');
        exit;
    } else {
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= $MAX_TRIES) {
            $_SESSION['login_locked_until'] = time() + $LOCK_SECONDS;
            $_SESSION['login_fails'] = 0;
            $error = 'Too many failed attempts. Try again in 5 minutes.';
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | BiMAP — LGU Malita</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --blue: #1a73e8;
            --blue-dark: #0d47a1;
            --gold: #c8a55a;
            --text: #0d1b2a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8f9fa;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
        }

        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: white;
            padding: 35px 40px;
            text-align: center;
        }

        .header img {
            width: 90px;
            height: 90px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
            padding: 8px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            opacity: 0.95;
            font-size: 15px;
        }

        .card-body {
            padding: 45px 40px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14.5px;
        }

        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus {
            border-color: var(--blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.15);
        }

        /* Show / hide password */
        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 46px; }
        .toggle-pw {
            position: absolute;
            right: 0;
            top: 0;
            height: calc(100% - 18px); /* exclude input's bottom margin */
            width: 46px;
            background: none;
            border: none;
            color: #6b7280;
            font-size: 16px;
            cursor: pointer;
        }
        .toggle-pw:hover { color: var(--blue); }

        .btn-login {
            width: 100%;
            padding: 16px;
            margin-top: 4px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26, 115, 232, 0.3);
        }

        .error-msg {
            background: #fee2e2;
            color: #b91c1c;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-link {
            text-align: center;
            margin-top: 30px;
            font-size: 14.5px;
            color: #6b7280;
        }

        .footer-link a {
            color: var(--blue);
            font-weight: 600;
        }

        .lgu-badge {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">

            <!-- Official LGU Header -->
            <div class="header">
                <img src="https://malita.gov.ph/wp-content/uploads/2023/01/official_seal-min.png" 
                     alt="LGU Malita Seal" 
                     onerror="this.src='assets/logo.png';">
                <h1>BiMAP Admin</h1>
                <p>Barangay Integrated Monitoring & Alert Platform</p>
                <p style="margin-top:8px; font-size:13px; opacity:0.9;">
                    Local Government Unit of Malita, Davao Occidental
                </p>
            </div>

            <div class="card-body">
                <?php if ($error): ?>
                    <div class="error-msg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>

                    <label>Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password"
                               placeholder="Enter your password"
                               autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Show or hide password">
                            <i class="fa-solid fa-eye" id="pwIcon"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i> SIGN IN
                    </button>
                </form>

            </div>
        </div>

        <div class="lgu-badge">
            <i class="fa-solid fa-shield-halved"></i> Official LGU Malita System
        </div>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('pwIcon');
        const show  = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
    }
    </script>

</body>
</html>
