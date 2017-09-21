/*



UserFrosting

By Alex Weissman



UserFrosting is 100% free and open-source.



Permission is hereby granted, free of charge, to any person obtaining a copy

of this software and associated documentation files (the 'Software'), to deal

in the Software without restriction, including without limitation the rights

to use, copy, modify, merge, publish, distribute, sublicense, and/or sell

copies of the Software, and to permit persons to whom the Software is

furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in

all copies or substantial portions of the Software.



THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR

IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,

FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE

AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER

LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,

OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN

THE SOFTWARE.



*/



$(document).ready(function() { 

	

	$("form[name='game-schedule'] select").on("change", function (e) {

		$("#game-schedule-alerts").html("<div class='alert alert-warning'>Saving...</div>");

            

		e.preventDefault();

		

		// Get the form instance

        var form = $(this).parent();



        // Serialize and post to the backend script in ajax mode

        var serializedData = form.find('input, textarea, select').not(':checkbox').serialize();

        // Get unchecked checkbox values, set them to 0

        form.find('input[type=checkbox]').each(function() {

            if ($(this).is(':checked'))

                serializedData += "&" + encodeURIComponent(this.name) + "=1";

            else

                serializedData += "&" + encodeURIComponent(this.name) + "=0";

        });



		// Append page CSRF token

          var csrf_token = $("meta[name=csrf_token]").attr("content");

          serializedData += "&csrf_token=" + encodeURIComponent(csrf_token);

		  

        var url = form.attr('action');

        return $.ajax({  

          type: "POST",  

          url: url,  

          data: serializedData       

        }).done(function(data, statusText, jqXHR) {

            $('#userfrosting-alerts').flashAlerts().done(function() {

                $("#game-schedule-alerts").html("<div class='alert alert-success'>Winner Team have been saved</div>");

            });

        }).fail(function(jqXHR) {

            if (site['debug'] == true) {

                document.body.innerHTML = jqXHR.responseText;

            } else {

                console.log("Error (" + jqXHR.status + "): " + jqXHR.responseText );

                // Display errors on failure

                $('#userfrosting-alerts').flashAlerts().done(function() {

                    $("#game-schedule-alerts").html("<div class='alert alert-danger'>Could not save settings.</div>");

                });

            }

        });		

	});	

	$('.js-update-schedule').click(function() {
		var btn = $(this);
		//var player_picked_id = btn.data('id');
		console.log("update schedule");
		updateGameSchedule();
	});		

});

function updateGameSchedule(){
	var url = site['uri']['public'] + "/game/schedule/update";

	// Generate the form
	$.ajax({  
	  type: "GET",  
	  url: url
	})
	.fail(function(result) {
		// Display errors on failure
		$('#userfrosting-alerts').flashAlerts().done(function() {
		});
	})
	.done(function(result) {		
	    // Reload the page
        window.location.reload();    
	});
}