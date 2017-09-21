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

$(document).ready(function() {   console.log("ssggsgggsg");                
	$('.js-make-pick-create').click(function() { 
        userForm('dialog-make-pick-create');
    });
	
	$('.js-make-pick-edit').click(function() {
		var btn = $(this);
		var player_picked_id = btn.data('id');
		userForm('dialog-make-pick-edit', player_picked_id);
	});	
	
	$('.js-make-pick-delete').click(function() {
        var btn = $(this);
        var player_picked_id = btn.data('id');        
        deletePlayerPickDialog('dialog-make-pick-delete', player_picked_id);
    });
	
        $('.js-display-playersnotpicked').click(function() { 
            var btn = $(this);
            var week_id = btn.data('id');
            displayPlayers('dialog-display-playersnotpicked',week_id);
        });
        
    $( ".selectTeamsPerPlayer" ).change(function() {
	  displayTeamsPerPlayer($(this).val());
	});

	$('.tablesorter-bootstrap').each(function(index, value){		
		var week = index + 1;
		// define tablesorter pager options
		var pagerOptions = {
		  // target the pager markup - see the HTML block below
		  container: $('.pager-'+week),
		  // output string - default is '{page}/{totalPages}'; possible variables: {page}, {totalPages}, {startRow}, {endRow} and {totalRows}
		  output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
		  // if true, the table will remain the same height no matter how many records are displayed. The space is made up by an empty
		  // table row set to a height to compensate; default is false
		  fixedHeight: true,
		  // remove rows from the table to speed up the sort of large tables.
		  // setting this to false, only hides the non-visible rows; needed if you plan to add/remove rows with the pager enabled.
		  removeRows: false,
		  // go to page selector - select dropdown that sets the current page
		  cssGoto: '.gotoPage-'+week
		};

		// Initialize tablesorters
		if (jQuery().tablesorter){
			$('.table-'+week).tablesorter({
				debug: false,
				theme: 'bootstrap',
				widthFixed: true,
				widgets: ['filter']
			}).tablesorterPager(pagerOptions);
		} else {
			console.log("The tablesorter plugin has not been added.");
		}
	});
	
	
	// Link submission buttons
	$("form[name='makeapick']").formValidation({
	  framework: 'bootstrap',
	  // Feedback icons
	  icon: {
		  valid: 'fa fa-check',
		  invalid: 'fa fa-times',
		  validating: 'fa fa-refresh'
	  },
	  fields: validators
	}).on('success.form.fv', function(e) {
	  // Prevent double form submission
	  e.preventDefault();

	  // Get the form instance
	  var form = $(e.target);

	  // Serialize and post to the backend script in ajax mode
	  var serializedData = form.find('input, textarea, select').not(':checkbox').serialize();
	  // Get non-disabled, unchecked checkbox values, set them to 0
	  form.find('input[type=checkbox]:enabled').each(function() {
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
		  // Reload the page
		  //window.location.reload(true);         
		  var replaceurl = site['uri']['public'] + "/game/pastweek/";
		  window.location.replace(replaceurl);
	  }).fail(function(jqXHR) {
		  if (site['debug'] == true) {
			  document.body.innerHTML = jqXHR.responseText;
		  } else {
			  console.log("Error (" + jqXHR.status + "): " + jqXHR.responseText );
		  }
		  $('#form-alerts').flashAlerts().done(function() {
			  // Re-enable submit button
			  form.data('formValidation').disableSubmitButtons(false);
		  });              
	  });
	});
});


