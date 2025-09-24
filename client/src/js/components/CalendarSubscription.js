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

        // Try to use the modern Clipboard API
        const textToCopy = input.value;
        const button = event.currentTarget;
        const originalText = button.innerHTML;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textToCopy)
                .then(() => {
                    button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                })
                .catch((err) => {
                    console.warn('Failed to copy URL:', err);
                });
        } else {
            // Fallback for older browsers
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            } catch (err) {
                console.warn('Failed to copy URL:', err);
            }
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
