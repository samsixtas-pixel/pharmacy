<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$userModel = new User();
$userId = (int)$_SESSION['user_id'];
$user = $userModel->find($userId);

$errors = [];
$step = $_GET['step'] ?? 'start';

if ($step === 'start' || $step === 'restart') {
    if (empty($user['totp_secret']) || $step === 'restart') {
        $_SESSION['temp_totp_secret'] = Totp::generateSecret();
    } else {
        $_SESSION['temp_totp_secret'] = $user['totp_secret'];
    }
}

$secret = $_SESSION['temp_totp_secret'] ?? $user['totp_secret'] ?? '';
$uri    = Totp::provisioningUri($secret, $user['username'], APP_NAME);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $code = trim($_POST['code'] ?? '');
    if (Totp::verify($secret, $code)) {
        $backup = Totp::generateBackupCodes(8);
        $userModel->enableTotp($userId, $secret, $backup);
        unset($_SESSION['temp_totp_secret']);
        AuditLog::record('auth.2fa.enabled', 'user', $userId, '2FA enabled');
        $_SESSION['show_backup_codes'] = $backup;
        header('Location: setup_2fa.php?step=codes');
        exit;
    }
    $errors[] = 'That code is incorrect. Check your phone time and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable') {
    $userModel->disableTotp($userId);
    AuditLog::record('auth.2fa.disabled', 'user', $userId, '2FA disabled');
    header('Location: setup_2fa.php?success=' . urlencode('Two-factor authentication disabled.'));
    exit;
}

$backupCodes = $_SESSION['show_backup_codes'] ?? null;
if ($step === 'codes' && $backupCodes) unset($_SESSION['show_backup_codes']);

$pageTitle = 'Two-Factor Authentication';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-shield-lock-fill me-1"></i> Two-Factor Authentication (2FA)
        <?php if (!empty($user['totp_enabled'])): ?>
          <span class="badge bg-success ms-2">Enabled</span>
        <?php else: ?>
          <span class="badge bg-warning ms-2">Disabled</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <?php if ($step === 'codes' && $backupCodes): ?>
          <div class="alert alert-success"><i class="bi bi-check2-circle"></i> <strong>2FA enabled.</strong> Save these backup codes — each works ONCE if you lose your phone.</div>
          <div class="row g-2 mb-3">
            <?php foreach ($backupCodes as $c): ?>
              <div class="col-6 col-md-4"><div class="border rounded p-2 text-center font-monospace"><?= e($c) ?></div></div>
            <?php endforeach; ?>
          </div>
          <a href="setup_2fa.php" class="btn btn-primary">Done</a>

        <?php elseif (!empty($user['totp_enabled'])): ?>
          <p class="text-muted">2FA is active. You will be asked for a code on every login.</p>
          <form method="POST" onsubmit="return confirm('Disable 2FA?')">
            <input type="hidden" name="action" value="disable">
            <button class="btn btn-outline-danger"><i class="bi bi-shield-slash"></i> Disable 2FA</button>
          </form>

        <?php else: ?>
          <p>Setup 2FA in <strong>three steps</strong>:</p>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="card border-0 bg-light h-100">
                <div class="card-body">
                  <div class="fw-bold mb-2"><span class="badge bg-primary me-1">1</span> Scan this QR code</div>
                  <div id="qrcode" class="text-center p-2" style="background:#fff;border-radius:8px;min-height:220px;display:flex;align-items:center;justify-content:center">
                    <span class="text-muted small">Generating QR…</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card border-0 bg-light h-100">
                <div class="card-body">
                  <div class="fw-bold mb-2"><span class="badge bg-primary me-1">2</span> Or enter this secret manually</div>
                  <p class="text-muted small mb-2">In Google Authenticator, tap <strong>+</strong> → <strong>Enter a setup key</strong>, then paste this:</p>
                  <div class="input-group mb-2">
                    <input type="text" id="secretField" class="form-control font-monospace text-center" value="<?= e($secret) ?>" readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="copySecret()">
                      <i class="bi bi-clipboard"></i>
                    </button>
                  </div>
                  <small class="text-muted">Account name: <strong><?= e($user['username']) ?></strong><br>
                  Type: <strong>Time-based</strong></small>
                </div>
              </div>
            </div>
          </div>

          <div class="card border-0 bg-light mb-3">
            <div class="card-body">
              <div class="fw-bold mb-2"><span class="badge bg-primary me-1">3</span> Enter the 6-digit code shown in the app</div>
              <form method="POST">
                <input type="hidden" name="action" value="verify">
                <div class="row g-2 align-items-end">
                  <div class="col-md-8">
                    <input type="text" name="code" class="form-control form-control-lg text-center font-monospace"
                           inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                           placeholder="123456" autocomplete="one-time-code" required autofocus
                           style="letter-spacing:.3em;font-size:1.5rem">
                  </div>
                  <div class="col-md-4">
                    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-check-lg"></i> Enable</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i> Why 2FA?</div>
      <div class="card-body small text-muted">
        <p>Even if someone steals your password, they cannot log in without the code from your phone.</p>
        <p><strong>Strongly recommended for admin accounts.</strong></p>
        <p class="mb-0">If you lose your phone, use a one-time backup code.</p>
      </div>
    </div>
  </div>
</div>

<!-- QR library (client-side, no external image API) -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script>
const TOTP_URI = <?= json_encode($uri) ?>;

function renderQR() {
  const container = document.getElementById('qrcode');
  if (!container) return;

  // Try client-side QR generation
  try {
    if (typeof qrcode !== 'function') throw new Error('Library not loaded');

    const qr = qrcode(0, 'M');
    qr.addData(TOTP_URI);
    qr.make();
    container.innerHTML = qr.createImgTag(5, 8);
    container.querySelector('img').style.maxWidth = '100%';
  } catch (e) {
    // Fallback: show a message pointing to the manual secret
    container.innerHTML = `
      <div class="text-center">
        <i class="bi bi-qr-code" style="font-size:4rem;color:#C4D8CE"></i>
        <p class="text-muted small mt-2 mb-1">QR couldn't be generated</p>
        <p class="text-muted small mb-0">Use the manual secret on the right →</p>
      </div>
    `;
  }
}

function copySecret() {
  const f = document.getElementById('secretField');
  f.select();
  f.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(f.value).then(() => {
    Swal.fire({ icon: 'success', title: 'Copied', text: 'Paste it into your authenticator app', timer: 1800, showConfirmButton: false });
  }).catch(() => {
    document.execCommand('copy');
    Swal.fire({ icon: 'success', title: 'Copied', timer: 1500, showConfirmButton: false });
  });
}

document.addEventListener('DOMContentLoaded', renderQR);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>