<?php
	/**
	 * Plugin Name: wptop
	 * Description: A top for WordPress, a real-time production code performance monitoring plugin. Read the README before activating.
	 * Author: Gennady Kovshenin
	 * License: GPLv3
	 * Version: 0.1
	 */

	class wptop {
		public static $version = '0.1';
		public static $table = 'top';

		/**
		 * Early initialization.
		 *
		 * Hooks, lines and other fishy business here.
		 */
		public static function bootstrap() {

			require dirname( __FILE__ ) . '/xhprof_lib.php';

			/** Filter */
			add_action( 'init', array( __CLASS__, 'maybe_discard' ) );

			/** Admin configuration */
			add_action( 'admin_init', array( __CLASS__, 'save_configuration' ) );

			add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );

			/** Scheduler */
			add_action( implode( '::', array( __CLASS__, 'cook' ) ), array( __CLASS__, 'cook' ) );
			
			/** Install upon activation */	
			register_activation_hook( __FILE__, array( __CLASS__, 'install' ) );

			/** Cleanup upon uninstall, be a good citizen. */
			register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

			/** Register the shutdown function which stops profiling */
			register_shutdown_function( function() {
				/** To make sure it's run last we employ this hack */
				register_shutdown_function( array( __CLASS__, 'disable' ) );
			} );
		}

		/** Initializes the Administration menu */
		public static function add_menu_page() {
			add_submenu_page( 'tools.php', 'wptop', 'wptop', 'manage_options', 'wptop', array( __CLASS__, 'menu_page' ) );
		}

		/** The main wptop interface */
		public static function menu_page() {
			global $wpdb;
			?>
				<div class="wrap">
				<h2>wptop</h2>

				<p>Performance statistics for your PHP code in production.</p>

				<style>
					#wptop-screen .contextual-help-back {
						position: absolute;
						top: 0;
						bottom: 0;
						left: 150px;
						right: 170px;
						border: 1px solid #e1e1e1;
						border-top: none;
						border-bottom: none;
						background: #f6fbfd;
					}

					#wptop-screen .contextual-help-wrap {
						overflow: auto;
						position: relative;
					}

					#wptop-screen .help-tab-content {
						padding: 1em;	
					}

					#wptop-overview .averages li {
						list-style: none;
						float: left;
						padding: 1em;
						border-right: 1px dotted gray;
						border-left: 1px dotted gray;
						text-align: center;
						margin-left: 0px;
					}
					
					#wptop-overview .averages li h4 {
						font-size: 20px;
					}

					#wptop-top .top-block {
						float: left;
					}
					#wptop-top .top-block li {
						list-style: none;
					}
				</style>

				<div id="wptop-screen" class="postbox" style="min-height: 400px">
					<div class="contextual-help-back"></div>
					<div class="contextual-help-wrap">
						<div class="contextual-help-tabs">
							<ul>
								<li class="active"><a href="#wptop-overview" aria-controls="wptop-all">Overview</a></li>
								<li><a href="#wptop-top" aria-controls="wptop-top">Top</a></li>
								<li><a href="#wptop-configuration" aria-controls="wptop-configuration">Configuration</a></li>
								<li><a href="#wptop-status" aria-controls="wptop-status">Status</a></li>
								<!-- <li><a href="#wptop-help" aria-controls="wptop-help">Help</a></li> -->
							</ul>
						</div>
						
						<div class="contextual-help-sidebar" style="overflow: initial;">
							<p>There's nothing better than knowing how your code performs in the wild...</p>
							<a href="http://codeseekah.com">http://codeseekah.com</a>
						</div>
					
						<div class="contextual-help-tabs-wrap">
							<?php $cache = get_option( 'wptop_cache' ); ?>
							<div id="wptop-overview" class="help-tab-content active">
								<h2>Overview</h2>
								<?php if ( $cache ) { ?>
									<p>Here's how it is looking so far...</p>
									<ul class="averages">
										<?php
										?>
										<li>
											<h4><?php echo intval( $cache['avg']['wall'] / 1000 ); ?> ms</h4>
											<p>Average time</p>
											<em><?php echo intval( $cache['min']['wall'] / 1000 ); ?> ms - <?php echo intval( $cache['max']['wall'] / 1000 ); ?> ms</em><br />
											<em>sd: <?php echo intval( $cache['std']['wall'] / 1000 ); ?> ms</em>
										</li>
										<li>
											<h4><?php echo intval( $cache['avg']['cpu'] / 1000 ); ?> ms</h4>
											<p>Average CPU time</p>
											<em><?php echo intval( $cache['min']['cpu'] / 1000 ); ?> ms - <?php echo intval( $cache['max']['cpu'] / 1000 ); ?> ms</em><br />
											<em>sd: <?php echo intval( $cache['std']['cpu'] / 1000 ); ?> ms</em>
										</li>
										<li>
											<h4><?php echo size_format( $cache['avg']['memory'] ); ?></h4>
											<p>Average memory</p>
											<em><?php echo size_format( $cache['min']['memory'] ); ?> - <?php echo size_format( $cache['max']['memory'] ); ?></em><br />
											<em>sd: <?php echo size_format( $cache['std']['memory'] ); ?></em>
										</li>
										<li>
											<h4><?php echo intval( $cache['avg']['calls'] ); ?></h4>
											<p>Average calls</p>
											<em><?php echo intval( $cache['min']['calls'] ); ?> - <?php echo intval( $cache['max']['calls'] ); ?></em><br />
											<em>sd: <?php echo intval( $cache['std']['calls'] ); ?></em>
										</li>
										<li>
											<h4><?php echo $cache['cooked']; ?></h4>
											<p>Entries analyzed</p>
											<em>Last analysis<br /><?php echo date( 'Y-m-d H:i:s', $cache['timestamp'] ); ?></em>
										</li>
									</ul>
									<div class="clear"></div>
								<?php } else { ?>
									<p>No analysis has been done so far. Check out the Status tab and come back later.</p>
								<?php } ?>
							</div>
							<div id="wptop-top" class="help-tab-content">
								<h2>Top</h2>
								<h3>Functions</h3>
								<div class="top-block">
									<h4>Average Time</h4>
									<ul>
										<?php foreach ( $cache['avg']['func']['wall'] as $function => $value ): ?>
											<li>
												<span class="value"><?php echo intval( $value / 1000 ); ?> ms</span>
												<?php echo esc_html( $function ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="top-block">
									<h4>Average Memory</h4>
									<ul>
										<?php foreach ( $cache['avg']['func']['memory'] as $function => $value ): ?>
											<li>
												<span class="value"><?php echo size_format( $value ); ?></span>
												<?php echo esc_html( $function ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="clear"></div>

								<h3>Requests</h3>
								<div class="top-block">
									<h4>Average Time</h4>
									<ul>
										<?php foreach ( $cache['avg']['req']['wall'] as $request ): ?>
											<li>
												<span class="value"><?php echo intval( $request['wall'] / 1000 ); ?> ms</span>
												<a href="<?php echo esc_url( $request['url'] ); ?>"><?php echo esc_html( $request['url'] ); ?></a>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="top-block">
									<h4>Average Memory</h4>
									<ul>
										<?php foreach ( $cache['avg']['req']['memory'] as $request ): ?>
											<li>
												<span class="value"><?php echo size_format( $request['memory'] ); ?></span>
												<a href="<?php echo esc_url( $request['url'] ); ?>"><?php echo esc_html( $request['url'] ); ?></a>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="clear"></div>
							</div>
							<div id="wptop-configuration" class="help-tab-content">
								<h2>Configuration</h2>

								<?php $configuration = get_option( 'wptop_configuration' ); ?>

								<form method="POST">
									<?php wp_nonce_field( 'wptop_configuration', 'wptop_configuration_nonce' ); ?>
									
									<h3>Schedule</h3>
									<label for="interval">Recalculate statistics</label>
									<select id="interval" name="interval">
										<option <?php selected( $configuration['interval'], 60 ); ?> value="60">every minute</option>
										<option <?php selected( $configuration['interval'], 900 ); ?> value="900">every 15 minutes</option>
										<option <?php selected( $configuration['interval'], 3600 ); ?> value="3600">every hour</option>
										<option <?php selected( $configuration['interval'], 86400 ); ?> value="86400">every day</option>
									</select>

									<h3>Filter</h3>
									<p>Profile requests performed in the following areas:</p>
									<input <?php checked( $configuration['filter']['dashboard'], true ); ?> type="checkbox" name="filter[dashboard]" id="filter[dashboard]" value="1"/><label for="filter[dashboard]">Dashboard</label><br />
									<input <?php checked( $configuration['filter']['ajax'], true ); ?> type="checkbox" name="filter[ajax]" id="filter[ajax]" value="1"/><label for="filter[ajax]">AJAX</label><br />
									<input <?php checked( $configuration['filter']['cron'], true ); ?> type="checkbox" name="filter[cron]" id="filter[cron]" value="1"/><label for="filter[cron]">Cron</label><br />

									<h3>Limits</h3>
									<label for="limit[entries]">Limit the amount of data stored and analyzed (0 for unlimited):</p>
									<input type="number" name="limit[entries]" id="limit[entries]" value="<?php echo esc_attr( $configuration['limit']['entries'] ); ?>" />
									<p>Large limits will take up more space in the database, so be careful.</p>

									<h3>Reset</h3>
									<input type="checkbox" name="limit[reset]" id="limit[reset]" /><label for="limit[reset]">Clear all entries</label>

									<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" /></p>
								</form>
							</div>
							<div id="wptop-status" class="help-tab-content">
								<h2>Status</h2>
								<ul>
									<li>Status: <?php echo defined( 'WPTOP_ENABLED' ) ? 'enabled' : 'disabled'; ?></li>
									<li>Mode:
										<?php echo defined( 'WPTOP_ENABLED' ) ? '' : 'disabled'; ?>
										<?php echo ( defined( 'WPTOP_ENABLED' ) && unserialize( WPTOP_ENABLED )['memory'] ) ? 'memory ' : ''; ?>
										<?php echo ( defined( 'WPTOP_ENABLED' ) && unserialize( WPTOP_ENABLED )['builtins'] ) ? 'builtins ' : ''; ?>
										<?php echo ( defined( 'WPTOP_ENABLED' ) && unserialize( WPTOP_ENABLED )['cpu'] ) ? 'cpu ' : ''; ?></li>
									<li>Raw entries: <?php echo $wpdb->get_var( sprintf( 'SELECT COUNT(*) FROM %s WHERE `status` = "raw"', $wpdb->prefix . self::$table ) ); ?></li>
									<li>Cooked entries: <?php echo $cache['cooked']; ?></li>
									<li>Last cook: <?php echo date( 'Y-m-d H:i:s', $cache['timestamp'] ); ?></li>
									<li>Scheduled cook: <?php echo date( 'Y-m-d H:i:s', wp_next_scheduled( implode( '::', array( __CLASS__, 'cook' ) ) ) ); ?> (now <?php echo date( 'Y-m-d H:i:s' ); ?>)</li>
									<?php
										$status = $wpdb->get_row( sprintf( 'SHOW TABLE STATUS LIKE "%s"', $wpdb->prefix . self::$table ) );
									?>
									<li>Dataset size: <?php echo esc_html( size_format( $status->Data_length ) ); ?></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
			<?php
		}

		/**
		 * Analyze raw entries and update totals, tops, etc.
		 */
		public static function cook() {
			$configuration = get_option( 'wptop_configuration' );

			/** Reschedule if we're inside of cron */
			if ( defined( 'DOING_CRON' ) || isset( $_GET['doing_wp_cron'] ) )
				wp_schedule_single_event( time() + $configuration['interval'], implode( '::', array( __CLASS__, 'cook' ) ) );

			global $wpdb;

			/** Truncate to limit */
			if ( $configuration['limit']['entries'] > 0 ) {
				$oldest = $wpdb->get_var( sprintf( 'SELECT MIN(`t`.`timestamp`) FROM (SELECT `timestamp` FROM %s ORDER BY `timestamp` DESC LIMIT %d) `t`', $wpdb->prefix . self::$table, $configuration['limit']['entries'] ) );
				$wpdb->query( sprintf( 'DELETE FROM %s WHERE `timestamp` < %d', $wpdb->prefix . self::$table, $oldest ) );
			}

			$rows = $wpdb->get_results( sprintf( 'SELECT * FROM %s WHERE `status` = "raw" ORDER BY `timestamp` DESC', $wpdb->prefix . self::$table ) );

			$compare = function( $key = null ) {
				if ( !$key ) return function( $a, $b ) {
					if ( $a == $b ) return 0;
					return ( $a < $b ) ? 1 : -1;
				};
				return function( $a, $b ) use ( $key ) {
					if ( $a[$key] == $b[$key] ) return 0;
					return ( $a[$key] < $b[$key] ) ? 1 : -1;
				};
			};

			foreach ( $rows as $row ) {
				$xhprof_data = unserialize( $row->raw );

				/** Calculate global function count */
				$count = array_reduce( $xhprof_data, function( $carry, $item ) {
					return $carry + $item['ct'];
				} );

				/** Get most popular functions, we keep 20 in account */
				$xhprof_data = xhprof_compute_flat_info( $xhprof_data, $totals );
				uasort( $xhprof_data, $compare( 'excl_wt' ) );
				$wt = array_slice( $xhprof_data, 0,20 );
				uasort( $xhprof_data, $compare( 'excl_pmu' ) );
				$mem = array_slice( $xhprof_data, 0,20 );

				$wpdb->update( $wpdb->prefix . self::$table, array(
					'status' => 'cooked',
					'calls' => $count,
					'top' => serialize( array( 'wt' => $wt, 'mem' => $mem ) ),
					'raw' => null, /** Discard the raw data */
				), array( 'id' => $row->id ) );
			}

			/** Statistics */
			$avg = $wpdb->get_row( sprintf( 'SELECT AVG(calls) AS calls, AVG(wall) AS wall, AVG(cpu) AS cpu, AVG(memory) AS memory FROM %s WHERE `status` = "cooked"', $wpdb->prefix . self::$table ), ARRAY_A );
			$max = $wpdb->get_row( sprintf( 'SELECT MAX(calls) AS calls, MAX(wall) AS wall, MAX(cpu) AS cpu, MAX(memory) AS memory FROM %s WHERE `status` = "cooked"', $wpdb->prefix . self::$table ), ARRAY_A );
			$min = $wpdb->get_row( sprintf( 'SELECT MIN(calls) AS calls, MIN(wall) AS wall, MIN(cpu) AS cpu, MIN(memory) AS memory FROM %s WHERE `status` = "cooked"', $wpdb->prefix . self::$table ), ARRAY_A );
			$std = $wpdb->get_row( sprintf( 'SELECT STD(calls) AS calls, STD(wall) AS wall, STD(cpu) AS cpu, STD(memory) AS memory FROM %s WHERE `status` = "cooked"', $wpdb->prefix . self::$table ), ARRAY_A );

			/** Request averages */
			$avg['req'] = array();
			$avg['req']['wall'] = $wpdb->get_results( sprintf( 'SELECT t.* FROM (SELECT AVG(wall) AS wall, url FROM %s WHERE status = "cooked" GROUP BY url) AS t ORDER BY t.wall DESC LIMIT %d', $wpdb->prefix . self::$table, 20 ), ARRAY_A );
			$avg['req']['memory'] = $wpdb->get_results( sprintf( 'SELECT t.* FROM (SELECT AVG(memory) AS memory, url FROM %s WHERE status = "cooked" GROUP BY url) AS t ORDER BY t.memory DESC LIMIT %d', $wpdb->prefix . self::$table, 20 ), ARRAY_A );

			/** Function averages */
			$avg['func'] = array( 'wall' => array(), 'memory' => array() );
			foreach ( $wpdb->get_results( sprintf( 'SELECT top FROM %s WHERE status = "cooked"', $wpdb->prefix . self::$table ) ) as $entry ) {
				$top = unserialize( $entry->top );	
				foreach ( $top['wt'] as $function => $value ) {
					if ( !isset( $avg['func']['wall'][$function] ) ) $avg['func']['wall'][$function] = array();
					$avg['func']['wall'][$function] []= $value['excl_wt'];
				}
				foreach ( $top['mem'] as $function => $value ) {
					if ( !isset( $avg['func']['memory'][$function] ) ) $avg['func']['memory'][$function] = array();
					$avg['func']['memory'][$function] []= $value['excl_pmu'];
				}
			}
			$avg['func']['wall'] = array_map( function( $value ) {
				return array_sum( $value ) / count( $value );
			}, $avg['func']['wall'] );
			$avg['func']['memory'] = array_map( function( $value ) {
				return array_sum( $value ) / count( $value );
			}, $avg['func']['memory'] );

			uasort( $avg['func']['wall'], $compare() );
			$avg['func']['wall'] = array_slice( $avg['func']['wall'], 0, 20 );

			uasort( $avg['func']['memory'], $compare() );
			$avg['func']['memory'] = array_slice( $avg['func']['memory'], 0, 20 );

			update_option( 'wptop_cache', array(
				'timestamp' => time(),
				'avg' => $avg, 'max' => $max, 'min' => $min, 'std' => $std,
				'cooked' => $wpdb->get_var( sprintf( 'SELECT COUNT(*) FROM %s WHERE `status` = "cooked"', $wpdb->prefix . self::$table ) )
			) );
		}

		/**
		 * Installation and update.
		 *
		 * Checks whether the plugin is even installed, if not...
		 * ...installs it.
		 *
		 * ...if it is, we try to update upon activation!
		 *
		 */
		public static function install() {
			/** We're already installed it seems */
			if ( get_option( 'wptop_version' ) )
				self::uninstall();

			global $wpdb;

			$sql = 'CREATE TABLE %s (
	id INT UNSIGNED UNIQUE NOT NULL AUTO_INCREMENT,
	-- Whether data here has been processed or not
	status ENUM( "cooked", "raw" ) DEFAULT "raw",
	-- The total time of the request
	wall INT UNSIGNED,
	-- The total CPU time of the request
	cpu INT UNSIGNED,
	-- The peak memory of the request
	memory INT UNSIGNED,
	-- Number of function calls
	calls INT UNSIGNED,
	-- Top functions
	top LONGTEXT,
	-- The timestamp of the request
	timestamp INT UNSIGNED,
	-- The request URL
	url VARCHAR(255),
	-- The raw profile data
	raw LONGTEXT
) %s';
			$charset_collate = '';
			
			if ( !empty( $wpdb->charset ) ) $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			if ( !empty( $wpdb->collate ) ) $charset_collate .= " COLLATE {$wpdb->collate}";

			$wpdb->query( sprintf( $sql, $wpdb->prefix . self::$table, $charset_collate ) );
			update_option( 'wptop_version', self::$version );

			/** Configuration defaults */
			update_option( 'wptop_configuration', array(
				'filter' => array( 'dashboard' => true, 'cron' => true, 'ajax' => true ),
				'limit' => array( 'entries' => 5000 ),
				'interval' => 3600,
			) );

			/** Schedule some cooking */
			wp_schedule_single_event( time() + 3600, implode( '::', array( __CLASS__, 'cook' ) ) );

			/** We'll ignore this run when disabling the profiler */
			define( 'WPTOP_INSTALLING', true );
		}

		/**
		 * Uninstall this all.
		 *
		 * Say goodbye to your data and whatnot.
		 */
		public static function uninstall() {
			delete_option( 'wptop_version' );
			delete_option( 'wptop_configuration' );
			delete_option( 'wptop_cache' );

			global $wpdb;
			$wpdb->query( sprintf( 'DROP TABLE %s', $wpdb->prefix . self::$table ) );
		}

		/**
		 * Configuration save
		 */
		public static function save_configuration() {
			if ( empty( $_POST ) ) return;
			if ( !is_admin() ) return;
			if ( !isset( $_GET['page'] ) || $_GET['page'] != 'wptop' ) return;
			if ( !current_user_can( 'manage_options' ) ) return;
			check_admin_referer( 'wptop_configuration', 'wptop_configuration_nonce' );

			if ( isset( $_POST['limit']['reset'] ) ) {
				/** Clear the database */
				global $wpdb;
				$wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->prefix . self::$table ) );
				delete_option( 'wptop_cache' );
				$cleared = true;
			}

			$configuration = array();
			$configuration['filter'] = array(
				'dashboard' => isset( $_POST['filter']['dashboard'] ),
				'ajax' => isset( $_POST['filter']['ajax'] ),
				'cron' => isset( $_POST['filter']['cron'] ),
			);
			$configuration['limit'] = array(
				'entries' => ( is_numeric( $_POST['limit']['entries'] ) && $_POST['limit']['entries'] >= 0 ) ? $_POST['limit']['entries'] : 5000,
			);
			$configuration['interval'] = ( is_numeric( $_POST['interval'] ) && $_POST['interval'] > 0 ) ? $_POST['interval'] : 3600;
			update_option( 'wptop_configuration', $configuration );

			wp_clear_scheduled_hook( implode( '::', array( __CLASS__, 'cook' ) ) );
			wp_schedule_single_event( time() + $configuration['interval'], implode( '::', array( __CLASS__, 'cook' ) ) );

			add_action( 'admin_notices', function() {
				?>
					<div class="updated"><p>Configuration saved</p></div>
				<?php
				if ( isset( $_POST['limit']['reset'] ) ) {
					?><div class="updated"><p>Performance data reset</p></div><?php
				}
			} );
		}

		/**
		 * In some cicumstances we can simply discard the profile
		 * as early as we can.
		 */
		public static function maybe_discard() {
			$configuration = get_option( 'wptop_configuration' );	

			if ( ( is_admin() && !$configuration['filter']['dashboard'] ) or
				( defined( 'DOING_CRON' ) && !$configuration['filter']['cron'] ) or
				( defined( 'DOING_AJAX' ) && !$configuration['filter']['ajax'] )
			) define( 'WPTOP_DISCARDED', true );

			if ( defined( 'WPTOP_DISCARDED' ) ) self::disable();
		}

		/**
		 * Disable the profiler and save the performance data.
		 */
		public static function disable() {
			if ( !defined( 'WPTOP_ENABLED' ) || !function_exists( 'xhprof_disable' ) || defined( 'WPTOP_DISCARDED' ) )
				return;

			$xhprof_data = xhprof_disable();

			if ( !get_option( 'wptop_version' ) || defined( 'WPTOP_INSTALLING' ) )
				return;

			/** We freeze the data as is. */
			global $wpdb;
			$wpdb->insert( $wpdb->prefix . self::$table, array(
				'status' => 'raw',
				'timestamp' => time(),

				'wall' => $xhprof_data['main()']['wt'],
				'cpu' => isset( $xhprof_data['main()']['cpu'] ) ? $xhprof_data['main()']['cpu'] : null,
				'memory' => isset( $xhprof_data['main()']['pmu'] ) ? $xhprof_data['main()']['pmu'] : null,

				'url' => $_SERVER['REQUEST_URI'],
				'raw' => serialize( $xhprof_data )
			) );
		}
	}

	/** Let's go! */
	if ( defined( 'WPINC' ) ) wptop::bootstrap();
