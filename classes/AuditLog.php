<?php
/**
 * AuditLog.php
 * Records significant system actions for accountability and transparency.
 */
class AuditLog extends Model
{
    protected function getTable(): string
    {
        return 'audit_log';
    }

    public static function record(string $action, ?string $entity = null, ?int $entityId = null, string $details = ''): void
    {
        if (!isset($_SESSION)) {
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO audit_log (user_id, username, action, entity, entity_id, details, ip_address)
                 VALUES (:user_id, :username, :action, :entity, :entity_id, :details, :ip_address)"
            );
            $stmt->execute([
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? 'guest',
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Never break application workflows because a log entry failed.
        }
    }

    public function search(string $keyword = '', ?int $userId = null, string $action = '', ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT a.*, u.full_name, u.username AS owner_username
                FROM audit_log a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($keyword !== '') {
            $sql .= " AND (a.details LIKE :keyword OR a.entity LIKE :keyword OR a.action LIKE :keyword)";
            $params['keyword'] = "%{$keyword}%";
        }

        if ($userId) {
            $sql .= " AND a.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        if ($action !== '') {
            $sql .= " AND a.action = :action";
            $params['action'] = $action;
        }

        if ($from) {
            $sql .= " AND DATE(a.created_at) >= :from";
            $params['from'] = $from;
        }

        if ($to) {
            $sql .= " AND DATE(a.created_at) <= :to";
            $params['to'] = $to;
        }

        $sql .= " ORDER BY a.id DESC LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function distinctActions(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
