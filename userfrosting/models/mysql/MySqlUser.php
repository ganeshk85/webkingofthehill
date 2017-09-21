<?php

namespace UserFrosting;

/**
 * @property string user_name
 * @property string display_name
 * @property string email
 * @property string password
 * @property string title
 * @property int activation_token
 * @property datetime last_activation_request
 * @property int lost_password_request
 * @property datetime lost_password_timestamp
 * @property int active
 * @property datetime sign_up_stamp
 * @property datetime last_sign_in_stamp
 * @property int enabled
 * @property int primary_group_id
 * @property string locale
 */
class MySqlUser extends MySqlDatabaseObject implements UserObjectInterface {
    
    protected $_groups;         // An undefined value means that the user's groups have not been loaded yet
    protected $_primary_group;  // The primary group for the user.  TODO: simply fetch it from the _groups array?
    protected $_players;
    
    public function __construct($properties, $id = null) {
        $this->_table = static::getTableUser();
        $this->_columns = static::$columns_user;
        // Set default locale, if not specified
        if (!isset($properties['locale']))
            $properties['locale'] = static::$app->site->default_locale;       
        parent::__construct($properties, $id);
    }
    
    // Determine whether this User is a guest (id set to user_id_guest) or a live, logged-in user
    public function isGuest(){
        if (!isset($this->_id) || $this->_id === static::$app->config('user_id_guest'))
            return true;
        else
            return false;
    }
    
    // Determine whether this User is a Admin
    public function isAdmin(){
        $user_groups = $this->getGroups();
        $isadmin = 0;
		
        foreach($user_groups as $group)
        {            
            if($group->id == 2)
            {                
                $isadmin = 1;
            }            
        }
        return $isadmin;
    }
    
    /* Determine if this user is currently logged in. */
    public static function isLoggedIn(){
        // TODO.  Not sure how to implement this right now.  Flag in DB?  Or, check sessions?
    }
    
    /* Refresh the User and their associated Groups from the DB.
     *
     */
    public function fresh(){
        // Update table and column info, in case it has changed
        $this->_table = static::getTableUser();
        $this->_columns = static::$columns_user;
        $user = new User(parent::fresh(), $this->_id);
        $user->_groups = $this->fetchGroups();
        $user->_primary_group = $this->fetchPrimaryGroup();
        return $user;
    }
    
    // Must be implemented for compatibility with Twig
    public function __isset($name) {
        if ($name == "primary_group" || $name == "theme" || $name == "icon" || $name == "landing_page")
            return isset($this->_primary_group);
        else
            return parent::__isset($name);
    }
    
    // Getter
    public function __get($name){
        if ($name == "primary_group")
            return $this->getPrimaryGroup();
        else if ($name == "theme")
            return $this->getPrimaryGroup()->theme;
        else if ($name == "icon")
            return $this->getPrimaryGroup()->icon;
        else if ($name == "landing_page")
            return $this->getPrimaryGroup()->landing_page;
        else
            return parent::__get($name);
    }
    
    // Get a list of groups to which this user belongs, lazily loading them if not already set
    public function getGroups(){
        if (!isset($this->_groups))
            $this->_groups = $this->fetchGroups();
            
        return $this->_groups;
    }

    // Add this user to a specified group.  Won't be stored in DB until store() is called.
    public function addGroup($group_id){
        // First, load current groups for user
        $this->getGroups();
        // Return if user already in group
        if (isset($this->_groups[$group_id]))
            return $this;
        
        // Next, check that the requested group actually exists
        if (!GroupLoader::exists($group_id))
            throw new \Exception("The specified group_id ($group_id) does not exist.");
                
        // Ok, add to the list of groups
        $this->_groups[$group_id] = GroupLoader::fetch($group_id);
        
        return $this;        
    }
    
    // Remove this user from a specified group.  Won't be stored in DB until store() is called.
    public function removeGroup($group_id){
        // First, load current groups for user
        $this->getGroups();
        // Return if user not in group
        if (!isset($this->_groups[$group_id]))
            return $this;
                
        // Ok, remove from the list of groups
        unset($this->_groups[$group_id]);
        
        return $this;           
    
    }
       
