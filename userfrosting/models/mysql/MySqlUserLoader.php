<?php

namespace UserFrosting;

/* This class is responsible for retrieving User object(s) from the database, checking for existence, etc. */

class MySqlUserLoader extends MySqlObjectLoader implements UserLoaderInterface {
    protected static $_columns;     // A list of the allowed columns for this type of DB object. Must be set in the child concrete class.  DO NOT USE `id` as a column!
    protected static $_table;       // The name of the table whose rows this class represents. Must be set in the child concrete class.    
       
    public static function init(){
        // Set table and columns for this class.  Kinda hacky but I don't see any other way to do it.
        static::$_table = static::getTableUser();
        static::$_columns = static::$columns_user;
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
            return new User($results, $results['id']);
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
            $results[$id] = new User($user, $id);

        return $results;
    }
    
    //Generate an activation key for a user
    public static function generateActivationToken($gen = null) {
        do {
            $gen = md5(uniqid(mt_rand(), false));
        } while(static::exists($gen, 'activation_token'));
        return $gen;
    }
    
    public static function getCountTotalActiveUsers(){
        $db = static::connection();
        
        $table_users = static::getTableUser();

        $results = [];

        $query = "SELECT count($table_users.id) as total_active_users
                FROM $table_users                
                WHERE $table_users.active = 1 AND $table_users.primary_group_id != 2";

        $stmt = $db->prepare($query);

        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
        return $row;
    }
}

?>
