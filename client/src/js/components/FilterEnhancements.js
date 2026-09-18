// Enhanced Filter Experience
export class FilterEnhancements {
    constructor()
    {
        this.initActiveFilterTracking();
        this.initCollapsibleFilters();
    }

    // Arrow-key field cycling is deliberately absent from this form: see #158 and #219.

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
            // search_terms is not a filter field either: Choices.js clones the .js-choice
            // multi-select into a container inside this form, and the clone it injects as the
            // search box carries that name (choices.js 10.2.0, templates.input), so its
            // input/change events bubble to the handlers below just like a real field's.
            if (fieldName === 'SecurityID' || fieldName === 'action_doFilter' || fieldName === 'search_terms') {
                return;
            }

            const isActive = fieldValue && fieldValue.trim() !== '';
            // A key absent from the snapshot - an untouched <select multiple> is the case this
            // form actually renders - yields null from get(), and null?.trim() is undefined,
            // which compares unequal to '' forever: the field reads as "already counted".
            // Coalescing fixes that, and coalescing is all it fixes: a checkbox would need
            // more, because event.target.value reads "on" whether or not the box is checked,
            // so isActive would hold permanently and unticking it would never decrement.
            // CalendarFilterForm renders no checkbox today. (#159)
            const fieldPreviouslyActive = (formData.get(fieldName) ?? '').trim() !== '';

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
