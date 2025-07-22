<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <header class="page-header mb-4">
        <h1 class="display-4">$Title</h1>
        <% if $Content %>
          <div class="lead">$Content</div>
        <% end_if %>
      </header>

      <!-- Calendar Filters -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card calendar-filters">
            <div class="card-body">
              <h5 class="card-title">Filter Events</h5>
              $FilterForm
            </div>
          </div>
        </div>
      </div>

      <!-- Elemental Area -->
      <% if $ElementalArea %>
        <div class="row mb-4">
          <div class="col-12">
            $ElementalArea
          </div>
        </div>
      <% end_if %>

      <% if $Events %>
        <div class="row">
          <% loop $Events %>
            <div class="col-lg-6 col-xl-4 mb-4">
              <% include Dynamic/Calendar/Includes/EventPreview %>
            </div>
          <% end_loop %>
        </div>
      <% else %>
        <div class="alert alert-info text-center" role="alert">
          <i class="bi bi-calendar-x display-1 text-muted"></i>
          <h4 class="alert-heading mt-3">No Events Found</h4>
          <p>Sorry, no events are scheduled for the selected time period.</p>
          <hr>
          <p class="mb-0">Try adjusting your date filters or check back later for new events.</p>
        </div>
      <% end_if %>

      <% if $Events.MoreThanOnePage %>
        <nav aria-label="Events pagination" class="mt-4">
          <ul class="pagination justify-content-center">
            <% if $Events.NotFirstPage %>
              <li class="page-item">
                <a class="page-link" href="$Events.PrevLink" aria-label="Previous">
                  <span aria-hidden="true">&laquo;</span>
                </a>
              </li>
            <% end_if %>
            <% loop $Events.Pages %>
              <li class="page-item <% if $CurrentBool %>active<% end_if %>">
                <% if $CurrentBool %>
                  <span class="page-link">$PageNum</span>
                <% else %>
                  <a class="page-link" href="$Link">$PageNum</a>
                <% end_if %>
              </li>
            <% end_loop %>
            <% if $Events.NotLastPage %>
              <li class="page-item">
                <a class="page-link" href="$Events.NextLink" aria-label="Next">
                  <span aria-hidden="true">&raquo;</span>
                </a>
              </li>
            <% end_if %>
          </ul>
          <p class="text-center text-muted">Page $Events.CurrentPage of $Events.TotalPages</p>
        </nav>
      <% end_if %>
    </div>
  </div>
</div>
</div>
