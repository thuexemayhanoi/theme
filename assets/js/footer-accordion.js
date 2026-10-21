/* Responsive native <details>: without JavaScript, all footer links remain visible. */
(function () {
  'use strict';
  var footer = document.getElementById('site-footer');
  if (!footer || !window.matchMedia) { return; }
  var items = Array.prototype.slice.call(footer.querySelectorAll('.nt-footer__disclosure'));
  if (!items.length) { return; }
  var desktop = window.matchMedia('(min-width: 1024px)');

  function sync() {
    var wide = desktop.matches;
    items.forEach(function (details) {
      details.open = wide;
      var summary = details.querySelector('summary');
      if (summary) { summary.tabIndex = wide ? -1 : 0; }
    });
  }
  sync();
  if (desktop.addEventListener) { desktop.addEventListener('change', sync); }
  else if (desktop.addListener) { desktop.addListener(sync); }

  // No desktop visitor should be able to accidentally hide important SEO links.
  items.forEach(function (details) {
    details.addEventListener('toggle', function () {
      if (desktop.matches && !details.open) { details.open = true; }
    });
  });
}());
