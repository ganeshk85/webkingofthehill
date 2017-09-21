<?php

namespace UserFrosting;

/*******

/players/*

*******/

// Handles player-related activities
class PaymentController extends \UserFrosting\BaseController {

    public function __construct($app){
        $this->_app = $app;
    }

    public function pagePayment(){        
        $target_user = UserLoader::fetch($this->_app->user->id);
        
        $hidden_buttons = [];
                
        $name = "Players";
        $icon = "fa fa-users";        
        
        $this->_app->render('players.html', [
            'page' => [
                'author' =>         $this->_app->site->author,
                'title' =>          $name,
                'description' =>    "",
                'alerts' =>         $this->_app->alerts->getAndClearMessages()
            ],
            "box_title" => $name,
            "icon" => $icon,
            "players" => $players,
            "currentweek" => $currentweek,
            "first_game_date" => $first_game_date,
            "isadmin" => $isadmin,
            "buttons" => [
                "hidden" => $hidden_buttons
            ]
        ]);          
    }
      
    // Display the form for updating the user payments
    public function formPaymentEdit($user_id){
        // Get the payment record by user_id
        $target_payment = PaymentLoader::fetch($user_id,"user_id");
        
        $payment_methods = PaymentHistoryLoader::getPaymentMethods();
        
        $get = $this->_app->request->get();
        
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        if (isset($get['render']))
            $render = $get['render'];
        else
            $render = "modal";
        
        // Get a list of all users
        /*$users = UserLoader::fetchAll();
        
        foreach ($users as $user_id => $user){
            $user_list[$user_id] = $user->export();            
        }*/
        
        if ($render == "modal")
            $template = "components/payment-info-modal.html";
        else
            $template = "components/payment-info-panel.html";
        
        // Determine authorized fields
        $fields = ['pay_amount', 'payment_method'];
        $show_fields = [];        
        $hidden_fields = [];
        $disabled_buttons = [];
        foreach ($fields as $field){            
                $show_fields[] = $field;            
        }
        
        // Always disallow editing
        $disabled_fields = ["total_amount","amount_due","amount_paid"];
        
        //if amount due is 0 then disable payment
        if($target_payment->amount_due <=0)
        {
            array_push($disabled_fields, "pay_amount","payment_method","check_number");
            $disabled_buttons = ["submit"];            
        }
        
        // Load validator rules
        $validators = new \Fortress\ClientSideValidator($this->_app->config('schema.path') . "/forms/payment-update.json");
        
        $this->_app->render($template, [
            "box_id" => $get['box_id'],
            "box_title" => "Add Payment",
            "submit_button" => "Update payment",
            "form_action" => $this->_app->site->uri['public'] . "/payments/u/$user_id",
            "target_payment" => $target_payment,
            "payment_methods" => $payment_methods,
            "fields" => [
                "disabled" => $disabled_fields,
                "hidden" => $hidden_fields
            ],
            "buttons" => [
                "hidden" => [
                    "edit", "enable", "delete", "activate"
                ],
                "disabled" => $disabled_buttons
            ],
            "validators" => $validators->formValidationRulesJson()
        ]);   
    }    
    
    // Update payment details of the user
    public function updatePayment($user_id){
        $post = $this->_app->request->post();
        
        // DEBUG: view posted data
        //error_log(print_r($post, true));
        
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/payment-update.json");
        
        // Get the alert message stream
        $ms = $this->_app->alerts; 
        
        //get the user information
        $target_user = UserLoader::fetch($user_id);
        
        //Get target users payment record
        $target_payment = PaymentLoader::fetch($user_id,"user_id");
        
        //get amount paid
        $amount_paid = $target_payment->amount_paid;
        //get amount due
        $amount_due = $target_payment->amount_due;        
        
        // Remove csrf_token
        unset($post['csrf_token']);
        
        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);                    
    
        // Sanitize
        $rf->sanitize();
    
        // Validate, and halt on validation errors.
        if (!$rf->validate()) {
            $this->_app->halt(400);
        }   
               
        // Get the filtered data
        $data = $rf->data();
        
        
        //if payment amount is less than 0
        if(($post['pay_amount'] <= 0) || empty($post['pay_amount']))
        {
            $ms->addMessageTranslated("danger", "PAYMENT_AMOUNT_ZERO", $post);
            $this->_app->halt(400);
        }
        
        //if payment amount is more than total amount
        if($post['pay_amount'] > $amount_due)
        {
            $ms->addMessageTranslated("danger", "PAYMENT_AMOUNT_MORE_AMOUNT_DUE", $post);
            $this->_app->halt(400);
        }
        
        // Perform desired data transformations on required fields.
        $data['amount_paid'] = $amount_paid + trim($post['pay_amount']);
        $data['amount_due'] = $amount_due - trim($post['pay_amount']);
        $data['last_payment_date'] = date("Y-m-d H:i:s");
        
        // Update    
        
        $target_payment->amount_paid = $data['amount_paid'];
        $target_payment->amount_due = $data['amount_due'];
        $target_payment->last_payment_date = $data['last_payment_date'];
        /*foreach ($data as $name => $value){
            if ($value != $target_payment->$name){
                $target_payment->$name = $value;
                // Custom success messages (optional)
                
            }
        }*/
                
        // Create the payment history
        $pay_data['user_id'] = $user_id;
        $pay_data['payment_id'] = $target_payment->id;
        $pay_data['amount_paid'] = trim($post['pay_amount']);
        $pay_data['payment_date'] = date("Y-m-d H:i:s");
        if(isset($post['payment_method']))
        {
            $pay_data['payment_method'] = $post['payment_method'];
        }
        if(isset($post['check_number']))
        {
            $pay_data['check_number'] = $post['check_number'];
        }
        
        $payment_history = new PaymentHistory($pay_data);
        
        //add payment history
        $payment_history->store();
        
        //update payment record
        $target_payment->store();        
        
        //update payment status and player status if amount due is 0
        if($target_payment->amount_due <=0)
        {
            $target_user->payment_status = 1;
            
            $target_user->store();
            
            //update player status
            $players = $target_user->getPlayers();    
            foreach ($players as $player) {                
                $player->active = 1;
                
                $player->store();
            }
                        
            $ms->addMessageTranslated("success", "PAYMENT_PLAYERS_ACTIVE");            
        }
        
        $ms->addMessageTranslated("success", "PAYMENT_UPDATED_SUCCESS");
        
    }
    
    // Delete a player, cleaning up their player picks if any
    public function deletePayment($payment_id){
        $post = $this->_app->request->post();
    
        // Get the target player
        /*$target_player = PlayerLoader::fetch($player_id);
    
        // Get the alert message stream
        $ms = $this->_app->alerts;        
        
        $ms->addMessageTranslated("success", "PLAYER_DELETION_SUCCESSFUL", ["player_name" => $target_player->player_name]);
        $target_player->delete();
        unset($target_player);*/
    }
    
}
?>