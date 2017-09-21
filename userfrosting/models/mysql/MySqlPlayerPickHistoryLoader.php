<?php

namespace UserFrosting;

/* This class is responsible for retrieving Group object(s) from the database, checking for existence, etc. */

class MySqlPlayerPickHistoryLoader extends MySqlObjectLoader{
    protected static $_columns;     // A list of the allowed columns for this type of DB object. Must be set in the child concrete class.  DO NOT USE `id` as a column!
    protected static $_table;       // The name of the table whose rows this class represents. Must be set in the child concrete class.    
    
    public static function init(){
        // Set table and columns for this class.  Kinda hacky but I don't see any other way to do it.
        static::$_table = static::getTablePlayerPickHistory();
        static::$_columns = static::$columns_player_pick_history;
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
            return new PlayerPickHistory($results, $results['id']);
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
            $results[$id] = new PlayerPickHistory($team, $id);

        return $results;
    }
    
    // Return if player has already picked a team for that particular pick for a particular player
    public static function pickExists($team_id, $player_id){
        $db = static::connection();

        $table_player_picks_history = static::getTablePlayerPickHistory();

        $query = "SELECT $table_player_picks_history.*
                FROM $table_player_picks_history                
                WHERE $table_player_picks_history.new_team_id =:teamId AND $table_player_picks_history.player_id =:playerId LIMIT 1";

        $stmt = $db->prepare($query);
        
        $sqlVars[':teamId'] = $team_id;
        $sqlVars[':playerId'] = $player_id;        
        
        $stmt->execute($sqlVars);

        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // PDO returns false if no record is found
        return $results;
    }

    public static function fetchAllPlayerPickHistory(){
        $db = static::connection();

        $player_pick_history_table = static::getTablePlayerPickHistory();
        $player_table = static::getTablePlayers();
        $teams_table = static::getTableGameTeams();
        $user_table = static::getTableUser();

        $results = [];

        //foreach($player_ids as $player)
        //{

            $query = "
            SELECT $player_pick_history_table.id, $player_table.player_name, $user_table.display_name, $user_table.id, $user_table.primary_group_id, new_team.full_name as new_team, old_team.full_name as old_team, $player_pick_history_table.new_team_id, $player_pick_history_table.old_team_id, $player_pick_history_table.week_id, $player_pick_history_table.picked_time_stamp
            FROM $player_pick_history_table
            INNER JOIN $player_table
            ON $player_table.id = $player_pick_history_table.player_id
            INNER JOIN $user_table
            ON $player_pick_history_table.edited_by_user_id = $user_table.id
            INNER JOIN $teams_table new_team
            ON new_team.id = $player_pick_history_table.new_team_id
            INNER JOIN $teams_table old_team
            ON old_team.id = $player_pick_history_table.old_team_id
            ORDER BY $player_pick_history_table.picked_time_stamp DESC";

            $stmt = $db->prepare($query);

            //$sqlVars[':userId'] = $user_id;

            $stmt->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                //$id = $row['id'];
                $results[] = $row;
            }

        //}

        return $results;
    }
    
}

?>
