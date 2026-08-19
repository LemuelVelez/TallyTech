(() => {
  const body = document.body;
  const navToggle = document.querySelector('[data-nav-toggle]');
  const accountMenu = document.querySelector('[data-account-menu]');
  const accountToggle = accountMenu?.querySelector('[data-account-toggle]');
  const accountDropdown = accountMenu?.querySelector('[data-account-dropdown]');
  const confirmDialog = document.querySelector('[data-confirm-dialog]');
  const confirmMessage = confirmDialog?.querySelector('[data-confirm-message]');
  const confirmCancel = confirmDialog?.querySelector('[data-confirm-cancel]');
  const confirmProceed = confirmDialog?.querySelector('[data-confirm-proceed]');

  let lastDialogTrigger = null;
  let pendingConfirmForm = null;
  let pendingConfirmSubmitter = null;

  const setNavigation = (open) => {
    body.classList.toggle('nav-open', open);
    navToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    navToggle?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  };

  const accountItems = () => accountDropdown
    ? Array.from(accountDropdown.querySelectorAll('a[href], button:not([disabled])'))
    : [];

  const setAccountMenu = (open, focusFirst = false) => {
    if (!accountToggle || !accountDropdown) return;
    accountToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    accountDropdown.hidden = !open;
    accountMenu?.classList.toggle('is-open', open);
    if (open && focusFirst) accountItems()[0]?.focus();
  };

  const confirmationFor = (form) => {
    if (form.dataset.confirm) return form.dataset.confirm;

    const activeCheckbox = form.querySelector('input[type="checkbox"][name="is_active"]');
    if (form.dataset.confirmActive && activeCheckbox?.checked) {
      return form.dataset.confirmActive;
    }

    const status = form.querySelector('select[name="status"]');
    if (form.dataset.confirmInactive
      && form.dataset.originalStatus === 'active'
      && status?.value === 'inactive') {
      return form.dataset.confirmInactive;
    }

    const role = form.querySelector('select[name="role"]');
    if (form.dataset.confirmAdminCreate
      && !form.dataset.originalRole
      && role?.value === 'admin') {
      return form.dataset.confirmAdminCreate;
    }

    if (form.dataset.confirmRoleChange
      && form.dataset.originalRole
      && role?.value
      && role.value !== form.dataset.originalRole) {
      return form.dataset.confirmRoleChange;
    }

    if (form.dataset.confirmStatusChange
      && form.dataset.originalStatus
      && status?.value
      && status.value !== form.dataset.originalStatus) {
      return form.dataset.confirmStatusChange;
    }

    return '';
  };

  const markSubmitting = (form) => {
    if (form.dataset.submitting === '1') return false;
    form.dataset.submitting = '1';
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
      button.disabled = true;
    });
    return true;
  };

  const clearPendingConfirmation = () => {
    pendingConfirmForm = null;
    pendingConfirmSubmitter = null;
    if (confirmProceed) confirmProceed.textContent = 'Confirm';
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

    if (event.target.closest('[data-nav-toggle]')) {
      setAccountMenu(false);
      setNavigation(!body.classList.contains('nav-open'));
    }
    if (event.target.closest('[data-nav-close]')) setNavigation(false);

    if (event.target.closest('[data-account-toggle]')) {
      setNavigation(false);
      setAccountMenu(accountDropdown?.hidden ?? true);
      return;
    }

    if (accountMenu && !accountMenu.contains(event.target)) {
      setAccountMenu(false);
    }
  });

  accountToggle?.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setAccountMenu(true, true);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      setAccountMenu(false);
    }
  });

  accountDropdown?.addEventListener('keydown', (event) => {
    const items = accountItems();
    const index = items.indexOf(document.activeElement);
    if (!items.length) return;

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      items[(index + 1 + items.length) % items.length]?.focus();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      items[(index - 1 + items.length) % items.length]?.focus();
    } else if (event.key === 'Home') {
      event.preventDefault();
      items[0]?.focus();
    } else if (event.key === 'End') {
      event.preventDefault();
      items[items.length - 1]?.focus();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      setAccountMenu(false);
      accountToggle?.focus();
    }
  });

  accountMenu?.addEventListener('focusout', () => {
    window.setTimeout(() => {
      if (accountMenu && !accountMenu.contains(document.activeElement)) setAccountMenu(false);
    }, 0);
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
      if (dialog === confirmDialog) return;
      if (lastDialogTrigger?.isConnected) lastDialogTrigger.focus();
      lastDialogTrigger = null;
    });
  });

  document.querySelectorAll('[data-role-managed-form]').forEach((form) => {
    const roleSelect = form.querySelector('[data-user-role]');
    const hiddenRole = form.querySelector('input[type="hidden"][name="role"]');
    const sportSection = form.querySelector('[data-sport-assignment]');
    const sportCheckboxes = sportSection ? Array.from(sportSection.querySelectorAll('input[name="sport_ids[]"]')) : [];

    const selectedRole = () => roleSelect?.value || hiddenRole?.value || '';
    const syncSportSection = () => {
      const facilitator = selectedRole() === 'facilitator';
      if (sportSection) sportSection.hidden = !facilitator;
      sportCheckboxes.forEach((checkbox) => {
        checkbox.disabled = !facilitator;
        checkbox.setCustomValidity('');
      });
    };

    roleSelect?.addEventListener('change', syncSportSection);
    sportCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
      sportCheckboxes.forEach((item) => item.setCustomValidity(''));
    }));

    form.addEventListener('submit', (event) => {
      if (selectedRole() !== 'facilitator' || !sportCheckboxes.length) return;
      if (sportCheckboxes.some((checkbox) => checkbox.checked)) return;

      event.preventDefault();
      sportCheckboxes[0].setCustomValidity('Assign at least one sport to the facilitator.');
      sportCheckboxes[0].reportValidity();
    });

    syncSportSection();
  });


  document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const field = toggle.closest('.password-field');
    const input = field?.querySelector('[data-password-input]');
    if (!(input instanceof HTMLInputElement)) return;

    toggle.addEventListener('click', () => {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      toggle.classList.toggle('is-visible', !showing);
      toggle.setAttribute('aria-pressed', !showing ? 'true' : 'false');
      toggle.setAttribute('aria-label', !showing ? 'Hide password' : 'Show password');
      input.focus({ preventScroll: true });
      const end = input.value.length;
      input.setSelectionRange?.(end, end);
    });
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;

    if (form.dataset.submitting === '1') {
      event.preventDefault();
      return;
    }

    if (form.dataset.confirmed === '1') {
      delete form.dataset.confirmed;
      markSubmitting(form);
      return;
    }

    const message = confirmationFor(form);
    if (!message) {
      markSubmitting(form);
      return;
    }

    if (!confirmDialog?.showModal) {
      if (!window.confirm(message)) {
        event.preventDefault();
        return;
      }
      markSubmitting(form);
      return;
    }

    event.preventDefault();
    pendingConfirmForm = form;
    pendingConfirmSubmitter = event.submitter || null;
    if (confirmMessage) confirmMessage.textContent = message;
    if (confirmProceed) {
      const actionLabel = pendingConfirmSubmitter?.textContent?.trim();
      confirmProceed.textContent = actionLabel || 'Confirm';
    }
    confirmDialog.showModal();
    window.setTimeout(() => confirmCancel?.focus(), 0);
  });

  confirmCancel?.addEventListener('click', () => confirmDialog?.close());
  confirmProceed?.addEventListener('click', () => {
    const form = pendingConfirmForm;
    const submitter = pendingConfirmSubmitter;
    if (!form) {
      confirmDialog?.close();
      return;
    }

    form.dataset.confirmed = '1';
    confirmDialog?.close();
    if (typeof form.requestSubmit === 'function') {
      if (submitter) form.requestSubmit(submitter); else form.requestSubmit();
    } else {
      markSubmitting(form);
      form.submit();
    }
  });

  confirmDialog?.addEventListener('close', clearPendingConfirmation);
  confirmDialog?.addEventListener('cancel', clearPendingConfirmation);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (body.classList.contains('nav-open')) setNavigation(false);
    if (accountDropdown && !accountDropdown.hidden) {
      setAccountMenu(false);
      accountToggle?.focus();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 860 && body.classList.contains('nav-open')) setNavigation(false);
  });

  const carousel = document.querySelector('[data-hero-carousel]');
  if (!carousel) return;

  const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
  const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));
  const previous = carousel.querySelector('[data-carousel-prev]');
  const next = carousel.querySelector('[data-carousel-next]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const interval = Number(carousel.dataset.interval) || 6000;
  let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
  let timer = null;
  let touchStartX = 0;

  const render = (index) => {
    if (!slides.length) return;
    activeIndex = (index + slides.length) % slides.length;

    slides.forEach((slide, slideIndex) => {
      const isActive = slideIndex === activeIndex;
      slide.classList.toggle('is-active', isActive);
      slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });

    dots.forEach((dot, dotIndex) => {
      const isActive = dotIndex === activeIndex;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-current', isActive ? 'true' : 'false');
    });
  };

  const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };

  const start = () => {
    stop();
    if (slides.length < 2 || reduceMotion.matches || document.hidden) return;
    timer = window.setInterval(() => render(activeIndex + 1), interval);
  };

  const move = (offset) => {
    render(activeIndex + offset);
    start();
  };

  previous?.addEventListener('click', () => move(-1));
  next?.addEventListener('click', () => move(1));

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
      render(index);
      start();
    });
  });

  carousel.addEventListener('mouseenter', stop);
  carousel.addEventListener('mouseleave', start);
  carousel.addEventListener('focusin', stop);
  carousel.addEventListener('focusout', (event) => {
    if (!carousel.contains(event.relatedTarget)) start();
  });

  carousel.addEventListener('touchstart', (event) => {
    touchStartX = event.changedTouches[0]?.clientX || 0;
    stop();
  }, { passive: true });

  carousel.addEventListener('touchend', (event) => {
    const touchEndX = event.changedTouches[0]?.clientX || 0;
    const distance = touchEndX - touchStartX;
    if (Math.abs(distance) > 45) render(activeIndex + (distance < 0 ? 1 : -1));
    start();
  }, { passive: true });

  carousel.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      move(-1);
    }
    if (event.key === 'ArrowRight') {
      event.preventDefault();
      move(1);
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop(); else start();
  });

  reduceMotion.addEventListener?.('change', start);
  render(activeIndex);
  start();
})();
