<?php

/**
 * Title: WordPress pay MemberPress iDEAL gateway
 * Description:
 * Copyright: Copyright (c) 2005 - 2015
 * Company: Pronamic
 * @author Remco Tolsma
 * @version 1.0.0
 * @since 1.0.0
 */
class Pronamic_WP_Pay_Extensions_MemberPress_IDealGateway extends MeprBaseRealGateway {
	/**
	 * Constructs and initialize iDEAL gateway.
	 */
	public function __construct() {
		// Set the name of this gateway.
		// @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L12-13
		$this->name = __( 'iDEAL', 'pronamic_ideal' );

		// Set the default settings.
		// @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L72-73
		$this->set_defaults();

		// Set the capabilities of this gateway.
		// @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L36-37
		$this->capabilities = array(
			//'process-payments',
			//'create-subscriptions',
			//'process-refunds',
			'cancel-subscriptions', //Yup we can cancel them here - needed for upgrade/downgrades
			//'update-subscriptions',
			//'suspend-subscriptions',
			//'send-cc-expirations'
		);

		// Setup the notification actions for this gateway
		$this->notifiers = array();
	}

	/**
	 * Load the specified settings.
	 *
	 * @param array $settings
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L69-70
	 */
	public function load( $settings ) {
		$this->settings = (object) $settings;

		$this->set_defaults();
	}

	/** 
	 * Set the default settings.
	 *
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L72-73
	 */
	protected function set_defaults() {
		if ( ! isset( $this->settings ) ) {
			$this->settings = array();
		}

		$this->settings = (object) array_merge(
			array(
				'gateway'           => 'MeprIDealGateway',
				'id'                => $this->generate_id(),
				'label'             => '',
				'use_label'         => true,
				'icon'              => '',
				'use_icon'          => true,
				'desc'              => '',
				'use_desc'          => true,
				'manually_complete' => false,
				'email'             => '',
				'sandbox'           => false,
				'debug'             => false
			),
			(array) $this->settings
		);

		$this->id        = $this->settings->id;
		$this->label     = $this->settings->label;
		$this->use_label = $this->settings->use_label;
		$this->icon      = $this->settings->icon;
		$this->use_icon  = $this->settings->use_icon;
		$this->desc      = $this->settings->desc;
		$this->use_desc  = $this->settings->use_desc;
		//$this->recurrence_type = $this->settings->recurrence_type;
	}

	/**
	 * Process payment.
	 *
	 * @param MeprTransaction $txn
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L119-122
	 */
	public function process_payment( $txn ) {
		if ( isset( $txn ) && $txn instanceof MeprTransaction ) {
			$usr = new MeprUser( $txn->user_id );
			$prd = new MeprProduct( $txn->product_id );
		} else {
			return;
		}

		$upgrade   = $txn->is_upgrade();
		$downgrade = $txn->is_downgrade();

		$txn->maybe_cancel_old_sub();

		if ( $upgrade ) {
			$this->upgraded_sub( $txn );
			$this->send_upgraded_txn_notices( $txn );
		} elseif( $downgrade ) {
			$this->downgraded_sub( $txn );
			$this->send_downgraded_txn_notices( $txn );
		} else {
			$this->new_sub( $txn );
		}

		$txn->gateway   = $this->id;
		$txn->trans_num = 't_' . uniqid();

		if ( ! $this->settings->manually_complete == 'on' and ! $this->settings->manually_complete == true ) {
			$txn->status = MeprTransaction::$complete_str;
			$this->send_transaction_receipt_notices($txn);
		}

		$txn->store();

		$this->send_product_welcome_notices($txn);
		$this->send_signup_notices($txn);

		return $txn;
	}

	/**
	 * Record subscription payment.
	 *
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L140-145
	 */
	public function record_subscription_payment() {

	}

	/**
	 * Record payment failure.
	 *
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L147-148
	 */
	public function record_payment_failure() {

	}

	/**
	 * Record payment.
	 *
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L124-129
	 */
	public function record_payment() {

	}

	/**
	 * Process refund.
	 *
	 * @param MeprTransaction $txn
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L131-133
	 */
	public function process_refund( MeprTransaction $txn ) {

	}

	/**
	 * Record refund.
	 *
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L135-138
	 */
	public function record_refund() {

	}

	/**
	 * Process trial payment.
	 *
	 * @param $transaction
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L150-157
	 */
	public function process_trial_payment( $transaction ) {

	}

	/**
	 * Reord trial payment.
	 *
	 * @param $transaction
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L159-161
	 */
	public function record_trial_payment( $transaction ) {

	}

