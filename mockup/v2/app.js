/*
 * TrustFeed mockup: sample data and page behaviour.
 * In the real app this data comes from MySQL through PHP endpoints (fetched with AJAX);
 * here it lives in the browser so every screen can be clicked through.
 * Articles and scores are the real results from the RapidAPI tests (docs/api-test-results.md).
 */
(function () {
  'use strict';

  var STORE_KEY = 'trustfeed-mock-v1';
  var SESSION_KEY = 'trustfeed-admin';
  var DEMO_USER = 'admin';
  var DEMO_PASS = 'admin123';

  var DEFAULT_DATA = {
    sources: [
      { id: 1, name: 'P.M. News', url: 'https://pmnewsnigeria.com/feed/', status: 'Active' },
      { id: 2, name: 'Premium Times', url: 'https://www.premiumtimesng.com/feed', status: 'Active' },
      { id: 3, name: 'Channels Television', url: 'https://www.channelstv.com/feed/', status: 'Active' },
      { id: 4, name: 'BusinessDay', url: 'https://businessday.ng/feed/', status: 'Active' },
      { id: 5, name: 'The Onion', url: 'https://theonion.com/feed/', status: 'Active' },
      { id: 6, name: 'Viral Today', url: 'https://viraltoday.example/feed', status: 'Active' },
      { id: 7, name: 'Naija Truth Daily', url: 'https://naijatruthdaily.example/feed', status: 'Active' },
      { id: 8, name: 'Lagos Wire', url: 'https://lagoswire.example/feed', status: 'Active' },
      { id: 9, name: 'Metro Pulse', url: 'https://metropulse.example/rss', status: 'Active' },
      { id: 10, name: 'Punch', url: 'https://punchng.com/feed/', status: 'Inactive' }
    ],
    articles: [
      { id: 101, source_id: 1, category: 'Business', date: '2026-10-09T22:59', score: 86, status: 'Real', views: 1204, image: true,
        title: 'Stock Market ends seven-day losing streak, adds ₦209bn',
        summary: 'The market capitalisation rose by 0.13 per cent to ₦161.260 trillion from ₦161.051 trillion recorded in the previous session, representing a gain of ₦209 billion.',
        link: 'https://pmnewsnigeria.com/2026/10/09/stock-market-ends-seven-day-losing-streak-adds-%e2%82%a6209bn/' },
      { id: 102, source_id: 2, category: 'Politics', date: '2026-10-08T15:20', score: 82, status: 'Real', views: 876, image: true,
        title: 'Macron to make state visit to Nigeria before end of October',
        summary: 'French President Emmanuel Macron will make a state visit to Nigeria before the end of October, officials said, as both countries seek closer trade and security cooperation.',
        link: 'https://www.premiumtimesng.com/' },
      { id: 103, source_id: 3, category: 'Education', date: '2026-10-08T12:05', score: 80, status: 'Real', views: 615, image: true,
        title: "FG pays eight months' SSANU salary arrears, strike averted",
        summary: 'The Federal Government has paid eight months of salary arrears owed to members of the Senior Staff Association of Nigerian Universities, and the union has called off its planned strike.',
        link: 'https://www.channelstv.com/' },
      { id: 104, source_id: 4, category: 'Business', date: '2026-10-08T09:40', score: 78, status: 'Real', views: 2650, image: true,
        title: 'Naira reaches two-year high after rate cut',
        summary: "The naira strengthened against the dollar to its highest level in two years, shaking off fears that followed the central bank's recent interest rate cut.",
        link: 'https://businessday.ng/' },
      { id: 105, source_id: 6, category: 'World', date: '2026-10-08T08:10', score: 5, status: 'Misinformation', views: 3311, image: true,
        title: 'SHOCKING: Elon Musk warns over a billion people will DIE by 2030 from water shortages!!!',
        summary: 'In a video now spreading across social media, Elon Musk says more than a billion people will die of thirst by 2030 and urges viewers to share the message before it is deleted.',
        link: 'https://viraltoday.example/musk-water' },
      { id: 110, source_id: 1, category: 'News', date: '2026-10-07T18:30', score: null, status: 'Pending Verification', views: 98, image: false,
        title: 'Abducted 20 NYSC Corps members, one other regain freedom in Imo',
        summary: 'Twenty prospective corps members and one other person abducted on the Owerri-Onitsha road in Imo State have regained their freedom after a joint operation by the police, DSS and military.',
        link: 'https://pmnewsnigeria.com/2026/10/07/abducted-20-nysc-corps-members-one-other-regain-freedom-in-imo/' },
      { id: 107, source_id: 8, category: 'Politics', date: '2026-10-07T17:45', score: 5, status: 'Misinformation', views: 1540, image: true,
        title: 'This wicked government wants every Nigerian to starve. Wake up before it is too late!',
        summary: 'Only a fool would still believe anything this heartless government says. They are deliberately destroying the economy so ordinary Nigerians will suffer, and anyone who supports them is an enemy of the people.',
        link: 'https://lagoswire.example/wicked-government' },
      { id: 108, source_id: 5, category: 'Local', date: '2026-10-07T16:00', score: 5, status: 'Misinformation', views: 980, image: true,
        title: 'Starbucks Announces New Teen-Free Hours For Nervous Adults Who Just Want To Redeem Birthday Reward',
        summary: '',
        link: 'https://theonion.com/' },
      { id: 106, source_id: 7, category: 'Health', date: '2026-10-07T13:30', score: 4, status: 'Misinformation', views: 2087, image: true,
        title: '5G masts are spreading coronavirus, scientists finally admit',
        summary: 'Scientists have finally confirmed that radiation from 5G masts weakens the immune system and spreads the coronavirus, a report shared widely on WhatsApp claims.',
        link: 'https://naijatruthdaily.example/5g-coronavirus' },
      { id: 109, source_id: 9, category: 'Health', date: '2026-10-07T11:15', score: 2, status: 'Misinformation', views: 1720, image: true,
        title: 'Doctors HATE this one fruit that cures diabetes in 7 days!!!',
        summary: 'A viral post claims that eating a single tropical fruit every morning reverses type 2 diabetes within a week, with no need for medication or diet changes.',
        link: 'https://metropulse.example/fruit-diabetes' }
    ],
    logs: [
      { t: '2026-10-09 23:00:41', type: 'Execution', msg: 'Hourly ingestion finished: 9 active feeds read, 1 new article saved, 8 duplicate URLs skipped, 1 article verified.' },
      { t: '2026-10-08 16:00:39', type: 'Execution', msg: 'Hourly ingestion finished: 9 active feeds read, 1 new article saved, 8 duplicate URLs skipped, 1 article verified.' },
      { t: '2026-10-08 09:00:12', type: 'RSS feed failed', msg: 'Could not connect to Metro Pulse (https://metropulse.example/rss). Feed skipped.' },
      { t: '2026-10-07 19:00:37', type: 'API timeout', msg: 'Fact-check API request timed out for article #110. Article saved as Pending Verification.' },
      { t: '2026-10-07 19:00:41', type: 'Execution', msg: 'Hourly ingestion finished: 9 active feeds read, 2 new articles saved, 7 duplicate URLs skipped, 1 article verified, 1 left pending.' }
    ]
  };

  // ---------- storage (falls back to memory when the browser blocks storage) ----------
  var memoryStore = {};
  function storageGet(area, key) {
    try { return window[area].getItem(key); } catch (e) { return memoryStore[area + key] || null; }
  }
  function storageSet(area, key, value) {
    try { window[area].setItem(key, value); } catch (e) { memoryStore[area + key] = value; }
  }
  function storageRemove(area, key) {
    try { window[area].removeItem(key); } catch (e) { delete memoryStore[area + key]; }
  }

  var data;
  function load() {
    var raw = storageGet('localStorage', STORE_KEY);
    try { data = raw ? JSON.parse(raw) : null; } catch (e) { data = null; }
    if (!data || !data.articles) data = JSON.parse(JSON.stringify(DEFAULT_DATA));
  }
  function save() { storageSet('localStorage', STORE_KEY, JSON.stringify(data)); }
  function resetData() { storageRemove('localStorage', STORE_KEY); load(); }

  // ---------- helpers ----------
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function $(sel) { return document.querySelector(sel); }
  function sourceName(id) {
    for (var i = 0; i < data.sources.length; i++) if (data.sources[i].id === id) return data.sources[i].name;
    return 'Unknown source';
  }
  function findArticle(id) {
    for (var i = 0; i < data.articles.length; i++) if (data.articles[i].id === id) return data.articles[i];
    return null;
  }
  function byNewest(a, b) { return a.date < b.date ? 1 : -1; }
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  function niceDate(iso) {
    var d = iso.split('T')[0].split('-');
    return MONTHS[parseInt(d[1], 10) - 1] + ' ' + parseInt(d[2], 10) + ', ' + d[0];
  }
  // FR-2.2 label + percentage, NFR-3.2 colours, Fig 3.2 50% rule
  function badge(a) {
    if (a.status === 'Pending Verification') return '<span class="badge bg-warning text-dark">Pending Verification</span>';
    var pct = a.score === null ? '' : ' &ndash; ' + a.score + '% Confidence'; // no % when the admin set it by hand
    if (a.status === 'Real') return '<span class="badge bg-success">Real' + pct + '</span>';
    return '<span class="badge bg-danger">Misinformation' + pct + '</span>';
  }
  function verdictForScore(score) { return score >= 50 ? 'Real' : 'Misinformation'; }
  function toast(msg, kind) {
    var box = document.getElementById('mockToast');
    if (!box) {
      box = document.createElement('div');
      box.id = 'mockToast';
      box.style.cssText = 'position:fixed;top:70px;right:16px;z-index:3000;max-width:340px';
      document.body.appendChild(box);
    }
    box.innerHTML = '<div class="alert alert-' + (kind || 'success') + ' shadow-sm mb-0">' + esc(msg) + '</div>';
    clearTimeout(toast.timer);
    toast.timer = setTimeout(function () { box.innerHTML = ''; }, 2600);
  }
  function modal(id) {
    var el = document.getElementById(id);
    if (window.bootstrap) return bootstrap.Modal.getOrCreateInstance(el);
    // fallback when Bootstrap's script cannot load (e.g. opened offline)
    return {
      show: function () { el.classList.add('show'); el.style.display = 'block'; },
      hide: function () { el.classList.remove('show'); el.style.display = 'none'; }
    };
  }
  function categories() {
    var seen = {}, list = [];
    data.articles.forEach(function (a) { if (a.category && !seen[a.category]) { seen[a.category] = true; list.push(a.category); } });
    return list.sort();
  }
  function articleUrl(a) { return 'article.html#a' + a.id; }

  // =====================================================================================
  // Public site (Blog Home)
  // =====================================================================================
  var feedState = { q: '', cat: '', page: 1 };
  var PAGE_SIZE = 5;

  function renderNavCategories() {
    var nav = document.getElementById('navCategories');
    if (!nav) return;
    var html = '<li class="nav-item"><a class="nav-link' + (feedState.cat === '' ? ' active' : '') + '" href="index.html" data-cat="">Home</a></li>';
    categories().slice(0, 4).forEach(function (c) {
      html += '<li class="nav-item"><a class="nav-link' + (feedState.cat === c ? ' active' : '') + '" href="index.html" data-cat="' + esc(c) + '">' + esc(c) + '</a></li>';
    });
    nav.innerHTML = html;
  }
  function renderCategoryWidget() {
    var box = document.getElementById('categoryWidget');
    if (!box) return;
    var cats = [''].concat(categories());
    var half = Math.ceil(cats.length / 2);
    function col(list) {
      return list.map(function (c) {
        return '<li><a href="index.html" data-cat="' + esc(c) + '"' + (feedState.cat === c ? ' class="fw-bold text-dark"' : '') + '>' + (c ? esc(c) : 'All') + '</a></li>';
      }).join('');
    }
    box.innerHTML = '<div class="col-sm-6"><ul class="list-unstyled mb-0">' + col(cats.slice(0, half)) + '</ul></div>' +
                    '<div class="col-sm-6"><ul class="list-unstyled mb-0">' + col(cats.slice(half)) + '</ul></div>';
  }

  function postCard(a, featured) {
    var img = a.image
      ? '<a href="' + articleUrl(a) + '"><img class="card-img-top" src="assets/placeholder-' + (featured ? '850x350' : '700x350') + '.svg" alt="Image from ' + esc(sourceName(a.source_id)) + '" /></a>'
      : '';
    return '<div class="card mb-4">' + img +
      '<div class="card-body">' +
      '<div class="small text-muted">' + niceDate(a.date) + ' &middot; ' + esc(sourceName(a.source_id)) + ' &middot; ' + esc(a.category) + '</div>' +
      '<h2 class="card-title' + (featured ? '' : ' h4') + '"><a class="text-reset text-decoration-none" href="' + articleUrl(a) + '">' + esc(a.title) + '</a></h2>' +
      '<p class="mb-2">' + badge(a) + '</p>' +
      '<p class="card-text">' + (a.summary ? esc(a.summary) : '<span class="text-muted">No summary was provided in the feed.</span>') + '</p>' +
      '<a class="btn btn-primary" href="' + articleUrl(a) + '">Read more →</a>' +
      '</div></div>';
  }

  function filteredArticles() {
    var q = feedState.q.toLowerCase();
    return data.articles.slice().sort(byNewest).filter(function (a) {
      if (feedState.cat && a.category !== feedState.cat) return false;
      if (q && (a.title + ' ' + a.summary + ' ' + sourceName(a.source_id)).toLowerCase().indexOf(q) === -1) return false;
      return true;
    });
  }

  function renderFeed() {
    var feed = document.getElementById('feed');
    if (!feed) return;
    // mimic the AJAX request to api/articles.php (FR-2.3)
    feed.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading articles…</div>';
    setTimeout(function () {
      var list = filteredArticles();
      var pages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
      if (feedState.page > pages) feedState.page = pages;
      var pageItems = list.slice((feedState.page - 1) * PAGE_SIZE, feedState.page * PAGE_SIZE);
      var heading = document.getElementById('feedHeading');
      if (heading) {
        heading.textContent = feedState.q ? 'Search results for "' + feedState.q + '"' : (feedState.cat || 'Latest News');
      }
      if (!pageItems.length) {
        feed.innerHTML = '<div class="alert alert-info">No articles found. Try a different search word or choose another category.</div>';
      } else {
        var html = postCard(pageItems[0], true);
        var rest = pageItems.slice(1), left = [], right = [];
        rest.forEach(function (a, i) { (i % 2 ? right : left).push(postCard(a, false)); });
        html += '<div class="row"><div class="col-lg-6">' + left.join('') + '</div><div class="col-lg-6">' + right.join('') + '</div></div>';
        html += pagination(feedState.page, pages, 'Newer', 'Older');
        feed.innerHTML = html;
      }
      renderNavCategories();
      renderCategoryWidget();
    }, 250);
  }

  function pagination(page, pages, prevLabel, nextLabel) {
    var html = '<nav aria-label="Pagination"><hr class="my-0" /><ul class="pagination justify-content-center my-4">';
    html += '<li class="page-item' + (page === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-goto-page="' + (page - 1) + '">' + prevLabel + '</a></li>';
    for (var p = 1; p <= pages; p++) {
      html += '<li class="page-item' + (p === page ? ' active' : '') + '"><a class="page-link" href="#" data-goto-page="' + p + '">' + p + '</a></li>';
    }
    html += '<li class="page-item' + (page === pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-goto-page="' + (page + 1) + '">' + nextLabel + '</a></li>';
    return html + '</ul></nav>';
  }

  function initPublicWidgets(onChange) {
    document.addEventListener('click', function (e) {
      var cat = e.target.closest('[data-cat]');
      if (cat) {
        if (!document.getElementById('feed')) {
          try { sessionStorage.setItem('tf-cat', cat.getAttribute('data-cat')); } catch (err) {}
          return; // follow the link to index.html
        }
        e.preventDefault();
        feedState.cat = cat.getAttribute('data-cat');
        feedState.q = '';
        feedState.page = 1;
        var q = document.getElementById('searchInput');
        if (q) q.value = '';
        onChange();
      }
    });
    var form = document.getElementById('searchForm');
    if (form) {
      form.addEventListener('submit', function (e) {
        var value = document.getElementById('searchInput').value.trim();
        if (!document.getElementById('feed')) {
          try { sessionStorage.setItem('tf-q', value); } catch (err) {}
          return; // go to index.html with the search
        }
        e.preventDefault();
        feedState.q = value;
        feedState.cat = '';
        feedState.page = 1;
        onChange();
      });
    }
  }

  function initFeedPage() {
    try {
      var c = sessionStorage.getItem('tf-cat'), q = sessionStorage.getItem('tf-q');
      if (c !== null) { feedState.cat = c; sessionStorage.removeItem('tf-cat'); }
      if (q !== null) { feedState.q = q; sessionStorage.removeItem('tf-q'); var input = document.getElementById('searchInput'); if (input) input.value = q; }
    } catch (err) {}
    initPublicWidgets(renderFeed);
    document.addEventListener('click', function (e) {
      var p = e.target.closest('[data-goto-page]');
      if (p && document.getElementById('feed')) {
        e.preventDefault();
        var n = parseInt(p.getAttribute('data-goto-page'), 10);
        if (n >= 1) { feedState.page = n; renderFeed(); window.scrollTo(0, 0); }
      }
    });
    renderFeed();
  }

  function initArticlePage() {
    initPublicWidgets(function () {});
    renderNavCategories();
    renderCategoryWidget();
    function show() {
      var id = parseInt((location.hash || '').replace(/[^0-9]/g, ''), 10);
      var a = findArticle(id) || data.articles.slice().sort(byNewest)[0];
      a.views += 1; // FR-2.4: api/view.php adds 1 to view_count
      save();
      var alertBox, pct = a.score === null ? '' : ' &ndash; ' + a.score + '% Confidence';
      if (a.status === 'Pending Verification') {
        alertBox = '<div class="alert alert-warning"><strong>Pending Verification.</strong> This article has not been verified yet.</div>';
      } else if (a.status === 'Real') {
        alertBox = '<div class="alert alert-success"><strong>Real' + pct + '</strong></div>';
      } else {
        alertBox = '<div class="alert alert-danger"><strong>Misinformation' + pct + '</strong></div>';
      }
      document.title = a.title + ' - TrustFeed';
      $('#articleBody').innerHTML =
        '<header class="mb-4">' +
        '<h1 class="fw-bolder mb-1">' + esc(a.title) + '</h1>' +
        '<div class="text-muted fst-italic mb-2">Posted on ' + niceDate(a.date) + ' by ' + esc(sourceName(a.source_id)) + '</div>' +
        '<a class="badge bg-secondary text-decoration-none link-light" href="index.html" data-cat="' + esc(a.category) + '">' + esc(a.category) + '</a>' +
        '</header>' +
        (a.image ? '<figure class="mb-4"><img class="img-fluid rounded" src="assets/placeholder-850x350.svg" alt="Image from ' + esc(sourceName(a.source_id)) + '" /></figure>' : '') +
        alertBox +
        '<section class="mb-5">' +
        '<p class="fs-5 mb-4">' + (a.summary ? esc(a.summary) : '<span class="text-muted">No summary was provided in the feed.</span>') + '</p>' +
        '<a class="btn btn-primary" href="' + esc(a.link) + '" target="_blank" rel="noopener">Read the full story on ' + esc(sourceName(a.source_id)) + '</a>' +
        '</section>';
      window.scrollTo(0, 0);
    }
    window.addEventListener('hashchange', show);
    show();
  }

  // =====================================================================================
  // Admin panel (SB Admin)
  // =====================================================================================
  function isLoggedIn() { return storageGet('sessionStorage', SESSION_KEY) === '1'; }
  function requireLogin() {
    if (!isLoggedIn()) { location.href = 'login.html'; return false; }
    return true;
  }
  function wireLogout() {
    var links = document.querySelectorAll('[data-logout]');
    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('click', function () { storageRemove('sessionStorage', SESSION_KEY); });
    }
  }

  function initLogin() {
    var form = document.getElementById('loginForm');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var u = document.getElementById('inputUsername').value.trim();
      var p = document.getElementById('inputPassword').value;
      var err = document.getElementById('loginError');
      if (u === DEMO_USER && p === DEMO_PASS) {
        storageSet('sessionStorage', SESSION_KEY, '1');
        location.href = 'dashboard.html';
      } else {
        err.classList.remove('d-none');
      }
    });
  }

  function initDashboard() {
    if (!requireLogin()) return;
    wireLogout();
    var total = data.articles.length, real = 0, fake = 0, pending = 0;
    data.articles.forEach(function (a) {
      if (a.status === 'Real') real++; else if (a.status === 'Misinformation') fake++; else pending++;
    });
    $('#statTotal').textContent = total;
    $('#statReal').textContent = real;
    $('#statFake').textContent = fake;
    var rf = real + fake;
    var pr = rf ? Math.round(real / rf * 1000) / 10 : 0, pf = rf ? Math.round(fake / rf * 1000) / 10 : 0;
    $('#ratioBar').innerHTML =
      '<div class="progress-bar bg-success" style="width:' + pr + '%">Real ' + pr + '%</div>' +
      '<div class="progress-bar bg-danger" style="width:' + pf + '%">Fake ' + pf + '%</div>';
    $('#ratioText').innerHTML = 'Real: <strong>' + real + '</strong> &nbsp; Fake: <strong>' + fake + '</strong> &nbsp; Pending: <strong>' + pending + '</strong>' +
      (fake ? ' &nbsp; Ratio (fake : real) = <strong>1 : ' + (real / fake).toFixed(1) + '</strong>' : '');
    $('#feedStatusRows').innerHTML = data.sources.map(function (s) {
      return '<tr><td>' + esc(s.name) + '</td><td><span class="badge ' + (s.status === 'Active' ? 'bg-success' : 'bg-secondary') + '">' + s.status + '</span></td></tr>';
    }).join('');
    $('#viewRows').innerHTML = data.articles.slice().sort(function (a, b) { return b.views - a.views; }).map(function (a) {
      return '<tr><td>' + esc(a.title) + '</td><td class="text-end">' + a.views.toLocaleString() + '</td></tr>';
    }).join('');
  }

  // ---------- RSS sources ----------
  var editingSource = null, deletingSource = null;
  function renderSources() {
    $('#sourceRows').innerHTML = data.sources.map(function (s) {
      return '<tr>' +
        '<td>' + s.id + '</td><td>' + esc(s.name) + '</td><td class="text-break">' + esc(s.url) + '</td>' +
        '<td><span class="badge ' + (s.status === 'Active' ? 'bg-success' : 'bg-secondary') + '">' + s.status + '</span></td>' +
        '<td class="text-nowrap">' +
        '<button class="btn btn-sm btn-outline-primary" data-edit="' + s.id + '"><i class="fas fa-edit"></i> Edit</button> ' +
        '<button class="btn btn-sm btn-outline-secondary" data-toggle-src="' + s.id + '">' + (s.status === 'Active' ? 'Disable' : 'Enable') + '</button> ' +
        '<button class="btn btn-sm btn-outline-danger" data-del-src="' + s.id + '"><i class="fas fa-trash"></i> Delete</button>' +
        '</td></tr>';
    }).join('');
    $('#activeCount').textContent = data.sources.filter(function (s) { return s.status === 'Active'; }).length;
  }
  function validSource(name, url, ignoreId) {
    if (!name || !url) return 'Enter a website name and an RSS feed URL.';
    if (!/^https?:\/\/\S+$/i.test(url)) return 'The RSS feed URL must start with http:// or https://.';
    for (var i = 0; i < data.sources.length; i++) {
      if (data.sources[i].url.toLowerCase() === url.toLowerCase() && data.sources[i].id !== ignoreId) return 'That RSS feed URL is already in the list.';
    }
    return '';
  }
  function initSources() {
    if (!requireLogin()) return;
    wireLogout();
    renderSources();
    $('#addSrc').addEventListener('submit', function (e) {
      e.preventDefault();
      var name = $('#sName').value.trim(), url = $('#sUrl').value.trim();
      var problem = validSource(name, url, null), box = $('#addError');
      if (problem) { box.textContent = problem; box.classList.remove('d-none'); return; }
      box.classList.add('d-none');
      var id = data.sources.reduce(function (m, s) { return Math.max(m, s.id); }, 0) + 1;
      data.sources.push({ id: id, name: name, url: url, status: $('#sStatus').value });
      save(); renderSources(); this.reset();
      toast(name + ' added.');
    });
    document.addEventListener('click', function (e) {
      var b;
      if ((b = e.target.closest('[data-toggle-src]'))) {
        var s = data.sources.filter(function (x) { return x.id === +b.getAttribute('data-toggle-src'); })[0];
        s.status = s.status === 'Active' ? 'Inactive' : 'Active';
        save(); renderSources();
        toast(s.name + (s.status === 'Active' ? ' enabled.' : ' disabled.'));
      } else if ((b = e.target.closest('[data-edit]'))) {
        editingSource = data.sources.filter(function (x) { return x.id === +b.getAttribute('data-edit'); })[0];
        $('#eName').value = editingSource.name;
        $('#eUrl').value = editingSource.url;
        $('#eStatus').value = editingSource.status;
        $('#editError').classList.add('d-none');
        modal('editModal').show();
      } else if ((b = e.target.closest('[data-del-src]'))) {
        deletingSource = data.sources.filter(function (x) { return x.id === +b.getAttribute('data-del-src'); })[0];
        var n = data.articles.filter(function (a) { return a.source_id === deletingSource.id; }).length;
        $('#deleteText').innerHTML = 'Delete <strong>' + esc(deletingSource.name) + '</strong>? This will also delete its articles (' + n + ').';
        modal('deleteModal').show();
      }
    });
    $('#saveEdit').addEventListener('click', function () {
      var name = $('#eName').value.trim(), url = $('#eUrl').value.trim();
      var problem = validSource(name, url, editingSource.id), box = $('#editError');
      if (problem) { box.textContent = problem; box.classList.remove('d-none'); return; }
      editingSource.name = name; editingSource.url = url; editingSource.status = $('#eStatus').value;
      save(); renderSources(); modal('editModal').hide();
      toast(name + ' updated.');
    });
    $('#confirmDelete').addEventListener('click', function () {
      var id = deletingSource.id, name = deletingSource.name;
      data.sources = data.sources.filter(function (s) { return s.id !== id; });
      data.articles = data.articles.filter(function (a) { return a.source_id !== id; }); // ON DELETE CASCADE
      save(); renderSources(); modal('deleteModal').hide();
      toast(name + ' and its articles were deleted.');
    });
  }

  // ---------- Articles ----------
  var articleState = { q: '', page: 1 }, deletingArticle = null, ADMIN_PAGE = 8;
  function renderAdminArticles() {
    var q = articleState.q.toLowerCase();
    var list = data.articles.slice().sort(byNewest).filter(function (a) {
      return !q || (a.title + ' ' + sourceName(a.source_id)).toLowerCase().indexOf(q) !== -1;
    });
    var pages = Math.max(1, Math.ceil(list.length / ADMIN_PAGE));
    if (articleState.page > pages) articleState.page = pages;
    var items = list.slice((articleState.page - 1) * ADMIN_PAGE, articleState.page * ADMIN_PAGE);
    $('#articleRows').innerHTML = items.length ? items.map(function (a) {
      var opts = ['Real', 'Misinformation', 'Pending Verification'].map(function (o) {
        return '<option' + (o === a.status ? ' selected' : '') + '>' + o + '</option>';
      }).join('');
      return '<tr>' +
        '<td>' + a.id + '</td><td>' + esc(a.title) + '</td><td>' + esc(sourceName(a.source_id)) + '</td>' +
        '<td class="text-nowrap">' + niceDate(a.date) + '</td><td>' + badge(a) + '</td>' +
        '<td class="text-end">' + a.views.toLocaleString() + '</td>' +
        '<td><select class="form-select form-select-sm" style="min-width:160px" data-reflag="' + a.id + '" aria-label="Re-flag article ' + a.id + '">' + opts + '</select></td>' +
        '<td><button class="btn btn-sm btn-outline-danger" data-del-art="' + a.id + '"><i class="fas fa-trash"></i> Delete</button></td>' +
        '</tr>';
    }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No articles match your search.</td></tr>';
    $('#articlePager').innerHTML = pagination(articleState.page, pages, 'Previous', 'Next').replace('justify-content-center my-4', 'justify-content-end mb-0').replace('<hr class="my-0" />', '');
    $('#articleCount').textContent = list.length;
  }
  function initArticles() {
    if (!requireLogin()) return;
    wireLogout();
    renderAdminArticles();
    $('#articleSearch').addEventListener('input', function () { articleState.q = this.value.trim(); articleState.page = 1; renderAdminArticles(); });
    $('#articleSearchForm').addEventListener('submit', function (e) { e.preventDefault(); });
    document.addEventListener('change', function (e) {
      var sel = e.target.closest('[data-reflag]');
      if (!sel) return;
      var a = findArticle(+sel.getAttribute('data-reflag'));
      a.status = sel.value;
      save(); renderAdminArticles();
      toast('Article #' + a.id + ' re-flagged as ' + a.status + '.');
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-del-art]'), p = e.target.closest('[data-goto-page]');
      if (b) {
        deletingArticle = findArticle(+b.getAttribute('data-del-art'));
        $('#deleteText').innerHTML = 'Permanently delete article #' + deletingArticle.id + ' (<em>' + esc(deletingArticle.title) + '</em>)? This cannot be undone.';
        modal('deleteModal').show();
      } else if (p) {
        e.preventDefault();
        var n = parseInt(p.getAttribute('data-goto-page'), 10);
        if (n >= 1) { articleState.page = n; renderAdminArticles(); }
      }
    });
    $('#confirmDelete').addEventListener('click', function () {
      var id = deletingArticle.id;
      data.articles = data.articles.filter(function (a) { return a.id !== id; });
      save(); renderAdminArticles(); modal('deleteModal').hide();
      toast('Article #' + id + ' deleted.');
    });
  }

  // ---------- Logs ----------
  function initLogs() {
    if (!requireLogin()) return;
    wireLogout();
    var cls = { 'Execution': 'bg-secondary', 'API timeout': 'bg-warning text-dark', 'API error': 'bg-danger', 'RSS feed failed': 'bg-danger' };
    $('#logRows').innerHTML = data.logs.slice().sort(function (a, b) { return a.t < b.t ? 1 : -1; }).map(function (l) {
      return '<tr><td class="text-nowrap">' + l.t + '</td><td><span class="badge ' + (cls[l.type] || 'bg-secondary') + '">' + esc(l.type) + '</span></td><td>' + esc(l.msg) + '</td></tr>';
    }).join('');
  }

  // ---------- start ----------
  load();
  window.TrustFeed = { resetData: resetData, verdictForScore: verdictForScore };
  var page = document.body.getAttribute('data-page');
  var start = {
    feed: initFeedPage, article: initArticlePage, login: initLogin,
    dashboard: initDashboard, sources: initSources, articles: initArticles, logs: initLogs
  }[page];
  if (start) start();
})();
