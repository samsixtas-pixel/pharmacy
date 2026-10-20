<?php
/**
 * DailySeal.php
 * -------------------------------------------------------
 * Computes a SHA-256 hash of each table's day-slice.
 * Any backdated INSERT/UPDATE/DELETE will break the hash,
 * making tampering detectable forever.
 * -------------------------------------------------------
 */

class DailySeal extends Model
{
    protected function getTable(): string
    {
        return 'daily_seals';
    }

    /** Build the hash for a specific table + date */
    private function hashTable(string $table, string $dateField, string $date): string
    {
        $allowed = ['sales', 'payments', 'cash_declarations', 'expenses', 'audit_log'];
        if (!in_array($table, $allowed, true)) return '';

        $sql = "SELECT * FROM {$table} WHERE {$dateField} = :d ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['d' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Serialize deterministically
        $payload = '';
        foreach ($rows as $r) {
            ksort($r);                       // stable order
            $payload .= json_encode($r, JSON_UNESCAPED_UNICODE);
            $payload .= "\n";
        }
        return hash('sha256', $payload);
    }

    /** Compute all sub-hashes + master hash for a date */
    public function compute(string $date): array
    {
        $salesHash        = $this->hashTable('sales',             'sale_date',        $date);
        $paymentsHash     = $this->hashTable('payments',          'payment_date',     $date);
        $declarationsHash = $this->hashTable('cash_declarations', 'declaration_date', $date);
        $expensesHash     = $this->hashTable('expenses',          'expense_date',     $date);
        $auditHash        = $this->hashTable('audit_log',         'DATE(created_at)', $date);

        // Aggregate stats
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c, COALESCE(SUM(total_amount),0) AS t FROM sales WHERE sale_date = :d"
        );
        $stmt->execute(['d' => $date]);
        $stats = $stmt->fetch();

        $master = hash('sha256',
            $salesHash . '|' . $paymentsHash . '|' . $declarationsHash . '|' .
            $expensesHash . '|' . $auditHash
        );

        return [
            'sales_hash'        => $salesHash,
            'payments_hash'     => $paymentsHash,
            'declarations_hash' => $declarationsHash,
            'expenses_hash'     => $expensesHash,
            'audit_hash'        => $auditHash,
            'master_hash'       => $master,
            'sales_count'       => (int)$stats['c'],
            'sales_total'       => (float)$stats['t'],
        ];
    }

    /**
     * Seal a date. If already sealed, compares master_hash and reports tampering.
     * Returns ['ok' => bool, 'existing' => array|null, 'tampered' => bool]
     */
    public function seal(string $date, int $userId): array
    {
        $computed = $this->compute($date);
        $existing = $this->findByDate($date);

        if ($existing) {
            $tampered = $existing['master_hash'] !== $computed['master_hash'];
            return ['ok' => true, 'existing' => $existing, 'tampered' => $tampered];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO daily_seals
                (seal_date, sales_hash, payments_hash, declarations_hash, expenses_hash, audit_hash, master_hash,
                 sales_count, sales_total, sealed_by)
             VALUES (:d, :sh, :ph, :dh, :eh, :ah, :mh, :sc, :st, :uid)"
        );
        $stmt->execute([
            'd'   => $date,
            'sh'  => $computed['sales_hash'],
            'ph'  => $computed['payments_hash'],
            'dh'  => $computed['declarations_hash'],
            'eh'  => $computed['expenses_hash'],
            'ah'  => $computed['audit_hash'],
            'mh'  => $computed['master_hash'],
            'sc'  => $computed['sales_count'],
            'st'  => $computed['sales_total'],
            'uid' => $userId,
        ]);

        return ['ok' => true, 'existing' => null, 'tampered' => false];
    }

    public function findByDate(string $date): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM daily_seals WHERE seal_date = :d LIMIT 1");
        $stmt->execute(['d' => $date]);
        return $stmt->fetch();
    }

    /**
     * Verify a day — recompute and compare against the stored seal.
     * Returns ['status' => 'sealed|unsealed|tampered', 'details' => [...]]
     */
    public function verify(string $date): array
    {
        $existing = $this->findByDate($date);
        $computed = $this->compute($date);

        if (!$existing) {
            return ['status' => 'unsealed', 'details' => $computed, 'existing' => null];
        }
        $tampered = $existing['master_hash'] !== $computed['master_hash'];
        return [
            'status'   => $tampered ? 'tampered' : 'sealed',
            'details'  => $computed,
            'existing' => $existing,
        ];
    }

    /** All seals, newest first */
    public function allSeals(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT ds.*, u.full_name AS sealed_by_name FROM daily_seals ds
                LEFT JOIN users u ON ds.sealed_by = u.id
                WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND ds.seal_date >= :from"; $params['from'] = $from; }
        if ($to)   { $sql .= " AND ds.seal_date <= :to";   $params['to']   = $to; }
        $sql .= " ORDER BY ds.seal_date DESC LIMIT 200";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}