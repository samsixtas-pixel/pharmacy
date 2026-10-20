<?php
/**
 * Product.php
 * -------------------------------------------------------
 * Manages medicine records (Products).
 *
 * Demonstrates: Inheritance, Encapsulation, CRUD,
 * Form-validation helper methods, Search functionality.
 * -------------------------------------------------------
 */

class Product extends Model implements Stockable
{
    protected function getTable(): string
    {
        return 'products';
    }

    /**
     * Adds a new medicine/product.
     */
    public function addProduct(string $name, string $category, string $size, float $costPrice, float $price, int $stock, string $description, string $status, ?string $sku = null, ?string $barcode = null, int $packSize = 1): bool
    {
      $stmt = $this->db->prepare(
          "INSERT INTO products (product_name, sku, barcode, category, size, pack_size, cost_price, selling_price, current_stock, description, status)
           VALUES (:name, :sku, :barcode, :category, :size, :pack_size, :cost, :price, :stock, :description, :status)"
      );

      return $stmt->execute([
            'name'        => $name,
            'sku'        => $sku !== null && trim($sku) !== '' ? trim($sku) : null,
            'barcode'    => $barcode !== null && trim($barcode) !== '' ? trim($barcode) : null,
            'category'    => $category,
            'size'        => $size,
            'pack_size'   => max(1, $packSize),
            'cost'        => $costPrice,
            'price'       => $price,
            'stock'       => $stock,
            'description' => $description,
            'status'      => $status,
        ]);
    }

    /**
     * Updates an existing product.
     */
    public function updateProduct(int $id, string $name, string $category, string $size, float $costPrice, float $price, int $stock, string $description, string $status, ?string $sku = null, ?string $barcode = null, int $packSize = 1): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE products SET product_name = :name, sku = :sku, barcode = :barcode, category = :category,
            size = :size, pack_size = :pack_size, cost_price = :cost, selling_price = :price,
            current_stock = :stock, description = :description, status = :status
            WHERE id = :id"
        );

        return $stmt->execute([
            'name'        => $name,
            'sku'        => $sku !== null && trim($sku) !== '' ? trim($sku) : null,
            'barcode'    => $barcode !== null && trim($barcode) !== '' ? trim($barcode) : null,
            'category'    => $category,
            'size'        => $size,
            'pack_size'   => max(1, $packSize),
            'cost'        => $costPrice,
            'price'       => $price,
            'stock'       => $stock,
            'description' => $description,
            'status'      => $status,
            'id'          => $id,
        ]);
    }

    public function findByCode(string $code): array|false
    {
        $code = trim($code);
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE sku = :code OR barcode = :code ORDER BY status DESC, product_name ASC LIMIT 1"
        );
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    public function updateStockTo(int $productId, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE products SET current_stock = :quantity WHERE id = :id"
        );
        return $stmt->execute(['quantity' => $quantity, 'id' => $productId]);
    }

    /**
     * Searches products by medicine name, SKU, or barcode.
     */
    public function search(string $keyword): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE product_name LIKE :keyword OR sku LIKE :keyword OR barcode LIKE :keyword ORDER BY product_name ASC"
        );
        $stmt->execute(['keyword' => "%{$keyword}%"]);
        return $stmt->fetchAll();
    }

    /**
     * Returns only active products — used for Sales/Stock dropdowns.
     */
    public function getActiveProducts(): array
    {
        $stmt = $this->db->query("SELECT * FROM products WHERE status = 'active' ORDER BY product_name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Increases stock — called automatically after a Stock In transaction.
     */
    public function increaseStock(int $productId, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE products SET current_stock = current_stock + :qty WHERE id = :id"
        );
        return $stmt->execute(['qty' => $quantity, 'id' => $productId]);
    }

    /**
     * Decreases stock — called automatically after Stock Out or a Sale.
     * Guards against negative stock.
     */
    public function decreaseStock(int $productId, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE products SET current_stock = GREATEST(current_stock - :qty, 0) WHERE id = :id"
        );
        return $stmt->execute(['qty' => $quantity, 'id' => $productId]);
    }

    /**
     * Returns products at or below the low-stock threshold.
     */
    public function getLowStockProducts(int $threshold = LOW_STOCK_THRESHOLD): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM products WHERE current_stock <= :threshold AND status = 'active' ORDER BY current_stock ASC"
        );
        $stmt->execute(['threshold' => $threshold]);
        return $stmt->fetchAll();
    }

    /** Dashboard helper: total number of products */
    public function countAll(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    }

    /** Dashboard helper: sum of current_stock across all products */
    public function sumStock(): int
    {
        return (int) $this->db->query("SELECT COALESCE(SUM(current_stock),0) FROM products")->fetchColumn();
    }

    /**
    * Bulk update cost_price + selling_price for many products at once.
    * $updates = [ ['id'=>1, 'cost'=>1000, 'selling'=>1500], ... ]
    * Only touches price columns — name, stock, category, etc. untouched.
    * Returns number of rows updated.
    */
    public function bulkUpdatePrices(array $updates, int $userId): int
    {
        $count = 0;
        $historyModel = new PriceHistory();
        $stmt = $this->db->prepare(
            "UPDATE products SET cost_price = :c, selling_price = :s WHERE id = :id"
        );
        $findStmt = $this->db->prepare("SELECT cost_price, selling_price FROM products WHERE id = :id LIMIT 1");

        foreach ($updates as $u) {
            $id = (int)($u['id'] ?? 0);
            $newCost = (float)($u['cost'] ?? 0);
            $newSell = (float)($u['selling'] ?? 0);
            if ($id <= 0) continue;

            $findStmt->execute(['id' => $id]);
            $old = $findStmt->fetch();
            if (!$old) continue;

            if ((float)$old['cost_price'] === $newCost && (float)$old['selling_price'] === $newSell) continue;

            $stmt->execute(['c' => $newCost, 's' => $newSell, 'id' => $id]);
            $count++;

            // Log to history
            $historyModel->log(
                $id,
                (float)$old['cost_price'],
                $newCost,
                (float)$old['selling_price'],
                $newSell,
                $userId,
                trim($u['reason'] ?? '') !== '' ? $u['reason'] : 'Bulk price update'
            );
        }
        return $count;
    }    
}
