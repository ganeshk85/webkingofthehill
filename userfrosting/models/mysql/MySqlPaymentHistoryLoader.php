<?php

namespace UserFrosting;

/* This class is responsible for retrieving User object(s) from the database, checking for existence, etc. */

class MySqlPaymentHistoryLoader extends MySqlObjectLoader {
    protected static $_columns;     // A list of the allowed columns for this type of DB object. Must be set in the child concrete class.  DO NOT USE `id` as a column!
    protected static $_table;       // The name of the table whose rows this class represents. Must be set in the child concrete class.    
       
    public static function init(){
        // Set table and columns for this class.  Kinda hacky but I don't see any other way to do it.
        static::$_table = static::getTablePaymentHistory();
        static::$_columns = static::$columns_payment_history;
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
            return new PaymentHistory($results, $results['id']);
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
            $results[$id] = new PaymentHistory($user, $id);

        return $results;
    }
    
    //Return the enum_list of the payment_method column in kh_payment_history table from database
    public static function getPaymentMethods(){
        $db = static::connection();

        $table_payment_history = static::getTablePaymentHistory();
   
        $query = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME =:table_name AND COLUMN_NAME =:column_name";
                

        $stmt = $db->prepare($query);
        
        $sqlVars[':table_name'] = $table_payment_history;
        $sqlVars[':column_name'] = 'payment_method';        
        
        $stmt->execute($sqlVars);
        
        $results = $stmt->fetch(\PDO::FETCH_ASSOC);        
        
        if($results)
        {
            $payment_method_list = explode(",", str_replace("'", "", substr($results['COLUMN_TYPE'], 5, (strlen($results['COLUMN_TYPE'])-6))));
        }
        else
        {
            $payment_method_list = [];
        }
                        
        return $payment_method_list;
    }
}

?>
