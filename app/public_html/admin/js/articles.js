/*
 * Admin Articles page (FR-3.3): live search, re-flag and delete without reloading the page.
 * Every change sends the CSRF token from the page's <meta name="csrf-token">.
 */
(function () {
  'use strict';

  var state = { q: '', page: 1 };
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var rows = document.getElementById('articleRows');
  var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
  var deletingId = null;
  var searchTimer = null;

  function esc(text) {
    return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function badge(a) {
    if (a.status === 'Real' || a.status === 'Misinformation') {
      var pct = a.score === null ? '' : ' &ndash; ' + a.score + '%';
      return '<span class="badge ' + (a.status === 'Real' ? 'bg-success' : 'bg-danger') + '">' + esc(a.status) + pct + '</span>';
    }
    // A Pending article with a score is waiting for the web-search second opinion (decision 32).
    var web = a.score === null ? '' : ' &ndash; web check (first AI: ' + a.score + '%)';
    return '<span class="badge bg-warning text-dark">Pending Verification' + web + '</span>';
  }

  function message(text, kind) {
    var box = document.getElementById('articleMessage');
    box.innerHTML = '<div class="alert alert-' + kind + '" role="alert">' + esc(text) + '</div>';
    clearTimeout(message.timer);
    message.timer = setTimeout(function () { box.innerHTML = ''; }, 4000);
  }

  // The admin session has ended (30 idle minutes): go to the login page.
  function checkSession(response) {
    if (response.status === 401) {
      location.href = 'login.php?expired=1';
      throw new Error('session ended');
    }
    return response.json();
  }

  function pager(page, pages) {
    var html = '<ul class="pagination justify-content-end mb-0">';
    html += '<li class="page-item' + (page === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-goto-page="' + (page - 1) + '">Previous</a></li>';
    var first = Math.max(1, Math.min(page - 2, pages - 4)), last = Math.min(pages, first + 4);
    for (var p = first; p <= last; p++) {
      html += '<li class="page-item' + (p === page ? ' active' : '') + '"><a class="page-link" href="#" data-goto-page="' + p + '">' + p + '</a></li>';
    }
    html += '<li class="page-item' + (page === pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-goto-page="' + (page + 1) + '">Next</a></li>';
    return html + '</ul>';
  }

  function load() {
    fetch('api/articles.php?q=' + encodeURIComponent(state.q) + '&page=' + state.page, { headers: { 'X-Requested-With': 'fetch' } })
      .then(checkSession)
      .then(function (data) {
        state.page = data.page;
        document.getElementById('articleCount').textContent = data.total;
        rows.innerHTML = data.articles.length ? data.articles.map(function (a) {
          var options = ['Real', 'Misinformation', 'Pending Verification'].map(function (o) {
            return '<option' + (o === a.status ? ' selected' : '') + '>' + o + '</option>';
          }).join('');
          return '<tr>' +
            '<td>' + a.id + '</td>' +
            '<td><a href="../article.php?id=' + a.id + '" target="_blank" rel="noopener">' + esc(a.title) + '</a></td>' +
            '<td>' + esc(a.source) + '</td>' +
            '<td class="text-nowrap">' + esc(a.date) + '</td>' +
            '<td>' + badge(a) + '</td>' +
            '<td class="text-end">' + a.views.toLocaleString() + '</td>' +
            '<td><select class="form-select form-select-sm" data-reflag="' + a.id + '" data-current="' + esc(a.status) + '" aria-label="Re-flag article ' + a.id + '">' + options + '</select></td>' +
            '<td><button class="btn btn-sm btn-outline-danger" type="button" data-delete="' + a.id + '" data-title="' + esc(a.title) + '"><i class="fas fa-trash"></i> Delete</button></td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No articles match your search.</td></tr>';
        document.getElementById('articlePager').innerHTML = data.pages > 1 ? pager(data.page, data.pages) : '';
      })
      .catch(function (err) {
        if (err.message !== 'session ended') {
          rows.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">The articles could not be loaded. Please reload the page.</td></tr>';
        }
      });
  }

  function send(fields) {
    var body = new URLSearchParams(fields);
    body.append('csrf_token', csrf);
    return fetch('api/article_action.php', { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' } })
      .then(checkSession)
      .then(function (data) {
        message(data.message || data.error, data.error ? 'danger' : 'success');
        load();
      })
      .catch(function (err) {
        if (err.message !== 'session ended') { message('The change could not be saved. Please try again.', 'danger'); }
      });
  }

  // Live search: wait until the admin stops typing for a moment.
  document.getElementById('articleSearch').addEventListener('input', function () {
    var value = this.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { state.q = value; state.page = 1; load(); }, 300);
  });
  document.getElementById('articleSearchForm').addEventListener('submit', function (e) {
    e.preventDefault();
    state.q = document.getElementById('articleSearch').value.trim();
    state.page = 1;
    load();
  });

  // Re-flag as soon as a new label is picked.
  document.addEventListener('change', function (e) {
    var select = e.target.closest('[data-reflag]');
    if (select && select.value !== select.dataset.current) {
      send({ action: 'reflag', id: select.dataset.reflag, status: select.value });
    }
  });

  document.addEventListener('click', function (e) {
    var del = e.target.closest('[data-delete]');
    var page = e.target.closest('[data-goto-page]');
    if (del) {
      deletingId = del.dataset.delete;
      document.getElementById('deleteId').textContent = deletingId;
      document.getElementById('deleteTitle').textContent = del.dataset.title;
      deleteModal.show();
    } else if (page) {
      e.preventDefault();
      var n = parseInt(page.dataset.gotoPage, 10);
      if (n >= 1 && !page.parentNode.classList.contains('disabled')) { state.page = n; load(); }
    }
  });
  document.getElementById('confirmDelete').addEventListener('click', function () {
    deleteModal.hide();
    send({ action: 'delete', id: deletingId });
  });

  load();
})();
