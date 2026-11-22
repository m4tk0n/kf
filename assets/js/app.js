/* assets/js/app.js */
(function () {
  'use strict';
  var sel = document.querySelector('.bar select');
  if (sel) sel.addEventListener('change', function () { sel.form.submit(); });
})();
