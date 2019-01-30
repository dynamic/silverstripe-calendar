<div id="Content" class="searchResults">
  <h1>$Title</h1>

  <% if $Events %>
    <ul id="SearchResults">
      <% loop $Events %>
        <li>
          <h4>
            <a href="$Link">
              <% if $MenuTitle %>
                $MenuTitle
              <% else %>
                $Title
              <% end_if %>
            </a>
          </h4>
          <h5>$StartDate</h5>
          <% if $Content %>
            <p>$Content.LimitWordCountXML</p>
          <% end_if %>
          <a class="readMoreLink" href="$Link" title="Read more about &quot;{$Title}&quot;">Read more about&quot;{$Title}&quot;...</a>
        </li>
      <% end_loop %>
    </ul>
  <% else %>
    <p>Sorry, no events at this time.</p>
  <% end_if %>

  <% if $Events.MoreThanOnePage %>
    <div id="PageNumbers">
      <div class="pagination">
        <% if $Events.NotFirstPage %>
          <a class="prev" href="$Events.PrevLink" title="View the previous page">&larr;</a>
        <% end_if %>
        <span>
          <% loop $Events.Pages %>
            <% if $CurrentBool %>
              $PageNum
            <% else %>
              <a href="$Link" title="View page number $PageNum" class="go-to-page">$PageNum</a>
            <% end_if %>
          <% end_loop %>
        </span>
        <% if $Events.NotLastPage %>
          <a class="next" href="$Events.NextLink" title="View the next page">&rarr;</a>
        <% end_if %>
      </div>
      <p>Page $Events.CurrentPage of $Events.TotalPages</p>
    </div>
  <% end_if %>
</div>
