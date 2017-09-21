<?php

namespace UserFrosting;

/*******

/players/*

*******/

// Handles player-related activities
class PlayerController extends \UserFrosting\BaseController {

    public function __construct($app){
        $this->_app = $app;
    }

    public function pagePlayers(){        
        $isadmin = 1;
        
        $target_user = UserLoader::fetch($this->_app->user->id);
        $isadmin = $target_user->isAdmin();
        
        // Access-controlled resource
        if (!$this->_app->user->checkAccess('view_players')){        
            // Get the player's
            $players = $target_user->getPlayersWithPaymentStatus();                        
        }        
        elseif ($this->_app->user->checkAccess('uri_players')){
            $players = PlayerLoader::fetchAdminAll();    
            
        }
        else
        {
            $this->_app->notFound();            
        }
        
        //get dummy week
        $target_week = GameWeekLoader::fetch(1);
        
        //get current week
        $currentweek = $target_week->getGameCurrentWeek();  
        
        
        
        //game week start date
        $game_start_date = $target_week->getGameWeekStartDate($currentweek);  
        
        if($currentweek +1 <=17)
        {
            $game_next_start_date = $target_week->getNextGameWeekStartDate($currentweek);
        
            $next_pick_start_date = date('Y-m-d 01:00:00', strtotime($game_next_start_date['start_date']));            
        }
        else
        {
            $next_pick_start_date = date('Y-m-d 01:00:00', strtotime($game_start_date['start_date']. '+ 7 day'));
        }
        
        $pick_team_time_over = false;
        
        $today_date_time = date('Y-m-d H:i:s');    
        
        if($isadmin == 1)
        {
            
            $pick_end_date_time = strtotime($game_start_date['start_date']. '+ 4 day');
            $pick_end_date = date('Y-m-d 23:59:59', $pick_end_date_time);                                    
            
            //disable team picks from 4th after game week start till next game week start
            if ((strtotime($today_date_time) >= strtotime($pick_end_date)) && (strtotime($today_date_time) <= strtotime($next_pick_start_date))) {
                $pick_team_time_over = true;
            }
        }
        else
        {            
            $pick_end_date_time = strtotime($game_start_date['start_date']. '+ 3 day');            
            $pick_end_date = date('Y-m-d 13:00:00', $pick_end_date_time);            
            
            //disable team picks from 4th after game week start till next game week start
            if ((strtotime($today_date_time) >= strtotime($pick_end_date)) && (strtotime($today_date_time) <= strtotime($next_pick_start_date))) {
                $pick_team_time_over = true;
            }
        }
        
        $hidden_buttons = [];
                
        if ($pick_team_time_over) {
            array_push($hidden_buttons, "createPlayer");
        }
        
        $name = "Players";
        $icon = "fa fa-users";        
        
        $this->_app->render('players.html', [
            'page' => [
                'author' =>         $this->_app->site->author,
                'title' =>          $name,
                'description' =>    "",
                'alerts' =>         $this->_app->alerts->getAndClearMessages()
            ],
            "box_title" => $name,
            "icon" => $icon,
            "players" => $players,
            "currentweek" => $currentweek,
            "first_game_date" => $first_game_date,
            "isadmin" => $isadmin,
            "buttons" => [
                "hidden" => $hidden_buttons
            ]
        ]);          
    }

