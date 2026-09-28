(() => {
  const body = document.body;
  const navToggle = document.querySelector('[data-nav-toggle]');
  const sidebarCompactToggle = document.querySelector('[data-sidebar-compact-toggle]');
  const accountMenu = document.querySelector('[data-account-menu]');
  const accountToggle = accountMenu?.querySelector('[data-account-toggle]');
  const accountDropdown = accountMenu?.querySelector('[data-account-dropdown]');
  const confirmDialog = document.querySelector('[data-confirm-dialog]');
  const confirmTitle = confirmDialog?.querySelector('[data-confirm-title]');
  const confirmMessage = confirmDialog?.querySelector('[data-confirm-message]');
  const confirmCancel = confirmDialog?.querySelector('[data-confirm-cancel]');
  const confirmProceed = confirmDialog?.querySelector('[data-confirm-proceed]');
  const confirmIconUse = confirmDialog?.querySelector('.confirmation-icon use');
  const confirmIconBase = confirmIconUse?.getAttribute('href')?.split('#')[0] || '';

  let lastDialogTrigger = null;
  let pendingConfirmForm = null;
  let pendingConfirmSubmitter = null;


  const dismissToast = (toast) => {
    if (!toast || toast.dataset.dismissing === '1') return;
    toast.dataset.dismissing = '1';
    toast.classList.add('is-dismissing');
    window.setTimeout(() => {
      toast.remove();
      const stack = document.querySelector('[data-toast-stack]');
      if (stack && !stack.querySelector('[data-toast]')) stack.remove();
    }, 220);
  };

  document.querySelectorAll('[data-toast]').forEach((toast) => {
    const delay = Number.parseInt(toast.dataset.dismissAfter || '5000', 10);
    const timeout = Number.isFinite(delay) && delay >= 0 ? delay : 5000;
    if (timeout > 0) window.setTimeout(() => dismissToast(toast), timeout);
  });

  document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-toast-close]');
    if (close) dismissToast(close.closest('[data-toast]'));
  });

  const scoreboardPresentationFrame = body.matches('[data-scoreboard-presentation-frame]');
  const scoreboardPresentationStage = document.querySelector('[data-scoreboard-presentation-stage]');
  const scoreboardPresentButton = document.querySelector('[data-scoreboard-present]');
  const scoreboardExitPresentationButton = document.querySelector('[data-scoreboard-exit-presentation]');
  const scoreboardPresentationIframe = document.querySelector('[data-scoreboard-presentation-iframe]');
  const SCOREBOARD_PRESENTATION_KEY = 'tallytech.scoreboardPresentation.v1';
  let scoreboardPresentationActive = false;
  let scoreboardHadNativeFullscreen = false;
  let scoreboardClosingPresentation = false;

  const readScoreboardPresentationPreference = () => {
    try {
      return window.localStorage.getItem(SCOREBOARD_PRESENTATION_KEY) === '1';
    } catch (_) {
      return false;
    }
  };

  const persistScoreboardPresentationPreference = (enabled) => {
    try {
      window.localStorage.setItem(SCOREBOARD_PRESENTATION_KEY, enabled ? '1' : '0');
    } catch (_) {
      // Presentation mode remains available for the current page without storage.
    }
  };

  const scoreboardFullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement || null;

  const isScoreboardMobileScreen = () => {
    const coarsePointer = typeof window.matchMedia === 'function'
      && window.matchMedia('(pointer: coarse)').matches;
    const narrowScreen = Math.min(window.screen?.width || window.innerWidth, window.screen?.height || window.innerHeight) <= 900;
    return coarsePointer || narrowScreen;
  };

  const lockScoreboardLandscape = async () => {
    if (!isScoreboardMobileScreen()) return false;

    const orientation = window.screen?.orientation;
    if (orientation && typeof orientation.lock === 'function') {
      try {
        await orientation.lock('landscape');
        return true;
      } catch (_) {
        // Some mobile browsers only allow orientation lock while native fullscreen is active.
      }
    }

    const legacyLock = window.screen?.lockOrientation
      || window.screen?.mozLockOrientation
      || window.screen?.msLockOrientation;
    if (typeof legacyLock === 'function') {
      try {
        return legacyLock.call(window.screen, 'landscape') !== false;
      } catch (_) {
        return false;
      }
    }

    return false;
  };

  const unlockScoreboardOrientation = () => {
    const orientation = window.screen?.orientation;
    if (orientation && typeof orientation.unlock === 'function') {
      try {
        orientation.unlock();
        return;
      } catch (_) {
        // Fall through to legacy orientation APIs when available.
      }
    }

    const legacyUnlock = window.screen?.unlockOrientation
      || window.screen?.mozUnlockOrientation
      || window.screen?.msUnlockOrientation;
    if (typeof legacyUnlock === 'function') {
      try {
        legacyUnlock.call(window.screen);
      } catch (_) {
        // Exiting presentation still succeeds if the browser owns orientation.
      }
    }
  };

  const requestScoreboardFullscreen = async () => {
    if (scoreboardFullscreenElement()) {
      scoreboardHadNativeFullscreen = true;
      await lockScoreboardLandscape();
      return true;
    }

    const root = document.documentElement;
    const request = root.requestFullscreen || root.webkitRequestFullscreen;
    if (typeof request !== 'function') {
      await lockScoreboardLandscape();
      return false;
    }

    try {
      await request.call(root);
      scoreboardHadNativeFullscreen = true;
      await lockScoreboardLandscape();
      return true;
    } catch (_) {
      await lockScoreboardLandscape();
      return false;
    }
  };

  const exitScoreboardFullscreen = async () => {
    const exit = document.exitFullscreen || document.webkitExitFullscreen;
    if (!scoreboardFullscreenElement() || typeof exit !== 'function') return;
    try {
      await exit.call(document);
    } catch (_) {
      // The presentation shell is still closed even if the browser rejects exitFullscreen().
    }
  };

  const buildScoreboardPresentationUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('presentation', '1');
    url.hash = '';
    return url.toString();
  };

  const announceScoreboardPresentationChange = () => {
    document.dispatchEvent(new CustomEvent('scoreboard:presentationchange', {
      detail: { active: scoreboardPresentationActive },
    }));
  };

  const showScoreboardPresentation = ({ requestFullscreen = false, persist = true } = {}) => {
    if (scoreboardPresentationFrame || !scoreboardPresentationStage || !scoreboardPresentationIframe) return;

    scoreboardPresentationActive = true;
    body.classList.add('is-scoreboard-presentation');
    scoreboardPresentationStage.hidden = false;
    if (!scoreboardPresentationIframe.src) scoreboardPresentationIframe.src = buildScoreboardPresentationUrl();
    if (persist) persistScoreboardPresentationPreference(true);
    announceScoreboardPresentationChange();

    if (requestFullscreen) {
      requestScoreboardFullscreen();
    } else {
      lockScoreboardLandscape();
    }
  };

  const hideScoreboardPresentation = ({ persist = true, exitFullscreen = true } = {}) => {
    if (scoreboardPresentationFrame || !scoreboardPresentationStage) return;

    scoreboardClosingPresentation = true;
    scoreboardPresentationActive = false;
    body.classList.remove('is-scoreboard-presentation');
    scoreboardPresentationStage.hidden = true;
    if (scoreboardPresentationIframe) scoreboardPresentationIframe.removeAttribute('src');
    if (persist) persistScoreboardPresentationPreference(false);
    announceScoreboardPresentationChange();
    unlockScoreboardOrientation();

    if (exitFullscreen) exitScoreboardFullscreen().finally(() => {
      scoreboardClosingPresentation = false;
      scoreboardHadNativeFullscreen = false;
    });
    else {
      scoreboardClosingPresentation = false;
      scoreboardHadNativeFullscreen = false;
    }
  };

  if (!scoreboardPresentationFrame && scoreboardPresentationStage) {
    scoreboardPresentButton?.addEventListener('click', () => showScoreboardPresentation({ requestFullscreen: true }));
    scoreboardExitPresentationButton?.addEventListener('click', () => hideScoreboardPresentation());

    const syncScoreboardFullscreenState = () => {
      const isFullscreen = Boolean(scoreboardFullscreenElement());
      if (isFullscreen) {
        scoreboardHadNativeFullscreen = true;
        if (scoreboardPresentationActive) lockScoreboardLandscape();
        return;
      }

      if (scoreboardPresentationActive && scoreboardHadNativeFullscreen && !scoreboardClosingPresentation) {
        hideScoreboardPresentation({ exitFullscreen: false });
        return;
      }
    };

    document.addEventListener('fullscreenchange', syncScoreboardFullscreenState);
    document.addEventListener('webkitfullscreenchange', syncScoreboardFullscreenState);
    scoreboardPresentationIframe?.addEventListener('load', () => {
      if (scoreboardPresentationActive) lockScoreboardLandscape();
    });

    if (readScoreboardPresentationPreference()) {
      showScoreboardPresentation({ requestFullscreen: true, persist: false });
    }
  }

  const scoreboardRefreshMeta = document.querySelector('[data-scoreboard-refresh]');
  if (scoreboardRefreshMeta) {
    const configuredSeconds = Number.parseInt(scoreboardRefreshMeta.getAttribute('content') || '30', 10);
    const refreshMs = (Number.isFinite(configuredSeconds) && configuredSeconds >= 5 ? configuredSeconds : 30) * 1000;
    const scheduleScoreboardRefresh = () => {
      window.setTimeout(() => {
        if (document.visibilityState === 'visible' && !scoreboardPresentationActive) {
          window.location.reload();
          return;
        }
        scheduleScoreboardRefresh();
      }, refreshMs);
    };
    scheduleScoreboardRefresh();
  }

  const scoreboardSportNav = document.querySelector('[data-scoreboard-sport-nav]');
  if (scoreboardSportNav) {
    const sportLinks = Array.from(scoreboardSportNav.querySelectorAll('[data-scoreboard-sport-link]'));
    const activeSportLink = sportLinks.find((link) => link.matches('[aria-current="page"], .active')) || sportLinks[0];
    const overallSelected = scoreboardSportNav.dataset.scoreboardOverall === 'true';
    if (overallSelected) {
      scoreboardSportNav.scrollLeft = 0;
    } else if (activeSportLink && scoreboardSportNav.scrollWidth > scoreboardSportNav.clientWidth) {
      const navRect = scoreboardSportNav.getBoundingClientRect();
      const linkRect = activeSportLink.getBoundingClientRect();
      const targetLeft = scoreboardSportNav.scrollLeft + (linkRect.left - navRect.left) - Math.max(0, (navRect.width - linkRect.width) / 2);
      scoreboardSportNav.scrollLeft = Math.max(0, targetLeft);
    }
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
    let autoRotateEnabled = false;

    try {
      const storedAutoRotate = window.localStorage.getItem(SCOREBOARD_AUTO_ROTATE_KEY);
      if (storedAutoRotate !== null) autoRotateEnabled = storedAutoRotate !== '0';
    } catch (_) {
      // First-visit auto rotation remains off when browser storage is unavailable.
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

      if (rotationLabel) rotationLabel.textContent = 'Next view in';
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
      if (!autoRotateEnabled || sportLinks.length < 2 || document.visibilityState === 'hidden' || scoreboardPresentationActive) {
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
    document.addEventListener('scoreboard:presentationchange', scheduleSportRotation);
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

  const confirmationPresentation = (message, submitter) => {
    const actionLabel = submitter?.textContent?.trim() || 'Confirm';
    const action = actionLabel.toLowerCase();
    const normalized = `${actionLabel} ${message}`.toLowerCase();

    if (/\b(delete|remove|deactivate|disable)\b/.test(action) || /\b(delete|remove|deactivate|disable)\b/.test(normalized)) {
      return { title: 'Confirm removal', tone: 'danger', actionLabel, icon: 'trash' };
    }
    if (/\b(log out|logout)\b/.test(action) || /\b(log out|logout)\b/.test(normalized)) {
      return { title: 'Confirm logout', tone: 'danger', actionLabel, icon: 'log-out' };
    }
    if (/\bvalidate\b/.test(action)) {
      return { title: 'Confirm validation', tone: 'success', actionLabel, icon: 'check-circle' };
    }
    if (/\b(activate|enable)\b/.test(action)) {
      return { title: 'Confirm activation', tone: 'success', actionLabel, icon: 'power' };
    }
    if (/\bgenerate\b/.test(action)) {
      return { title: 'Confirm generation', tone: 'warning', actionLabel, icon: 'play-circle' };
    }
    if (/\bsubmit\b/.test(action)) {
      return { title: 'Confirm submission', tone: 'default', actionLabel, icon: 'clipboard-check' };
    }
    if (/\b(save|change|update)\b/.test(action)) {
      return { title: 'Confirm changes', tone: 'default', actionLabel, icon: 'save' };
    }

    if (/\bvalidate\b/.test(normalized)) {
      return { title: 'Confirm validation', tone: 'success', actionLabel, icon: 'check-circle' };
    }
    if (/\b(activate|enable)\b/.test(normalized)) {
      return { title: 'Confirm activation', tone: 'success', actionLabel, icon: 'power' };
    }
    if (/\bgenerate\b/.test(normalized)) {
      return { title: 'Confirm generation', tone: 'warning', actionLabel, icon: 'play-circle' };
    }

    return { title: 'Confirm action', tone: 'default', actionLabel, icon: 'alert-triangle' };
  };

  const clearPendingConfirmation = () => {
    pendingConfirmForm = null;
    pendingConfirmSubmitter = null;
    if (confirmTitle) confirmTitle.textContent = 'Confirm action';
    if (confirmMessage) confirmMessage.textContent = 'Are you sure you want to continue?';
    if (confirmProceed) confirmProceed.textContent = 'Confirm';
    if (confirmIconUse && confirmIconBase) confirmIconUse.setAttribute('href', `${confirmIconBase}#alert-triangle`);
    if (confirmDialog) confirmDialog.dataset.confirmTone = 'default';
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
      if (event.target !== dialog) return;
      if (openDatePickerState?.contentHost === dialog && openDatePickerState.mode === 'sheet') {
        closeDatePicker(openDatePickerState, true);
        return;
      }
      dialog.close();
    });

    dialog.addEventListener('cancel', (event) => {
      if (openDatePickerState?.contentHost === dialog) {
        event.preventDefault();
        closeDatePicker(openDatePickerState, true);
      }
    });

    dialog.addEventListener('close', () => {
      if (openDatePickerState?.contentHost === dialog) closeDatePicker(openDatePickerState);
      if (dialog === confirmDialog) return;
      if (lastDialogTrigger?.isConnected) lastDialogTrigger.focus();
      lastDialogTrigger = null;
    });
  });

  const selectStates = new Map();
  let openSelectState = null;
  let openDatePickerState = null;
  let sleekSelectIdCounter = 0;

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
    if (restoreFocus && state.trigger.isConnected) state.trigger.focus();
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
    const maxPanelWidth = Math.min(420, Math.max(1, window.innerWidth - (viewportGap * 2)));
    const minimumPanelWidth = Math.min(maxPanelWidth, Math.max(180, rect.width));

    state.content.style.setProperty('--select-width', `${minimumPanelWidth}px`);
    state.content.style.maxHeight = `${available}px`;
    state.content.style.position = 'fixed';
    state.content.style.top = openAbove ? 'auto' : `${rect.bottom + 6}px`;
    state.content.style.bottom = openAbove ? `${window.innerHeight - rect.top + 6}px` : 'auto';

    const measuredWidth = Math.min(maxPanelWidth, Math.max(minimumPanelWidth, state.content.getBoundingClientRect().width));
    const panelLeft = Math.max(viewportGap, Math.min(rect.left, window.innerWidth - measuredWidth - viewportGap));
    state.content.style.left = `${panelLeft}px`;
  };

  const refreshSleekSelect = (state) => {
    const { select, trigger, content } = state;
    const selected = select.options[select.selectedIndex];
    const triggerText = selected?.textContent?.trim() || 'Select an option';
    const isPlaceholder = Boolean(selected && selected.value === '');
    trigger.textContent = triggerText;
    trigger.title = triggerText;
    trigger.disabled = select.disabled;
    trigger.classList.toggle('is-placeholder', isPlaceholder);
    trigger.setAttribute('aria-invalid', select.matches(':invalid') ? 'true' : 'false');
    content.innerHTML = '';

    Array.from(select.options).forEach((option, index) => {
      const item = document.createElement('button');
      const optionIsPlaceholder = option.value === '';
      item.type = 'button';
      item.className = 'sleek-select-option';
      item.dataset.selectIndex = String(index);
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
      item.disabled = option.disabled;
      item.textContent = option.textContent;
      item.classList.toggle('is-selected', option.selected);
      item.classList.toggle('is-placeholder', optionIsPlaceholder);
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
    if (openDatePickerState) closeDatePicker(openDatePickerState);
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

  const destroySleekSelect = (state) => {
    if (!state) return;
    if (openSelectState === state) openSelectState = null;
    state.open = false;
    state.observer?.disconnect();
    if (state.form && state.resetHandler) state.form.removeEventListener('reset', state.resetHandler);
    state.content.remove();
    state.trigger.remove();
    state.select.classList.remove('sleek-select-native');
    delete state.select.dataset.sleekSelectReady;
    if (state.hadTabIndex) state.select.setAttribute('tabindex', state.originalTabIndex);
    else state.select.removeAttribute('tabindex');
    if (state.wrapper.contains(state.select)) state.wrapper.replaceWith(state.select);
    else state.wrapper.remove();
    selectStates.delete(state.select);
  };

  const initSleekSelect = (select) => {
    if (!(select instanceof HTMLSelectElement) || select.multiple || select.dataset.sleekSelectReady === '1') return;
    select.dataset.sleekSelectReady = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'sleek-select';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    const hadTabIndex = select.hasAttribute('tabindex');
    const originalTabIndex = select.getAttribute('tabindex') || '';
    select.classList.add('sleek-select-native');
    select.tabIndex = -1;

    const contentId = `sleek-select-content-${++sleekSelectIdCounter}`;
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'sleek-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', contentId);
    wrapper.appendChild(trigger);

    const content = document.createElement('div');
    content.id = contentId;
    content.className = 'sleek-select-content';
    content.setAttribute('role', 'listbox');
    content.hidden = true;
    if (supportsPopover) content.setAttribute('popover', 'manual');
    const contentHost = select.closest('dialog') || document.body;
    contentHost.appendChild(content);

    const state = {
      select,
      wrapper,
      trigger,
      content,
      open: false,
      observer: null,
      form: select.form,
      resetHandler: null,
      hadTabIndex,
      originalTabIndex,
    };
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
      if (!options.length) return;

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

    if (state.form) {
      state.resetHandler = () => requestAnimationFrame(() => refreshSleekSelect(state));
      state.form.addEventListener('reset', state.resetHandler);
    }

    state.observer = new MutationObserver(() => refreshSleekSelect(state));
    state.observer.observe(select, { attributes: true, childList: true, subtree: true });
  };

  const upgradeSleekSelects = (root) => {
    if (root instanceof HTMLSelectElement) initSleekSelect(root);
    root?.querySelectorAll?.('select').forEach(initSleekSelect);
  };


  const datePickerStates = new Map();
  let datePickerIdCounter = 0;

  const datePickerSelector = 'input[type="date"]:not([data-native-picker]), input[type="datetime-local"]:not([data-native-picker]), input[type="time"]:not([data-native-picker])';
  const datePickerMonthFormatter = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' });
  const datePickerDateFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  const datePickerFullDateFormatter = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  const datePickerMonthNames = Array.from({ length: 12 }, (_, month) => new Intl.DateTimeFormat(undefined, { month: 'short' }).format(new Date(2024, month, 1)));
  const datePickerWeekdays = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
  const DATE_PICKER_BREAKPOINTS = Object.freeze({
    mobileMaxWidth: 640,
    desktopMinWidth: 1025,
    shortViewportHeight: 500,
  });
  const DATE_PICKER_VIEWPORT_GAP = 10;
  const DATE_PICKER_ANCHOR_GAP = 6;

  const padDatePickerNumber = (value) => String(value).padStart(2, '0');
  const datePickerPartsToDate = (parts) => new Date(parts.year, parts.month - 1, parts.day);
  const datePickerDateKey = (parts) => `${String(parts.year).padStart(4, '0')}-${padDatePickerNumber(parts.month)}-${padDatePickerNumber(parts.day)}`;
  const datePickerTimeKey = (parts) => `${padDatePickerNumber(parts.hour)}:${padDatePickerNumber(parts.minute)}`;
  const datePickerDateTimeKey = (parts) => `${datePickerDateKey(parts)}T${datePickerTimeKey(parts)}`;
  const datePickerTodayParts = () => {
    const now = new Date();
    return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate(), hour: now.getHours(), minute: now.getMinutes() };
  };
  const parseDatePickerValue = (type, value) => {
    const source = String(value || '').trim();
    if (!source) return null;
    if (type === 'date') {
      const match = /^(\d{4,})-(\d{2})-(\d{2})$/.exec(source);
      if (!match) return null;
      return { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]), hour: 0, minute: 0 };
    }
    if (type === 'time') {
      const match = /^(\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/.exec(source);
      if (!match) return null;
      const today = datePickerTodayParts();
      return { ...today, hour: Number(match[1]), minute: Number(match[2]) };
    }
    const match = /^(\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/.exec(source);
    if (!match) return null;
    return { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]), hour: Number(match[4]), minute: Number(match[5]) };
  };
  const serializeDatePickerValue = (type, parts) => {
    if (!parts) return '';
    if (type === 'date') return datePickerDateKey(parts);
    if (type === 'time') return datePickerTimeKey(parts);
    return datePickerDateTimeKey(parts);
  };
  const compareDatePickerParts = (a, b) => datePickerDateKey(a).localeCompare(datePickerDateKey(b));
  const addDatePickerDays = (parts, amount) => {
    const date = datePickerPartsToDate(parts);
    date.setDate(date.getDate() + amount);
    return { ...parts, year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
  };
  const addDatePickerMonths = (parts, amount) => {
    const day = parts.day;
    const date = new Date(parts.year, parts.month - 1 + amount, 1);
    const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    return { ...parts, year: date.getFullYear(), month: date.getMonth() + 1, day: Math.min(day, lastDay) };
  };
  const getDatePickerIconBase = () => document.querySelector('.ui-icon use[href*="#"]')?.getAttribute('href')?.split('#')[0] || 'assets/icons/ui.svg';
  const makeDatePickerIcon = (name, className = 'ui-icon') => {
    const namespace = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(namespace, 'svg');
    const use = document.createElementNS(namespace, 'use');
    svg.setAttribute('class', className);
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    use.setAttribute('href', `${getDatePickerIconBase()}#${name}`);
    svg.appendChild(use);
    return svg;
  };
  const getDatePickerLabel = (input) => {
    const explicit = input.labels?.[0];
    const wrapperLabel = input.closest('label');
    const label = explicit || wrapperLabel;
    if (!label) return input.getAttribute('aria-label') || input.name || 'Date';
    const clone = label.cloneNode(true);
    clone.querySelectorAll('input, select, textarea, button, .sleek-select, .date-picker').forEach((node) => node.remove());
    return clone.textContent.replace(/\s+/g, ' ').trim() || input.getAttribute('aria-label') || input.name || 'Date';
  };
  const getLinkedDatePickerStart = (input) => {
    if (!input.form) return null;
    if (input.type === 'date' && input.name === 'end_date') return input.form.elements.namedItem('start_date');
    if (input.type === 'date' && input.name === 'to') return input.form.elements.namedItem('from');
    return null;
  };
  const effectiveDatePickerMin = (state) => {
    const ownMin = state.input.min || '';
    const linkedMin = state.linkedStart?.value || '';
    if (!linkedMin) return ownMin;
    if (!ownMin) return linkedMin;
    return ownMin.localeCompare(linkedMin) >= 0 ? ownMin : linkedMin;
  };
  const datePickerConstraintParts = (state, kind) => parseDatePickerValue(state.type, kind === 'min' ? effectiveDatePickerMin(state) : state.input.max);
  const datePickerStepSeconds = (state) => {
    if (state.input.step === 'any') return null;
    const raw = Number(state.input.step || (state.type === 'date' ? 1 : 60));
    return Number.isFinite(raw) && raw > 0 ? raw : (state.type === 'date' ? 1 : 60);
  };
  const datePickerStepMatches = (state, parts) => {
    const step = datePickerStepSeconds(state);
    if (step === null) return true;
    if (state.type === 'date') {
      const base = datePickerConstraintParts(state, 'min') || { year: 1970, month: 1, day: 1, hour: 0, minute: 0 };
      const candidateDays = Math.trunc(Date.UTC(parts.year, parts.month - 1, parts.day) / 86400000);
      const baseDays = Math.trunc(Date.UTC(base.year, base.month - 1, base.day) / 86400000);
      return Math.abs((candidateDays - baseDays) % step) < 1e-9;
    }
    if (state.type === 'time') {
      const base = datePickerConstraintParts(state, 'min') || { hour: 0, minute: 0 };
      const candidateSeconds = (parts.hour * 3600) + (parts.minute * 60);
      const baseSeconds = (base.hour * 3600) + (base.minute * 60);
      return Math.abs((candidateSeconds - baseSeconds) % step) < 1e-9;
    }
    const base = datePickerConstraintParts(state, 'min') || { year: 1970, month: 1, day: 1, hour: 0, minute: 0 };
    const candidateSeconds = Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute) / 1000;
    const baseSeconds = Date.UTC(base.year, base.month - 1, base.day, base.hour, base.minute) / 1000;
    return Math.abs((candidateSeconds - baseSeconds) % step) < 1e-9;
  };
  const datePickerWithinBounds = (state, parts) => {
    const value = serializeDatePickerValue(state.type, parts);
    const min = effectiveDatePickerMin(state);
    const max = state.input.max || '';
    if (state.type === 'time' && min && max && min > max) {
      if (!(value >= min || value <= max)) return false;
    } else {
      if (min && value < min) return false;
      if (max && value > max) return false;
    }
    return true;
  };
  const datePickerCandidateAllowed = (state, parts) => datePickerWithinBounds(state, parts) && datePickerStepMatches(state, parts);
  const datePickerDayAllowed = (state, parts) => {
    if (state.type === 'time') return false;
    const dayKey = datePickerDateKey(parts);
    const min = effectiveDatePickerMin(state);
    const max = state.input.max || '';
    const minDay = min ? min.slice(0, 10) : '';
    const maxDay = max ? max.slice(0, 10) : '';
    if (minDay && dayKey < minDay) return false;
    if (maxDay && dayKey > maxDay) return false;
    if (state.type === 'date') return datePickerStepMatches(state, parts);
    for (let hour = 0; hour < 24; hour += 1) {
      for (let minute = 0; minute < 60; minute += 1) {
        if (datePickerCandidateAllowed(state, { ...parts, hour, minute })) return true;
      }
    }
    return false;
  };
  const datePickerMonthHasAllowedDay = (state, year, month) => {
    const days = new Date(year, month, 0).getDate();
    for (let day = 1; day <= days; day += 1) {
      if (datePickerDayAllowed(state, { year, month, day, hour: state.draft.hour, minute: state.draft.minute })) return true;
    }
    return false;
  };
  const datePickerHour12 = (hour24) => {
    const hour = hour24 % 12;
    return hour === 0 ? 12 : hour;
  };
  const datePickerPeriod = (hour24) => hour24 >= 12 ? 'PM' : 'AM';
  const datePickerHour24 = (hour12, period) => {
    const normalized = hour12 % 12;
    return period === 'PM' ? normalized + 12 : normalized;
  };
  const formatDatePickerTrigger = (state) => {
    const parsed = parseDatePickerValue(state.type, state.input.value);
    if (!parsed) return state.type === 'date' ? 'Select date' : state.type === 'time' ? 'Select time' : 'Select date & time';
    if (state.type === 'time') {
      return `${datePickerHour12(parsed.hour)}:${padDatePickerNumber(parsed.minute)} ${datePickerPeriod(parsed.hour)}`;
    }
    const dateText = datePickerDateFormatter.format(datePickerPartsToDate(parsed));
    if (state.type === 'date') return dateText;
    return `${dateText} · ${datePickerHour12(parsed.hour)}:${padDatePickerNumber(parsed.minute)} ${datePickerPeriod(parsed.hour)}`;
  };
  const syncDatePickerTrigger = (state) => {
    const text = formatDatePickerTrigger(state);
    state.valueNode.textContent = text;
    state.trigger.title = text;
    state.trigger.disabled = state.input.disabled;
    state.trigger.classList.toggle('is-placeholder', !state.input.value);
    state.trigger.classList.toggle('is-readonly', state.input.readOnly);
    state.trigger.setAttribute('aria-readonly', state.input.readOnly ? 'true' : 'false');
    state.trigger.setAttribute('aria-disabled', state.input.disabled || state.input.readOnly ? 'true' : 'false');
    state.trigger.setAttribute('aria-invalid', state.input.matches(':invalid') ? 'true' : 'false');
  };
  const commitDatePickerValue = (state, value, closeAfter = false) => {
    if (state.input.readOnly || state.input.disabled) return;
    state.input.value = value;
    state.input.dispatchEvent(new Event('input', { bubbles: true }));
    state.input.dispatchEvent(new Event('change', { bubbles: true }));
    syncDatePickerTrigger(state);
    if (closeAfter) closeDatePicker(state, true);
  };
  const setDatePickerDraftFromInput = (state) => {
    const parsed = parseDatePickerValue(state.type, state.input.value);
    const fallback = datePickerTodayParts();
    state.draft = parsed || fallback;
    state.draftHasValue = Boolean(parsed);
    state.viewYear = state.draft.year;
    state.viewMonth = state.draft.month;
    state.focusDate = { ...state.draft };
    state.jumpCenterYear = state.viewYear;
  };
  const clampDatePickerFocus = (state, parts) => {
    let candidate = { ...parts };
    const min = datePickerConstraintParts(state, 'min');
    const max = datePickerConstraintParts(state, 'max');
    if (min && compareDatePickerParts(candidate, min) < 0) candidate = { ...candidate, year: min.year, month: min.month, day: min.day };
    if (max && compareDatePickerParts(candidate, max) > 0) candidate = { ...candidate, year: max.year, month: max.month, day: max.day };
    return candidate;
  };
  const findAllowedDatePickerDay = (state, start, direction) => {
    let candidate = clampDatePickerFocus(state, start);
    for (let attempts = 0; attempts < 3700; attempts += 1) {
      if (datePickerDayAllowed(state, candidate)) return candidate;
      candidate = addDatePickerDays(candidate, direction);
      const clamped = clampDatePickerFocus(state, candidate);
      if (datePickerDateKey(clamped) !== datePickerDateKey(candidate)) return null;
    }
    return null;
  };
  const focusDatePickerDay = (state, parts) => {
    const target = findAllowedDatePickerDay(state, parts, 1) || findAllowedDatePickerDay(state, parts, -1) || parts;
    state.focusDate = target;
    state.viewYear = target.year;
    state.viewMonth = target.month;
    state.showJump = false;
    renderDatePicker(state);
    requestAnimationFrame(() => state.panel.querySelector(`[data-date-picker-day="${datePickerDateKey(target)}"]`)?.focus({ preventScroll: true }));
  };
  const renderDatePickerCalendar = (state) => {
    const calendar = state.calendar;
    if (!calendar) return;
    state.monthLabel.textContent = datePickerMonthFormatter.format(new Date(state.viewYear, state.viewMonth - 1, 1));
    state.calendarView.hidden = state.showJump;
    state.jumpView.hidden = !state.showJump;
    if (state.showJump) {
      renderDatePickerJump(state);
      return;
    }

    state.dayGrid.innerHTML = '';
    const first = new Date(state.viewYear, state.viewMonth - 1, 1);
    const gridStart = new Date(state.viewYear, state.viewMonth - 1, 1 - first.getDay());
    const todayKey = datePickerDateKey(datePickerTodayParts());
    const selectedKey = state.draftHasValue ? datePickerDateKey(state.draft) : '';
    const focusKey = datePickerDateKey(state.focusDate || state.draft);

    for (let index = 0; index < 42; index += 1) {
      const date = new Date(gridStart);
      date.setDate(gridStart.getDate() + index);
      const parts = { year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate(), hour: state.draft.hour, minute: state.draft.minute };
      const key = datePickerDateKey(parts);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-picker-day';
      button.dataset.datePickerDay = key;
      button.setAttribute('role', 'gridcell');
      button.setAttribute('aria-label', datePickerFullDateFormatter.format(date));
      button.setAttribute('aria-selected', key === selectedKey ? 'true' : 'false');
      button.textContent = String(parts.day);
      button.disabled = !datePickerDayAllowed(state, parts);
      button.tabIndex = key === focusKey && !button.disabled ? 0 : -1;
      button.classList.toggle('is-outside', parts.month !== state.viewMonth);
      button.classList.toggle('is-today', key === todayKey);
      button.classList.toggle('is-selected', key === selectedKey);
      state.dayGrid.appendChild(button);
    }

    if (!state.dayGrid.querySelector('[tabindex="0"]')) {
      const firstEnabled = state.dayGrid.querySelector('.date-picker-day:not(:disabled)');
      if (firstEnabled) firstEnabled.tabIndex = 0;
    }
  };
  const renderDatePickerJump = (state) => {
    const yearScrollTop = state.jumpYears.scrollTop;
    state.jumpYears.innerHTML = '';
    state.jumpMonths.innerHTML = '';
    const startYear = state.jumpCenterYear - 5;
    for (let offset = 0; offset < 11; offset += 1) {
      const year = startYear + offset;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-picker-jump-option';
      button.dataset.datePickerYear = String(year);
      button.textContent = String(year);
      button.classList.toggle('is-selected', year === state.viewYear);
      const min = datePickerConstraintParts(state, 'min');
      const max = datePickerConstraintParts(state, 'max');
      button.disabled = Boolean((min && year < min.year) || (max && year > max.year));
      state.jumpYears.appendChild(button);
    }
    state.jumpYearRange.textContent = `${startYear}–${startYear + 10}`;
    datePickerMonthNames.forEach((name, monthIndex) => {
      const month = monthIndex + 1;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-picker-jump-option';
      button.dataset.datePickerMonth = String(month);
      button.textContent = name;
      button.disabled = !datePickerMonthHasAllowedDay(state, state.viewYear, month);
      button.classList.toggle('is-selected', month === state.viewMonth);
      state.jumpMonths.appendChild(button);
    });
    state.jumpYears.scrollTop = yearScrollTop;
  };
  const hasAllowedDatePickerMinute = (state, hour24) => {
    for (let minute = 0; minute < 60; minute += 1) {
      if (datePickerCandidateAllowed(state, { ...state.draft, hour: hour24, minute })) return true;
    }
    return false;
  };
  const ensureDatePickerTimeOptions = (state) => {
    if (!state.timePanel || state.timeOptionsBuilt) return;

    for (let hour = 1; hour <= 12; hour += 1) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-picker-time-option';
      button.dataset.datePickerHour = String(hour);
      button.textContent = String(hour);
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', 'false');
      button.tabIndex = -1;
      state.hourList.appendChild(button);
    }

    for (let minute = 0; minute < 60; minute += 1) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-picker-time-option';
      button.dataset.datePickerMinute = String(minute);
      button.textContent = padDatePickerNumber(minute);
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', 'false');
      button.tabIndex = -1;
      state.minuteList.appendChild(button);
    }

    const empty = document.createElement('span');
    empty.className = 'date-picker-time-empty';
    empty.textContent = '—';
    empty.hidden = true;
    state.minuteList.appendChild(empty);
    state.minuteEmpty = empty;

    ['AM', 'PM'].forEach((itemPeriod) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-picker-time-option';
      button.dataset.datePickerPeriod = itemPeriod;
      button.textContent = itemPeriod;
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', 'false');
      button.tabIndex = -1;
      state.periodList.appendChild(button);
    });

    state.timeOptionsBuilt = true;
  };
  const scrollDatePickerListOptionIntoView = (list, option) => {
    if (!list || !option || option.hidden) return;
    const listRect = list.getBoundingClientRect();
    const optionRect = option.getBoundingClientRect();
    if (!listRect.height || !optionRect.height) return;

    if (optionRect.top < listRect.top) {
      list.scrollTop -= listRect.top - optionRect.top;
    } else if (optionRect.bottom > listRect.bottom) {
      list.scrollTop += optionRect.bottom - listRect.bottom;
    }
  };
  const scrollDatePickerSelectedTimeIntoView = (state) => {
    if (!state?.open || !state.timePanel || !state.draftHasValue) return;
    const period = datePickerPeriod(state.draft.hour);
    const hour12 = datePickerHour12(state.draft.hour);
    const targets = [
      [state.hourList, state.hourList.querySelector(`[data-date-picker-hour="${hour12}"]`)],
      [state.minuteList, state.minuteList.querySelector(`[data-date-picker-minute="${state.draft.minute}"]`)],
      [state.periodList, state.periodList.querySelector(`[data-date-picker-period="${period}"]`)],
    ];
    targets.forEach(([list, option]) => scrollDatePickerListOptionIntoView(list, option));
  };
  const queueDatePickerSelectedTimeScroll = (state) => {
    if (!state?.timePanel) return;
    requestAnimationFrame(() => scrollDatePickerSelectedTimeIntoView(state));
  };
  const renderDatePickerTime = (state) => {
    if (!state.timePanel) return;
    ensureDatePickerTimeOptions(state);

    const scrollPositions = {
      hour: state.hourList.scrollTop,
      minute: state.minuteList.scrollTop,
      period: state.periodList.scrollTop,
    };
    const period = datePickerPeriod(state.draft.hour);
    const hour12 = datePickerHour12(state.draft.hour);

    state.hourList.querySelectorAll('[data-date-picker-hour]').forEach((button) => {
      const hour = Number(button.dataset.datePickerHour);
      const hour24 = datePickerHour24(hour, period);
      const hourSelected = hour === hour12 && state.draftHasValue;
      button.setAttribute('aria-selected', hourSelected ? 'true' : 'false');
      button.classList.toggle('is-selected', hourSelected);
      button.disabled = !hasAllowedDatePickerMinute(state, hour24);
      button.tabIndex = hourSelected && !button.disabled ? 0 : -1;
    });

    let renderedMinutes = 0;
    state.minuteList.querySelectorAll('[data-date-picker-minute]').forEach((button) => {
      const minute = Number(button.dataset.datePickerMinute);
      const candidate = { ...state.draft, minute };
      const stepMatches = datePickerStepMatches(state, candidate);
      button.hidden = !stepMatches;
      if (!stepMatches) {
        button.setAttribute('aria-selected', 'false');
        button.classList.remove('is-selected');
        button.disabled = true;
        button.tabIndex = -1;
        return;
      }

      renderedMinutes += 1;
      const minuteSelected = minute === state.draft.minute && state.draftHasValue;
      button.setAttribute('aria-selected', minuteSelected ? 'true' : 'false');
      button.classList.toggle('is-selected', minuteSelected);
      button.disabled = !datePickerWithinBounds(state, candidate);
      button.tabIndex = minuteSelected && !button.disabled ? 0 : -1;
    });
    if (state.minuteEmpty) state.minuteEmpty.hidden = renderedMinutes > 0;

    state.periodList.querySelectorAll('[data-date-picker-period]').forEach((button) => {
      const itemPeriod = button.dataset.datePickerPeriod;
      const hour24 = datePickerHour24(hour12, itemPeriod);
      const periodSelected = itemPeriod === period && state.draftHasValue;
      button.setAttribute('aria-selected', periodSelected ? 'true' : 'false');
      button.classList.toggle('is-selected', periodSelected);
      button.disabled = !hasAllowedDatePickerMinute(state, hour24);
      button.tabIndex = periodSelected && !button.disabled ? 0 : -1;
    });

    [state.hourList, state.minuteList, state.periodList].forEach((list) => {
      if (!list.querySelector('.date-picker-time-option[tabindex="0"]:not([hidden])')) {
        const firstEnabled = list.querySelector('.date-picker-time-option:not([hidden]):not(:disabled)');
        if (firstEnabled) firstEnabled.tabIndex = 0;
      }
    });

    state.hourList.scrollTop = scrollPositions.hour;
    state.minuteList.scrollTop = scrollPositions.minute;
    state.periodList.scrollTop = scrollPositions.period;

    const done = state.panel.querySelector('[data-date-picker-done]');
    if (done) done.disabled = !state.draftHasValue || !datePickerCandidateAllowed(state, state.draft);
  };
  const renderDatePicker = (state) => {
    if (state.type !== 'time') renderDatePickerCalendar(state);
    if (state.type !== 'date') renderDatePickerTime(state);
    const done = state.panel.querySelector('[data-date-picker-done]');
    if (done) done.disabled = !state.draftHasValue || !datePickerCandidateAllowed(state, state.draft);
    syncDatePickerTrigger(state);
    if (state.open) positionDatePicker(state);
  };
  const getDatePickerViewportRect = () => {
    const viewport = window.visualViewport;
    const left = viewport?.offsetLeft || 0;
    const top = viewport?.offsetTop || 0;
    const width = viewport?.width || window.innerWidth;
    const height = viewport?.height || window.innerHeight;
    return { left, top, right: left + width, bottom: top + height, width, height };
  };
  const getDatePickerBoundaryRect = (state, inset = 0) => {
    const viewport = getDatePickerViewportRect();
    const hostRect = state.contentHost instanceof HTMLDialogElement && state.contentHost.open
      ? state.contentHost.getBoundingClientRect()
      : null;
    const rawLeft = hostRect ? Math.max(viewport.left, hostRect.left) : viewport.left;
    const rawTop = hostRect ? Math.max(viewport.top, hostRect.top) : viewport.top;
    const rawRight = hostRect ? Math.min(viewport.right, hostRect.right) : viewport.right;
    const rawBottom = hostRect ? Math.min(viewport.bottom, hostRect.bottom) : viewport.bottom;
    const left = Math.min(rawRight, rawLeft + inset);
    const top = Math.min(rawBottom, rawTop + inset);
    const right = Math.max(left, rawRight - inset);
    const bottom = Math.max(top, rawBottom - inset);
    return {
      left,
      top,
      right,
      bottom,
      width: Math.max(1, right - left),
      height: Math.max(1, bottom - top),
      viewport,
      hostRect,
    };
  };
  const getDatePickerResponsiveContext = () => {
    const viewport = getDatePickerViewportRect();
    const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
    const finePointer = window.matchMedia('(pointer: fine)').matches;
    const shortViewport = viewport.height < DATE_PICKER_BREAKPOINTS.shortViewportHeight;
    const mobile = viewport.width <= DATE_PICKER_BREAKPOINTS.mobileMaxWidth || shortViewport;
    const desktop = !mobile && viewport.width >= DATE_PICKER_BREAKPOINTS.desktopMinWidth && finePointer && !coarsePointer;
    return {
      viewport,
      coarsePointer,
      finePointer,
      shortViewport,
      mobile,
      desktop,
      tablet: !mobile && !desktop,
      landscape: viewport.width > viewport.height,
    };
  };
  const setDatePickerScrollLock = (state, locked) => {
    if (!state || state.scrollLocked === locked) return;
    state.scrollLocked = locked;
    if (state.contentHost instanceof HTMLDialogElement) {
      state.contentHost.classList.toggle('date-picker-scroll-lock', locked);
      return;
    }
    document.documentElement.classList.toggle('date-picker-scroll-lock', locked);
    document.body.classList.toggle('date-picker-scroll-lock', locked);
  };
  const syncDatePickerModeClasses = (state, mode, context, stacked) => {
    const previousLayout = `${state.mode || ''}:${state.layout || ''}:${state.orientation || ''}`;
    state.mode = mode;
    state.layout = stacked ? 'stacked' : 'side-by-side';
    state.orientation = context.landscape ? 'landscape' : 'portrait';
    state.panel.classList.toggle('is-mode-anchored', mode === 'anchored');
    state.panel.classList.toggle('is-mode-sheet', mode === 'sheet');
    state.panel.classList.toggle('is-mobile', context.mobile);
    state.panel.classList.toggle('is-tablet', context.tablet);
    state.panel.classList.toggle('is-desktop', context.desktop);
    state.panel.classList.toggle('is-landscape', context.landscape);
    state.panel.classList.toggle('is-portrait', !context.landscape);
    state.panel.classList.toggle('is-coarse', context.coarsePointer);
    state.panel.classList.toggle('is-stacked', Boolean(stacked));
    const nextLayout = `${state.mode}:${state.layout}:${state.orientation}`;
    return previousLayout !== nextLayout;
  };
  const setDatePickerBackdropRect = (state, boundary) => {
    if (!state.backdrop) return;
    Object.assign(state.backdrop.style, {
      top: `${boundary.top}px`,
      left: `${boundary.left}px`,
      width: `${boundary.width}px`,
      height: `${boundary.height}px`,
    });
  };
  const measureDatePickerNaturalHeight = (state) => {
    const previousMaxHeight = state.panel.style.maxHeight;
    state.panel.style.maxHeight = 'none';
    const height = Math.max(state.panel.scrollHeight, state.panel.getBoundingClientRect().height, 1);
    state.panel.style.maxHeight = previousMaxHeight;
    return height;
  };
  const positionDatePicker = (state) => {
    if (!state?.open) return;

    const context = getDatePickerResponsiveContext();
    const anchorBoundary = getDatePickerBoundaryRect(state, DATE_PICKER_VIEWPORT_GAP);
    const sheetBoundary = getDatePickerBoundaryRect(state, 0);
    const rect = state.trigger.getBoundingClientRect();
    const rootFontSize = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    const preferredWidth = (state.type === 'datetime-local' ? 39 : state.type === 'time' ? 24 : 22) * rootFontSize;
    const twoColumnMinimum = 36 * rootFontSize;
    const orientationStacked = state.type === 'datetime-local' && !context.landscape;

    state.panel.style.removeProperty('--date-picker-time-list-max');
    Object.assign(state.panel.style, {
      position: 'fixed',
      inset: 'auto',
      top: `${anchorBoundary.top}px`,
      bottom: 'auto',
      left: `${anchorBoundary.left}px`,
      right: 'auto',
      width: `${Math.min(preferredWidth, anchorBoundary.width)}px`,
      maxHeight: 'none',
    });

    let stacked = state.type === 'datetime-local' && (context.tablet ? orientationStacked : Math.min(preferredWidth, anchorBoundary.width) < twoColumnMinimum);
    syncDatePickerModeClasses(state, 'anchored', context, stacked);
    const desiredHeight = measureDatePickerNaturalHeight(state);
    const spaceBelow = Math.max(0, anchorBoundary.bottom - rect.bottom - DATE_PICKER_ANCHOR_GAP);
    const spaceAbove = Math.max(0, rect.top - anchorBoundary.top - DATE_PICKER_ANCHOR_GAP);
    const fitsTabletWidth = preferredWidth <= anchorBoundary.width;
    const fitsTabletHeight = desiredHeight <= Math.max(spaceBelow, spaceAbove);
    const useSheet = context.mobile || (context.tablet && (!fitsTabletWidth || !fitsTabletHeight));

    if (useSheet) {
      stacked = state.type === 'datetime-local' && !context.landscape;
      const layoutChanged = syncDatePickerModeClasses(state, 'sheet', context, stacked);
      const sheetMargin = context.shortViewport ? 12 : 16;
      const maxSheetHeight = Math.max(1, sheetBoundary.height - sheetMargin);
      const panelWidth = Math.max(1, sheetBoundary.width);
      const timeListHeight = Math.max(88, Math.min(216, maxSheetHeight - (state.type === 'datetime-local' ? 218 : 168)));
      state.panel.style.setProperty('--date-picker-time-list-max', `${timeListHeight}px`);
      Object.assign(state.panel.style, {
        top: `${sheetBoundary.top}px`,
        bottom: 'auto',
        left: `${sheetBoundary.left}px`,
        right: 'auto',
        width: `${panelWidth}px`,
        maxHeight: `${maxSheetHeight}px`,
      });
      const panelHeight = Math.min(maxSheetHeight, Math.max(1, state.panel.getBoundingClientRect().height));
      state.panel.style.top = `${Math.max(sheetBoundary.top, sheetBoundary.bottom - panelHeight)}px`;
      setDatePickerBackdropRect(state, sheetBoundary);
      state.backdrop.hidden = false;
      setDatePickerScrollLock(state, true);
      if (layoutChanged || state.timePanel) queueDatePickerSelectedTimeScroll(state);
      return;
    }

    const panelWidth = Math.min(preferredWidth, anchorBoundary.width);
    stacked = state.type === 'datetime-local' && (context.tablet ? orientationStacked : panelWidth < twoColumnMinimum);
    const layoutChanged = syncDatePickerModeClasses(state, 'anchored', context, stacked);
    state.backdrop.hidden = true;
    setDatePickerScrollLock(state, false);

    const triggerIsInViewport = rect.bottom > context.viewport.top && rect.top < context.viewport.bottom && rect.right > context.viewport.left && rect.left < context.viewport.right;
    const triggerIsInHost = !anchorBoundary.hostRect || (rect.bottom > anchorBoundary.hostRect.top && rect.top < anchorBoundary.hostRect.bottom && rect.right > anchorBoundary.hostRect.left && rect.left < anchorBoundary.hostRect.right);
    if (!triggerIsInViewport || !triggerIsInHost) {
      closeDatePicker(state);
      return;
    }

    Object.assign(state.panel.style, {
      width: `${panelWidth}px`,
      maxHeight: 'none',
    });
    const anchoredDesiredHeight = measureDatePickerNaturalHeight(state);
    const anchoredSpaceBelow = Math.max(0, anchorBoundary.bottom - rect.bottom - DATE_PICKER_ANCHOR_GAP);
    const anchoredSpaceAbove = Math.max(0, rect.top - anchorBoundary.top - DATE_PICKER_ANCHOR_GAP);
    const openAbove = anchoredSpaceBelow < Math.min(anchoredDesiredHeight, 260) && anchoredSpaceAbove > anchoredSpaceBelow;
    const availableHeight = Math.max(1, Math.min(560, openAbove ? anchoredSpaceAbove : anchoredSpaceBelow));
    const panelLeft = Math.max(anchorBoundary.left, Math.min(rect.left, anchorBoundary.right - panelWidth));
    const timeListHeight = Math.max(88, Math.min(216, availableHeight - (state.type === 'datetime-local' ? 152 : 132)));
    state.panel.style.setProperty('--date-picker-time-list-max', `${timeListHeight}px`);

    Object.assign(state.panel.style, {
      top: `${openAbove ? anchorBoundary.top : rect.bottom + DATE_PICKER_ANCHOR_GAP}px`,
      bottom: 'auto',
      left: `${panelLeft}px`,
      right: 'auto',
      width: `${panelWidth}px`,
      maxHeight: `${availableHeight}px`,
    });
    if (openAbove) {
      const panelHeight = Math.min(availableHeight, Math.max(1, state.panel.getBoundingClientRect().height));
      state.panel.style.top = `${Math.max(anchorBoundary.top, rect.top - DATE_PICKER_ANCHOR_GAP - panelHeight)}px`;
    }
    if (layoutChanged || state.timePanel) queueDatePickerSelectedTimeScroll(state);
  };
  const closeDatePicker = (state, restoreFocus = false) => {
    if (!state || !state.open) return;
    state.open = false;
    state.wrapper.classList.remove('is-open');
    state.trigger.setAttribute('aria-expanded', 'false');
    state.backdrop.hidden = true;
    setDatePickerScrollLock(state, false);
    if (supportsPopover && state.panel.matches(':popover-open')) state.panel.hidePopover();
    else state.panel.hidden = true;
    if (openDatePickerState === state) openDatePickerState = null;
    if (restoreFocus && state.trigger.isConnected) state.trigger.focus();
  };
  const openDatePicker = (state, focusCalendar = false) => {
    if (!state || state.input.disabled || state.input.readOnly) return;
    if (openSelectState) closeSleekSelect(openSelectState);
    if (openDatePickerState && openDatePickerState !== state) closeDatePicker(openDatePickerState);
    setDatePickerDraftFromInput(state);
    state.showJump = false;
    state.open = true;
    openDatePickerState = state;
    state.wrapper.classList.add('is-open');
    state.trigger.setAttribute('aria-expanded', 'true');
    state.panel.hidden = false;
    if (supportsPopover && !state.panel.matches(':popover-open')) state.panel.showPopover();
    renderDatePicker(state);
    positionDatePicker(state);
    queueDatePickerSelectedTimeScroll(state);
    requestAnimationFrame(() => {
      if (focusCalendar && state.type !== 'time') {
        const selected = state.panel.querySelector('.date-picker-day[aria-selected="true"]:not(:disabled)');
        const focusable = selected || state.panel.querySelector('.date-picker-day[tabindex="0"]:not(:disabled)');
        focusable?.focus({ preventScroll: true });
      } else if (focusCalendar && state.type === 'time') {
        state.hourList.querySelector('.date-picker-time-option.is-selected:not(:disabled), .date-picker-time-option:not(:disabled)')?.focus({ preventScroll: true });
      }
    });
  };
  const selectDatePickerDay = (state, value) => {
    const parsed = parseDatePickerValue('date', value);
    if (!parsed || !datePickerDayAllowed(state, { ...parsed, hour: state.draft.hour, minute: state.draft.minute })) return;
    state.draft = { ...state.draft, year: parsed.year, month: parsed.month, day: parsed.day };
    state.focusDate = { ...state.draft };
    state.draftHasValue = true;
    if (state.type === 'date' && state.mode !== 'sheet') {
      commitDatePickerValue(state, datePickerDateKey(state.draft), true);
      return;
    }
    renderDatePicker(state);
  };
  const updateDatePickerTimePart = (state, kind, rawValue) => {
    const next = { ...state.draft };
    if (kind === 'hour') next.hour = datePickerHour24(Number(rawValue), datePickerPeriod(state.draft.hour));
    if (kind === 'minute') next.minute = Number(rawValue);
    if (kind === 'period') next.hour = datePickerHour24(datePickerHour12(state.draft.hour), rawValue);
    if (!datePickerWithinBounds(state, next) && kind === 'minute') return;
    state.draft = next;
    state.draftHasValue = true;
    renderDatePicker(state);
    requestAnimationFrame(() => {
      const selector = kind === 'hour' ? `[data-date-picker-hour="${rawValue}"]` : kind === 'minute' ? `[data-date-picker-minute="${rawValue}"]` : `[data-date-picker-period="${rawValue}"]`;
      state.panel.querySelector(`${selector}:not(:disabled)`)?.focus({ preventScroll: true });
    });
  };
  const destroyDatePicker = (state) => {
    if (!state) return;
    if (openDatePickerState === state) openDatePickerState = null;
    state.open = false;
    setDatePickerScrollLock(state, false);
    state.observer?.disconnect();
    if (state.form && state.resetHandler) state.form.removeEventListener('reset', state.resetHandler);
    if (state.linkedStart && state.linkedStartHandler) state.linkedStart.removeEventListener('change', state.linkedStartHandler);
    state.backdrop?.remove();
    state.panel.remove();
    state.trigger.remove();
    state.input.classList.remove('date-picker-native');
    delete state.input.dataset.datePickerReady;
    if (state.hadTabIndex) state.input.setAttribute('tabindex', state.originalTabIndex);
    else state.input.removeAttribute('tabindex');
    if (state.wrapper.contains(state.input)) state.wrapper.replaceWith(state.input);
    else state.wrapper.remove();
    datePickerStates.delete(state.input);
  };
  const initDatePicker = (input) => {
    if (!(input instanceof HTMLInputElement) || !['date', 'datetime-local', 'time'].includes(input.type) || input.hasAttribute('data-native-picker') || input.dataset.datePickerReady === '1') return;
    input.dataset.datePickerReady = '1';
    const accessibleLabel = getDatePickerLabel(input);
    const wrapper = document.createElement('div');
    wrapper.className = `date-picker date-picker-${input.type}`;
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    const hadTabIndex = input.hasAttribute('tabindex');
    const originalTabIndex = input.getAttribute('tabindex') || '';
    input.classList.add('date-picker-native');
    input.tabIndex = -1;

    const panelId = `date-picker-panel-${++datePickerIdCounter}`;
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'date-picker-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', panelId);
    trigger.setAttribute('aria-label', accessibleLabel);
    trigger.appendChild(makeDatePickerIcon(input.type === 'datetime-local' ? 'calendar-clock' : 'calendar', 'ui-icon date-picker-trigger-icon'));
    const valueNode = document.createElement('span');
    valueNode.className = 'date-picker-trigger-value';
    trigger.appendChild(valueNode);
    wrapper.appendChild(trigger);

    const backdrop = document.createElement('div');
    backdrop.className = 'date-picker-backdrop';
    backdrop.hidden = true;
    backdrop.setAttribute('aria-hidden', 'true');

    const panel = document.createElement('div');
    panel.id = panelId;
    panel.className = `date-picker-panel date-picker-panel-${input.type}`;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', `${accessibleLabel} picker`);
    panel.hidden = true;
    if (supportsPopover) panel.setAttribute('popover', 'manual');
    const contentHost = input.closest('dialog') || document.body;
    contentHost.append(backdrop, panel);

    const state = {
      input,
      wrapper,
      trigger,
      valueNode,
      panel,
      backdrop,
      contentHost,
      type: input.type,
      open: false,
      mode: null,
      layout: null,
      orientation: null,
      scrollLocked: false,
      showJump: false,
      draft: datePickerTodayParts(),
      draftHasValue: false,
      viewYear: datePickerTodayParts().year,
      viewMonth: datePickerTodayParts().month,
      focusDate: datePickerTodayParts(),
      jumpCenterYear: datePickerTodayParts().year,
      calendar: null,
      calendarView: null,
      jumpView: null,
      monthLabel: null,
      dayGrid: null,
      jumpYears: null,
      jumpMonths: null,
      jumpYearRange: null,
      timePanel: null,
      hourList: null,
      minuteList: null,
      periodList: null,
      minuteEmpty: null,
      timeOptionsBuilt: false,
      body: null,
      observer: null,
      form: input.form,
      resetHandler: null,
      linkedStart: getLinkedDatePickerStart(input),
      linkedStartHandler: null,
      hadTabIndex,
      originalTabIndex,
    };
    datePickerStates.set(input, state);
    setDatePickerDraftFromInput(state);

    const sheetBar = document.createElement('div');
    sheetBar.className = 'date-picker-sheet-bar';
    const sheetTitle = document.createElement('strong');
    sheetTitle.className = 'date-picker-sheet-title';
    sheetTitle.textContent = state.type === 'date' ? 'Choose date' : state.type === 'time' ? 'Choose time' : 'Choose date and time';
    const sheetClose = document.createElement('button');
    sheetClose.type = 'button';
    sheetClose.className = 'date-picker-sheet-close';
    sheetClose.dataset.datePickerClose = '1';
    sheetClose.setAttribute('aria-label', 'Close picker');
    sheetClose.appendChild(makeDatePickerIcon('x'));
    sheetClose.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      closeDatePicker(state, true);
    });
    sheetBar.append(sheetTitle, sheetClose);
    panel.appendChild(sheetBar);

    const body = document.createElement('div');
    body.className = 'date-picker-body';
    state.body = body;
    panel.appendChild(body);

    if (state.type !== 'time') {
      const calendar = document.createElement('section');
      calendar.className = 'date-picker-calendar';
      state.calendar = calendar;

      const header = document.createElement('div');
      header.className = 'date-picker-head';
      const previous = document.createElement('button');
      previous.type = 'button';
      previous.className = 'date-picker-nav';
      previous.dataset.datePickerPrevious = '1';
      previous.setAttribute('aria-label', 'Previous month');
      previous.appendChild(makeDatePickerIcon('chevron-left'));
      const monthLabel = document.createElement('button');
      monthLabel.type = 'button';
      monthLabel.className = 'date-picker-month-label';
      monthLabel.dataset.datePickerJumpToggle = '1';
      monthLabel.setAttribute('aria-label', 'Choose month and year');
      const next = document.createElement('button');
      next.type = 'button';
      next.className = 'date-picker-nav';
      next.dataset.datePickerNext = '1';
      next.setAttribute('aria-label', 'Next month');
      next.appendChild(makeDatePickerIcon('chevron-right'));
      header.append(previous, monthLabel, next);
      state.monthLabel = monthLabel;

      const calendarView = document.createElement('div');
      calendarView.className = 'date-picker-calendar-view';
      state.calendarView = calendarView;
      const weekdays = document.createElement('div');
      weekdays.className = 'date-picker-weekdays';
      weekdays.setAttribute('aria-hidden', 'true');
      datePickerWeekdays.forEach((day) => {
        const span = document.createElement('span');
        span.textContent = day;
        weekdays.appendChild(span);
      });
      const dayGrid = document.createElement('div');
      dayGrid.className = 'date-picker-grid';
      dayGrid.setAttribute('role', 'grid');
      dayGrid.setAttribute('aria-label', 'Calendar');
      state.dayGrid = dayGrid;
      calendarView.append(weekdays, dayGrid);

      const jumpView = document.createElement('div');
      jumpView.className = 'date-picker-jump';
      jumpView.hidden = true;
      state.jumpView = jumpView;
      const jumpHead = document.createElement('div');
      jumpHead.className = 'date-picker-jump-head';
      const prevYears = document.createElement('button');
      prevYears.type = 'button';
      prevYears.className = 'date-picker-nav';
      prevYears.dataset.datePickerYearPage = '-11';
      prevYears.setAttribute('aria-label', 'Earlier years');
      prevYears.appendChild(makeDatePickerIcon('chevron-left'));
      const yearRange = document.createElement('strong');
      yearRange.className = 'date-picker-year-range';
      const nextYears = document.createElement('button');
      nextYears.type = 'button';
      nextYears.className = 'date-picker-nav';
      nextYears.dataset.datePickerYearPage = '11';
      nextYears.setAttribute('aria-label', 'Later years');
      nextYears.appendChild(makeDatePickerIcon('chevron-right'));
      jumpHead.append(prevYears, yearRange, nextYears);
      state.jumpYearRange = yearRange;
      const jumpYears = document.createElement('div');
      jumpYears.className = 'date-picker-year-grid';
      state.jumpYears = jumpYears;
      const jumpMonths = document.createElement('div');
      jumpMonths.className = 'date-picker-month-grid';
      state.jumpMonths = jumpMonths;
      jumpView.append(jumpHead, jumpYears, jumpMonths);
      calendar.append(header, calendarView, jumpView);
      body.appendChild(calendar);
    }

    if (state.type !== 'date') {
      const timePanel = document.createElement('section');
      timePanel.className = 'date-picker-time';
      state.timePanel = timePanel;
      const timeTitle = document.createElement('div');
      timeTitle.className = 'date-picker-time-title';
      timeTitle.textContent = 'Time';
      const columns = document.createElement('div');
      columns.className = 'date-picker-time-columns';
      const createColumn = (label) => {
        const group = document.createElement('div');
        group.className = 'date-picker-time-group';
        const heading = document.createElement('span');
        heading.className = 'date-picker-time-label';
        heading.textContent = label;
        const list = document.createElement('div');
        list.className = 'date-picker-time-list';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', label);
        group.append(heading, list);
        columns.appendChild(group);
        return list;
      };
      state.hourList = createColumn('Hour');
      state.minuteList = createColumn('Minute');
      state.periodList = createColumn('AM / PM');
      timePanel.append(timeTitle, columns);
      body.appendChild(timePanel);
    }

    const footer = document.createElement('div');
    footer.className = 'date-picker-footer';
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'date-picker-footer-button';
    clear.dataset.datePickerClear = '1';
    clear.textContent = 'Clear';
    footer.appendChild(clear);
    const spacer = document.createElement('span');
    spacer.className = 'date-picker-footer-spacer';
    footer.appendChild(spacer);
    const current = document.createElement('button');
    current.type = 'button';
    current.className = 'date-picker-footer-button';
    current.dataset.datePickerCurrent = '1';
    current.textContent = state.type === 'date' ? 'Today' : 'Now';
    footer.appendChild(current);
    const done = document.createElement('button');
    done.type = 'button';
    done.className = 'date-picker-done';
    done.dataset.datePickerDone = '1';
    done.textContent = 'Done';
    footer.appendChild(done);
    panel.appendChild(footer);

    syncDatePickerTrigger(state);
    renderDatePicker(state);

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      if (state.open) closeDatePicker(state); else openDatePicker(state);
    });
    trigger.addEventListener('keydown', (event) => {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openDatePicker(state, true);
      } else if (event.key === 'Escape' && state.open) {
        event.preventDefault();
        event.stopPropagation();
        closeDatePicker(state, true);
      }
    });

    backdrop.addEventListener('pointerdown', (event) => {
      if (event.target !== backdrop || state.mode !== 'sheet') return;
      event.preventDefault();
      closeDatePicker(state, true);
    });

    panel.addEventListener('click', (event) => {
      if (event.target.closest('[data-date-picker-close]')) {
        closeDatePicker(state, true);
        return;
      }
      const day = event.target.closest('[data-date-picker-day]');
      if (day && !day.disabled) {
        selectDatePickerDay(state, day.dataset.datePickerDay);
        return;
      }
      if (event.target.closest('[data-date-picker-previous]')) {
        const currentView = { ...state.focusDate, year: state.viewYear, month: state.viewMonth, day: 1 };
        const nextView = addDatePickerMonths(currentView, -1);
        state.viewYear = nextView.year;
        state.viewMonth = nextView.month;
        state.focusDate = clampDatePickerFocus(state, { ...state.focusDate, year: nextView.year, month: nextView.month, day: Math.min(state.focusDate.day, new Date(nextView.year, nextView.month, 0).getDate()) });
        renderDatePicker(state);
        return;
      }
      if (event.target.closest('[data-date-picker-next]')) {
        const currentView = { ...state.focusDate, year: state.viewYear, month: state.viewMonth, day: 1 };
        const nextView = addDatePickerMonths(currentView, 1);
        state.viewYear = nextView.year;
        state.viewMonth = nextView.month;
        state.focusDate = clampDatePickerFocus(state, { ...state.focusDate, year: nextView.year, month: nextView.month, day: Math.min(state.focusDate.day, new Date(nextView.year, nextView.month, 0).getDate()) });
        renderDatePicker(state);
        return;
      }
      if (event.target.closest('[data-date-picker-jump-toggle]')) {
        state.showJump = !state.showJump;
        state.jumpCenterYear = state.viewYear;
        renderDatePicker(state);
        return;
      }
      const yearPage = event.target.closest('[data-date-picker-year-page]');
      if (yearPage) {
        state.jumpCenterYear += Number(yearPage.dataset.datePickerYearPage || 0);
        renderDatePickerJump(state);
        return;
      }
      const year = event.target.closest('[data-date-picker-year]');
      if (year && !year.disabled) {
        state.viewYear = Number(year.dataset.datePickerYear);
        state.jumpCenterYear = state.viewYear;
        renderDatePickerJump(state);
        return;
      }
      const month = event.target.closest('[data-date-picker-month]');
      if (month && !month.disabled) {
        state.viewMonth = Number(month.dataset.datePickerMonth);
        state.focusDate = clampDatePickerFocus(state, { ...state.focusDate, year: state.viewYear, month: state.viewMonth, day: Math.min(state.focusDate.day, new Date(state.viewYear, state.viewMonth, 0).getDate()) });
        state.showJump = false;
        renderDatePicker(state);
        requestAnimationFrame(() => state.dayGrid.querySelector('.date-picker-day[tabindex="0"]:not(:disabled)')?.focus({ preventScroll: true }));
        return;
      }
      const hour = event.target.closest('[data-date-picker-hour]');
      if (hour && !hour.disabled) {
        updateDatePickerTimePart(state, 'hour', hour.dataset.datePickerHour);
        return;
      }
      const minute = event.target.closest('[data-date-picker-minute]');
      if (minute && !minute.disabled) {
        updateDatePickerTimePart(state, 'minute', minute.dataset.datePickerMinute);
        return;
      }
      const period = event.target.closest('[data-date-picker-period]');
      if (period && !period.disabled) {
        updateDatePickerTimePart(state, 'period', period.dataset.datePickerPeriod);
        return;
      }
      if (event.target.closest('[data-date-picker-clear]')) {
        commitDatePickerValue(state, '', state.mode !== 'sheet');
        return;
      }
      if (event.target.closest('[data-date-picker-current]')) {
        const now = datePickerTodayParts();
        if (!datePickerCandidateAllowed(state, now)) return;
        if (state.type === 'date' && state.mode !== 'sheet') {
          commitDatePickerValue(state, datePickerDateKey(now), true);
          return;
        }
        state.draft = now;
        state.draftHasValue = true;
        state.viewYear = now.year;
        state.viewMonth = now.month;
        state.focusDate = { ...now };
        renderDatePicker(state);
        return;
      }
      if (event.target.closest('[data-date-picker-done]') && state.draftHasValue && datePickerCandidateAllowed(state, state.draft)) {
        commitDatePickerValue(state, serializeDatePickerValue(state.type, state.draft), true);
      }
    });

    panel.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closeDatePicker(state, true);
        return;
      }
      const timeOption = event.target.closest('.date-picker-time-option');
      if (timeOption && ['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
        const list = timeOption.closest('.date-picker-time-list');
        const options = Array.from(list?.querySelectorAll('.date-picker-time-option:not(:disabled)') || []);
        const currentIndex = options.indexOf(timeOption);
        if (options.length) {
          event.preventDefault();
          let nextIndex = currentIndex;
          if (event.key === 'ArrowUp') nextIndex = Math.max(0, currentIndex - 1);
          if (event.key === 'ArrowDown') nextIndex = Math.min(options.length - 1, currentIndex + 1);
          if (event.key === 'Home') nextIndex = 0;
          if (event.key === 'End') nextIndex = options.length - 1;
          options.forEach((option, index) => { option.tabIndex = index === nextIndex ? 0 : -1; });
          options[nextIndex]?.focus({ preventScroll: true });
          scrollDatePickerListOptionIntoView(list, options[nextIndex]);
        }
        return;
      }

      const day = event.target.closest('[data-date-picker-day]');
      if (!day) return;
      const current = parseDatePickerValue('date', day.dataset.datePickerDay);
      if (!current) return;
      let target = null;
      if (event.key === 'ArrowLeft') target = addDatePickerDays(current, -1);
      if (event.key === 'ArrowRight') target = addDatePickerDays(current, 1);
      if (event.key === 'ArrowUp') target = addDatePickerDays(current, -7);
      if (event.key === 'ArrowDown') target = addDatePickerDays(current, 7);
      if (event.key === 'PageUp') target = addDatePickerMonths(current, event.shiftKey ? -12 : -1);
      if (event.key === 'PageDown') target = addDatePickerMonths(current, event.shiftKey ? 12 : 1);
      if (event.key === 'Home') target = addDatePickerDays(current, -datePickerPartsToDate(current).getDay());
      if (event.key === 'End') target = addDatePickerDays(current, 6 - datePickerPartsToDate(current).getDay());
      if (target) {
        event.preventDefault();
        const direction = ['ArrowLeft', 'ArrowUp', 'PageUp', 'Home'].includes(event.key) ? -1 : 1;
        const allowed = findAllowedDatePickerDay(state, target, direction) || findAllowedDatePickerDay(state, target, -direction);
        if (allowed) focusDatePickerDay(state, allowed);
        return;
      }
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        selectDatePickerDay(state, day.dataset.datePickerDay);
      }
    });

    input.addEventListener('input', () => {
      syncDatePickerTrigger(state);
      if (state.open) {
        setDatePickerDraftFromInput(state);
        renderDatePicker(state);
        queueDatePickerSelectedTimeScroll(state);
      }
    });
    input.addEventListener('change', () => {
      syncDatePickerTrigger(state);
      if (state.open) {
        setDatePickerDraftFromInput(state);
        renderDatePicker(state);
        queueDatePickerSelectedTimeScroll(state);
      }
    });
    input.addEventListener('focus', () => trigger.focus());
    input.addEventListener('invalid', () => {
      trigger.setAttribute('aria-invalid', 'true');
      requestAnimationFrame(() => trigger.focus());
    });

    if (state.form) {
      state.resetHandler = () => requestAnimationFrame(() => {
        setDatePickerDraftFromInput(state);
        renderDatePicker(state);
      });
      state.form.addEventListener('reset', state.resetHandler);
    }
    if (state.linkedStart) {
      state.linkedStartHandler = () => {
        if (state.open) renderDatePicker(state);
        syncDatePickerTrigger(state);
      };
      state.linkedStart.addEventListener('change', state.linkedStartHandler);
    }

    state.observer = new MutationObserver(() => {
      syncDatePickerTrigger(state);
      if (state.open) {
        renderDatePicker(state);
        queueDatePickerSelectedTimeScroll(state);
      }
    });
    state.observer.observe(input, { attributes: true, attributeFilter: ['disabled', 'readonly', 'required', 'min', 'max', 'step', 'value'] });
  };
  const upgradeDatePickers = (root) => {
    if (root instanceof HTMLInputElement && root.matches(datePickerSelector)) initDatePicker(root);
    root?.querySelectorAll?.(datePickerSelector).forEach(initDatePicker);
  };
  const cleanupDetachedDatePickers = () => {
    Array.from(datePickerStates.values()).forEach((state) => {
      if (!state.input.isConnected) destroyDatePicker(state);
    });
  };

  const cleanupDetachedSleekSelects = () => {
    Array.from(selectStates.values()).forEach((state) => {
      if (!state.select.isConnected) destroySleekSelect(state);
    });
  };

  document.querySelectorAll('select').forEach(initSleekSelect);
  document.querySelectorAll(datePickerSelector).forEach(initDatePicker);

  const sleekSelectDocumentObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
          upgradeSleekSelects(node);
          upgradeDatePickers(node);
        }
      });
    });
    cleanupDetachedSleekSelects();
    cleanupDetachedDatePickers();
  });
  sleekSelectDocumentObserver.observe(document.body, { childList: true, subtree: true });

  document.addEventListener('pointerdown', (event) => {
    if (openSelectState && !openSelectState.wrapper.contains(event.target) && !openSelectState.content.contains(event.target)) {
      closeSleekSelect(openSelectState);
    }
    if (openDatePickerState?.mode === 'anchored' && !openDatePickerState.wrapper.contains(event.target) && !openDatePickerState.panel.contains(event.target)) {
      closeDatePicker(openDatePickerState);
    }
  });

  let floatingPositionFrame = 0;
  const scheduleFloatingPosition = () => {
    if (floatingPositionFrame) return;
    floatingPositionFrame = requestAnimationFrame(() => {
      floatingPositionFrame = 0;
      if (openSelectState) positionSleekSelect(openSelectState);
      if (openDatePickerState) positionDatePicker(openDatePickerState);
    });
  };
  const scrollAffectsFloatingControl = (eventTarget, state, floatingContent) => {
    if (!state?.open) return false;
    if (eventTarget instanceof Node && floatingContent?.contains(eventTarget)) return false;
    if (eventTarget === document || eventTarget === document.documentElement || eventTarget === document.body) return true;
    return eventTarget instanceof Element ? eventTarget.contains(state.trigger) : true;
  };

  window.addEventListener('resize', scheduleFloatingPosition, { passive: true });
  window.addEventListener('orientationchange', scheduleFloatingPosition, { passive: true });
  window.visualViewport?.addEventListener('resize', scheduleFloatingPosition, { passive: true });
  window.visualViewport?.addEventListener('scroll', scheduleFloatingPosition, { passive: true });

  document.addEventListener('scroll', (event) => {
    const repositionSelect = scrollAffectsFloatingControl(event.target, openSelectState, openSelectState?.content);
    const repositionDatePicker = scrollAffectsFloatingControl(event.target, openDatePickerState, openDatePickerState?.panel);
    if (repositionSelect || repositionDatePicker) scheduleFloatingPosition();
  }, { capture: true, passive: true });

  document.querySelectorAll('[data-schedule-team-form]').forEach((form) => {
    const sportSelect = form.querySelector('[data-schedule-sport]');
    const teamCheckboxes = Array.from(form.querySelectorAll('[data-schedule-team-checks] input[name="team_ids[]"]'));
    const note = form.querySelector('[data-schedule-team-note]');
    const saveButton = form.querySelector('[data-schedule-save]');

    const scheduleTeamState = () => {
      const selectedOption = sportSelect?.options?.[sportSelect.selectedIndex] || null;
      const resultType = selectedOption?.dataset?.resultType || '';
      const checkedCount = teamCheckboxes.filter((checkbox) => checkbox.checked).length;

      if (!resultType) {
        return { valid: false, message: 'Select a sport to set the participating-team rule.' };
      }
      if (resultType === 'match') {
        return {
          valid: checkedCount === 2,
          message: checkedCount === 2
            ? 'Exactly 2 teams selected. The first selected team is Team A and the second is Team B.'
            : `Match sports require exactly 2 participating teams (${checkedCount} selected).`,
        };
      }

      return {
        valid: checkedCount <= 2,
        message: checkedCount <= 2
          ? 'Judged sports may leave participating teams empty or select up to 2 teams.'
          : `Judged sports can include at most 2 participating teams (${checkedCount} selected).`,
      };
    };

    const syncScheduleTeams = () => {
      const state = scheduleTeamState();
      if (note) note.textContent = state.message;
      if (saveButton) saveButton.disabled = !state.valid;
      teamCheckboxes.forEach((checkbox) => checkbox.setCustomValidity(''));
    };

    sportSelect?.addEventListener('change', syncScheduleTeams);
    teamCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncScheduleTeams));
    form.addEventListener('submit', (event) => {
      const state = scheduleTeamState();
      if (state.valid) return;
      event.preventDefault();
      if (note) note.textContent = state.message;
    });

    syncScheduleTeams();
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
    const presentation = confirmationPresentation(message, pendingConfirmSubmitter);
    if (confirmTitle) confirmTitle.textContent = presentation.title;
    if (confirmMessage) confirmMessage.textContent = message;
    if (confirmProceed) confirmProceed.textContent = presentation.actionLabel;
    if (confirmIconUse && confirmIconBase) confirmIconUse.setAttribute('href', `${confirmIconBase}#${presentation.icon}`);
    confirmDialog.dataset.confirmTone = presentation.tone;
    confirmDialog.showModal();
    window.setTimeout(() => confirmCancel?.focus(), 0);
  });

  confirmCancel?.addEventListener('click', () => confirmDialog?.close());
  confirmDialog?.addEventListener('click', (event) => {
    if (event.target === confirmDialog) confirmDialog.close();
  });
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
    if (event.key !== 'Escape' || !openDatePickerState) return;
    event.preventDefault();
    event.stopPropagation();
    closeDatePicker(openDatePickerState, true);
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (openDatePickerState) {
      event.preventDefault();
      event.stopPropagation();
      closeDatePicker(openDatePickerState, true);
      return;
    }
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

      const targetLane = target.closest('[data-bracket-lane]')?.dataset.bracketLane || '';
      const feeds = Array.from(feedMap.values()).map((feed) => ({
        ...feed,
        source: graph.matchByCode.get(feed.code),
      })).filter((feed) => {
        if (!feed.types.has('loser')) return true;
        const sourceLane = feed.source?.closest('[data-bracket-lane]')?.dataset.bracketLane || '';
        return !sourceLane || !targetLane || sourceLane === targetLane;
      });
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


  document.querySelectorAll('#bracket-generator').forEach((dialog) => {
    const format = dialog.querySelector('select[name="tournament_format"]');
    const third = dialog.querySelector('input[name="third_place_playoff"]');
    const teamChecks = Array.from(dialog.querySelectorAll('input[name="team_ids[]"]'));
    const pairingBox = dialog.querySelector('[data-pairing-builder]');
    const pairingNote = dialog.querySelector('[data-bracket-pairing-note]');
    const generateButton = dialog.querySelector('[data-bracket-generate]');
    const slots = [];
    let checkOrder = [];

    const teamName = (id) => {
      const checkbox = teamChecks.find((item) => item.value === id);
      return checkbox?.closest('label')?.textContent.trim() || id;
    };
    const selectedIds = () => checkOrder.filter((id) => teamChecks.some((item) => item.value === id && item.checked));
    const trimEmptyMatches = () => {
      while (slots.length >= 2 && !slots[slots.length - 1] && !slots[slots.length - 2]) slots.splice(-2, 2);
    };
    const visibleSlotCount = () => {
      let lastUsed = slots.length - 1;
      while (lastUsed >= 0 && !slots[lastUsed]) lastUsed--;
      if (lastUsed < 0) return 0;
      return Math.ceil((lastUsed + 1) / 2) * 2;
    };
    const refreshThird = () => {
      if (!format || !third) return;
      const doubleElimination = format.value === 'double_elimination';
      third.disabled = doubleElimination;
      if (doubleElimination) third.checked = false;
      third.closest('label')?.classList.toggle('is-hidden', doubleElimination);
    };
    const refreshValidity = () => {
      const ids = selectedIds();
      const slotCount = visibleSlotCount();
      const validCount = format?.value === 'double_elimination'
        ? ids.length === 4
        : [2, 4, 8, 16].includes(ids.length);
      const allSlotsFilled = slotCount === ids.length
        && slotCount > 0
        && slots.slice(0, slotCount).every(Boolean)
        && new Set(slots.slice(0, slotCount)).size === slotCount;
      const valid = validCount && allSlotsFilled;
      if (generateButton) generateButton.disabled = !valid;
      if (!pairingNote) return;
      if (ids.length === 0) {
        pairingNote.textContent = 'Select 2, 4, 8, or 16 teams for single elimination, or exactly 4 for double elimination.';
      } else if (!validCount) {
        pairingNote.textContent = format?.value === 'double_elimination'
          ? 'Double elimination requires exactly 4 checked teams.'
          : 'Single elimination requires 2, 4, 8, or 16 checked teams.';
      } else if (!allSlotsFilled) {
        pairingNote.textContent = 'Fill every pairing slot before generating the bracket.';
      } else {
        pairingNote.textContent = 'Bracket pairing is ready to generate.';
      }
    };
    const renderPairs = (focusSlotIndex = null) => {
      if (!pairingBox) return;
      pairingBox.replaceChildren();
      const ids = selectedIds();
      if (ids.length === 0) {
        slots.length = 0;
        refreshValidity();
        return;
      }

      const minimumSlots = Math.ceil(ids.length / 2) * 2;
      while (slots.length < minimumSlots) slots.push(null);
      const slotCount = Math.max(minimumSlots, visibleSlotCount());

      const title = document.createElement('span');
      title.className = 'field-label pairing-builder-title';
      title.textContent = 'Bracket Pairing';
      pairingBox.append(title);

      for (let matchIndex = 0; matchIndex < slotCount / 2; matchIndex++) {
        const match = document.createElement('div');
        match.className = 'pairing-match';
        const matchLabel = document.createElement('strong');
        matchLabel.className = 'pairing-match-label';
        matchLabel.textContent = `M${matchIndex + 1}`;
        const row = document.createElement('div');
        row.className = 'pairing-match-row';

        [matchIndex * 2, matchIndex * 2 + 1].forEach((slotIndex, sideIndex) => {
          const select = document.createElement('select');
          select.name = 'pairing[]';
          select.required = true;
          select.dataset.pairingIndex = String(slotIndex);
          const blank = document.createElement('option');
          blank.value = '';
          blank.textContent = sideIndex === 0 ? 'Team A' : 'Team B';
          select.append(blank);
          ids.forEach((id) => {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = teamName(id);
            option.selected = slots[slotIndex] === id;
            select.append(option);
          });
          select.addEventListener('change', () => {
            const previous = slots[slotIndex] || null;
            const next = select.value || null;
            if (next && next !== previous) {
              const duplicateIndex = slots.findIndex((value, index) => index !== slotIndex && value === next);
              if (duplicateIndex >= 0) slots[duplicateIndex] = previous;
            }
            slots[slotIndex] = next;
            trimEmptyMatches();
            renderPairs(slotIndex);
          });
          row.append(select);
          if (sideIndex === 0) {
            const versus = document.createElement('span');
            versus.className = 'pairing-vs';
            versus.textContent = 'VS';
            row.append(versus);
          }
        });
        match.append(matchLabel, row);
        pairingBox.append(match);
      }
      refreshValidity();

      if (focusSlotIndex !== null) {
        requestAnimationFrame(() => {
          const nextSelect = pairingBox.querySelector(`select[data-pairing-index="${focusSlotIndex}"]`);
          if (!nextSelect) return;
          initSleekSelect(nextSelect);
          selectStates.get(nextSelect)?.trigger.focus({ preventScroll: true });
        });
      }
    };

    teamChecks.forEach((checkbox) => checkbox.addEventListener('change', () => {
      const id = checkbox.value;
      if (checkbox.checked) {
        if (!checkOrder.includes(id)) checkOrder.push(id);
        let emptyIndex = slots.findIndex((value) => !value);
        if (emptyIndex < 0) emptyIndex = slots.length;
        slots[emptyIndex] = id;
      } else {
        checkOrder = checkOrder.filter((value) => value !== id);
        const slotIndex = slots.findIndex((value) => value === id);
        if (slotIndex >= 0) slots[slotIndex] = null;
        trimEmptyMatches();
      }
      renderPairs();
    }));
    format?.addEventListener('change', () => {
      refreshThird();
      refreshValidity();
    });
    refreshThird();
    renderPairs();
  });

  document.querySelectorAll('[data-use-another-account]').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelector('[data-remember-card]')?.setAttribute('hidden', '');
      const loginForm = document.querySelector('[data-login-form]');
      loginForm?.removeAttribute('hidden');
      loginForm?.querySelector('input[name="username"]')?.focus();
    });
  });
})();

// Generated-password copy buttons.
document.addEventListener('click',async(e)=>{const b=e.target.closest('[data-copy-value]');if(!b)return;try{await navigator.clipboard.writeText(b.dataset.copyValue||'');const old=b.textContent;b.textContent='Copied';setTimeout(()=>b.textContent=old,1200);}catch(_){}});
