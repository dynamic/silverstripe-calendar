// Keyboard Navigation Component
// Provides comprehensive keyboard accessibility

export class KeyboardNavigation {
  constructor() {
    this.initEventCardNavigation();
    this.initCalendarNavigation();
    this.initFilterNavigation();
    this.initSkipLinks();
    this.currentFocusIndex = 0;
  }

  initEventCardNavigation() {
    const eventCards = document.querySelectorAll('.event-card[tabindex="0"]');

    eventCards.forEach((card, index) => {
      card.addEventListener('keydown', (e) => {
        switch (e.key) {
          case 'Enter':
          case ' ':
            e.preventDefault();
            this.activateEvent(card);
            break;
          case 'ArrowRight':
          case 'ArrowDown':
            e.preventDefault();
            this.focusNextCard(eventCards, index);
            break;
          case 'ArrowLeft':
          case 'ArrowUp':
            e.preventDefault();
            this.focusPrevCard(eventCards, index);
            break;
          case 'Home':
            e.preventDefault();
            this.focusFirstCard(eventCards);
            break;
          case 'End':
            e.preventDefault();
            this.focusLastCard(eventCards);
            break;
          case 'Escape':
            e.preventDefault();
            this.clearFocus();
            break;
        }
      });
    });
  }

  initCalendarNavigation() {
    // FullCalendar keyboard enhancements
    const calendar = document.querySelector('.fc');
    if (!calendar) return;

    // Add keyboard support for calendar navigation
    calendar.addEventListener('keydown', (e) => {
      if (e.target.classList.contains('fc-daygrid-day')) {
        this.handleDayNavigation(e);
      } else if (e.target.classList.contains('fc-event')) {
        this.handleEventNavigation(e);
      }
    });

    // Month navigation shortcuts
    document.addEventListener('keydown', (e) => {
      // Only if no input is focused
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

      switch (e.key) {
        case 'n':
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.navigateToToday();
          }
          break;
        case 'ArrowLeft':
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.navigateToPrevious();
          }
          break;
        case 'ArrowRight':
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.navigateToNext();
          }
          break;
      }
    });
  }

  handleDayNavigation(e) {
    const currentDay = e.target;
    const allDays = Array.from(document.querySelectorAll('.fc-daygrid-day'));
    const currentIndex = allDays.indexOf(currentDay);

    switch (e.key) {
      case 'ArrowLeft':
        e.preventDefault();
        this.focusDay(allDays, currentIndex - 1);
        break;
      case 'ArrowRight':
        e.preventDefault();
        this.focusDay(allDays, currentIndex + 1);
        break;
      case 'ArrowUp':
        e.preventDefault();
        this.focusDay(allDays, currentIndex - 7);
        break;
      case 'ArrowDown':
        e.preventDefault();
        this.focusDay(allDays, currentIndex + 7);
        break;
      case 'Enter':
      case ' ':
        e.preventDefault();
        this.selectDay(currentDay);
        break;
    }
  }

  handleEventNavigation(e) {
    const currentEvent = e.target;

    switch (e.key) {
      case 'Enter':
      case ' ':
        e.preventDefault();
        this.activateCalendarEvent(currentEvent);
        break;
      case 'Escape':
        e.preventDefault();
        currentEvent.blur();
        break;
    }
  }

  initFilterNavigation() {
    const filterForm = document.querySelector('.calendar-filters');
    if (!filterForm) return;

    // Quick filter navigation
    const quickFilters = document.querySelectorAll('.quick-filter');
    quickFilters.forEach((filter, index) => {
      filter.addEventListener('keydown', (e) => {
        switch (e.key) {
          case 'ArrowLeft':
            e.preventDefault();
            this.focusQuickFilter(quickFilters, index - 1);
            break;
          case 'ArrowRight':
            e.preventDefault();
            this.focusQuickFilter(quickFilters, index + 1);
            break;
        }
      });
    });
  }

  initSkipLinks() {
    // Add skip link if not present
    let skipLink = document.querySelector('.skip-link');
    if (!skipLink) {
      skipLink = document.createElement('a');
      skipLink.className = 'skip-link';
      skipLink.href = '#calendar-main';
      skipLink.textContent = 'Skip to calendar content';
      document.body.insertBefore(skipLink, document.body.firstChild);
    }

    // Ensure main content has proper ID
    const mainContent = document.querySelector('.calendar-container');
    if (mainContent && !mainContent.id) {
      mainContent.id = 'calendar-main';
    }
  }

  // Navigation helper methods
  focusNextCard(cards, currentIndex) {
    const nextIndex = (currentIndex + 1) % cards.length;
    this.focusCard(cards[nextIndex]);
  }

  focusPrevCard(cards, currentIndex) {
    const prevIndex = currentIndex === 0 ? cards.length - 1 : currentIndex - 1;
    this.focusCard(cards[prevIndex]);
  }

  focusFirstCard(cards) {
    if (cards.length > 0) {
      this.focusCard(cards[0]);
    }
  }

  focusLastCard(cards) {
    if (cards.length > 0) {
      this.focusCard(cards[cards.length - 1]);
    }
  }

  focusCard(card) {
    if (card) {
      card.focus();
      this.announceToScreenReader(`Focused on event: ${this.getCardTitle(card)}`);
    }
  }

  focusDay(days, index) {
    if (index >= 0 && index < days.length) {
      days[index].focus();
      this.announceToScreenReader(`Focused on ${this.getDayLabel(days[index])}`);
    }
  }

  focusQuickFilter(filters, index) {
    if (index >= 0 && index < filters.length) {
      filters[index].focus();
    }
  }

  activateEvent(card) {
    const link = card.querySelector('a');
    if (link) {
      link.click();
    } else {
      // Trigger custom event for card activation
      card.dispatchEvent(new CustomEvent('eventActivated', {
        detail: { card }
      }));
    }
  }

  activateCalendarEvent(eventElement) {
    // Trigger FullCalendar event click
    eventElement.click();
  }

  selectDay(dayElement) {
    // Add selected state
    document.querySelectorAll('.fc-daygrid-day').forEach(day => {
      day.removeAttribute('aria-selected');
    });

    dayElement.setAttribute('aria-selected', 'true');
    this.announceToScreenReader(`Selected ${this.getDayLabel(dayElement)}`);
  }

  navigateToToday() {
    const todayButton = document.querySelector('.fc-today-button');
    if (todayButton) {
      todayButton.click();
      this.announceToScreenReader('Navigated to today');
    }
  }

  navigateToPrevious() {
    const prevButton = document.querySelector('.fc-prev-button');
    if (prevButton && !prevButton.disabled) {
      prevButton.click();
      this.announceToScreenReader('Navigated to previous period');
    }
  }

  navigateToNext() {
    const nextButton = document.querySelector('.fc-next-button');
    if (nextButton && !nextButton.disabled) {
      nextButton.click();
      this.announceToScreenReader('Navigated to next period');
    }
  }

  clearFocus() {
    document.activeElement?.blur();
  }

  // Helper methods
  getCardTitle(card) {
    const titleElement = card.querySelector('.event-title, .card-title, h1, h2, h3, h4, h5, h6');
    return titleElement ? titleElement.textContent.trim() : 'Untitled event';
  }

  getDayLabel(dayElement) {
    const dateAttr = dayElement.getAttribute('data-date');
    if (dateAttr) {
      const date = new Date(dateAttr);
      return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
    return 'Unknown date';
  }

  announceToScreenReader(message) {
    // Create or update live region for screen reader announcements
    let liveRegion = document.querySelector('.live-region[aria-live="polite"]');

    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.className = 'live-region';
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');
      document.body.appendChild(liveRegion);
    }

    liveRegion.textContent = message;
  }
}
