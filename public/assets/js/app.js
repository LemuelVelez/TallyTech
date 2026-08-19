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
