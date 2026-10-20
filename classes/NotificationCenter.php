<?php
/**
 * NotificationCenter.php
 * -------------------------------------------------------
 * Computes live alerts for the topbar bell + notification
 * dropdown. No database table needed — computed on demand.
 * -------------------------------------------------------
 */

class NotificationCenter
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** All alerts for the currently logged-in user */
    public function forCurrentUser(): array
    {
        $alerts = [];

        if (User::isAdmin()) {
            // 1. Unverified cash declarations (last 14 days)
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM cash_declarations
                 WHERE admin_verified = 0
                   AND declaration_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level'   => 'warning',
                    'icon'    => 'bi-cash-coin',
                    'title'   => $n . ' unverified cash declaration(s)',
                    'url'     => BASE_URL . 'views/cash/index.php',
                ];
            }

            // 2. Cash discrepancies (last 30 days)
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM cash_declarations
                 WHERE ABS(difference) > 0.009
                   AND declaration_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'icon'  => 'bi-exclamation-octagon-fill',
                    'title' => $n . ' cash discrepancy record(s)',
                    'url'   => BASE_URL . 'views/reports/suspicious.php',
                ];
            }

            // 3. Sales below cost in last 30 days
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM sales
                 WHERE selling_price < cost_price
                   AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'icon'  => 'bi-graph-down-arrow',
                    'title' => $n . ' sale(s) below cost price',
                    'url'   => BASE_URL . 'views/reports/suspicious.php',
                ];
            }

            // 4. Pending stock audit corrections
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM stock_audits WHERE corrected = 0 AND difference <> 0"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-clipboard-check',
                    'title' => $n . ' pending stock audit correction(s)',
                    'url'   => BASE_URL . 'views/reports/stock_audit_report.php?only_mismatches=1',
                ];
            }

            // 5. Customers over credit limit
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM customer_credit c
                 WHERE c.credit_limit > 0
                   AND (SELECT COALESCE(SUM(remaining_balance),0) FROM sales s
                        WHERE s.customer_name = c.customer_name
                          AND s.payment_status IN ('partial','unpaid')) > c.credit_limit"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-credit-card-2-front',
                    'title' => $n . ' customer(s) over credit limit',
                    'url'   => BASE_URL . 'views/customers/credit.php',
                ];
            }

            // 6. Low stock products
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM products
                 WHERE current_stock <= " . LOW_STOCK_THRESHOLD . " AND status = 'active'"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level' => 'info',
                    'icon'  => 'bi-exclamation-triangle-fill',
                    'title' => $n . ' product(s) low on stock',
                    'url'   => BASE_URL . 'views/products/index.php',
                ];
            }

            // 7. Debts > 60 days
            $n = (int) $this->db->query(
                "SELECT COUNT(*) FROM sales
                 WHERE payment_status IN ('partial','unpaid')
                   AND DATEDIFF(CURDATE(), sale_date) > 60"
            )->fetchColumn();
            if ($n > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'icon'  => 'bi-hourglass-split',
                    'title' => $n . ' debt(s) older than 60 days',
                    'url'   => BASE_URL . 'views/reports/receivables_aging.php',
                ];
            }
        } else {
            // Worker: only see their own pending declaration
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM cash_declarations
                 WHERE user_id = :uid AND declaration_date = CURDATE()"
            );
            $stmt->execute(['uid' => $uid]);
            $done = (int)$stmt->fetchColumn();

            if ($done === 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'icon'  => 'bi-cash-coin',
                    'title' => "You haven't declared today's cash yet",
                    'url'   => BASE_URL . 'views/cash/declare.php',
                ];
            }
        }

        return $alerts;
    }

    /** Count of "urgent" alerts (danger + warning) */
    public function urgentCount(array $alerts): int
    {
        $n = 0;
        foreach ($alerts as $a) {
            if (in_array($a['level'], ['danger', 'warning'], true)) $n++;
        }
        return $n;
    }
}