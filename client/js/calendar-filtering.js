/**
 * Calendar Event Filtering JavaScript
 * 
 * Enhances the calendar filtering experience with dynamic updates,
 * form validation, and improved user interactions.
 */

(function($) {
    'use strict';

    /**
     * Calendar Filter Manager
     */
    class CalendarFilterManager {
        constructor() {
            this.$form = $('.calendar-filters');
            this.$container = $('.calendar-events-container');
            this.$loadingOverlay = null;
            
            this.init();
        }

        init() {
            if (this.$form.length === 0) return;

            this.bindEvents();
            this.initDatePickers();
            this.initFormValidation();
            this.initAutoSubmit();
        }

        bindEvents() {
            // Form submission
            this.$form.on('submit', (e) => this.handleFormSubmit(e));

            // Clear filters
            $('.btn-clear-filters').on('click', (e) => this.handleClearFilters(e));

            // Auto-submit on change for certain fields
            this.$form.find('input[type="checkbox"], input[type="radio"]').on('change', () => {
                if (this.shouldAutoSubmit()) {
                    this.submitForm();
                }
            });

            // Search input with debounce
            let searchTimeout;
            this.$form.find('input[name="search"]').on('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.shouldAutoSubmit()) {
                        this.submitForm();
                    }
                }, 500); // 500ms debounce
            });
        }

        initDatePickers() {
            // Initialize date pickers with constraints
            const fromDate = this.$form.find('input[name="from"]');
            const toDate = this.$form.find('input[name="to"]');

            fromDate.on('change', () => {
                const fromValue = fromDate.val();
                if (fromValue) {
                    toDate.attr('min', fromValue);
                    if (toDate.val() && toDate.val() < fromValue) {
                        toDate.val(fromValue);
                    }
                }
            });

            toDate.on('change', () => {
                const toValue = toDate.val();
                if (toValue) {
                    fromDate.attr('max', toValue);
                    if (fromDate.val() && fromDate.val() > toValue) {
                        fromDate.val(toValue);
                    }
                }
            });
        }

        initFormValidation() {
            this.$form.find('input[type="date"]').on('blur', (e) => {
                this.validateDateInput($(e.target));
            });
        }

        initAutoSubmit() {
            // Check if auto-submit is enabled (could be a data attribute)
            this.autoSubmit = this.$form.data('auto-submit') !== false;
        }

        validateDateInput($input) {
            const value = $input.val();
            const isValid = value === '' || this.isValidDate(value);

            $input.toggleClass('is-invalid', !isValid);
            
            if (!isValid) {
                this.showValidationMessage($input, 'Please enter a valid date');
            } else {
                this.hideValidationMessage($input);
            }

            return isValid;
        }

        isValidDate(dateString) {
            const date = new Date(dateString);
            return date instanceof Date && !isNaN(date);
        }

        showValidationMessage($input, message) {
            let $feedback = $input.siblings('.invalid-feedback');
            if ($feedback.length === 0) {
                $feedback = $('<div class="invalid-feedback"></div>');
                $input.after($feedback);
            }
            $feedback.text(message);
        }

        hideValidationMessage($input) {
            $input.siblings('.invalid-feedback').remove();
        }

        shouldAutoSubmit() {
            return this.autoSubmit && window.location.search.indexOf('auto_submit=0') === -1;
        }

        handleFormSubmit(e) {
            e.preventDefault();
            
            // Validate form
            if (!this.validateForm()) {
                return false;
            }

            this.submitForm();
        }

        handleClearFilters(e) {
            e.preventDefault();
            
            // Clear all form fields
            this.$form.find('input[type="text"], input[type="date"], input[type="search"]').val('');
            this.$form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
            
            // Check "all" radio buttons if they exist
            this.$form.find('input[type="radio"][value=""]').prop('checked', true);
            
            // Submit cleared form
            this.submitForm();
        }

        validateForm() {
            let isValid = true;
            
            // Validate date inputs
            this.$form.find('input[type="date"]').each((i, el) => {
                if (!this.validateDateInput($(el))) {
                    isValid = false;
                }
            });

            return isValid;
        }

        submitForm() {
            if (this.isLoading) return;

            this.showLoading();
            
            const formData = new FormData(this.$form[0]);
            const searchParams = new URLSearchParams();
            
            // Build query string from form data
            for (let [key, value] of formData.entries()) {
                if (value !== '' && value !== null) {
                    searchParams.append(key, value);
                }
            }

            // Update URL without page reload
            const newUrl = window.location.pathname + '?' + searchParams.toString();
            window.history.replaceState({}, '', newUrl);

            // For now, just reload the page with new parameters
            // In a more advanced implementation, this could be an AJAX request
            window.location.href = newUrl;
        }

        showLoading() {
            this.isLoading = true;
            this.$form.addClass('calendar-loading');
            
            // Disable form elements
            this.$form.find('input, button').prop('disabled', true);
        }

        hideLoading() {
            this.isLoading = false;
            this.$form.removeClass('calendar-loading');
            
            // Re-enable form elements
            this.$form.find('input, button').prop('disabled', false);
        }
    }

    /**
     * Category Filter Enhancements
     */
    class CategoryFilterEnhancer {
        constructor() {
            this.$categoryFilter = $('.category-filter');
            this.init();
        }

        init() {
            if (this.$categoryFilter.length === 0) return;

            this.addSelectAllOption();
            this.addSearchFilter();
        }

        addSelectAllOption() {
            const $selectAll = $(`
                <div class="form-check border-bottom pb-2 mb-2">
                    <input class="form-check-input" type="checkbox" id="selectAllCategories">
                    <label class="form-check-label fw-bold" for="selectAllCategories">
                        Select All
                    </label>
                </div>
            `);

            this.$categoryFilter.prepend($selectAll);

            // Handle select all functionality
            $selectAll.find('input').on('change', (e) => {
                const isChecked = $(e.target).is(':checked');
                this.$categoryFilter.find('input[name="categories[]"]').prop('checked', isChecked);
            });

            // Update select all when individual categories change
            this.$categoryFilter.on('change', 'input[name="categories[]"]', () => {
                this.updateSelectAllState();
            });

            // Initial state
            this.updateSelectAllState();
        }

        addSearchFilter() {
            const $searchFilter = $(`
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" 
                           placeholder="Search categories..." id="categorySearch">
                </div>
            `);

            this.$categoryFilter.prepend($searchFilter);

            // Handle category search
            $searchFilter.find('input').on('input', (e) => {
                const searchTerm = $(e.target).val().toLowerCase();
                this.filterCategories(searchTerm);
            });
        }

        updateSelectAllState() {
            const $selectAll = this.$categoryFilter.find('#selectAllCategories');
            const $categories = this.$categoryFilter.find('input[name="categories[]"]');
            const $checkedCategories = $categories.filter(':checked');

            if ($checkedCategories.length === 0) {
                $selectAll.prop('checked', false).prop('indeterminate', false);
            } else if ($checkedCategories.length === $categories.length) {
                $selectAll.prop('checked', true).prop('indeterminate', false);
            } else {
                $selectAll.prop('checked', false).prop('indeterminate', true);
            }
        }

        filterCategories(searchTerm) {
            this.$categoryFilter.find('.form-check').each((i, el) => {
                const $el = $(el);
                const label = $el.find('label').text().toLowerCase();
                
                // Skip select all and search elements
                if ($el.find('#selectAllCategories, #categorySearch').length > 0) return;
                
                $el.toggle(label.includes(searchTerm));
            });
        }
    }

    /**
     * Event Card Enhancements
     */
    class EventCardEnhancer {
        constructor() {
            this.$eventCards = $('.event-card');
            this.init();
        }

        init() {
            if (this.$eventCards.length === 0) return;

            this.addHoverEffects();
            this.initLazyLoading();
        }

        addHoverEffects() {
            this.$eventCards.on('mouseenter', function() {
                $(this).addClass('shadow-sm');
            }).on('mouseleave', function() {
                $(this).removeClass('shadow-sm');
            });
        }

        initLazyLoading() {
            // If there are many event cards, implement lazy loading for images
            if (this.$eventCards.length > 20) {
                this.implementLazyLoading();
            }
        }

        implementLazyLoading() {
            // Simple lazy loading implementation
            if ('IntersectionObserver' in window) {
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

                this.$eventCards.find('img[data-src]').each((i, img) => {
                    imageObserver.observe(img);
                });
            }
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        new CalendarFilterManager();
        new CategoryFilterEnhancer();
        new EventCardEnhancer();
    });

})(jQuery);
