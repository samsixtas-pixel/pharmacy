<?php
/**
 * Sale.php
 * -------------------------------------------------------
 * Manages sales transactions including automatic payment
 * status calculation (paid/partial/unpaid) and automatic
 * medicine stock reduction.
 *
 * Demonstrates: Inheritance, Encapsulation, business-rule
 * methods kept separate from presentation (controllers).
 * -------------------------------------------------------
 */

class Sale extends Model
{
    protected function getTable(): string
    {
        return 'sales';
    }

    /**
     * Determines payment status from amount paid vs total.
     * Centralized here so the rule is defined only once.
     */
    public static function determineStatus(float $totalAmount, float $amountPaid): string
    {
        if ($amountPaid <= 0) {
            return 'unpaid';
        }
        if ($amountPaid >= $totalAmount) {
            return 'paid';
        }
        return 'partial';
    }

    /**
     * Records a new sale, automatically computing total amount,
     * remaining balance, and payment status, then reduces stock.
     */
    public function addSale(string $customerName, int $productId, int $quantity, float $costPrice, float $sellingPrice, float $discount, float $amountPaid, string $saleDate, int $userId, ?string $customerPhone = null): bool
    {
        $customerName = trim($customerName) !== '' ? trim($customerName) : 'Walk-in';
        $grossTotal = $sellingPrice * $quantity;
        $discount = max(0, min($discount, $grossTotal));
        $totalAmount = $grossTotal - $discount;
        $totalCost = $costPrice * $quantity;
        $profit = $totalAmount - $totalCost;

        $amountPaid = min($amountPaid, $totalAmount);
        $remainingBalance = $totalAmount - $amountPaid;
        $status = self::determineStatus($totalAmount, $amountPaid);

        $stmt = $this->db->prepare(
        "INSERT INTO sales (customer_name, customer_phone, product_id, quantity, discount, cost_price, total_cost,
         selling_price, total_amount, profit,
         amount_paid, remaining_balance, payment_status, sale_date, user_id)
         VALUES (:customer, :phone, :product_id, :quantity, :discount, :cost, :total_cost,
                 :price, :total, :profit,
                 :paid, :balance, :status, :date, :user_id)"
    );

    $ok = $stmt->execute([
        'customer'   => $customerName,
        'phone'      => $customerPhone ?: null,
        'product_id' => $productId,
        'quantity'   => $quantity,
        'discount'   => $discount,
        'cost'       => $costPrice,
        'total_cost' => $totalCost,
        'price'      => $sellingPrice,
        'total'      => $totalAmount,
        'profit'     => $profit,
        'paid'       => $amountPaid,
        'balance'    => $remainingBalance,
        'status'     => $status,
        'date'       => $saleDate,
        'user_id'    => $userId,
    ]);

        if ($ok) {
            $product = new Product();
            $product->decreaseStock($productId, $quantity);
        }

