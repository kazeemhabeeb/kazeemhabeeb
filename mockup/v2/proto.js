// Prototype-only: show or hide the proposal reference tags, remembered between pages.
(function () {
  var box = document.getElementById('reqToggle');
  var hidden = false;
  try { hidden = localStorage.getItem('proto-hide-req') === '1'; } catch (e) {}
  document.body.classList.toggle('no-req', hidden);
  if (!box) return;
  box.checked = !hidden;
  box.addEventListener('change', function () {
    document.body.classList.toggle('no-req', !box.checked);
    try { localStorage.setItem('proto-hide-req', box.checked ? '0' : '1'); } catch (e) {}
  });
})();
