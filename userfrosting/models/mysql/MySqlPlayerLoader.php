<?php

namespace UserFrosting;

/* This class is responsible for retrieving User object(s) from the database, checking for existence, etc. */

class MySqlPlayerLoader extends MySqlObjectLoader {
    protected static $_columns;     // A list of the allowed columns for this type of DB object. Must be set in the child concrete class.  DO NOT USE `id` as a column!
    protected static $_table;       // The name of the table whose rows this class represents. Must be set in the child concrete class.    
       
    public static function init(){
        // Set table and columns for this class.  Kinda hacky but I don't see any other way to do it.
        static::$_table = static::getTablePlayers();
        static::$_columns = static::$columns_players;
    }       

    /* Determine if a user exists based on the value of a given column.  Returns true if a match is found, false otherwise.
     * @param value $value The value to find.
     * @param string $name The name of the column to match (defaults to id)
     * @return bool
    */
    public static function exists($value, $name = "id"){
        return parent::fetch($value, $name);
    }
   
    /* Fetch a single user based on the value of a given column.  For non-unique columns, it will return the first entry found.  Returns false if no match is found.
     * @param value $value The value to find.
     * @param string $name The name of the column to match (defaults to id)
     * @return User
    */
    public static function fetch($value, $name = "id"){
        $results = parent::fetch($value, $name);
        
        if ($results)
            return new Player($results, $results['id']);
        else
            return false;
    }
    
    /* Fetch a list of users based on the value of a given column.  Returns empty array if no match is found.
     * @param value $value The value to find. (defaults to null, which means return all records in the table)
     * @param string $name The name of the column to match (defaults to null)
     * @return array An array of User objects
    */
    public static function fetchAll($value = null, $name = null){
        $resultArr = parent::fetchAll($value, $name);
        
        $results = [];
        foreach ($resultArr as $id => $user)
            $results[$id] = new Player($user, $id);

        return $results;
    }

    public static function fetchAdminAll(){
        $db = static::connection();

        $table_users = static::getTableUser();
        $table_players = static::getTablePlayers();

        $results = [];

        $query = "SELECT $table_players.*, $table_users.payment_status
                FROM $table_players INNER JOIN $table_users
                ON $table_players.primary_user_id = $table_users.id                
                GROUP BY $table_players.id
                ORDER BY $table_players.id ASC";

        $stmt = $db->prepare($query);

        //$sqlVars[':prevweek'] = $current_week - 1;
        //$sqlVars[':userId'] = $user_id;

        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;
    }
    
    public static function fetchTop10Active(){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();

        $results = [];

        $query = "SELECT $table_players.id, $table_players.player_name, count($table_players.id) as numgameswon
                FROM $table_players INNER JOIN $table_player_picks
                ON $table_players.id = $table_player_picks.player_id                
                WHERE $table_players.lost != 1 AND $table_players.enabled != 0
                GROUP BY $table_players.id
                ORDER BY numgameswon DESC LIMIT 10";

        $stmt = $db->prepare($query);

        //$sqlVars[':prevweek'] = $current_week - 1;
        //$sqlVars[':userId'] = $user_id;

        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;
    }
    
    public static function fetchPlayersNoPick($weekId){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();
        $table_users = static::getTableUser();

        $query = "
            SELECT $table_players.*, $table_users.payment_status
            FROM $table_players INNER JOIN $table_users
            ON $table_players.primary_user_id = $table_users.id  
            WHERE $table_players.enabled != 0 AND $table_players.id
            NOT IN
            (SELECT $table_player_picks.player_id
             FROM $table_player_picks
             WHERE $table_player_picks.week_id = :weekId )
            GROUP BY $table_players.id
            ORDER BY  $table_players.player_name ASC";

        $stmt = $db->prepare($query);

        $sqlVars[':weekId'] = $weekId;        

        $stmt->execute($sqlVars);

        //error_log(print_r($stmt->fetch(\PDO::FETCH_ASSOC)));
        $results = [];
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }
        
        /*while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $results[$id] = new Player($row, $row['id']);
        }*/        

        return $results;
    }
    
    public static function getCountTotalActivePlayers(){
        $db = static::connection();
        
        $table_players = static::getTablePlayers();

        $results = [];

        $query = "SELECT count($table_players.id) as total_active_players
                FROM $table_players                
                WHERE $table_players.active = 1 AND $table_players.enabled = 1";

        $stmt = $db->prepare($query);

        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
        return $row;
    }
    
    public static function fetchPlayersLost(){
        $db = static::connection();
        
        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();

        $results = [];

        $query = "SELECT $table_players.*
                FROM $table_players INNER JOIN $table_player_picks
                ON $table_players.id = $table_player_picks.player_id
                WHERE $table_players.lost = 1
                GROUP BY $table_players.id";
        
        /*$query = "SELECT $table_players.*
                FROM $table_players                
                WHERE $table_players.lost = 1";*/

        $stmt = $db->prepare($query);

        //$sqlVars[':prevweek'] = $current_week - 1;
        //$sqlVars[':userId'] = $user_id;

        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;
    }

    public static function fetchPlayersWon(){
        $db = static::connection();

        $table_player_picks = static::getTablePlayerPick();
        $table_players = static::getTablePlayers();

        $results = [];

        $query = "SELECT $table_players.*
                FROM $table_players INNER JOIN $table_player_picks
                ON $table_players.id = $table_player_picks.player_id
                WHERE $table_players.enabled = 1 AND $table_players.lost = 0
                GROUP BY $table_players.id";

        /*$query = "SELECT $table_players.*
                FROM $table_players
                WHERE $table_players.lost = 1";*/

        $stmt = $db->prepare($query);

        //$sqlVars[':prevweek'] = $current_week - 1;
        //$sqlVars[':userId'] = $user_id;

        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        return $results;
    }
}

?>
