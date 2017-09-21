<?php

namespace UserFrosting;

// Represents the UserFrosting database.  Used for initializing connections for queries.  Set $params to the connection variables you'd like to use.
abstract class UFDatabase {

    public static $app;         // The Slim app, containing configuration info

    protected static $table_user = "user";       
    protected static $table_players = "players";       
    protected static $table_group = "group";
    protected static $table_group_user = "group_user";
    protected static $table_configuration = "configuration";
    protected static $table_authorize_user = "authorize_user";
    protected static $table_authorize_group = "authorize_group";    
    protected static $table_gameschedule = "game_schedule";       
    protected static $table_teams = "teams";
    protected static $table_weeks = "weeks";
    protected static $table_player_pick = "player_picks";
    protected static $table_player_pick_history = "player_picks_history";
    protected static $table_payments = "payments";
    protected static $table_payment_history = "payment_history";
    
    protected static $columns_user = [
            "user_name",
            "display_name",
            "password",
            "email",
            "activation_token",
            "last_activation_request",
            "lost_password_request",
            "lost_password_timestamp",
            "active",
            "title",
            "sign_up_stamp",
            "last_sign_in_stamp",
            "enabled",
            "primary_group_id",
            "payment_status",
            "locale"
        ];
    
    protected static $columns_players = [
            "player_name",            
            "active",            
            "enabled",
            "lost",
            "primary_user_id",
            "user_name",
            "display_name",
            "sign_up_stamp"
        ];

    protected static $columns_group = [
            "name",
            "is_default",
            "can_delete",
            "theme",
            "landing_page",
            "new_user_title",
            "icon"
        ];
    
    protected static $columns_gameschedule = [
            "game_id",
            "game_week",            
            "game_date",            
            "away_team",
            "home_team",
            "game_time_et",
            "tv_station",
            "winner",
            "winner_team_id",
            "loser_team_id"
        ];
		
	protected static $columns_teams = [
            "code",
            "full_name",            
            "short_name",
            "games_won",
            "games_lost"
        ];

        protected static $columns_weeks = [
            "week_number",
            "start_date",
            "bye_teams"
        ];

        protected static $columns_player_pick = [
            "player_id",
            "week_id",
            "team_id",
            "user_id",
            "picked_time_stamp"
        ];
        
        protected static $columns_player_pick_history = [
            "player_id",
            "week_id",
            "new_team_id",
            "old_team_id",
            "user_id",
            "edited_by_user_id",
            "picked_time_stamp"
        ];
        
        protected static $columns_payments = [
            "user_id",
            "num_players",
            "total_amount",
            "amount_due",
            "amount_paid",
            "last_payment_date"
        ];
        
        protected static $columns_payment_history = [
            "user_id",
            "payment_id",
            "amount_paid",
            "payment_date",
            "payment_method",
            "check_number"
        ];
    
    public static function getTableUser(){
        return static::$app->config('db')['db_prefix'] . static::$table_user;
    }
    
    public static function getTablePlayers(){
        return static::$app->config('db')['db_prefix'] . static::$table_players;
    }

    public static function getTableGroup(){
        return static::$app->config('db')['db_prefix'] . static::$table_group;
    }
    
    public static function getTableGameSchedule(){
        return static::$app->config('db')['db_prefix'] . static::$table_gameschedule;
    }
	
    public static function getTableGameTeams(){
        return static::$app->config('db')['db_prefix'] . static::$table_teams;
    }

    public static function getTableWeeks(){
        return static::$app->config('db')['db_prefix'] . static::$table_weeks;
    }

    public static function getTablePlayerPick(){
        return static::$app->config('db')['db_prefix'] . static::$table_player_pick;
    }
    
    public static function getTablePlayerPickHistory(){
        return static::$app->config('db')['db_prefix'] . static::$table_player_pick_history;
    }

    public static function getTablePayments(){
        return static::$app->config('db')['db_prefix'] . static::$table_payments;
    }
    
    public static function getTablePaymentHistory(){
        return static::$app->config('db')['db_prefix'] . static::$table_payment_history;
    }
    
    public static function getTableGroupUser(){
        return static::$app->config('db')['db_prefix'] . static::$table_group_user;
    }
    
    public static function getTableConfiguration(){
        return static::$app->config('db')['db_prefix'] . static::$table_configuration;
    }

    public static function getTableAuthorizeUser(){
        return static::$app->config('db')['db_prefix'] . static::$table_authorize_user;
    }

    public static function getTableAuthorizeGroup(){
        return static::$app->config('db')['db_prefix'] . static::$table_authorize_group;
    }
}
