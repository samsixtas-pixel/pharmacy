/**
 * app.js — Zain Herbal Products IMS v4
 */
document.addEventListener('DOMContentLoaded', function () {

  const sidebar  = document.getElementById('sidebar');
  const toggle   = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  const netEl = document.getElementById('networkStatus');

  function setNet() {
    if (!netEl) return;
    netEl.className = 'network-status ' + (navigator.onLine ? 'online' : 'offline');
  }
  setNet();
    window.addEventListener('online', setNet);
    window.addEventListener('offline', setNet);

  function openSidebar() {
    sidebar && sidebar.classList.add('show');
    backdrop && backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar && sidebar.classList.remove('show');
    backdrop && backdrop.classList.remove('show');
    document.body.style.overflow = '';
  }

  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;

  // Show a subtle install banner
  const b = document.createElement('div');
  b.className = 'pwa-install-banner';
  b.innerHTML = `
    <span>📱 Install this app on your phone for faster access.</span>
    <button class="btn btn-sm btn-light ms-3" id="pwaInstall">Install</button>
    <button class="btn btn-sm btn-outline-light ms-2" id="pwaDismiss">Later</button>
  `;
  document.body.appendChild(b);

  document.getElementById('pwaInstall').onclick = async () => {
    b.remove();
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
  };
  document.getElementById('pwaDismiss').onclick = () => b.remove();
  });

  if (toggle) toggle.addEventListener('click', function () {
    sidebar && sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
  });

  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  // Close on nav link tap (mobile)
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        if (window.innerWidth <= 767) closeSidebar();
      });
    });
  }

  window.addEventListener('resize', function () {
    if (window.innerWidth > 767) closeSidebar();
  });

  // Delete confirm
  document.querySelectorAll('.btn-delete').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var href = el.getAttribute('href') || el.dataset.href;
      var name = el.dataset.name || 'this record';
      Swal.fire({
        title: 'Delete?',
        html: 'This will permanently delete <strong>' + name + '</strong>.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC3545',
        cancelButtonColor: '#5C7068',
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
      }).then(function (r) {
        if (r.isConfirmed) window.location.href = href;
      });
    });
  });
});

// Legacy table filter helper
function filterTable(inputId, tableId) {
  var input = document.getElementById(inputId);
  var table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('keyup', function () {
    var term = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(function (row) {
      row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  });
}
