<?php
/**
 * CashDeclaration.php
 * -------------------------------------------------------
 * Phase 4 — Full cash flow tracking:
 *   Opening → Sales cash + Debt payments + Mobile money − Expenses = Expected
 *   Staff declares physical cash → boss confirms receipt → auto-seal.
 * -------------------------------------------------------
 */

class CashDeclaration extends Model
{
    protected function getTable(): string { return 'cash_declarations'; }

    /**
     * Compute expected cash for a worker on a given day.
     * Expected = Opening + Cash Sales + Debt Payments − Expenses Paid
     */
    public function computeExpected(int $userId, string $date, float $openingCash = 0.0): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount_paid),0)      AS cash_from_sales,
                    COALESCE(SUM(total_amount),0)    AS revenue,
                    COALESCE(SUM(remaining_balance),0) AS new_debt,
                    COUNT(*) AS sale_count
             FROM sales WHERE user_id = :uid AND sale_date = :d"
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        $sales = $stmt->fetch();

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount),0) AS debt_payments
             FROM payments WHERE user_id = :uid AND payment_date = :d"
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        $debt = $stmt->fetch();

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount),0) AS expenses
             FROM expenses WHERE user_id = :uid AND expense_date = :d"
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        $exp = $stmt->fetch();

        $cashSales = (float)$sales['cash_from_sales'];
        $debtPay   = (float)$debt['debt_payments'];
        $expenses  = (float)$exp['expenses'];

        $expected = $openingCash + $cashSales + $debtPay - $expenses;

        return [
            'expected_cash'   => $expected,
            'opening_cash'    => $openingCash,
            'cash_from_sales' => $cashSales,
            'debt_payments'   => $debtPay,
            'expenses_paid'   => $expenses,
            'revenue'         => (float)$sales['revenue'],
            'new_debt'        => (float)$sales['new_debt'],
            'sale_count'      => (int)$sales['sale_count'],
        ];
    }

    /** Yesterday's approved declaration → likely opening cash for today */
    public function suggestedOpeningCash(int $userId, string $date): array
    {
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $prev = $this->findByUserAndDate($userId, $yesterday);
        if (!$prev) {
            return ['amount' => 0.0, 'yesterday_unapproved' => false, 'yesterday_missing' => true];
        }
        if ((int)$prev['admin_verified'] === 1) {
            return ['amount' => 0.0, 'yesterday_unapproved' => false, 'yesterday_missing' => false];
        }
        // Not approved — staff may still be holding that cash
        return [
            'amount'                => (float)$prev['declared_cash'],
            'yesterday_unapproved'  => true,
            'yesterday_missing'     => false,
        ];
    }

    /**
     * Submit or update a declaration.
     * $data = [
     *   'opening_cash' => float, 'cash_in_hand' => float, 'mobile_money' => float,
     *   'notes' => string, 'difference_reason' => string,
     *   'force_opening_only' => bool   // if true, save only the opening cash
     * ]
     */
    public function submit(int $userId, string $date, array $data): bool
    {
        $opening    = (float)($data['opening_cash']    ?? 0);
        $cashInHand = (float)($data['cash_in_hand']    ?? 0);
        $mobile     = (float)($data['mobile_money']    ?? 0);
        $notes      = trim($data['notes']             ?? '');
        $reason     = trim($data['difference_reason'] ?? '');
        $openingOnly = !empty($data['force_opening_only']);

        $calc = $this->computeExpected($userId, $date, $opening);
        $expected = $calc['expected_cash'];

        if ($openingOnly) {
            $declared = $opening;
            $diff = 0.0;
            $cashInHand = $opening;
            $mobile = 0.0;
        } else {
            $declared = $cashInHand + $mobile;
            $diff = $declared - $expected;
        }

        $existing = $this->findByUserAndDate($userId, $date);

        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE cash_declarations
                 SET opening_cash = :op, expected_cash = :exp, expenses_paid = :exppaid,
                     cash_in_hand = :cih, mobile_money = :mm, declared_cash = :dec,
                     difference = :diff, notes = :notes, difference_reason = :reason,
                     is_opening_only = :oponly, admin_verified = 0
                 WHERE id = :id"
            );
            return $stmt->execute([
                'op'        => $opening,
                'exp'       => $expected,
                'exppaid'   => $calc['expenses_paid'],
                'cih'       => $cashInHand,
                'mm'        => $mobile,
                'dec'       => $declared,
                'diff'      => $diff,
                'notes'     => $notes,
                'reason'    => $reason,
                'oponly'    => $openingOnly ? 1 : 0,
                'id'        => $existing['id'],
            ]);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO cash_declarations
                (user_id, declaration_date, opening_cash, expected_cash, expenses_paid,
                 cash_in_hand, mobile_money, declared_cash, difference, notes, difference_reason, is_opening_only)
             VALUES
                (:uid, :d, :op, :exp, :exppaid,
                 :cih, :mm, :dec, :diff, :notes, :reason, :oponly)"
        );
        return $stmt->execute([
            'uid'     => $userId,
            'd'       => $date,
            'op'      => $opening,
            'exp'     => $expected,
            'exppaid' => $calc['expenses_paid'],
            'cih'     => $cashInHand,
            'mm'      => $mobile,
            'dec'     => $declared,
            'diff'    => $diff,
            'notes'   => $notes,
            'reason'  => $reason,
            'oponly'  => $openingOnly ? 1 : 0,
        ]);
    }

    public function findByUserAndDate(int $userId, string $date): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM cash_declarations WHERE user_id = :uid AND declaration_date = :d LIMIT 1"
        );
        $stmt->execute(['uid' => $userId, 'd' => $date]);
        return $stmt->fetch();
    }

    public function search(?string $from = null, ?string $to = null, ?int $userId = null, ?int $onlyDiscrepancies = 0): array
    {
        $sql = "SELECT c.*, u.full_name AS worker_name, u.username AS worker_username,
                       v.full_name AS verifier_name
                FROM cash_declarations c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN users v ON c.verified_by = v.id
                WHERE 1=1";
        $params = [];
        if ($from)    { $sql .= " AND c.declaration_date >= :from"; $params['from'] = $from; }
        if ($to)      { $sql .= " AND c.declaration_date <= :to";   $params['to']   = $to; }
        if ($userId)  { $sql .= " AND c.user_id = :uid";            $params['uid']  = $userId; }
        if ($onlyDiscrepancies) { $sql .= " AND ABS(c.difference) > 0.009"; }
        $sql .= " ORDER BY c.declaration_date DESC, c.id DESC LIMIT 300";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Boss confirms receipt → auto-seal the day.
     * $receivedAmount = actual amount boss received (may differ if boss gave change back etc.)
     */
    public function confirmReceipt(int $id, int $adminId, float $receivedAmount, string $adminNote = ''): array
    {
        $decl = $this->find($id);
        if (!$decl) return ['ok' => false, 'error' => 'Declaration not found.'];
        if ((int)$decl['admin_verified'] === 1) return ['ok' => false, 'error' => 'Already confirmed.'];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE cash_declarations
                 SET admin_verified = 1, verified_by = :v, verified_at = NOW(),
                     received_amount = :ra, received_at = NOW(),
                     notes = CONCAT(COALESCE(notes,''), ' | Boss: ', :note)
                 WHERE id = :id"
            );
            $stmt->execute([
                'v'    => $adminId,
                'ra'   => $receivedAmount,
                'note' => $adminNote !== '' ? $adminNote : 'Received',
                'id'   => $id,
            ]);

            // Auto-seal that date
            $sealModel = new DailySeal();
            $sealResult = $sealModel->seal($decl['declaration_date'], $adminId);

            $this->db->commit();

            return [
                'ok'           => true,
                'date'         => $decl['declaration_date'],
                'tampered'     => $sealResult['tampered'] ?? false,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function verify(int $id, int $adminId): bool
    {
        // Legacy — use confirmReceipt instead
        $stmt = $this->db->prepare(
            "UPDATE cash_declarations SET admin_verified = 1, verified_by = :v, verified_at = NOW() WHERE id = :id"
        );
        return $stmt->execute(['v' => $adminId, 'id' => $id]);
    }

    public function pendingAlerts(): array
    {
        $unverified = (int)$this->db->query(
            "SELECT COUNT(*) FROM cash_declarations
             WHERE admin_verified = 0 AND declaration_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        $discrepancies = (int)$this->db->query(
            "SELECT COUNT(*) FROM cash_declarations
             WHERE ABS(difference) > 0.009
               AND declaration_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        return ['unverified' => $unverified, 'discrepancies' => $discrepancies];
    }
}