<div class="event-item mb-3">
  <div class="d-flex justify-content-between align-items-start">
    <div class="event-content flex-grow-1">
      <h5 class="event-title mb-1">
        <a href="$Link" class="text-decoration-none">
          <% if $MenuTitle %>$MenuTitle<% else %>$Title<% end_if %>
        </a>
        <% if $eventRecurs %>
          <small class="badge bg-info text-dark ms-2">
            <i class="bi bi-arrow-repeat"></i> Recurring
          </small>
        <% end_if %>
      </h5>

      <div class="event-datetime mb-2">
        <small class="text-muted">
          <i class="bi bi-calendar-event"></i> $StartDate.Nice
          <% if $StartTime %>
            <i class="bi bi-clock ms-2"></i> $StartTime.Nice<% if $EndTime %> - $EndTime.Nice<% end_if %>
          <% end_if %>
        </small>
      </div>

      <% if $Content %>
        <p class="event-excerpt text-muted mb-2">$Content.LimitWordCountXML(15)</p>
      <% end_if %>

      <% if $Categories %>
        <div class="event-categories mb-2">
          <% loop $Categories %>
            <span class="badge bg-secondary me-1">$Title</span>
          <% end_loop %>
        </div>
      <% end_if %>
    </div>

    <div class="event-actions ms-3">
      <a href="$Link" class="btn btn-outline-primary btn-sm">
        View Details
      </a>
    </div>
  </div>
</div>
