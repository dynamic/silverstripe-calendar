// EventPage Component
// Handles EventPage-specific functionality like add-to-calendar

export class EventPage {
    constructor() {
        this.init();
    }

    init() {
        this.bindAddToCalendarButtons();
    }

    bindAddToCalendarButtons() {
        // Find all add-to-calendar buttons
        const buttons = document.querySelectorAll('.js-add-to-calendar');

        buttons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.addToCalendar(button);
            });
        });

        // Legacy support for onclick="addToCalendar()" calls
        if (typeof window.addToCalendar === 'undefined') {
            const self = this;
            window.addToCalendar = function(button) {
                // If no button is passed, try to find the legacy button
                if (!button) {
                    button = document.querySelector('button[onclick*="addToCalendar"]');
                }
                if (button) {
                    self.addToCalendar(button);
                }
            };
        }
    }

    addToCalendar(buttonElement = null) {
        // Get event data from button data attributes or page meta
        const eventData = this.getEventData(buttonElement);

        if (!eventData.title || !eventData.startDate) {
            console.warn('EventPage: Missing required event data for calendar export');
            return;
        }

        // Create calendar URL
        const calendarUrl = this.createGoogleCalendarUrl(eventData);

        // Open in new window
        window.open(calendarUrl, '_blank', 'noopener,noreferrer');
    }

    getEventData(buttonElement) {
        // Try to get data from button attributes first
        if (buttonElement) {
            const dataset = buttonElement.dataset;
            if (dataset.eventTitle && dataset.eventStartDate) {
                return {
                    title: dataset.eventTitle,
                    startDate: dataset.eventStartDate,
                    startTime: dataset.eventStartTime || '',
                    endDate: dataset.eventEndDate || dataset.eventStartDate,
                    endTime: dataset.eventEndTime || '',
                    allDay: dataset.eventAllDay === 'true'
                };
            }
        }

        // Fallback to meta tags or page data
        const getMetaContent = (name) => {
            const meta = document.querySelector(`meta[name="${name}"], meta[property="${name}"]`);
            return meta ? meta.getAttribute('content') : '';
        };

        return {
            title: getMetaContent('event:title') || document.title.split(' | ')[0] || '',
            startDate: getMetaContent('event:start_date') || '',
            startTime: getMetaContent('event:start_time') || '',
            endDate: getMetaContent('event:end_date') || getMetaContent('event:start_date') || '',
            endTime: getMetaContent('event:end_time') || '',
            allDay: getMetaContent('event:all_day') === 'true'
        };
    }

    createGoogleCalendarUrl(eventData) {
        const title = encodeURIComponent(eventData.title);

        // Format dates for Google Calendar
        let dateString;
        if (eventData.allDay) {
            // All-day events use YYYYMMDD format
            dateString = `${eventData.startDate}/${eventData.endDate}`;
        } else {
            // Timed events use YYYYMMDDTHHMMSS format
            const startDateTime = eventData.startDate + 'T' + (eventData.startTime || '000000');
            const endDateTime = eventData.endDate + 'T' + (eventData.endTime || '235959');
            dateString = `${startDateTime}/${endDateTime}`;
        }

        return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${dateString}`;
    }

    // Static method for manual initialization
    static init() {
        return new EventPage();
    }
}