/* Display a modal form for updating/creating a player team picked */
function userForm(box_id, player_picked_id) {	
	player_picked_id = typeof player_picked_id !== 'undefined' ? player_picked_id : "";
	
	// Delete any existing instance of the form with the same name
	if($('#' + box_id).length ) {
		$('#' + box_id).remove();
	}
	
    var data = {
		box_id: box_id,
		render: 'modal'
	};
    
    var url = site['uri']['public'] + "/forms/game";  
    
    // If we are updating an existing user
    if (player_picked_id) {
        data = {
            box_id: box_id,
            render: 'modal',
            mode: "update"
        };
        
        url = site['uri']['public'] + "/forms/game/u/" + player_picked_id;
    }
    
	// Fetch and render the form
	$.ajax({  
	  type: "GET",  
	  url: url,
	  data: data,
	  cache: false
	})
	.fail(function(result) {
        // Display errors on failure
        $('#userfrosting-alerts').flashAlerts().done(function() {
        });
	})
	.done(function(result) {
		// Append the form as a modal dialog to the body
		$( "body" ).append(result);
		$('#' + box_id).modal('show');
		
        // Initialize select2's
        $('#' + box_id + ' .select2').select2();
        
		// Initialize bootstrap switches
		var switches = $('#' + box_id + ' .bootstrapswitch');
		switches.data('on-label', '<i class="fa fa-check"></i>');
		switches.data('off-label', '<i class="fa fa-times"></i>');
		switches.bootstrapSwitch();
		switches.bootstrapSwitch('setSizeClass', 'switch-mini' );
		
		// Initialize primary group buttons
		$(".bootstrapradio").bootstrapradio();
		
		// Enable/disable primary group buttons when switch is toggled
		switches.on('switch-change', function(event, data){
			var el = data.el;
			var id = el.data('id');
			// Get corresponding primary button
			var primary_button = $('#' + box_id + ' button.bootstrapradio[name="primary_group_id"][value="' + id + '"]');
			// If switch is turned on, enable the corresponding button, otherwise turn off and disable it
			if (data.value) {
				primary_button.bootstrapradio('disabled', false);
			} else {
				primary_button.bootstrapradio('disabled', true);
			}	
		});
		
		// Link submission buttons
        $("form[name='makeapick']").formValidation({
          framework: 'bootstrap',
          // Feedback icons
          icon: {
              valid: 'fa fa-check',
              invalid: 'fa fa-times',
              validating: 'fa fa-refresh'
          },
          fields: validators
        }).on('success.form.fv', function(e) {
          // Prevent double form submission
          e.preventDefault();
    
          // Get the form instance
          var form = $(e.target);
    
          // Serialize and post to the backend script in ajax mode
          var serializedData = form.find('input, textarea, select').not(':checkbox').serialize();
          // Get non-disabled, unchecked checkbox values, set them to 0
          form.find('input[type=checkbox]:enabled').each(function() {
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
              // Reload the page
              window.location.reload(true);         			  
          }).fail(function(jqXHR) {
              if (site['debug'] == true) {
                  document.body.innerHTML = jqXHR.responseText;
              } else {
                  console.log("Error (" + jqXHR.status + "): " + jqXHR.responseText );
              }
              $('#form-alerts').flashAlerts().done(function() {
                  // Re-enable submit button
                  form.data('formValidation').disableSubmitButtons(false);
              });              
          });
        }); 	
	});
}
function deletePlayerPickDialog(box_id, player_picked_id){
	// Delete any existing instance of the form with the same name
	if($('#' + box_id).length ) {
		$('#' + box_id).remove();
	}
	
	var url = site['uri']['public'] + "/forms/confirm";
	
	var data = {
		box_id: box_id,
		box_title: "Delete Team Picked",
		confirm_message: "Are you sure you want to delete the pick?",
		confirm_button: "Yes, delete pick"
	}
	
	// Generate the form
	$.ajax({  
	  type: "GET",  
	  url: url,
	  data: data
	})
	.fail(function(result) {
		// Display errors on failure
		$('#userfrosting-alerts').flashAlerts().done(function() {
		});
	})
	.done(function(result) {		
		// Append the form as a modal dialog to the body
		$( "body" ).append(result);
		$('#' + box_id).modal('show');        
		$('#' + box_id + ' .js-confirm').click(function(){
			
			var url = site['uri']['public'] + "/game/u/" + player_picked_id + "/delete";
			
			csrf_token = $("meta[name=csrf_token]").attr("content");
			var data = {
				player_picked_id: player_picked_id,
				csrf_token: csrf_token
			}
			
			$.ajax({  
			  type: "POST",  
			  url: url,  
			  data: data
			}).done(function(result) {
			  // Reload the page
			  window.location.reload();         
			}).fail(function(jqXHR) {
				if (site['debug'] == true) {
					document.body.innerHTML = jqXHR.responseText;
				} else {
					console.log("Error (" + jqXHR.status + "): " + jqXHR.responseText );
				}
				$('#userfrosting-alerts').flashAlerts().done(function() {
					// Close the dialog
					$('#' + box_id).modal('hide');
				});              
			});
		});
	});
}

function displayPlayers(box_id, week_id){
	// Delete any existing instance of the form with the same name
	if($('#' + box_id).length ) {
		$('#' + box_id).remove();
	}
	
	var url = site['uri']['public'] + "/game/playersnopick/" + week_id;
	
        var data = {
		box_id: box_id,
		render: 'modal'
	};
        
	// Generate the form
	$.ajax({  
	  type: "GET",  
	  url: url,
	  data: data
	})
	.fail(function(result) {
		// Display errors on failure
		$('#userfrosting-alerts').flashAlerts().done(function() {
		});
	})
	.done(function(result) {		
		// Append the form as a modal dialog to the body
		$( "body" ).append(result);
		$('#' + box_id).modal('show');     
                
                // define tablesorter pager options
                var pagerOptions = {
                  // target the pager markup - see the HTML block below
                  container: $('.pager'),
                  // output string - default is '{page}/{totalPages}'; possible variables: {page}, {totalPages}, {startRow}, {endRow} and {totalRows}
                  output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
                  // if true, the table will remain the same height no matter how many records are displayed. The space is made up by an empty
                  // table row set to a height to compensate; default is false
                  fixedHeight: true,
                  // remove rows from the table to speed up the sort of large tables.
                  // setting this to false, only hides the non-visible rows; needed if you plan to add/remove rows with the pager enabled.
                  removeRows: false,
                  // go to page selector - select dropdown that sets the current page
                  cssGoto: '.gotoPage'
                };

                // Initialize tablesorters
                if (jQuery().tablesorter){
                    $('.tablesorter-bootstrap').tablesorter({
                        debug: false,
                        theme: 'bootstrap',
                        widthFixed: true,
                        widgets: ['filter']
                    }).tablesorterPager(pagerOptions);
                } else {
                    console.log("The tablesorter plugin has not been added.");
                }
		
	});
}

function displayTeamsPerPlayer(player_id){
	var url = site['uri']['public'] + "/forms/game/teamsperplayer/" + player_id;
	
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
		console.log(result);
		$('.selectTeams').empty().append(result);
		$('.selectTeams').select2("destroy");
		 $('.selectTeams').select2();
	});
}
