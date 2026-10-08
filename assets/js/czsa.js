/* CZ Saved Articles v1.0.0 */
(function () {
  'use strict';

  if (!window.CZSA) return;

  const cfg  = window.CZSA;
  const i18n = cfg.i18n;
  const rest = cfg.rest;
  const ctx  = cfg.context;

  // ── REST ──────────────────────────────────────────────────────────────────

  async function apiFetch(path, { method = 'GET', body } = {}) {
    const root = rest.root.replace(/^https?:/i, window.location.protocol);
    const url  = root + String(path).replace(/^\/+/, '');
    const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': rest.nonce };
    const res  = await fetch(url, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });
    const text = await res.text();
    if (!res.ok) throw new Error(`${method} ${url} → ${res.status}`);
    return text ? JSON.parse(text) : {};
  }

  // ── Bookmark button ───────────────────────────────────────────────────────

  let isSaved   = !!(ctx && ctx.isSaved);
  let isLoading = false;

  function getBookmarkSvg(filled) {
    return `<span class="czcr-icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24"
        fill="${filled ? 'currentColor' : 'none'}"
        stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
      </svg>
    </span>`;
  }

  function buildBookmarkBtn() {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'czcr-toolbar__btn czsa-bookmark-btn' + (isSaved ? ' is-saved' : '');
    btn.setAttribute('aria-label', isSaved ? i18n.saved : i18n.save);
    btn.dataset.czsa = 'bookmark';
    btn.innerHTML = getBookmarkSvg(isSaved);
    btn.addEventListener('click', onBookmarkClick);
    return btn;
  }

  async function onBookmarkClick() {
    if (!cfg.user.loggedIn) {
      window.location.href = cfg.loginUrl || '';
      return;
    }
    if (isLoading || !ctx || !ctx.postId) return;

    isLoading = true;
    const btn = document.querySelector('[data-czsa="bookmark"]');
    if (btn) btn.disabled = true;

    try {
      const result = await apiFetch('toggle', { method: 'POST', body: { post_id: ctx.postId } });
      isSaved = result.saved;

      // Notifies the theme (header icons) of the new saved state.
      document.dispatchEvent(new CustomEvent('czsa:saved-change', {
        detail: { postId: ctx.postId, saved: !!isSaved },
      }));

      if (btn) {
        btn.innerHTML = getBookmarkSvg(isSaved);
        btn.setAttribute('aria-label', isSaved ? i18n.saved : i18n.save);
        btn.classList.toggle('is-saved', isSaved);
      }
    } catch (e) {
      console.error('[CZSA] toggle failed', e);
    }

    isLoading = false;
    if (btn) btn.disabled = false;
  }

  function injectBookmarkButton() {
    const toolbar = document.querySelector('.czcr-toolbar');
    if (!toolbar) return;

    if (toolbar.querySelector('[data-czsa="bookmark"]')) return; // already injected

    toolbar.appendChild(buildBookmarkBtn());
  }

  // ── Saved articles page ───────────────────────────────────────────────────

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function initSavedPage() {
    const app = document.getElementById('czsa-saved-app');
    if (!app) return;

    const list = app.querySelector('.czsa-list');
    if (!list) return;

    const allItems = Array.from(list.querySelectorAll('.czsa-item'));
    let sortMode   = null; // null | 'asc' | 'desc'
    let searchQuery = '';

    function normalize(str) {
      return String(str).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function applyFiltersAndSort() {
      const q = normalize(searchQuery);

      let visible = allItems.filter(item => {
        if (!q) return true;
        return (
          normalize(item.dataset.title  || '').includes(q) ||
          normalize(item.dataset.author || '').includes(q) ||
          normalize(item.dataset.volume || '').includes(q)
        );
      });

      if (sortMode === 'asc') {
        visible.sort((a, b) => normalize(a.dataset.title || '').localeCompare(normalize(b.dataset.title || ''), 'it'));
      } else if (sortMode === 'desc') {
        visible.sort((a, b) => normalize(b.dataset.title || '').localeCompare(normalize(a.dataset.title || ''), 'it'));
      }

      allItems.forEach(item => { if (item.parentNode) item.remove(); });
      visible.forEach(item => list.appendChild(item));

      const existingNoResults = app.querySelector('.czsa-no-results');
      if (existingNoResults) existingNoResults.remove();
      if (visible.length === 0) {
        const p = document.createElement('p');
        p.className = 'czsa-empty czsa-no-results';
        p.textContent = i18n.no_results;
        list.insertAdjacentElement('afterend', p);
      }
    }

    async function doRemove(item, postId) {
      try {
        await apiFetch('toggle', { method: 'POST', body: { post_id: postId } });
        const idx = allItems.indexOf(item);
        if (idx !== -1) allItems.splice(idx, 1);
        item.remove();
        if (allItems.length === 0) {
          list.outerHTML = `<p class="czsa-empty">${escapeHtml(i18n.empty)}</p>`;
        } else {
          applyFiltersAndSort();
        }
      } catch (e) {
        console.error('[CZSA] remove failed', e);
      }
    }

    function showConfirm(item, postId) {
      if (item.querySelector('.czsa-item__confirm')) return;
      const removeBtn = item.querySelector('[data-czsa-remove]');
      if (removeBtn) removeBtn.classList.add('is-hidden');
      const confirmEl = document.createElement('div');
      confirmEl.className = 'czsa-item__confirm';
      confirmEl.innerHTML = `
        <p class="czsa-item__confirm-msg">${escapeHtml(i18n.confirm_remove)}</p>
        <div class="czsa-item__confirm-actions">
          <button type="button" class="czsa-item__confirm-yes">${escapeHtml(i18n.remove)}</button>
          <button type="button" class="czsa-item__cancel">${escapeHtml(i18n.cancel)}</button>
        </div>`;
      item.appendChild(confirmEl);
      confirmEl.querySelector('.czsa-item__confirm-yes').addEventListener('click', () => doRemove(item, postId));
      confirmEl.querySelector('.czsa-item__cancel').addEventListener('click', () => {
        confirmEl.remove();
        if (removeBtn) removeBtn.classList.remove('is-hidden');
      });
    }

    function wireItem(item) {
      const btn = item.querySelector('[data-czsa-remove]');
      if (!btn) return;
      btn.addEventListener('click', () => showConfirm(item, Number(btn.dataset.czsaRemove)));
    }

    // Search
    const searchInput = app.querySelector('.czsa-toolbar__search');
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        searchQuery = searchInput.value;
        applyFiltersAndSort();
      });
    }

    // Order select
    const orderSelect = app.querySelector('.czsa-toolbar__order');
    if (orderSelect) {
      orderSelect.addEventListener('change', () => {
        sortMode = orderSelect.value === 'date' ? null : orderSelect.value;
        applyFiltersAndSort();
      });
    }

    allItems.forEach(item => wireItem(item));
  }

  // ── Boot ──────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    if (ctx && ctx.type === 'post') {
      injectBookmarkButton();
    } else if (ctx && ctx.type === 'saved-page') {
      initSavedPage();
    }
  });

})();
