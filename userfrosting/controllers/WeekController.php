<?php

namespace UserFrosting;

/*******

/players/*

*******/

// Handles user-related activities
class WeekController extends \UserFrosting\BaseController {

    public function __construct($app){
        $this->_app = $app;
    }
	
    // Display the form for creating a new user
    public function formMakePickCreate(){        
        $get = $this->_app->request->get();

        $user_player_list = [];
        //get user
        $target_user = UserLoader::fetch($this->_app->user->id);
        //get dummy week
        $target_week = GameWeekLoader::fetch(1);

        $data['user_id'] = $target_user->id;
        
        // Create a dummy user to prepopulate fields
        $target_pick = new PlayerPick($data); 
        
        //isadmin
        $isadmin = $target_user->isAdmin();

        //get current week
        $currentweek = $target_week->getGameCurrentWeek();       
        
        //get Bye Teams
        $bye_teams = $target_week->getByeTeamsByWeek($currentweek);        
        
        $bye_teams_split = explode(',', $bye_teams->bye_teams);        

        // Get first date of game
        $first_game_date = $target_week->getFirstGameDate();

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
        
        //get all players if admin otherwise only players associated with each user
        //error_log(print_r($isadmin));
        if($isadmin == 1)
        {
            //is admin
            //$user_player_list = PlayerLoader::fetchAll();
            $user_player_list = $target_week->getAdminPlayersPerWeek($currentweek);
            $game_teams = GameTeamsLoader::fetchAll();
            
            $pick_end_date_time = strtotime($game_start_date['start_date']. '+ 4 day');
            $pick_end_date = date('Y-m-d 23:59:59', $pick_end_date_time);                                    
            
            //disable team picks from 4th after game week start till next game week start
            if ((strtotime($today_date_time) >= strtotime($pick_end_date)) && (strtotime($today_date_time) <= strtotime($next_pick_start_date))) {
                $pick_team_time_over = true;
            }
        }
        else
        {
            //is user
            //$user_player_list = $target_user->getPlayers();
            $user_player_list = $target_week->getPlayersPerWeek($target_user, $currentweek);
            //error_log(print_r($user_player_list));
            
            //Get teams based on player previous picks if any otherwise show all teams
            //$game_teams = GameTeamsLoader::fetchAll();
            $game_teams = $target_pick->getTeamsNotPickedPerUser($currentweek, $target_user->id);
            
            $pick_end_date_time = strtotime($game_start_date['start_date']. '+ 3 day');            
            $pick_end_date = date('Y-m-d 13:00:00', $pick_end_date_time);            
            
            //disable team picks from 4th after game week start till next game week start
            if ((strtotime($today_date_time) >= strtotime($pick_end_date)) && (strtotime($today_date_time) <= strtotime($next_pick_start_date))) {
                $pick_team_time_over = true;
            }
            
        }

        //remove bye week teams from the list of teams for that particular week
        foreach ($game_teams as $key => $value)
        {
            foreach ($bye_teams_split as $byes)
            {                
                if(strcmp($value->code, $byes) == 0)
                {
                    //if they match remove that team from list
                    unset($game_teams[$key]);
                }                    
            }
        }
        
        $make_pick_alerts = "";
	$template = "makeapick.html";
        
        
        // Determine authorized fields for those that have default values.  Don't hide any fields
        $fields = ['week_id', 'team_id', 'player_id'];
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        $hidden_buttons = ['edit'];
        foreach ($fields as $field){            
            if ($pick_team_time_over) {
                $disabled_fields[] = $field;                
            }
            else
            {
                $show_fields[] = $field;
            }            
        }

        if($pick_team_time_over)
        {
            array_push($hidden_buttons, "submit");
            $next_day = $currentweek + 1;
            $next_game = date('l, d F Y', strtotime($game_next_start_date['start_date']));
            $make_pick_alerts = "No Picks allowed after 1PM on Game Days. Pick for Week $next_day will begin after 8am on $next_game.";
        }

        // Load validator rules
        $validators = new \Fortress\ClientSideValidator($this->_app->config('schema.path') . "/forms/make-pick.json");
        
        $this->_app->render($template, [
            "box_id" => "make-pick",
            "box_title" => "Make a Pick",
            "submit_button" => "Submit",
            "form_action" => $this->_app->site->uri['public'] . "/game/makeapick",
            "target_user" => $target_user,
            "isadmin" => $isadmin,
            "first_game_date" => $first_game_date,
            "current_week" => $currentweek,
            "game_teams" => $game_teams,
            "game_players" => $user_player_list,
            "make_pick_alerts" =>$make_pick_alerts,
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => $hidden_fields
            ],
            "buttons" => [
                "hidden" => $hidden_buttons
            ],
            "validators" => $validators->formValidationRulesJson()
        ]);   
    }
	
	// Ajax call: Display the form SELECT element for teams for per player
    public function formSelectTeamsPerPlayer($player_id){    
        //get user
        $target_user = UserLoader::fetch($this->_app->user->id);
    
        //isadmin
        $isadmin = $target_user->isAdmin();
        
        //get dummy week
        $target_week = GameWeekLoader::fetch(1);
        
        //get player info
        $target_player = PlayerLoader::fetch($player_id);

        //get current week
        $currentweek = $target_week->getGameCurrentWeek();     
        
        //get Bye Teams
        $bye_teams = $target_week->getByeTeamsByWeek($currentweek);        
        
        $bye_teams_split = explode(',', $bye_teams->bye_teams);        
        
        if($isadmin == 1)
        {
            $game_teams = GameTeamsLoader::fetchAll();
			//$game_teams = $target_week->getTeamsNotPickedPerPlayer($currentweek, $player_id);
        }
        else{
            //Get teams based on player previous picks if any otherwise show all teams
            //$game_teams = GameTeamsLoader::fetchAll();
            $game_teams = $target_week->getTeamsNotPickedPerPlayer($currentweek, $player_id);
            
        }
        
        
        //remove bye week teams from the list of teams for that particular week
        foreach ($game_teams as $key => $value)
        {
            foreach ($bye_teams_split as $byes)
            {                
                if(strcmp($value->code, $byes) == 0)
                {
                    //if they match remove that team from list
                    unset($game_teams[$key]);
                }                    
            }
        }
        
        //var_dump($game_teams);
        $select = "";
        
        foreach($game_teams as $key => $value)
        {
            $select.= "<option value='$value->id'>$value->full_name</option>";
            
        }
        
        echo $select;
    }
	
    // Display the form for editing an existing player picked team
    public function formMakePickEdit($player_picked_id){
        // Get the player pick to edit
        $target_pick = PlayerPickLoader::fetch($player_picked_id);        
        
        $get = $this->_app->request->get();
        
        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";
        
        $user_player_list = [];
        
        //get user
        $target_user = UserLoader::fetch($target_pick->user_id);        
        
        //get dummy week
        $target_week = GameWeekLoader::fetch(1);
        
        //get player info
        $target_player = PlayerLoader::fetch($target_pick->player_id);

        //isadmin
        $isadmin = $target_user->isAdmin();

        //get current week
        $currentweek = $target_week->getGameCurrentWeek();        

        // Get first date of game
        $first_game_date = $target_week->getFirstGameDate();

        //Get weeks
        $game_weeks = $target_pick->week_id;
        
        //Get teams based on player previous picks if any otherwise show all teams
        //$game_teams = $target_pick->getTeamsNotPickedPerUser($currentweek, $target_user->id);
        $game_teams = $target_pick->getTeamsNotPickedPerPlayer($currentweek, $target_pick->player_id);
        //error_log(print_r($game_teams));
        

        //get all players if admin otherwise only players associated with each user
        //error_log(print_r($isadmin));
        
        
        $user_player_list[] = $target_player;
        
        if ($render == "modal")
            $template = "components/make-pick-modal.html";
        else
            $template = "components/make-pick-panel.html";
        
        // Determine authorized fields
        $fields = ['week_id', 'team_id', 'player_id'];        
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        foreach ($fields as $field){
            //if ($this->_app->user->checkAccess("update_account_setting", ["user" => $target_user, "property" => $field]))
                $show_fields[] = $field;
            //else if ($this->_app->user->checkAccess("view_account_setting", ["user" => $target_user, "property" => $field]))
                //$disabled_fields[] = $field;
            //else
                //$hidden_fields[] = $field;
        }
        
        // Always disallow
        $disabled_fields[] = "week_id";        
        $disabled_fields[] = "player_id";        
        
        // Load validator rules
        $validators = new \Fortress\ClientSideValidator($this->_app->config('schema.path') . "/forms/make-pick.json");
        
        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "Edit Make a Pick",
            "submit_button" => "Update",
            "form_action" => $this->_app->site->uri['public'] . "/game/u/$player_picked_id",
            "target_user" => $target_user,
            "isadmin" => $isadmin,
            "first_game_date" => $first_game_date,
            "current_week" => $game_weeks,
            "game_teams" => $game_teams,
            "game_players" => $user_player_list,            
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => $hidden_fields
            ],
            "buttons" => [
                "hidden" => [                
                    "edit"
                ]
            ],
            "validators" => $validators->formValidationRulesJson()
        ]);
    }  
    
    public function pagePastWeek(){

        $target_user = UserLoader::fetch($this->_app->user->id);
        $isadmin = $target_user->isAdmin();

        //get dummy week
        $target_week = GameWeekLoader::fetch(1);

        //get current week
        $currentweek = $target_week->getGameCurrentWeek();

        $player_ids = $target_user->getPlayers();        
        
        //Get weeks
        $game_weeks = $target_week->getGameWeeks($currentweek);

        //get dummy player pick        
        $data['picked_time_stamp'] = date("Y-m-d H:i:s");
        $past_picks = new PlayerPick($data);        
        
        
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
            $past_player_picks = $past_picks->getAllPastPlayerPicks($currentweek);         
            
            $pick_end_date_time = strtotime($game_start_date['start_date']. '+ 4 day');
            $pick_end_date = date('Y-m-d 23:59:59', $pick_end_date_time);                                    
            
            //disable team picks from 4th after game week start till next game week start
            if ((strtotime($today_date_time) >= strtotime($pick_end_date)) && (strtotime($today_date_time) <= strtotime($next_pick_start_date))) {
                $pick_team_time_over = true;
            }
        }
        else
        {
            $past_player_picks = $past_picks->getPastPlayerPicks($currentweek,$player_ids);
            
            $pick_end_date_time = strtotime($game_start_date['start_date']. '+ 3 day');            
            $pick_end_date = date('Y-m-d 13:00:00', $pick_end_date_time);            
            
            //disable team picks from 4th after game week start till next game week start
            if ((strtotime($today_date_time) >= strtotime($pick_end_date)) && (strtotime($today_date_time) <= strtotime($next_pick_start_date))) {
                $pick_team_time_over = true;
            }
        }
        
        $disabled_buttons = [];
                
        if ($pick_team_time_over) {
            array_push($disabled_buttons, "edit", "delete");            
        }
        
        $name = "Your Past Weeks Picks";
        $icon = "fa fa-users";        
                        
        $this->_app->render('pastweek.html', [
            'page' => [
                'author' =>         $this->_app->site->author,
                'title' =>          $name,
                'description' =>    "A listing of the users for your site.  Provides management tools including the ability to edit user details, manually activate users, enable/disable users, and more.",
                'alerts' =>         $this->_app->alerts->getAndClearMessages()
            ],
            "box_title" => $name,
            "icon" => $icon,
            "isadmin" => $isadmin,
            "current_week" => $currentweek,
            "game_weeks" => $game_weeks,
            "past_picks" => $past_player_picks,
            "buttons" => [
                "disabled" => $disabled_buttons
            ]
        ]);          
    }
        
    public function pagePlayersNoPick($week_id){
       $get = $this->_app->request->get();
        
        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";
        
        $target_players = PlayerLoader::fetchPlayersNoPick($week_id);
        
        if ($render == "modal")
            $template = "playersnopick.html";
        
        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "Players Who did Not Pick Teams for Week $week_id",
            "players" => $target_players            
        ]);   
    }
    
    public function pageRankings(){
        //$target_user = UserLoader::fetch($this->_app->user->id);
		//get dummy week
        $target_week = GameWeekLoader::fetch(1);

        //get current week
        $currentweek = $target_week->getGameCurrentWeek();
		
		//Get weeks
        $game_weeks = $target_week->getGameWeeks($currentweek);
		
        $target_teams = GameTeamsLoader::fetchTop10($currentweek);
        $target_players = PlayerLoader::fetchTop10Active();
        
        $total_active_players = PlayerLoader::getCountTotalActivePlayers();        
        $total_active_users = UserLoader::getCountTotalActiveUsers();
        $total_pool_amount = PaymentLoader::getCountTotalPoolAmount();

        $name = "Current Rankings";
        $icon = "fa fa-users";        
                        
        $this->_app->render('rankings.html', [
            'page' => [
                'author' =>         $this->_app->site->author,
                'title' =>          $name,
                'description' =>    "",
                'alerts' =>         $this->_app->alerts->getAndClearMessages()
            ],
            "box_title" => $name,
            "icon" => $icon,
            "teams" => $target_teams,
			"week_id" => $currentweek,
			"game_weeks" => $game_weeks,
            "players" => $target_players,
            "total_active_players" => $total_active_players['total_active_players'],
            "total_active_users" => $total_active_users['total_active_users'],
            "total_pool_amount" => $total_pool_amount['total_pool_amount']
        ]);          
    }
	
	// Ajax call: Display rankings per week
    public function RankingsPerWeek($week_id){    
        //get user
        $target_user = UserLoader::fetch($this->_app->user->id);
    
        //isadmin
        $isadmin = $target_user->isAdmin();

		$target_teams = GameTeamsLoader::fetchTop10($week_id);		        
        
		echo json_encode($target_teams);
    }
        
    public function pageStats(){
        //$target_user = UserLoader::fetch($this->_app->user->id);        
        $target_players_lost = PlayerLoader::fetchPlayersLost();
        $target_players_won = PlayerLoader::fetchPlayersWon();
        
        $total_count_players_lost = count($target_players_lost);
        $total_active_players = PlayerLoader::getCountTotalActivePlayers();

        $target_pick_history = PlayerPickHistoryLoader::fetchAllPlayerPickHistory();

        $name = "Player Statistics";
        $icon = "fa fa-users";        
                        
        $this->_app->render('stats.html', [
            'page' => [
                'author' =>         $this->_app->site->author,
                'title' =>          $name,
                'description' =>    "",
                'alerts' =>         $this->_app->alerts->getAndClearMessages()
            ],
            "box_title" => $name,
            "icon" => $icon,            
            "players_lost" => $target_players_lost,
            "players_won" => $target_players_won,
            "total_count_players_lost" => $total_count_players_lost,
            "total_active_players" => $total_active_players['total_active_players'],
            "target_pick_history" => $target_pick_history
        ]);          
    }
    
    //get Real Time Bye Team Names using API
    public function getGameByeTeamsAPI(){
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'http://www.fantasyfootballnerd.com/service/byes/json/2r3z3pvzqrpi/',
            CURLOPT_POST => 1            
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources
        curl_close($curl);
        
        $bye_teams = json_decode($resp);

        $teams = '';

        $user_id = 1;

        $game_schedule = GameScheduleLoader::fetch($user_id);
        $game_table = $game_schedule->gameWeeksAll();

        foreach($game_table as $game)
        {
            //error_log(print_r($game['game_week']));
            $data['week_number'] = $game['game_week'];

            $data['start_date'] = $game['start_game_date'];

            foreach($bye_teams as $bye_team)
            {
                foreach($bye_team as $bye)
                {
                    if($bye->byeWeek == $game['game_week'])
                    {
                        $teams .= trim($bye->team).',';
                    }

                }

                $data['bye_teams'] = $teams;                
            }
            $teams = '';
                
            // Create the game records
            $allweeks = new GameWeek($data);

            // Store new user to database
            $allweeks->store();
            
        }                
    }

    // Create new user
    public function createPlayerPick(){
        $post = $this->_app->request->post();

        // DEBUG: view posted data
        //error_log(print_r($post, true));

        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/make-pick.json");

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);

        // Sanitize data
        $rf->sanitize();

        // Validate, and halt on validation errors.
        $error = !$rf->validate(true);

        // Get the filtered data
        $data = $rf->data();

        // Remove csrf_token, password confirmation from object data
        $rf->removeFields(['csrf_token']);

        // Perform desired data transformations on required fields.  Is this a feature we could add to Fortress?
        $data['player_id'] = trim($data['player_id']);
        $data['week_id'] = trim($data['week_id']);
        $data['team_id'] = trim($data['team_id']);
        
        //get player
        $target_player = PlayerLoader::fetch($post['player_id']);
                
        //get user id from player
        $data['user_id'] = $target_player->primary_user_id;

        $data['picked_time_stamp'] = date("Y-m-d H:i:s");                

        //check if player already selected that team before in the previous week
        if (PlayerPickLoader::pickExists($data['team_id'], $data['player_id'])){
            $ms->addMessageTranslated("danger", "PLAYER_PICKED_IN_USE", $data);
            $error = true;
        }
        
        
        // Halt on any validation errors
        if ($error) {
            $this->_app->halt(400);
        }

        // Create the player
        $player_pick = new PlayerPick($data);

        // Success message
        $ms->addMessageTranslated("success", "PLAYER_PICKED_COMPLETE");
        
        // Store new user to database
        $player_pick->store();

        
    }
    
    // Update player picks
    public function updatePlayerPick($player_picked_id){
        $post = $this->_app->request->post();
        
        // DEBUG: view posted data
        error_log(print_r($post, true));
        
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/make-pick.json");
        
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        // Get the target user
        $target_user = UserLoader::fetch($this->_app->user->id);        
        
        // Get the target user
        $target_pick = PlayerPickLoader::fetch($player_picked_id);
        
        //Create pick history
        $dataHistory['picked_time_stamp'] = date("Y-m-d H:i:s");
        $target_pick_history = new PlayerPickHistory($dataHistory);                 
        $target_pick_history->old_team_id = $target_pick->team_id;
        
        // Remove csrf_token
        unset($post['csrf_token']);
                                
        // Check authorization for submitted fields, if the value has been changed
        foreach ($post as $name => $value) {
            if (isset($target_pick->$name)){
                // Check authorization
                if (!$this->_app->user->checkAccess('update_player_picked_setting', ['user' => $target_user, 'property' => $name])){
                    $ms->addMessageTranslated("danger", "ACCESS_DENIED");
                    $this->_app->halt(403);
                }
            } else if (!isset($target_pick->$name)) {
                $ms->addMessageTranslated("danger", "NO_DATA");
                $this->_app->halt(400);
            }
        }

        //check if player already selected that team before in the previous week
        if (PlayerPickLoader::pickExists($post['team_id'], $target_pick->player_id)){
            $ms->addMessageTranslated("danger", "PLAYER_PICKED_IN_USE");
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
        
        // Update the user and generate success messages
        foreach ($data as $name => $value){
            if ($value != $target_pick->$name){
                $target_pick->$name = $value;
                // Custom success messages (optional)
               
            }
        }
        
        //add to pick history
        $target_pick_history->player_id = $target_pick->player_id;
        $target_pick_history->week_id = $target_pick->week_id;
        
        $target_pick_history->new_team_id = $post['team_id'];
        $target_pick_history->user_id = $target_pick->user_id;
        $target_pick_history->edited_by_user_id = $this->_app->user->id;        
        
        $ms->addMessageTranslated("success", "PLAYER_PICKED_UPDATED");
        $target_pick->store();        
        
        $target_pick_history->store();        
        
    }
    
    // Delete player picked
    public function deletePlayerPick($player_picked_id){
        $post = $this->_app->request->post();
        
        // Get the target user
        $target_pick = PlayerPickLoader::fetch($player_picked_id);
    
        // Get the alert message stream
        $ms = $this->_app->alerts;
        
        $ms->addMessageTranslated("success", "PLAYER_PICKED_DELETION_SUCCESSFUL");
        
        $target_pick->delete();
        unset($target_user);
    }
    
}
?>