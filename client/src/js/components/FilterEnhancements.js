// Enhanced Filter Experience
export class FilterEnhancements {
    constructor()
    {
        this.initActiveFilterTracking();
        this.initCollapsibleFilters();
    }

    // No arrow-key field cycling lives here any more. It used to bind ArrowUp/ArrowDown to
    // every focusable element in `.calendar-filter-form` and call preventDefault() before the
    // browser could act, which swallowed the native option cycling of the DropdownField
    // <select>s and the segment stepping of the DateField <input type="date">s - that is the
    // bug this removes, see dynamic/silverstripe-calendar#158.
    //
    // Guarding it instead was tried and does not work on the form this module renders: every
    // field in it is a <select>, a native date input, or a Choices.js widget that owns its own
    // arrow keys, and submit buttons and links must stay out of any ring because landing on
    // them makes the next Enter destructive. What is left is the search box alone, so a
    // guarded ring is provably either a no-op or a key hog that steals caret movement from the
    // only text field on the form. Tab already walks these fields. The wider question of
    // whether the module should offer arrow navigation at all, and over what, is tracked in
    // dynamic/silverstripe-calendar#219.

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
