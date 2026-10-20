<?php
/**
 * Report.php
 * -------------------------------------------------------
 * Centralizes all report queries (Products, Boxes, Stock,
 * Sales, Debts) with optional date filtering and search.
 *
 * Demonstrates: Encapsulation, separation of reporting
 * logic from presentation/views.
 * -------------------------------------------------------
 */

class Report extends Model
{
    protected function getTable(): string
    {
        return 'products'; // not used directly, reports span many tables
    }

    public function productReport(string $keyword = ''): array
    {
        $sql = "SELECT * FROM products WHERE product_name LIKE :keyword ORDER BY product_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['keyword' => "%{$keyword}%"]);
        return $stmt->fetchAll();
    }

    public function boxReport(string $keyword = ''): array
    {
        $sql = "SELECT * FROM boxes WHERE box_name LIKE :keyword ORDER BY box_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['keyword' => "%{$keyword}%"]);
        return $stmt->fetchAll();
    }

    public function boxInReport(?string $from = null, ?string $to = null): array
    {
        return $this->dateFilteredHistory('box_in', 'boxes', 'box_name', 'box_id', $from, $to);
    }

    public function boxOutReport(?string $from = null, ?string $to = null): array
    {
        return $this->dateFilteredHistory('box_out', 'boxes', 'box_name', 'box_id', $from, $to);
    }

    public function stockInReport(?string $from = null, ?string $to = null): array
    {
        return $this->dateFilteredHistory('stock_in', 'products', 'product_name', 'product_id', $from, $to);
    }

    public function stockOutReport(?string $from = null, ?string $to = null): array
    {
        return $this->dateFilteredHistory('stock_out', 'products', 'product_name', 'product_id', $from, $to);
    }

    public function salesReport(?string $from = null, ?string $to = null, string $keyword = ''): array
    {
        $sql = "SELECT s.*, p.product_name FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.customer_name LIKE :keyword";
        $params = ['keyword' => "%{$keyword}%"];

        if ($from) {
            $sql .= " AND s.sale_date >= :from";
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= " AND s.sale_date <= :to";
            $params['to'] = $to;
        }
        $sql .= " ORDER BY s.sale_date DESC, s.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function debtReport(?string $from = null, ?string $to = null, string $keyword = ''): array
    {
        $sql = "SELECT s.*, p.product_name FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.payment_status IN ('partial','unpaid') AND s.customer_name LIKE :keyword";
        $params = ['keyword' => "%{$keyword}%"];

        if ($from) {
            $sql .= " AND s.sale_date >= :from";
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= " AND s.sale_date <= :to";
            $params['to'] = $to;
        }
        $sql .= " ORDER BY s.remaining_balance DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function expenseReport(?string $from = null, ?string $to = null, string $keyword = ''): array
    {
        $sql = "SELECT e.*, u.full_name AS recorded_by
                FROM expenses e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.title LIKE :keyword";
        $params = ['keyword' => "%{$keyword}%"];

        if ($from) {
            $sql .= " AND e.expense_date >= :from";
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= " AND e.expense_date <= :to";
            $params['to'] = $to;
        }
        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Shared helper for box_in / box_out / stock_in / stock_out
     * reports, all of which share the same join shape.
     */
    private function dateFilteredHistory(string $table, string $joinTable, string $nameColumn, string $idColumn, ?string $from, ?string $to): array
    {
        $sql = "SELECT t.*, j.{$nameColumn} AS item_name, u.full_name AS recorded_by
                FROM {$table} t
                JOIN {$joinTable} j ON t.{$idColumn} = j.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($from) {
            $sql .= " AND t.transaction_date >= :from";
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= " AND t.transaction_date <= :to";
            $params['to'] = $to;
        }
        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
