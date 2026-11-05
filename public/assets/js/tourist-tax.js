/* Changing the city reloads the form with that city's own questions. Without this the button does it. */
(function () {
  var city = document.querySelector('[data-tt-city]');
  var form = document.querySelector('[data-tt-form]');
  if (!city || !form) return;
  city.addEventListener('change', function () {
    /* The old city's answers do not apply: a tier or price from Paris means nothing in Tokyo. */
    Array.prototype.forEach.call(form.querySelectorAll('[name=tier],[name=price]'), function (el) { el.disabled = true; });
    form.submit();
  });
})();