   // Display the form for creating a new player
    public function formPlayerCreate(){        
        $get = $this->_app->request->get();
        
        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";
        
        //check if user is admin or user
        if ($this->_app->user->checkAccess("create_player"))
        {
            // If admin, Get a list of all users
            $users = UserLoader::fetchAll();

            foreach ($users as $user_id => $user){
                $user_list[$user_id] = $user->export();            
            }             
        }
        else
        {
            //if user, get only user details
            $user_list[$this->_app->user->id] = $this->_app->user;            
        }
        
        // Set default values
        $data['active'] = "1";
        $data['enabled'] = "1";
        
        // Create a dummy player to prepopulate fields
        $target_player = new Player($data);        
        
        if ($render == "modal")
            $template = "components/player-info-modal.html";
        else
            $template = "components/player-info-panel.html";
        
        // Determine authorized fields
        $fields = ['user_name'];
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        foreach ($fields as $field){
            if ($this->_app->user->checkAccess("create_player", ["property" => $field]))
            {
                $show_fields[] = $field;
            }
            else
            {
               $disabled_fields[] = $field;
            }
        }    
        
        // Load validator rules
        $validators = new \Fortress\ClientSideValidator($this->_app->config('schema.path') . "/forms/player-create.json");
        
        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "Create Player",
            "submit_button" => "Create player",
            "form_action" => $this->_app->site->uri['public'] . "/players",
            "target_player" => $target_player,
            "user_list" => $user_list,            
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => $hidden_fields
            ],
            "buttons" => [
                "hidden" => [
                    "edit", "enable", "delete", "activate"
                ]
            ],
            "validators" => $validators->formValidationRulesJson()
        ]);   
    }  
        
    // Display the form for editing an existing player
    public function formPlayerEdit($player_id){
        // Get the player to edit
        $target_player = PlayerLoader::fetch($player_id);        
        
        $get = $this->_app->request->get();
        
        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";
        
        // Get a list of all users
        $users = UserLoader::fetchAll();
        
        foreach ($users as $user_id => $user){
            $user_list[$user_id] = $user->export();            
        }
        
        if ($render == "modal")
            $template = "components/player-info-modal.html";
        else
            $template = "components/player-info-panel.html";
        
        // Determine authorized fields
        $fields = ['player_name', 'user_name'];        
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        foreach ($fields as $field){            
                $show_fields[] = $field;            
        }
        
        // Always disallow editing username
        $disabled_fields[] = "user_name";
        
        // Load validator rules
        $validators = new \Fortress\ClientSideValidator($this->_app->config('schema.path') . "/forms/player-update.json");
        
        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "Edit Player",
            "submit_button" => "Update player",
            "form_action" => $this->_app->site->uri['public'] . "/players/u/$player_id",
            "target_player" => $target_player,
            "user_list" => $user_list,            
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => $hidden_fields
            ],
            "buttons" => [
                "hidden" => [
                    "edit", "enable", "delete", "activate"
                ]
            ],
            "validators" => $validators->formValidationRulesJson()
        ]);   
    }    

    // Create new player
    public function createPlayer(){
        $post = $this->_app->request->post();
        
        // DEBUG: view posted data
        //error_log(print_r($post, true));
        
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/player-create.json");
        
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        // Access-controlled resource
        if (!$this->_app->user->checkAccess('create_player')){
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);        
  
        // Sanitize data
        $rf->sanitize();
                
        // Validate, and halt on validation errors.
        $error = !$rf->validate(true);
        
        // Get the filtered data
        $data = $rf->data();        
        
        // Remove csrf_token from object data
        $rf->removeFields(['csrf_token']);
        
        // Perform desired data transformations on required fields.
        $data['player_name'] = trim($data['player_name']);        
        $data['active'] = 0;
        
        if(!isset($post['user_name']))
        {
           $data['user_name'] = $this->_app->user->id;
        }
        else
        {
            $data['user_name'] = trim($post['user_name']);
        }
        
        // Check if playername already exists
        if (PlayerLoader::exists($data['player_name'], 'player_name')){
            $ms->addMessageTranslated("danger", "PLAYER_PLAYERNAME_IN_USE", $data);
            $error = true;
        }

        // Halt on any validation errors
        if ($error) {
            $this->_app->halt(400);
        }
        
        // Create the player
        $player = new Player($data);

        // Store new user to database
        $player->store();        
        
        // Success message
        $ms->addMessageTranslated("success", "PLAYER_CREATION_COMPLETE", $data);
    }
    
    
    // Update player details, enabled/disabled status, activation status
    public function updatePlayer($player_id){
        $post = $this->_app->request->post();
        
        // DEBUG: view posted data
        //error_log(print_r($post, true));
        
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/player-update.json");
        
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        // Get the target player
        $target_player = PlayerLoader::fetch($player_id);
                       
        // Remove csrf_token
        unset($post['csrf_token']);
                                
        // Check authorization for submitted fields, if the value has been changed
        foreach ($post as $name => $value) {
            if ((isset($target_player->$name) && $post[$name] != $target_player->$name)){
                // Check authorization
                if (!$this->_app->user->checkAccess('update_player_setting', ['user' => $target_player, 'property' => $name])){
                    $ms->addMessageTranslated("danger", "ACCESS_DENIED");
                    $this->_app->halt(403);
                }
            } else 
            if (!isset($target_player->$name)) {
                $ms->addMessageTranslated("danger", "NO_DATA");
                $this->_app->halt(400);
            }
        }
  
        // Check that name is not already in use
        if (isset($post['player_name']) && $post['player_name'] != $target_player->player_name && PlayerLoader::exists($post['player_name'], 'player_name')){
            $ms->addMessageTranslated("danger", "PLAYERNAME_IN_USE", $post);
            $this->_app->halt(400);
        }
        
        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);                    
    
        // Sanitize
        $rf->sanitize();
    
        // Validate, and halt on validation errors.
        if (!$rf->validate()) {
            $this->_app->halt(400);
        }   
               
        // Get the filtered data
        $data = $rf->data();
        
        // Update the player and generate success messages                
        foreach ($data as $name => $value){
            if ($value != $target_player->$name){
                $target_player->$name = $value;
                // Custom success messages (optional)
                if ($name == "enabled") {
                    if ($value == "1")
                        $ms->addMessageTranslated("success", "PLAYER_ENABLE_SUCCESSFUL", ["player_name" => $target_player->player_name]);
                    else
                        $ms->addMessageTranslated("success", "PLAYER_DISABLE_SUCCESSFUL", ["player_name" => $target_player->player_name]);
                }
                if ($name == "active") {
                    $ms->addMessageTranslated("success", "PLAYER_MANUALLY_ACTIVATED", ["player_name" => $target_player->player_name]);
                }
            }
        }
        
        $ms->addMessageTranslated("success", "PLAYER_DETAILS_UPDATED", ["player_name" => $target_player->player_name]);
        $target_player->store();        
        
    }
    
    // Delete a player, cleaning up their player picks if any
    public function deletePlayer($player_id){
        $post = $this->_app->request->post();
    
        // Get the target player
        $target_player = PlayerLoader::fetch($player_id);
    
        // Get the alert message stream
        $ms = $this->_app->alerts;        
        
        //get payment record of the user
        $user_payment = $target_player->fetchPayment($target_player->primary_user_id);
        
        //Do not delete user if there are players associated with it
        if($user_payment->amount_due > 0)
        {                        
            $ms->addMessageTranslated("danger", "PLAYER_CLEAR_PAYMENT_FIRST");
            $this->_app->halt(403);
        }
        
        $ms->addMessageTranslated("success", "PLAYER_DELETION_SUCCESSFUL", ["player_name" => $target_player->player_name]);
        $target_player->delete();
        unset($target_player);
    }
    
}
?>