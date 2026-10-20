<?php
class PriceHistory extends Model
{
    protected function getTable(): string { return 'price_history'; }

    /** Log a manual price change (used by product edit) */
    public function log(int $productId, float $oldCost, float $newCost, float $oldSelling, float $newSelling, int $userId, string $reason = ''): bool
    {
        if ($oldCost == $newCost && $oldSelling == $newSelling) return false;

        $stmt = $this->db->prepare(
            "INSERT INTO price_history (product_id, old_cost, new_cost, old_selling, new_selling, changed_by, reason)
             VALUES (:pid, :oc, :nc, :os, :ns, :uid, :r)"
        );
        return $stmt->execute([
            'pid' => $productId, 'oc' => $oldCost, 'nc' => $newCost,
            'os'  => $oldSelling, 'ns' => $newSelling, 'uid' => $userId, 'r' => $reason,
        ]);
    }

    /** Recent history with product + user names */
    public function search(?int $productId = null, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT ph.*, p.product_name, p.size, u.full_name AS changed_by_name
                FROM price_history ph
                JOIN products p ON ph.product_id = p.id
                LEFT JOIN users u ON ph.changed_by = u.id
                WHERE 1=1";
        $params = [];
        if ($productId) { $sql .= " AND ph.product_id = :pid"; $params['pid'] = $productId; }
        if ($from) { $sql .= " AND DATE(ph.created_at) >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND DATE(ph.created_at) <= :to";   $params['to']   = $to; }
        $sql .= " ORDER BY ph.id DESC LIMIT 300";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}