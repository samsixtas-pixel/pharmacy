<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$creditModel = new CustomerCredit();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $creditLimit = (float) ($_POST['credit_limit'] ?? 0);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $v = new Validator();
    $v->required($customerName, 'Customer name')
      ->required($_POST['credit_limit'] ?? '', 'Credit limit')
      ->numeric($_POST['credit_limit'] ?? '', 'Credit limit')
      ->minValue($creditLimit, 0, 'Credit limit');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } elseif ($creditModel->save($customerName, $creditLimit, $notes)) {
        header('Location: credit.php?success=Credit limit saved successfully');
        exit;
    } else {
        $errors[] = 'Unable to save the customer credit limit.';
    }
}

$customers = $creditModel->all('customer_name ASC');
$pageTitle = 'Customer Credit';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Set Credit Limit</div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" value="<?= e($_POST['customer_name'] ?? '') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Credit Limit (TZS)</label>
            <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="<?= e((string) ($_POST['credit_limit'] ?? '')) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Limit</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Customer Credit Limits</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Customer</th><th>Credit Limit</th><th>Outstanding</th><th>Available</th><th>Notes</th></tr></thead>
            <tbody>
              <?php if (empty($customers)): ?><tr><td colspan="5" class="text-center text-muted py-4">No credit limits set.</td></tr>
              <?php else: foreach ($customers as $customer): ?>
                <tr>
                  <td><?= e($customer['customer_name']) ?></td>
                  <td><?= formatMoney($customer['credit_limit']) ?></td>
                  <td><?= formatMoney($creditModel->outstandingBalance($customer['customer_name'])) ?></td>
                  <td><?= formatMoney($creditModel->nextAvailableCredit($customer['customer_name'])) ?></td>
                  <td><?= e($customer['notes'] ?: '—') ?></td>
                  <td>
                      <a href="<?= BASE_URL ?>views/reports/customer_statement_pdf.php?customer=<?= urlencode($_REQUEST['customer_name']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Statement">
                      <i class="bi bi-file-earmark-text"></i>
                      </a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
