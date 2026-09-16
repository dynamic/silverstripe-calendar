// Elements that can take keyboard focus within the filter form.
const FOCUSABLE_SELECTOR = 'input, select, textarea, a[href], button, [tabindex]:not([tabindex="-1"])';

// Input types whose native ArrowUp/ArrowDown handling changes their own value, so the
// form's focus navigation must leave those keys alone. The same test decides ring
// membership, so a field in the ring is always one the handler can navigate away from.
const NATIVE_ARROW_INPUT_TYPES = [
    'date',
    'datetime',
    'datetime-local',
    'month',
    'week',
    'time',
    'number',
    'range',
    'radio',
    'search'
];

// Controls that *do* something rather than *hold* something: submitting the form, following
// a link. An arrow key must never park focus on one of them, because the next Enter or Space
// then fires it - so they are excluded from ring membership entirely. <button> counts even
// without a type attribute, since a bare <button> inside a form submits it.
const ACTIVATING_SELECTOR = 'button, input[type="submit"], input[type="reset"], a[href], [role="button"], .form-actions';

// WAI-ARIA roles that define their own ArrowUp/ArrowDown contract, so anything inside
// one of them keeps its keys for the widget instead of for field cycling.
const NATIVE_ARROW_ROLE_SELECTOR = '[role="combobox"], [role="listbox"], [role="menu"], '
    + '[role="menubar"], [role="radiogroup"], [role="tree"], [role="grid"]';

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

      // Bound once per form, so a second construction (an AJAX-refreshed form, for
      // instance) cannot stack a second listener and step the ring twice per keypress.
      // Mirrors the `data-choices-initialized` guard in calendar.js.
        if (form.dataset.arrowNavBound === 'true') {
            return;
        }

        form.dataset.arrowNavBound = 'true';

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

      // A held arrow key belongs to the focused control (caret movement, option
      // stepping, IME candidate paging) - only discrete presses drive the ring.
        if (e.repeat) {
            return;
        }

      // While an IME composition is open the arrow keys page its candidate list.
        if (e.isComposing) {
            return;
        }

      // Leave browser and OS shortcuts (Cmd+Arrow, Ctrl+Arrow, ...) untouched.
        if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
            return;
        }

      // A widget that already claimed the key keeps it, even one this handler has
      // never heard of: this listener sits on the form and sees everything.
        if (e.defaultPrevented) {
            return;
        }

        const target = e.target;

      // The ring is indexed against the focused control, so a keydown that merely
      // bubbled up from an unfocused element has no position in it to step from.
        if (!(target instanceof HTMLElement) || target !== document.activeElement) {
            return;
        }

      // <select> cycles its options and native date/time/number/range inputs step
      // their value with these keys - returning here keeps that native behavior,
      // because preventDefault() below would otherwise swallow it.
        if (this.hasNativeArrowBehavior(target)) {
            return;
        }

        const ring = this.getArrowNavigationTargets(form);

      // Navigation needs at least two *fields* to be navigation. With fewer the keys
      // belong to the focused control, and on a form whose only plain field is the
      // search box this makes the handler a deliberate no-op rather than a shortcut
      // onto its submit button.
        if (ring.length < 2) {
            return;
        }

        const currentIndex = ring.indexOf(target);

        if (currentIndex === -1) {
            return;
        }

      // Cycling wraps from the last control to the first so the ring has no dead end.
        const offset = e.key === 'ArrowDown' ? 1 : -1;
        const length = ring.length;
        const previous = document.activeElement;

      // Step to the next candidate that actually takes focus. The key stays
      // uncancelled unless one does, so a refused focus still scrolls the page
      // normally instead of landing on a dead key.
        for (let attempt = 1; attempt < length; attempt++) {
            const candidate = ring[(currentIndex + offset * attempt + length) % length];

            candidate.focus();

            if (this.hasReceivedFocus(candidate, previous)) {
                e.preventDefault();
                return;
            }
        }

      // Nothing in the ring would take focus. Each focus() above blurs the source, so
      // return it here rather than stranding the user with focus on <body>, and leave
      // the key uncancelled so the arrow still does its native thing.
        target.focus();
    }

    hasReceivedFocus(candidate, previous)
    {
      // Focus has to have left `previous` first. Without that, a ring member which is an
      // ancestor of the focused control - a `[tabindex="0"]` wrapper, say - satisfies the
      // contains() test without focus having moved at all, and the key gets swallowed on
      // a step that did nothing.
        if (document.activeElement === previous) {
            return false;
        }

      // A destination that forwards focus to a descendant has still taken it, and the key
      // must be cancelled for that too or the page scrolls as well as moving focus.
        return candidate === document.activeElement || candidate.contains(document.activeElement);
    }

    getArrowNavigationTargets(form)
    {
      // The ring holds only the fields this handler is also willing to navigate *away*
      // from. Applying the native-arrow test to membership as well as to the keypress is
      // what stops arrow nav becoming a one-way trap, where focus lands on a date field
      // it then refuses to leave. Tab stays the way to reach those fields, and it also
      // covers Choices.js internals, which all sit inside `.choices` and so never need
      // naming here. Buttons, submit inputs and links are out for a different reason:
      // landing on them makes the following Enter destructive.
        return this.getFocusableFields(form).filter(
            (element) => !this.hasNativeArrowBehavior(element)
                && !element.matches(ACTIVATING_SELECTOR)
                && !element.closest('.form-actions')
        );
    }

    getFocusableFields(form)
    {
        return Array.from(form.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
            (element) => this.isFocusableElement(element)
        );
    }

    hasNativeArrowBehavior(element)
    {
        if (element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
            return true;
        }

      // Choices.js renders its own widget and drives its keyboard interaction.
        if (element.closest('.choices')) {
            return true;
        }

      // A rich-text or ARIA composite widget owns its own arrow keys.
        if (element.isContentEditable || element.closest(NATIVE_ARROW_ROLE_SELECTOR)) {
            return true;
        }

        if (element.tagName === 'INPUT') {
            // A datalist input opens its popup with these keys.
            if (element.hasAttribute('list')) {
                return true;
            }

            // element.type normalises an absent, empty or unknown type to "text".
            return NATIVE_ARROW_INPUT_TYPES.includes(element.type);
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

      // tabIndex is the parsed number the browser honours; comparing the attribute as a
      // string misses values like " -1" and "-01" that parse to the same thing.
        if (element.hidden || element.tabIndex < 0) {
            return false;
        }

      // Computed style rather than checkVisibility(): that method's option for this was
      // spelled `checkVisibilityCSS` before Chrome 119 / Safari 17.4, so passing
      // `visibilityProperty` to an older engine silently returns true for a
      // visibility:hidden element. Computed visibility is inherited, so this catches an
      // element hidden only through an ancestor too.
        const style = window.getComputedStyle(element);

        if (style.visibility === 'hidden' || style.display === 'none') {
            return false;
        }

      // An inert subtree is neither focusable nor clickable, yet reports itself visible
      // and enabled and still has client rects.
        if (element.closest('[inert]')) {
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
