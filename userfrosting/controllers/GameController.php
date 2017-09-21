<?php

namespace UserFrosting;

/*******

/players/*

*******/

// Handles user-related activities
class GameController extends \UserFrosting\BaseController {

    public function __construct($app){
        $this->_app = $app;
    }

    public function pageGameSchedule(){     
		
        $target_user = UserLoader::fetch($this->_app->user->id);
        $isadmin = $target_user->isAdmin();
		
        $games = GameScheduleLoader::fetchAll();

        $name = "Game Schedule";
        $icon = "fa fa-users";        
                        
        $this->_app->render('schedule.html', [
            'page' => [
                'author' =>         $this->_app->site->author,
                'title' =>          $name,
                'description' =>    "A listing of the users for your site.  Provides management tools including the ability to edit user details, manually activate users, enable/disable users, and more.",
                'alerts' =>         $this->_app->alerts->getAndClearMessages()
            ],
            "box_title" => $name,
            "icon" => $icon,
            "games" => $games,
            "isadmin" => $isadmin			
        ]);          
    }
	
    public function saveGameWinner($game_id){
        // Get the alert message stream
        $ms = $this->_app->alerts;
        
        $post = $this->_app->request->post();
        
        // DEBUG: view posted data
        //error_log(print_r($post, true));
                        
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/game-update.json");
        
        // Get the target player
        $target_game = GameScheduleLoader::fetch($game_id);        

        // Remove csrf_token
        unset($post['csrf_token']);
                                
        // Check authorization for submitted fields, if the value has been changed
        foreach ($post as $name => $value) {
            if ((isset($target_game->$name) && $post[$name] != $target_game->$name)){
                // Check authorization
                if (!$this->_app->user->checkAccess('update_game_schedule_setting', ['user' => $target_game, 'property' => $name])){
                    $ms->addMessageTranslated("danger", "ACCESS_DENIED");
                    $this->_app->halt(403);
                }
            } else 
            if (!isset($target_game->$name)) {
                $ms->addMessageTranslated("danger", "NO_DATA");
                $this->_app->halt(400);
            }
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

        //if post winner, save the team id instead of team code
        if(isset($post["winner"])) {
            //Get the team id for the winner team
            $winner_team = $target_game->getTeamId($post["winner"]);
            $winner_team_id = $winner_team->id;
            $data["winner_team_id"] = $winner_team_id;

            //get the game week
            $game_week = $target_game->game_week;
            $game_away_team = $target_game->away_team;
            $game_home_team = $target_game->home_team;

            //get the losing team
            if($post["winner"] == $target_game->away_team)
            {
                $losing_team = $target_game->getTeamId($game_home_team);
            }
            else
            {
                $losing_team = $target_game->getTeamId($game_away_team);
            }

            $losing_team_id = $losing_team->id;
            $data["loser_team_id"] = $losing_team_id;

            //get all players who had selected the winner team in that particular week
            $game_player_winners = $target_game->getAllPlayersWonLost($game_week, $winner_team_id);            

            //get all players who had selected the losing team in that particular week
            $game_player_losers = $target_game->getAllPlayersWonLost($game_week, $losing_team_id);            

            //add game ids to teams table
            $target_game->addTeamsWonLost($game_id, $winner_team_id, $losing_team_id);

            //disable players who chose losing team
            $target_game->disableLostPlayers($game_player_losers);
        }

        // Update the player and generate success messages                
        foreach ($data as $name => $value){
            if ($value != $target_game->$name){
                $target_game->$name = $value;
                // Custom success messages (optional)                
            }
        }
                
        $target_game->store();
    }
    
    //get Real Time Game Schedule using API
    public function getGameScheduleAPI(){
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://www.fantasyfootballnerd.com/service/schedule/json/a7a6y3gf7r5c/',            
            CURLOPT_POST => 1            
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources
        curl_close($curl);
        
        $schedule = json_decode($resp);
        
        foreach($schedule->Schedule as $game)
        {         
            $data['game_id'] = trim($game->gameId);
            $data['game_week'] = trim($game->gameWeek);
            $data['game_date'] = trim($game->gameDate);
            $data['away_team'] = trim($game->awayTeam);
            $data['home_team'] = trim($game->homeTeam);
            $data['game_time_et'] = trim($game->gameTimeET);
            $data['tv_station'] = trim($game->tvStation);
            $data['winner'] = trim($game->winner);
                        
            // Create the game records
            $game_sch = new GameSchedule($data);

            // Store new user to database
            $game_sch->store();        
        }        
    }
	
	//get Real Time Team Names using API
    public function getGameTeamsAPI(){
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://www.fantasyfootballnerd.com/service/nfl-teams/json/a7a6y3gf7r5c/',            
            CURLOPT_POST => 1            
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources
        curl_close($curl);
        
        $teams = json_decode($resp);
        
        foreach($teams->NFLTeams as $team)
        {         
            $data['code'] = trim($team->code);
            $data['full_name'] = trim($team->fullName);
            $data['short_name'] = trim($team->shortName);
                        
            // Create the game records
            $allteams = new GameTeams($data);

            // Store new user to database
            $allteams->store();        
        }        
    }
    
    
}
?>