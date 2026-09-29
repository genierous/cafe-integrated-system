<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $email = trim($_POST['email'] ?? '');

    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Only proceed for a real, active account — but the confirmation
        // message below is identical either way so we don't leak which
        // emails exist in the system.
        if ($user && $user['status'] === 'ACTIVE') {
            // Don't stack duplicate requests: reuse an existing PENDING one.
            $existing = $pdo->prepare("SELECT request_id FROM password_reset_requests WHERE user_id = ? AND status = 'PENDING' LIMIT 1");
            $existing->execute([$user['user_id']]);

            if (!$existing->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO password_reset_requests (user_id, unlock_at, ip_address)
                    VALUES (?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)");
                $stmt->execute([$user['user_id'], PASSWORD_RESET_LOCK_MINUTES, $_SERVER['REMOTE_ADDR'] ?? '']);
                $requestId = (int)$pdo->lastInsertId();

                notifyRole(
                    $pdo,
                    ROLE_HR_MANAGER,
                    'Password Reset Requested',
                    $user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ') forgot their password and is requesting a reset. Available for review in ' . PASSWORD_RESET_LOCK_MINUTES . ' minutes.',
                    'PASSWORD_RESET',
                    $requestId
                );
                logAudit($pdo, $user['user_id'], 'PASSWORD_RESET_REQUESTED', 'Auth', 'request_id=' . $requestId);
            }
        } else {
            // No matching/active account — log the attempt for security visibility,
            // but still show the generic confirmation message to the visitor.
            logAudit($pdo, null, 'PASSWORD_RESET_REQUEST_UNKNOWN_EMAIL', 'Auth', 'Email attempted: ' . $email);
        }
    }

    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Nunito:ital,wght@0,300;0,400;0,600;0,700;0,800;1,400&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/hrms/css/style.css?v=<?= ASSET_VER ?>">
<link rel="stylesheet" href="assets/shared/responsive.css?v=<?= defined('ASSET_VER') ? ASSET_VER : time() ?>">
</head>
<body class="landing-page">
  <div class="container py-5">
    <div class="text-center mb-5">
      <i class="bi bi-shield-lock-fill fs-1 text-white"></i>
      <h3 class="fw-bold mt-2 text-white"><?= APP_NAME ?></h3>
      <p class="text-white-50"><?= APP_TAGLINE ?></p>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-4">
        <div class="card auth-card shadow-lg">
          <div class="card-body p-4">
            <div class="text-center mb-4">
              <i class="bi bi-shield-lock fs-2 text-primary"></i>
              <h5 class="fw-bold mt-2">Forgot Password</h5>
              <p class="text-muted small mb-0">We'll notify HR to reset it for you</p>
            </div>

            <?php if ($submitted): ?>
              <div class="alert alert-success small">
                <i class="bi bi-check-circle"></i>
                If that email has an account with us, we've notified HR.
                For account security, your HR Manager can only process the reset
                after a <?= PASSWORD_RESET_LOCK_MINUTES ?>-minute waiting period.
                Please coordinate with HR once that time has passed.
              </div>
              <a href="index.php" class="btn btn-outline-secondary w-100">Back to Login</a>
            <?php else: ?>
              <p class="small text-muted">
                Enter the email address on your account. HR will be notified right away,
                but for security, resets are only allowed after a
                <?= PASSWORD_RESET_LOCK_MINUTES ?>-minute cooldown &mdash; this protects
                your account from being taken over through a rushed or fake request.
              </p>
              <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="mb-3">
                  <label class="form-label">Email Address</label>
                  <input type="email" name="email" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100">Notify HR</button>
              </form>
              <p class="text-center small mt-3 mb-0">
                <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
              </p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
