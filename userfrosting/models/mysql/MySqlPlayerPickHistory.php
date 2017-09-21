<?php

namespace UserFrosting;

/**
 * @property string player_name 
 * @property int active 
 * @property int enabled
 * @property int primary_user_id 
 */
class MySqlPlayerPickHistory extends MySqlDatabaseObject {
    
    protected $_primary_user;         // An undefined value means that the user's groups have not been loaded yet
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTablePlayerPickHistory();
        $this->_columns = static::$columns_player_pick_history;
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
    
    //Delete Player Picked
    public function delete(){        
        // Can only delete an object where `id` is set
        if (!$this->_id) {
            return false;
        }
        
        $result = parent::delete();         

        return $result;
    }
    
    // Return Game Past Player Picks till current week
    public function getPastPlayerPicksHistory($current_week, $player_ids){
        $db = static::connection();

        $player_pick_table = static::getTablePlayerPick();
        $player_table = static::getTablePlayers();
        $teams_table = static::getTableGameTeams();

        $results = [];
        
        foreach($player_ids as $player)
        {
            
            $query = "
            SELECT $player_pick_table.id, $player_table.player_name, $player_table.user_name, $player_table.primary_user_id, $player_table.lost, $teams_table.full_name, $player_pick_table.team_id, $player_pick_table.week_id, $player_pick_table.picked_time_stamp
            FROM $player_pick_table
            INNER JOIN $player_table 
            ON $player_table.id = $player_pick_table.player_id
            INNER JOIN $teams_table 
            ON $teams_table.id = $player_pick_table.team_id 
            WHERE $player_pick_table.week_id <=:currweek
            AND $player_pick_table.player_id =:playerId
            GROUP BY $player_pick_table.week_id";
            
            $stmt = $db->prepare($query);

            $sqlVars[':currweek'] = $current_week;
            $sqlVars[':playerId'] = $player->id;

            $stmt->execute($sqlVars);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                //$id = $row['id'];
                $results[] = $row;
            }
                         
        }
        
        return $results;
    }


    // Return Game Past Player Picks till current week
    public function getAllPastPlayerPicks($current_week){
        $db = static::connection();

        $player_pick_table = static::getTablePlayerPick();
        $player_table = static::getTablePlayers();
        $teams_table = static::getTableGameTeams();

        $results = [];
            
        $query = "
        SELECT $player_pick_table.id, $player_table.player_name, $player_table.user_name, $player_table.primary_user_id, $player_table.lost, $teams_table.full_name, $player_pick_table.team_id, $player_pick_table.week_id, $player_pick_table.picked_time_stamp
        FROM $player_pick_table
        INNER JOIN $player_table 
        ON $player_table.id = $player_pick_table.player_id
        INNER JOIN $teams_table 
        ON $teams_table.id = $player_pick_table.team_id 
        WHERE $player_pick_table.week_id <=:currweek";

        $stmt = $db->prepare($query);

        $sqlVars[':currweek'] = $current_week;            

        $stmt->execute($sqlVars);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            //$id = $row['id'];
            $results[] = $row;
        }
        
        return $results;
    }
        
  

}

?>
