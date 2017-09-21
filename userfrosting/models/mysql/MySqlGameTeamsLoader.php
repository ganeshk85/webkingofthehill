<?php

namespace UserFrosting;

/* This class is responsible for retrieving Group object(s) from the database, checking for existence, etc. */

class MySqlGameTeamsLoader extends MySqlObjectLoader{
    protected static $_columns;     // A list of the allowed columns for this type of DB object. Must be set in the child concrete class.  DO NOT USE `id` as a column!
    protected static $_table;       // The name of the table whose rows this class represents. Must be set in the child concrete class.    
    
    public static function init(){
        // Set table and columns for this class.  Kinda hacky but I don't see any other way to do it.
        static::$_table = static::getTableGameTeams();
        static::$_columns = static::$columns_teams;
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
            return new GameTeams($results, $results['id']);
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
            $results[$id] = new GameTeams($team, $id);

        return $results;
    }

    public static function fetchTop10($week){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();
        $table_teams = static::getTableGameTeams();

        $results = [];

        $query = "SELECT $table_teams.id, $table_teams.code, $table_teams.full_name, count($table_teams.id) as numplayers
                FROM $table_teams INNER JOIN $table_player_picks
                ON $table_teams.id = $table_player_picks.team_id
                INNER JOIN $table_players
                ON $table_players.id = $table_player_picks.player_id
                WHERE $table_players.lost != 1 AND $table_players.enabled != 0 AND $table_player_picks.week_id =:week_id
                GROUP BY $table_teams.id
                ORDER BY numplayers DESC LIMIT 12";

        $stmt = $db->prepare($query);

        $sqlVars[':week_id'] = $week;
        //$sqlVars[':userId'] = $user_id;

        $stmt->execute($sqlVars);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;        
    }
    
}

?>