	/**
	 * Process create subscription.
	 *
	 * @param $txn
	 * @see https://gitlab.com/pronamic/memberpress/blob/1.2.4/app/lib/MeprBaseGateway.php#L163-167
	 */
	public function process_create_subscription($txn) {
		if(isset($txn) && $txn instanceof MeprTransaction) {
      $usr = new MeprUser($txn->user_id);
      $prd = new MeprProduct($txn->product_id);
    }
    else {
      return;
    }

    $sub = $txn->subscription();

    // Not super thrilled about this but there are literally
    // no automated recurring profiles when paying offline
    $sub->subscr_id = 'ts_' . uniqid();
    $sub->status = MeprSubscription::$active_str;
    $sub->created_at = date('c');
    $sub->gateway = $this->id;

    //If this subscription has a paid trail, we need to change the price of this transaction to the trial price duh
    if($sub->trial) {
      $txn->set_subtotal(MeprUtils::format_float($sub->trial_amount));
      $expires_ts = time() + MeprUtils::days($sub->trial_days);
      $txn->expires_at = date('c', $expires_ts);
    }

    // This will only work before maybe_cancel_old_sub is run
    $upgrade = $sub->is_upgrade();
    $downgrade = $sub->is_downgrade();

    $sub->maybe_cancel_old_sub();

    if($upgrade) {
      $this->upgraded_sub($sub);
      $this->send_upgraded_sub_notices($sub);
    }
    else if($downgrade) {
      $this->downgraded_sub($sub);
      $this->send_downgraded_sub_notices($sub);
    }
    else {
      $this->new_sub($sub);
      $this->send_new_sub_notices($sub);
    }

    $sub->store();

    $txn->gateway = $this->id;
    $txn->trans_num = 't_' . uniqid();

    if(!$this->settings->manually_complete == 'on' and !$this->settings->manually_complete == true) {
      $txn->status = MeprTransaction::$complete_str;
      $this->send_transaction_receipt_notices($txn);
    }

    $txn->store();

    $this->send_product_welcome_notices($txn);
    $this->send_signup_notices($txn);

    return array('subscription' => $sub, 'transaction' => $txn);
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    // No reason to separate this out without webhooks/postbacks/ipns
  }

  public function process_update_subscription($sub_id) {
    // This happens manually in test mode
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // No need for this one with the artificial gateway
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {}

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {}

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {}

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {}

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);
    $_REQUEST['sub_id'] = $sub_id;
    $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    $sub = new MeprSubscription($_REQUEST['sub_id']);

    if(!$sub) { return false; }

    // Seriously ... if sub was already cancelled what are we doing here?
    if($sub->status == MeprSubscription::$cancelled_str) { return $sub; }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if(isset($_REQUEST['expire']))
      $sub->limit_reached_actions();

    if(!isset($_REQUEST['silent']) || ($_REQUEST['silent'] == false))
      $this->send_cancelled_sub_notices($sub);

    return $sub;
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
    * this is in place so that payment solutions like paypal can redirect
    * before any content is rendered.
  */
  public function process_signup_form($txn) {
    //if($txn->amount <= 0.00) {
    //  MeprTransaction::create_free_transaction($txn);
    //  return;
    //}

    // Redirect to thank you page
    //$mepr_options = MeprOptions::fetch();
    //MeprUtils::wp_redirect($mepr_options->thankyou_page_url("trans_num={$txn->trans_num}"));
  }

  public function display_payment_page($txn) {
    // Nothing here yet
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    // This happens manually in test mode
  }

  /** This gets called on the_content and just renders the payment form
    */
  public function display_payment_form($amount, $user, $product_id, $txn_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = new MeprProduct($product_id);
    $coupon = false;

    $txn = new MeprTransaction($txn_id);

    //Artifically set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;

    ?>
      <div class="mp_wrapper">
        <form action="" method="post" id="payment-form" class="mepr-form" novalidate>
          <input type="hidden" name="mepr_process_payment_form" value="Y" />
          <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn_id; ?>" />

          <div class="mepr_spacer">&nbsp;</div>

          <input type="submit" class="mepr-submit" value="<?php _e('Submit', 'memberpress'); ?>" />
          <img src="<?php echo admin_url('images/loading.gif'); ?>" style="display: none;" class="mepr-loading-gif" />
          <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>

          <noscript><p class="mepr_nojs"><?php _e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
        </form>
      </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    // This is done in the javascript with Stripe
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();
    $manually_complete = ($this->settings->manually_complete == 'on' or $this->settings->manually_complete == true);
    ?>
    <table>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][manually_complete]"<?php echo checked($manually_complete); ?> />&nbsp;<?php _e('Admin Must Manually Complete Transactions', 'memberpress'); ?></td>
      </tr>
    </table>
    <?php
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    return $errors;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    */
  public function enqueue_user_account_scripts() {
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    // Handled Manually in test gateway
    ?>
    <p><b><?php _e('This action is not possible with the payment method used with this Subscription','memberpress'); ?></b></p>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    return $errors;
  }

  /** Used to update the credit card information on a subscription by the given gateway.
    * This method should be used by the class to record a successful cancellation from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_update_account_form($sub_id) {
    // Handled Manually in test gateway
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    return false; // Why bother
  }

  public function force_ssl() {
    return false; // Why bother
  }
} //End class
