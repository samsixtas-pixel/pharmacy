<?php
/**
 * StockOut.php
 * Records outgoing medicine stock and decreases product stock.
 */

class StockOut extends Transaction
{
    protected function getTable(): string
    {
        return 'stock_out';
    }

    public function record(int $itemId, int $quantity, string $note, string $date, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO stock_out (product_id, quantity, reason, transaction_date, user_id)
             VALUES (:product_id, :quantity, :reason, :date, :user_id)"
        );

        $ok = $stmt->execute([
            'product_id' => $itemId,
            'quantity'   => $quantity,
            'reason'     => $note,
            'date'       => $date,
            'user_id'    => $userId,
        ]);

        if ($ok) {
            $product = new Product();
            $this->applyEffect($product, $itemId, $quantity);
        }

        return $ok;
    }

    /** Polymorphic: StockOut always decreases */
    protected function applyEffect(Stockable $item, int $itemId, int $quantity): bool
    {
        return $item->decreaseStock($itemId, $quantity);
    }

    public function getHistory(): array
    {
        return $this->history('products', 'product_name', 'product_id');
    }
}
