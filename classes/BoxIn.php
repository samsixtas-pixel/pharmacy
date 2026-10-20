<?php
/**
 * BoxIn.php
 * Records incoming box quantity and increases box current_quantity.
 */

class BoxIn extends Transaction
{
    protected function getTable(): string
    {
        return 'box_in';
    }

    public function record(int $itemId, int $quantity, string $note, string $date, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO box_in (box_id, quantity, remark, transaction_date, user_id)
             VALUES (:box_id, :quantity, :remark, :date, :user_id)"
        );

        $ok = $stmt->execute([
            'box_id'   => $itemId,
            'quantity' => $quantity,
            'remark'   => $note,
            'date'     => $date,
            'user_id'  => $userId,
        ]);

        if ($ok) {
            $box = new Box();
            $this->applyEffect($box, $itemId, $quantity);
        }

        return $ok;
    }

    /** Polymorphic: BoxIn always increases */
    protected function applyEffect(Stockable $item, int $itemId, int $quantity): bool
    {
        return $item->increaseStock($itemId, $quantity);
    }

    public function getHistory(): array
    {
        return $this->history('boxes', 'box_name', 'box_id');
    }
}
