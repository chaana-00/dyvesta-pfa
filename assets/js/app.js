/* =====================================================================
   DYVESTA — Personal File Audit
   Shared front-end helpers
   ===================================================================== */

/* ---------------- Theme toggle ---------------- */
(function () {
  const saved = localStorage.getItem('dyvesta-theme');
  if (saved === 'light') document.documentElement.classList.add('light');
})();

function toggleTheme() {
  document.documentElement.classList.toggle('light');
  localStorage.setItem(
    'dyvesta-theme',
    document.documentElement.classList.contains('light') ? 'light' : 'dark'
  );
}

/* ---------------- Toasts ---------------- */
function toast(message, type) {
  let stack = document.getElementById('toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.id = 'toast-stack';
    document.body.appendChild(stack);
  }
  const el = document.createElement('div');
  el.className = 'toast' + (type ? ' ' + type : '');
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

/* ---------------- Modals ---------------- */
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.add('open');
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}
document.addEventListener('click', function (e) {
  if (e.target.classList && e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

/* ---------------- Quick actions menu ---------------- */
function toggleQuickMenu() {
  const menu = document.getElementById('quick-menu');
  if (menu) menu.classList.toggle('open');
}
document.addEventListener('click', function (e) {
  const menu = document.getElementById('quick-menu');
  const btn = document.getElementById('quick-menu-btn');
  if (menu && menu.classList.contains('open') && !menu.contains(e.target) && e.target !== btn) {
    menu.classList.remove('open');
  }
});

/* ---------------- Generic JSON POST helper ---------------- */
async function apiPost(url, data) {
  const form = new URLSearchParams();
  Object.keys(data || {}).forEach((k) => form.append(k, data[k]));
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form,
  });
  let json;
  try {
    json = await res.json();
  } catch (e) {
    json = { ok: false, message: 'Unexpected server response.' };
  }
  return json;
}

/* ---------------- Client-side table filter (search box) ---------------- */
function filterTable(inputEl, tableSelector) {
  const q = inputEl.value.trim().toLowerCase();
  document.querySelectorAll(tableSelector + ' tbody tr').forEach((tr) => {
    const text = tr.getAttribute('data-search') || tr.textContent;
    tr.style.display = text.toLowerCase().includes(q) ? '' : 'none';
  });
}

/* ---------------- Pill filter (status / auditor) ---------------- */
function pillFilter(pillEl, groupSelector, tableSelector, attr) {
  document.querySelectorAll(groupSelector + ' .pill').forEach((p) => p.classList.remove('active'));
  pillEl.classList.add('active');
  const val = pillEl.getAttribute('data-value');
  document.querySelectorAll(tableSelector + ' tbody tr').forEach((tr) => {
    if (val === 'all' || tr.getAttribute(attr) === val) {
      tr.style.display = '';
    } else {
      tr.style.display = 'none';
    }
  });
}

/* ---------------- File input -> auto submit label ---------------- */
function bindDropzone(dzId, inputId, formId) {
  const dz = document.getElementById(dzId);
  const input = document.getElementById(inputId);
  if (!dz || !input) return;
  dz.addEventListener('click', () => input.click());
  dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.style.borderColor = 'var(--accent)'; });
  dz.addEventListener('dragleave', () => { dz.style.borderColor = ''; });
  dz.addEventListener('drop', (e) => {
    e.preventDefault();
    dz.style.borderColor = '';
    if (e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      document.getElementById(formId).requestSubmit();
    }
  });
  input.addEventListener('change', () => {
    if (input.files.length) document.getElementById(formId).requestSubmit();
  });
}
