<?php
/**
 * BoxOut.php
 * Records outgoing box quantity and decreases box current_quantity.
 */

class BoxOut extends Transaction
{
    protected function getTable(): string
    {
        return 'box_out';
    }

    public function record(int $itemId, int $quantity, string $note, string $date, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO box_out (box_id, quantity, reason, transaction_date, user_id)
             VALUES (:box_id, :quantity, :reason, :date, :user_id)"
        );

        $ok = $stmt->execute([
            'box_id'  => $itemId,
            'quantity'=> $quantity,
            'reason'  => $note,
            'date'    => $date,
            'user_id' => $userId,
        ]);

        if ($ok) {
            $box = new Box();
            $this->applyEffect($box, $itemId, $quantity);
        }

        return $ok;
    }

    /** Polymorphic: BoxOut always decreases */
    protected function applyEffect(Stockable $item, int $itemId, int $quantity): bool
    {
        return $item->decreaseStock($itemId, $quantity);
    }

    public function getHistory(): array
    {
        return $this->history('boxes', 'box_name', 'box_id');
    }
}
