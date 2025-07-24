// Smart Filtering Component
// Provides predictive filtering and search capabilities

export class SmartFiltering {
  constructor() {
    this.filterHistory = this.loadFilterHistory();
    this.initPredictiveFilters();
    this.initSavedFilters();
    this.initAutoComplete();
  }

  initPredictiveFilters() {
    // Suggest popular filter combinations based on history
    if (this.filterHistory && this.filterHistory.length > 0) {
      this.showFilterSuggestions(this.filterHistory);
    }
  }

  initSavedFilters() {
    // Initialize saved filter functionality
    const savedFilters = this.loadSavedFilters();
    this.renderSavedFilters(savedFilters);
  }

  initAutoComplete() {
    const searchInput = document.querySelector('#event-search');
    if (!searchInput) return;

    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        this.performSearch(e.target.value);
      }, 300);
    });
  }

  performSearch(query) {
    if (query.length < 2) return;

    // Simulate API call - in real implementation this would be an AJAX request
    const suggestions = this.generateSuggestions(query);
    this.showSearchSuggestions(suggestions);
  }

  generateSuggestions(query) {
    // Mock data - in real implementation this would come from the server
    const mockEvents = [
      { title: 'Christmas Service', date: '2025-12-25', type: 'service' },
      { title: 'Youth Group Meeting', date: '2025-07-30', type: 'meeting' },
      { title: 'Bible Study', date: '2025-07-31', type: 'study' }
    ];

    return mockEvents.filter(event =>
      event.title.toLowerCase().includes(query.toLowerCase())
    );
  }

  showSearchSuggestions(suggestions) {
    const suggestionsContainer = document.querySelector('.search-suggestions');
    if (!suggestionsContainer) return;

    if (suggestions.length === 0) {
      suggestionsContainer.style.display = 'none';
      return;
    }

    suggestionsContainer.innerHTML = suggestions.map(suggestion => `
      <div class="search-suggestion" data-id="${suggestion.id}">
        <div class="suggestion-title">${suggestion.title}</div>
        <div class="suggestion-meta">${suggestion.date} • ${suggestion.type}</div>
      </div>
    `).join('');

    suggestionsContainer.style.display = 'block';
  }

  showFilterSuggestions(history) {
    // Show suggested filter combinations
    console.log('Showing filter suggestions based on history:', history);
  }

  loadFilterHistory() {
    try {
      return JSON.parse(localStorage.getItem('calendar-filter-history') || '[]');
    } catch (e) {
      return [];
    }
  }

  loadSavedFilters() {
    try {
      return JSON.parse(localStorage.getItem('calendar-saved-filters') || '[]');
    } catch (e) {
      return [];
    }
  }

  renderSavedFilters(filters) {
    // Render saved filter dropdown
    console.log('Rendering saved filters:', filters);
  }

  saveCurrentFilter(name, filterData) {
    const savedFilters = this.loadSavedFilters();
    savedFilters.push({ name, data: filterData, created: new Date().toISOString() });
    localStorage.setItem('calendar-saved-filters', JSON.stringify(savedFilters));
  }

  addToHistory(filterData) {
    const history = this.loadFilterHistory();
    history.unshift(filterData);
    // Keep only last 10 filter combinations
    const trimmedHistory = history.slice(0, 10);
    localStorage.setItem('calendar-filter-history', JSON.stringify(trimmedHistory));
  }
}
