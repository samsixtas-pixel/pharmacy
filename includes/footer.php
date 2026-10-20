    </main>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Offline queue (loaded first so pages can use it) -->
<script src="<?= BASE_URL ?>assets/js/offline-queue.js"></script>
<!-- App JS -->
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

<script>
// Register Service Worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('<?= BASE_URL ?>sw.js', { scope: '<?= BASE_URL ?>' })
      .then((reg) => console.log('[PWA] Service Worker registered'))
      .catch((err) => console.warn('[PWA] SW failed', err));
  });
}
</script>

<?php if (isset($_GET['success'])): ?>
<script>
Swal.fire({ icon: 'success', title: 'Success', text: <?= json_encode($_GET['success']) ?>, timer: 2200, showConfirmButton: false });
</script>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<script>
Swal.fire({ icon: 'error', title: 'Error', text: <?= json_encode($_GET['error']) ?> });
</script>
<?php endif; ?>

<script>
// ============================================
// AUTO-INJECT CSRF TOKEN INTO ALL FORMS
// ============================================
(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token) return;

  // Inject into any form missing it
  document.querySelectorAll('form').forEach(form => {
    if (form.method.toLowerCase() === 'post' &&
        !form.querySelector('input[name="csrf_token"]')) {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'csrf_token';
      inp.value = token;
      form.appendChild(inp);
    }
  });

  // For fetch() calls — attach to any non-GET
  const origFetch = window.fetch;
  window.fetch = function (url, options) {
    options = options || {};
    if (options.method && options.method.toUpperCase() !== 'GET') {
      options.headers = new Headers(options.headers || {});
      if (!options.headers.has('X-CSRF-Token')) {
        options.headers.set('X-CSRF-Token', token);
      }
    }
    return origFetch.call(this, url, options);
  };
})();
</script>

</body>
</html>
