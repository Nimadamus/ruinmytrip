/* Tick an item on a trip's "Before you go" list without reloading. Without this the form posts and redirects back. */
(function () {
  var forms = document.querySelectorAll('[data-tcl-form]');
  var counter = document.querySelector('[data-tcl-count]');
  Array.prototype.forEach.call(forms, function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.fetch || !window.FormData) return;
      ev.preventDefault();
      var data = new FormData(form);
      data.append('ajax', '1');
      var doneInput = form.querySelector('[name=done]');
      var nowDone = doneInput.value === '1';
      var item = form.closest('.tcl-item');
      var box = form.querySelector('.tcl-box');
      fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' }).then(function (r) {
        if (!r.ok) { form.submit(); return; }
        doneInput.value = nowDone ? '0' : '1';
        item.classList.toggle('is-done', nowDone);
        box.setAttribute('aria-pressed', nowDone ? 'true' : 'false');
        box.textContent = nowDone ? '✓' : '';
        if (counter) counter.textContent = String(document.querySelectorAll('.tcl-item.is-done').length);
      }).catch(function () { form.submit(); });
    });
  });
})();
