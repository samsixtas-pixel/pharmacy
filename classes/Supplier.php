<?php
/**
 * Supplier.php — CRUD for suppliers.
 */

class Supplier extends Model
{
    protected function getTable(): string
    {
        return 'suppliers';
    }

    public function addSupplier(string $name, string $phone, string $email, string $address, string $notes, string $status): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO suppliers (supplier_name, phone, email, address, notes, status)
             VALUES (:n, :p, :e, :a, :notes, :s)"
        );
        return $stmt->execute([
            'n' => $name, 'p' => $phone, 'e' => $email,
            'a' => $address, 'notes' => $notes, 's' => $status,
        ]);
    }

    public function updateSupplier(int $id, string $name, string $phone, string $email, string $address, string $notes, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE suppliers SET supplier_name = :n, phone = :p, email = :e,
             address = :a, notes = :notes, status = :s WHERE id = :id"
        );
        return $stmt->execute([
            'n' => $name, 'p' => $phone, 'e' => $email,
            'a' => $address, 'notes' => $notes, 's' => $status, 'id' => $id,
        ]);
    }

    public function search(string $keyword = ''): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM suppliers WHERE supplier_name LIKE :kw ORDER BY supplier_name ASC"
        );
        $stmt->execute(['kw' => "%{$keyword}%"]);
        return $stmt->fetchAll();
    }

    public function getActive(): array
    {
        return $this->db->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC")->fetchAll();
    }

    /** Suppliers with total purchases and outstanding balance */
    public function allWithStats(): array
    {
        $sql = "SELECT s.*,
                  COUNT(p.id) AS purchase_count,
                  COALESCE(SUM(p.total_amount),0) AS total_purchased,
                  COALESCE(SUM(p.balance),0) AS outstanding
                FROM suppliers s
                LEFT JOIN purchases p ON p.supplier_id = s.id
                GROUP BY s.id
                ORDER BY s.supplier_name ASC";
        return $this->db->query($sql)->fetchAll();
    }
}