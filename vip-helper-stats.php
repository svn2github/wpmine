<?php
/*
 * NOTE: This feature is currently still in testing phase
 */
 
/*
 *	VIP Helper Functions for Statistics that are specific to WordPress.com
 *
 * vip_get_stats_csv() and vip_get_stats_xml() are output compatible to
 * stats_get_csv() provided by http://wordpress.org/extend/plugins/stats/ 
 *
 * To add these functions to your theme add
include(ABSPATH . 'wp-content/themes/vip/plugins/vip-helper-stats.php');
 * in the theme's 'functions.php'. This should be wrapped in a 
if ( function_exists('wpcom_is_vip') ) { // WPCOM specific
 * so you don't load it in your local environment. This will help alert you if
 * have any unconditional dependencies on the WordPress.com environment.
 */

/*
 * Return stats as array
 * @param string $table table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param integer $num_days The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and possibility to refine the query with additional AND condition. usually unused
 * @param integer $limit The maximum number of records to return. Default is 5. Maximum 100.
 * @param boolean $summarize If present, summarizes all matching records.
 * @return array Result as array.
 * @author tott
 */
function vip_get_stats_array( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
	global $wpdb;
	$cache_id = md5( 'array' . $wpdb->blogid . $table . $end_date . $num_days . $end . $limit . $summarize );
	$arr = wp_cache_get( $cache_id, 'vip_stats' );
	if ( !$arr ) {
		$stat_result = _vip_get_stats_result( $table, $end_date, $num_days, $and, $limit );
		$arr = vip_stats_csv_print( $stat_result, $table, $limit, $summarize, true );
		wp_cache_set( $cache_id, $arr, 'vip_stats', 600 );
	}
	return $arr;
}

/*
 * Return stats as csv 
 * @param string $table table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param integer $num_days The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and possibility to refine the query with additional AND condition. usually unused
 * @param integer $limit The maximum number of records to return. Default is 5. Maximum 100.
 * @param boolean $summarize If present, summarizes all matching records.
 * @return string Result format is csv with one row per line and column names in first row.
 * Strings containing double quotes, commas, or "\n" are enclosed in double-quotes. Double-qoutes in strings are escaped by inserting another double-quote.
 * Example: "pet food" recipe
 * Becomes: """pet food"" recipe"
 * @author tott
 */
function vip_get_stats_csv( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
	global $wpdb;
	$cache_id = md5( 'csv' . $wpdb->blogid . $table . $end_date . $num_days . $end . $limit . $summarize );
	$csv = wp_cache_get( $cache_id, 'vip_stats' );
	if ( !$csv ) {
		$stat_result = _vip_get_stats_result( $table, $end_date, $num_days, $and, $limit );
		$csv = vip_stats_csv_print( $stat_result, $table, $limit, $summarize );
		wp_cache_set( $cache_id, $csv, 'vip_stats', 600 );
	}
	return $csv;
}

/*
 * Return stats as xml
 * @param string $table table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param integer $num_days The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and possibility to refine the query with additional AND condition. usually unused
 * @param integer $limit The maximum number of records to return. Default is 5. Maximum 100.
 * @param boolean $summarize If present, summarizes all matching records.
 * @return string Result format is xml dataset
 * @author tott
 */
function vip_get_stats_xml( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
	global $wpdb;
	$cache_id = md5( 'xml' . $wpdb->blogid . $table . $end_date . $num_days . $end . $limit . $summarize );
	$xml = wp_cache_get( $cache_id, 'vip_stats' );
	if ( !$xml ) {
		$stat_result = _vip_get_stats_result( $table, $end_date, $num_days, $and, $limit );
		$xml = vip_stats_xml_print( $stat_result, $table, $limit, $summarize );
		wp_cache_set( $cache_id, $xml, 'vip_stats', 600 );
	}
	return $xml;
}

/*
 * ONLY INTERNAL FUNCTIONS FROM HERE ON, USE ONLY vip_get_stats_csv() and vip_get_stats_xml()
 */
 
function vip_csv_expand_post( $post ) {
	return array( $post->ID, $post->post_title, $post->post_permalink ? $post->post_permalink : global_permalink( $GLOBALS['blog_id'], $post->ID ) );
}

function vip_csv_quote( $v ) {
	if ( is_array( $v ) )
		return join(',', array_map( 'vip_csv_quote', $v ));
	if ( strstr( $v, '"' ) || strstr( $v, ',' ) || strstr( $v, "\n" ) )
		return '"' . str_replace( '"', '""', $v ) . '"';
	return "$v";
}

