<?php
/**
 * Stockable.php
 * -------------------------------------------------------
 * Interface implemented by any class that tracks an
 * increasing/decreasing quantity (Product, Box).
 *
 * Demonstrates: Abstraction + Polymorphism — both Product
 * and Box implement these methods differently/independently
 * but can be treated interchangeably wherever a Stockable
 * is expected (e.g. a generic stock report function).
 * -------------------------------------------------------
 */

interface Stockable
{
    public function increaseStock(int $id, int $quantity): bool;
    public function decreaseStock(int $id, int $quantity): bool;
}
