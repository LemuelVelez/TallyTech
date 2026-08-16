(() => {
  const body = document.body;
  const toggle = document.querySelector('[data-nav-toggle]');
  let lastDialogTrigger = null;

  const setNavigation = (open) => {
    body.classList.toggle('nav-open', open);
    toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  };

  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal]');
    if (opener && !opener.disabled) {
      const dialog = document.getElementById(opener.dataset.modal);
      if (dialog?.showModal) {
        lastDialogTrigger = opener;
        dialog.showModal();
      }
    }

    if (event.target.closest('[data-close]')) {
      event.target.closest('dialog')?.close();
    }

    if (event.target.closest('[data-nav-toggle]')) setNavigation(!body.classList.contains('nav-open'));
    if (event.target.closest('[data-nav-close]')) setNavigation(false);
  });

  document.querySelectorAll('dialog').forEach((dialog, index) => {
    const heading = dialog.querySelector('.modal-head h2');
    if (heading && !dialog.hasAttribute('aria-labelledby')) {
      heading.id ||= `dialog-title-${index + 1}`;
      dialog.setAttribute('aria-labelledby', heading.id);
    }
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
      if (lastDialogTrigger?.isConnected) lastDialogTrigger.focus();
      lastDialogTrigger = null;
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }
      if (!window.confirm(form.dataset.confirm || 'Are you sure?')) {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = '1';
      form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        button.disabled = true;
      });
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && body.classList.contains('nav-open')) setNavigation(false);
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 820 && body.classList.contains('nav-open')) setNavigation(false);
  });
})();
