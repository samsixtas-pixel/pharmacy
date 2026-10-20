/**
 * offline-queue.js
 * -------------------------------------------------------
 * IndexedDB-backed queue for actions taken while offline.
 * - Queues POST requests to specific URLs.
 * - Persists across reloads and reboots (IndexedDB is durable).
 * - Auto-flushes when navigator.onLine === true.
 * - Exports the queue as a downloadable .json file on every change.
 * -------------------------------------------------------
 */

const ZAIN_DB_NAME = 'zain_offline';
const ZAIN_DB_VERSION = 1;
const ZAIN_STORE = 'pending_actions';

let _db = null;

function openDB() {
  return new Promise((resolve, reject) => {
    if (_db) return resolve(_db);
    const req = indexedDB.open(ZAIN_DB_NAME, ZAIN_DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(ZAIN_STORE)) {
        const store = db.createObjectStore(ZAIN_STORE, { keyPath: 'id', autoIncrement: true });
        store.createIndex('created', 'created', { unique: false });
      }
    };
    req.onsuccess = () => { _db = req.result; resolve(_db); };
    req.onerror = () => reject(req.error);
  });
}

async function enqueue(action) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(ZAIN_STORE, 'readwrite');
    const store = tx.objectStore(ZAIN_STORE);
    const record = {
      ...action,
      created: Date.now(),
      attempts: 0,
    };
    const req = store.add(record);
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function listQueue() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(ZAIN_STORE, 'readonly');
    const store = tx.objectStore(ZAIN_STORE);
    const req = store.getAll();
    req.onsuccess = () => resolve(req.result || []);
    req.onerror = () => reject(req.error);
  });
}

async function removeFromQueue(id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(ZAIN_STORE, 'readwrite');
    const store = tx.objectStore(ZAIN_STORE);
    const req = store.delete(id);
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  });
}

async function updateAttempts(id, attempts) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(ZAIN_STORE, 'readwrite');
    const store = tx.objectStore(ZAIN_STORE);
    const getReq = store.get(id);
    getReq.onsuccess = () => {
      const rec = getReq.result;
      if (!rec) return resolve();
      rec.attempts = attempts;
      const putReq = store.put(rec);
      putReq.onsuccess = () => resolve();
      putReq.onerror = () => reject(putReq.error);
    };
    getReq.onerror = () => reject(getReq.error);
  });
}

// -------- Sync engine --------

let syncing = false;

async function flushQueue() {
  if (syncing) return;
  if (!navigator.onLine) return;
  syncing = true;

  try {
    const queue = await listQueue();
    if (queue.length === 0) return;

    updateSyncBadge(queue.length, 'syncing');

    for (const action of queue) {
      try {
        const res = await fetch(action.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: action.body,
          credentials: 'same-origin',
        });

        if (res.ok) {
          await removeFromQueue(action.id);
        } else if (res.status >= 400 && res.status < 500) {
          // Server rejected — don't retry forever. Mark as "failed" and keep for the boss to see.
          await updateAttempts(action.id, -1);
        } else {
          await updateAttempts(action.id, (action.attempts || 0) + 1);
        }
      } catch (err) {
        await updateAttempts(action.id, (action.attempts || 0) + 1);
      }
    }

    const remaining = await listQueue();
    updateSyncBadge(remaining.length, remaining.length === 0 ? 'synced' : 'pending');
  } finally {
    syncing = false;
  }
}

// -------- Downloadable log file --------

let _downloadTimer = null;

async function exportQueueAsDownload() {
  // Debounce — avoid hammering the disk
  if (_downloadTimer) clearTimeout(_downloadTimer);
  _downloadTimer = setTimeout(async () => {
    try {
      const queue = await listQueue();
      const blob = new Blob(
        [JSON.stringify({
          exported_at: new Date().toISOString(),
          total_pending: queue.length,
          actions: queue,
        }, null, 2)],
        { type: 'application/json' }
      );
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'mpango-offline-queue.json';
      document.body.appendChild(a);
      a.click();
      setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 500);
    } catch (e) {
      console.warn('[offline-queue] export failed', e);
    }
  }, 800);
}

// -------- Badge / status --------

function updateSyncBadge(count, state = 'pending') {
  let el = document.getElementById('offlineBadge');
  if (!el) {
    el = document.createElement('div');
    el.id = 'offlineBadge';
    el.className = 'offline-badge';
    document.body.appendChild(el);
  }
  if (count === 0) {
    el.classList.remove('show');
    el.textContent = '';
    return;
  }
  el.classList.add('show');
  if (state === 'syncing') {
    el.innerHTML = `<i class="bi bi-arrow-repeat"></i> Syncing ${count}…`;
    el.classList.add('syncing');
    el.classList.remove('pending', 'error');
  } else {
    el.innerHTML = `<i class="bi bi-cloud-upload"></i> ${count} pending (offline)`;
    el.classList.remove('syncing');
    el.classList.add('pending');
  }
}

// -------- Public API used by pages --------

window.OfflineQueue = {
  enqueue,
  listQueue,
  flushQueue,
  exportQueueAsDownload,
  updateSyncBadge,
  async count() { return (await listQueue()).length; },
};

// -------- Auto-start --------

window.addEventListener('online', () => { flushQueue(); });
window.addEventListener('offline', () => { updateSyncBadge(0, 'offline'); });

document.addEventListener('DOMContentLoaded', async () => {
  const n = await window.OfflineQueue.count();
  updateSyncBadge(n, 'pending');
  if (navigator.onLine) flushQueue();
  // Re-flush every 30 seconds as a safety net
  setInterval(flushQueue, 30000);
});