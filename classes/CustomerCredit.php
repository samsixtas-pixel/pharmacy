<?php
class CustomerCredit extends Model
{
    protected function getTable(): string
    {
        return 'customer_credit';
    }

    public function findByCustomer(string $customerName): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM customer_credit WHERE customer_name = :customer_name LIMIT 1");
        $stmt->execute(['customer_name' => trim($customerName)]);
        return $stmt->fetch();
    }

    public function save(string $customerName, float $creditLimit, ?string $notes = null): bool
    {
        $customerName = trim($customerName);
        if ($customerName === '') {
            return false;
        }

        $existing = $this->findByCustomer($customerName);
        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE customer_credit SET credit_limit = :limit, notes = :notes, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            return $stmt->execute([
                'limit' => $creditLimit,
                'notes' => $notes,
                'id' => (int) $existing['id'],
            ]);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO customer_credit (customer_name, credit_limit, notes) VALUES (:customer_name, :limit, :notes)"
        );
        return $stmt->execute([
            'customer_name' => $customerName,
            'limit' => $creditLimit,
            'notes' => $notes,
        ]);
    }

    public function deleteByCustomer(string $customerName): bool
    {
        $stmt = $this->db->prepare("DELETE FROM customer_credit WHERE customer_name = :customer_name");
        return $stmt->execute(['customer_name' => trim($customerName)]);
    }

    public function nextAvailableCredit(string $customerName): float
    {
        $credit = $this->findByCustomer($customerName);
        $limit = $credit ? (float) $credit['credit_limit'] : 0.0;
        $outstanding = $this->outstandingBalance($customerName);
        return max(0.0, $limit - $outstanding);
    }

    public function outstandingBalance(string $customerName): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(remaining_balance), 0) FROM sales WHERE customer_name = :customer_name AND payment_status IN ('partial', 'unpaid')"
        );
        $stmt->execute(['customer_name' => trim($customerName)]);
        return (float) $stmt->fetchColumn();
    }

    public function wouldExceedLimit(string $customerName, float $newSaleTotal): bool
    {
        $limit = $this->findByCustomer($customerName) ? (float) $this->findByCustomer($customerName)['credit_limit'] : 0.0;
        if ($limit <= 0) {
            return false;
        }
        return $this->outstandingBalance($customerName) + $newSaleTotal > $limit;
    }
}
