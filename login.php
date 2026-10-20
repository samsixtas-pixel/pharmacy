<?php
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_GET['cancel'])) {
    unset($_SESSION['pending_2fa_user']);
    header('Location: login.php');
    exit;
}

if (User::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ================= 2FA step =================
    if (($_POST['step'] ?? '') === '2fa') {
        $userId = (int)($_SESSION['pending_2fa_user'] ?? 0);
        $code   = trim($_POST['code'] ?? '');

        $rate = Security::checkLoginRateLimit('2fa_' . $userId, $ip);
        if (!$rate['allowed']) {
            $error = 'Too many attempts. Try again in ' . ceil($rate['retry_after'] / 60) . ' minute(s).';
            Security::logSuspicious('auth.2fa.rate_limited', 'User #' . $userId . ' rate-limited');
        } elseif ($userId <= 0) {
            header('Location: login.php');
            exit;
        } else {
            $userModel = new User();
            $user = $userModel->find($userId);

            if ($user && !empty($user['totp_enabled'])) {
                $valid = Totp::verify($user['totp_secret'], $code)
                      || $userModel->consumeBackupCode($userId, $code);

                if ($valid) {
                    Security::recordLoginAttempt('2fa_' . $userId, $ip, true);
                    unset($_SESSION['pending_2fa_user']);
                    Security::regenerateSession();
                    $userModel->createSession($user);
                    AuditLog::record('auth.2fa.success', 'user', $userId, 'Two-factor verified');
                    header('Location: dashboard.php');
                    exit;
                }
                Security::recordLoginAttempt('2fa_' . $userId, $ip, false);
                AuditLog::record('auth.2fa.fail', 'user', $userId, 'Invalid 2FA code');
                $error = 'Invalid or expired 2FA code.';
            } else {
                header('Location: login.php');
                exit;
            }
        }
    }
    // ================= Username + password =================
    else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $validator = new Validator();
        $validator->required($username, 'Username')->required($password, 'Password');

        if ($validator->fails()) {
            $error = implode(' ', $validator->getErrors());
        } else {
            $rate = Security::checkLoginRateLimit($username, $ip);
            if (!$rate['allowed']) {
                $mins = ceil($rate['retry_after'] / 60);
                $error = "Too many failed attempts. Try again in {$mins} minute(s).";
                Security::logSuspicious('auth.login.rate_limited', 'Username: ' . $username . ' | IP: ' . $ip);
            } else {
                $userModel = new User();
                $user = $userModel->login($username, $password);

                if ($user) {
                    Security::recordLoginAttempt($username, $ip, true);

                    if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
                        $_SESSION['pending_2fa_user'] = (int)$user['id'];
                        AuditLog::record('auth.2fa.challenge', 'user', (int)$user['id'],
                            'Password OK, awaiting 2FA');
                    } else {
                        Security::regenerateSession();
                        $userModel->createSession($user);
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    Security::recordLoginAttempt($username, $ip, false);
                    AuditLog::record('auth.login.fail', null, null,
                        'Failed login for "' . $username . '" from ' . $ip);
                    $error = 'Invalid username or password.';
                }
            }
        }
    }
}

