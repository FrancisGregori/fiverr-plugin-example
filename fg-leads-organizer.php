<?php
/*
 * Plugin Name: FG Leads Organizer
 * Description: This plugin will capture and organize the leads that will come of the site forms
 * Version: 1.0.0
 * Author: Francis Gregori
 * Author URI: https://www.francisgregori.com.br/
 * License: GPL-2.0+
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'add_action' ) ) {
	die( 'Nothing to do...' );
}

require 'fg-leads-list.php';

/**
 * PLUGIN CONSTANTS
 */
define( 'ENVIRONMENT', 'PRODUCTION' );
define( 'FG_LEADS_ORGANIZER', __FILE__ );
define( 'FG_TABLE_NAME', 'fg_leads_organizer' );

if ( ENVIRONMENT === 'PRODUCTION' ) {
	define( 'PIPEDRIVE_TOKEN', 'xxx' );
	define( 'PIPEDRIVE_API_URL', 'https://imobillenegociosimo.pipedrive.com/v1/' );
	define( 'RDSTATION_TOKEN', 'xxx' );
	define( 'RDSTATION_API_URL', 'https://www.rdstation.com.br/api/1.3/conversions' );
} else {
	define( 'PIPEDRIVE_TOKEN', 'xxx' );
	define( 'PIPEDRIVE_API_URL', 'https://francisgregori.pipedrive.com/v1/' );
	define( 'RDSTATION_TOKEN', 'xxx' ); // Public token - https://app.rdstation.com.br/integracoes/tokens
	define( 'RDSTATION_API_URL', 'https://www.rdstation.com.br/api/1.3/conversions' );

}


class FGLeadsOrganizer {

	/**
	 * @var instance
	 */
	static $instance;

	/**
	 * @var leads WP_List_Table object
	 */
	public $leads_obj;

	public function __construct() {


		add_filter( 'set-screen-option', [ __CLASS__, 'fg_leads_set_screen' ], 10, 3 );

		register_activation_hook( FG_LEADS_ORGANIZER, array( $this, 'fg_leads_organizer_on_activation' ) );

		add_action( 'admin_menu', array( $this, 'fg_leads_organizer_admin_menu' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'fg_leads_organizer_scripts' ) );

		add_action( 'wp_ajax_fg_leads_organizer_ajax_request', array( $this, 'fg_leads_organizer_ajax_request' ) );
		add_action( 'wp_ajax_nopriv_fg_leads_organizer_ajax_request',
			array( $this, 'fg_leads_organizer_ajax_request' )
		);

//		add_action( 'wpcf7_sent', array( $this, 'fg_leads_organizer_wpcf7_function' ) );

	}

