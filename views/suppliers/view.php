<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$m = new Supplier();
$id = (int)($_GET['id'] ?? 0);
$supplier = $m->find($id);
if (!$supplier) { header('Location: index.php?error=Supplier not found'); exit; }

$db = Database::getConnection();
$stmt = $db->prepare(
    "SELECT p.*, u.full_name AS recorded_by FROM purchases p
     LEFT JOIN users u ON p.user_id = u.id
     WHERE p.supplier_id = :sid ORDER BY p.purchase_date DESC, p.id DESC"
);
$stmt->execute(['sid' => $id]);
$purchases = $stmt->fetchAll();

$pageTitle = 'Supplier Details';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card mb-3" style="max-width:750px">
  <div class="card-header d-flex justify-content-between">
    <span>Supplier Details</span>
    <a href="edit.php?id=<?= $supplier['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit</a>
  </div>
  <div class="card-body">
    <table class="table table-borderless mb-0">
      <tr><th width="180">Name</th><td><?= e($supplier['supplier_name']) ?></td></tr>
      <tr><th>Phone</th><td><?= e($supplier['phone']) ?></td></tr>
      <tr><th>Email</th><td><?= e($supplier['email']) ?></td></tr>
      <tr><th>Address</th><td><?= e($supplier['address']) ?></td></tr>
      <tr><th>Notes</th><td><?= nl2br(e($supplier['notes'])) ?></td></tr>
      <tr><th>Status</th><td><?= statusBadge($supplier['status']) ?></td></tr>
      <tr><th>Added</th><td><?= formatDate($supplier['created_at']) ?></td></tr>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">Purchases from this Supplier (<?= count($purchases) ?>)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Invoice</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($purchases)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No purchases yet.</td></tr>
          <?php else: foreach ($purchases as $p): ?>
            <tr>
              <td><?= formatDate($p['purchase_date']) ?></td>
              <td><?= e($p['invoice_no']) ?></td>
              <td><?= formatMoney($p['total_amount']) ?></td>
              <td><?= formatMoney($p['amount_paid']) ?></td>
              <td class="text-danger"><?= formatMoney($p['balance']) ?></td>
              <td><?= statusBadge($p['payment_status']) ?></td>
              <td><a href="<?= BASE_URL ?>views/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<a href="index.php" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Back</a>

<?php include __DIR__ . '/../../includes/footer.php'; ?>