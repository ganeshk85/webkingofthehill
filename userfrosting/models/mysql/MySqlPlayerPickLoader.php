<?php

namespace UserFrosting;

/* This class is responsible for retrieving Group object(s) from the database, checking for existence, etc. */

class MySqlPlayerPickLoader extends MySqlObjectLoader{
    protected static $_columns;     // A list of the allowed columns for this type of DB object. Must be set in the child concrete class.  DO NOT USE `id` as a column!
    protected static $_table;       // The name of the table whose rows this class represents. Must be set in the child concrete class.    
    
    public static function init(){
        // Set table and columns for this class.  Kinda hacky but I don't see any other way to do it.
        static::$_table = static::getTablePlayerPick();
        static::$_columns = static::$columns_player_pick;
    }
    
    /* Determine if a group exists based on the value of a given column.  Returns true if a match is found, false otherwise.
     * @param value $value The value to find.
     * @param string $name The name of the column to match (defaults to id)
     * @return bool
    */
    public static function exists($value, $name = "id"){
        return parent::fetch($value, $name);
    }
   
    /* Fetch a single group based on the value of a given column.  For non-unique columns, it will return the first entry found.  Returns false if no match is found.
     * @param value $value The value to find.
     * @param string $name The name of the column to match (defaults to id)
     * @return Group
    */
    public static function fetch($value, $name = "id"){
        $results = parent::fetch($value, $name);
        
        if ($results)
            return new PlayerPick($results, $results['id']);
        else
            return false;
    }

    /* Fetch a list of groups based on the value of a given column.  Returns empty array if no match is found.
     * @param value $value The value to find. (defaults to null, which means return all records in the table)
     * @param string $name The name of the column to match (defaults to null)
     * @return array An array of Group objects
    */
    public static function fetchAll($value = null, $name = null){
        $resultArr = parent::fetchAll($value, $name);
        
        $results = [];
        foreach ($resultArr as $id => $team)
            $results[$id] = new PlayerPick($team, $id);

        return $results;
    }
    
    // Return if player has already picked a team for that particular pick for a particular player
    public static function pickExists($team_id, $player_id){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();

        $query = "SELECT $table_player_picks.*
                FROM $table_player_picks                
                WHERE $table_player_picks.team_id =:teamId AND $table_player_picks.player_id =:playerId LIMIT 1";

        $stmt = $db->prepare($query);
        
        $sqlVars[':teamId'] = $team_id;
        $sqlVars[':playerId'] = $player_id;        
        
        $stmt->execute($sqlVars);

        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // PDO returns false if no record is found
        return $results;
    }

    // Return player Pick of each player per user
    public static function fetchCurrentPlayerPicksPerUser($current_week, $player_ids){
        $db = static::connection();

        $player_pick_table = static::getTablePlayerPick();
        $player_table = static::getTablePlayers();
        $teams_table = static::getTableGameTeams();

        $results = [];

        foreach($player_ids as $player)
        {

            $query = "
            SELECT $player_pick_table.id, $player_table.player_name, $player_table.user_name, $player_table.primary_user_id, $player_table.active, $player_table.enabled, $player_table.lost, $teams_table.full_name, $player_pick_table.week_id, $player_pick_table.picked_time_stamp
            FROM $player_pick_table
            INNER JOIN $player_table
            ON $player_table.id = $player_pick_table.player_id
            INNER JOIN $teams_table
            ON $teams_table.id = $player_pick_table.team_id
            WHERE $player_pick_table.week_id =:currweek
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
    
}

?>
