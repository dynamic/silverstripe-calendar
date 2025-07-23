// Calendar Frontend Module
// Bootstrap 5.3 + FullCalendar integration for Dynamic SilverStripe Calendar

import '../scss/calendar.scss';
import Choices from 'choices.js';

// Import components
import './components/CalendarView';
import './components/FullCalendarView';
import './components/SmartFiltering';
import './components/TouchInteractions';
import './components/KeyboardNavigation';

// Global function for Choices.js initialization (called by CalendarFilterForm)
window.initializeChoicesJS = function() {
  const multiSelectElements = document.querySelectorAll('.js-choice');
  const config = window.CalendarChoicesConfig || {};
  
  multiSelectElements.forEach(function(element) {
    if (element.tagName === 'SELECT' && !element.hasAttribute('data-choices-initialized')) {
      new Choices(element, config);
      element.setAttribute('data-choices-initialized', 'true');
    }
  });
};

class CalendarModule {
  constructor() {
    this.init();
  }

  init() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
    } else {
      this.initializeComponents();
    }
  }

  initializeComponents() {
    console.log('Initializing Dynamic Calendar Module...');

    // Initialize FullCalendar view if container exists
    const calendarContainer = document.querySelector('#dynamic-calendar-container');
    if (calendarContainer) {
      this.calendarView = new CalendarView(calendarContainer);
    }

    // Initialize list view enhancements
    const listView = document.querySelector('.calendar-list-view');
    if (listView) {
      this.initializeListView(listView);
    }

    // Initialize filtering system
    const filterForm = document.querySelector('.calendar-filter-form');
    if (filterForm) {
      this.smartFiltering = new SmartFiltering(filterForm);
    }

    // Initialize accessibility features
    this.keyboardNavigation = new KeyboardNavigation();

    // Initialize mobile/touch features
    if (this.isTouchDevice()) {
      this.touchInteractions = new TouchInteractions();
    }

    console.log('Calendar module initialized successfully');
  }

  initializeListView(container) {
    // Enhance existing list view with modern interactions
    container.classList.add('enhanced-list-view');

    // Add lazy loading for images
    const images = container.querySelectorAll('img[data-src]');
    if (images.length > 0) {
      this.initializeLazyLoading(images);
    }

    // Add smooth scrolling for pagination
    const paginationLinks = container.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        this.handlePaginationClick(link);
      });
    });
  }

  initializeLazyLoading(images) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          img.classList.remove('lazy');
          imageObserver.unobserve(img);
        }
      });
    });

    images.forEach(img => imageObserver.observe(img));
  }

  async handlePaginationClick(link) {
    const url = link.href;

    try {
      const response = await fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (response.ok) {
        const html = await response.text();
        // Update content dynamically
        this.updateListContent(html);
        // Update URL without page reload
        history.pushState(null, '', url);
      }
    } catch (error) {
      console.error('Error loading page:', error);
      // Fallback to normal navigation
      window.location.href = url;
    }
  }

  updateListContent(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newContent = doc.querySelector('.calendar-list-view');
    const currentContent = document.querySelector('.calendar-list-view');

    if (newContent && currentContent) {
      currentContent.innerHTML = newContent.innerHTML;
      this.initializeListView(currentContent);
    }
  }

  isTouchDevice() {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  }
}

// Initialize when module loads
new CalendarModule();
