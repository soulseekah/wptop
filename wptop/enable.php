<?php
	/** Enable the XHProf code profiler */

	/**
	 * The main profiler enable function.
	 *
	 * Needs to be called early on from pretty much any context as it does
	 * not depend on WordPress to be available.
	 */
	function wptop_enable( $attrs = array() ) {
		if ( !isset( $attrs['memory'] ) ) $attrs['memory'] = true;
		if ( !isset( $attrs['cpu'] ) ) $attrs['cpu'] = true;
		if ( !isset( $attrs['builtins'] ) ) $attrs['builtins'] = true;

		/** Ooh, no xhprof? Too bad... */
		if ( !function_exists( 'xhprof_enable' ) ) return;

		$flags = $attrs['memory'] ? XHPROF_FLAGS_MEMORY : 0;
		$flags |= $attrs['cpu'] ? XHPROF_FLAGS_CPU : 0;
		$flags |= $attrs['builtins'] ? XHPROF_FLAGS_NO_BUILTINS : 0;

		xhprof_enable( $flags );

		/** Global state of the profiler */
		define( 'WPTOP_ENABLED', serialize( $attrs ) );
	}
