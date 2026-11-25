/* Home hero (views/home_v2.php, 2026-11-25): the Popular Destinations row scrolls by its arrows
 * (the row itself scrolls by touch, trackpad and keyboard with no script at all), and the round
 * search button in the home header jumps to the hero search instead of leaving the page. */
(function () {
  'use strict';
  var track = document.getElementById('hx-track');
  if (track) {
    var prev = document.querySelector('.v2-hx-car-prev');
    var next = document.querySelector('.v2-hx-car-next');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var update = function () {
      var max = track.scrollWidth - track.clientWidth;
      var scrollable = max > 4;
      prev.hidden = next.hidden = !scrollable;
      prev.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft >= max - 4;
    };
    var step = function (dir) {
      var card = track.querySelector('li');
      var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      var w = card ? card.getBoundingClientRect().width + gap : track.clientWidth * 0.8;
      var n = Math.max(1, Math.floor((track.clientWidth + gap) / w) - 1);
      track.scrollBy({ left: dir * w * n, behavior: reduce ? 'auto' : 'smooth' });
    };
    prev.addEventListener('click', function () { step(-1); });
    next.addEventListener('click', function () { step(1); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
    window.addEventListener('resize', update);
    update();
  }
  var jump = document.querySelector('.v2-ico-search[data-focus]');
  if (jump) {
    jump.addEventListener('click', function (ev) {
      var input = document.getElementById(jump.getAttribute('data-focus'));
      if (!input) return;
      ev.preventDefault();
      input.scrollIntoView({ block: 'center', behavior: 'auto' });
      input.focus({ preventScroll: true });
    });
  }
})();
