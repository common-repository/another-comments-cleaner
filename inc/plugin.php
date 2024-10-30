<?php
if ( !defined( 'WPINC' ) ) return;
class ancc {
	// option name
	const optname = 'ancc-settings';
	
	// hook name for CRON job
	const taskname = 'ancc-task';
	
	private static $instance = null;
	
	// comment status strings
	private $comment_status = array();
	
	// actions to take based on status
	private $comment_action = array();
	
	// admin page hook
	private $hook = null;
	
	private function __construct() {
		add_action( self::taskname, array( $this, 'sched_task' ) );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 10 );
		// dashboard
		if ( is_admin() ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				add_action( 'wp_ajax_ancc-immediate-clean', array( $this, 'immediate_clean' ) );
				add_action( 'wp_ajax_ancc-save-map', array( $this, 'save_map' ) );
				add_action( 'wp_ajax_ancc-sched', array( $this, 'save_sched' ) );
			} else {
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				add_action( 'admin_print_scripts', array( $this, 'print_scripts' ) );
				add_action( 'init', array( $this, 'init' ) );
			}
		}
		
	}
	
	/**
	 *  load translation files
	 */
	public function plugins_loaded() {
		load_plugin_textdomain( 'ancc', false, ANCC_PATH . '/languages' );
	}
	
	/**
	 *  method called by WP_Cron
	 */
	public function sched_task() {
		$options = get_option( self::optname, array() );
		if ( !isset( $options['period'] ) || 'none' == $options['period'] ) return;
		if ( !isset( $options['map'] ) ) return;
		if ( 'custom' != $options['method'] ) {
			$this->proceed( $options['map'] );
		} else {
			if ( isset( $options['lastSched'] ) && $options['lastSched'] && isset( $options['nSched'] ) && 1 < absint( $options['nSched'] ) ) {
				$now = time();
				$n = absint( $options['nSched'] );
				$ts = absint( $options['lastSched'] ) + ( 3600 * 24 * $n );
				if ( $now >= $ts ) {
					$ts += ( 3600 * 24 * $n );
					while ( $now > $ts ) {
						// in case the site was down for a long priod - otherwise task might be ran every day
						$ts =+ ( 3600 * 24 * $n );
					}
					$options['lastSched'] = $ts;
					update_option( self::optname, $options );
					
					$this->proceed( $options['map'] );
				}
			}
		}
	}
	
	/**
	 *  return an human readable version of the next cleaning task occurence date
	 */
	public function next_sched_readable() {
		$options = get_option( self::optname, array() );
		if ( !isset( $options['period'] ) ) return 'N/A';
		switch( $options['period'] ) {
			case 'hourly':
			case 'daily':
			case 'twicedaily':
				$ts = wp_next_scheduled( self::taskname );
				if ( $ts ) {
					$d = date_create( '@' . $ts, new DateTimeZone( 'UTC' ) );
					return $d->format( get_option( 'date_format' ) ) . ' ' . $d->format( get_option( 'time_format' ) ) . ' UTC';
				}
				break;
			case 'custom':
				if ( isset( $options['lastSched'] ) && $options['lastSched'] && isset( $options['nSched'] ) && 1 < absint( $options['nSched'] ) ) {
					$ts = absint( $options['lastSched'] ) + ( 3600 * 24 * absint( $options['nSched'] ) );
					$d = date_create( '@' . $ts, new DateTimeZone( 'UTC' ) );
					return $d->format( get_option( 'date_format' ) ) . ' ' . $d->format( get_option( 'time_format' ) ) . ' UTC';
				}
				break;
			case 'none':
			default:
				return 'N/A';
		}
		return 'N/A';
	}
	
	/**
	 *  AJAX callback - save schedules
	 */
	public function save_sched() {
		if ( false === wp_verify_nonce( $_POST['nonce'], 'ancc' ) ) die;
		if ( !current_user_can( 'moderate_comments' ) ) die;
		if ( !isset( $_POST['args'] ) ) die;
		$args = array();
		parse_str( $_POST['args'], $args );
		if ( !isset( $args['period'] ) || empty( $args['period'] ) ) die;
		switch ( $args['period'] ) {
			case 'default':
				$freq = ( in_array( $args['period-default'], array( 'hourly', 'daily', 'twicedaily' ) ) )? $args['period-default'] : 'daily';
				$ts = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
				if ( 'hourly' == $freq ) {
					$_ts = absint( $ts->format( 'U' ) ) + 60;
				} else {
					$ts->setTime( 23, 59, 59 );
					$_ts = absint( $ts->format( 'U' ) );
				}
				if ( wp_next_scheduled( self::taskname ) ) {
					wp_clear_scheduled_hook( self::taskname );
				}
				
				wp_schedule_event( $_ts, $freq, self::taskname );
				
				$options = get_option( self::optname, array() );
				$options['lastSched'] = false;
				$options['nSched'] = false;
				$options['period'] = $freq;
				update_option( self::optname, $options );
				
				break;
			case 'custom':
				$n = absint( $args['custom-n'] );
				if ( 2 > $n ) $n = 2;
				$h = absint( $args['custom-h'] );
				$m = absint( $args['custom-m'] );
				if ( 0 > $h || 23 < $h ) $h = 12;
				if ( 0 > $m || 59 < $m ) $m = 30;
				$ts = new DateTime( 'tomorrow', new DateTimeZone( 'UTC' ) );
				$ts->setTime( $h, $m );
				$_ts = absint( $ts->format( 'U' ) );
				if ( wp_next_scheduled( self::taskname ) ) {
					wp_clear_scheduled_hook( self::taskname );
				}
				
				wp_schedule_event( $_ts, 'daily', self::taskname );
				
				$options = get_option( self::optname, array() );
				$options['lastSched'] = $_ts;
				$options['nSched'] = $n;
				$options['hSched'] = $h;
				$options['mSched'] = $m;
				$options['period'] = 'custom';
				update_option( self::optname, $options );
				
				break;
			case 'disabled':
				wp_clear_scheduled_hook( self::taskname );
				$options = get_option( self::optname, array() );
				$options['lastSched'] = false;
				$options['nSched'] = false;
				$options['period'] = 'none';
				update_option( self::optname, $options );
				break;
			default:
		}
		header( 'Content-Type: application/json' );
		echo json_encode( array(
			'status' => true,
			'msg' => ancc__( 'Settings saved.' ),
			'nextRun' => $this->next_sched_readable(),
		) );
		die;
	}
	
	/**
	 *  AJAX callback - save status/actions map
	 */
	public function save_map() {
		if ( false === wp_verify_nonce( $_POST['nonce'], 'ancc' ) ) die;
		if ( !current_user_can( 'moderate_comments' ) ) die;
		
		$map = array(
			'pending' => 'ignore',
			'spam' => 'delete',
			'trash' => 'ignore',
		);
		
		if ( isset( $_POST['spam'] ) && in_array( $_POST['spam'], array( 'ignore', 'delete' ) ) ) $map['spam'] = $_POST['spam'];
		if ( isset( $_POST['trash'] ) && in_array( $_POST['trash'], array( 'ignore', 'delete' ) ) ) $map['trash'] = $_POST['trash'];
		if ( isset( $_POST['pending'] ) && in_array( $_POST['pending'], array( 'ignore', 'delete', 'trash' ) ) ) $map['pending'] = $_POST['pending'];
		$options = get_option( self::optname, array() );
		$options = array( 'map' => $map ) + $options;
		update_option( self::optname, $options );
		header( 'Content-Type: application/json' );
		echo json_encode( array(
			'status' => true,
			'msg' => ancc__( 'Settings saved.' ),
		) );
		die;
	}
	
	/**
	 *  AJAX callback - clean immediatly
	 */
	public function immediate_clean() {
		if ( false === wp_verify_nonce( $_POST['nonce'], 'ancc' ) ) die;
		if ( !current_user_can( 'moderate_comments' ) ) die;
		$map = array();
		if ( isset( $_POST['spam'] ) ) $map['spam'] = $_POST['spam'];
		if ( isset( $_POST['trash'] ) ) $map['trash'] = $_POST['trash'];
		if ( isset( $_POST['pending'] ) ) $map['pending'] = $_POST['pending'];
		$result = $this->proceed( $map );
		header( 'Content-TYpe: application/json' );
		$stats = array(
			'lastTrash' => $result['trash'],
			'lastDelete' => $result['delete'],
			'lastIgnore' => $result['ignore'],
			'lastExec' => time(),
		);
		$options = get_option( self::optname, array() );
		$new_options = $stats + $options;
		update_option( self::optname, $new_options );
		
		$stats['execDate'] = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' \U\T\C', $stats['lastExec'] );
		
		$response = array(
			'status' => true,
			'stats' => $stats,
		);
		if ( 0 == $result['totalRows'] ) {
			$response['msg'] = __( 'No comment to delete', 'ancc' );
		} else {
			$response['msg'] = sprintf(
				__( 'Done. %s: %s, %s: %s, %s: %s', 'ancc' ),
				_n( 'Deleted', 'Deleted', $result['delete'], 'ancc' ),
				'<strong>' . $result['delete'] . '</strong>',
				_n( 'Moved to trash', 'Moved to trash', $result['trash'], 'ancc' ),
				'<strong>' . $result['trash'] . '</strong>',
				_n( 'Ignored', 'Ignored', $result['ignore'], 'ancc' ),
				'<strong>' . $result['ignore'] . '</strong>'
			);
		}
		echo json_encode( $response );
		die;
	}
	
	/**
	 *  perform all the DB operations
	 */
	private function proceed( $_map = array() ) {
		$affected_rows = array(
			'trash' => 0,
			'delete' => 0,
			'ignore' => 0,
		);
		$status = array(
			'pending' => '0',
			'spam' => 'spam',
			'trash' => 'trash',
		);
		$total_row = 0;
		global $wpdb;
		$map = array();
		foreach ( $_map as $i => $j ) {
			if ( 'pending' != $i ) $map[$i] = $j;
		}
		// move 'pending' at the end of the array to avoid it being processed twice (trash then delete)
		if ( isset( $_map['pending'] ) ) $map['pending'] = $_map['pending'];
		foreach ( $map as $key => $action ) {
			if ( 'spam' == $key && 'trash' == $action ) continue;
			if ( 'trash' == $key && 'trash' == $action ) continue;
			if ( array_key_exists( $key, $status ) && array_key_exists( $action, $affected_rows ) ) {
				$_comments_ids = $wpdb->get_col( "SELECT comment_id FROM {$wpdb->comments} WHERE comment_approved = '{$status[$key]}'" );
				if ( !empty( $_comments_ids ) ) {
					if ( 'trash' == $action ) {
						// only for unapproved comments
						$comments_ids = implode( ',', array_map( 'intval', $_comments_ids ) );
						$count = $wpdb->query( "UPDATE {$wpdb->comments} SET comment_approved = 'trash' WHERE comment_id IN ( $comments_ids )" );
						
						if ( false !== $count ) {
							$affected_rows['trash'] += $count;
							$total_row += $count;
						}
						// set comments meta data
						if ( 20 < count( $_comments_ids ) ) {
							// if there is too much comments, split the array into smaller chunks - avoid troubles with low end server configs
							$chunks = array_chunk( $_comments_ids, 20 );
							foreach ( $chunks as $_ids ) {
								$values = $this->trash_meta_fields( $_ids );
								$values = implode( ',', $values );
								$wpdb->query( "INSERT INTO {$wpdb->commentmeta} (`comment_id`, `meta_key`, `meta_value`) VALUES $values" );
							}
						} else {
							$values = $this->trash_meta_fields( $_comments_ids );
							$values = implode( ',', $values );
							$wpdb->query( "INSERT INTO {$wpdb->commentmeta} (`comment_id`, `meta_key`, `meta_value`) VALUES $values" );
						}
						
					} elseif( 'delete' == $action ) {
						$comments_ids = implode( ',', array_map( 'intval', $_comments_ids ) );
						$count = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_id IN ( $comments_ids )" );
						$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ( $comments_ids )" );
						if ( false !== $count ) {
							$affected_rows['delete'] += $count;
							$total_row += $count;
						}
					} else {
						$affected_rows['ignore'] +=  count( $_comments_ids );
					}
				}
			}
		}
		if ( $total_row ) {
			$wpdb->query( "OPTIMIZE TABLE {$wpdb->comments}" );
			$wpdb->query( "OPTIMIZE TABLE {$wpdb->commentmeta}" );
		}
		return $affected_rows + array( 'totalRows' => $total_row );
	}
	
	/**
	 *  prepare the value to insert in commentmeta for comments moved to trash
	 */
	private function trash_meta_fields( $ids = array() ) {
		$result = array();
		$time = (string)time();
		foreach ( $ids as $id ) {
			$result[] = "($id,'_wp_trash_meta_status','0'),($id,'_wp_trash_meta_time','$time')";
		}
		return $result;
	}	
	
	/**
	 *  initialisation
	 */
	public function init() {
		$this->comment_status = array(
			'pending' => ancc_x( 'Pending', 'comment status' ),
			'spam' => ancc_x( 'Spam', 'verb' ),
			'trash' => ancc_x( 'Trash', 'attachment filter' ),
		);
		$this->comment_action = array(
			'delete' => ancc__( 'Permanently Delete Comment' ),
			'trash' => ancc__( 'Move to Trash' ),
			'ignore' => __( 'Ignore', 'meva' ),
		);
	}
	
	/**
	 *  print JS variables on admin pages
	 */
	public function print_scripts() {
		$scr = get_current_screen();
		if( $scr->id == $this->hook ) {
			$ancc = array(
				'settings' => get_option( self::optname, new stdClass() ),
				'nonce' => wp_create_nonce( 'ancc' ),
				'adminUrl' => admin_url(),
			);
			?><script type="text/javascript">var ancc = <?php echo json_encode( $ancc ); ?>;</script><?php
		}
	}
	
	/**
	 *  enqueue styles/scripts on admin page
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook == $this->hook ) {
			wp_register_script( 'ancc', ANCC_URL . 'assets/ancc.js', array( 'jquery' ), null );
			wp_localize_script( 'ancc', 'anccLocale', array(
				'unknownError' => __( 'An unknown error occurred', 'ancc' ),
			) );
			wp_enqueue_script( 'ancc' );
			wp_enqueue_style( 'ancc', ANCC_URL . 'assets/ancc.css', array(), null );
		}
	}
	
	/**
	 *  add the admin page in menu
	 */
	public function admin_menu() {
		$this->hook = add_comments_page( __( 'Comments Cleaner', 'ancc' ), __( 'Cleaner' ), 'moderate_comments', 'ancc', array( $this, 'admin_page' ) );
	}

	/**
	 *  admin page callback
	 */
	public function admin_page() {
		include ANCC_PATH . 'inc/admin-page.php';
	}

	/**
	 *  load the plugin's text domain
	 */
	public function load_textdomain() {
	    load_plugin_textdomain( 'wpcc', false, WPCC_PATH . '/languages' );
	}
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
}
ancc::instance();
