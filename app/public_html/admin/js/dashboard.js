// Dashboard: size the Real / Fake bars. Widths are set here because the site's security policy
// does not allow style="" attributes in the HTML.
document.querySelectorAll('[data-width]').forEach(function (bar) {
  bar.style.width = bar.getAttribute('data-width') + '%';
});
