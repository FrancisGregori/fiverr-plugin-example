<?php

/**
 * Description: This plugin will capture and organize the leads list in a wordpress table
 * Version: 1.0.0
 * Author: Francis Gregori
 * Author URI: https://www.francisgregori.com.br/
 * License: GPL-2.0+
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class FGLeads_List extends WP_List_Table {

	private $_tableName = null;

	/** Class constructor */
	public function __construct( $_tableName ) {

		$this->_tableName = $_tableName;

		parent::__construct( [
			'singular' => __( 'Lead', 'fglead' ), //singular name of the listed records
			'plural'   => __( 'Leads', 'fglead' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}

	/**
	 * @return null
	 */
	public function getTableName() {
		return $this->_tableName;
	}


	/**
	 * Retrieve leads data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public function get_leads( $per_page = 5, $page_number = 1 ) {

		global $wpdb;


		$sql = "SELECT * FROM " . $this->getTableName();

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


	/**
	 * Delete a lead record.
	 *
	 * @param int $id lead id
	 */
	public function delete_lead( $id ) {
		global $wpdb;

		$wpdb->delete(
			$this->getTableName(),
			[ 'id' => $id ],
			[ '%d' ]
		);
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$this->getTableName()}";

		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no lead data is available */
	public function no_items() {
		_e( 'No leads avaliable.', 'fglead' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'email':
			case 'phone':
				return $item[ $column_name ];
			case 'created_at':
				return date( 'd/m/Y H:i:s', strtotime( $item[ $column_name ] ) );
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
		);
	}


	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_name( $item ) {

		$delete_nonce = wp_create_nonce( 'fglead_delete_lead' );

		$title = '<strong>' . $item['name'] . '</strong>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&lead=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce )
		];

		return $title . $this->row_actions( $actions );
	}


	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'fglead' ),
			'email'      => __( 'E-mail', 'fglead' ),
			'phone'      => __( 'Telefone', 'fglead' ),
			'created_at' => __( 'Data', 'fglead' ),
		];

		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'name'       => array( 'name', true ),
			'email'      => array( 'email', false ),
			'created_at' => array( 'created_at', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'leads_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_leads( $per_page, $current_page );
	}

	public function process_bulk_action() {

		$url = admin_url( 'admin.php?page=fg-leads-organizer' );

		//Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );

			if ( ! wp_verify_nonce( $nonce, 'fglead_delete_lead' ) ) {
				die( 'Go get a life script kiddies' );
			} else {
				self::delete_lead( absint( $_GET['lead'] ) );

				echo 'Os dados foram removidos com sucesso! <br> <a href="' . $url . '"><< Voltar para listagem</a>  ';
				// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
				// add_query_arg() return the current url
				wp_redirect( $url );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record ids and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_lead( $id );
			}

			echo 'Os dados foram removidos com sucesso! <a href="' . $url . '"><< Voltar para listagem</a>  ';

			// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
			// add_query_arg() return the current url
			wp_redirect( $url );
			exit;
		}
	}
}