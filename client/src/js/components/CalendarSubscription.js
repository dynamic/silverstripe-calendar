// Calendar ICS Subscription Component
export class CalendarSubscription {
  constructor() {
    this.initSubscriptionModal();
    this.initCopyFunctionality();
  }

  /**
   * Initialize the subscription modal functionality
   */
  initSubscriptionModal() {
    const subscribeBtn = document.querySelector('.js-subscribe-calendar');
    const modal = document.getElementById('subscribeModal');
    
    if (!subscribeBtn || !modal) return;

    // Update subscription URL when modal is shown
    modal.addEventListener('show.bs.modal', () => {
      this.updateSubscriptionURL();
      this.updateCurrentFiltersDisplay();
    });
  }

  /**
   * Update the subscription URL based on current filter state
   */
  updateSubscriptionURL() {
    const subscribeBtn = document.querySelector('.js-subscribe-calendar');
    const urlInput = document.getElementById('subscription-url');
    
    if (!subscribeBtn || !urlInput) return;

    const baseUrl = subscribeBtn.dataset.calendarUrl;
    const icalUrl = `${baseUrl}/ical`;
    
    // Get current filter parameters
    const filterParams = this.getCurrentFilterParameters();
    
    // Build subscription URL
    let subscriptionUrl = icalUrl;
    if (filterParams.length > 0) {
      subscriptionUrl += '?' + filterParams.join('&');
    }
    
    urlInput.value = subscriptionUrl;
  }

  /**
   * Get current filter parameters from the form
   * @returns {Array} Array of URL parameter strings
   */
  getCurrentFilterParameters() {
    const filterForm = document.querySelector('.calendar-filter-form form');
    if (!filterForm) return [];

    const params = [];
    const formData = new FormData(filterForm);

    for (let [key, value] of formData.entries()) {
      // Skip security tokens and form actions
      if (key === 'SecurityID' || key.startsWith('action_')) continue;
      
      // Only include non-empty values
      if (value && value.trim() !== '') {
        // Handle array parameters (like categories)
        if (key.endsWith('[]')) {
          params.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        } else {
          params.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
      }
    }

    return params;
  }

  /**
   * Update the current filters display in the modal
   */
  updateCurrentFiltersDisplay() {
    const filtersDisplay = document.getElementById('current-filters-display');
    if (!filtersDisplay) return;

    const filterDescriptions = [];
    const filterForm = document.querySelector('.calendar-filter-form form');
    
    if (!filterForm) {
      filtersDisplay.textContent = 'All events';
      return;
    }

    const formData = new FormData(filterForm);
    
    // Check for search term
    const search = formData.get('search');
    if (search && search.trim() !== '') {
      filterDescriptions.push(`Search: "${search.trim()}"`);
    }

    // Check for categories
    const categories = formData.getAll('categories[]');
    if (categories && categories.length > 0) {
      // Get category names from the form
      const categoryNames = this.getCategoryNames(categories);
      if (categoryNames.length > 0) {
        filterDescriptions.push(`Categories: ${categoryNames.join(', ')}`);
      }
    }

    // Check for date range
    const fromDate = formData.get('from');
    const toDate = formData.get('to');
    if (fromDate || toDate) {
      if (fromDate && toDate) {
        filterDescriptions.push(`Date range: ${fromDate} to ${toDate}`);
      } else if (fromDate) {
        filterDescriptions.push(`From: ${fromDate}`);
      } else if (toDate) {
        filterDescriptions.push(`Until: ${toDate}`);
      }
    }

    // Check for event type
    const eventType = formData.get('eventType');
    if (eventType && eventType.trim() !== '') {
      filterDescriptions.push(`Type: ${eventType}`);
    }

    // Check for all day filter
    const allDay = formData.get('allDay');
    if (allDay) {
      filterDescriptions.push('All-day events only');
    }

    // Update display
    if (filterDescriptions.length > 0) {
      filtersDisplay.textContent = filterDescriptions.join(', ');
    } else {
      filtersDisplay.textContent = 'All events';
    }
  }

  /**
   * Get category names from category IDs
   * @param {Array} categoryIds Array of category IDs
   * @returns {Array} Array of category names
   */
  getCategoryNames(categoryIds) {
    const categoryNames = [];
    const categorySelect = document.querySelector('[name="categories[]"]');
    
    if (!categorySelect) return categoryNames;

    // Handle both select and checkbox inputs
    if (categorySelect.tagName === 'SELECT') {
      // For select elements (including multi-select)
      const options = categorySelect.options;
      for (let i = 0; i < options.length; i++) {
        const option = options[i];
        if (categoryIds.includes(option.value) && option.text) {
          categoryNames.push(option.text);
        }
      }
    } else {
      // For checkbox inputs
      const checkboxes = document.querySelectorAll('[name="categories[]"]:checked');
      checkboxes.forEach(checkbox => {
        const label = document.querySelector(`label[for="${checkbox.id}"]`);
        if (label) {
          categoryNames.push(label.textContent.trim());
        }
      });
    }

    return categoryNames;
  }

  /**
   * Initialize copy to clipboard functionality
   */
  initCopyFunctionality() {
    const copyButtons = document.querySelectorAll('.js-copy-url');
    
    copyButtons.forEach(button => {
      button.addEventListener('click', () => {
        this.copySubscriptionURL(button);
      });
    });
  }

  /**
   * Copy the subscription URL to clipboard
   * @param {HTMLElement} button The copy button that was clicked
   */
  async copySubscriptionURL(button) {
    const urlInput = document.getElementById('subscription-url');
    if (!urlInput || !urlInput.value) return;

    try {
      // Use the modern Clipboard API if available
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(urlInput.value);
      } else {
        // Fallback for older browsers
        urlInput.select();
        urlInput.setSelectionRange(0, 99999); // For mobile devices
        document.execCommand('copy');
      }

      // Show success feedback
      this.showCopyFeedback(button, true);
      
    } catch (error) {
      console.error('Failed to copy URL:', error);
      
      // Show error feedback and fallback
      this.showCopyFeedback(button, false);
      
      // Select the text as fallback
      urlInput.select();
      urlInput.setSelectionRange(0, 99999);
    }
  }

  /**
   * Show visual feedback after copy attempt
   * @param {HTMLElement} button The copy button
   * @param {boolean} success Whether the copy was successful
   */
  showCopyFeedback(button, success) {
    const originalContent = button.innerHTML;
    const originalClasses = button.className;
    
    if (success) {
      button.innerHTML = '<i class="bi bi-check me-2"></i>Copied!';
      button.classList.add('copied');
    } else {
      button.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Press Ctrl+C';
    }

    // Reset after 2 seconds
    setTimeout(() => {
      button.innerHTML = originalContent;
      button.className = originalClasses;
    }, 2000);
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new CalendarSubscription();
});