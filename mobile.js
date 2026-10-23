/* Мобильное меню (бургер) для сайта «Про технологии» */
(function () {
  var root = document.documentElement;

  function setOpen(open) {
    root.classList.toggle('nav-open', open);
    var btn = document.querySelector('.nav-toggle');
    if (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    }
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('.nav-toggle')) {
      e.preventDefault();
      setOpen(!root.classList.contains('nav-open'));
      return;
    }
    // клик по пункту меню или мимо шапки — закрываем
    if (t.closest('.site-nav a') || !t.closest('.site-header')) setOpen(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) setOpen(false);
  });
})();