function vip_stats_csv_print( $rows, $table, $limit, $summarize = NULL, $return_array = false ) {
	if ( empty( $rows ) )
		return "Error: zero rows returned.";
		
	$result = '';
	
	switch ( $table ) {
	
		case 'views' :
			if ( !is_null( $summarize ) )
				$_rows = array( array( 'date' => '-', 'views' => array_sum( array_map( create_function( '$row', 'return $row["views"];' ), $rows ) ) ) );
			else
				$_rows =& $rows;
				
			array_unshift( $_rows, array( 'date', 'views' ) );
			break;
			
		case 'postviews' :
			$posts = array();
			if ( isset( $GLOBALS['post_id'] ) && $GLOBALS['post_id'] ) {
				$_rows = array( array( 'date', 'views' ) );
				foreach ( $rows as $date => $array )
					$_rows[] = array( $date, $array[$GLOBALS['post_id']] );
				break;
			}
			$_rows = array( array( 'date', 'post_id', 'post_title', 'post_permalink', 'views' ) );
			foreach ( $rows as $date => $day_rows ) {
				foreach ( $day_rows as $k => $v ) {
					if ( $k < 1 )
						continue;
					$posts[$k] = true;
					$_rows[] = array( $date, &$posts[$k], $v );
				}
			}
			foreach ( stats_get_posts( array_keys( $posts ), $GLOBALS['blog_id'] ) as $id => $post )
				$posts[$id] = vip_csv_expand_post( $post );
			break;
		default :
			$_rows = array( array( 'date', rtrim( $table, 's' ), 'views' ) );
			foreach ( $rows as $date => $day_rows )
				foreach ( $day_rows as $k => $v )
					if ( $k !== $v )
						$_rows[] = array( $date, $k, $v );
	}

	if ( $limit > 0 && count( $_rows ) > $limit + 1 )
		$_rows = array_slice( $_rows, 0, $limit + 1 );

	if ( true === $return_array ) {
		$mapping = array_shift( $_rows );
		$out = array();
		foreach( $_rows as $key => $values ) {
			$out[] = array( 'date' => $values[0], 'post_id' => $values[1][0], 'post_title' => $values[1][1], 'post_permalink' => $values[1][2], 'views' => $values[2] );
		}
		return $out;
	}
	
	foreach ( $_rows as $row ) {
		// Remove date col from summarized data
		if ( !is_null( $summarize ) )
			array_shift($row);

		$row = array_map( 'vip_csv_quote', $row );

		$result .= join( ',', $row ) . "\n";
	}

	return $result;
}

function vip_stats_xml_print( $rows, $table, $limit, $summarize = NULL ) {
	if ( empty( $rows ) )
		return "Error: zero rows returned.";

	$return = '';
	
	switch ( $table ) {
		case 'views' :
			if ( is_null( $summarize ) ) {
				$count = 0;
				foreach ( $rows as $row ) {
					$count++;
					if ( 0 < $limit && $count > $limit )
						break;
					$return .= "\t" . '<day date="' . attribute_escape( $row['date'] ) . '">' . (int) $row['views'] . '</day>' . "\n";
				}
			}
			$return .= "\t" . '<total>' . (int) array_sum( array_map( create_function( '$row', 'return $row["views"];' ), $rows ) ) . '</total>' . "\n";
			break;
		case 'postviews' :
			if ( isset( $GLOBALS['post_id'] ) && $GLOBALS['post_id'] ) {
				if ( is_null( $summarize ) ) {
					$count = 0;
					foreach ( $rows as $date => $row ) {
						$count++;
						if ( 0 < $limit && $count > $limit )
							break;
						$return .= "\t" . '<day date="' . attribute_escape( $date ) . '">' . (int) $row[$GLOBALS['post_id']] . '</day>' . "\n";
					}
				}
				$return .= "\t" . '<total>' . (int) array_sum( array_map( create_function( '$row', 'return $row[$GLOBALS[\'post_id\']];' ), $rows ) ) . '</total>' . "\n";
				break;
			}

			$post_ids = array();
			foreach ( $rows as $day_rows )
				foreach ( $day_rows as $k => $v )
					if ( 0 < $k )
						$post_ids[] = $k;

			foreach ( stats_get_posts( $post_ids, $GLOBALS['blog_id'] ) as $id => $post ) 
				$posts[$id] = vip_csv_expand_post( $post );

			foreach ( $rows as $date => $day_rows ) {
				if ( is_null( $summarize ) )
					$return .= "\t" . '<day date="' . $date . '">' . "\n";
				foreach ( $day_rows as $k => $v ) {
					if ( $k < 1 )
						continue;
					$return .= "\t\t" . '<post id="' . attribute_escape( $k ) . '" title="' . attribute_escape( $posts[$k][1] ) . '" url="' . attribute_escape( $posts[$k][2] ) . '">' . (int) $v . '</post>' . "\n";
				}
				if ( !is_null( $summarize ) )
					$return .= "\t" . '</day>' . "\n";
			}
			break;
		default :
			$_rows = array( array( 'date', rtrim($table, 's'), 'views' ) );
			foreach ( $rows as $date => $day_rows ) {
				if ( is_null( $summarize ) )
					$return .= "\t" . '<day date="' . $date . '">' . "\n";
				foreach ( $day_rows as $k => $v )
					if ( $k !== $v )
						$return .= "\t\t" . '<' . rtrim( $table, 's' ) . ' value="' . attribute_escape( $k ) . '" count="' . $count . '" limit="' . $limit . '">' . (int) $v . '</' . rtrim( $table, 's' ) . '>' . "\n";
				if ( is_null( $summarize ) )
					$return .= "\t" . '</day>' . "\n";
			}
	}

	$return .= '</' . $table . '>' . "\n";
	return $return;
}

function _vip_get_stats_result( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 400 ) {
	global $post_id, $wpdb;
	$blog_id = $wpdb->blogid;
	
	// adjust parameters
	if ( ! in_array( $table, array( 'views', 'postviews', 'referrers', 'searchterms', 'clicks' ) ) )
		$table = 'views';
	
	if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end ) )
		$end = $GLOBAL['today'];
	
	$format = strtolower( $format );
	if ( ! in_array( $format, array( 'csv', 'xml' ) ) )
		$format = 'csv';
	
	if ( $limit > 100 )
		$limit = 100;
	else 
		$limit = (int) $limit;
	
	if ( $num_days > 90 )
		$num_days = 90;
	else
		$num_days = (int) $num_days;
	
	
	if ( $table == 'postviews' && !empty($post_id) )
		$and = "AND post_id = $post_id";

	$args = array( $blog_id, $end, $days, $and, $limit );

	$result = array();

	if ( is_callable( "stats_get_$table" ) ) {
		$result = call_user_func_array( "stats_get_$table", $args );
	}
	
	return $result;
}

