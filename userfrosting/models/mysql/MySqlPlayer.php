<?php

namespace UserFrosting;

/**
 * @property string player_name 
 * @property int active 
 * @property int enabled
 * @property int primary_user_id 
 */
class MySqlPlayer extends MySqlDatabaseObject implements PlayerObjectInterface {
    
    protected $_primary_user;         // An undefined value means that the user's groups have not been loaded yet
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTablePlayers();
        $this->_columns = static::$columns_players;
        // Set default locale, if not specified
        if (!isset($properties['locale']))
            $properties['locale'] = static::$app->site->default_locale;       
        parent::__construct($properties, $id);
    }
    
    // Must be implemented for compatibility with Twig
    public function __isset($name) {
        if ($name == "primary_group" || $name == "theme" || $name == "icon" || $name == "landing_page")
            return isset($this->_primary_group);
        else
            return parent::__isset($name);
    }
    
    // Return a collection of Users.
    public function getUsers(){
        $db = static::connection();

        $user_table = static::getTableUser();        
        
        $query = "
            SELECT DISTINCT $user_table.*
            FROM $user_table";
        
        $stmt = $db->prepare($query);                
        
        $stmt->execute();
        
        // For now just create an array of Group objects.  Later we can implement GroupCollection for faster access.
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $results[$id] = new User($row, $row['id']);
        }
        return $results;
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
    
    //fetch payment record for the particular user id
    public function fetchPayment($primary_user_id = null) {
        if (!isset($this->primary_user_id)){
            throw new \Exception("This player does not appear to have a primary user id set.");
        }
        
        $db = static::connection();
        $payment_table = static::getTablePayments();
        
        $query = "
            SELECT $payment_table.*
            FROM $payment_table
            WHERE $payment_table.user_id = :user_id LIMIT 1";
        
        $stmt = $db->prepare($query);
        
        if(empty($primary_user_id))
        {
            $sqlVars[':user_id'] = $this->primary_user_id;
        }
        else
        {
            $sqlVars[':user_id'] = $primary_user_id;
        }
        
        $stmt->execute($sqlVars);
        
        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($results)
            return new Payment($results, $results['id']);
        else
            return false;        
    }
    
    public function store($force_create = false){
        // Initialize timestamps for new Players.
        if (!isset($this->_id) || $force_create){
            $this->sign_up_stamp = date("Y-m-d H:i:s");          
            $this->primary_user_id = $this->user_name;
            $this->user_name = $this->getPrimaryUser()->user_name;
            $this->display_name = $this->getPrimaryUser()->display_name;           
        }
        
        $default_pay_per_player = 31;
        
        //get player's primary users payment status
        $payment_status = $this->getPrimaryUser()->payment_status;
                
        //update user payment status only if previous payment status is Paid(1)
        if($payment_status != 0)
        {
            $this->getPrimaryUser()->payment_status = 0;
            $this->getPrimaryUser()->store();
        }
        
        //Add payment details when new player is created
        // Get the User's payments as stored in the DB
        $db_payments = $this->fetchPayment();

        $num_players = $db_payments->num_players;
        $total_amount = $db_payments->total_amount;
        $amount_due = $db_payments->amount_due;
        
        //update
        if (!isset($this->_id) || $force_create){
            $db_payments->num_players = $num_players + 1;
        
            $db_payments->total_amount = $total_amount + $default_pay_per_player;

            $db_payments->amount_due = $amount_due + $default_pay_per_player;
        }
                
        $db_payments->store();
        
        // Update the player record itself
        parent::store();
        
        // Store function should always return the id of the object
        return $this->_id;
    }
    
    /*** Delete this player from the database, along with any linked player picks
    ***/
    public function delete(){        
        // Can only delete an object where `id` is set
        if (!$this->_id) {
            return false;
        }
        
        $result = parent::delete();

        //delete the corresponding picks from player pick table
        // Get connection
        $db = static::connection();
        $player_pick_table = static::getTablePlayerPick();
        
        $sqlVars[":id"] = $this->_id;
        
        $query = "
            DELETE FROM $player_pick_table
            WHERE player_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);
        
        $default_pay_per_player = 31;
        
        //get player's primary users payment status
        $payment_status = $this->getPrimaryUser()->payment_status;
        
        //Update payment details when player is deleted
        // Get the User's payments as stored in the DB
        $db_payments = $this->fetchPayment();

        $num_players = $db_payments->num_players;
        $total_amount = $db_payments->total_amount;
        $amount_due = $db_payments->amount_due;
        $amount_paid = $db_payments->amount_paid;
        
        //update
        $db_payments->num_players = $num_players - 1;
        
        //update only if previous payment status is Not Paid(0)
        //if($payment_status == 0)
        //{
            if($db_payments->amount_due >= 30)
            {
                $db_payments->total_amount = $total_amount - $default_pay_per_player;

                $db_payments->amount_due = $amount_due - $default_pay_per_player;
                
                $db_payments->amount_paid = $amount_paid - $default_pay_per_player;
            } 
        //}
        
        $db_payments->store();
     
        return $result;
    }
    
}

?>
