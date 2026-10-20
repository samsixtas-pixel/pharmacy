<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$declModel = new CashDeclaration();
$userId = (int)$_SESSION['user_id'];
$date = trim($_GET['date'] ?? date('Y-m-d'));

// Forbid future dates
if ($date > date('Y-m-d')) $date = date('Y-m-d');

$existing   = $declModel->findByUserAndDate($userId, $date);
$suggested  = $declModel->suggestedOpeningCash($userId, $date);

$openingCash = $existing ? (float)$existing['opening_cash'] : $suggested['amount'];

$calc = $declModel->computeExpected($userId, $date, $openingCash);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postDate = $_POST['declaration_date'] ?? $date;
    $mode = $_POST['mode'] ?? 'closing';

    if ($postDate > date('Y-m-d')) {
        $errors[] = 'You cannot declare for a future date.';
    }

    if (!$errors) {
        if ($mode === 'opening_only') {
            $ok = $declModel->submit($userId, $postDate, [
                'opening_cash' => (float)($_POST['opening_cash'] ?? 0),
                'force_opening_only' => true,
            ]);
            if ($ok) {
                AuditLog::record('cash.opening', 'cash_declarations', null,
                    'Opening cash declared: ' . number_format((float)($_POST['opening_cash'] ?? 0), 2));
                header('Location: declare.php?date=' . urlencode($postDate) . '&success=opening_saved');
                exit;
            }
            $errors[] = 'Failed to save opening cash.';
        } else {
            $opening = (float)($_POST['opening_cash'] ?? 0);
            $cashIn  = (float)($_POST['cash_in_hand'] ?? 0);
            $mobile  = (float)($_POST['mobile_money'] ?? 0);
            $reason  = trim($_POST['difference_reason'] ?? '');
            $notes   = trim($_POST['notes'] ?? '');

            $recalc = $declModel->computeExpected($userId, $postDate, $opening);
            $declaredTotal = $cashIn + $mobile;
            $diff = $declaredTotal - $recalc['expected_cash'];

            if (abs($diff) > 0.009 && $reason === '') {
                $errors[] = 'You must explain the difference (difference reason is required when cash differs from expected).';
            }

            if (!$errors) {
                $ok = $declModel->submit($userId, $postDate, [
                    'opening_cash'      => $opening,
                    'cash_in_hand'      => $cashIn,
                    'mobile_money'      => $mobile,
                    'notes'             => $notes,
                    'difference_reason' => $reason,
                ]);
                if ($ok) {
                    AuditLog::record('cash.declare', 'cash_declarations', null,
                        'Declared: cash ' . number_format($cashIn, 2) .
                        ' + mobile ' . number_format($mobile, 2) .
                        ' | expected ' . number_format($recalc['expected_cash'], 2) .
                        ' | diff ' . number_format($diff, 2) .
                        ' | date ' . $postDate);
                    header('Location: declare.php?date=' . urlencode($postDate) . '&success=1');
                    exit;
                }
                $errors[] = 'Failed to save declaration.';
            }
        }
    }
}

if (!empty($_GET['success'])) {
    $success = ($_GET['success'] === 'opening_saved')
        ? 'Opening cash saved. Good start to your shift!'
        : 'Declaration saved. Waiting for boss to confirm receipt.';
}

// Compute suggested opening again (in case staff just changed date)
$openingCash = $existing ? (float)$existing['opening_cash'] : $suggested['amount'];
$calc = $declModel->computeExpected($userId, $date, $openingCash);

$pageTitle = 'Declare Cash';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<!-- Date picker -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <label class="mb-0">Shift Date</label>
    <input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
  </form>
  <?php if ($existing && (int)$existing['admin_verified'] === 1): ?>
    <span class="badge bg-success" style="font-size:.85rem;padding:.5rem 1rem">
      <i class="bi bi-check2-circle"></i> Confirmed by boss &amp; day sealed
    </span>
  <?php elseif ($existing): ?>
    <span class="badge bg-warning" style="font-size:.85rem;padding:.5rem 1rem">
      <i class="bi bi-hourglass-split"></i> Awaiting boss confirmation
    </span>
  <?php endif; ?>
</div>

<?php if ($suggested['yesterday_unapproved']): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong>Yesterday's cash was not confirmed by the boss.</strong>
    You must declare the physical cash in hand at the <strong>start</strong> of this shift.
  </div>
<?php endif; ?>

<!-- System-computed info -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#118057"><i class="bi bi-cart-check-fill"></i></div>
      <div><div class="label">Cash from Sales</div><div class="value"><?= formatMoney($calc['cash_from_sales']) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-cash-coin"></i></div>
      <div><div class="label">Debt Payments</div><div class="value"><?= formatMoney($calc['debt_payments']) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#B91C1C"><i class="bi bi-wallet2"></i></div>
      <div><div class="label">Expenses Paid</div><div class="value"><?= formatMoney($calc['expenses_paid']) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#C98012"><i class="bi bi-journal-text"></i></div>
      <div><div class="label">New Debts</div><div class="value"><?= formatMoney($calc['new_debt']) ?></div></div>
    </div>
  </div>
</div>

