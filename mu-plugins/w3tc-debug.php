<?php
/**
 * Place this file in mu-plugins. This will replace the W3 Total Cache
 * Object Cache and insert an intermediate object which contains a stats()
 * method, which is used by the Debug Bar Extender to emit statistics for the
 * object cache.
 *
 * It won't catch any cache calls made between the initial load of W3TC
 * and the mu-plugins, nor will it catch any caching calls made after
 * stats() is called, but it will catch everything that matters.
 */
class Oomph_ObjectCacheBridge {
	static $w3cache;

	function __construct() {
		self::$w3cache = w3_instance('W3_ObjectCacheBridge');
	}

	function __call( $method, $args ) {
		return call_user_func_array( array( self::$w3cache, $method ), $args );
	}

	function _get_engine($group = 'default') {
		$cache = self::$w3cache;

		// Because _get_engine is private in W3_ObjectCacheBridge... which is lameo.
		if (!isset($cache->_caches['fragmentcache']))
			return $cache->_caches['objectcache'];

		switch($group) {
			case 'transient':
			case 'site-transient':
				return $cache->_caches['fragmentcache'];
			default:
				return $cache->_caches['objectcache'];
		}
	}

	function stats($group = 'default') {
		$cache = $this->_get_engine($group);

		// Aggregate debug statistics
		$key_stats = array();
		foreach( $cache->debug_info as $index => $debug ) {
			$key = $debug['id'] . ':' . $debug['group'];

			$key_stats[$key][] = $debug;
		}

		printf( '
		<ul>
			<li>Total cache calls: <strong>%d</strong></li>
			<li>Cache hits: <strong>%d</strong></li>
			<li>Cache misses: <strong>%d</strong></li>
			<li>Total time: <strong>%.04f</strong></li>
		</ul>
		', $cache->cache_total, $cache->cache_hits, $cache->cache_misses, $cache->time_total
		);

		echo '
		<style>
		.cache-info-table {
			border-collapse: collapse;
		}
		.cache-info-table .head-row td {
			border-top: 1px solid #999;
		}
		.cache-info-table td,
		.cache-info-table th {
			width: 20%;
			vertical-align: top;
			white-space: nowrap;
		}
		</style>
		<table class="cache-info-table">
			<tr>
				<th>Key</th><th>Status</th><th>Source</th><th>Data Size (b)</th><th>Time (s)</th>
			</tr>';

		foreach( $key_stats as $key => $stats ) {
			$rows = array();

			// Count the number of internal references (after each persistent cache)
			$internal = 0;

			foreach( $stats as $i => $stat ) {
				if( $stat['internal'] ) {
					$internal++;
				}

				$row = sprintf( '%s<td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
					$i > 0 ? '<tr>' : '',	// First row in group gets <tr> from header row
					$stat['cached'] ? 'cached' : 'not cached',
					$internal > 0 ? 'internal (' . $internal . ')' : 'persistent',
					$stat['data_size'],
					round($stat['time'], 4)
				);

				if( $stat['internal'] ) {
					$internal_row = $row;
				}

				// Save the last "internal row" count to display before the next "persistent" row
				else {
					if( isset( $internal_row ) ) {
						$rows[] = $internal_row;
					}

					$internal = 0;
					$rows[] = $row;
					unset( $internal_row );
				}
			}

			// Display any internal row aggregates at the end
			if( isset( $internal_row ) ) {
				$rows[] = $internal_row;
			}

			echo '
			<tr class="head-row">
				<td rowspan="' . count( $rows ) . '"><strong>' . $key . '</strong></td>' .
				implode( "\n\t\t\t", $rows );

			unset( $internal_row );
		}

		echo '
		</table>';

		// Clear out debug info so it doesn't appear twice
		$cache->debug_info = array();
	}
}

$GLOBALS['wp_object_cache'] = new Oomph_ObjectCacheBridge();
