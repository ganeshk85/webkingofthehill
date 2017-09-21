<?php

namespace UserFrosting;

/**
 * @property string player_name 
 * @property int active 
 * @property int enabled
 * @property int primary_user_id 
 */
class MySqlPaymentHistory extends MySqlDatabaseObject implements PaymentObjectInterface {
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTablePaymentHistory();
        $this->_columns = static::$columns_payment_history;
        
        parent::__construct($properties, $id);
    }
    
    // Must be implemented for compatibility with Twig
    public function __isset($name) {
        if ($name == "primary_group" || $name == "theme" || $name == "icon" || $name == "landing_page")
            return isset($this->_primary_group);
        else
            return parent::__isset($name);
    }
    
    public function store(){        
        parent::store();
        
        // Store function should always return the id of the object
        return $this->_id;
    }
    
    /*** Delete this payment from Database
    ***/
    public function delete(){        
        // Can only delete an object where `id` is set
        if (!$this->_id) {
            return false;
        }
        
        $result = parent::delete();

        return $result;
    }
    
}

?>