        return $ok;
    }

    /**
     * Returns sales joined with product/medicine name, newest first.
     */
    public function getAllWithDetails(): array
    {
        $sql = "SELECT s.*, p.product_name, u.full_name AS sold_by
                FROM sales s
                JOIN products p ON s.product_id = p.id
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY s.sale_date DESC, s.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Returns the most recent N sales for the dashboard widget.
     */
    public function getRecent(int $limit = 5): array
    {
        $sql = "SELECT s.*, p.product_name
                FROM sales s
                JOIN products p ON s.product_id = p.id
                ORDER BY s.sale_date DESC, s.id DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Returns sales with PARTIAL or UNPAID status (debt list).
     */
    public function getOutstandingDebts(): array
    {
        $sql = "SELECT s.*, p.product_name
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.payment_status IN ('partial','unpaid')
                ORDER BY s.remaining_balance DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Applies a debt payment: increases amount_paid, decreases
     * remaining_balance, and auto-updates status to 'paid' when
     * the balance reaches zero. Also logs the payment to history.
     */
    public function applyPayment(int $saleId, float $amount, string $paymentDate, int $userId): bool
    {
        $sale = $this->find($saleId);
        if (!$sale) {
            return false;
        }

        $amount = min($amount, (float) $sale['remaining_balance']);
        if ($amount <= 0) {
            return false;
        }

        $newPaid    = (float) $sale['amount_paid'] + $amount;
        $newBalance = (float) $sale['total_amount'] - $newPaid;
        $newStatus  = self::determineStatus((float) $sale['total_amount'], $newPaid);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE sales SET amount_paid = :paid, remaining_balance = :balance, payment_status = :status WHERE id = :id"
            );
            $stmt->execute([
                'paid'    => $newPaid,
                'balance' => $newBalance,
                'status'  => $newStatus,
                'id'      => $saleId,
            ]);

            $stmt2 = $this->db->prepare(
                "INSERT INTO payments (sale_id, amount, payment_date, user_id) VALUES (:sale_id, :amount, :date, :user_id)"
            );
            $stmt2->execute([
                'sale_id' => $saleId,
                'amount'  => $amount,
                'date'    => $paymentDate,
                'user_id' => $userId,
            ]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /** Returns full payment history for one sale */
    public function getPaymentHistory(int $saleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pay.*, u.full_name AS received_by FROM payments pay
             LEFT JOIN users u ON pay.user_id = u.id
             WHERE pay.sale_id = :sale_id ORDER BY pay.payment_date DESC, pay.id DESC"
        );
        $stmt->execute(['sale_id' => $saleId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns all sales made today, newest first.
     */
    public function getToday(): array
    {
        $sql = "SELECT s.*, p.product_name
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.sale_date = CURDATE()
                ORDER BY s.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /** Count of sales made today */
    public function countToday(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM sales WHERE sale_date = CURDATE()")->fetchColumn();
    }

    /** Total revenue (total_amount) from today's sales */
    public function sumTodayTotal(): float
    {
        return (float) $this->db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date = CURDATE()")->fetchColumn();
    }

    /** Total cash actually collected (amount_paid) from today's sales */
    public function sumTodayPaid(): float
    {
        return (float) $this->db->query("SELECT COALESCE(SUM(amount_paid),0) FROM sales WHERE sale_date = CURDATE()")->fetchColumn();
    }

    /** ---------------- Dashboard aggregate helpers ---------------- */

    public function countAll(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sales WHERE payment_status = :status");
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public function sumOutstandingBalance(): float
    {
        return (float) $this->db->query("SELECT COALESCE(SUM(remaining_balance),0) FROM sales WHERE payment_status IN ('partial','unpaid')")->fetchColumn();
    }

    /* ---------------- Profit / Loss helpers ---------------- */

    public function sumRevenue(?string $from = null, ?string $to = null): float
    {
        $sql = "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND sale_date >= :from"; $params['from'] = $from; }
        if ($to) { $sql .= " AND sale_date <= :to"; $params['to'] = $to; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    public function sumCostOfGoods(?string $from = null, ?string $to = null): float
    {
        $sql = "SELECT COALESCE(SUM(total_cost),0) FROM sales WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND sale_date >= :from"; $params['from'] = $from; }
        if ($to) { $sql .= " AND sale_date <= :to"; $params['to'] = $to; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    public function sumGrossProfit(?string $from = null, ?string $to = null): float
    {
        $sql = "SELECT COALESCE(SUM(profit),0) FROM sales WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND sale_date >= :from"; $params['from'] = $from; }
        if ($to) { $sql .= " AND sale_date <= :to"; $params['to'] = $to; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /** Per-product profit breakdown (used in the profit report table). */
    public function profitByProduct(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT p.product_name, p.category, p.size,
                       SUM(s.quantity)    AS qty_sold,
                       SUM(s.discount)    AS total_discount,
                       SUM(s.total_amount) AS revenue,
                       SUM(s.total_cost)   AS total_cost,
                       SUM(s.profit)       AS profit
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND s.sale_date >= :from"; $params['from'] = $from; }
        if ($to) { $sql .= " AND s.sale_date <= :to"; $params['to'] = $to; }
        $sql .= " GROUP BY p.id ORDER BY profit DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Sum of all discounts given — used for suspicious activity detection */
    public function sumDiscounts(?string $from = null, ?string $to = null, ?int $userId = null): float
    {
        $sql = "SELECT COALESCE(SUM(discount),0) FROM sales WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND sale_date >= :from"; $params['from'] = $from; }
        if ($to) { $sql .= " AND sale_date <= :to"; $params['to'] = $to; }
        if ($userId) { $sql .= " AND user_id = :uid"; $params['uid'] = $userId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /** Sales made below cost (potential theft indicator) */
    public function salesBelowCost(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT s.*, p.product_name, p.category, u.full_name AS worker_name
                FROM sales s
                JOIN products p ON s.product_id = p.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.selling_price < s.cost_price";
        $params = [];
        if ($from) { $sql .= " AND s.sale_date >= :from"; $params['from'] = $from; }
        if ($to) { $sql .= " AND s.sale_date <= :to"; $params['to'] = $to; }
        $sql .= " ORDER BY s.sale_date DESC LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Revenue + profit for last N days — used by dashboard chart */
    public function dailyTrend(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $sql = "SELECT sale_date,
                       COALESCE(SUM(total_amount),0) AS revenue,
                       COALESCE(SUM(profit),0)       AS profit,
                       COUNT(*) AS sales_count
                FROM sales
                WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY sale_date
                ORDER BY sale_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Fill missing days with zeros
        $map = [];
        foreach ($rows as $r) $map[$r['sale_date']] = $r;
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = $map[$d] ?? [
                'sale_date' => $d, 'revenue' => 0, 'profit' => 0, 'sales_count' => 0,
            ];
        }
        return $out;
    }

    /** Top N products by revenue in a period */
    public function topProducts(int $limit = 5, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT p.product_name, p.size,
                       SUM(s.quantity) AS qty,
                       SUM(s.total_amount) AS revenue
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND s.sale_date >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND s.sale_date <= :to";   $params['to']   = $to; }
        $sql .= " GROUP BY p.id ORDER BY revenue DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Category split (wines vs whiskeys) for a period */
    public function categorySplit(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT p.category,
                       COALESCE(SUM(s.total_amount),0) AS revenue,
                       COALESCE(SUM(s.profit),0)       AS profit
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND s.sale_date >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND s.sale_date <= :to";   $params['to']   = $to; }
        $sql .= " GROUP BY p.category ORDER BY revenue DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
