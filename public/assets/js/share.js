/*
 * The share row on an "I'm going" card: the phone's own share sheet where there is one, and a copy
 * button that says it worked. Which button was pressed is counted by contribute-track.js from the
 * data-cta on it; nothing here sends anything.
 */
(function () {
  'use strict';
  var row = document.querySelector('.share-row');
  if (!row) return;
  var url = row.getAttribute('data-share-url');
  var text = row.getAttribute('data-share-text');
  var nat = row.querySelector('.share-native');
  if (nat && navigator.share) {
    nat.hidden = false;
    nat.addEventListener('click', function () {
      navigator.share({ title: text, text: text, url: url }).catch(function () {});
    });
  }
  var copy = row.querySelector('.share-copy');
  if (copy) {
    copy.addEventListener('click', function () {
      var done = function () { copy.textContent = 'Link copied'; setTimeout(function () { copy.textContent = 'Copy link'; }, 2500); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link', url); });
      } else {
        window.prompt('Copy this link', url);
      }
    });
  }
})();
