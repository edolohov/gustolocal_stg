(function() {
  'use strict';

  // Ensure menu is closed on page load
  document.addEventListener('DOMContentLoaded', function() {
    const navigation = document.querySelector('.gl-navigation');
    const toggle = document.querySelector('[data-gl-mobile-toggle]');
    
    if (navigation) {
      // Ensure navigation has id for JavaScript targeting
      if (!navigation.id) {
        navigation.id = 'gl-navigation';
      }
      navigation.classList.remove('is-open');
    }
    
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Toggle navigation menu');
    }
    
    // Close WordPress navigation block's own menu state
    const wpContainer = navigation && navigation.querySelector('.wp-block-navigation__responsive-container');
    if (wpContainer) {
      wpContainer.classList.remove('is-menu-open', 'has-modal-open');
    }
    
    // Ensure body scroll is enabled
    document.body.style.overflow = '';
  });

  // Mobile navigation toggle - use event delegation for reliability
  function handleToggleClick(event) {
    console.log('Toggle clicked!', event);
    event.preventDefault();
    event.stopPropagation();
    
    const toggle = event.currentTarget;
    console.log('Toggle element:', toggle);
    
    // Try to find navigation by class first (more reliable)
    let target = document.querySelector('.gl-navigation');
    
    // If not found, try by data-target attribute
    if (!target) {
      const targetSelector = toggle.getAttribute('data-target');
      if (targetSelector) {
        target = document.querySelector(targetSelector);
      }
    }
    
    // If still not found, try by aria-controls
    if (!target) {
      const ariaControls = toggle.getAttribute('aria-controls');
      if (ariaControls) {
        target = document.querySelector('#' + ariaControls) || document.querySelector('.' + ariaControls);
      }
    }
    
    if (!target) {
      console.warn('Navigation element not found. Tried: .gl-navigation, data-target, aria-controls');
      return;
    }
    
    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
    
    // Toggle navigation
    target.classList.toggle('is-open');
    const isNowOpen = target.classList.contains('is-open');
    console.log('Menu toggled. is-open:', isNowOpen, 'Navigation element:', target);
    toggle.setAttribute('aria-expanded', isNowOpen);
    
    // Force show/hide WordPress navigation container
    const wpContainer = target.querySelector('.wp-block-navigation__responsive-container');
    const wpDialog = target.querySelector('.wp-block-navigation__responsive-dialog');
    const wpContent = target.querySelector('.wp-block-navigation__responsive-container-content');
    
    if (wpContainer) {
      if (isNowOpen) {
        // Force show container when menu is open
        wpContainer.style.display = 'block';
        wpContainer.style.visibility = 'visible';
        wpContainer.style.opacity = '1';
        wpContainer.classList.add('is-menu-open', 'has-modal-open');
        
        // Also show dialog and content
        if (wpDialog) {
          wpDialog.style.display = 'block';
          wpDialog.style.visibility = 'visible';
          wpDialog.style.opacity = '1';
        }
        if (wpContent) {
          wpContent.style.display = 'block';
          wpContent.style.visibility = 'visible';
          wpContent.style.opacity = '1';
        }
      } else {
        // Force hide container when menu is closed
        wpContainer.style.display = 'none';
        wpContainer.style.visibility = 'hidden';
        wpContainer.style.opacity = '0';
        wpContainer.classList.remove('is-menu-open', 'has-modal-open');
        
        if (wpDialog) {
          wpDialog.style.display = 'none';
          wpDialog.style.visibility = 'hidden';
          wpDialog.style.opacity = '0';
        }
        if (wpContent) {
          wpContent.style.display = 'none';
          wpContent.style.visibility = 'hidden';
          wpContent.style.opacity = '0';
        }
      }
    }
    
    // Prevent body scroll when menu is open
    if (isNowOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    
    // Update button icon
    const svg = toggle.querySelector('svg');
    if (svg && !isExpanded) {
      toggle.setAttribute('aria-label', 'Close navigation menu');
    } else if (svg && isExpanded) {
      toggle.setAttribute('aria-label', 'Toggle navigation menu');
    }
  }

  // Attach event listeners when DOM is ready
  function initNavigation() {
    console.log('Initializing navigation...');
    const toggles = document.querySelectorAll('[data-gl-mobile-toggle]');
    console.log('Found toggles:', toggles.length);
    
    toggles.forEach(function(toggle) {
      console.log('Attaching listener to toggle:', toggle);
      // Remove any existing listeners
      toggle.removeEventListener('click', handleToggleClick);
      // Add new listener with capture phase
      toggle.addEventListener('click', handleToggleClick, true);
    });
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavigation);
  } else {
    initNavigation();
  }

  // Also initialize after a short delay to catch dynamically added elements
  setTimeout(initNavigation, 100);
  setTimeout(initNavigation, 500);
  setTimeout(initNavigation, 1000);

  // Close navigation when clicking outside or on overlay
  document.addEventListener('click', function(event) {
    const navigation = document.querySelector('.gl-navigation');
    const toggle = document.querySelector('[data-gl-mobile-toggle]');
    
    if (!navigation || !toggle) return;
    
    const isOpen = navigation.classList.contains('is-open');
    if (!isOpen) return;
    
    // Check if click is on toggle button
    if (toggle.contains(event.target)) return;
    
    // Check if click is inside the white menu panel
    const menuPanel = navigation.querySelector('.wp-block-navigation__responsive-dialog') || 
                      navigation.querySelector('.wp-block-navigation__responsive-container-content') ||
                      navigation.querySelector('ul');
    const isClickOnMenuPanel = menuPanel && menuPanel.contains(event.target);
    
    // Check if click is on the responsive container itself (overlay)
    const responsiveContainer = navigation.querySelector('.wp-block-navigation__responsive-container');
    const isClickOnContainer = responsiveContainer && responsiveContainer === event.target;
    
    // Close if click is on overlay (dark background) but not on menu panel
    if (!isClickOnMenuPanel && !isClickOnContainer) {
      navigation.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Toggle navigation menu');
      document.body.style.overflow = '';
    }
  });

  // Close navigation on escape key
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
      const navigation = document.querySelector('.gl-navigation');
      const toggle = document.querySelector('[data-gl-mobile-toggle]');
      
      if (navigation && navigation.classList.contains('is-open')) {
        navigation.classList.remove('is-open');
        document.body.style.overflow = '';
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
          toggle.setAttribute('aria-label', 'Toggle navigation menu');
          toggle.focus();
        }
      }
    }
  });
})();

