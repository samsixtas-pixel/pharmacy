<?php
/**
 * Expense.php
 * -------------------------------------------------------
 * Manages MPANGO LOUNGE operating expenses (matumizi): rent,
 * utilities, salaries, transport, maintenance, supplies,
 * and other costs — separate from medicine/box stock.
 *
 * Demonstrates: Inheritance (extends Model), Encapsulation,
 * CRUD, search/filter, dashboard aggregate helpers.
 * -------------------------------------------------------
 */

class Expense extends Model
{
    protected function getTable(): string
    {
        return 'expenses';
    }

    /** Valid expense categories, used by add/edit forms */
    public static function categories(): array
    {
        return [
            'rent'        => 'Rent',
            'utilities'   => 'Utilities',
            'salaries'    => 'Salaries',
            'transport'   => 'Transport',
            'maintenance' => 'Maintenance',
            'supplies'    => 'Supplies',
            'other'       => 'Other',
        ];
    }

    public function addExpense(string $title, string $category, float $amount, string $date, string $notes, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO expenses (title, category, amount, expense_date, notes, user_id)
             VALUES (:title, :category, :amount, :date, :notes, :user_id)"
        );

        return $stmt->execute([
            'title'    => $title,
            'category' => $category,
            'amount'   => $amount,
            'date'     => $date,
            'notes'    => $notes,
            'user_id'  => $userId,
        ]);
    }

    public function updateExpense(int $id, string $title, string $category, float $amount, string $date, string $notes): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE expenses SET title = :title, category = :category, amount = :amount,
             expense_date = :date, notes = :notes WHERE id = :id"
        );

        return $stmt->execute([
            'title'    => $title,
            'category' => $category,
            'amount'   => $amount,
            'date'     => $date,
            'notes'    => $notes,
            'id'       => $id,
        ]);
    }

    /**
     * Returns expenses joined with the recording user, newest first,
     * with optional keyword search and date range filtering.
     */
    public function search(string $keyword = '', ?string $from = null, ?string $to = null, string $category = ''): array
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
        if ($category) {
            $sql .= " AND e.category = :category";
            $params['category'] = $category;
        }
        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Returns the most recent N expenses for the dashboard widget */
    public function getRecent(int $limit = 5): array
    {
        $sql = "SELECT e.*, u.full_name AS recorded_by
                FROM expenses e
                LEFT JOIN users u ON e.user_id = u.id
                ORDER BY e.expense_date DESC, e.id DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** ---------------- Dashboard aggregate helpers ---------------- */

    public function countAll(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
    }

    public function sumAll(): float
    {
        return (float) $this->db->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
    }

    /** Sum of expenses recorded within the current calendar month */
    public function sumThisMonth(): float
    {
        $sql = "SELECT COALESCE(SUM(amount),0) FROM expenses
                WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())";
        return (float) $this->db->query($sql)->fetchColumn();
    }

    /** Sum of expenses recorded today */
    public function sumToday(): float
    {
        return (float) $this->db->query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date = CURDATE()"
        )->fetchColumn();
    }

    /** Today's expense rows for dashboard widget */
    public function getToday(): array
    {
        $sql = "SELECT e.*, u.full_name AS recorded_by
                FROM expenses e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.expense_date = CURDATE()
                ORDER BY e.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /** Totals grouped by category — useful for a simple breakdown chart/table */
    public function totalsByCategory(): array
    {
        $sql = "SELECT category, COALESCE(SUM(amount),0) AS total
                FROM expenses GROUP BY category ORDER BY total DESC";
        return $this->db->query($sql)->fetchAll();
    }
}
