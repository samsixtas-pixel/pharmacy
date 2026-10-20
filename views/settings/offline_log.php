<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$pageTitle = 'Offline Activity Log';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-cloud-slash me-1"></i> Offline Activity (on this device)</span>
    <div>
      <button class="btn btn-sm btn-outline-primary" onclick="window.OfflineQueue.flushQueue()"><i class="bi bi-arrow-repeat"></i> Force Sync Now</button>
      <button class="btn btn-sm btn-outline-secondary" onclick="window.OfflineQueue.exportQueueAsDownload()"><i class="bi bi-download"></i> Download JSON</button>
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-info small">
      This page shows pending offline actions stored on <strong>this device only</strong>. Queues live in the browser's IndexedDB.
      Each device (phone, tablet, desktop) has its own queue.
    </div>

    <div id="offlineList">Loading…</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const list = await window.OfflineQueue.listQueue();
  const el = document.getElementById('offlineList');
  if (list.length === 0) {
    el.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-check2-circle" style="font-size:2rem;color:#118057"></i><br>All synced — nothing pending.</div>';
    return;
  }
  let html = '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>#</th><th>When</th><th>Type</th><th>Details</th><th>Attempts</th></tr></thead><tbody>';
  list.forEach((a, i) => {
    const when = new Date(a.created).toLocaleString();
    const type = a.meta?.type || 'unknown';
    const detail = Object.entries(a.meta || {}).filter(([k]) => k !== 'type').map(([k, v]) => `${k}: ${v}`).join(' | ');
    const att = a.attempts === -1 ? '<span class="badge bg-danger">Rejected by server</span>' : a.attempts;
    html += `<tr><td>${i+1}</td><td>${when}</td><td><span class="badge bg-secondary">${type}</span></td><td class="small">${detail}</td><td>${att}</td></tr>`;
  });
  html += '</tbody></table></div>';
  el.innerHTML = html;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>