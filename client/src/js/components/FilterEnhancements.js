// Elements that can take keyboard focus within the filter form.
const FOCUSABLE_SELECTOR = 'input, select, textarea, a[href], button, [tabindex]:not([tabindex="-1"])';

// Input types whose native ArrowUp/ArrowDown handling changes their own value,
// so the form's focus navigation must leave those keys alone.
const NATIVE_ARROW_INPUT_TYPES = [
    'date',
    'datetime-local',
    'month',
    'week',
    'time',
    'number',
    'range',
    'radio'
];

// Enhanced Filter Experience
export class FilterEnhancements {
    constructor()
    {
        this.initActiveFilterTracking();
        this.initCollapsibleFilters();
        this.initKeyboardSupport();
    }

    initCollapsibleFilters()
    {
        const header = document.querySelector('.js-toggle-filters');
        const collapseTarget = document.querySelector('#calendar-filters');

        if (!header || !collapseTarget) {
            return;
        }

      // Auto-expand if there are active filters
        const hasActiveFilters = header.querySelector('.badge[aria-label="Active filters"]');
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

    initActiveFilterTracking()
    {
        const form = document.querySelector('.calendar-filter-form');
        if (!form) {
            return;
        }

        let activeCount = 0;
        const formData = new FormData(form);

      // Initialize active count
        for (let [key, value] of formData.entries()) {
            if (key === 'SecurityID' || key === 'action_doFilter') {
                continue;
            }
            if (value && value.trim() !== '') {
                activeCount++;
            }
        }

        this.updateFilterBadge(activeCount);

        const updateActiveFiltersBadge = (fieldName, fieldValue) => {
            if (fieldName === 'SecurityID' || fieldName === 'action_doFilter') {
                return;
            }

            const isActive = fieldValue && fieldValue.trim() !== '';
            const fieldPreviouslyActive = formData.get(fieldName)?.trim() !== '';

            if (isActive && !fieldPreviouslyActive) {
                activeCount++;
            } else if (!isActive && fieldPreviouslyActive) {
                activeCount--;
            }

            formData.set(fieldName, fieldValue);
            this.updateFilterBadge(activeCount);
        };

      // Listen for form changes
        form.addEventListener('change', (event) => {
            const { name, value } = event.target;
            updateActiveFiltersBadge(name, value);
        });
        form.addEventListener('input', this.debounce((event) => {
            const { name, value } = event.target;
            updateActiveFiltersBadge(name, value);
        }, 300));
    }

    updateFilterBadge(count)
    {
        const header = document.querySelector('.js-toggle-filters');
        if (!header) {
            return;
        }

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

    initKeyboardSupport()
    {
      // Add keyboard navigation within filters
        this.setupFormKeyboardNav();
    }

    setupFormKeyboardNav()
    {
        const form = document.querySelector('.calendar-filter-form');
        if (!form) {
            return;
        }

      // Delegated on the form and re-queried per event, so fields that are replaced
      // after page load (Choices.js builds its own widget around `categories`)
      // take part in navigation instead of being looked up from a stale list.
        form.addEventListener('keydown', (e) => {
            this.handleFilterFormArrowNav(e, form);
        });
    }

    handleFilterFormArrowNav(e, form)
    {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') {
            return;
        }

      // Leave browser and OS shortcuts (Cmd+Arrow, Ctrl+Arrow, ...) untouched.
        if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
            return;
        }

        const target = e.target;

      // <select> cycles its options and native date/time/number/range inputs step
      // their value with these keys - returning here keeps that native behaviour,
      // because preventDefault() below would otherwise swallow it.
        if (!(target instanceof HTMLElement) || this.hasNativeArrowBehaviour(target)) {
            return;
        }

        const focusableElements = this.getFocusableFields(form);

        if (focusableElements.length < 2) {
            return;
        }

        const currentIndex = focusableElements.indexOf(target);

        if (currentIndex === -1) {
            return;
        }

        const offset = e.key === 'ArrowDown' ? 1 : -1;
        const nextIndex = (currentIndex + offset + focusableElements.length) % focusableElements.length;
        const nextElement = focusableElements[nextIndex];

        nextElement.focus();

      // Only cancel the key if focus actually moved. A focus() that no-ops would
      // otherwise leave the user with a dead arrow key: nothing focused, nothing scrolled.
        if (document.activeElement === nextElement) {
            e.preventDefault();
        }
    }

    getFocusableFields(form)
    {
        return Array.from(form.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
            (element) => this.isFocusableElement(element)
        );
    }

    hasNativeArrowBehaviour(element)
    {
        if (element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
            return true;
        }

      // Choices.js renders its own widget and drives its keyboard interaction.
        if (element.closest('.choices')) {
            return true;
        }

        if (element.tagName === 'INPUT') {
            // A datalist input opens its popup with these keys.
            if (element.hasAttribute('list')) {
                return true;
            }

            const type = (element.getAttribute('type') || 'text').toLowerCase();
            return NATIVE_ARROW_INPUT_TYPES.includes(type);
        }

        return false;
    }

    isFocusableElement(element)
    {
      // :disabled also matches controls disabled by an ancestor <fieldset>, which the
      // element's own disabled property does not reflect.
        if (element.matches(':disabled')) {
            return false;
        }

        if (element.hidden || element.getAttribute('tabindex') === '-1') {
            return false;
        }

      // getClientRects() is non-empty for visibility:hidden elements, which cannot take
      // focus; checkVisibility() is the part that catches them.
        if (typeof element.checkVisibility === 'function'
            && !element.checkVisibility({ visibilityProperty: true })) {
            return false;
        }

      // An inert subtree is neither focusable nor clickable, yet reports itself visible
      // and enabled and still has client rects.
        if (element.closest('[inert]')) {
            return false;
        }

      // Inside a Choices.js widget the remove buttons on selected items and the dropdown
      // rows are not destinations: focus would land on a control that the passthrough rule
      // above then refuses to navigate away from. Everything else in the widget stays
      // eligible, which covers both the multiple-select search box and the tabbable
      // container a single-select instance renders.
        if (element.closest('.choices')
            && element.matches('.choices__button, [data-choice-selectable]')) {
            return false;
        }

        return element.getClientRects().length > 0;
    }

    focusFirstInput(container)
    {
        const firstInput = container.querySelector('input, select');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 150);
        }
    }

    trackFilterAction(action)
    {
      // Analytics tracking for filter usage
        if (typeof gtag !== 'undefined') {
            gtag('event', 'calendar_filter', {
                event_category: 'engagement',
                event_label: action
            });
        }
    }

    debounce(func, wait)
    {
        let timeout;
        return function executedFunction(...args)
        {
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
