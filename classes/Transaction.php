<?php
/**
 * Transaction.php
 * -------------------------------------------------------
 * Abstract base class shared by StockIn, StockOut, BoxIn,
 * and BoxOut — all of which record a movement history row
 * and then call increaseStock()/decreaseStock() on the
 * related Stockable item.
 *
 * Demonstrates: Abstraction, Inheritance, Polymorphism
 * (the abstract applyEffect() method behaves differently
 * in each child: increase for "In" classes, decrease for
 * "Out" classes).
 * -------------------------------------------------------
 */

abstract class Transaction extends Model
{
    /**
     * Records the transaction row in the history table.
     */
    abstract public function record(int $itemId, int $quantity, string $note, string $date, int $userId): bool;

    /**
     * Applies the stock effect (increase or decrease) on the
     * related Stockable object. Implemented differently by
     * each concrete child class.
     */
    abstract protected function applyEffect(Stockable $item, int $itemId, int $quantity): bool;

    /**
     * Returns full history with joined item name, newest first.
     */
    public function history(string $joinTable, string $nameColumn, string $idColumn = 'product_id'): array
    {
        $table = $this->getTable();
        $sql = "SELECT t.*, j.{$nameColumn} AS item_name, u.full_name AS recorded_by
                FROM {$table} t
                JOIN {$joinTable} j ON t.{$idColumn} = j.id
                LEFT JOIN users u ON t.user_id = u.id
                ORDER BY t.transaction_date DESC, t.id DESC";
        return $this->db->query($sql)->fetchAll();
    }
}
