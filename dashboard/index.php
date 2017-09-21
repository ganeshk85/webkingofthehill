<?php
    require_once "../userfrosting/initialize.php";

    use UserFrosting as UF;
   
    // Front page
    $app->get('/', function () use ($app) {
        // Forward to installation if not complete
        if (!isset($app->site->install_status) || $app->site->install_status == "pending"){
            $app->redirect($app->urlFor('uri_install'));
        }        
        // Forward to the user's landing page (if logged in), otherwise take them to the home page
        if ($app->user->isGuest()){
            $controller = new UF\AccountController($app);
            $controller->pageHome();
        // If this is the first the root user is logging in, take them to site settings
        } else if ($app->user->id == $app->config('user_id_master') && $app->site->install_status == "new"){
            $app->site->install_status = "complete";
            $app->site->store();
            $app->alerts->addMessage("success", "Congratulations, you've successfully logged in for the first time.  Please take a moment to customize your site settings.");
            $app->redirect($app->urlFor('uri_settings'));  
        } else {
            $app->redirect($app->user->landing_page);        
        }
    })->name('uri_home');

    // Miscellaneous pages
    /*$app->get('/dashboard/?', function () use ($app) {
        // Access-controlled page
        if (!$app->user->checkAccess('uri_dashboard')){
            $app->notFound();
        }
        
        $app->render('dashboard.html', [
            'page' => [
                'author' =>         $app->site->author,
                'title' =>          "Dashboard",
                'description' =>    "Your user dashboard.",
                'alerts' =>         $app->alerts->getAndClearMessages()
            ]
        ]);          
    });*/
    
    /*$app->get('/zerg/?', function () use ($app) {    
        // Access-controlled page
        if (!$app->user->checkAccess('uri_zerg')){
            $app->notFound();
        }
        
        $app->render('zerg.html', [
            'page' => [
                'author' =>         $app->site->author,
                'title' =>          "Zerg",
                'description' =>    "Dedicated to the pursuit of genetic perfection, the zerg relentlessly hunt down and assimilate advanced species across the galaxy, incorporating useful genetic code into their own.",
                'alerts' =>         $app->alerts->getAndClearMessages()
            ]
        ]); 
    });    */
   	
	   
    // Account management pages
    $app->get('/account/:action/?', function ($action) use ($app) {    
        // Forward to installation if not complete
        if (!isset($app->site->install_status) || $app->site->install_status == "pending"){
            $app->redirect($app->urlFor('uri_install'));
        }
    
        $get = $app->request->get();
        
        $controller = new UF\AccountController($app);
    
        switch ($action) {
            case "login":               return $controller->pageLogin();
            case "logout":              return $controller->logout(); 
            case "register":            return $controller->pageRegister();
            case "activate":            return $controller->activate();            
            case "resend-activation":   return $controller->pageResendActivation();
            case "forgot-password":     return $controller->pageForgotPassword();
            case "reset-password":      if (isset($get['confirm']) && $get['confirm'] == "true")
                                            return $controller->pageResetPassword();
                                        else
                                            return $controller->denyResetPassword();
            case "captcha":             return $controller->captcha();
            case "settings":            return $controller->pageAccountSettings();
            default:                    return $controller->page404();   
        }
    });

    $app->post('/account/:action/?', function ($action) use ($app) {            
        $controller = new UF\AccountController($app);
    
        switch ($action) {
            case "login":               return $controller->login();     
            case "register":            return $controller->register();
            case "resend-activation":   return $controller->resendActivation();
            case "forgot-password":     return $controller->forgotPassword();
            case "reset-password":      return $controller->resetPassword();            
            case "settings":            return $controller->accountSettings();
            default:                    $app->notFound();
        }
    });    
	
    //KOTH custom pages
    //Player management pages
    $app->get('/players/?', function () use ($app) {    	
            $controller = new UF\PlayerController($app);
    return $controller->pagePlayers();	
    }); 
    
    // Player info form (update/view)
    $app->get('/forms/players/u/:player_id/?', function ($player_id) use ($app) {
        $controller = new UF\PlayerController($app);
        $get = $app->request->get();        
        if (isset($get['mode']) && $get['mode'] == "update")
            return $controller->formPlayerEdit($player_id);
        else
            return $controller->formPlayerView($player_id);
    });
    
    // Player creation form
    $app->get('/forms/players/?', function () use ($app) {
        $controller = new UF\PlayerController($app);
        return $controller->formPlayerCreate();
    });    
    
    // Create player
    $app->post('/players/?', function () use ($app) {
        $controller = new UF\PlayerController($app);
        return $controller->createPlayer();
    });
    
    // Update player info
    $app->post('/players/u/:player_id/?', function ($player_id) use ($app) {
        $controller = new UF\PlayerController($app);
        return $controller->updatePlayer($player_id);
    });       
    
    // Delete player
    $app->post('/players/u/:player_id/delete/?', function ($player_id) use ($app) {
        $controller = new UF\PlayerController($app);
        return $controller->deletePlayer($player_id);
    });
    
    // Game Management
    //Get Schedule
    $app->get('/game/schedule/?', function () use ($app) {
        $controller = new UF\GameController($app);
        return $controller->pageGameSchedule();
    });
    
    $app->get('/game/schedule/update/?', function () use ($app) {
        $controller = new UF\GameController($app);
        return $controller->getGameScheduleAPI();
    });
    
    $app->post('/game/schedule/:game_id/?', function ($game_id) use ($app) {
        $controller = new UF\GameController($app);
        return $controller->saveGameWinner($game_id);        
    });
    
    /*$app->post('/game/schedule/u/:game_id/?', function ($game_id) use ($app) {
        $controller = new UF\GameController($app);
        return $controller->saveGameWinner($game_id);
    });*/       
    
    // Make Pick info form (update/view)
    $app->get('/forms/game/u/:player_picked_id/?', function ($player_picked_id) use ($app) {
        $controller = new UF\WeekController($app);
        $get = $app->request->get();        
        if (isset($get['mode']) && $get['mode'] == "update")
            return $controller->formMakePickEdit($player_picked_id);
        else
            return $controller->formMakePickView($player_picked_id);
    });  
    
    // Make Pick creation form
    $app->get('/forms/game/?', function () use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->formMakePickCreate();
    });
    
    //Make Pick Page
    $app->get('/game/makeapick/?', function () use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->formMakePickCreate();
    });

    // Create Player Picked
    $app->post('/game/makeapick/?', function () use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->createPlayerPick();
    });
    
	//Ajax call only: display teams per player 
    $app->get('/forms/game/teamsperplayer/:player_id/?', function ($player_id) use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->formSelectTeamsPerPlayer($player_id);
    });
	
    // Update Player Picked
    $app->post('/game/u/:player_picked_id/?', function ($player_picked_id) use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->updatePlayerPick($player_picked_id);
    });  
	
    // Delete Player Picked
    $app->post('/game/u/:player_picked_id/delete/?', function ($player_picked_id) use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->deletePlayerPick($player_picked_id);
    });
    
    //Past Weeks
    $app->get('/game/pastweek/?', function () use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->pagePastWeek();
    });
    
    //Players Not Picked List
    $app->get('/game/playersnopick/:week_id/??', function ($week_id) use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->pagePlayersNoPick($week_id);
    });
    
    //Current Rankings
    $app->get('/game/rankings/?', function () use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->pageRankings();
    });
	
	//Ajax call only: display rankings per week id 
    $app->get('/forms/game/rankingsperweek/:week_id/?', function ($week_id) use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->RankingsPerWeek($week_id);
    });
    
    //Player Statistics
    $app->get('/game/stats/?', function () use ($app) {
        $controller = new UF\WeekController($app);
        return $controller->pageStats();
    });
    
    //Payment managment pages
    //// User info form (update/view)
    $app->get('/forms/payments/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\PaymentController($app);        
        return $controller->formPaymentEdit($user_id);        
    });
    
    // Update user payment info
    $app->post('/payments/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\PaymentController($app);
        return $controller->updatePayment($user_id);
    });
    
    // User management pages
    $app->get('/users/?', function () use ($app) {
        $controller = new UF\UserController($app);
        return $controller->pageUsers();
    });    

    $app->get('/users/:primary_group/?', function ($primary_group) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->pageUsers($primary_group);
    });
    
    // User info form (update/view)
    $app->get('/forms/users/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        $get = $app->request->get();        
        if (isset($get['mode']) && $get['mode'] == "update")
            return $controller->formUserEdit($user_id);
        else
            return $controller->formUserView($user_id);
    });  
    
    // User creation form
    $app->get('/forms/users/?', function () use ($app) {
        $controller = new UF\UserController($app);
        return $controller->formUserCreate();
    });
    
    // User info page
    $app->get('/users/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->pageUser($user_id);
    });       
    
    // User Reset Password form
    $app->get('/forms/users/reset-password/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->formAdminResetPassword($user_id);
    });

    // Create user
    $app->post('/users/?', function () use ($app) {
        $controller = new UF\UserController($app);
        return $controller->createUser();
    });
    
    // Update user info
    $app->post('/users/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->updateUser($user_id);
    });       
    
    // User Reset Password by Admin
    $app->post('/users/reset-password/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->adminResetPassword($user_id);
    });
    
    // Delete user
    $app->post('/users/u/:user_id/delete/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->deleteUser($user_id);
    });
    
    //get user disable form
    $app->get('/forms/usersdisabled/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);        
        return $controller->formUserEnabledStatus($user_id, 0);        
    });
    
    //disable user along with user's players
    $app->post('/users/u/:user_id/userdisable/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->disableUser($user_id);
    });
    
    //get user enable form
    $app->get('/forms/usersenabled/u/:user_id/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);        
        return $controller->formUserEnabledStatus($user_id, 1);        
    });
    
    //enable user along with user's players
    $app->post('/users/u/:user_id/userenable/?', function ($user_id) use ($app) {
        $controller = new UF\UserController($app);
        return $controller->enableUser($user_id);
    });
    
    // Group management pages
    $app->get('/groups/?', function () use ($app) {
        $controller = new UF\GroupController($app);
        return $controller->pageGroups();
    }); 
    
    // Group info form (update/view)
    $app->get('/forms/groups/g/:group_id/?', function ($group_id) use ($app) {
        $controller = new UF\GroupController($app);
        $get = $app->request->get();        
        if (isset($get['mode']) && $get['mode'] == "update")
            return $controller->formGroupEdit($group_id);
        else
            return $controller->formGroupView($group_id);
    });

    // Group creation form
    $app->get('/forms/groups/?', function () use ($app) {
        $controller = new UF\GroupController($app);
        return $controller->formGroupCreate();
    });    
    
    // Create group
    $app->post('/groups/?', function () use ($app) {
        $controller = new UF\GroupController($app);
        return $controller->createGroup();
    });
    
    // Update group info
    $app->post('/groups/g/:group_id/?', function ($group_id) use ($app) {
        $controller = new UF\GroupController($app);
        return $controller->updateGroup($group_id);
    });       
    
    // Delete group
    $app->post('/groups/g/:group_id/delete/?', function ($group_id) use ($app) {
        $controller = new UF\GroupController($app);
        return $controller->deleteGroup($group_id);
    });
    
    // Admin tools
    $app->get('/config/settings/?', function () use ($app) {
        $controller = new UF\AdminController($app);
        return $controller->pageSiteSettings();
    })->name('uri_settings');   
    
    $app->post('/config/settings/?', function () use ($app) {
        $controller = new UF\AdminController($app);
        return $controller->siteSettings();        
    });
    
    // Build the minified, concatenated CSS and JS
    $app->get('/config/build', function() use ($app){
        // Access-controlled page
        if (!$app->user->checkAccess('uri_minify')){
            $app->notFound();
        }
        
        $app->schema->build();
        $app->alerts->addMessageTranslated("success", "MINIFICATION_SUCCESS");
        $app->redirect($app->urlFor('uri_settings'));
    });    
    
    // Installation pages
    $app->get('/install/?', function () use ($app) {
        $controller = new UF\InstallController($app);
        if (isset($app->site->install_status)){
            // If tables have been created, move on to master account setup
            if ($app->site->install_status == "pending"){
                $app->redirect($app->site->uri['public'] . "/install/master");
            } else {
                // Everything is set up, so go to the home page!
                $app->redirect($app->urlFor('uri_home'));
            }
        } else {
            return $controller->pageSetupDB();
        }
    })->name('uri_install');
    
    $app->get('/install/master/?', function () use ($app) {
        $controller = new UF\InstallController($app);
        if (isset($app->site->install_status) && ($app->site->install_status == "pending")){
            return $controller->pageSetupMasterAccount();
        } else {
            $app->redirect($app->urlFor('uri_install'));
        }
    });

    $app->post('/install/:action/?', function ($action) use ($app) {
        $controller = new UF\InstallController($app);
        switch ($action) {
            case "master":            return $controller->setupMasterAccount();      
            default:                  $app->notFound();
        }   
    });
    
    // Slim info page
    $app->get('/sliminfo/?', function () use ($app) {
        // Access-controlled page
        if (!$app->user->checkAccess('uri_slim_info')){
            $app->notFound();
        }
        echo "<pre>";
        print_r($app->environment());
        echo "</pre>";
    });

    // PHP info page
    $app->get('/phpinfo/?', function () use ($app) {
        // Access-controlled page
        if (!$app->user->checkAccess('uri_php_info')){
            $app->notFound();
        }    
        echo "<pre>";
        print_r(phpinfo());
        echo "</pre>";
    });

    // PHP info page
    $app->get('/errorlog/?', function () use ($app) {
        // Access-controlled page
        if (!$app->user->checkAccess('uri_error_log')){
            $app->notFound();
        }
        $log = $app->site->getLog();
        echo "<pre>";
        echo implode("<br>",$log['messages']);
        echo "</pre>";
    });      
    
    // Generic confirmation dialog
    $app->get('/forms/confirm/?', function () use ($app) {
        $get = $app->request->get();
        
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($app->config('schema.path') . "/forms/confirm-modal.json");
        
        // Get the alert message stream
        $ms = $app->alerts;         
        
        // Remove csrf_token
        unset($get['csrf_token']);
        
        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $get);                    
    
        // Sanitize
        $rf->sanitize();
    
        // Validate, and halt on validation errors.
        if (!$rf->validate()) {
            $app->halt(400);
        }           
        
        $data = $rf->data();
        
        $app->render('components/confirm-modal.html', $data);   
    }); 
    
    // Alert stream
    $app->get('/alerts/?', function () use ($app) {
        $controller = new UF\BaseController($app);
        $controller->alerts();
    });
    
    // JS Config
    $app->get('/js/config.js', function () use ($app) {
        $controller = new UF\BaseController($app);
        $controller->configJS();
    });
    
    // Theme CSS
    $app->get('/css/theme.css', function () use ($app) {
        $controller = new UF\BaseController($app);
        $controller->themeCSS();
    });
    
    // Not found page (404)
    $app->notFound(function () use ($app) {
        if ($app->request->isGet()) {
            $controller = new UF\BaseController($app);
            $controller->page404();
        } else {
            $app->alerts->addMessageTranslated("danger", "SERVER_ERROR");
        }
    });     
    
    $app->get('/test/auth', function() use ($app){
        if (0 == "php")
            echo "0 = php";
        
        $params = [
            "user" => [
                "id" => 1
            ],
            "post" => [
                "id" => 7
            ]
        ];
        
        $conditions = "(equals(self.id,user.id)||hasPost(self.id,post.id))&&subset(post, [\"id\", \"title\", \"content\", \"subject\", 3])";
        
        $ace = new UF\AccessConditionExpression($app);
        $result = $ace->evaluateCondition($conditions, $params);
        
        if ($result){
            echo "Passed $conditions";
        } else {
            echo "Failed $conditions";
        }
    });
    
    $app->run();
?>
