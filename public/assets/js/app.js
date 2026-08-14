(() => {
  const body = document.body;
  const toggle = document.querySelector('[data-nav-toggle]');

  const setNavigation = (open) => {
    body.classList.toggle('nav-open', open);
    toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal]');
    if (opener && !opener.disabled) {
      const dialog = document.getElementById(opener.dataset.modal);
      if (dialog?.showModal) dialog.showModal();
    }

    if (event.target.closest('[data-close]')) {
      event.target.closest('dialog')?.close();
    }

    if (event.target.closest('[data-nav-toggle]')) setNavigation(!body.classList.contains('nav-open'));
    if (event.target.closest('[data-nav-close]')) setNavigation(false);
  });

  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Are you sure?')) event.preventDefault();
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && body.classList.contains('nav-open')) setNavigation(false);
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 820 && body.classList.contains('nav-open')) setNavigation(false);
  });
})();
