(() => {
  const body = document.body;
  const navToggle = document.querySelector('[data-nav-toggle]');
  const sidebarCompactToggle = document.querySelector('[data-sidebar-compact-toggle]');
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


  document.querySelectorAll('[data-flash-alert]').forEach((alert) => {
    const delay = Number.parseInt(alert.dataset.dismissAfter || '5000', 10);
    window.setTimeout(() => alert.remove(), Number.isFinite(delay) && delay >= 0 ? delay : 5000);
  });

  const scoreboardSportNav = document.querySelector('[data-scoreboard-sport-nav]');
  if (scoreboardSportNav) {
    const sportLinks = Array.from(scoreboardSportNav.querySelectorAll('[data-scoreboard-sport-link]'));
    const rotationUi = document.querySelector('[data-scoreboard-rotation-ui]');
    const rotationToggle = rotationUi?.querySelector('[data-scoreboard-auto-rotate-toggle]');
    const rotationLabel = rotationUi?.querySelector('[data-scoreboard-rotation-label]');
    const rotationCountdown = rotationUi?.querySelector('[data-scoreboard-rotation-countdown]');
    const rotationUnit = rotationUi?.querySelector('[data-scoreboard-rotation-unit]');
    const configuredDelay = Number.parseInt(scoreboardSportNav.dataset.autoRotateMs || '15000', 10);
    const rotateDelay = Number.isFinite(configuredDelay) && configuredDelay >= 3000 ? configuredDelay : 15000;
    const SCOREBOARD_AUTO_ROTATE_KEY = 'tallytech.scoreboardAutoRotate.v1';
    let sportRotateTimer = null;
    let sportCountdownTimer = null;
    let rotationDeadline = 0;
    let autoRotateEnabled = true;

    try {
      const storedAutoRotate = window.localStorage.getItem(SCOREBOARD_AUTO_ROTATE_KEY);
      if (storedAutoRotate !== null) autoRotateEnabled = storedAutoRotate !== '0';
    } catch (_) {
      // Auto rotation still works when browser storage is unavailable.
    }

    const persistAutoRotation = () => {
      try {
        window.localStorage.setItem(SCOREBOARD_AUTO_ROTATE_KEY, autoRotateEnabled ? '1' : '0');
      } catch (_) {
        // Keep the current-page setting even when browser storage is unavailable.
      }
    };

    const stopSportRotation = () => {
      if (sportRotateTimer !== null) {
        window.clearTimeout(sportRotateTimer);
        sportRotateTimer = null;
      }
      if (sportCountdownTimer !== null) {
        window.clearInterval(sportCountdownTimer);
        sportCountdownTimer = null;
      }
      rotationDeadline = 0;
    };

    const updateRotationUi = () => {
      if (!rotationUi) return;
      const canRotate = sportLinks.length >= 2;
      rotationUi.hidden = !canRotate;
      if (!canRotate) return;

      if (rotationToggle) rotationToggle.checked = autoRotateEnabled;
      rotationUi.classList.toggle('is-disabled', !autoRotateEnabled);

      if (!autoRotateEnabled) {
        if (rotationLabel) rotationLabel.textContent = 'Auto rotation is off';
        if (rotationCountdown) rotationCountdown.hidden = true;
        if (rotationUnit) rotationUnit.hidden = true;
        return;
      }

      if (rotationLabel) rotationLabel.textContent = 'Next sport in';
      if (rotationUnit) rotationUnit.hidden = false;
      if (rotationCountdown) {
        const remainingMs = Math.max(0, rotationDeadline - Date.now());
        rotationCountdown.hidden = false;
        rotationCountdown.textContent = String(Math.max(0, Math.ceil(remainingMs / 1000)));
      }
    };

    const goToNextSport = () => {
      const currentIndex = sportLinks.findIndex((link) => link.matches('[aria-current="page"], .active'));
      const nextIndex = currentIndex >= 0 ? (currentIndex + 1) % sportLinks.length : 0;
      const nextLink = sportLinks[nextIndex];
      if (nextLink?.href) window.location.assign(nextLink.href);
    };

    const scheduleSportRotation = () => {
      stopSportRotation();
      if (!autoRotateEnabled || sportLinks.length < 2 || document.visibilityState === 'hidden') {
        updateRotationUi();
        return;
      }

      rotationDeadline = Date.now() + rotateDelay;
      updateRotationUi();
      sportCountdownTimer = window.setInterval(updateRotationUi, 250);
      sportRotateTimer = window.setTimeout(goToNextSport, rotateDelay);
    };

    if (rotationToggle) {
      rotationToggle.checked = autoRotateEnabled;
      rotationToggle.addEventListener('change', () => {
        autoRotateEnabled = rotationToggle.checked;
        persistAutoRotation();
        scheduleSportRotation();
      });
    }

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') stopSportRotation();
      else scheduleSportRotation();
    });
    sportLinks.forEach((link) => link.addEventListener('click', stopSportRotation));
    scheduleSportRotation();
  }

  const setNavigation = (open) => {
    body.classList.toggle('nav-open', open);
    navToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    navToggle?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  };

  const SIDEBAR_COMPACT_KEY = 'tallytech.sidebarCompact';

  const setSidebarCompact = (compact, persist = true) => {
    if (window.matchMedia('(max-width: 860px)').matches) return;

    body.classList.toggle('sidebar-compact', compact);
    sidebarCompactToggle?.setAttribute('aria-expanded', compact ? 'false' : 'true');
    sidebarCompactToggle?.setAttribute('aria-label', compact ? 'Expand navigation' : 'Collapse navigation');
    sidebarCompactToggle?.setAttribute('title', compact ? 'Expand navigation' : 'Collapse navigation');

    if (persist) {
      try {
        window.localStorage.setItem(SIDEBAR_COMPACT_KEY, compact ? '1' : '0');
      } catch (_) {
        // The compact state still works when browser storage is unavailable.
      }
    }
  };

  if (sidebarCompactToggle) {
    let storedCompact = null;
    try {
      storedCompact = window.localStorage.getItem(SIDEBAR_COMPACT_KEY);
    } catch (_) {
      storedCompact = null;
    }

    const initialCompact = storedCompact === null
      ? body.classList.contains('sidebar-compact')
      : storedCompact === '1';
    setSidebarCompact(initialCompact, false);
  }

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

    if (event.target.closest('[data-sidebar-compact-toggle]')) {
      setSidebarCompact(!body.classList.contains('sidebar-compact'));
    }

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

  window.addEventListener('resize', () => {
    if (window.matchMedia('(max-width: 860px)').matches) {
      sidebarCompactToggle?.setAttribute('aria-expanded', 'true');
      sidebarCompactToggle?.setAttribute('aria-label', 'Collapse navigation');
      sidebarCompactToggle?.setAttribute('title', 'Collapse navigation');
    } else if (sidebarCompactToggle) {
      setSidebarCompact(body.classList.contains('sidebar-compact'), false);
      setNavigation(false);
    }
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

  const selectStates = new Map();
  let openSelectState = null;

  const supportsPopover = 'showPopover' in HTMLElement.prototype;

  const closeSleekSelect = (state, restoreFocus = false) => {
    if (!state || !state.open) return;
    state.open = false;
    state.wrapper.classList.remove('is-open');
    state.trigger.setAttribute('aria-expanded', 'false');

    if (supportsPopover && state.content.matches(':popover-open')) {
      state.content.hidePopover();
    } else {
      state.content.hidden = true;
    }

    if (openSelectState === state) openSelectState = null;
    if (restoreFocus) state.trigger.focus();
  };

  const positionSleekSelect = (state) => {
    if (!state?.open) return;
    const rect = state.trigger.getBoundingClientRect();
    const viewportGap = 10;
    const desiredHeight = Math.min(state.content.scrollHeight || 280, 320);
    const spaceBelow = window.innerHeight - rect.bottom - viewportGap;
    const spaceAbove = rect.top - viewportGap;
    const openAbove = spaceBelow < Math.min(desiredHeight, 180) && spaceAbove > spaceBelow;
    const available = Math.max(120, Math.min(320, openAbove ? spaceAbove : spaceBelow));

    const panelWidth = Math.max(180, Math.min(rect.width, window.innerWidth - (viewportGap * 2)));
    const panelLeft = Math.max(viewportGap, Math.min(rect.left, window.innerWidth - panelWidth - viewportGap));
    state.content.style.setProperty('--select-width', `${panelWidth}px`);
    state.content.style.maxHeight = `${available}px`;
    state.content.style.position = 'fixed';
    state.content.style.left = `${panelLeft}px`;
    state.content.style.top = openAbove ? 'auto' : `${rect.bottom + 6}px`;
    state.content.style.bottom = openAbove ? `${window.innerHeight - rect.top + 6}px` : 'auto';
  };

  const refreshSleekSelect = (state) => {
    const { select, trigger, content } = state;
    const selected = select.options[select.selectedIndex];
    trigger.textContent = selected?.textContent?.trim() || 'Select an option';
    trigger.disabled = select.disabled;
    trigger.setAttribute('aria-invalid', select.matches(':invalid') ? 'true' : 'false');
    content.innerHTML = '';

    Array.from(select.options).forEach((option, index) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'sleek-select-option';
      item.dataset.selectIndex = String(index);
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
      item.disabled = option.disabled;
      item.textContent = option.textContent;
      item.classList.toggle('is-selected', option.selected);
      content.appendChild(item);
    });

    if (state.open) positionSleekSelect(state);
  };

  const focusSelectOption = (state, index) => {
    const options = Array.from(state.content.querySelectorAll('.sleek-select-option:not(:disabled)'));
    if (!options.length) return;
    const clamped = Math.max(0, Math.min(index, options.length - 1));
    options.forEach((item) => item.classList.remove('is-focused'));
    options[clamped].classList.add('is-focused');
    options[clamped].focus({ preventScroll: true });
    options[clamped].scrollIntoView({ block: 'nearest' });
  };

  const openSleekSelect = (state, focusSelected = false) => {
    if (!state || state.select.disabled) return;
    if (openSelectState && openSelectState !== state) closeSleekSelect(openSelectState);

    refreshSleekSelect(state);
    state.open = true;
    openSelectState = state;
    state.wrapper.classList.add('is-open');
    state.trigger.setAttribute('aria-expanded', 'true');

    if (supportsPopover) {
      state.content.hidden = false;
      state.content.showPopover();
    } else {
      state.content.hidden = false;
    }

    positionSleekSelect(state);

    if (focusSelected) {
      const enabledOptions = Array.from(state.content.querySelectorAll('.sleek-select-option:not(:disabled)'));
      const selectedIndex = enabledOptions.findIndex((item) => item.classList.contains('is-selected'));
      requestAnimationFrame(() => focusSelectOption(state, selectedIndex >= 0 ? selectedIndex : 0));
    }
  };

  const initSleekSelect = (select, index) => {
    if (!(select instanceof HTMLSelectElement) || select.multiple || select.dataset.sleekSelectReady === '1') return;
    select.dataset.sleekSelectReady = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'sleek-select';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('sleek-select-native');
    select.tabIndex = -1;

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'sleek-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `sleek-select-content-${index + 1}`);
    wrapper.appendChild(trigger);

    const content = document.createElement('div');
    content.id = `sleek-select-content-${index + 1}`;
    content.className = 'sleek-select-content';
    content.setAttribute('role', 'listbox');
    content.hidden = true;
    if (supportsPopover) content.setAttribute('popover', 'manual');
    const contentHost = select.closest('dialog') || document.body;
    contentHost.appendChild(content);

    const state = { select, wrapper, trigger, content, open: false };
    selectStates.set(select, state);
    refreshSleekSelect(state);

    trigger.addEventListener('click', () => {
      if (state.open) closeSleekSelect(state); else openSleekSelect(state);
    });

    trigger.addEventListener('keydown', (event) => {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openSleekSelect(state, true);
      } else if (event.key === 'Escape' && state.open) {
        event.preventDefault();
        closeSleekSelect(state, true);
      } else if (event.key === 'Tab' && state.open) {
        closeSleekSelect(state);
      }
    });

    content.addEventListener('click', (event) => {
      const item = event.target.closest('.sleek-select-option');
      if (!item || item.disabled) return;
      const optionIndex = Number(item.dataset.selectIndex);
      if (!Number.isInteger(optionIndex) || !select.options[optionIndex]) return;

      select.selectedIndex = optionIndex;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      refreshSleekSelect(state);
      closeSleekSelect(state, true);
    });

    content.addEventListener('keydown', (event) => {
      const options = Array.from(content.querySelectorAll('.sleek-select-option:not(:disabled)'));
      const current = options.indexOf(document.activeElement);

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        focusSelectOption(state, current < 0 ? 0 : (current + 1) % options.length);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        focusSelectOption(state, current < 0 ? options.length - 1 : (current - 1 + options.length) % options.length);
      } else if (event.key === 'Home') {
        event.preventDefault();
        focusSelectOption(state, 0);
      } else if (event.key === 'End') {
        event.preventDefault();
        focusSelectOption(state, options.length - 1);
      } else if (event.key === 'Escape' || event.key === 'Tab') {
        closeSleekSelect(state, event.key === 'Escape');
      }
    });

    select.addEventListener('change', () => refreshSleekSelect(state));
    select.addEventListener('focus', () => trigger.focus());
    select.addEventListener('invalid', () => {
      trigger.setAttribute('aria-invalid', 'true');
      requestAnimationFrame(() => trigger.focus());
    });

    select.form?.addEventListener('reset', () => requestAnimationFrame(() => refreshSleekSelect(state)));

    const observer = new MutationObserver(() => refreshSleekSelect(state));
    observer.observe(select, { attributes: true, childList: true, subtree: true });
  };

  document.querySelectorAll('select').forEach(initSleekSelect);

  document.addEventListener('pointerdown', (event) => {
    if (!openSelectState) return;
    if (openSelectState.wrapper.contains(event.target) || openSelectState.content.contains(event.target)) return;
    closeSleekSelect(openSelectState);
  });

  window.addEventListener('resize', () => {
    if (openSelectState) positionSleekSelect(openSelectState);
  });

  document.addEventListener('scroll', () => {
    if (openSelectState) positionSleekSelect(openSelectState);
  }, true);

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

  document.querySelectorAll('[data-password-rules]').forEach((rules) => {
    const form = rules.closest('form');
    const input = form?.querySelector('[data-password-input]');
    if (!(input instanceof HTMLInputElement)) return;

    const checks = {
      length: (value) => value.length >= 8,
      case: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value),
      number: (value) => /\d/.test(value),
      special: (value) => /[^A-Za-z0-9]/.test(value),
    };

    const syncPasswordRules = () => {
      const value = input.value;
      rules.querySelectorAll('[data-password-rule]').forEach((rule) => {
        const check = checks[rule.dataset.passwordRule];
        const isMet = typeof check === 'function' && check(value);
        rule.classList.toggle('is-met', isMet);
        rule.setAttribute('data-rule-met', isMet ? 'true' : 'false');
      });
    };

    input.addEventListener('input', syncPasswordRules);
    form?.addEventListener('reset', () => requestAnimationFrame(syncPasswordRules));
    syncPasswordRules();
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
    if (openSelectState) closeSleekSelect(openSelectState, true);
    if (body.classList.contains('nav-open')) setNavigation(false);
    if (accountDropdown && !accountDropdown.hidden) {
      setAccountMenu(false);
      accountToggle?.focus();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 860 && body.classList.contains('nav-open')) setNavigation(false);
  });

  const bracketBoards = Array.from(document.querySelectorAll('[data-bracket-board]'));
  const svgNamespace = 'http://www.w3.org/2000/svg';
  const normalizeMatchCode = (value) => String(value || '').trim().toUpperCase();

  const bracketPoint = (element, boardRect, edge, scale = 1) => {
    const rect = element.getBoundingClientRect();
    const safeScale = Number.isFinite(scale) && scale > 0 ? scale : 1;
    return {
      x: ((edge === 'right' ? rect.right : rect.left) - boardRect.left) / safeScale,
      y: (rect.top - boardRect.top + (rect.height / 2)) / safeScale,
    };
  };

  const appendBracketPath = (svg, d, classes = [], meta = {}) => {
    if (!d) return;
    const path = document.createElementNS(svgNamespace, 'path');
    path.setAttribute('d', d);
    classes.filter(Boolean).forEach((className) => path.classList.add(className));
    if (meta.from) path.dataset.from = normalizeMatchCode(meta.from);
    if (meta.to) path.dataset.to = normalizeMatchCode(meta.to);
    svg.appendChild(path);
  };

  const buildBracketGraph = (board) => {
    const matches = Array.from(board.querySelectorAll('[data-bracket-match][data-match-code]'));
    const matchByCode = new Map();
    const feedsByCode = new Map();
    const nextByCode = new Map();

    matches.forEach((match) => {
      const code = normalizeMatchCode(match.dataset.matchCode);
      if (!code) return;
      matchByCode.set(code, match);
    });

    matches.forEach((match) => {
      const targetCode = normalizeMatchCode(match.dataset.matchCode);
      if (!targetCode) return;

      const feeds = [match.dataset.feedA, match.dataset.feedB]
        .map(normalizeMatchCode)
        .filter((code, index, list) => code && matchByCode.has(code) && list.indexOf(code) === index);

      feedsByCode.set(targetCode, feeds);
      feeds.forEach((sourceCode) => {
        const next = nextByCode.get(sourceCode) || [];
        if (!next.includes(targetCode)) next.push(targetCode);
        nextByCode.set(sourceCode, next);
      });
    });

    return { matches, matchByCode, feedsByCode, nextByCode };
  };

  const collectBracketPath = (graph, startCode) => {
    const selected = new Set();
    const visit = (code, relationMap) => {
      if (!code || selected.has(code)) return;
      selected.add(code);
      (relationMap.get(code) || []).forEach((relatedCode) => visit(relatedCode, relationMap));
    };

    const upstream = new Set();
    const downstream = new Set();
    const walk = (code, relationMap, targetSet) => {
      if (!code || targetSet.has(code)) return;
      targetSet.add(code);
      (relationMap.get(code) || []).forEach((relatedCode) => walk(relatedCode, relationMap, targetSet));
    };

    walk(startCode, graph.feedsByCode, upstream);
    walk(startCode, graph.nextByCode, downstream);
    upstream.forEach((code) => selected.add(code));
    downstream.forEach((code) => selected.add(code));
    return selected;
  };

  const clearBracketSelection = (board, announce = true) => {
    board.classList.remove('has-bracket-selection');
    board.dataset.selectedMatch = '';
    board.querySelectorAll('[data-bracket-match]').forEach((match) => {
      match.classList.remove('is-selected', 'is-path-match');
      match.setAttribute('aria-pressed', 'false');
    });
    board.querySelectorAll('[data-bracket-connectors] path').forEach((path) => path.classList.remove('is-active'));

    const status = board.querySelector('[data-bracket-selection-status]');
    if (status && announce) status.textContent = 'Bracket path highlight cleared.';
  };

  const applyBracketSelection = (board, match, announce = true) => {
    if (!match) {
      clearBracketSelection(board, announce);
      return;
    }

    const graph = buildBracketGraph(board);
    const code = normalizeMatchCode(match.dataset.matchCode);
    if (!code || !graph.matchByCode.has(code)) return;

    const pathCodes = collectBracketPath(graph, code);
    board.classList.add('has-bracket-selection');
    board.dataset.selectedMatch = code;

    graph.matches.forEach((candidate) => {
      const candidateCode = normalizeMatchCode(candidate.dataset.matchCode);
      const isSelected = candidateCode === code;
      candidate.classList.toggle('is-selected', isSelected);
      candidate.classList.toggle('is-path-match', pathCodes.has(candidateCode));
      candidate.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });

    board.querySelectorAll('[data-bracket-connectors] path').forEach((path) => {
      const from = normalizeMatchCode(path.dataset.from);
      const to = normalizeMatchCode(path.dataset.to);
      path.classList.toggle('is-active', pathCodes.has(from) && pathCodes.has(to));
    });

    const status = board.querySelector('[data-bracket-selection-status]');
    if (status && announce) {
      const round = match.dataset.matchRound ? `, ${match.dataset.matchRound}` : '';
      status.textContent = `${code}${round} selected. Connected tournament path highlighted.`;
    }
  };

  const drawBracketBoard = (board) => {
    const svg = board.querySelector('[data-bracket-connectors]');
    if (!svg) return;

    const graph = buildBracketGraph(board);
    const boardRect = board.getBoundingClientRect();
    const bracketScale = Math.max(0.01, Number.parseFloat(board.dataset.bracketZoom || '1') || 1);
    const width = Math.max(board.scrollWidth, Math.ceil(boardRect.width / bracketScale));
    const height = Math.max(board.scrollHeight, Math.ceil(boardRect.height / bracketScale));
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));
    svg.replaceChildren();

    graph.matches.forEach((target) => {
      const targetCode = normalizeMatchCode(target.dataset.matchCode);
      const rawFeeds = [
        { code: target.dataset.feedA, type: target.dataset.feedAType },
        { code: target.dataset.feedB, type: target.dataset.feedBType },
      ];
      const feedMap = new Map();

      rawFeeds.forEach((feed) => {
        const code = normalizeMatchCode(feed.code);
        if (!code || !graph.matchByCode.has(code)) return;
        const existing = feedMap.get(code) || { code, types: new Set() };
        if (feed.type) existing.types.add(String(feed.type).toLowerCase());
        feedMap.set(code, existing);
      });

      const feeds = Array.from(feedMap.values()).map((feed) => ({
        ...feed,
        source: graph.matchByCode.get(feed.code),
      }));
      if (!feeds.length) return;

      const targetPoint = bracketPoint(target, boardRect, 'left', bracketScale);
      const sourcePoints = feeds.map((feed) => ({
        ...bracketPoint(feed.source, boardRect, 'right', bracketScale),
        code: feed.code,
        types: feed.types,
      }));
      const maxSourceX = Math.max(...sourcePoints.map((point) => point.x));
      const availableGap = targetPoint.x - maxSourceX;
      const joinX = availableGap >= 44
        ? maxSourceX + Math.max(22, Math.min(availableGap * 0.55, availableGap - 18))
        : maxSourceX + 22;
      const conditionalClass = target.classList.contains('conditional') ? 'is-conditional' : '';

      sourcePoints.forEach((source) => {
        appendBracketPath(
          svg,
          `M ${source.x} ${source.y} H ${joinX} V ${targetPoint.y} H ${targetPoint.x}`,
          [source.types.has('loser') ? 'is-loser-feed' : '', conditionalClass],
          { from: source.code, to: targetCode }
        );
      });
    });

    board.dataset.bracketReady = 'true';
    const selectedCode = normalizeMatchCode(board.dataset.selectedMatch);
    if (selectedCode && graph.matchByCode.has(selectedCode)) {
      applyBracketSelection(board, graph.matchByCode.get(selectedCode), false);
    }
  };

  if (bracketBoards.length) {
    const bracketZoomMin = 0.35;
    const bracketZoomMax = 2.5;
    const bracketZoomDefault = 0.5;
    const bracketZoomStep = 0.1;
    const bracketZoomStoragePrefix = 'tallytech.bracketZoom.v1';
    const bracketComponents = Array.from(document.querySelectorAll('[data-bracket-component]'));
    const bracketFrames = new Map();
    const bracketTrailingTimers = new WeakMap();
    const bracketZoomStorageKeys = new WeakMap();
    const bracketZoomPersistTimers = new WeakMap();
    const clampBracketZoom = (value) => {
      const parsed = Number.parseFloat(value);
      const safeValue = Number.isFinite(parsed) ? parsed : bracketZoomDefault;
      return Math.min(bracketZoomMax, Math.max(bracketZoomMin, safeValue));
    };

    const bracketZoomStorageKey = (component, scroll, index) => {
      const variant = component.classList.contains('tt-bracket-component--management') ? 'management' : 'public';
      const label = (scroll.getAttribute('aria-label') || `bracket-${index + 1}`).trim();
      return `${bracketZoomStoragePrefix}:${variant}:${label}`;
    };

    const storedBracketZoom = (key) => {
      try {
        const stored = window.localStorage.getItem(key);
        if (stored === null) return null;
        const parsed = Number.parseFloat(stored);
        return Number.isFinite(parsed) ? clampBracketZoom(parsed) : null;
      } catch (_) {
        return null;
      }
    };

    const persistBracketZoom = (component, zoom, immediate = false) => {
      const key = bracketZoomStorageKeys.get(component);
      if (!key) return;

      const currentTimer = bracketZoomPersistTimers.get(component);
      if (currentTimer) window.clearTimeout(currentTimer);

      const write = () => {
        bracketZoomPersistTimers.delete(component);
        try {
          window.localStorage.setItem(key, String(clampBracketZoom(zoom)));
        } catch (_) {
          // Zoom remains usable when browser storage is unavailable.
        }
      };

      if (immediate) write();
      else bracketZoomPersistTimers.set(component, window.setTimeout(write, 150));
    };

    const syncBracketShell = (board) => {
      const shell = board.closest('[data-bracket-zoom-shell]');
      if (!shell) return;
      const zoom = clampBracketZoom(board.dataset.bracketZoom || bracketZoomDefault);
      shell.style.width = `${Math.ceil(board.scrollWidth * zoom)}px`;
      shell.style.height = `${Math.ceil(board.scrollHeight * zoom)}px`;
    };

    const redrawBracket = (board) => {
      if (!board || bracketFrames.has(board)) return;
      const frame = window.requestAnimationFrame(() => {
        bracketFrames.delete(board);
        syncBracketShell(board);
        drawBracketBoard(board);
      });
      bracketFrames.set(board, frame);
    };

    const redrawBrackets = () => bracketBoards.forEach(redrawBracket);
    const trailingBracketRedraw = (board, delay = 110) => {
      const currentTimer = bracketTrailingTimers.get(board);
      if (currentTimer) window.clearTimeout(currentTimer);
      const timer = window.setTimeout(() => {
        bracketTrailingTimers.delete(board);
        redrawBracket(board);
      }, delay);
      bracketTrailingTimers.set(board, timer);
    };

    const currentBracketZoom = (board) => clampBracketZoom(board?.dataset.bracketZoom || bracketZoomDefault);
    const centerBracketAnchor = (scroll) => ({
      scroll,
      offsetX: scroll.clientWidth / 2,
      offsetY: scroll.clientHeight / 2,
    });

    const setBracketZoom = (component, requestedZoom, anchor = null, persist = true) => {
      const board = component.querySelector('[data-bracket-board]');
      const scroll = component.querySelector('[data-bracket-scroll]');
      if (!board || !scroll) return 1;

      const oldZoom = currentBracketZoom(board);
      const zoom = clampBracketZoom(requestedZoom);
      board.dataset.bracketZoom = String(zoom);
      board.style.zoom = '';
      board.style.transform = `scale(${zoom})`;
      syncBracketShell(board);

      if (anchor?.scroll === scroll && oldZoom > 0) {
        const ratio = zoom / oldZoom;
        scroll.scrollLeft = (scroll.scrollLeft + anchor.offsetX) * ratio - anchor.offsetX;
        scroll.scrollTop = (scroll.scrollTop + anchor.offsetY) * ratio - anchor.offsetY;
      }

      scroll.classList.toggle('is-zoomed', Math.abs(zoom - 1) > 0.001);

      const value = component.querySelector('[data-bracket-zoom-value]');
      const zoomOut = component.querySelector('[data-bracket-zoom-out]');
      const zoomIn = component.querySelector('[data-bracket-zoom-in]');
      if (value) value.textContent = `${Math.round(zoom * 100)}%`;
      if (zoomOut) zoomOut.disabled = zoom <= bracketZoomMin + 0.0001;
      if (zoomIn) zoomIn.disabled = zoom >= bracketZoomMax - 0.0001;
      if (persist) persistBracketZoom(component, zoom);
      redrawBracket(board);
      return zoom;
    };

    bracketComponents.forEach((component, componentIndex) => {
      const board = component.querySelector('[data-bracket-board]');
      const scroll = component.querySelector('[data-bracket-scroll]');
      if (!board || !scroll) return;

      const storageKey = bracketZoomStorageKey(component, scroll, componentIndex);
      bracketZoomStorageKeys.set(component, storageKey);
      const initialZoom = storedBracketZoom(storageKey) ?? bracketZoomDefault;
      const currentZoom = () => currentBracketZoom(board);
      const steppedZoom = (direction) => Math.round((currentZoom() + (direction * bracketZoomStep)) * 10) / 10;
      const centeredAnchor = () => centerBracketAnchor(scroll);
      const zoomHint = component.querySelector('[data-bracket-zoom-hint]');
      if (zoomHint) {
        zoomHint.textContent = window.matchMedia?.('(pointer: coarse)').matches ? 'Pinch to zoom' : 'Ctrl + scroll to zoom';
      }

      component.querySelector('[data-bracket-zoom-out]')?.addEventListener('click', () => {
        setBracketZoom(component, steppedZoom(-1), centeredAnchor());
        trailingBracketRedraw(board);
      });
      component.querySelector('[data-bracket-zoom-in]')?.addEventListener('click', () => {
        setBracketZoom(component, steppedZoom(1), centeredAnchor());
        trailingBracketRedraw(board);
      });
      component.querySelector('[data-bracket-zoom-reset]')?.addEventListener('click', () => {
        setBracketZoom(component, bracketZoomDefault, centeredAnchor());
        trailingBracketRedraw(board);
      });

      scroll.addEventListener('wheel', (event) => {
        if (!event.ctrlKey && !event.metaKey) return;
        event.preventDefault();

        let deltaY = event.deltaY;
        if (event.deltaMode === 1) deltaY *= 16;
        else if (event.deltaMode === 2) deltaY *= Math.max(scroll.clientHeight, 1);

        const rect = scroll.getBoundingClientRect();
        const anchor = {
          scroll,
          offsetX: event.clientX - rect.left,
          offsetY: event.clientY - rect.top,
        };
        setBracketZoom(component, currentZoom() * Math.exp(-deltaY * 0.0015), anchor);
        trailingBracketRedraw(board);
      }, { passive: false });

      const distanceBetween = (a, b) => Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
      const midpointBetween = (a, b) => ({
        clientX: (a.clientX + b.clientX) / 2,
        clientY: (a.clientY + b.clientY) / 2,
      });

      if ('PointerEvent' in window) {
        const pointers = new Map();
        let pinch = null;

        const beginPinch = () => {
          if (pointers.size !== 2) {
            pinch = null;
            return;
          }
          const points = Array.from(pointers.values());
          const startDistance = distanceBetween(points[0], points[1]);
          if (startDistance <= 0) return;
          pinch = { startDistance, startZoom: currentZoom() };
          pointers.forEach((_, pointerId) => {
            try { scroll.setPointerCapture?.(pointerId); } catch (_) {}
          });
        };

        scroll.addEventListener('pointerdown', (event) => {
          if (event.pointerType === 'mouse') return;
          pointers.set(event.pointerId, { clientX: event.clientX, clientY: event.clientY });
          if (pointers.size === 2) beginPinch();
          else if (pointers.size > 2) pinch = null;
        });

        scroll.addEventListener('pointermove', (event) => {
          if (!pointers.has(event.pointerId)) return;
          pointers.set(event.pointerId, { clientX: event.clientX, clientY: event.clientY });
          if (pointers.size !== 2 || !pinch) return;

          event.preventDefault();
          const points = Array.from(pointers.values());
          const currentDistance = distanceBetween(points[0], points[1]);
          if (currentDistance <= 0) return;
          const midpoint = midpointBetween(points[0], points[1]);
          const rect = scroll.getBoundingClientRect();
          setBracketZoom(component, pinch.startZoom * (currentDistance / pinch.startDistance), {
            scroll,
            offsetX: midpoint.clientX - rect.left,
            offsetY: midpoint.clientY - rect.top,
          });
        }, { passive: false });

        const endPointer = (event) => {
          if (!pointers.has(event.pointerId)) return;
          pointers.delete(event.pointerId);
          if (pointers.size === 2) beginPinch();
          else pinch = null;
          trailingBracketRedraw(board);
        };
        scroll.addEventListener('pointerup', endPointer);
        scroll.addEventListener('pointercancel', endPointer);
      } else {
        let pinch = null;
        const beginTouchPinch = (touches) => {
          if (touches.length !== 2) {
            pinch = null;
            return;
          }
          const startDistance = distanceBetween(touches[0], touches[1]);
          if (startDistance <= 0) return;
          pinch = { startDistance, startZoom: currentZoom() };
        };

        scroll.addEventListener('touchstart', (event) => {
          if (event.touches.length === 2) beginTouchPinch(event.touches);
          else if (event.touches.length > 2) pinch = null;
        }, { passive: true });

        scroll.addEventListener('touchmove', (event) => {
          if (event.touches.length !== 2 || !pinch) return;
          event.preventDefault();
          const currentDistance = distanceBetween(event.touches[0], event.touches[1]);
          if (currentDistance <= 0) return;
          const midpoint = midpointBetween(event.touches[0], event.touches[1]);
          const rect = scroll.getBoundingClientRect();
          setBracketZoom(component, pinch.startZoom * (currentDistance / pinch.startDistance), {
            scroll,
            offsetX: midpoint.clientX - rect.left,
            offsetY: midpoint.clientY - rect.top,
          });
        }, { passive: false });

        scroll.addEventListener('touchend', (event) => {
          if (event.touches.length === 2) beginTouchPinch(event.touches);
          else pinch = null;
          trailingBracketRedraw(board);
        }, { passive: true });
        scroll.addEventListener('touchcancel', () => {
          pinch = null;
          trailingBracketRedraw(board);
        }, { passive: true });
      }

      const preventSafariGestureZoom = (event) => event.preventDefault();
      scroll.addEventListener('gesturestart', preventSafariGestureZoom, { passive: false });
      scroll.addEventListener('gesturechange', preventSafariGestureZoom, { passive: false });

      setBracketZoom(component, initialZoom, null, false);
    });

    window.addEventListener('pagehide', () => {
      bracketComponents.forEach((component) => {
        const board = component.querySelector('[data-bracket-board]');
        if (board) persistBracketZoom(component, currentBracketZoom(board), true);
      });
    });

    bracketBoards.forEach((board) => {
      board.addEventListener('click', (event) => {
        const match = event.target.closest('[data-bracket-match]');
        if (match && board.contains(match)) {
          const selectedCode = normalizeMatchCode(board.dataset.selectedMatch);
          const matchCode = normalizeMatchCode(match.dataset.matchCode);
          if (selectedCode && selectedCode === matchCode) clearBracketSelection(board);
          else applyBracketSelection(board, match);
          return;
        }

        if (!event.target.closest('.tt-bracket-round')) clearBracketSelection(board, false);
      });

      board.addEventListener('keydown', (event) => {
        const match = event.target.closest('[data-bracket-match]');
        if (match && ['Enter', ' '].includes(event.key)) {
          event.preventDefault();
          const selectedCode = normalizeMatchCode(board.dataset.selectedMatch);
          const matchCode = normalizeMatchCode(match.dataset.matchCode);
          if (selectedCode && selectedCode === matchCode) clearBracketSelection(board);
          else applyBracketSelection(board, match);
          return;
        }

        if (event.key === 'Escape' && board.classList.contains('has-bracket-selection')) {
          event.preventDefault();
          clearBracketSelection(board);
        }
      });
    });

    redrawBrackets();
    window.addEventListener('load', redrawBrackets, { once: true });
    window.addEventListener('resize', redrawBrackets, { passive: true });
    window.addEventListener('scroll', redrawBrackets, { passive: true });

    if (document.fonts?.ready) {
      document.fonts.ready.then(redrawBrackets).catch(() => {});
    }

    if ('ResizeObserver' in window) {
      const bracketResizeObserver = new ResizeObserver((entries) => {
        entries.forEach((entry) => redrawBracket(entry.target));
      });
      bracketBoards.forEach((board) => bracketResizeObserver.observe(board));
    }
  }

})();
