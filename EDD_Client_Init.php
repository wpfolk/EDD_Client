<?php

require_once 'EDD_Client_Updater.php';

if ( ! class_exists( 'EDD_Client_Init' ) ):
    class EDD_Client_Init {

	    protected array $plugin = [];

        /*
         * Instantiate the EDD_Client_Init class
         */
        public function __construct($plugin_path, $api_url)
        {
	        $plugin_data = get_file_data($plugin_path, [
		        'name'          => 'Plugin Name',
		        'version'       => 'Version',
                'author'        => 'Author',
	        ], 'plugin');

	        $this->plugin['name']           = $plugin_data['name'];
	        $this->plugin['version']        = $plugin_data['version'];
	        $this->plugin['author']         = $plugin_data['author'];
	        $this->plugin['machine_name']   = str_replace(' ', '-', strtolower($this->plugin['name']));
	        $this->plugin['path']           = $plugin_path;
	        $this->plugin['slug']           = plugin_basename($this->plugin['path']);
	        $this->plugin['api_url']        = $api_url;
	        $this->plugin['license']        = trim(get_option($this->plugin['machine_name'].'_license_key'));
	        $this->plugin['license_status'] = get_option($this->plugin['machine_name'].'_license_status');

	        add_action( 'admin_init', function (){
	            // Enqueue Scripts
                add_action('admin_print_scripts-plugins.php', [ $this, 'enqueue_scripts' ]);
                add_action('admin_print_styles-plugins.php', [ $this, 'enqueue_styles' ]);

                // License GUI
		        if ($this->plugin['license_status'] !== 'valid') {
			        global $pagenow;
			        if ( $pagenow == 'plugins.php' ) {
				        add_action( 'admin_notices', [ $this, 'display_admin_notice' ] );
			        }
			        add_action('after_plugin_row_' . $this->plugin['slug'], [ $this, 'insert_license_row' ], 10, 3);
		        }
		        else{
			        add_filter('plugin_action_links_' . $this->plugin['slug'], [$this, 'insert_license_link'], 9, 2);
			        add_action('after_plugin_row_' . $this->plugin['slug'], [ $this, 'insert_license_operation_row' ], 10, 3);
		        }

                // Add Ajax Actions
                add_action( 'wp_ajax_'.$this->plugin['machine_name'].'-edd-client-operations', [ $this, 'edd_operations' ] );

                // Trigger Plugin Update
                $this->plugin_updater();
            });
        }

        /*
         * A helper function to return if plugin is premium
         */
        public function is_premium(){
            if ($this->plugin['license_status'] === 'valid'){
	            return true;
            }
            else{
                return false;
            }
        }

        /*
         * Enqueue Scripts
         */
        public function enqueue_scripts()
        {
            wp_register_script('edd-client', plugins_url('script.js', __FILE__), array('jquery'));
            wp_enqueue_script('edd-client');
        }

        /*
         * Enqueue Styles
         */
        public function enqueue_styles()
        {
            wp_register_style('edd-client', plugins_url('style.css', __FILE__));
            wp_enqueue_style('edd-client');
        }

        /*
         * Add a License Link to Plugin
         */
        public function insert_license_link($links)
        {
	        $settings_link = '<a href="javascript:void(0);" class="edd-client-cred-link">License</a>';
	        array_push($links, $settings_link);
            return $links;
        }

        /*
         * Adds row on the plugin table. Provides GUI to enter License
         */
        public function insert_license_row()
        {
	        ?>
            <tr class="plugin-update-tr active">
                <td colspan="3">
                    <div class="update-message notice inline notice-error notice-alt">
	                    <p>Enter <a href="javascript:void(0);" class="edd-client-cred-link">License</a> key for <?php echo $this->plugin['name']?> to get security and feature updates to properly work on your site.</p>
                        <div id="<?php echo 'ec-'.$this->plugin['machine_name']?>" class="edd-client-row" style="display:none">
                            <input class="edd-client-license-key" type="text" placeholder="Enter Your License"/>
                            <button class="button edd-client-button" data-action=<?php echo $this->plugin['machine_name'].'-edd-client-operations'?> data-operation="activate_license" data-nonce="<?php echo wp_create_nonce( $this->plugin['machine_name'].'-edd-client-operations' ) ?>"> <span class="dashicons dashicons-update"></span> Activate License</button>
                        </div>
                    </div>
                </td>
            </tr>
	        <?php
        }

        /*
         * Adds row on the plugin table. Provides GUI to deactivate, check Expiry of license etc.
         */
	    public function insert_license_operation_row()
	    {
		    ?>
            <tr class="edd-client-row notice-warning notice-alt" style="display: none">
                <td colspan="3">
                    <div class="edd-client-row">
                        <input class="edd-client-license-key" type="text" style="margin-right:-14px; border-top-right-radius:0px; border-bottom-right-radius:0px; border-right:0px;" value="<?php echo $this->plugin['license'] ?>"/>
                        <button class="button edd-client-button" data-action=<?php echo $this->plugin['machine_name'].'-edd-client-operations'?> data-operation="change_license" data-nonce="<?php echo wp_create_nonce( $this->plugin['machine_name'].'-edd-client-operations' ) ?>" style="margin-left:-4px; border-top-left-radius:0px; border-bottom-left-radius:0px;"> <span class="dashicons dashicons-update"></span> Change License</button>

                        <button class="button edd-client-button" data-action=<?php echo $this->plugin['machine_name'].'-edd-client-operations'?> data-operation="check_expiry" data-nonce="<?php echo wp_create_nonce( $this->plugin['machine_name'].'-edd-client-operations' ) ?>"> <span class="dashicons dashicons-update"></span> Check Expiry Date</button>
                        <button class="button edd-client-button" data-action=<?php echo $this->plugin['machine_name'].'-edd-client-operations'?> data-operation="deactivate_license" data-nonce="<?php echo wp_create_nonce( $this->plugin['machine_name'].'-edd-client-operations' ) ?>"> <span class="dashicons dashicons-update"></span> Deactivate License</button>
                    </div>
                </td>
            </tr>
		    <?php
        }

	    /*
		 *  Display admin notice if plugin license key is not yet entered
		 */
	    public function display_admin_notice(){
		    ?>
		    <div class="notice notice-warning is-dismissible">
			    <p>Almost done - Activate license to make <strong><?php echo $this->plugin['name']?></strong> properly work on your site
				    <input class="edd-client-license-key" type="text" placeholder="Enter Your License"/>
				    <button class="button edd-client-button" data-action=<?php echo $this->plugin['machine_name'].'-edd-client-operations'?> data-operation="activate_license" data-nonce="<?php echo wp_create_nonce( $this->plugin['machine_name'].'-edd-client-operations' ) ?>"> <span class="dashicons dashicons-update"></span> Activate License</button>
			    </p>
		    </div>
		    <?php
	    }

        /**
         * Trigger Plugin Update
         */
        public function plugin_updater()
        {
            new EDD_Client_Updater(
                $this->plugin['api_url'],
                $this->plugin['path'],
                array(
                    'version'   => $this->plugin['version'],
                    'license'   => $this->plugin['license'],
                    'item_name' => $this->plugin['name'],
                    'author'    => $this->plugin['author']
                )
            );

        }

        /*
         * Different EDD Operations executed on Ajax call
         */
        public function edd_operations()
        {
            if (empty( $operation = $_POST['operation'] ) || wp_verify_nonce($_POST['nonce'], $this->plugin['machine_name'].'-edd-client-operations') === false) {
                wp_send_json_error('Something went wrong');
            }

            switch($operation){
                case 'activate_license':
                    $license = !empty($_POST['license']) ? $_POST['license'] : wp_send_json_error('License field can not be empty');
                    $license = sanitize_text_field($license);

                    $license_data = $this->validate_license($license, $this->plugin['name'], $this->plugin['api_url']);
                    if( $license_data->license === 'valid' ) {
                        update_option($this->plugin['machine_name'].'_license_status', $license_data->license);
                        update_option($this->plugin['machine_name'].'_license_key', $license);
                        wp_send_json_success('License successfully activated');
                    }
                    break;
                case 'deactivate_license':
                    $license_data = $this->invalidate_license($this->plugin['license'], $this->plugin['name'], $this->plugin['api_url']);
                    if( $license_data->license === 'deactivated' || $license_data->license === 'failed' ) {
                        delete_option($this->plugin['machine_name'].'_license_status');
                        delete_option($this->plugin['machine_name'].'_license_key');
                        wp_send_json_success('License deactivated for this site');
                    }
                    break;
                case 'change_license':
                    $new_license = !empty($_POST['license']) ? $_POST['license'] : wp_send_json_error('License field can not be empty');
                    $new_license = sanitize_text_field($new_license);
                    $old_license = $this->plugin['license'];
                    if($new_license !== $old_license){
                        $license_data = $this->validate_license($new_license, $this->plugin['name'], $this->plugin['api_url']);
                        if ($license_data->license === 'valid'){
                            $license_data = $this->invalidate_license($old_license, $this->plugin['name'], $this->plugin['api_url']);
                            if($license_data->license === 'deactivated' || $license_data->license === 'failed'){
                                update_option($this->plugin['machine_name'].'_license_key', $new_license);
                                wp_send_json_success('License Successfully Changed.');
                            }
                        }
                    }
                    else{
                        wp_send_json_error('Enter a new license.');
                    }
                    break;
                case 'check_expiry':
                    $license = $this->plugin['license'];
                    $this->check_expiry($license, $this->plugin['name'], $this->plugin['api_url']);
                    break;
                default:
                    wp_send_json_error('Something went wrong');
            }

        }

        /*
         *  Validate License
         */
        public function validate_license($license, $plugin_name, $api_url){
            // data to send in our API request
            $api_params = array(
                'edd_action' => 'activate_license',
                'license' => $license,
                'item_name' => urlencode($plugin_name),
                'url' => home_url()
            );

            // Call the custom API.
            $response = wp_remote_post($api_url, array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));

            // make sure the response came back okay
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {

                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                } else {
                    $message = __('An error occurred, please try again.');
                }
                wp_send_json_error($message);

            }
            else {

                $license_data = json_decode(wp_remote_retrieve_body($response));

                if (false === $license_data->success) {

                    switch ($license_data->error) {

                        case 'expired' :

                            $message = sprintf(
                                __('Your license key expired on %s.'),
                                date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
                            );
                            break;

                        case 'disabled' :
                        case 'revoked' :

                            $message = __('Your license key has been disabled.');
                            break;

                        case 'missing' :

                            $message = __('Invalid license.');
                            break;

                        case 'invalid' :
                        case 'site_inactive' :

                            $message = __('Your license is not active for this URL.');
                            break;

                        case 'item_name_mismatch' :

                            $message = sprintf(__('This appears to be an invalid license key for %s.'), $plugin_name);
                            break;

                        case 'no_activations_left':

                            $message = __('Your license key has reached its activation limit.');
                            break;

                        default :

                            $message = __('An error occurred, please try again.');
                            break;
                    }
                    wp_send_json_error($message);
                }
                else{
                    return $license_data;
                }
            }
        }

        /*
         * Invalidate License for current website. This will decrease the site count
         */
        public function invalidate_license($license, $plugin_name, $api_url) {
            // data to send in our API request
            $api_params = array(
                'edd_action' => 'deactivate_license',
                'license'    => $license,
                'item_name'  => urlencode($plugin_name), // the name of our product in EDD
                'url'        => home_url()
            );

            // Call the custom API.
            $response = wp_remote_post( $api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

            // make sure the response came back okay
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

                if ( is_wp_error( $response ) ) {
                    $message = $response->get_error_message();
                } else {
                    $message = __( 'An error occurred, please try again.' );
                }
                wp_send_json_error($message);
            }

            // decode the license data
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            return $license_data;
        }

        /*
         * Check License Expiry
         */
        public function check_expiry($license, $plugin_name, $api_url) {

            $api_params = array(
                'edd_action' => 'check_license',
                'license' => $license,
                'item_name' => urlencode( $plugin_name ),
                'url'       => home_url()
            );

            // Call the custom API.
            $response = wp_remote_post( $api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

	        // make sure the response came back okay
	        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

		        if ( is_wp_error( $response ) ) {
			        $message = $response->get_error_message();
		        } else {
			        $message = __( 'An error occurred, please try again.' );
		        }
		        wp_send_json_error($message);
	        }

            $license_data = json_decode( wp_remote_retrieve_body( $response ) );

            if( $license_data->license == 'valid' ) {
		        wp_send_json_success('License will expire on: '.$license_data->expires);
	        }
            elseif ($license_data->license == 'expired'){
		        wp_send_json_success('License expired on: '.$license_data->expires);
	        }
            elseif ($license_data->license == 'disabled'){
		        wp_send_json_success('Your license has been disabled by the seller');
	        }
            elseif ($license_data->license == 'invalid'){
		        wp_send_json_success('Invalid license key');
	        }
	        else{
		        wp_send_json_error('Something went wrong');
	        }
        }

    }
endif;
