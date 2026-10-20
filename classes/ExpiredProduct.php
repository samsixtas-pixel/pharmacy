<?php
/**
 * ExpiredProduct.php
 * -------------------------------------------------------
 * Staff submit expired/damaged/missing product reports.
 * Admin approves (stock decreases, loss recorded) or rejects.
 * Approved losses feed into the balance sheet as an expense.
 * -------------------------------------------------------
 */

class ExpiredProduct extends Model
{
    protected function getTable(): string { return 'expired_products'; }

    public static function reasons(): array
    {
        return [
            'expired'  => 'Expired',
            'damaged'  => 'Damaged',
            'broken'   => 'Broken',
            'missing'  => 'Missing',
            'other'    => 'Other',
        ];
    }

    /** Staff submits a new request */
    public function submit(int $productId, int $quantity, string $reason, string $notes, int $userId): bool
    {
        $product = (new Product())->find($productId);
        if (!$product) return false;

        $stmt = $this->db->prepare(
            "INSERT INTO expired_products (product_id, quantity, reason, notes, cost_at_time, requested_by)
             VALUES (:pid, :qty, :reason, :notes, :cost, :uid)"
        );
        return $stmt->execute([
            'pid'    => $productId,
            'qty'    => $quantity,
            'reason' => $reason,
            'notes'  => $notes,
            'cost'   => (float)$product['cost_price'],
            'uid'    => $userId,
        ]);
    }

    /** Admin approves — decreases stock + marks reviewed */
    public function approve(int $id, int $adminId, string $adminNotes = ''): bool
    {
        $item = $this->find($id);
        if (!$item || $item['status'] !== 'pending') return false;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE expired_products
                 SET status='approved', reviewed_by=:r, reviewed_at=NOW(), admin_notes=:n
                 WHERE id=:id"
            );
            $stmt->execute(['r' => $adminId, 'n' => $adminNotes, 'id' => $id]);

            (new Product())->decreaseStock((int)$item['product_id'], (int)$item['quantity']);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /** Admin rejects */
    public function reject(int $id, int $adminId, string $adminNotes = ''): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE expired_products
             SET status='rejected', reviewed_by=:r, reviewed_at=NOW(), admin_notes=:n
             WHERE id=:id AND status='pending'"
        );
        return $stmt->execute(['r' => $adminId, 'n' => $adminNotes, 'id' => $id]);
    }

    /** Flexible search */
    public function search(array $f = []): array
    {
        $sql = "SELECT ep.*, p.product_name, p.size, p.category,
                       u.full_name AS requester_name, v.full_name AS reviewer_name
                FROM expired_products ep
                JOIN products p ON ep.product_id = p.id
                LEFT JOIN users u ON ep.requested_by = u.id
                LEFT JOIN users v ON ep.reviewed_by = v.id
                WHERE 1=1";
        $params = [];
        if (!empty($f['status']))  { $sql .= " AND ep.status = :st";       $params['st'] = $f['status']; }
        if (!empty($f['reason']))  { $sql .= " AND ep.reason = :r";        $params['r']  = $f['reason']; }
        if (!empty($f['from']))    { $sql .= " AND DATE(ep.requested_at) >= :from"; $params['from'] = $f['from']; }
        if (!empty($f['to']))      { $sql .= " AND DATE(ep.requested_at) <= :to";   $params['to']   = $f['to']; }
        if (!empty($f['user_id'])) { $sql .= " AND ep.requested_by = :uid"; $params['uid'] = $f['user_id']; }
        $sql .= " ORDER BY ep.requested_at DESC LIMIT 300";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function pendingCount(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM expired_products WHERE status='pending'")->fetchColumn();
    }

    /** Total loss value (approved only) in a period */
    public function sumLoss(?string $from = null, ?string $to = null, ?int $userId = null): float
    {
        $sql = "SELECT COALESCE(SUM(cost_at_time * quantity),0) FROM expired_products
                WHERE status='approved'";
        $params = [];
        if ($from)   { $sql .= " AND DATE(reviewed_at) >= :from"; $params['from'] = $from; }
        if ($to)     { $sql .= " AND DATE(reviewed_at) <= :to";   $params['to']   = $to; }
        if ($userId) { $sql .= " AND requested_by = :uid";        $params['uid']  = $userId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /** Breakdown by reason */
    public function lossByReason(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT reason, COUNT(*) AS count, COALESCE(SUM(cost_at_time * quantity),0) AS loss
                FROM expired_products WHERE status='approved'";
        $params = [];
        if ($from) { $sql .= " AND DATE(reviewed_at) >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND DATE(reviewed_at) <= :to";   $params['to']   = $to; }
        $sql .= " GROUP BY reason ORDER BY loss DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Approved losses today — used on dashboards */
    public function sumLossToday(): float
    {
        return $this->sumLoss(date('Y-m-d'), date('Y-m-d'));
    }
}