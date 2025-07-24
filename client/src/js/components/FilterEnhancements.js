// Enhanced Filter Experience
export class FilterEnhancements {
  constructor() {
    this.initCollapsibleFilters();
    this.initActiveFilterTracking();
    this.initKeyboardSupport();
    this.initFilterMemory();
  }

  initCollapsibleFilters() {
    const filterForm = document.querySelector('.calendar-filter-form');
    if (!filterForm) return;

    const header = filterForm.querySelector('.filter-header');
    const collapseTarget = filterForm.querySelector('#calendar-filters-content');

    if (!header || !collapseTarget) return;

    // Auto-expand if there are active filters
    const hasActiveFilters = filterForm.querySelector('.badge[aria-label="Active filters"]');
    if (hasActiveFilters) {
      collapseTarget.classList.add('show');
      header.setAttribute('aria-expanded', 'true');
    }

    // Enhanced collapse behavior
    collapseTarget.addEventListener('show.bs.collapse', () => {
      header.setAttribute('aria-expanded', 'true');
      this.focusFirstInput(collapseTarget);
      this.trackFilterAction('expand');
    });

    collapseTarget.addEventListener('hide.bs.collapse', () => {
      header.setAttribute('aria-expanded', 'false');
      this.trackFilterAction('collapse');
    });
  }

  initActiveFilterTracking() {
    const form = document.querySelector('.calendar-filter-form form');
    if (!form) return;

    // Track active filters and update badge
    const updateActiveFiltersBadge = () => {
      const formData = new FormData(form);
      let activeCount = 0;

      for (let [key, value] of formData.entries()) {
        if (key === 'SecurityID' || key === 'action_doFilter') continue;
        if (value && value.trim() !== '') {
          activeCount++;
        }
      }

      this.updateFilterBadge(activeCount);
    };

    // Listen for form changes
    form.addEventListener('change', updateActiveFiltersBadge);
    form.addEventListener('input', this.debounce(updateActiveFiltersBadge, 300));
  }

  updateFilterBadge(count) {
    const header = document.querySelector('.filter-header h5');
    if (!header) return;

    let badge = header.querySelector('.badge');

    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge bg-primary ms-2';
        badge.setAttribute('aria-label', 'Active filters');
        header.appendChild(badge);
      }
      badge.textContent = count;
    } else if (badge) {
      badge.remove();
    }
  }

  initKeyboardSupport() {
    const header = document.querySelector('.filter-header');
    if (!header) return;

    header.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        header.click();
      }
    });

    // Add keyboard navigation within filters
    this.setupFormKeyboardNav();
  }

  setupFormKeyboardNav() {
    const form = document.querySelector('.calendar-filter-form form');
    if (!form) return;

    const focusableElements = form.querySelectorAll(
      'input, select, button, [tabindex]:not([tabindex="-1"])'
    );

    focusableElements.forEach((element, index) => {
      element.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          const nextIndex = (index + 1) % focusableElements.length;
          focusableElements[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          const prevIndex = (index - 1 + focusableElements.length) % focusableElements.length;
          focusableElements[prevIndex].focus();
        }
      });
    });
  }

  initFilterMemory() {
    const form = document.querySelector('.calendar-filter-form form');
    if (!form) return;

    // Remember filter state in localStorage
    const saveFilters = () => {
      const formData = new FormData(form);
      const filters = {};

      for (let [key, value] of formData.entries()) {
        if (key !== 'SecurityID' && key !== 'action_doFilter') {
          filters[key] = value;
        }
      }

      localStorage.setItem('calendar-filters', JSON.stringify(filters));
    };

    // Auto-save on change
    form.addEventListener('change', saveFilters);
  }

  focusFirstInput(container) {
    const firstInput = container.querySelector('input, select');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 150);
    }
  }

  trackFilterAction(action) {
    // Analytics tracking for filter usage
    if (typeof gtag !== 'undefined') {
      gtag('event', 'calendar_filter', {
        event_category: 'engagement',
        event_label: action
      });
    }
  }

  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new FilterEnhancements();
});
