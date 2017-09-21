<?php

namespace UserFrosting;

/**
 * @property string player_name 
 * @property int active 
 * @property int enabled
 * @property int primary_user_id 
 */
class MySqlGameWeek extends MySqlDatabaseObject {
    
    protected $_primary_user;         // An undefined value means that the user's groups have not been loaded yet
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTableWeeks();
        $this->_columns = static::$columns_weeks;
        parent::__construct($properties, $id);
    }
    
    // Must be implemented for compatibility with Twig
    public function __isset($name) {
        if ($name == "primary_group" || $name == "theme" || $name == "icon" || $name == "landing_page")
            return isset($this->_primary_group);
        else
            return parent::__isset($name);
    }
    
    public function store($force_create = false){        
        // Update the user record itself
        parent::store();
        
        // Store function should always return the id of the object
        return $this->_id;
    }
    
    // Return Current Week
    public function getGameCurrentWeek(){
        $db = static::connection();

        $game_week = static::getTableWeeks();

        $query = "SELECT $game_week.id,$game_week.week_number
                  FROM $game_week 
                  WHERE DATE_ADD(CURRENT_TIMESTAMP(), Interval -7 DAY) < $game_week.start_date
                  ORDER BY $game_week.week_number ASC LIMIT 1";

        $stmt = $db->prepare($query);

        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $results = $row['week_number'];

        return $results;
    }


    // Return First Game Date of the Season
    public function getFirstGameDate(){
        $db = static::connection();

        $game_table = static::getTableGameSchedule();

        $query = "
            SELECT $game_table.game_week, min($game_table.game_date) as first_game_date
            FROM $game_table
			GROUP BY $game_table.game_week LIMIT 1";

        $stmt = $db->prepare($query);

        $stmt->execute();

        $results = $stmt->fetch(\PDO::FETCH_ASSOC);


        return $results;
    }
    
    // Return Game Week Start Date
    public function getGameWeekStartDate($week_number){
        $db = static::connection();

        $game_week_table = static::getTableWeeks();

        $query = "SELECT $game_week_table.start_date
                  FROM $game_week_table 
                  WHERE $game_week_table.week_number =:weekNumber
                  LIMIT 1";

        $stmt = $db->prepare($query);
        
        $sqlVars[':weekNumber'] = $week_number;

        $stmt->execute($sqlVars);

        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $results;        
    }
    
    // Return Next Game Week Start Date
    public function getNextGameWeekStartDate($week_number){
        $db = static::connection();

        $game_week_table = static::getTableWeeks();

        $query = "SELECT $game_week_table.start_date
                  FROM $game_week_table 
                  WHERE $game_week_table.week_number =:weekNumber
                  LIMIT 1";

        $stmt = $db->prepare($query);
        
        $sqlVars[':weekNumber'] = $week_number + 1;

        $stmt->execute($sqlVars);

        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $results;        
    }

    // Return All Game Weeks till Current Week order by Descending
    public function getGameWeeks($current_week){
        $db = static::connection();

        $game_table = static::getTableGameSchedule();

        $query = "
            SELECT DISTINCT $game_table.game_week
            FROM $game_table
            WHERE $game_table.game_week <=:currweek
            GROUP BY $game_table.game_week ORDER BY $game_table.game_week DESC";

        $stmt = $db->prepare($query);

        $sqlVars[':currweek'] = $current_week;

        $stmt->execute($sqlVars);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row['game_week'];
        }
        return $results;
    }
    
    // Return Bye Teams by week number
    public function getByeTeamsByWeek($current_week){
        $db = static::connection();

        $game_week = static::getTableWeeks();

        $query = "SELECT $game_week.*
                  FROM $game_week 
                  WHERE $game_week.week_number =:currWeek
                  LIMIT 1";

        $stmt = $db->prepare($query);
        
        $sqlVars[':currWeek'] = $current_week;

        $stmt->execute($sqlVars);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $results = new GameWeek($row, $row['id']);
        
        return $results;
    }
    
     // Return Players Per Week
    public function getPlayersPerWeek($user, $weekId){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();

        $query = "
            SELECT $table_players.id,$table_players.player_name
            FROM $table_players
            WHERE $table_players.primary_user_id = :id AND $table_players.enabled != 0 AND $table_players.active != 0 AND $table_players.id
            NOT IN
            (SELECT $table_player_picks.player_id
             FROM $table_player_picks
             WHERE $table_player_picks.week_id = :weekId )
             ORDER BY  $table_players.player_name ASC";

        $stmt = $db->prepare($query);

        $sqlVars[':weekId'] = $weekId;
        $sqlVars[':id'] = $user->id;

        $stmt->execute($sqlVars);

        //error_log(print_r($stmt->fetch(\PDO::FETCH_ASSOC)));
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $results[$id] = new Player($row, $row['id']);
        }
        //$results = $stmt->fetch(\PDO::FETCH_ASSOC);
        //error_log(print_r($results));
        return $results;
    }

    // Return Players Per Week
    public function getAdminPlayersPerWeek($weekId){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();

        $query = "
            SELECT $table_players.id,$table_players.player_name
            FROM $table_players
            WHERE $table_players.enabled != 0 AND $table_players.active != 0 AND $table_players.id
            NOT IN
            (SELECT $table_player_picks.player_id
             FROM $table_player_picks
             WHERE $table_player_picks.week_id = :weekId )
             ORDER BY  $table_players.player_name ASC";

        $stmt = $db->prepare($query);

        $sqlVars[':weekId'] = $weekId;        

        $stmt->execute($sqlVars);

        //error_log(print_r($stmt->fetch(\PDO::FETCH_ASSOC)));
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $results[$id] = new Player($row, $row['id']);
        }
        //$results = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $results;
    }
    
    //Return teams based on player previous picks if any otherwise show all teams
    public function getTeamsNotPickedPerPlayer($current_week, $player_id){
        $db = static::connection();
        
        $table_player_picks = static::getTablePlayerPick();
        $table_teams = static::getTableGameTeams();

        $results = [];
            
        $query = "
            SELECT $table_teams.*
            FROM $table_teams
            WHERE $table_teams.id
            NOT IN
            (SELECT $table_player_picks.team_id
             FROM $table_player_picks
             WHERE $table_player_picks.week_id <= :prevweek AND $table_player_picks.player_id = :playerId )
             ORDER BY  $table_teams.full_name ASC";
        

        $stmt = $db->prepare($query);

        $sqlVars[':prevweek'] = $current_week - 1;
        $sqlVars[':playerId'] = $player_id;

        $stmt->execute($sqlVars);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {            
            $results[] = new GameTeams($row, $row['id']);
        }
        
        return $results;
    }

}

?>
