// Touch Interactions Component
// Handles touch gestures and mobile interactions

export class TouchInteractions {
  constructor() {
    this.initSwipeNavigation();
    this.initPullToRefresh();
    this.initTouchOptimizations();
    this.gestureThreshold = 50; // Minimum swipe distance
  }

  initSwipeNavigation() {
    let startX, startY, startTime;
    const calendar = document.querySelector('.calendar-container');
    if (!calendar) return;

    calendar.addEventListener('touchstart', (e) => {
      const touch = e.touches[0];
      startX = touch.clientX;
      startY = touch.clientY;
      startTime = Date.now();
    }, { passive: true });

    calendar.addEventListener('touchend', (e) => {
      if (!startX || !startY) return;

      const touch = e.changedTouches[0];
      const endX = touch.clientX;
      const endY = touch.clientY;
      const endTime = Date.now();

      const deltaX = endX - startX;
      const deltaY = endY - startY;
      const deltaTime = endTime - startTime;

      // Only process quick swipes (under 300ms)
      if (deltaTime > 300) return;

      // Horizontal swipe for month/week navigation
      if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > this.gestureThreshold) {
        e.preventDefault();

        if (deltaX > 0) {
          this.navigatePrevious();
          this.showSwipeIndicator('right');
        } else {
          this.navigateNext();
          this.showSwipeIndicator('left');
        }
      }

      // Reset coordinates
      startX = startY = null;
    }, { passive: false });
  }

  navigatePrevious() {
    // Navigate to previous month/week
    const prevButton = document.querySelector('.fc-prev-button');
    if (prevButton && !prevButton.disabled) {
      prevButton.click();
    }
  }

  navigateNext() {
    // Navigate to next month/week
    const nextButton = document.querySelector('.fc-next-button');
    if (nextButton && !nextButton.disabled) {
      nextButton.click();
    }
  }

  showSwipeIndicator(direction) {
    const indicator = document.createElement('div');
    indicator.className = `swipe-indicator swipe-${direction}`;
    indicator.innerHTML = direction === 'left' ? '→' : '←';

    document.body.appendChild(indicator);

    // Show indicator briefly
    setTimeout(() => indicator.classList.add('show'), 10);
    setTimeout(() => {
      indicator.classList.remove('show');
      setTimeout(() => document.body.removeChild(indicator), 300);
    }, 500);
  }

  initPullToRefresh() {
    let startY = 0;
    let currentY = 0;
    let isPulling = false;

    const calendar = document.querySelector('.calendar-container');
    if (!calendar) return;

    calendar.addEventListener('touchstart', (e) => {
      if (calendar.scrollTop === 0) {
        startY = e.touches[0].clientY;
      }
    }, { passive: true });

    calendar.addEventListener('touchmove', (e) => {
      if (startY === 0) return;

      currentY = e.touches[0].clientY;
      const pullDistance = currentY - startY;

      if (pullDistance > 0 && calendar.scrollTop === 0) {
        isPulling = true;

        // Show pull indicator
        if (pullDistance > 80) {
          this.showPullRefreshIndicator(true);
        } else {
          this.showPullRefreshIndicator(false);
        }
      }
    }, { passive: true });

    calendar.addEventListener('touchend', () => {
      if (isPulling && (currentY - startY) > 80) {
        this.performRefresh();
      }

      this.hidePullRefreshIndicator();
      startY = 0;
      currentY = 0;
      isPulling = false;
    }, { passive: true });
  }

  showPullRefreshIndicator(ready) {
    let indicator = document.querySelector('.pull-refresh-indicator');

    if (!indicator) {
      indicator = document.createElement('div');
      indicator.className = 'pull-refresh-indicator';
      indicator.innerHTML = `
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>${ready ? 'Release to refresh' : 'Pull to refresh'}</span>
      `;
      document.body.appendChild(indicator);
    }

    indicator.classList.toggle('active', true);
    indicator.querySelector('span').textContent = ready ? 'Release to refresh' : 'Pull to refresh';
  }

  hidePullRefreshIndicator() {
    const indicator = document.querySelector('.pull-refresh-indicator');
    if (indicator) {
      indicator.classList.remove('active');
    }
  }

  performRefresh() {
    // Refresh calendar data
    console.log('Refreshing calendar data...');

    // Show loading state
    const indicator = document.querySelector('.pull-refresh-indicator');
    if (indicator) {
      indicator.querySelector('span').textContent = 'Refreshing...';
    }

    // Simulate refresh (in real implementation, this would refetch data)
    setTimeout(() => {
      this.hidePullRefreshIndicator();
      // Optionally show success message
      this.showRefreshSuccess();
    }, 1000);
  }

  showRefreshSuccess() {
    // Show brief success message
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0';
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">Calendar refreshed!</div>
      </div>
    `;

    document.body.appendChild(toast);

    // Auto-hide toast
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 2000);
  }

  initTouchOptimizations() {
    // Improve touch responsiveness
    document.addEventListener('touchstart', () => {}, { passive: true });

    // Prevent zoom on double-tap for specific elements
    const preventZoom = document.querySelectorAll('.event-card, .fc-event, .btn');
    preventZoom.forEach(el => {
      el.style.touchAction = 'manipulation';
    });

    // Improve scrolling performance
    const scrollElements = document.querySelectorAll('.calendar-container, .event-list');
    scrollElements.forEach(el => {
      el.style.webkitOverflowScrolling = 'touch';
    });
  }
}
