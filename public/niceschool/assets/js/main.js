/**
* Template Name: NiceSchool
* Template URL: https://bootstrapmade.com/nice-school-bootstrap-education-template/
* Updated: May 10 2025 with Bootstrap v5.3.6
* Author: BootstrapMade.com
* License: https://bootstrapmade.com/license/
*/

(function() {
  "use strict";

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector('body');
    const selectHeader = document.querySelector('#header');
    if (!selectHeader.classList.contains('scroll-up-sticky') && !selectHeader.classList.contains('sticky-top') && !selectHeader.classList.contains('fixed-top')) return;
    window.scrollY > 100 ? selectBody.classList.add('scrolled') : selectBody.classList.remove('scrolled');
  }

  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const selectBody = document.querySelector('body');
  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');
  const mobileNavToggleIcon = mobileNavToggleBtn?.querySelector('i');

  function mobileNavToggle(forceOpen = null, restoreFocus = false) {
    if (!mobileNavToggleBtn) return;

    const shouldOpen = forceOpen ?? !selectBody.classList.contains('mobile-nav-active');
    selectBody.classList.toggle('mobile-nav-active', shouldOpen);
    mobileNavToggleIcon?.classList.toggle('bi-list', !shouldOpen);
    mobileNavToggleIcon?.classList.toggle('bi-x', shouldOpen);
    mobileNavToggleBtn.setAttribute('aria-expanded', String(shouldOpen));
    mobileNavToggleBtn.setAttribute('aria-label', shouldOpen ? 'Cerrar menú' : 'Abrir menú');

    if (shouldOpen) {
      window.requestAnimationFrame(() => {
        document.querySelector('#navmenu a')?.focus({ preventScroll: true });
      });
    } else if (restoreFocus) {
      mobileNavToggleBtn.focus({ preventScroll: true });
    }
  }
  if (mobileNavToggleBtn) {
    mobileNavToggleBtn.addEventListener('click', () => mobileNavToggle());
  }

  /**
   * Hide mobile nav on same-page/hash links
   */
  document.querySelectorAll('#navmenu a').forEach(navmenu => {
    navmenu.addEventListener('click', () => {
      if (selectBody.classList.contains('mobile-nav-active') && !navmenu.querySelector('.toggle-dropdown')) {
        mobileNavToggle(false);
      }
    });

  });

  /**
   * Toggle mobile nav dropdowns
   */
  document.querySelectorAll('.navmenu .toggle-dropdown').forEach(navmenu => {
    navmenu.addEventListener('click', function(e) {
      e.preventDefault();
      this.parentNode.classList.toggle('active');
      this.parentNode.nextElementSibling.classList.toggle('dropdown-active');
      const expanded = this.parentNode.nextElementSibling.classList.contains('dropdown-active');
      this.parentNode.setAttribute('aria-expanded', String(expanded));
      this.setAttribute('aria-label', expanded ? 'Cerrar opciones de Colegio' : 'Abrir opciones de Colegio');
      e.stopImmediatePropagation();
    });
  });

  /**
   * Keep desktop dropdowns discoverable and accurately announced by keyboard.
   */
  document.querySelectorAll('.navmenu .dropdown').forEach(dropdown => {
    const trigger = dropdown.querySelector(':scope > a');
    if (!trigger) return;

    const setDesktopDropdownState = expanded => {
      if (window.innerWidth >= 1200) {
        trigger.setAttribute('aria-expanded', String(expanded));
      }
    };

    dropdown.addEventListener('mouseenter', () => setDesktopDropdownState(true));
    dropdown.addEventListener('mouseleave', () => setDesktopDropdownState(false));
    dropdown.addEventListener('focusin', () => setDesktopDropdownState(true));
    dropdown.addEventListener('focusout', event => {
      if (!dropdown.contains(event.relatedTarget)) {
        setDesktopDropdownState(false);
      }
    });
  });

  document.addEventListener('keydown', event => {
    if (!selectBody.classList.contains('mobile-nav-active')) return;

    if (event.key === 'Escape') {
      mobileNavToggle(false, true);
      return;
    }

    if (event.key !== 'Tab') return;

    const focusableItems = Array.from(document.querySelectorAll('#navmenu a[href], #navmenu button:not([disabled])'))
      .filter(item => item.getClientRects().length > 0 && item.getAttribute('aria-hidden') !== 'true');

    if (!focusableItems.length) return;

    const firstItem = focusableItems[0];
    const lastItem = focusableItems[focusableItems.length - 1];

    if (event.shiftKey && document.activeElement === firstItem) {
      event.preventDefault();
      lastItem.focus();
    } else if (!event.shiftKey && document.activeElement === lastItem) {
      event.preventDefault();
      firstItem.focus();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 1200 && selectBody.classList.contains('mobile-nav-active')) {
      mobileNavToggle(false);
    }
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector('#preloader');
  if (preloader) {
    window.addEventListener('load', () => {
      preloader.remove();
    });
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  if (scrollTop) {
    scrollTop.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({
        top: 0,
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
      });
    });
  }

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);

  /**
   * Animation on scroll function and init
   */
  function aosInit() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    AOS.init({
      duration: reduceMotion ? 0 : 600,
      easing: 'ease-in-out',
      once: true,
      mirror: false,
      disable: reduceMotion || window.innerWidth < 768
    });
  }
  window.addEventListener('load', aosInit);

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('video[autoplay]').forEach((video) => video.pause());
  }

  /**
   * Initiate Pure Counter
   */
  new PureCounter();

  /**
   * Init isotope layout and filters
   */
  document.querySelectorAll('.isotope-layout').forEach(function(isotopeItem) {
    let layout = isotopeItem.getAttribute('data-layout') ?? 'masonry';
    let filter = isotopeItem.getAttribute('data-default-filter') ?? '*';
    let sort = isotopeItem.getAttribute('data-sort') ?? 'original-order';

    let initIsotope;
    imagesLoaded(isotopeItem.querySelector('.isotope-container'), function() {
      initIsotope = new Isotope(isotopeItem.querySelector('.isotope-container'), {
        itemSelector: '.isotope-item',
        layoutMode: layout,
        filter: filter,
        sortBy: sort
      });
    });

    isotopeItem.querySelectorAll('.isotope-filters li').forEach(function(filters) {
      filters.addEventListener('click', function() {
        isotopeItem.querySelector('.isotope-filters .filter-active').classList.remove('filter-active');
        this.classList.add('filter-active');
        initIsotope.arrange({
          filter: this.getAttribute('data-filter')
        });
        if (typeof aosInit === 'function') {
          aosInit();
        }
      }, false);
    });

  });

  /**
   * Init swiper sliders
   */
  function initSwiper() {
    document.querySelectorAll(".init-swiper").forEach(function(swiperElement) {
      let config = JSON.parse(
        swiperElement.querySelector(".swiper-config").innerHTML.trim()
      );

      if (swiperElement.classList.contains("swiper-tab")) {
        initSwiperWithCustomPagination(swiperElement, config);
      } else {
        new Swiper(swiperElement, config);
      }
    });
  }

  window.addEventListener("load", initSwiper);

  /**
   * Initiate glightbox
   */
  const glightbox = GLightbox({
    selector: '.glightbox'
  });

})();