<!-- Closing declaration form -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-cash-stack me-1"></i>Declare Cash for <?= formatDate($date) ?></div>
  <div class="card-body">
    <form method="POST" id="declareForm" novalidate>
      <input type="hidden" name="declaration_date" value="<?= e($date) ?>">
      <input type="hidden" name="mode" value="closing">

      <!-- Opening cash -->
      <div class="mb-3">
        <label class="form-label fw-bold">Opening Cash (cash in hand at start of shift)</label>
        <input type="number" step="0.01" min="0" name="opening_cash" id="openingInput"
               class="form-control form-control-lg" value="<?= e((string)$openingCash) ?>" required
               <?= $existing && (int)$existing['admin_verified'] === 1 ? 'readonly' : '' ?>>
        <small class="text-muted">
          <?php if ($suggested['yesterday_unapproved']): ?>
            Yesterday's cash was not confirmed. Physically count the cash in the drawer and enter it above.
          <?php else: ?>
            Usually 0 (you handed yesterday's cash to the boss). Change this if the boss left change for you.
          <?php endif; ?>
        </small>
      </div>

      <hr>

      <div class="mb-3">
        <label class="form-label fw-bold">Expected Cash (system)</label>
        <input type="text" id="expectedDisplay" class="form-control form-control-lg fw-bold text-end"
               readonly value="<?= number_format($calc['expected_cash'], 2) ?>"
               style="background:#EDF9F3;color:#0D6E5D">
        <small class="text-muted">= Opening + Cash from sales + Debt payments − Expenses</small>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-bold">Physical Cash in Hand (TZS)</label>
          <input type="number" step="0.01" min="0" name="cash_in_hand" id="cashInHandInput"
                 class="form-control form-control-lg" value="<?= e((string)($existing['cash_in_hand'] ?? 0)) ?>" required>
          <small class="text-muted">Count the notes &amp; coins physically.</small>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label fw-bold">Mobile Money Received (TZS)</label>
          <input type="number" step="0.01" min="0" name="mobile_money" id="mobileMoneyInput"
                 class="form-control form-control-lg" value="<?= e((string)($existing['mobile_money'] ?? 0)) ?>" required>
          <small class="text-muted">Total M-Pesa / Tigo Pesa / Airtel Money received today.</small>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">Total Declared (cash + mobile)</label>
        <input type="text" id="declaredDisplay" class="form-control form-control-lg fw-bold text-end"
               readonly value="0.00" style="font-size:1.5rem">
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">Difference (declared − expected)</label>
        <input type="text" id="differenceDisplay" class="form-control form-control-lg fw-bold text-end"
               readonly value="0.00">
      </div>

      <div class="mb-3" id="reasonWrapper" style="display:none">
        <label class="form-label fw-bold text-danger">Reason for Difference (required)</label>
        <textarea name="difference_reason" id="reasonInput" class="form-control" rows="3"
                  placeholder="e.g. Paid for taxi from the till, small change shortage, customer refund"
                  ><?= e($existing['difference_reason'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Notes (optional)</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($existing['notes'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-100"
              <?= $existing && (int)$existing['admin_verified'] === 1 ? 'disabled' : '' ?>>
        <i class="bi bi-check-circle-fill"></i> <?= $existing ? 'Update Declaration' : 'Submit Declaration' ?>
      </button>
    </form>
  </div>
</div>

<!-- Opening-only form (if yesterday unapproved and no declaration yet) -->
<?php if ($suggested['yesterday_unapproved'] && !$existing): ?>
<div class="card">
  <div class="card-header"><i class="bi bi-shield-exclamation me-1"></i>Start Shift — Declare Opening Cash Only</div>
  <div class="card-body">
    <p class="text-muted small">If you don't want to make a full closing declaration yet, you can declare the opening cash now. You can fill the rest at the end of shift.</p>
    <form method="POST">
      <input type="hidden" name="declaration_date" value="<?= e($date) ?>">
      <input type="hidden" name="mode" value="opening_only">
      <div class="row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label">Opening Cash in Hand</label>
          <input type="number" step="0.01" min="0" name="opening_cash" class="form-control" required value="0">
        </div>
        <div class="col-md-4">
          <button class="btn btn-outline-primary w-100"><i class="bi bi-play-circle"></i> Start Shift</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const expected = <?= json_encode((float)$calc['expected_cash']) ?>;
const openingInput = document.getElementById('openingInput');
const cashInput    = document.getElementById('cashInHandInput');
const mobileInput  = document.getElementById('mobileMoneyInput');
const expectedDisp = document.getElementById('expectedDisplay');
const declaredDisp = document.getElementById('declaredDisplay');
const diffDisp     = document.getElementById('differenceDisplay');
const reasonBox    = document.getElementById('reasonWrapper');
const reasonInput  = document.getElementById('reasonInput');

// The system recomputes expected as opening changes.
// The PHP-provided expected = opening + cashSales + debtPay − exp. Use base = expected − opening for dynamic adjust.
const baseExpectedNoOpening = expected - <?= json_encode((float)$openingCash) ?>;

function recalc() {
  const opening = parseFloat(openingInput.value || 0);
  const cash    = parseFloat(cashInput.value || 0);
  const mobile  = parseFloat(mobileInput.value || 0);

  const expNow = baseExpectedNoOpening + opening;
  const declared = cash + mobile;
  const diff = declared - expNow;

  expectedDisp.value = expNow.toLocaleString('en-US', { minimumFractionDigits: 2 });
  declaredDisp.value = declared.toLocaleString('en-US', { minimumFractionDigits: 2 });
  diffDisp.value = diff.toLocaleString('en-US', { minimumFractionDigits: 2 });
  diffDisp.style.color = Math.abs(diff) < 0.01 ? '#0D6E5D' : '#B91C1C';

  if (Math.abs(diff) > 0.009) {
    reasonBox.style.display = 'block';
    reasonInput.required = true;
  } else {
    reasonBox.style.display = 'none';
    reasonInput.required = false;
  }
}
openingInput.addEventListener('input', recalc);
cashInput.addEventListener('input', recalc);
mobileInput.addEventListener('input', recalc);
recalc();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>