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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password, profile_pic FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_pic']  = $admin['profile_pic'];
        header('Location: admin/dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
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

        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 14px 16px;
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

        .btn-login {
            width: 100%;
            padding: 16px;
            margin-top: 10px;
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
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="admin@malita.gov.ph"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>

                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>

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

</body>
</html>