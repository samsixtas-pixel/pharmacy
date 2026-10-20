<?php
/**
 * Purchase.php — MPANGO LOUNGE
 * -------------------------------------------------------
 * Purchase orders from suppliers. Each purchase has many
 * items; creating a purchase also increases product stock
 * and (optionally) updates product cost price.
 * -------------------------------------------------------
 */

class Purchase extends Model
{
    protected function getTable(): string
    {
        return 'purchases';
    }

    /**
     * Create a purchase order.
     * If $status === 'received', stock is increased immediately.
     * If $status === 'pending',  stock is NOT increased until admin marks received.
     */
    public function createPurchase(
        int $supplierId,
        string $invoiceNo,
        string $date,
        float $amountPaid,
        string $notes,
        array $items,
        bool $updateCost,
        int $userId,
        string $status = 'received'
    ): int {
        if (empty($items)) return 0;

        $total = 0;
        foreach ($items as $it) {
            $qty  = (int)($it['quantity'] ?? 0);
            $cost = (float)($it['unit_cost'] ?? 0);
            $total += $qty * $cost;
        }

        $amountPaid = max(0, min($amountPaid, $total));
        $balance    = $total - $amountPaid;
        $pStatus    = $amountPaid <= 0 ? 'unpaid' : ($balance <= 0.009 ? 'paid' : 'partial');

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO purchases
                    (supplier_id, invoice_no, purchase_date, total_amount, amount_paid, balance,
                     payment_status, status, notes, user_id)
                 VALUES
                    (:sid, :inv, :d, :tot, :paid, :bal, :pst, :st, :notes, :uid)"
            );
            $stmt->execute([
                'sid'   => $supplierId,
                'inv'   => $invoiceNo !== '' ? $invoiceNo : null,
                'd'     => $date,
                'tot'   => $total,
                'paid'  => $amountPaid,
                'bal'   => $balance,
                'pst'   => $pStatus,
                'st'    => $status,
                'notes' => $notes,
                'uid'   => $userId,
            ]);
            $purchaseId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                "INSERT INTO purchase_items
                    (purchase_id, product_id, quantity, quantity_crates, pack_size, unit_cost, subtotal)
                 VALUES
                    (:pid, :prid, :qty, :qtyc, :pk, :cost, :sub)"
            );
            $prodModel = new Product();

            foreach ($items as $it) {
                $qty    = (int)($it['quantity'] ?? 0);
                $cost   = (float)($it['unit_cost'] ?? 0);
                $sub    = $qty * $cost;
                $qtyCr  = (int)($it['quantity_crates'] ?? 0);
                $pkSize = (int)($it['pack_size'] ?? 1);

                $itemStmt->execute([
                    'pid'  => $purchaseId,
                    'prid' => (int)$it['product_id'],
                    'qty'  => $qty,
                    'qtyc' => $qtyCr,
                    'pk'   => $pkSize,
                    'cost' => $cost,
                    'sub'  => $sub,
                ]);

                if ($status === 'received') {
                    $prodModel->increaseStock((int)$it['product_id'], $qty);
                }

                if ($updateCost) {
                    $product = $prodModel->find((int)$it['product_id']);
                    if ($product && (float)$product['cost_price'] !== $cost) {
                        $logStmt = $this->db->prepare(
                            "INSERT INTO price_history
                                (product_id, old_cost, new_cost, old_selling, new_selling, changed_by, reason)
                             VALUES
                                (:pid, :oc, :nc, :os, :ns, :uid, :r)"
                        );
                        $logStmt->execute([
                            'pid' => (int)$it['product_id'],
                            'oc'  => (float)$product['cost_price'],
                            'nc'  => $cost,
                            'os'  => (float)$product['selling_price'],
                            'ns'  => (float)$product['selling_price'],
                            'uid' => $userId,
                            'r'   => 'Auto-update from purchase #' . $purchaseId,
                        ]);

                        $upd = $this->db->prepare("UPDATE products SET cost_price = :c WHERE id = :id");
                        $upd->execute(['c' => $cost, 'id' => (int)$it['product_id']]);
                    }
                }
            }

            $this->db->commit();
            return $purchaseId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Purchase::createPurchase failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark a pending purchase as received — increases stock for every item.
     */
    public function markReceived(int $purchaseId, int $adminId): bool
    {
        $p = $this->find($purchaseId);
        if (!$p) return false;
        if (($p['status'] ?? 'received') === 'received') return false;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM purchase_items WHERE purchase_id = :id");
            $stmt->execute(['id' => $purchaseId]);
            $items = $stmt->fetchAll();

            $prodModel = new Product();
            foreach ($items as $it) {
                $prodModel->increaseStock((int)$it['product_id'], (int)$it['quantity']);
            }

            $stmt = $this->db->prepare(
                "UPDATE purchases
                    SET status = 'received', received_at = NOW(), received_by = :uid
                  WHERE id = :id"
            );
            $stmt->execute(['uid' => $adminId, 'id' => $purchaseId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Purchase::markReceived failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Count of pending orders */
    public function countPending(): int
    {
        try {
            return (int) $this->db
                ->query("SELECT COUNT(*) FROM purchases WHERE status = 'pending'")
                ->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Full purchases list with supplier name */
    public function allWithSupplier(?string $from = null, ?string $to = null, ?int $supplierId = null): array
    {
        $sql = "SELECT p.*, s.supplier_name, u.full_name AS recorded_by
                FROM purchases p
                JOIN suppliers s ON p.supplier_id = s.id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND p.purchase_date >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND p.purchase_date <= :to";   $params['to']   = $to;   }
        if ($supplierId) { $sql .= " AND p.supplier_id = :sid"; $params['sid'] = $supplierId; }
        $sql .= " ORDER BY p.purchase_date DESC, p.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Single purchase with supplier + items */
    public function findWithDetails(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, s.supplier_name, s.phone AS supplier_phone, s.email AS supplier_email,
                    s.address AS supplier_address, u.full_name AS recorded_by
             FROM purchases p
             JOIN suppliers s ON p.supplier_id = s.id
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $purchase = $stmt->fetch();
        if (!$purchase) return false;

        $stmt = $this->db->prepare(
            "SELECT pi.*, pr.product_name, pr.size, pr.category
             FROM purchase_items pi
             JOIN products pr ON pi.product_id = pr.id
             WHERE pi.purchase_id = :pid"
        );
        $stmt->execute(['pid' => $id]);
        $purchase['items'] = $stmt->fetchAll();
        return $purchase;
    }

    /** Record a payment against a purchase */
    public function applyPayment(int $purchaseId, float $amount): bool
    {
        $p = $this->find($purchaseId);
        if (!$p) return false;
        $amount = min($amount, (float)$p['balance']);
        if ($amount <= 0) return false;

        $newPaid = (float)$p['amount_paid'] + $amount;
        $newBal  = (float)$p['total_amount'] - $newPaid;
        $status  = $newBal <= 0.009 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

        $stmt = $this->db->prepare(
            "UPDATE purchases SET amount_paid = :paid, balance = :bal, payment_status = :st WHERE id = :id"
        );
        return $stmt->execute([
            'paid' => $newPaid,
            'bal'  => $newBal,
            'st'   => $status,
            'id'   => $purchaseId,
        ]);
    }

    /** Total purchases in a range */
    public function sumRange(?string $from = null, ?string $to = null): float
    {
        $sql = "SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND purchase_date >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND purchase_date <= :to";   $params['to']   = $to;   }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}