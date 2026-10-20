<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$errors = [];
$old = ['supplier_name'=>'', 'phone'=>'', 'email'=>'', 'address'=>'', 'notes'=>'', 'status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $v = new Validator();
    $v->required($_POST['supplier_name'] ?? '', 'Supplier name')
      ->maxLength($_POST['supplier_name'] ?? '', 150, 'Supplier name');

    if ($v->fails()) $errors = $v->getErrors();
    else {
        $m = new Supplier();
        $ok = $m->addSupplier(
            trim($_POST['supplier_name']),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['notes'] ?? ''),
            $_POST['status'] ?? 'active'
        );
        if ($ok) {
            AuditLog::record('supplier.create', 'suppliers', (int)$m->lastInsertId(), 'Added supplier: ' . trim($_POST['supplier_name']));
            header('Location: index.php?success=Supplier added successfully');
            exit;
        }
        $errors[] = 'Failed to add supplier.';
    }
}

$pageTitle = 'Add Supplier';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header">Add New Supplier</div>
  <div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="POST" novalidate>
      <div class="mb-3"><label class="form-label">Supplier Name</label><input type="text" name="supplier_name" class="form-control" value="<?= e($old['supplier_name']) ?>" required></div>
      <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>"></div>
        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= e($old['address']) ?>"></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= e($old['notes']) ?></textarea></div>
      <div class="mb-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= $old['status']==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $old['status']==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Supplier</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>