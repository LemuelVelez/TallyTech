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

  const draftRoot = document.querySelector('[data-draft-generator]');
  if (draftRoot) {
    const form = draftRoot.querySelector('[data-draft-form]');
    const banCount = draftRoot.querySelector('[data-draft-ban-count]');
    const sportSelect = draftRoot.querySelector('[data-draft-sport]');
    const categorySelect = draftRoot.querySelector('[data-draft-category]');
    const message = draftRoot.querySelector('[data-draft-message]');
    const previewPanel = document.querySelector('[data-draft-preview]');
    const previewEmpty = previewPanel?.querySelector('[data-draft-preview-empty]');
    const draftSheet = previewPanel?.querySelector('[data-draft-sheet]');
    const copyButton = previewPanel?.querySelector('[data-draft-copy]');
    let lastDraftSummary = '';

    const valueFor = (selector) => String(draftRoot.querySelector(selector)?.value || '').trim();
    const setPreviewText = (selector, value, fallback = '—') => {
      const element = previewPanel?.querySelector(selector);
      if (element) element.textContent = value || fallback;
    };
    const teamNameFor = (side) => {
      const select = draftRoot.querySelector(`[data-draft-team="${side}"]`);
      return String(select?.selectedOptions?.[0]?.dataset.teamName || select?.selectedOptions?.[0]?.textContent || '').trim();
    };

    const updateDraftCategories = () => {
      if (!sportSelect || !categorySelect) return;
      const selected = sportSelect.selectedOptions?.[0];
      const categories = String(selected?.dataset.categories || '')
        .split('|')
        .map((value) => value.trim())
        .filter(Boolean);
      const previous = categorySelect.value;
      categorySelect.replaceChildren();
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = categories.length ? 'Select category' : 'No category';
      categorySelect.appendChild(placeholder);
      categories.forEach((category) => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categorySelect.appendChild(option);
      });
      categorySelect.disabled = categories.length === 0;
      if (categories.includes(previous)) categorySelect.value = previous;
      else if (categories.length === 1) categorySelect.value = categories[0];
    };

    const updateDraftBanRows = () => {
      const count = Math.max(0, Math.min(5, Number.parseInt(banCount?.value || '0', 10) || 0));
      draftRoot.querySelectorAll('[data-draft-ban-row]').forEach((row) => {
        const parts = String(row.dataset.draftBanRow || '').split(':');
        const index = Number.parseInt(parts[1] || '0', 10) || 0;
        row.hidden = index > count;
        const input = row.querySelector('input');
        if (input) input.disabled = index > count;
      });
    };

    const draftBansFor = (side) => {
      const count = Math.max(0, Math.min(5, Number.parseInt(banCount?.value || '0', 10) || 0));
      const bans = [];
      for (let index = 1; index <= count; index += 1) {
        const value = valueFor(`[data-draft-ban="${side}:${index}"]`);
        if (value) bans.push(value);
      }
      return bans;
    };

    const renderDraftBans = (side, bans) => {
      const container = previewPanel?.querySelector(`[data-preview-bans="${side}"]`);
      if (!container) return;
      container.replaceChildren();
      if (!bans.length) {
        const empty = document.createElement('span');
        empty.className = 'draft-empty-ban';
        empty.textContent = 'None';
        container.appendChild(empty);
        return;
      }
      bans.forEach((ban) => {
        const chip = document.createElement('span');
        chip.textContent = ban;
        container.appendChild(chip);
      });
    };

    const validateDraft = () => {
      if (!form) return 'Draft form is unavailable.';
      if (!form.checkValidity()) {
        form.reportValidity();
        return 'Complete all mandatory draft fields.';
      }
      const duration = valueFor('[data-draft-duration]');
      if (duration && !/^\d{1,3}:[0-5]\d$/.test(duration)) {
        draftRoot.querySelector('[data-draft-duration]')?.focus();
        return 'Duration must use M:SS format, for example 18:42.';
      }
      const team1 = valueFor('[data-draft-team="1"]');
      const team2 = valueFor('[data-draft-team="2"]');
      if (team1 && team2 && team1 === team2) {
        draftRoot.querySelector('[data-draft-team="2"]')?.focus();
        return 'Team 1 and Team 2 must be different teams.';
      }
      return '';
    };

    const buildDraftSummary = (data) => {
      const line = (label, value) => `${label}: ${value || '—'}`;
      const roleLines = (side) => data.roles[side]
        .map((role, index) => `${data.roleLabels[index]}: ${role || '—'}`)
        .join(', ');
      return [
        `${data.sport || 'Match Draft'}${data.category ? ` · ${data.category}` : ''} · Game ${data.game}`,
        line('Duration', data.duration),
        line('Map / Court', data.map),
        line('VOD', data.vod),
        '',
        `${data.teamNames[1]}${data.winner === 'team1' ? ' — WINNER' : ''}`,
        line('Side', data.sides[1]),
        roleLines(1),
        line('Bans', data.bans[1].join(', ') || 'None'),
        '',
        `${data.teamNames[2]}${data.winner === 'team2' ? ' — WINNER' : ''}`,
        line('Side', data.sides[2]),
        roleLines(2),
        line('Bans', data.bans[2].join(', ') || 'None'),
      ].join('\n');
    };

    const renderDraft = () => {
      const error = validateDraft();
      if (message) {
        message.classList.remove('is-success');
        message.textContent = error;
      }
      if (error) return;

      const roleLabels = ['EXP Lane', 'Jungler', 'Mid Lane', 'Gold Lane', 'Roamer'];
      const data = {
        game: valueFor('[data-draft-game]') || '1',
        duration: valueFor('[data-draft-duration]'),
        sport: valueFor('[data-draft-sport]'),
        category: valueFor('[data-draft-category]'),
        vod: valueFor('[data-draft-vod]'),
        map: valueFor('[data-draft-map]'),
        winner: valueFor('[data-draft-winner]'),
        teamNames: { 1: teamNameFor(1) || 'Team 1', 2: teamNameFor(2) || 'Team 2' },
        sides: { 1: valueFor('[data-draft-side="1"]'), 2: valueFor('[data-draft-side="2"]') },
        roleLabels,
        roles: { 1: [], 2: [] },
        bans: { 1: draftBansFor(1), 2: draftBansFor(2) },
      };

      [1, 2].forEach((side) => {
        roleLabels.forEach((_, index) => {
          data.roles[side].push(valueFor(`[data-draft-role="${side}:${index}"]`));
        });
      });

      setPreviewText('[data-preview-game]', data.game, '1');
      setPreviewText('[data-preview-sport]', data.sport, 'Match Draft');
      setPreviewText('[data-preview-category]', data.category, '');
      setPreviewText('[data-preview-duration]', data.duration);
      setPreviewText('[data-preview-map]', data.map);
      setPreviewText('[data-preview-vod]', data.vod);

      [1, 2].forEach((side) => {
        setPreviewText(`[data-preview-team-name="${side}"]`, data.teamNames[side], `Team ${side}`);
        setPreviewText(`[data-preview-side="${side}"]`, data.sides[side]);
        data.roles[side].forEach((role, index) => setPreviewText(`[data-preview-role="${side}:${index}"]`, role));
        renderDraftBans(side, data.bans[side]);
        const teamCard = previewPanel?.querySelector(`[data-preview-team-card="${side}"]`);
        const winnerBadge = previewPanel?.querySelector(`[data-preview-winner="${side}"]`);
        const isWinner = data.winner === `team${side}`;
        teamCard?.classList.toggle('is-winner', isWinner);
        if (winnerBadge) winnerBadge.hidden = !isWinner;
      });

      lastDraftSummary = buildDraftSummary(data);
      if (previewEmpty) previewEmpty.hidden = true;
      if (draftSheet) draftSheet.hidden = false;
      if (copyButton) copyButton.disabled = false;
      if (message) {
        message.classList.add('is-success');
        message.textContent = 'Draft preview generated.';
      }
      previewPanel?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    };

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      renderDraft();
    });
    banCount?.addEventListener('change', updateDraftBanRows);
    sportSelect?.addEventListener('change', updateDraftCategories);
    form?.addEventListener('reset', () => {
      window.setTimeout(() => {
        updateDraftBanRows();
        updateDraftCategories();
        lastDraftSummary = '';
        if (previewEmpty) previewEmpty.hidden = false;
        if (draftSheet) draftSheet.hidden = true;
        if (copyButton) copyButton.disabled = true;
        if (message) {
          message.classList.remove('is-success');
          message.textContent = '';
        }
      }, 0);
    });
    copyButton?.addEventListener('click', async () => {
      if (!lastDraftSummary) return;
      try {
        if (!navigator.clipboard?.writeText) throw new Error('Clipboard API unavailable');
        await navigator.clipboard.writeText(lastDraftSummary);
        if (message) {
          message.classList.add('is-success');
          message.textContent = 'Draft summary copied.';
        }
      } catch (_) {
        const textarea = document.createElement('textarea');
        textarea.value = lastDraftSummary;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();
        if (message) {
          message.classList.toggle('is-success', copied);
          message.textContent = copied ? 'Draft summary copied.' : 'Copy is unavailable in this browser.';
        }
      }
    });

    updateDraftBanRows();
    updateDraftCategories();
  }

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
    let bracketFrame = 0;
    const redrawBrackets = () => {
      if (bracketFrame) window.cancelAnimationFrame(bracketFrame);
      bracketFrame = window.requestAnimationFrame(() => {
        bracketFrame = 0;
        bracketBoards.forEach(drawBracketBoard);
      });
    };

    const bracketZoomMin = 0.7;
    const bracketZoomMax = 1.4;
    const bracketZoomStep = 0.1;
    const cssZoomSupported = typeof CSS !== 'undefined' && CSS.supports?.('zoom', '1');
    const clampBracketZoom = (value) => Math.min(bracketZoomMax, Math.max(bracketZoomMin, Math.round(value * 10) / 10));

    const setBracketZoom = (component, requestedZoom) => {
      const board = component.querySelector('[data-bracket-board]');
      const shell = component.querySelector('[data-bracket-zoom-shell]');
      if (!board || !shell) return;

      const zoom = clampBracketZoom(requestedZoom);
      board.dataset.bracketZoom = String(zoom);
      if (cssZoomSupported) {
        board.style.zoom = String(zoom);
        board.style.transform = '';
        shell.style.width = '';
        shell.style.height = '';
      } else {
        board.style.zoom = '';
        board.style.transform = `scale(${zoom})`;
        shell.style.width = `${Math.ceil(board.scrollWidth * zoom)}px`;
        shell.style.height = `${Math.ceil(board.scrollHeight * zoom)}px`;
      }

      const value = component.querySelector('[data-bracket-zoom-value]');
      const zoomOut = component.querySelector('[data-bracket-zoom-out]');
      const zoomIn = component.querySelector('[data-bracket-zoom-in]');
      if (value) value.textContent = `${Math.round(zoom * 100)}%`;
      if (zoomOut) zoomOut.disabled = zoom <= bracketZoomMin;
      if (zoomIn) zoomIn.disabled = zoom >= bracketZoomMax;
      redrawBrackets();
    };

    document.querySelectorAll('[data-bracket-component]').forEach((component) => {
      const board = component.querySelector('[data-bracket-board]');
      if (!board) return;
      const currentZoom = () => Number.parseFloat(board.dataset.bracketZoom || '1') || 1;
      component.querySelector('[data-bracket-zoom-out]')?.addEventListener('click', () => setBracketZoom(component, currentZoom() - bracketZoomStep));
      component.querySelector('[data-bracket-zoom-in]')?.addEventListener('click', () => setBracketZoom(component, currentZoom() + bracketZoomStep));
      component.querySelector('[data-bracket-zoom-reset]')?.addEventListener('click', () => setBracketZoom(component, 1));
      setBracketZoom(component, 1);
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

    if (document.fonts?.ready) {
      document.fonts.ready.then(redrawBrackets).catch(() => {});
    }

    if ('ResizeObserver' in window) {
      const bracketResizeObserver = new ResizeObserver(redrawBrackets);
      bracketBoards.forEach((board) => bracketResizeObserver.observe(board));
    }
  }
})();
