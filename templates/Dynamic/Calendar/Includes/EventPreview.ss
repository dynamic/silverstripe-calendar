<div class="card h-100 event-card" data-event-id="$ID" data-is-recurring="<% if $eventRecurs %>true<% else %>false<% end_if %>">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">
      <a href="$Link" class="text-decoration-none">
        <% if $MenuTitle %>
          $MenuTitle
        <% else %>
          $Title
        <% end_if %>
      </a>
    </h5>
    <% if $eventRecurs %>
      <span class="badge bg-info text-dark">
        <i class="bi bi-arrow-repeat"></i> Recurring
      </span>
    <% end_if %>
  </div>
  <div class="card-body">
    <div class="event-datetime mb-3">
      <p class="mb-1">
        <i class="bi bi-calendar-event"></i>
        <strong>$StartDate.Nice</strong>
      </p>
      <% if $StartTime %>
        <p class="mb-1">
          <i class="bi bi-clock"></i>
          $StartTime.Nice
          <% if $EndTime %>- $EndTime.Nice<% end_if %>
        </p>
      <% end_if %>
      <% if $eventRecurs %>
        <p class="text-muted small mb-1">
          <i class="bi bi-info-circle"></i>
          $RecurrenceDescription
        </p>
      <% end_if %>
    </div>
    <% if $Content %>
      <p class="card-text">$Content.LimitWordCountXML(25)</p>
    <% end_if %>
    <% if $Categories %>
      <div class="mb-2">
        <% loop $Categories %>
          <span class="badge bg-secondary me-1">$Title</span>
        <% end_loop %>
      </div>
    <% end_if %>
  </div>
  <div class="card-footer">
    <a href="$Link" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-eye"></i> View Details
    </a>
  </div>
</div>
