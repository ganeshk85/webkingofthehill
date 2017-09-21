<?php

namespace UserFrosting;

/**
 * @property string player_name 
 * @property int active 
 * @property int enabled
 * @property int primary_user_id 
 */
class MySqlGameSchedule extends MySqlDatabaseObject {
    
    protected $_primary_user;         // An undefined value means that the user's groups have not been loaded yet
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTableGameSchedule();
        $this->_columns = static::$columns_gameschedule;             
        parent::__construct($properties, $id);
    }
    
    // Must be implemented for compatibility with Twig
    public function __isset($name) {
        if ($name == "primary_group" || $name == "theme" || $name == "icon" || $name == "landing_page")
            return isset($this->_primary_group);
        else
            return parent::__isset($name);
    }
    
    // Get a user for adding a player
    public function getPrimaryUser(){
        if (!isset($this->_primary_user))
            $this->_primary_user = $this->fetchPrimaryUser();
            
        return $this->_primary_user;
    }
    
     private function fetchPrimaryUser() {
        if (!isset($this->user_name)){
            throw new \Exception("This player does not appear to have a primary user id set.");
        }
        $db = static::connection();
        $user_table = static::getTableUser();
        
        $query = "
            SELECT $user_table.*
            FROM $user_table
            WHERE $user_table.id = :user_name LIMIT 1";
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':user_name'] = $this->user_name;
        
        $stmt->execute($sqlVars);
        
        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($results)
            return new User($results, $results['id']);
        else
            return false;        
    }
    
    public function store($force_create = false){        
        // Update the user record itself
        parent::store();
        
        // Store function should always return the id of the object
        return $this->_id;
    }
    
    /*** Delete this user from the database, along with any linked groups and authorization rules
    ***/
    public function delete(){        
        // Can only delete an object where `id` is set
        if (!$this->_id) {
            return false;
        }
        
        $result = parent::delete();
        
        // Get connection
        $db = static::connection();
        $link_table = static::getTableGroupUser();
        $auth_table = static::getTableAuthorizeUser();
        
        $sqlVars[":id"] = $this->_id;
        
        $query = "
            DELETE FROM $link_table
            WHERE user_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);
     
        $query = "
            DELETE FROM $auth_table
            WHERE user_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);     

        return $result;
    }


    public static function gameWeeksAll(){
        $game_table = 'kh_game_schedule';

        $db = static::connection();
        
        $query = "
            SELECT $game_table.game_week, min($game_table.game_date) as start_game_date
            FROM $game_table
			GROUP BY $game_table.game_week";

        $stmt = $db->prepare($query);

        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }
        return $results;
    }

    public static function getTeamId($team_name){
        $db = static::connection();
        $teams_table = static::getTableGameTeams();

        $query = "
            SELECT $teams_table.*
            FROM $teams_table
            WHERE $teams_table.code = :team_name LIMIT 1";

        $stmt = $db->prepare($query);

        $sqlVars[':team_name'] = $team_name;

        $stmt->execute($sqlVars);

        $results = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($results)
            return new GameTeams($results, $results['id']);
        else
            return false;

    }

    public static function getAllPlayersWonLost($game_week, $team_id){
        $db = static::connection();
        $player_pick_table = static::getTablePlayerPick();

        $query = "
            SELECT $player_pick_table.*
            FROM $player_pick_table
            WHERE $player_pick_table.team_id = :teamId AND $player_pick_table.week_id = :gameWeek";

        $stmt = $db->prepare($query);

        $sqlVars[':teamId'] = $team_id;
        $sqlVars[':gameWeek'] = $game_week;

        $stmt->execute($sqlVars);

        // For now just create an array of Group objects.  Later we can implement GroupCollection for faster access.
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            //$id = $row['id'];
            $results[] = $row['player_id'];
        }
        return $results;

    }

    public static function addTeamsWonLost($game_id, $winner_team_id, $losing_team_id){        
        // First, load current game ids won per team
        $winner_team = GameTeamsLoader::fetch($winner_team_id);
        $losing_team = GameTeamsLoader::fetch($losing_team_id);

        $winner_game_ids = $winner_team->games_won;
        $losing_game_ids = $losing_team->games_lost;

        if(empty($winner_game_ids))
        {
           //unset($winner_game_ids_split);
           $winner_game_ids_split = array();
        }
        else
        {
            $winner_game_ids_split = explode(',', $winner_game_ids);
        }

        if(empty($losing_game_ids))
        {
           //unset($losing_game_ids_split);
           $losing_game_ids_split = array();
        }
        else
        {
            $losing_game_ids_split = explode(',', $losing_game_ids);
        }

        $win_game_id_exists = false;
        $lost_game_id_exists = false;

        foreach ($winner_game_ids_split as $key => $value)
        {
            if($value == $game_id)
            {
                $win_game_id_exists = true;
            }
        }

        foreach ($losing_game_ids_split as $key => $value)
        {
            if($value == $game_id)
            {
                $lost_game_id_exists = true;
            }
        }

        if(!$win_game_id_exists)
        {
            array_push($winner_game_ids_split, $game_id);
        }
        
        if(!$lost_game_id_exists)
        {
            array_push($losing_game_ids_split, $game_id);
        }
        
        $winner_game_ids_join = implode(",", $winner_game_ids_split);
        $losing_game_ids_join = implode(",", $losing_game_ids_split);

        $winner_team->games_won = $winner_game_ids_join;
        $losing_team->games_lost = $losing_game_ids_join;

        $winner_team->store();
        $losing_team->store();
    }

    public static function disableLostPlayers($game_player_losers){
        $enabled = 0;
        $lost = 1;
        foreach($game_player_losers as $player_id)
        {
            $player = PlayerLoader::fetch($player_id);

            $player->enabled = $enabled;
            $player->lost = $lost;

            $player->store();
        }
    }

    
}

?>
