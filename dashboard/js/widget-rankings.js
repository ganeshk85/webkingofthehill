$(document).ready(function() {
	$( ".selectRankingsPerWeek" ).change(function() {
	  displayRankingsPerWeek($(this).val());
	});
});


function displayRankingsPerWeek(week_id){
	var url = site['uri']['public'] + "/forms/game/rankingsperweek/" + week_id;
	
	// Fetch and render the form
	$.ajax({  
	  type: "GET",  
	  url: url,
	  cache: false
	})
	.fail(function(result) {
        // Display errors on failure
        $('#userfrosting-alerts').flashAlerts().done(function() {
        });
	})
	.done(function(result) {		
		var rankings = jQuery.parseJSON(result);		
		var output="";		
		for (var i in rankings) 
		{	
			output+="<tr>";
			output+="<td>" + rankings[i].full_name + "</td><td>" + rankings[i].numplayers + "</td>";
			output+="</tr>";
		}		
		
		$('tbody.rankings_content').html(output);
		$('.week_id').text(week_id);
		
	});
}