$pendingUser = null;
if (!empty($_SESSION['pending_2fa_user'])) {
    $userModel = new User();
    $pendingUser = $userModel->find((int)$_SESSION['pending_2fa_user']);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#041F18">
<title>Sign In | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/images/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login-glass.css?v=<?= @filemtime(__DIR__ . '/assets/css/login-glass.css') ?: 1 ?>">
<meta name="csrf-token" content="<?= e(Security::csrfToken()) ?>">
</head>
<body>
<main class="scene">
  <!-- Layer 1: sharp gradient + dot pattern -->
  <div class="background" aria-hidden="true"></div>
  <!-- Layer 1b: subtle vignette -->
  <div class="shade" aria-hidden="true"></div>
  <!-- Layer 2: blur bubble (radial mask fades blur to nothing at edges) -->
  <div class="blur-bubble" aria-hidden="true"></div>
  <!-- Layer 2b: grain overlay -->
  <div class="grain" aria-hidden="true"></div>

  <section class="login-shell" aria-labelledby="login-title">
    <article class="login-card">
      <header class="topline">
        <span class="wordmark">WELCOME <span>BACK</span></span>
        <span class="badge-secure">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          Secure
        </span>
      </header>

      <?php if ($pendingUser): ?>
        <!-- ============ 2FA STEP ============ -->
        <div class="intro">
          <p class="eyebrow"><i></i> TWO-FACTOR AUTHENTICATION</p>
          <h1 id="login-title">Verify <em>you.</em></h1>
          <p class="intro-copy">Open Google Authenticator and enter the 6-digit code for your account.</p>
        </div>

        <?php if ($error): ?>
          <p class="message error" role="status"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form" novalidate>
          <input type="hidden" name="step" value="2fa">

          <label class="field">
            <span class="field-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
            </span>
            <span class="sr-only">Authentication code</span>
            <input type="text" name="code"
                   inputmode="numeric" pattern="[0-9A-Za-z]*" maxlength="8"
                   placeholder="123456" autocomplete="one-time-code"
                   autofocus required>
          </label>

          <button class="submit" type="submit">
            <span class="submit-label">Verify</span>
            <span class="arrow" aria-hidden="true">↗</span>
            <span class="glint" aria-hidden="true"></span>
          </button>
        </form>

        <footer class="card-footer">
          <a href="login.php?cancel=1">Cancel and return to login</a>
        </footer>

      <?php else: ?>
        <!-- ============ NORMAL LOGIN ============ -->
        <div class="intro">
          <p class="eyebrow"><i></i> YOUR SPACE, SECURED</p>
          <h1 id="login-title">Mpango <em>Lounge.</em></h1>
          <p class="intro-copy">Sign in to manage sales, stock and cash for MPANGO LOUNGE.</p>
        </div>

        <?php if ($error): ?>
          <p class="message error" role="status"><?= e($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['success'])): ?>
          <p class="message success" role="status"><?= e($_GET['success']) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form" novalidate>
          <label class="field">
            <span class="field-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </span>
            <span class="sr-only">Username</span>
            <input type="text" name="username" autocomplete="username"
                   placeholder="Username" autofocus required>
          </label>

          <label class="field">
            <span class="field-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
            </span>
            <span class="sr-only">Password</span>
            <input id="password" type="password" name="password"
                   autocomplete="current-password" placeholder="Password" required>
            <button class="reveal" type="button"
                    aria-label="Show password" aria-pressed="false"
                    title="Show password">
              <svg viewBox="0 0 24 24" class="icon-show" aria-hidden="true">
                <path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"/>
                <circle cx="12" cy="12" r="2.7"/>
              </svg>
              <svg viewBox="0 0 24 24" class="icon-hide" aria-hidden="true" style="display:none">
                <path d="M3 3l18 18"/>
                <path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.2 0 9.5 7 9.5 7a16.3 16.3 0 0 1-3.6 4.2"/>
                <path d="M6.3 6.3C3.7 8.1 2.5 12 2.5 12s3.3 7 9.5 7a10.4 10.4 0 0 0 4-.8"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
              </svg>
            </button>
          </label>

          <button class="submit" type="submit">
            <span class="submit-label">Sign in</span>
            <span class="arrow" aria-hidden="true">↗</span>
            <span class="glint" aria-hidden="true"></span>
          </button>
        </form>

        <footer class="card-footer">
          <span>Contact the boss to get account access.</span>
        </footer>
      <?php endif; ?>

      <div class="edge" aria-hidden="true"></div>
    </article>

    <p class="below-note">
      <i></i> MPANGO LOUNGE <span>/</span> mmari designs
    </p>
  </section>
</main>

<script>
// CSRF auto-inject
(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token) return;
  document.querySelectorAll('form').forEach(f => {
    if (f.method.toLowerCase() === 'post' && !f.querySelector('input[name="csrf_token"]')) {
      const i = document.createElement('input');
      i.type = 'hidden';
      i.name = 'csrf_token';
      i.value = token;
      f.appendChild(i);
    }
  });
})();

// Password reveal toggle
(function () {
  const btn = document.querySelector('.reveal');
  const pw  = document.querySelector('#password');
  if (!btn || !pw) return;

  const iconShow = btn.querySelector('.icon-show');
  const iconHide = btn.querySelector('.icon-hide');

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    const hidden = pw.type === 'password';
    pw.type = hidden ? 'text' : 'password';
    btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
    btn.setAttribute('aria-label',   hidden ? 'Hide password' : 'Show password');
    btn.setAttribute('title',        hidden ? 'Hide password' : 'Show password');
    iconShow.style.display = hidden ? 'none' : '';
    iconHide.style.display = hidden ? ''     : 'none';
    const len = pw.value.length;
    pw.focus();
    try { pw.setSelectionRange(len, len); } catch (_) {}
  });
})();
</script>
</body>
</html>