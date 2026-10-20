<?php
class StockAudit extends Model
{
    protected function getTable(): string
    {
        return 'stock_audits';
    }

    /**
     * Records the difference between the system stock and the physical count.
     */
    public function record(
        int $productId,
        int $systemQty,
        int $physicalQty,
        string $reason,
        int $userId,
        bool $corrected = false
    ): bool {
        $difference = $physicalQty - $systemQty;
        $stmt = $this->db->prepare(
            "INSERT INTO stock_audits (audit_date, product_id, system_qty, physical_qty, difference, reason, corrected, user_id)
             VALUES (CURDATE(), :product_id, :system_qty, :physical_qty, :difference, :reason, :corrected, :user_id)"
        );

        return $stmt->execute([
            'product_id' => $productId,
            'system_qty' => $systemQty,
            'physical_qty' => $physicalQty,
            'difference' => $difference,
            'reason' => $reason,
            'corrected' => $corrected ? 1 : 0,
            'user_id' => $userId,
        ]);
    }

    /**
     * Applies the recorded correction to the product's current stock.
     */
    public function applyCorrection(int $auditId, int $userId): bool
    {
        $this->db->beginTransaction();
        try {
            $audit = $this->find($auditId);
            if (!$audit || (int) $audit['corrected'] === 1) {
                $this->db->rollBack();
                return false;
            }

            $product = new Product();
            $product->updateStockTo((int) $audit['product_id'], (int) $audit['physical_qty']);

            $stmt = $this->db->prepare(
                "UPDATE stock_audits SET corrected = 1, user_id = :user_id WHERE id = :id"
            );
            $stmt->execute(['user_id' => $userId, 'id' => $auditId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Returns audit rows, newest first, including product and user names.
     */
    public function search(?int $productId = null, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT sa.*, p.product_name, u.full_name AS audited_by
                FROM stock_audits sa
                JOIN products p ON sa.product_id = p.id
                LEFT JOIN users u ON sa.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($productId) {
            $sql .= ' AND sa.product_id = :product_id';
            $params['product_id'] = $productId;
        }
        if ($from) {
            $sql .= ' AND sa.audit_date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND sa.audit_date <= :to';
            $params['to'] = $to;
        }

        $sql .= ' ORDER BY sa.audit_date DESC, sa.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Returns totals and a count of mismatches for the current audit log.
     */
    public function summary(): array
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) AS total_audits,
                    SUM(CASE WHEN difference <> 0 THEN 1 ELSE 0 END) AS mismatches,
                    SUM(CASE WHEN corrected = 1 THEN 1 ELSE 0 END) AS corrected
             FROM stock_audits"
        );
        return $stmt->fetch() ?: ['total_audits' => 0, 'mismatches' => 0, 'corrected' => 0];
    }
}
