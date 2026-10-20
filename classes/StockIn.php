<?php
/**
 * StockIn.php
 * Records incoming medicine stock and increases product stock.
 */

class StockIn extends Transaction
{
    protected function getTable(): string
    {
        return 'stock_in';
    }

    public function record(int $itemId, int $quantity, string $note, string $date, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO stock_in (product_id, quantity, remark, transaction_date, user_id)
             VALUES (:product_id, :quantity, :remark, :date, :user_id)"
        );

        $ok = $stmt->execute([
            'product_id' => $itemId,
            'quantity'   => $quantity,
            'remark'     => $note,
            'date'       => $date,
            'user_id'    => $userId,
        ]);

        if ($ok) {
            $product = new Product();
            $this->applyEffect($product, $itemId, $quantity);
        }

        return $ok;
    }

    /** Polymorphic: StockIn always increases */
    protected function applyEffect(Stockable $item, int $itemId, int $quantity): bool
    {
        return $item->increaseStock($itemId, $quantity);
    }

    public function getHistory(): array
    {
        return $this->history('products', 'product_name', 'product_id');
    }
}
