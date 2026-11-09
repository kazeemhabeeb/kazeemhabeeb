// Article page: a publisher image that fails to load is replaced by the placeholder.
(function () {
  'use strict';
  function usePlaceholder(img) {
    if (img.src.indexOf('assets/placeholder') === -1) {
      img.src = 'assets/placeholder-850x350.svg';
    }
  }
  // Images that fail from now on...
  document.addEventListener('error', function (e) {
    if (e.target.tagName === 'IMG') { usePlaceholder(e.target); }
  }, true);
  // ...and any that already failed before this script loaded.
  Array.prototype.forEach.call(document.images, function (img) {
    if (img.complete && img.naturalWidth === 0) { usePlaceholder(img); }
  });
})();
