export class CalendarSubscription {
    constructor() {
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bindEvents());
        } else {
            this.bindEvents();
        }
    }

    bindEvents() {
        $(document).on('shown.bs.modal', '#subscribeModal', () => {
            this.updateSubscribeButton();
        });

        $(document).on('click', '.js-copy-url', (e) => {
            this.copyUrl(e);
        });

        $(document).on('click', '.js-subscribe-app', (e) => {
            this.subscribeInApp(e);
        });

        // Stop accordion toggle events from closing the modal
        $(document).on('click', '#subscribeModal .accordion-button', (e) => {
            e.stopPropagation();
        });
    }

    updateSubscribeButton() {
        const urlInput = document.querySelector('#subscription-url');
        const subscribeButton = document.querySelector('.js-subscribe-app');

        if (!urlInput || !subscribeButton) return;

        // If URL input is empty, generate the subscription URL from the calendar URL
        if (!urlInput.value.trim()) {
            const calendarButton = document.querySelector('.js-subscribe-calendar');
            if (calendarButton) {
                const calendarUrl = calendarButton.getAttribute('data-calendar-url');
                if (calendarUrl) {
                    // Build the full ICS subscription URL using the correct /ical endpoint
                    const baseUrl = window.location.origin;
                    const icsUrl = `${baseUrl}${calendarUrl}/ical`;
                    urlInput.value = icsUrl;
                }
            }
        }

        const httpsUrl = urlInput.value;
        const webcalUrl = httpsUrl.replace(/^https?:\/\//, 'webcal://');

        subscribeButton.href = webcalUrl;
    }

    copyUrl(event) {
        const input = document.querySelector('#subscription-url');
        if (!input) return;

        input.select();
        input.setSelectionRange(0, 99999);

        try {
            document.execCommand('copy');

            const button = event.currentTarget;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';

            setTimeout(() => {
                button.innerHTML = originalText;
            }, 2000);
        } catch (err) {
            console.warn('Failed to copy URL:', err);
        }
    }

    subscribeInApp(event) {
        const urlInput = document.querySelector('#subscription-url');
        if (!urlInput) return;

        const httpsUrl = urlInput.value;
        const webcalUrl = httpsUrl.replace(/^https?:\/\//, 'webcal://');

        window.location.href = webcalUrl;
        event.preventDefault();
    }
}

export default CalendarSubscription;
