/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * Cybersalt Extension Manager admin behaviour: filters, selection, the
 * install/update queue (lock-out modal), changelog and version picker,
 * uninstall and enable/disable. No dependencies beyond Joomla core +
 * Bootstrap 5 (loaded by the view).
 */
(() => {
  'use strict';

  const opts = (window.Joomla && Joomla.getOptions('com_csextensionmanager')) || {};
  const items = opts.items || {};
  const t = (key, ...args) => {
    let s = (window.Joomla && Joomla.Text) ? Joomla.Text._(key, key) : key;
    args.forEach((a) => { s = s.replace(/%s|%d/, String(a)); });
    return s;
  };
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  const modal = (el) => (el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null);

  // ---------------------------------------------------------------- storage
  const store = {
    get(k, d) { try { const v = localStorage.getItem('csem.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('csem.' + k, JSON.stringify(v)); } catch (e) { /* private mode */ } },
  };

  // ---------------------------------------------------------------- HTTP
  async function post(url, data) {
    const body = new FormData();
    Object.entries(data || {}).forEach(([k, v]) => body.append(k, v));
    body.append(opts.token, '1');

    let res;
    try {
      res = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
    } catch (e) {
      return { success: false, message: t('COM_CSEXTENSIONMANAGER_JS_NETWORK_ERROR'), data: {}, status: 0 };
    }

    const text = await res.text();
    try {
      const json = JSON.parse(text);
      json.status = res.status;
      return json;
    } catch (e) {
      // A PHP error page instead of JSON: show its text. DOMParser builds an
      // inert document, so nothing in the page runs.
      const doc = new DOMParser().parseFromString(text, 'text/html');
      const plain = (doc.body ? doc.body.textContent || '' : '').replace(/\s+/g, ' ').trim().slice(0, 300);
      return { success: false, message: plain || ('HTTP ' + res.status), data: {}, status: res.status };
    }
  }

  // ---------------------------------------------------------------- busy / confirm
  // Bootstrap ignores hide() while a modal is still fading in, so remember
  // what we want and settle it once the show transition ends.
  function busy(text) {
    const el = $('#csemBusyModal');
    if (!el) { return; }
    const p = $('[data-csem-busy-text]', el);
    if (p && text) { p.textContent = text; }
    el.dataset.csemWant = 'shown';
    if (!el.dataset.csemBound) {
      el.dataset.csemBound = '1';
      el.addEventListener('shown.bs.modal', () => {
        if (el.dataset.csemWant === 'hidden') { modal(el).hide(); }
      });
    }
    modal(el).show();
  }

  function unbusy() {
    const el = $('#csemBusyModal');
    if (!el) { return; }
    el.dataset.csemWant = 'hidden';
    modal(el).hide();
  }

  function confirmBox(text) {
    const el = $('#csemConfirmModal');
    if (!el) { return Promise.resolve(window.confirm(text)); }
    $('[data-csem-confirm-text]', el).textContent = text;
    return new Promise((resolve) => {
      const ok = $('[data-csem-confirm-ok]', el);
      let answered = false;
      const onOk = () => { answered = true; modal(el).hide(); };
      const onHidden = () => {
        ok.removeEventListener('click', onOk);
        el.removeEventListener('hidden.bs.modal', onHidden);
        resolve(answered);
      };
      ok.addEventListener('click', onOk);
      el.addEventListener('hidden.bs.modal', onHidden);
      modal(el).show();
    });
  }

  document.addEventListener('click', (e) => {
    const link = e.target.closest('.csem-busy-link, #toolbar-csem-refresh a, #toolbar-csem-refresh button');
    if (link && !e.defaultPrevented && !e.ctrlKey && !e.metaKey) { busy(t('COM_CSEXTENSIONMANAGER_JS_WORKING')); }
  });

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.matches('[data-csem-confirm]')) {
      e.preventDefault();
      confirmBox(form.getAttribute('data-csem-confirm')).then((ok) => {
        if (ok) { busy(t('COM_CSEXTENSIONMANAGER_JS_WORKING')); form.submit(); }
      });
      return;
    }
    if (form.matches('.csem-busy-form')) { busy(t('COM_CSEXTENSIONMANAGER_JS_WORKING')); }
  });

  // ---------------------------------------------------------------- filters
  const root = $('#csem');
  const cards = $$('[data-csem-card]');
  const fSearch = $('#csem-search');
  const fStatus = $('#csem-filter-status');
  const fGroup = $('#csem-filter-group');
  const fTier = $('#csem-filter-tier');
  const fRelevant = $('#csem-filter-relevant');

  function applyFilters(save = true) {
    if (!root) { return; }
    const q = (fSearch?.value || '').trim().toLowerCase();
    const status = fStatus?.value || '';
    const group = fGroup?.value || '';
    const tier = fTier?.value || '';
    const relevant = !!fRelevant?.checked;
    let shown = 0;

    cards.forEach((card) => {
      const d = card.dataset;
      let ok = true;
      if (q && !d.search.includes(q)) { ok = false; }
      if (ok && status) {
        ok = status === 'installed' ? d.installed === '1' : d.status === status;
      }
      if (ok && group && d.group !== group) { ok = false; }
      if (ok && tier && d.tier !== tier) { ok = false; }
      if (ok && relevant && d.relevant === '0') { ok = false; }
      card.classList.toggle('d-none', !ok);
      if (ok) { shown++; }
    });

    $$('[data-csem-section]').forEach((section) => {
      const visible = $$('[data-csem-card]:not(.d-none)', section).length;
      section.classList.toggle('d-none', visible === 0);
      const badge = $('[data-csem-section-count]', section);
      if (badge) { badge.textContent = String(visible); }
    });

    $('[data-csem-empty]')?.classList.toggle('d-none', shown > 0);

    if (save) {
      store.set('filters', { q: fSearch?.value || '', status, group, tier, relevant });
    }
  }

  function setFilters(f) {
    if (fSearch) { fSearch.value = f.q || ''; }
    if (fStatus) { fStatus.value = f.status || ''; }
    if (fGroup) { fGroup.value = f.group || ''; }
    if (fTier) { fTier.value = f.tier || ''; }
    if (fRelevant) { fRelevant.checked = !!f.relevant; }
    applyFilters();
  }

  function filterFromHash() {
    const m = /^#filter=([a-z]+)$/.exec(window.location.hash);
    if (!m) { return false; }
    const f = { q: '', status: '', group: '', tier: '', relevant: false };
    if (m[1] === 'addon') { f.group = 'addon'; } else { f.status = m[1]; }
    setFilters(f);
    return true;
  }

  if (root) {
    [fSearch, fStatus, fGroup, fTier, fRelevant].forEach((el) => el?.addEventListener(el === fSearch ? 'input' : 'change', () => applyFilters()));
    $('[data-csem-clear-filters]')?.addEventListener('click', () => setFilters({}));
    $$('[data-csem-filter-link]').forEach((a) => a.addEventListener('click', (e) => {
      e.preventDefault();
      history.replaceState(null, '', a.getAttribute('href'));
      filterFromHash();
      $('.csem-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    window.addEventListener('hashchange', filterFromHash);

    if (!filterFromHash()) { setFilters(store.get('filters', {})); }
  }

  // ---------------------------------------------------------------- selection (shift-click ranges)
  const lastClickedByGroup = {};
  const runBtn = $('[data-csem-run-selected]');
  const selectedLabel = $('[data-csem-selected-label]');

  function selectedKeys() {
    return $$('.csem-select:checked').map((cb) => cb.value);
  }

  function updateSelection() {
    const n = selectedKeys().length;
    if (runBtn) { runBtn.disabled = n === 0; }
    if (selectedLabel) { selectedLabel.textContent = t('COM_CSEXTENSIONMANAGER_JS_SELECTED', n); }
  }

  document.addEventListener('click', (e) => {
    const cb = e.target.closest('.csem-select');
    if (!cb) { return; }
    const group = cb.dataset.group || '';
    const boxes = $$('.csem-select').filter((b) => (b.dataset.group || '') === group && !b.closest('.d-none'));
    const last = lastClickedByGroup[group];
    if (e.shiftKey && last && last !== cb && boxes.includes(last)) {
      const [a, b] = [boxes.indexOf(last), boxes.indexOf(cb)].sort((x, y) => x - y);
      boxes.slice(a, b + 1).forEach((box) => { box.checked = cb.checked; });
      window.getSelection()?.removeAllRanges();
    }
    lastClickedByGroup[group] = cb;
    updateSelection();
  });

  $$('[data-csem-select]').forEach((btn) => btn.addEventListener('click', () => {
    const mode = btn.dataset.csemSelect;
    $$('.csem-select').forEach((cb) => {
      const card = cb.closest('[data-csem-card]');
      if (mode === 'none') { cb.checked = false; }
      if (mode === 'update') { cb.checked = card.dataset.status === 'update'; }
      if (mode === 'visible') { cb.checked = !card.classList.contains('d-none'); }
    });
    updateSelection();
  }));

  runBtn?.addEventListener('click', () => {
    runQueue(selectedKeys().map((key) => ({ key, op: 'install' })));
  });

  document.addEventListener('click', (e) => {
    const all = e.target.closest('[data-csem-update-all]');
    if (!all) { return; }
    e.preventDefault();
    const keys = Object.keys(items).filter((k) => items[k].status === 'update' && items[k].entitled && items[k].compatible);
    if (!keys.length) {
      Joomla.renderMessages({ info: [esc(t('COM_CSEXTENSIONMANAGER_JS_NOTHING_TO_UPDATE'))] });
      return;
    }
    // The manager first, so the rest runs on the new code.
    keys.sort((a, b) => (items[b].self ? 1 : 0) - (items[a].self ? 1 : 0));
    runQueue(keys.map((key) => ({ key, op: 'install' })));
  });

  // ---------------------------------------------------------------- queue (lock-out modal)
  async function runQueue(entries) {
    if (!entries.length || !opts.urls) { return; }

    // Pull in missing parents (MCP for Joomla before its add-ons).
    const queue = [];
    const queued = new Set();
    entries.forEach((entry) => {
      if (entry.op === 'install') {
        (items[entry.key]?.requires || []).forEach((req) => {
          if (!queued.has(req) && !(items[req] && items[req].installed)) {
            queue.push({ key: req, op: 'install', dependency: true });
            queued.add(req);
          }
        });
      }
      if (!queued.has(entry.key) || entry.version) {
        queue.push(entry);
        queued.add(entry.key);
      }
    });

    const el = $('#csemQueueModal');
    const list = $('[data-csem-queue-list]', el);
    const bar = $('[data-csem-queue-bar]', el);
    const status = $('[data-csem-queue-status]', el);
    const reload = $('[data-csem-queue-reload]', el);

    list.innerHTML = '';
    reload.disabled = true;
    bar.style.width = '0%';
    bar.classList.remove('bg-danger', 'bg-success');
    bar.classList.add('progress-bar-striped', 'progress-bar-animated');

    const rows = queue.map((entry) => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex flex-wrap align-items-start gap-2';
      const title = items[entry.key]?.title || entry.key;
      const what = entry.op === 'uninstall' ? t('COM_CSEXTENSIONMANAGER_JS_UNINSTALLING') : t('COM_CSEXTENSIONMANAGER_JS_INSTALLING');
      li.innerHTML = '<span class="csem-q-icon icon-clock text-body-secondary" aria-hidden="true"></span>'
        + '<div class="flex-grow-1 min-w-0"><strong>' + esc(title) + '</strong>'
        + (entry.version ? ' <span class="badge bg-secondary">' + esc(entry.version) + '</span>' : '')
        + (entry.dependency ? ' <span class="badge bg-info text-dark">' + esc(t('COM_CSEXTENSIONMANAGER_JS_DEPENDENCY')) + '</span>' : '')
        + '<div class="small text-body-secondary csem-q-msg">' + esc(t('COM_CSEXTENSIONMANAGER_JS_WAITING')) + '</div></div>';
      li.dataset.what = what;
      list.appendChild(li);
      return li;
    });

    modal(el).show();

    const failed = new Set();
    let errors = 0;
    let selfUpdated = false;

    for (let i = 0; i < queue.length; i++) {
      const entry = queue[i];
      const li = rows[i];
      const icon = $('.csem-q-icon', li);
      const msg = $('.csem-q-msg', li);

      status.textContent = t('COM_CSEXTENSIONMANAGER_JS_QUEUE_RUNNING', i + 1, queue.length);

      // A parent that failed: its add-ons can't go in.
      const blockedBy = (items[entry.key]?.requires || []).find((r) => failed.has(r));
      if (blockedBy) {
        icon.className = 'csem-q-icon icon-minus-circle text-body-secondary';
        msg.textContent = (items[blockedBy]?.title || blockedBy) + ' ✗';
        failed.add(entry.key);
        errors++;
        continue;
      }

      icon.className = 'csem-q-icon spinner-border spinner-border-sm';
      msg.textContent = li.dataset.what;

      const data = { key: entry.key };
      if (entry.version) { data.version = entry.version; }
      if (entry.force) { data.force = '1'; }

      const res = await post(entry.op === 'uninstall' ? opts.urls.uninstall : opts.urls.install, data);

      if (res.success) {
        icon.className = 'csem-q-icon icon-check text-success';
        msg.textContent = res.message || '';
        if (items[entry.key]) { items[entry.key].installed = entry.op !== 'uninstall'; }
        if (res.data && res.data.self) { selfUpdated = true; }
      } else {
        icon.className = 'csem-q-icon icon-times text-danger';
        msg.innerHTML = '<span class="text-danger">' + esc(res.message || 'Error') + '</span>';
        failed.add(entry.key);
        errors++;
      }

      (res.data?.messages || []).forEach((m) => {
        if (m.type === 'error' || m.type === 'warning') {
          const d = document.createElement('div');
          d.className = 'small ' + (m.type === 'error' ? 'text-danger' : 'text-warning-emphasis');
          d.textContent = m.text;
          msg.appendChild(d);
        }
      });

      const pct = Math.round(((i + 1) / queue.length) * 100);
      bar.style.width = pct + '%';
      bar.parentElement.setAttribute('aria-valuenow', String(pct));
    }

    bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    bar.classList.add(errors ? 'bg-danger' : 'bg-success');
    status.textContent = errors ? t('COM_CSEXTENSIONMANAGER_JS_QUEUE_DONE_ERRORS', errors) : t('COM_CSEXTENSIONMANAGER_JS_QUEUE_DONE');
    reload.disabled = false;
    reload.focus();

    if (selfUpdated) {
      status.textContent += ' ' + t('COM_CSEXTENSIONMANAGER_JS_SELF_RELOAD');
      window.setTimeout(() => window.location.reload(), 2500);
    }
  }

  $('[data-csem-queue-reload]')?.addEventListener('click', () => {
    window.location.hash = '';
    window.location.reload();
  });

  // ---------------------------------------------------------------- install / uninstall buttons
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-csem-install]');
    if (!btn) { return; }
    e.preventDefault();
    const entry = { key: btn.dataset.csemInstall, op: 'install' };
    if (btn.dataset.version) { entry.version = btn.dataset.version; }
    runQueue([entry]);
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-csem-uninstall]');
    if (!btn) { return; }
    e.preventDefault();
    const key = btn.dataset.csemUninstall;
    const title = items[key]?.title || key;
    if (!(await confirmBox(t('COM_CSEXTENSIONMANAGER_JS_CONFIRM_UNINSTALL', title)))) { return; }

    busy(t('COM_CSEXTENSIONMANAGER_JS_UNINSTALLING'));
    let res = await post(opts.urls.uninstall, { key });

    if (!res.success && res.data && res.data.needs_confirm) {
      unbusy();
      const names = (res.data.dependants || []).join(', ');
      if (!(await confirmBox(t('COM_CSEXTENSIONMANAGER_JS_CONFIRM_UNINSTALL_DEPENDANTS', title, names)))) { return; }
      busy(t('COM_CSEXTENSIONMANAGER_JS_UNINSTALLING'));
      res = await post(opts.urls.uninstall, { key, force: '1' });
    }

    unbusy();
    Joomla.renderMessages(res.success ? { message: [esc(res.message)] } : { error: [esc(res.message)] });
    if (res.success) { window.setTimeout(() => window.location.reload(), 800); }
  });

  // ---------------------------------------------------------------- enable / disable
  document.addEventListener('click', async (e) => {
    const el = e.target.closest('[data-csem-toggle]');
    if (!el) { return; }

    const isSwitch = el.matches('input[type="checkbox"]');
    if (!isSwitch) { e.preventDefault(); }

    const enabled = isSwitch ? el.checked : el.dataset.enabled !== '1';
    el.disabled = true;

    const res = await post(opts.urls.toggle, { key: el.dataset.key, id: el.dataset.csemToggle, enabled: enabled ? '1' : '0' });

    el.disabled = false;

    if (!res.success) {
      if (isSwitch) { el.checked = !enabled; }
      Joomla.renderMessages({ error: [esc(res.message || t('COM_CSEXTENSIONMANAGER_JS_TOGGLE_FAILED'))] });
      return;
    }

    if (!isSwitch) {
      el.dataset.enabled = enabled ? '1' : '0';
      el.textContent = t(enabled ? 'COM_CSEXTENSIONMANAGER_BTN_DISABLE' : 'COM_CSEXTENSIONMANAGER_BTN_ENABLE');
    }

    Joomla.renderMessages({ message: [esc(res.message)] });
  });

  // ---------------------------------------------------------------- changelog / version picker
  const typeBadge = {
    security: 'bg-danger',
    fix: 'bg-warning text-dark',
    addition: 'bg-success',
    change: 'bg-secondary',
    remove: 'bg-dark',
    language: 'bg-info text-dark',
    note: 'bg-secondary',
  };

  const versionCompare = (a, b) => {
    const pa = String(a).split(/[.+-]/).map((x) => parseInt(x, 10) || 0);
    const pb = String(b).split(/[.+-]/).map((x) => parseInt(x, 10) || 0);
    for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
      const d = (pa[i] || 0) - (pb[i] || 0);
      if (d) { return d; }
    }
    return 0;
  };

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-csem-changelog]');
    if (!btn) { return; }
    e.preventDefault();

    const key = btn.dataset.csemChangelog;
    const el = $('#csemChangelogModal');
    const body = $('[data-csem-changelog-body]', el);
    $('#csemChangelogTitle').textContent = t('COM_CSEXTENSIONMANAGER_JS_CHANGELOG_TITLE', items[key]?.title || key);
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">' + esc(t('COM_CSEXTENSIONMANAGER_JS_LOADING')) + '</p></div>';
    modal(el).show();

    const res = await post(opts.urls.changelog, { key });

    if (!res.success) {
      body.innerHTML = '<div class="alert alert-warning mb-0">' + esc(res.message) + '</div>';
      return;
    }

    const d = res.data;
    if (!d.versions || !d.versions.length) {
      body.innerHTML = '<div class="alert alert-info mb-0">' + esc(t('COM_CSEXTENSIONMANAGER_JS_CHANGELOG_EMPTY')) + '</div>';
      return;
    }

    const installed = d.installed || '';
    let html = '';

    d.versions.forEach((v) => {
      const isNew = installed && versionCompare(v.version, installed) > 0;
      const isHere = installed && versionCompare(v.version, installed) === 0;
      html += '<section class="csem-cl-version border rounded p-2 mb-2' + (isNew ? ' csem-cl-new' : '') + '">'
        + '<div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h4 class="h6 mb-0">' + esc(v.version) + '</h4>'
        + (isNew ? '<span class="badge bg-success">' + esc(t('COM_CSEXTENSIONMANAGER_JS_NEW_SINCE')) + '</span>' : '')
        + (isHere ? '<span class="badge bg-primary">' + esc(t('COM_CSEXTENSIONMANAGER_JS_INSTALLED_HERE')) + '</span>' : '');

      if (d.can_install && !isHere) {
        html += '<button type="button" class="btn btn-sm btn-secondary ms-auto" data-csem-pick="' + esc(v.version) + '" data-key="' + esc(key) + '">'
          + esc(t('COM_CSEXTENSIONMANAGER_JS_INSTALL_THIS', v.version)) + '</button>';
      }

      html += '</div><ul class="small mb-0">';
      (v.items || []).forEach((it) => {
        const cls = typeBadge[it.type] || 'bg-secondary';
        html += '<li>' + (it.type && it.type !== 'change' ? '<span class="badge ' + cls + ' me-1">' + esc(it.type) + '</span>' : '') + esc(it.text) + '</li>';
      });
      html += '</ul></section>';
    });

    body.innerHTML = html;

    if (btn.dataset.pickVersion) {
      $('[data-csem-pick]', body)?.closest('section')?.scrollIntoView({ block: 'start' });
    } else {
      $('.csem-cl-new', body)?.scrollIntoView({ block: 'start' });
    }
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-csem-pick]');
    if (!btn) { return; }
    e.preventDefault();
    const key = btn.dataset.key;
    const version = btn.dataset.csemPick;
    if (!(await confirmBox(t('COM_CSEXTENSIONMANAGER_JS_CONFIRM_VERSION', items[key]?.title || key, version)))) { return; }
    modal($('#csemChangelogModal'))?.hide();
    runQueue([{ key, op: 'install', version }]);
  });

  updateSelection();
})();
