/**
 * Calendar Event Filtering JavaScript
 *
 * Consolidated filtering functionality for the Dynamic Calendar module.
 * Provides enhanced form interactions, validation, auto-submit, and mobile-friendly features.
 */

(function ($) {
    'use strict';

    /**
     * Main Calendar Filter Manager
     */
    class CalendarFilterManager {
        constructor()
        {
            this.$form = $('.calendar-filters, .calendar-filter-form');
            this.$container = $('.calendar-events-container');
            this.isLoading = false;

            this.init();
        }

        init()
        {
            if (this.$form.length === 0) {
                return;
            }

            this.bindEvents();
            this.initDatePickers();
            this.initFormValidation();
            this.initAutoSubmit();
            this.initMobileEnhancements();
        }

        bindEvents()
        {
            // Form submission
            this.$form.on('submit', (e) => this.handleFormSubmit(e));

            // Clear filters
            $('.btn-clear-filters').on('click', (e) => this.handleClearFilters(e));

            // Auto-submit on change for certain fields
            this.$form.find('input[type="checkbox"], input[type="radio"]').on('change', () => {
                if (this.shouldAutoSubmit()) {
                    clearTimeout(this.autoSubmitTimeout);
                    this.autoSubmitTimeout = setTimeout(() => this.submitForm(), 300);
                }
            });

            // Search input with debounce
            const $searchInput = this.$form.find('input[name="search"]');
            if ($searchInput.length) {
                $searchInput.on('input', (e) => {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        if (this.shouldAutoSubmit()) {
                            this.submitForm();
                        }
                    }, 500); // 500ms debounce
                });
            }

            // Date inputs auto-submit
            this.$form.find('input[type="date"]').on('change', () => {
                if (this.shouldAutoSubmit()) {
                    this.submitForm();
                }
            });
        }

        initDatePickers()
        {
            const $fromDate = this.$form.find('input[name="from"]');
            const $toDate = this.$form.find('input[name="to"]');

            // Set up date constraints
            $fromDate.on('change', () => {
                const fromValue = $fromDate.val();
                if (fromValue) {
                    $toDate.attr('min', fromValue);
                    if ($toDate.val() && $toDate.val() < fromValue) {
                        $toDate.val(fromValue);
                    }
                }
            });

            $toDate.on('change', () => {
                const toValue = $toDate.val();
                if (toValue) {
                    $fromDate.attr('max', toValue);
                    if ($fromDate.val() && $fromDate.val() > toValue) {
                        $fromDate.val(toValue);
                    }
                }
            });
        }

        initFormValidation()
        {
            this.$form.find('input[type="date"]').on('blur', (e) => {
                this.validateDateInput($(e.target));
            });

            // Form validation on submit
            this.$form.on('submit', (e) => {
                const $fromDate = this.$form.find('input[name="from"]');
                const $toDate = this.$form.find('input[name="to"]');

                if ($fromDate.val() && $toDate.val()) {
                    const from = new Date($fromDate.val());
                    const to = new Date($toDate.val());

                    if (from > to) {
                        e.preventDefault();
                        alert('Start date cannot be after end date.');
                        $fromDate.focus();
                        return false;
                    }
                }
            });
        }

        initAutoSubmit()
        {
            this.autoSubmit = this.$form.data('auto-submit') !== false;
        }

        initMobileEnhancements()
        {
            // Category filter collapse/expand for mobile
            if (window.innerWidth < 768) {
                this.setupMobileCategoryFilter();
            }
        }

        setupMobileCategoryFilter()
        {
            const $categoryFilter = this.$form.find('.category-filter');
            if ($categoryFilter.length === 0) {
                return;
            }

            const $categoryLabel = this.$form.find('label:contains("Categories")').first();
            if ($categoryLabel.length === 0) {
                return;
            }

            $categoryLabel.css('cursor', 'pointer');
            $categoryLabel.on('click', () => {
                const isVisible = $categoryFilter.is(':visible');
                $categoryFilter.slideToggle();

                // Update indicator
                let $indicator = $categoryLabel.find('.collapse-indicator');
                if ($indicator.length === 0) {
                    $indicator = $('<span class="collapse-indicator"></span>');
                    $categoryLabel.append($indicator);
                }
                $indicator.html(isVisible ? ' ▼' : ' ▲');
            });

            // Start collapsed on mobile
            $categoryFilter.hide();
            $categoryLabel.append('<span class="collapse-indicator"> ▼</span>');
        }

        validateDateInput($input)
        {
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

        isValidDate(dateString)
        {
            const date = new Date(dateString);
            return date instanceof Date && !isNaN(date);
        }

        showValidationMessage($input, message)
        {
            let $feedback = $input.siblings('.invalid-feedback');
            if ($feedback.length === 0) {
                $feedback = $('<div class="invalid-feedback"></div>');
                $input.after($feedback);
            }
            $feedback.text(message);
        }

        hideValidationMessage($input)
        {
            $input.siblings('.invalid-feedback').remove();
        }

        shouldAutoSubmit()
        {
            return this.autoSubmit && window.location.search.indexOf('auto_submit=0') === -1;
        }

        handleFormSubmit(e)
        {
            if (this.isLoading) {
                e.preventDefault();
                return false;
            }

            // Let the form submit normally but show loading state
            this.showLoading();
        }

        handleClearFilters(e)
        {
            e.preventDefault();

            // Clear all form fields
            this.$form.find('input[type="text"], input[type="date"], input[type="search"]').val('');
            this.$form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);

            // Check "all" radio buttons if they exist
            this.$form.find('input[type="radio"][value=""]').prop('checked', true);

            // Submit cleared form
            this.submitForm();
        }

        submitForm()
        {
            if (this.isLoading) {
                return;
            }

            this.showLoading();

            // Build query string from form data
            const formData = new FormData(this.$form[0]);
            const searchParams = new URLSearchParams();

            for (let [key, value] of formData.entries()) {
                if (value !== '' && value !== null) {
                    searchParams.append(key, value);
                }
            }

            // Navigate to filtered results
            const newUrl = window.location.pathname + '?' + searchParams.toString();
            window.location.href = newUrl;
        }

        showLoading()
        {
            this.isLoading = true;
            this.$form.addClass('calendar-loading');

            // Update submit button
            const $submitBtn = this.$form.find('button[type="submit"]');
            if ($submitBtn.length) {
                $submitBtn.prop('disabled', true);
                this.originalButtonText = $submitBtn.html();
                $submitBtn.html('<i class="bi bi-hourglass-split"></i> Loading...');

                // Fallback to re-enable
                setTimeout(() => {
                    $submitBtn.prop('disabled', false);
                    $submitBtn.html(this.originalButtonText || 'Filter Events');
                }, 5000);
            }
        }
    }

    /**
     * Category Filter Enhancements
     */
    class CategoryFilterEnhancer {
        constructor()
        {
            this.$categoryFilter = $('.category-filter');
            this.init();
        }

        init()
        {
            if (this.$categoryFilter.length === 0) {
                return;
            }

            this.addSelectAllOption();
            this.addSearchFilter();
        }

        addSelectAllOption()
        {
            const $selectAll = $(`
                < div class = "form-check border-bottom pb-2 mb-2" >
                    < input class = "form-check-input" type = "checkbox" id = "selectAllCategories" >
                    < label class = "form-check-label fw-bold" for = "selectAllCategories" >
                        Select All
                    <  / label >
                <  / div >
            `);

            this.$categoryFilter.prepend($selectAll);

            // Handle select all functionality
            $selectAll.find('input').on('change', (e) => {
                const isChecked = $(e.target).is(':checked');
                this.$categoryFilter.find('input[name*="categories"]').prop('checked', isChecked);
            });

            // Update select all when individual categories change
            this.$categoryFilter.on('change', 'input[name*="categories"]', () => {
                this.updateSelectAllState();
            });

            this.updateSelectAllState();
        }

        addSearchFilter()
        {
            const $searchFilter = $(`
                < div class = "mb-2" >
                    < input type = "text" class = "form-control form-control-sm"
                           placeholder = "Search categories..." id = "categorySearch" >
                <  / div >
            `);

            this.$categoryFilter.prepend($searchFilter);

            $searchFilter.find('input').on('input', (e) => {
                const searchTerm = $(e.target).val().toLowerCase();
                this.filterCategories(searchTerm);
            });
        }

        updateSelectAllState()
        {
            const $selectAll = this.$categoryFilter.find('#selectAllCategories');
            const $categories = this.$categoryFilter.find('input[name*="categories"]');
            const $checkedCategories = $categories.filter(':checked');

            if ($checkedCategories.length === 0) {
                $selectAll.prop('checked', false).prop('indeterminate', false);
            } else if ($checkedCategories.length === $categories.length) {
                $selectAll.prop('checked', true).prop('indeterminate', false);
            } else {
                $selectAll.prop('checked', false).prop('indeterminate', true);
            }
        }

        filterCategories(searchTerm)
        {
            this.$categoryFilter.find('.form-check').each((i, el) => {
                const $el = $(el);
                const label = $el.find('label').text().toLowerCase();

                if ($el.find('#selectAllCategories, #categorySearch').length > 0) {
                    return;
                }

                $el.toggle(label.includes(searchTerm));
            });
        }
    }

    /**
     * Utility functions
     */
    function getURLParameter(name)
    {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    function updateURL(params)
    {
        const url = new URL(window.location);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        window.history.replaceState({}, '', url);
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        new CalendarFilterManager();
        new CategoryFilterEnhancer();
    });

    // Also initialize for vanilla JS compatibility
    document.addEventListener('DOMContentLoaded', () => {
        // Fallback initialization if jQuery is not available
        if (typeof $ === 'undefined') {
            console.warn('Calendar filters require jQuery for full functionality');
        }
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
