<% include SideBar %>
<div class="content-container unit size3of4 lastUnit">
    <article>
		<% with $Event %>
            <h1>$Title</h1>
            <div class="content">$Content</div>
			<div class="event-info">
				<p>Start: $StartDate.NiceUS @$StartTime.Nice</p>
			</div>
		<% end_with %>
    </article>
	$Form
	$CommentsForm
</div>