	/**
	 * In plugin activation we will verify if the table exist on the database
	 * if the plugin table does not exist it will created
	 */
	public static function fg_leads_organizer_on_activation() {
		global $wpdb;

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$table_name = $wpdb->prefix . FG_TABLE_NAME;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			//table not in database. Create new table
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
						`id` INT NOT NULL AUTO_INCREMENT,
						`name` VARCHAR(255) NOT NULL,
						`phone` VARCHAR(15) NOT NULL,
						`email` VARCHAR(150) NOT NULL,
						`message` TEXT NULL,
						`property_id` VARCHAR(45) NULL,
						`property_title` VARCHAR(255) NULL,
						`property_price` VARCHAR(45) NULL,
						`source` VARCHAR(45) NULL,
						`created_at` DATETIME NULL DEFAULT NOW(),
						PRIMARY KEY (`id`)
						) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

	}


	/**
	 * @param $status
	 * @param $option
	 * @param $value
	 *
	 * @return mixed
	 */
	public static function fg_leads_set_screen( $status, $option, $value ) {
		return $value;
	}


	/**
	 * The function responsible for creating the menu link on the Wordpress admin
	 */
	public function fg_leads_organizer_admin_menu() {


		$hook = add_menu_page( 'Leads API', 'Leads API', 'manage_options', 'fg-leads-organizer', array(
			$this,
			'fg_leads_organizer_page'
		), 'dashicons-clipboard', 30 );

		add_action( "load-$hook", [ $this, 'fg_leads_screen_option' ] );


	}

	/**
	 * Adding the PHP file to the plugin URL
	 */
	public function fg_leads_organizer_page() {
		global $title;
		?>
        <div class="wrap">
            <h2><?php echo $title; ?></h2>

            <div id="fg-leads-organizer">
                <div id="post-body" class="metabox-holder">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
								<?php
								$this->leads_obj->prepare_items();
								$this->leads_obj->display(); ?>
                            </form>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        </div>
		<?php
	}

	/**
	 * Screen options
	 */
	public function fg_leads_screen_option() {

		global $wpdb;

		/**
		 * Get table name
		 */
		$table_name = $wpdb->prefix . FG_TABLE_NAME;

		$option = 'per_page';
		$args   = [
			'label'   => 'Leads',
			'default' => 5,
			'option'  => 'leads_per_page'
		];

		add_screen_option( $option, $args );

		$this->leads_obj = new FGLeads_List( $table_name );
	}

	/**
	 * Adding the necessary css and js files to the plugin
	 */
	public function fg_leads_organizer_scripts() {
		$protocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
		$params   = array(
			'ajaxurl'   => admin_url( 'admin-ajax.php', $protocol ),
			'pluginurl' => plugin_dir_url( __FILE__ )
		);

//		wp_enqueue_style( 'fgbc-styles', plugin_dir_url( __FILE__ ) . 'assets/css/fgbc-styles.css' );

		wp_enqueue_script( 'fglo-scripts', plugin_dir_url( __FILE__ ) . 'assets/js/fglo-scripts.js', array( 'jquery' ) );
		wp_localize_script( 'fglo-scripts', 'fglo_params', $params );
	}

	/**
	 * The function responsible for inserting the data on database
	 */
	public function fg_leads_organizer_insert_data( $fields, $source ) {

		global $wpdb;

		/**
		 * Variable to store all values of the table fields
		 */
		$tableFields = array();

		$tableFields['name']  = isset( $fields['FORMIMO_NOME'] ) ? $fields['FORMIMO_NOME'] : '';
		$tableFields['phone'] = isset( $fields['FORMIMO_CELULAR'] ) ? $fields['FORMIMO_CELULAR'] : '';
		$tableFields['email'] = isset( $fields['FORMIMO_EMAIL'] ) ? strtolower( $fields['FORMIMO_EMAIL'] ) : '';

		if ( $source === 'IMOVEL' ) {
			$property_id = isset( $fields['FORMIMO_IDIMOB'] ) ? $fields['FORMIMO_IDIMOB'] : '';

			if ( ! empty( $property_id ) ) {

				$tableFields['property_id'] = get_post_meta( $property_id, 'property_id', true );

				$property_title = get_the_title( $property_id );

				if ( $property_title ) {
					$tableFields['property_title'] = $property_title;
				}

				$property_price = get_post_meta( $property_id, 'property_price', true );

				if ( $property_price ) {
					$tableFields['property_price'] = number_format( $property_price, 0, ',', '.' );
				}
			}
		} elseif ( $source === 'CONTATO' ) {

			$tableFields['message'] = isset( $fields['FORMIMO_MSG'] ) ? $fields['FORMIMO_MSG'] : '';
		}

		/**
		 * Get table name
		 */
		$table_name = $wpdb->prefix . FG_TABLE_NAME;

		/**
		 * Checks if this email already exist in the database
		 */
		$verifyLead = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE email = '{$tableFields['email']}'" );


		/**
		 * If it does not exist, we insert the lead
		 */
		if ( ! $verifyLead ) {
			/**
			 * Insert data in the wordpress table
			 */
			$wpdb->insert( $table_name, $tableFields );
		}


		/**
		 * Performs all functions required to register the deal on Pipedrive
		 */
		$this->pipedriveRequest( $tableFields, $source );

		/**
		 * Performs all functions required to register the lead on RD Station
		 */
		$this->rdStationRequest( $tableFields, $source );


		exit();

	}

	/**
	 * The function responsible for the ajax requests of the plugin
	 */
	public function fg_leads_organizer_ajax_request() {

		$params = array();
		parse_str( $_REQUEST['data'], $params );

		$this->fg_leads_organizer_insert_data( $params, ( $params['FORMIMO_IDIMOB'] ? 'IMOVEL' : 'CONTATO' ) );

		exit();
	}


	/*	public function fg_leads_organizer_wpcf7_function( $form ) {
			$submission = WPCF7_Submission::get_instance();

			if ( $submission ) {

				$this->fg_leads_organizer_insert_data( $submission->get_posted_data(), 'CONTATO' );

			}
		}*/

	/**
	 * @param $api
	 * @param $url
	 * @param $data
	 * @param string $method
	 *
	 * @return array|mixed|object
	 */
	public function curlRequest( $api, $url, $data, $method = 'GET' ) {

		if ( $api === 'pipe' ) {
			$url = PIPEDRIVE_API_URL . $url . '?api_token=' . PIPEDRIVE_TOKEN;
		} elseif ( $api === 'rd' ) {
			$url = RDSTATION_API_URL;
		} else {
			return false;
		}

		if ( $method === 'GET' ) {
			$url = $url . $data;
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		if ( $method === 'POST' ) {
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		}

		$output = curl_exec( $ch );
		curl_close( $ch );

		return json_decode( $output, true );
	}

	/**
	 * RD Station functions
	 *
	 * @param $tableFields
	 * @param $source
	 */
	public function rdStationRequest( $tableFields, $source ) {

		/**
		 * Required fields
		 */
		$fields = array(
			'token_rdstation' => RDSTATION_TOKEN,
			'name'            => $tableFields['name'],
			'email_lead'      => $tableFields['email'],
			'personal_phone'  => $tableFields['phone'],
			'tags'            => 'SITE'
		);

		if ( $source === 'IMOVEL' ) {
			$fields['identificador'] = "Imóvel Cod. {$tableFields['property_id']} - SITE";

			$propertyPrice = str_replace( ',', '.', str_replace( '.', '', $tableFields['property_price'] ) );

			if ( $propertyPrice > 2000000 ) {
				$tag = 'clientes acima de 2 milhões';
			} else if ( $propertyPrice < 2000000 && $propertyPrice >= 1500000 ) {
				$tag = 'clientes de 1.5 até 2 milhões';
			} else if ( $propertyPrice < 1500000 && $propertyPrice >= 1000000 ) {
				$tag = 'clientes de 1 mi até 1.5';
			} else if ( $propertyPrice < 1000000 && $propertyPrice >= 750000 ) {
				$tag = 'clientes de 750 até 1 milhão';
			} else if ( $propertyPrice < 750000 && $propertyPrice >= 600000 ) {
				$tag = 'clientes de 600 até 750 mil';
			} else if ( $propertyPrice < 600000 ) {
				$tag = 'clientes até 600 k';
			} else if ( $propertyPrice < 500000 ) {
				$tag = 'clientes até 500 k';
			}

			$fields['tags'] .= ", {$tag}";

		} else {
			$fields['identificador'] = 'CONTATO VIA SITE';
		}

		$this->curlRequest( 'rd', null, $fields, 'POST' );

	}

	/**
	 * Pipedrive functions
	 *
	 * @param $tableFields
	 * @param $source
	 */
	public function pipedriveRequest( $tableFields, $source ) {

		/**
		 * Init the person variable
		 */
		$person = null;

		/**
		 * Check if this email is already registered for a person
		 */
		$verifyPerson = $this->curlRequest( 'pipe', 'persons/find', "&term={$tableFields['email']}&search_by_email=1" );


		if ( $verifyPerson['data'] ) {
			$personID = $verifyPerson['data'][0]['id'];
		} else {

			$person = $this->curlRequest( 'pipe', 'persons', array(
				'name'                                     => $tableFields['name'],
				'email'                                    => $tableFields['email'],
				'phone'                                    => $tableFields['phone'],
				'b21bf87f53a8d9f101b301836b9fa9eb70636fc5' => 'Site'
			), 'POST' );

			$personID = $person['data']['id'];

		}

		if ( $source === 'IMOVEL' ) {


			$propertyPrice = str_replace( ',', '.', str_replace( '.', '', $tableFields['property_price'] ) );

			$this->curlRequest( 'pipe', 'deals', array(
				'user_id'   => '365185', // Lauricio User ID
				'title'     => $tableFields['property_title'],
				'value'     => empty( $tableFields['property_price'] ) ? '' : ( floatval($propertyPrice) * .05 ),
				'person_id' => $personID,
			), 'POST' );

		} elseif ( $source === 'CONTATO' ) {

			$this->curlRequest( 'pipe', 'deals', array(
				'user_id'                                  => '365185', // Lauricio User ID
				'title'                                    => $tableFields['name'] . ' - Contato via site',
				'value'                                    => 0,
				'person_id'                                => $personID,
				'b6bf253be59c234289d83a4c0c4b813f90fecddd' => $tableFields['message']
			), 'POST' );
		}

	}

	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

$FGBarcodeGenerator = new FGLeadsOrganizer();
