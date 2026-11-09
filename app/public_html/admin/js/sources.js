// RSS Sources page: fill the Edit and Delete pop-ups from the row's button, then open them.
(function () {
  'use strict';
  var editModal = new bootstrap.Modal(document.getElementById('editModal'));
  var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

  document.addEventListener('click', function (e) {
    var edit = e.target.closest('[data-edit]');
    var del = e.target.closest('[data-delete]');
    if (edit) {
      document.getElementById('eId').value = edit.dataset.id;
      document.getElementById('eName').value = edit.dataset.name;
      document.getElementById('eUrl').value = edit.dataset.url;
      document.getElementById('eStatus').value = edit.dataset.status;
      editModal.show();
    } else if (del) {
      document.getElementById('dId').value = del.dataset.id;
      document.getElementById('dName').textContent = del.dataset.name;      // textContent: never run as HTML
      document.getElementById('dArticles').textContent = del.dataset.articles;
      deleteModal.show();
    }
  });

  // After a failed edit, reopen the pop-up with what was typed and the error above it.
  if (document.getElementById('eId').value !== '') {
    editModal.show();
  }
})();
