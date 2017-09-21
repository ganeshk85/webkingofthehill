<?php

namespace UserFrosting;

/**
 * @property string player_name 
 * @property int active 
 * @property int enabled
 * @property int primary_user_id 
 */
class MySqlGameTeams extends MySqlDatabaseObject {
    
    protected $_primary_user;         // An undefined value means that the user's groups have not been loaded yet
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTableGameTeams();
        $this->_columns = static::$columns_teams;             
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
    
}

?>
