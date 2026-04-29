document.addEventListener('DOMContentLoaded', function() {
  const burgerBtn = document.getElementById('burger-menu-btn');
  const mobileOverlay = document.getElementById('mobile-menu-overlay');
  const mobilePanel = document.querySelector('.mobile-menu-panel');
  const mobileNavItems = document.querySelectorAll('.mobile-nav-item');

  if (burgerBtn) {
    burgerBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleMobileMenu();
    });
  }

  if (mobileOverlay) {
    mobileOverlay.addEventListener('click', function(e) {
      if (e.target === mobileOverlay) {
        closeMobileMenu();
      }
    });
  }

  mobileNavItems.forEach(item => {
    item.addEventListener('click', function() {
      setTimeout(() => {
        closeMobileMenu();
      }, 150);
    });
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && mobileOverlay && mobileOverlay.classList.contains('active')) {
      closeMobileMenu();
    }
  });

  window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
      closeMobileMenu();
    }
  });

  function preventScroll(e) {
    e.preventDefault();
  }

  function toggleMobileMenu() {
    if (mobileOverlay && mobileOverlay.classList.contains('active')) {
      closeMobileMenu();
    } else {
      openMobileMenu();
    }
  }

  function openMobileMenu() {
    if (burgerBtn) {
      burgerBtn.classList.add('active');
      burgerBtn.setAttribute('aria-expanded', 'true');
    }

    if (mobileOverlay) {
      mobileOverlay.classList.add('active');
      mobileOverlay.setAttribute('aria-hidden', 'false');
    }

  }

  function openMobileMenu() {
  }

  function closeMobileMenu() {
    if (burgerBtn) {
      burgerBtn.classList.remove('active');
      burgerBtn.setAttribute('aria-expanded', 'false');
    }

    if (mobileOverlay) {
      mobileOverlay.classList.remove('active');
      mobileOverlay.setAttribute('aria-hidden', 'true');
    }

    document.body.style.overflow = '';
  }

  const darkToggleMobile = document.getElementById('dark-toggle-mobile');
  if (darkToggleMobile) {
    darkToggleMobile.addEventListener('click', function() {
      const darkToggle = document.getElementById('dark-toggle');
      if (darkToggle) {
        darkToggle.click();
      }
    });
  }
});
