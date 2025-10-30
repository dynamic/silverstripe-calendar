export const getRollingListWeekView = () => ({
  type: 'list',
  duration: { days: 7 },
  visibleRange(currentDate) {
    const start = new Date(currentDate.valueOf());
    start.setHours(0, 0, 0, 0);
    const end = new Date(start.valueOf());
    end.setDate(end.getDate() + 7);
    return { start, end };
  }
});
