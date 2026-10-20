<?php
require_once __DIR__ . '/../../includes/autoload.php';
User::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$expenseModel = new Expense();
$expense = $expenseModel->find($id);

if ($expense) {
    PeriodLock::ensureUnlocked($expense['expense_date'], 'delete an expense in');
    $expenseModel->delete($id);
    AuditLog::record('expense.delete', 'expenses', $id, 'Deleted expense: ' . $expense['title']);
}

header('Location: index.php?success=Expense deleted successfully');
exit;
