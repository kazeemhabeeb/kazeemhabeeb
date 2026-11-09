/*
 * Public news feed: loads articles from api/articles.php with Fetch (FR-2.3),
 * draws the Blog Home cards with credibility badges (FR-2.2, NFR-3.2) and alert labels (§3.1.1),
 * filters by category and pages without reloading, and quietly refreshes every 5 minutes.
 */
(function () {
  'use strict';

  var REFRESH_MS = 5 * 60 * 1000;
  var state = { page: 1, category: new URLSearchParams(location.search).get('category') || '' };
  var feed = document.getElementById('feed');

  // Escape text before putting it into HTML (the same job as e() in PHP).
  function esc(text) {
    return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function badge(a) {
    if (a.status === 'Real' || a.status === 'Misinformation') {
      var pct = a.score === null ? '' : ' &ndash; ' + a.score + '% Confidence';
      return '<span class="badge ' + (a.status === 'Real' ? 'bg-success' : 'bg-danger') + '">' + esc(a.status) + pct + '</span>';
    }
    return '<span class="badge bg-warning text-dark">Pending Verification</span>';
  }

  function card(a, featured) {
    var url = 'article.php?id=' + a.id;
    var img = a.image || 'assets/placeholder-' + (featured ? '850x350' : '700x350') + '.svg';
    var alerts = a.alerts.length ? ' <span class="small text-danger ms-1">&#9888; ' + esc(a.alerts.join(', ')) + '</span>' : '';
    var meta = [a.date, a.source, a.category].filter(Boolean).map(esc).join(' &middot; ');
    return '<div class="card mb-4">' +
      '<a href="' + url + '"><img class="card-img-top" src="' + esc(img) + '" alt="" loading="lazy" referrerpolicy="no-referrer" /></a>' +
      '<div class="card-body">' +
      '<div class="small text-muted">' + meta + '</div>' +
      '<h2 class="card-title' + (featured ? '' : ' h4') + '"><a class="text-reset text-decoration-none" href="' + url + '">' + esc(a.title) + '</a></h2>' +
      '<p class="mb-2">' + badge(a) + alerts + '</p>' +
      '<p class="card-text">' + (a.summary ? esc(a.summary) : '<span class="text-muted">No summary was provided in the feed.</span>') + '</p>' +
      '<a class="btn btn-primary" href="' + url + '">Read more &rarr;</a>' +
      '</div></div>';
  }

  function pagination(page, pages) {
    var html = '<nav aria-label="Pagination"><hr class="my-0" /><ul class="pagination justify-content-center my-4">';
    html += '<li class="page-item' + (page === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-goto-page="' + (page - 1) + '">Newer</a></li>';
    // Show at most 5 numbered links around the current page.
    var first = Math.max(1, Math.min(page - 2, pages - 4)), last = Math.min(pages, first + 4);
    for (var p = first; p <= last; p++) {
      html += '<li class="page-item' + (p === page ? ' active' : '') + '"><a class="page-link" href="#" data-goto-page="' + p + '">' + p + '</a></li>';
    }
    html += '<li class="page-item' + (page === pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-goto-page="' + (page + 1) + '">Older</a></li>';
    return html + '</ul></nav>';
  }

  function categoryLink(c, extraClass) {
    var href = 'index.php' + (c ? '?category=' + encodeURIComponent(c) : '');
    return '<a href="' + href + '" data-cat="' + esc(c) + '"' + extraClass + '>' + (c ? esc(c) : 'All') + '</a>';
  }

  function drawCategories(data) {
    var nav = '<li class="nav-item"><a class="nav-link' + (state.category === '' ? ' active' : '') + '" href="index.php" data-cat="">Home</a></li>';
    data.nav.forEach(function (c) {
      nav += '<li class="nav-item">' + categoryLink(c, ' class="nav-link' + (state.category === c ? ' active' : '') + '"') + '</li>';
    });
    document.getElementById('navCategories').innerHTML = nav;

    var list = [''].concat(data.categories), half = Math.ceil(list.length / 2), cols = '';
    [list.slice(0, half), list.slice(half)].forEach(function (column) {
      cols += '<div class="col-sm-6"><ul class="list-unstyled mb-0">' + column.map(function (c) {
        return '<li>' + categoryLink(c, state.category === c ? ' class="fw-bold text-dark"' : '') + '</li>';
      }).join('') + '</ul></div>';
    });
    document.getElementById('categoryWidget').innerHTML = cols;
  }

  function draw(data) {
    state.page = data.page;
    document.getElementById('feedHeading').textContent = state.category || 'Latest News';
    drawCategories(data);
    if (!data.articles.length) {
      feed.innerHTML = '<div class="alert alert-info">No articles found in this category yet.</div>';
      return;
    }
    // Blog Home layout: one featured card, then two columns.
    var html = card(data.articles[0], true), left = [], right = [];
    data.articles.slice(1).forEach(function (a, i) { (i % 2 ? right : left).push(card(a, false)); });
    html += '<div class="row"><div class="col-lg-6">' + left.join('') + '</div><div class="col-lg-6">' + right.join('') + '</div></div>';
    feed.innerHTML = html + pagination(data.page, data.pages);
  }

  // Load one page by AJAX. "quiet" is used by the 5-minute refresh: no spinner, and errors are ignored.
  function load(quiet) {
    if (!quiet) {
      feed.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading articles&hellip;</div>';
    }
    var url = 'api/articles.php?page=' + state.page + (state.category ? '&category=' + encodeURIComponent(state.category) : '');
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
      .then(draw)
      .catch(function () {
        if (!quiet) {
          feed.innerHTML = '<div class="alert alert-warning">News could not be loaded right now. Please try again in a few minutes.</div>';
        }
      });
  }

  // Category and page links work without reloading the page.
  document.addEventListener('click', function (e) {
    var cat = e.target.closest('[data-cat]');
    var page = e.target.closest('[data-goto-page]');
    if (cat) {
      e.preventDefault();
      state.category = cat.getAttribute('data-cat');
      state.page = 1;
      history.replaceState(null, '', state.category ? '?category=' + encodeURIComponent(state.category) : 'index.php');
      load(false);
    } else if (page) {
      e.preventDefault();
      var n = parseInt(page.getAttribute('data-goto-page'), 10);
      if (n >= 1 && !page.parentNode.classList.contains('disabled')) {
        state.page = n;
        load(false);
        window.scrollTo(0, 0);
      }
    }
  });

  // A publisher image that fails to load is replaced by the placeholder.
  document.addEventListener('error', function (e) {
    var img = e.target;
    if (img.tagName === 'IMG' && img.src.indexOf('assets/placeholder') === -1) {
      img.src = 'assets/placeholder-700x350.svg';
    }
  }, true);

  load(false);
  setInterval(function () { load(true); }, REFRESH_MS);
})();
