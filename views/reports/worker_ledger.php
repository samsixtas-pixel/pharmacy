<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$db = Database::getConnection();

$userId = (int) ($_GET['user_id'] ?? 0);
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$workers = $db->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();
if ($userId === 0 && !empty($workers)) {
    $userId = (int) $workers[0]['id'];
}

$worker = null;
foreach ($workers as $w) {
    if ((int) $w['id'] === $userId) {
        $worker = $w;
        break;
    }
}

$sales = $payments = $declarations = $expenses = [];
if ($worker) {
    $stmt = $db->prepare(
        "SELECT s.*, p.product_name, p.size, p.category
         FROM sales s
         JOIN products p ON s.product_id = p.id
         WHERE s.user_id = :uid AND s.sale_date BETWEEN :from AND :to
         ORDER BY s.sale_date DESC, s.id DESC"
    );
    $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
    $sales = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT pay.*, s.customer_name, s.total_amount
         FROM payments pay
         JOIN sales s ON pay.sale_id = s.id
         WHERE pay.user_id = :uid AND pay.payment_date BETWEEN :from AND :to
         ORDER BY pay.payment_date DESC, pay.id DESC"
    );
    $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
    $payments = $stmt->fetchAll();

    $declModel = new CashDeclaration();
    $declarations = $declModel->search($from, $to, $userId, 0);

    $stmt = $db->prepare(
        "SELECT * FROM expenses
         WHERE user_id = :uid AND expense_date BETWEEN :from AND :to
         ORDER BY expense_date DESC, id DESC"
    );
    $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
    $expenses = $stmt->fetchAll();
}

$pageTitle = 'Worker Ledger';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <select name="user_id" class="form-select" style="max-width:220px" onchange="this.form.submit()">
      <?php foreach ($workers as $w): ?>
        <option value="<?= $w['id'] ?>" <?= $userId === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['full_name']) ?> (@<?= e($w['username']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<?php if (!$worker): ?>
  <div class="alert alert-info">No worker selected.</div>
<?php else: ?>
  <?php
  $totRevenue = array_sum(array_column($sales, 'total_amount'));
  $totCash = array_sum(array_column($sales, 'amount_paid'));
  $totDiscount = array_sum(array_column($sales, 'discount'));
  $totDebtPay = array_sum(array_column($payments, 'amount'));
  $totExpenses = array_sum(array_column($expenses, 'amount'));
  $totDeclared = array_sum(array_column($declarations, 'declared_cash'));
  $totDiff = array_sum(array_column($declarations, 'difference'));
  ?>

  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="icon" style="background:#0D6E5D"><i class="bi bi-bag-check"></i></div><div><div class="label">Sales</div><div class="value"><?= count($sales) ?></div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card money-card"><div class="icon" style="background:#118057"><i class="bi bi-cash-stack"></i></div><div><div class="label">Revenue</div><div class="value"><?= formatMoney($totRevenue) ?></div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card money-card"><div class="icon" style="background:#A8680A"><i class="bi bi-percent"></i></div><div><div class="label">Discounts</div><div class="value"><?= formatMoney($totDiscount) ?></div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card money-card"><div class="icon" style="background:<?= $totDiff < 0 ? '#B91C1C' : '#0D6E5D' ?>;"><i class="bi bi-wallet2"></i></div><div><div class="label">Cash Diff.</div><div class="value" style="color:<?= $totDiff < 0 ? '#B91C1C' : '#0D6E5D' ?>"><?= ($totDiff >= 0 ? '+' : '−') . formatMoney(abs($totDiff)) ?></div></div></div></div></div>
  </div>

  <div class="card mb-3">
    <div class="card-header">1. Sales — <?= formatDate($from) ?> → <?= formatDate($to) ?></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Discount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($sales)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">No sales.</td></tr>
            <?php else: foreach ($sales as $s): ?>
              <tr>
                <td><?= formatDate($s['sale_date']) ?></td>
                <td><?= e($s['customer_name']) ?></td>
                <td><?= e($s['product_name']) ?> <span class="text-muted small"><?= e($s['size']) ?></span></td>
                <td><?= (int) $s['quantity'] ?></td>
                <td><?= formatMoney($s['total_amount']) ?></td>
                <td><?= $s['discount'] > 0 ? '<span class="text-danger">− ' . formatMoney($s['discount']) . '</span>' : '—' ?></td>
                <td><?= formatMoney($s['amount_paid']) ?></td>
                <td><?= formatMoney($s['remaining_balance']) ?></td>
                <td><?= statusBadge($s['payment_status']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">2. Debt Payments Received</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date</th><th>Customer</th><th>Amount</th></tr></thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">No debt payments.</td></tr>
            <?php else: foreach ($payments as $p): ?>
              <tr><td><?= formatDate($p['payment_date']) ?></td><td><?= e($p['customer_name']) ?></td><td><?= formatMoney($p['amount']) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">3. Expenses Recorded by Worker</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date</th><th>Title</th><th>Category</th><th>Amount</th></tr></thead>
          <tbody>
            <?php if (empty($expenses)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">No expenses.</td></tr>
            <?php else: foreach ($expenses as $x): ?>
              <tr><td><?= formatDate($x['expense_date']) ?></td><td><?= e($x['title']) ?></td><td><span class="badge bg-secondary text-capitalize"><?= e(Expense::categories()[$x['category']] ?? $x['category']) ?></span></td><td><?= formatMoney($x['amount']) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">4. Cash Declarations</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date</th><th>Expected</th><th>Declared</th><th>Difference</th><th>Verified</th><th>Notes</th></tr></thead>
          <tbody>
            <?php if (empty($declarations)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No declarations.</td></tr>
            <?php else: foreach ($declarations as $d):
              $diff = (float) $d['difference'];
              $cls = abs($diff) < 0.01 ? 'text-muted' : ($diff < 0 ? 'text-danger fw-bold' : 'text-success fw-bold');
            ?>
              <tr>
                <td><?= formatDate($d['declaration_date']) ?></td>
                <td><?= formatMoney($d['expected_cash']) ?></td>
                <td><?= formatMoney($d['declared_cash']) ?></td>
                <td class="<?= $cls ?>"><?= $diff == 0 ? '0.00' : ($diff > 0 ? '+ ' : '− ') . formatMoney(abs($diff)) ?></td>
                <td><?= $d['admin_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning">No</span>' ?></td>
                <td class="small"><?= e($d['notes']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
