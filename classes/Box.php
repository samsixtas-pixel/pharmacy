<?php
/**
 * Box.php
 * -------------------------------------------------------
 * Manages the independent Box module (packaging boxes,
 * separate from medicine stock).
 *
 * Demonstrates: Inheritance (extends Model), Polymorphism
 * (implements Stockable just like Product does, but with
 * its own table/logic — same method signatures, different
 * behavior under the hood).
 * -------------------------------------------------------
 */

class Box extends Model implements Stockable
{
    protected function getTable(): string
    {
        return 'boxes';
    }

    public function addBox(string $name, int $quantity, string $description, string $status): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO boxes (box_name, current_quantity, description, status)
             VALUES (:name, :quantity, :description, :status)"
        );

        return $stmt->execute([
            'name'        => $name,
            'quantity'    => $quantity,
            'description' => $description,
            'status'      => $status,
        ]);
    }

    public function updateBox(int $id, string $name, int $quantity, string $description, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE boxes SET box_name = :name, current_quantity = :quantity,
             description = :description, status = :status WHERE id = :id"
        );

        return $stmt->execute([
            'name'        => $name,
            'quantity'    => $quantity,
            'description' => $description,
            'status'      => $status,
            'id'          => $id,
        ]);
    }

    public function search(string $keyword): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM boxes WHERE box_name LIKE :keyword ORDER BY box_name ASC"
        );
        $stmt->execute(['keyword' => "%{$keyword}%"]);
        return $stmt->fetchAll();
    }

    public function getActiveBoxes(): array
    {
        $stmt = $this->db->query("SELECT * FROM boxes WHERE status = 'active' ORDER BY box_name ASC");
        return $stmt->fetchAll();
    }

    /** Polymorphic implementation of Stockable::increaseStock */
    public function increaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE boxes SET current_quantity = current_quantity + :qty WHERE id = :id"
        );
        return $stmt->execute(['qty' => $quantity, 'id' => $id]);
    }

    /** Polymorphic implementation of Stockable::decreaseStock */
    public function decreaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE boxes SET current_quantity = GREATEST(current_quantity - :qty, 0) WHERE id = :id"
        );
        return $stmt->execute(['qty' => $quantity, 'id' => $id]);
    }

    public function countAll(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM boxes")->fetchColumn();
    }

    public function sumQuantity(): int
    {
        return (int) $this->db->query("SELECT COALESCE(SUM(current_quantity),0) FROM boxes")->fetchColumn();
    }
}
