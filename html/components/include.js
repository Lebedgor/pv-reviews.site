/**
 * PVR Media Reviews — Component Loader
 * Injects shared header and footer HTML into page placeholders.
 */
(function() {
  function load(path, targetId) {
    return fetch(path)
      .then(function(r) { return r.ok ? r.text() : ''; })
      .then(function(html) {
        var el = document.getElementById(targetId);
        if (el) el.innerHTML = html;
      })
      .catch(function() {});
  }

  var base = '';
  var scripts = document.querySelectorAll('script[src]');
  for (var i = 0; i < scripts.length; i++) {
    var src = scripts[i].getAttribute('src');
    if (src.indexOf('components/include.js') !== -1) {
      base = src.replace('components/include.js', 'components/');
      break;
    }
  }

  Promise.all([
    load(base + 'header.html', 'pv-header'),
    load(base + 'footer.html', 'pv-footer')
  ]).then(function() {
    document.dispatchEvent(new Event('pv-components-loaded'));
  });
})();
