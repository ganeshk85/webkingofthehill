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
    // Link buttons
    $('.js-player-create').click(function() { 
        userForm('dialog-player-create');
    });
    
    $('.js-player-edit').click(function() {
        var btn = $(this);
        var player_id = btn.data('id');
        userForm('dialog-player-edit', player_id);
    });

    $('.js-player-activate').click(function() {
        var btn = $(this);
        var player_id = btn.data('id');
        updatePlayerActiveStatus(player_id)
        .always(function(response) {
            // Reload page after updating user details
            window.location.reload();
        });
    });
    
    $('.js-player-enable').click(function () {
        var btn = $(this);
        var player_id = btn.data('id');
        updatePlayerEnabledStatus(player_id, "1")
        .always(function(response) {
            // Reload page after updating user details
            window.location.reload();
        });
    });
    
    $('.js-player-disable').click(function () {
        var btn = $(this);
        var player_id = btn.data('id');
        updatePlayerEnabledStatus(player_id, "0")
        .always(function(response) {
            // Reload page after updating user details
            window.location.reload();
        });
    });	
    
    $('.js-player-delete').click(function() {
        var btn = $(this);
        var player_id = btn.data('id');
        var player_name = btn.data('player_name');
        deletePlayerDialog('dialog-player-delete', player_id, player_name);
    });	 	
});

// Enable/disable the specified user
function updatePlayerEnabledStatus(player_id, enabled) {
	enabled = typeof enabled !== 'undefined' ? enabled : 1;
	csrf_token = $("meta[name=csrf_token]").attr("content");
    var data = {
		enabled: enabled,
		csrf_token: csrf_token
	};
	
	var url = site['uri']['public'] + "/players/u/" + player_id;
	
    return $.ajax({  
	  type: "POST",  
	  url: url,  
	  data: data	  
    });
}

// Activate new user account
function updatePlayerActiveStatus(player_id) {
	csrf_token = $("meta[name=csrf_token]").attr("content");
    var data = {
		active: "1",
        csrf_token: csrf_token
	}
    
    var url = site['uri']['public'] + "/players/u/" + player_id;

    return $.ajax({  
	  type: "POST",  
	  url: url,  
	  data: data
	});
}

function deletePlayerDialog(box_id, player_id, name){
	// Delete any existing instance of the form with the same name
	if($('#' + box_id).length ) {
		$('#' + box_id).remove();
	}
	
    var url = site['uri']['public'] + "/forms/confirm";
    
	var data = {
		box_id: box_id,
		box_title: "Delete Player",
		confirm_message: "Are you sure you want to delete the player " + name + "? You will lose all your previous picks.",
		confirm_button: "Yes, delete player"
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
            
            var url = site['uri']['public'] + "/players/u/" + player_id + "/delete";
            
            csrf_token = $("meta[name=csrf_token]").attr("content");
            var data = {
                player_id: player_id,
                csrf_token: csrf_token
            }
            
            $.ajax({  
              type: "POST",  
              url: url,  
              data: data
            }).done(function(result) {
              // Reload the page
              window.location.reload();      
              //console.log(result);
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

/* Display a modal form for updating/creating a user */
function userForm(box_id, player_id) {	
	player_id = typeof player_id !== 'undefined' ? player_id : "";
	
	// Delete any existing instance of the form with the same name
	if($('#' + box_id).length ) {
		$('#' + box_id).remove();
	}
	
    var data = {
		box_id: box_id,
		render: 'modal'
	};
    
    var url = site['uri']['public'] + "/forms/players";  
    
    // If we are updating an existing player
    if (player_id) {
        data = {
            box_id: box_id,
            render: 'modal',
            mode: "update"
        };
        
        url = site['uri']['public'] + "/forms/players/u/" + player_id;
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
		
		// Link submission buttons
        $("form[name='player']").formValidation({
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
              //console.log(data);
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

// Display user info in a panel
function userDisplay(box_id, player_id) {
	player_id = typeof player_id !== 'undefined' ? player_id : "";
	
	// Delete any existing instance of the form with the same name
	if($('#' + box_id).length ) {
		$('#' + box_id).remove();
	}
	
	var data = {
		box_id: box_id,
		render: 'modal',
        mode: 'view'
	};
	
	// Generate the form
	$.ajax({  
	  type: "GET",  
	  url: site['uri']['public'] + "/forms/players/u/" + player_id,  
	  data: data,
	  cache: false
	})
	.fail(function(result) {
        // Display errors on failure
        $('#userfrosting-alerts').flashAlerts().done(function() {
        });
	})
	.done(function(result) {

		// Initialize bootstrap switches for user groups
		var switches = $('#' + box_id + ' input[name="select_groups"]');
		switches.data('on-label', '<i class="fa fa-check"></i>');
		switches.data('off-label', '<i class="fa fa-times"></i>');
		switches.bootstrapSwitch();
		switches.bootstrapSwitch('setSizeClass', 'switch-mini' );

		// Initialize primary group buttons
		$(".bootstrapradio").bootstrapradio();
		
		// Link buttons
		$('#' + box_id + ' .js-player-edit').click(function() { 
			userForm('dialog-player-edit', player_id);
		});

		$('#' + box_id + ' .js-player-activate').click(function() {    
			updatePlayerActiveStatus(player_id);
		});
		
		$('#' + box_id + ' .js-player-enable').click(function () {
			updatePlayerEnabledStatus(player_id, "1");
		});
		
		$('#' + box_id + ' .js-player-disable').click(function () {
			updatePlayerEnabledStatus(player_id, "0");
		});	
		
		$('#' + box_id + ' .js-player-delete').click(function() {
			var player_name = $(this).data('name');
			deletePlayerDialog('delete-player-dialog', player_id, player_name);
			$('#dialog-player-delete').modal('show');
		});	
		
	});
}
