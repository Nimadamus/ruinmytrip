/*
 * Keep a half-written review from disappearing.
 *
 * Losing four hundred words to an accidental refresh, a session timeout or a back button is the
 * kind of thing somebody does not come back from -- they do not write it again, they stop writing.
 * This saves what is in the form to localStorage as it is typed, restores it on the next visit to
 * the same form, and clears it once the review is actually submitted.
 *
 * Deliberately small:
 *
 *   - localStorage only. No server round trips, nothing to fail, nothing new to store about
 *     anybody. A draft never leaves the device it was typed on.
 *   - keyed by the form's own action and the place or destination being reviewed, so a draft for
 *     one place can never surface on another.
 *   - it never overwrites what the server already put in the fields. Editing an existing review
 *     starts from that review, not from something typed a week ago.
 *   - it expires. A fortnight-old draft is not a draft, it is clutter.
 */
(function () {
  'use strict';

  var MAX_AGE_MS = 14 * 24 * 60 * 60 * 1000;
  var SAVE_DEBOUNCE_MS = 700;

  function storageOK() {
    try {
      var k = '__rmt_probe';
      window.localStorage.setItem(k, '1');
      window.localStorage.removeItem(k);
      return true;
    } catch (e) {
      return false;      // private mode, storage disabled, quota: all fine, just do nothing
    }
  }

  if (!storageOK()) return;

  var form = document.querySelector('form[data-review-draft]');
  if (!form) return;

  var key = 'rmt.draft.' + (form.getAttribute('data-review-draft') || 'review');

  // Only the fields worth protecting: the ones somebody spent time on. Ratings and selects are one
  // click to redo; four paragraphs are not.
  var FIELDS = ['title', 'body', 'what_great', 'what_ruined'];

  function fields() {
    return FIELDS.map(function (n) { return form.querySelector('[name="' + n + '"]'); })
                 .filter(Boolean);
  }

  function save() {
    var data = { at: Date.now(), values: {} };
    var any = false;
    fields().forEach(function (el) {
      var v = (el.value || '').trim();
      if (v) { data.values[el.name] = el.value; any = true; }
    });
    try {
      if (any) window.localStorage.setItem(key, JSON.stringify(data));
      else window.localStorage.removeItem(key);
    } catch (e) { /* quota or disabled: the form still works */ }
  }

  function clear() {
    try { window.localStorage.removeItem(key); } catch (e) {}
  }

  function restore() {
    var raw;
    try { raw = window.localStorage.getItem(key); } catch (e) { return; }
    if (!raw) return;

    var data;
    try { data = JSON.parse(raw); } catch (e) { clear(); return; }
    if (!data || !data.values || !data.at || (Date.now() - data.at) > MAX_AGE_MS) { clear(); return; }

    var restored = 0;
    fields().forEach(function (el) {
      // Never clobber what the server rendered: an edit form arrives with the real review in it.
      if ((el.value || '').trim() !== '') return;
      var v = data.values[el.name];
      if (typeof v === 'string' && v !== '') { el.value = v; restored++; }
    });
    if (!restored) return;

    var note = document.createElement('p');
    note.className = 'hint';
    note.setAttribute('role', 'status');
    note.style.margin = '0 0 12px';
    note.textContent = 'Restored what you had written here before. ';

    var discard = document.createElement('button');
    discard.type = 'button';
    discard.className = 'btn btn-ghost';
    discard.style.cssText = 'padding:2px 10px;font-size:.85rem;margin-left:6px';
    discard.textContent = 'Start over';
    discard.addEventListener('click', function () {
      fields().forEach(function (el) { el.value = ''; });
      clear();
      note.remove();
    });
    note.appendChild(discard);
    form.insertBefore(note, form.firstChild);
  }

  var timer = null;
  form.addEventListener('input', function (ev) {
    if (FIELDS.indexOf(ev.target.name) === -1) return;
    if (timer) clearTimeout(timer);
    timer = setTimeout(save, SAVE_DEBOUNCE_MS);
  });

  // Submitting is the one moment we know the text is safe somewhere else.
  form.addEventListener('submit', clear);

  restore();
})();