    // Fetch an array of Groups that this User belongs to from the database
    private function fetchGroups(){
        $db = static::connection();

        $link_table = static::getTableGroupUser();
        $group_table = static::getTableGroup();
        
        $query = "
            SELECT $group_table.*
            FROM $link_table, $group_table
            WHERE $link_table.user_id = :id
            AND $link_table.group_id = $group_table.id";
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':id'] = $this->_id;
        
        $stmt->execute($sqlVars);
        
        // For now just create an array of Group objects.  Later we can implement GroupCollection for faster access.
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $results[$id] = new Group($row, $row['id']);
        }
        return $results;        
    }

    // Get the theme for this user, based on their primary group.  Guest/undefined user returns the default theme.  Master user returns the root theme.  Lazy load.
    public function getTheme(){
        if (!isset($this->_id) || $this->_id == static::$app->config('user_id_guest'))
            return "default";
        else if ($this->_id == static::$app->config('user_id_master'))
            return "root";
        else
            return $this->getPrimaryGroup()->theme;
    }
    
    // Get the primary group to which this user belongs.  Lazy load into object.
    public function getPrimaryGroup(){
        if (!isset($this->_primary_group))
            $this->_primary_group = $this->fetchPrimaryGroup();
            
        return $this->_primary_group;
    }
    
    private function fetchPrimaryGroup() {
        if (!isset($this->primary_group_id)){
            throw new \Exception("This user does not appear to have a primary group id set.");
        }
        $db = static::connection();
        $group_table = static::getTableGroup();
        
        $query = "
            SELECT $group_table.*
            FROM $group_table
            WHERE $group_table.id = :primary_group_id LIMIT 1";
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':primary_group_id'] = $this->primary_group_id;
        
        $stmt->execute($sqlVars);
        
        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($results)
            return new Group($results, $results['id']);
        else
            return false;        
    }
 
    public function store($force_create = false){
        // Initialize timestamps for new Users.  Should this be done here, or somewhere else?
        if (!isset($this->_id) || $force_create){
            $this->sign_up_stamp = date("Y-m-d H:i:s");
            $this->activation_token = UserLoader::generateActivationToken();
            $this->last_activation_request = date("Y-m-d H:i:s");
        }
        
        // Update the user record itself
        parent::store();
        
        // Get the User object's current groups
        $this->getGroups();
        
        // Get the User's groups as stored in the DB
        $db_groups = $this->fetchGroups();

        $link_table = static::getTableGroupUser();

        // Add any groups in object that are not in DB yet
        $db = static::connection();
        $query = "
            INSERT INTO $link_table (user_id, group_id)
            VALUES (:user_id, :group_id);";
        foreach ($this->_groups as $group_id => $group){
            $stmt = $db->prepare($query);          
            if (!isset($db_groups[$group_id])){
                $sqlVars = [
                    ':group_id' => $group_id,
                    ':user_id' => $this->_id
                ];
                $stmt->execute($sqlVars);
            } 
        }
        
        // Remove any group links in DB that are no longer modeled in this object
        if ($db_groups){
            $db = static::connection();
            $query = "
                DELETE FROM $link_table
                WHERE group_id = :group_id
                AND user_id = :user_id LIMIT 1";
            
            $stmt = $db->prepare($query);          
            foreach ($db_groups as $group_id => $group){
                if (!isset($this->_groups[$group_id])){
                    $sqlVars = [
                        ':group_id' => $group_id,
                        ':user_id' => $this->_id
                    ];
                    $stmt->execute($sqlVars);
                }
            }
        }
        
        // Store function should always return the id of the object
        return $this->_id;
    }
    
    /*** Delete this user from the database, along with any linked groups and authorization rules
    ***/
    public function delete(){        
        // Can only delete an object where `id` is set
        if (!$this->_id) {
            return false;
        }
        
        $result = parent::delete();
        
        // Get connection
        $db = static::connection();
        $link_table = static::getTableGroupUser();
        $auth_table = static::getTableAuthorizeUser();
        
        $sqlVars[":id"] = $this->_id;
        
        $query = "
            DELETE FROM $link_table
            WHERE user_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);
     
        $query = "
            DELETE FROM $auth_table
            WHERE user_id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->execute($sqlVars);     

        return $result;
    }
    
    // Determine if this user has access to the given $hook under the given $params
    public function checkAccess($hook, $params = []){
        if ($this->isGuest()){   // TODO: do we sometimes want to allow access to protected resources for guests?  Should we model a "guest" group?
            return false;
        }
    
        // The master (root) account has access to everything.
        if ($this->_id == static::$app->config('user_id_master'))
            return true;
             
        // Try to find an authorization rule for $hook that matches the currently logged-in user, or one of their groups.
        $rule = AuthLoader::fetchUserAuthHook($this->_id, $hook);
        
        if (empty($rule))
            $pass = false;
        else {      
            $ace = new AccessConditionExpression(static::$app); // TODO: should we have to pass the app in, or just make it available statically?
            $pass = $ace->evaluateCondition($rule['conditions'], $params);
        }
        
        // If no user-specific rule is passed, look for a group-level rule
        if (!$pass){
            $ace = new AccessConditionExpression(static::$app);
            $groups = $this->getGroups();
            foreach ($groups as $group){
                // Try to find an authorization rule for $hook that matches this group
                $rule = AuthLoader::fetchGroupAuthHook($group->id, $hook);
                if (!$rule)
                    continue;
                $pass = $ace->evaluateCondition($rule['conditions'], $params);
                if ($pass)
                    break;
            }
        }
        return $pass;
    }
 
    // Check that the specified password (unhashed) matches this user's password (hashed).
    public function verifyPassword($password){
        if (Authentication::getPasswordHashType($this->password) == "sha1"){
            $salt = substr($this->password, 0, 25);		// Extract the salt from the hash
            $hash_input = $salt . sha1($salt . $password);
            if ($hash_input == $this->password){
                return true;
            } else {
                return false;
            }
        }	
        // Homegrown implementation (assuming that current install has been using a cost parameter of 12)
        else if (Authentication::getPasswordHashType($this->password) == "homegrown"){
            /*used for manual implementation of bcrypt*/
            $cost = '12'; 
            if (substr($this->password, 0, 60) == crypt($password, "$2y$".$cost."$".substr($this->password, 60))){
                return true;
            } else {
                return false;
            }
        // Modern implementation
        } else {
            return password_verify($password, $this->password);
        }    
    }
    
    public function login($password){
        // Check the password
        if (!$this->verifyPassword($password))
            return false;
    
        //Update last sign in
        $this->last_sign_in_stamp = date("Y-m-d H:i:s");
        
        // Update password if we had encountered an outdated hash
        if (Authentication::getPasswordHashType($this->password) != "modern"){
            // Hash the user's password and update
            $password_hash = Authentication::getPasswordHashType($password);
            if ($password_hash === null){
                error_log("Notice: outdated password hash could not be updated because the new hashing algorithm is not supported.  Are you running PHP >= 5.3.7?");
            } else {
                $this->password = $password_hash;
                error_log("Notice: outdated password hash has been automatically updated to modern hashing.");
            }
        }
        
        $this->store();
        
        return $this;
    }
    
    //Player functions
    // Get a list of players that user has
    public function getPlayers(){
        if (!isset($this->_players))
            $this->_players = $this->fetchPlayers();
            
        return $this->_players;
    }
    
    // Fetch an array of Players that this User belongs to from the database
    private function fetchPlayers(){
        $db = static::connection();
        
        $player_table = static::getTablePlayers();
        
        $query = "
            SELECT $player_table.*
            FROM $player_table
            WHERE $player_table.primary_user_id = :id";
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':id'] = $this->_id;
        
        $stmt->execute($sqlVars);
        
        // For now just create an array of Group objects.  Later we can implement GroupCollection for faster access.
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $results[$id] = new Player($row, $row['id']);
        }
        return $results;        
    }

    // Get a list of players with payment status
    public function getPlayersWithPaymentStatus(){
        if (!isset($this->_players))
            $this->_players = $this->fetchPlayersWithPaymentStatus();
            
        return $this->_players;
    }
    
    // Fetch an array of Players that this User belongs to from the database with Payement Status
    private function fetchPlayersWithPaymentStatus(){
        $db = static::connection();
        
        $table_players = static::getTablePlayers();
        $table_users = static::getTableUser();        
        
        $query = "SELECT $table_players.*, $table_users.payment_status
                FROM $table_players INNER JOIN $table_users
                ON $table_players.primary_user_id = $table_users.id                
                WHERE $table_players.primary_user_id = :id
                GROUP BY $table_players.id
                ORDER BY $table_players.id ASC";
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':id'] = $this->_id;
        
        $stmt->execute($sqlVars);
        
        // For now just create an array of Group objects.  Later we can implement GroupCollection for faster access.
        $results = [];
                
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }
        
        return $results;        
    }

    
    public function createPayment(){
        if (!isset($this->user_name)){
            throw new \Exception("This user does not appear to have a user id set.");
        }
        
        $db = static::connection();
        
        $payment_table = static::getTablePayments();
        
        $query = "
            INSERT INTO $payment_table (user_id)
            VALUES (:user_id);";            
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':user_id'] = $this->_id;
        
        $stmt->execute($sqlVars);
        
    }
    
    public function deletePayment(){
        if (!isset($this->_id)){
            throw new \Exception("This user does not appear to have a user id set.");
        }
        
        $db = static::connection();
        
        $payment_table = static::getTablePayments();
        
        $query = "
            DELETE FROM $payment_table 
            WHERE $payment_table.user_id = :user_id";            
        
        $stmt = $db->prepare($query);
        
        $sqlVars[':user_id'] = $this->_id;
        
        $stmt->execute($sqlVars);
        
    }
    
    public function getPlayerPickHistory($user_id){
        $db = static::connection();

        $player_pick_history_table = static::getTablePlayerPickHistory();
        $player_table = static::getTablePlayers();
        $teams_table = static::getTableGameTeams();

        $results = [];
        
        //foreach($player_ids as $player)
        //{
            
            $query = "
            SELECT $player_pick_history_table.id, $player_table.player_name, $player_table.user_name, $player_table.primary_user_id, $player_table.lost, new_team.full_name as new_team, old_team.full_name as old_team, $player_pick_history_table.new_team_id, $player_pick_history_table.old_team_id, $player_pick_history_table.week_id, $player_pick_history_table.picked_time_stamp
            FROM $player_pick_history_table
            INNER JOIN $player_table 
            ON $player_table.id = $player_pick_history_table.player_id
            INNER JOIN $teams_table new_team
            ON new_team.id = $player_pick_history_table.new_team_id             
            INNER JOIN $teams_table old_team
            ON old_team.id = $player_pick_history_table.old_team_id
            WHERE $player_pick_history_table.user_id =:userId
            ORDER BY $player_pick_history_table.picked_time_stamp DESC";
            
            $stmt = $db->prepare($query);

            $sqlVars[':userId'] = $user_id;

            $stmt->execute($sqlVars);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                //$id = $row['id'];
                $results[] = $row;
            }
                         
        //}
        
        return $results;
    }
	
	// Return Past Player Picks till current week per user
    public function getPastPlayerPicksPerUser($current_week, $user_id){
        $db = static::connection();

        $player_pick_table = static::getTablePlayerPick();
        $player_table = static::getTablePlayers();
        $teams_table = static::getTableGameTeams();

        $results = [];
            
        $query = "
        SELECT $player_pick_table.id, $player_table.player_name, $player_table.user_name, $player_table.primary_user_id, $player_table.active, $player_table.enabled, $player_table.lost, $teams_table.full_name, $player_pick_table.week_id, $player_pick_table.picked_time_stamp
        FROM $player_pick_table
        INNER JOIN $player_table 
        ON $player_table.id = $player_pick_table.player_id
        INNER JOIN $teams_table 
        ON $teams_table.id = $player_pick_table.team_id 
        WHERE $player_pick_table.week_id <=:currweek
        AND $player_pick_table.user_id =:userId";
        
        $stmt = $db->prepare($query);

        $sqlVars[':currweek'] = $current_week;
        $sqlVars[':userId'] = $user_id;

        $stmt->execute($sqlVars);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            //$id = $row['id'];
            $results[] = $row;
        }
        
        return $results;
    }
}

?>
