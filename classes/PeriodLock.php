<?php
/**
 * PeriodLock.php
 * -------------------------------------------------------
 * Admin locks a date range after review. Once locked, no sale,
 * payment, or expense can be created, edited, or deleted inside it.
 * -------------------------------------------------------
 */

class PeriodLock extends Model
{
    protected function getTable(): string
    {
        return 'period_locks';
    }

    /** Returns the lock row covering the given date, or false if unlocked */
    public function findForDate(string $date): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM period_locks
             WHERE :d BETWEEN lock_from AND lock_to
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['d' => $date]);
        return $stmt->fetch();
    }

    /** True if the date falls inside any lock window */
    public function isLocked(string $date): bool
    {
        return (bool) $this->findForDate($date);
    }

    /** Abort the current request if the date is locked */
    public static function ensureUnlocked(string $date, string $action = 'modify records in'): void
    {
        $lock = (new self())->findForDate($date);
        if ($lock) {
            $msg = urlencode("This period (" . $lock['lock_from'] . " to " . $lock['lock_to'] . ") is locked. You cannot {$action} it.");
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';
            $redirect = $referrer ?: BASE_URL . 'dashboard.php';
            $separator = str_contains($referrer, '?') ? '&' : '?';
            header('Location: ' . $redirect . $separator . 'error=' . $msg);
            exit;
        }
    }

    /** Create a new lock */
    public function add(string $from, string $to, string $reason, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO period_locks (lock_from, lock_to, reason, locked_by)
             VALUES (:from, :to, :reason, :uid)"
        );
        return $stmt->execute([
            'from'   => $from,
            'to'     => $to,
            'reason' => $reason,
            'uid'    => $userId,
        ]);
    }

    /** All locks, newest first */
    public function allLocks(): array
    {
        $sql = "SELECT pl.*, u.full_name AS locker_name
                FROM period_locks pl
                LEFT JOIN users u ON pl.locked_by = u.id
                ORDER BY pl.lock_from DESC, pl.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /** Remove a lock */
    public function remove(int $id): bool
    {
        return $this->delete($id);
    }
}
