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
