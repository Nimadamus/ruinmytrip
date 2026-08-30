// RuinMyTrip — minimal progressive enhancement (no framework).
document.addEventListener('click', function (e) {
  const t = e.target.closest('[data-confirm]');
  if (t && !confirm(t.getAttribute('data-confirm'))) e.preventDefault();
});
// Close mobile nav when a link is tapped.
document.querySelectorAll('.site-nav a').forEach(a =>
  a.addEventListener('click', () => document.body.classList.remove('nav-open')));

document.addEventListener('click', function (e) {
  const b = e.target.closest('[data-copy]');
  if (!b) return;
  e.preventDefault();
  const url = b.getAttribute('data-copy') || '';
  const prev = b.textContent;
  const done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = prev; }, 1600); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copy', url); });
  } else {
    window.prompt('Copy', url);
  }
});

// Native share where the browser has it. The button is hidden by default so a desktop browser
// without navigator.share never shows a control that would do nothing.
(function () {
  if (!navigator.share) return;
  document.querySelectorAll('.share-row .js-share-native').forEach(function (b) {
    b.hidden = false;
    b.addEventListener('click', function () {
      const row = b.closest('.share-row');
      navigator.share({
        title: row.getAttribute('data-share-text') || document.title,
        text: row.getAttribute('data-share-text') || '',
        url: row.getAttribute('data-share-url') || location.href
      }).catch(function () { /* the sharer cancelled, which is not an error */ });
    });
  });
})();
