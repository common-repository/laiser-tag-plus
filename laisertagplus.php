<?php
/*
Plugin Name: Laiser Tag Plus
Plugin URI: http://www.pcis.com/laiser-tag
Description: Advanced tagging service which suggests tags and Flickr photos for your posts
Version: 1.0.3
Author: PCIS
Author URI: http://www.pcis.com/laiser-tag
License: GPL2
*/

define( 'LTPOC_WP_GTE_23', version_compare( $wp_version, '2.3', '>=' ) );
define( 'LTPOC_WP_GTE_25', version_compare( $wp_version, '2.5', '>=' ) );
define( 'LTPOC_WP_GTE_26', version_compare( $wp_version, '2.6', '>=' ) );
define( 'LTPOC_WP_GTE_27', version_compare( $wp_version, '2.7', '>=' ) );
define( 'LTPOC_WP_GTE_28', version_compare( $wp_version, '2.8', '>=' ) );
define( 'LTPOC_WP_GTE_33', version_compare( $wp_version, '3.3', '>=' ) );

define( 'LTPOC_DRAFT_API_KEY', 'mdbtyu4ku286uhpakuj48dgj' );
define( 'LTPOC_FLICKR_API_KEY', 'f3745df3c6537073c523dc6d06751250' );

define( 'LTPOC_HTTP_PATH', plugin_dir_url( __FILE__ ) );
define( 'LTPOC_FILE_PATH', plugin_dir_path( __FILE__ ) );

 load_plugin_textdomain( 'laisertagplus', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

function ltpoc_agent_is_safari() {
	static $is_safari;
	if ( ! isset( $is_safari ) ) {
		$matches = array();
		$is_safari = preg_match( '/Safari/', $_SERVER['HTTP_USER_AGENT'], $matches );
	}
	return $is_safari;
}

$ltpoc_key_entered = false;
$ltpoc_api_key = get_option( 'ltpoc_api_key' );
if ( $ltpoc_api_key && ! empty( $ltpoc_api_key ) ) {
	$ltpoc_key_entered = true;
}

if ( ! $ltpoc_relevance_minimum = get_option( 'ltpoc_relevance_minimum' ) ) {
	$ltpoc_relevance_minimum = 'any';
}

if ( ! $ltpoc_auto_fetch = get_option( 'ltpoc_auto_fetch' ) ) {
	$ltpoc_auto_fetch = 'yes';
}

if ( ! $ltpoc_key_entered ) {
	add_action( 'admin_notices', 'ltpoc_warn_no_key_edit_page' );
	add_action( 'after_plugin_row', 'ltpoc_warn_no_key_plugin_page' );
}

function ltpoc_warn_no_key_plugin_page( $plugin_file ) {
	if ( strpos( $plugin_file, 'laisertagplus.php' ) ) {
		echo "<tr><td colspan='5' class='plugin-update'>";
		echo '<strong>Note</strong>: Laiser Tag Plus requires an API key to work. <a href="options-general.php?page=laisertagplus.php">Set your API Key</a>.';
		echo "</td></tr>";
	}
}

function ltpoc_warn_no_key_edit_page() {
	if ( ltpoc_on_edit_page() ) {
		echo '<div class="error" style="padding:5px;"><strong>Note</strong>: Laiser Tag Plus is active but you have not <a href="options-general.php?page=laisertagplus.php">set your API Key</a>.</div>';
	}
}

function ltpoc_on_edit_page() {
	global $pagenow;
	return ( $pagenow == 'post-new.php' ) || ( $pagenow == 'post.php' ) || ( $pagenow == 'tiny_mce_config.php' );
}

function ltpoc_api_param_xml( $req_id = null, $metadata = '', $allow_distribution = false, $allow_search = false ) {
	if ( ! $req_id ) {
		$req_id = 'draft-' . time();
	}

	$submitter = home_url();
	return '
		<c:params xmlns:c="http://s.opencalais.com/1/pred/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
			<c:processingDirectives c:contentType="text/html" c:outputFormat="xml/rdf" c:enableMetadataType="SocialTags"></c:processingDirectives>
			<c:userDirectives c:allowDistribution="' . ( $allow_distribution ? 'true' : 'false') . '" c:allowSearch="' . ( $allow_search ? 'true' : 'false' ) . '" c:externalID="' . $req_id . '" c:submitter="' . $submitter . '">
			</c:userDirectives>
			<c:externalMetadata>
				' . $metadata . '
				<rdf:description><c:caller>Logic Monitor Plus</c:caller></rdf:description>
			</c:externalMetadata>
		</c:params>
	';
}

define( 'LTPOC_DRAFT_CONTENT', 0 );
define( 'LTPOC_FINAL_CONTENT', 1 );

function ltpoc_ping_ltpoc_api( $content, $content_status = LTPOC_DRAFT_CONTENT, $paramsXML = null ) {
	global $ltpoc_api_key;
	if ( ! $paramsXML ) {
		$paramsXML = ltpoc_api_param_xml();
	}

//	if ($content_status == LTPOC_DRAFT_CONTENT) {
//		$key = LTPOC_DRAFT_API_KEY;
//	}
//	else {
	$key = $ltpoc_api_key;
//	}


	$done = false;
	$tries = 0;
	do {
		$tries++;
		$response = ltpoc_do_ping_ltpoc_api( $key, $content, $paramsXML );
		if ( isset( $response['errortype'] ) && $response['errortype'] == 3 && $tries <= 3 ) {
			continue;
		}
		$done = true;
	}
	while ( ! $done );

	return $response;
}

function ltpoc_format_content( $content ) {
	$block_tags = array(
		'address',
		'article',
		'aside',
		'blockquote',
		'canvas',
		'dd',
		'div',
		'dl',
		'fieldset',
		'figcaption',
		'figure',
		'footer',
		'form',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'header',
		'hgroup',
		'hr',
		'main',
		'nav',
		'output',
		'p',
		'pre',
		'section',
		'table',
		'tfoot',
		'ul',
		'video',
		'li',
		'br'
	);
	foreach ($block_tags as $block_tag) {
		$content = str_ireplace(array( '<'.$block_tag, $block_tag.'>', $block_tag.' />', $block_tag.'/>'), array(' <'.$block_tag, $block_tag.'> ', $block_tag.' /> ', $block_tag.' /> '), $content);
	}
	return strip_tags($content);
}

function ltpoc_do_ping_ltpoc_api( $key, $content, $paramsXML ) {
	if ( ! isset( $_POST['publish'] ) && ! isset( $_POST['save'] ) ) {
		$result = wp_remote_post( 'https://api.thomsonreuters.com:443/permid/calais', array(
			'headers' => array(
				'x-ag-access-token' => $key,
				'Content-Type' => 'text/xml',
				'outputFormat' => 'xml/rdf',
			),
			'body' => '<body>'.ltpoc_format_content($content).'</body>',
		) );

		if ( ! is_wp_error( $result ) && isset( $result['body'] ) && isset( $result['response']['code'] ) ) {

			// Requested xml/rdf, but errors come back as json encoded it appears as of Jun 17 2015
			$is_json = false;
			if ( isset( $result['headers']['content-type'] ) && 'application/json' == $result['headers']['content-type'] ) {
				$is_json = true;
			}

			$result_code = $result['response']['code'];
			$result_body = $is_json ? json_decode( $result['body'] ) : $result['body'];

			if ( isset( $result_body->fault ) ) {
				return array(
					'success' => false,
					'error' => $result_body->fault->faultstring,
					'errortype' => 1
				);
			}
			if ( 200 == $result_code ) {
				return array(
					'success' => true,
					'content' => $result['body'],
					'errortype' => 0
				);
			}
		}
		else {
			return array(
				'success' => false,
				'error' => 'Could not contact OpenCalais: -- " ' . print_r( $result, true ) . '"',
				'errortype' => 3
			);
		}
	}
}

function ltpoc_get_flickr_license_info() {
	$info = get_option( 'ltpoc_flickrLicenseInfo' );
	if ( ! $info ) {
		$result = wp_remote_post( 'https://api.flickr.com/services/rest', array(
			'body' => array(
				'method' => 'flickr.photos.licenses.getInfo',
				'api_key' => LTPOC_FLICKR_API_KEY,
				'format' => 'json',
				'nojsoncallback' => 1,
			),
		));
		if ( ! is_wp_error( $result ) && isset( $result['body'] ) ) {
			$info = $result['body'];
			update_option( 'ltpoc_flickrLicenseInfo', $info );
		}
	}
	return $info;
}

function ltpoc_ping_flickr_api( $data ) {

	$result = wp_remote_post( 'https://api.flickr.com/services/rest', array(
		'body' => array(
			'method' => 'flickr.photos.search',
			'api_key' => LTPOC_FLICKR_API_KEY,
			'tags' => $data['tags'],
			'license' => '1,2,3,4,5,6',
			'extras' => 'tags,license,owner_name',
			'per_page' => $data['per_page'],
			'page' => $data['page'],
			'sort' => $data['sort'],
			'format' => 'json',
			'nojsoncallback' => 1,
		),
	));

	if ( ! is_wp_error( $result ) && isset( $result['body'] ) ) {

		// Check for additional errors returned by the API.
		$json_body = json_decode( $result['body'] );
		if ( ( isset( $json_body->stat ) && 'ok' != $json_body->stat  )
			|| '200' != $result['response']['code'] ) {
			return array(
				'success' => false,
				'error' => isset( $json_body->message ) ? $json_body->message : 'An unknown error has occured',
			);
		}

		return array(
			'success' => true,
			'headers' => $result['headers'],
			'content' => $result['body']
		);
	}
	else {
		return array(
			'success' => false,
			'error' => 'Could not contact Flickr.'
		);
	}
}

function ltpoc_request_handler() {
	wp_enqueue_script( 'jquery' );

	if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
		// copied from wp 2.5
		if ( isset( $_GET['action'] ) && 'ajax-tag-search' == $_GET['action'] ) {
			global $wpdb;
			if ( ! current_user_can( 'manage_categories' ) ) {
				die( '-1' );
			}

			$s = $_GET['q']; // is this slashed already?

			if ( strstr($s, ',') ) {
				die;
			} // it's a multiple tag insert, we won't find anything
			$results = $wpdb->get_col( "SELECT name FROM $wpdb->terms WHERE name LIKE ('%$s%')" );
			echo join( $results, "\n" );
			die;
		}
	}
	if ( ! empty( $_POST['ltpoc_action'] ) ) {
		switch ( $_POST['ltpoc_action'] ) {
			case 'update_api_key':
				if ( current_user_can( 'manage_options' ) ) {
					$get_q = '';
					$key_changed = false;
					if ( isset( $_POST['ltpoc_api_key'] ) ) {
						global $ltpoc_api_key;
						if ( $_POST['ltpoc_api_key'] == '' ) {
							update_option( 'ltpoc_api_key', stripslashes( $_POST['ltpoc_api_key'] ) );
						}
						else {
							if ( $_POST['ltpoc_api_key'] != $ltpoc_api_key ) {
								$key_changed = true;
								$ltpoc_api_key = $_POST['ltpoc_api_key'];
								$test = ltpoc_ping_ltpoc_api( 'Wordpress Plugin API key test.', LTPOC_FINAL_CONTENT );
								if ($test['success']) {
									$success = update_option( 'ltpoc_api_key', stripslashes( $_POST['ltpoc_api_key'] ) );
									if ( ! $success ) {
										$get_q .= '&ltpoc_update_failed=true';
									}
								}
								else {
									if ( $test['error'] == 'API Key Invalid.' ) {
										$test['error'] = 'The API key ' . $ltpoc_api_key . ' does not appear to be valid.';
									}
									$get_q .= '&ltpoc_api_test_failed=' . urlencode( $test['error'] );
								}
							}
						}
					}

					$allow_search = ( isset( $_POST['ltpoc_privacy_searchable'] ) && $_POST['ltpoc_privacy_searchable'] == 'on' );
					$allow_dist = ( isset( $_POST['ltpoc_privacy_distribute'] ) && $_POST['ltpoc_privacy_distribute'] == 'on' );
					update_option('ltpoc_privacy_prefs', array(
						'allow_search' => ( $allow_search ? 'yes' : 'no' ),
						'allow_distribution' => ( $allow_dist ? 'yes' : 'no' )
					));

					if ( isset( $_POST['ltpoc_relevance_minimum'] ) ) {
						update_option( 'ltpoc_relevance_minimum', $_POST['ltpoc_relevance_minimum'] );
					}

					if ( isset( $_POST['ltpoc_auto_fetch'] ) && $_POST['ltpoc_auto_fetch'] == 'on' ) {
						update_option( 'ltpoc_auto_fetch', 'yes' );
					}
					else {
						update_option( 'ltpoc_auto_fetch', 'no' );
					}

                    if ( isset( $_POST['ltpoc_tag_types'] ) ) {
                        $post_tag_types = $_POST['ltpoc_tag_types'];
                        $tag_types = ltpoc_get_tag_types();
                        foreach ( $tag_types as $tag_type => $checked ) {
                            $sanitized_type = sanitize_title( $tag_type );
                            if ( isset( $post_tag_types[ $sanitized_type ] ) ) {
                                $tag_types[$tag_type] = 1;
                            }
                            else {
                                $tag_types[$tag_type] = 0;
                            }
                        }
                        update_option( 'ltpoc_tag_types', $tag_types );
                    }

					if ( $get_q == '' ) {
						$get_q .= '&updated=true' . ( $key_changed ? '&ltpoc_key_changed=true' : '' );
					}

					header( 'Location: ' . admin_url( 'options-general.php?page=laisertagplus.php' . $get_q ) );
					die();
				}
				else {
					wp_die( 'You are not allowed to manage options.' );
				}
				die();
			case 'api_proxy_oc':
				$result = ltpoc_ping_ltpoc_api( stripslashes( $_POST['text'] ) );
				if ( $result['success'] == false ) {
					header( 'Content-Type: text/html; charset=utf-8' );
					echo '__ltpoc_request_failed__{ error: \'' . addslashes( $result['error'] ) . '\'}';
				}
				else {
					header( 'Content-Type: text/xml; charset=utf-8' );
					echo $result['content'];
				}
				die();
			case 'api_proxy_flickr':
				$result = ltpoc_ping_flickr_api( $_POST );
				if ( $result['success'] == false ) {
					header( 'Content-Type: text/html; charset=utf-8' );
					echo '__ltpoc_request_failed__{ error: \'' . addslashes( $result['error'] ) . '\'}';
				}
				else {
					if ( isset( $result['headers'] ) && gettype( $result['headers'] ) == 'array' ) {
						foreach ( $result['headers'] as $header_key => $header_value ) {
							// Yahoo API returns gziped encoding but data is not encoded as such
							if ( 'content-encoding' == $header_key ) {
								continue;
							}
							header( $header_key . ': ' . $header_value );
						}
					}
					echo $result['content'];
				}
				die();
		}
	}
	if ( ! empty( $_GET['ltpoc_action'] ) ) {
		switch ( $_GET['ltpoc_action'] ) {
			case 'admin_js':
				global $ltpoc_config, $ltpoc_relevance_minimum, $ltpoc_auto_fetch;
				header( "Content-type: text/javascript" );
				require( LTPOC_FILE_PATH . '/js/cf/offset.js' );
				if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
					require( LTPOC_FILE_PATH . '/js/suggest.js' );
				}
				require( LTPOC_FILE_PATH . '/js/cf/CFCore.js' );
				require( LTPOC_FILE_PATH . '/js/OCCore.js' );
				print( 'ltpoc.wp_gte_28 = ' . ( LTPOC_WP_GTE_28 ? 'true' : 'false' ) . ';' );
				print( 'ltpoc.wp_gte_27 = ' . ( LTPOC_WP_GTE_27 ? 'true' : 'false' ) . ';' );
				print( 'ltpoc.wp_gte_25 = ' . ( LTPOC_WP_GTE_25 ? 'true' : 'false' ) . ';' );
				print( 'ltpoc.wp_gte_23 = ' . ( LTPOC_WP_GTE_23 ? 'true' : 'false' ) . ';' );
				print( 'ltpoc.minimumRelevance = \'' . $ltpoc_relevance_minimum . '\';' );
				print( 'ltpoc.autoFetch = ' . ( $ltpoc_auto_fetch == 'yes' ? 'true' : 'false') . ';' );
				require( LTPOC_FILE_PATH . '/js/xmlObjectifier.js' );
				require( LTPOC_FILE_PATH . '/js/json2.js' );
				require( LTPOC_FILE_PATH . '/js/cf/CFTokenManager.js' );
				require( LTPOC_FILE_PATH . '/js/cf/CFTokenBox.js' );
				require( LTPOC_FILE_PATH . '/js/cf/CFToken.js' );
				require( LTPOC_FILE_PATH . '/js/cf/CFTextToken.js' );
				require( LTPOC_FILE_PATH . '/js/OCTagSource.js' );
				require( LTPOC_FILE_PATH . '/js/OCEventFact.js' );
				require( LTPOC_FILE_PATH . '/js/OCEntity.js' );
				require( LTPOC_FILE_PATH . '/js/OCDocCat.js' );
				require( LTPOC_FILE_PATH . '/js/OCSocialTag.js' );
				require( LTPOC_FILE_PATH . '/js/OCArtifactManager.js' );
				require( LTPOC_FILE_PATH . '/js/OCArtifactType.js' );
				require( LTPOC_FILE_PATH . '/js/OCTag.js' );
				require( LTPOC_FILE_PATH . '/js/OCTagManager.js' );
				require( LTPOC_FILE_PATH . '/js/OCTagToken.js' );
				require( LTPOC_FILE_PATH . '/js/OCTagBox.js' );
				require( LTPOC_FILE_PATH . '/js/OCImage.js' );
				require( LTPOC_FILE_PATH . '/js/OCImageManager.js' );
				require( LTPOC_FILE_PATH . '/js/OCImageToken.js' );
				require( LTPOC_FILE_PATH . '/js/OCImageParadeBox.js' );

				$licensesJSON = ltpoc_get_flickr_license_info();
				if ( $licensesJSON ) {
					print( 'ltpoc.imageManager.flickrLicenseInfo = ' . $licensesJSON . ';' );
				}

                // Not the ideal way to do this, but using the same pattern from above.
                $tag_types = ltpoc_get_tag_types();
                if ( $tag_types ) {
                    print( 'ltpoc.allowedTagTypes = ' . json_encode( $tag_types ) . ';' );
                }

				if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
					require( LTPOC_FILE_PATH . '/js/mce/mce2/editor_plugin.js' );
				}
				require( LTPOC_FILE_PATH . '/js/admin-edit.js' );
				die();
			case 'admin_css':
				header( "Content-type: text/css" );
				print( ltpoc_get_css( 'admin' ) );
				ob_start();
				require( LTPOC_FILE_PATH . '/css/admin-edit.css' );
				require( LTPOC_FILE_PATH . '/css/token-styles.css');
				$css = ob_get_contents();
				ob_end_clean();
				$css = str_replace( 'CALAISPLUGIN', LTPOC_HTTP_PATH, $css );
				print( $css );
				if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
					require( LTPOC_FILE_PATH . '/css/admin-edit-wp23.css' );
				}
				if ( ltpoc_agent_is_safari() ) {
					print( '
						.right_textTokenButton {
							top: 2px;
						}
					' );
				}
				die();
			case 'rte_css':
				header( "Content-type: text/css" );
				print( ltpoc_get_css( 'rte' ) );
				die();
			case 'published_css':
				header( "Content-type: text/css" );
				print( ltpoc_get_css( 'published' ) );
				die();
		}
	}
}
add_action('init', 'ltpoc_request_handler', 10);

function ltpoc_get_control_wrapper($which, $id = '', $title = '') {
	$wrapper = array();
	if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
		$wrapper['head'] = '
			<div class="dbx-b-ox-wrapper">
				<fieldset id="' . $id . '_fieldset" class="dbx-box">
					<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">' . $title . '</h3></div>
					<div class="dbx-c-ontent-wrapper">
						<div class="dbx-content">
							<div id="' . $id . '">
		';
		$wrapper['foot'] = '
							</div>
						</div>
					</div>
				</fieldset>
			</div>
		';
	}
	else {
		if ( LTPOC_WP_GTE_25 && ! LTPOC_WP_GTE_27 ) {
			$wrapper['head'] = '
			<div id="' . $id . '" class="postbox">
				<h3>' . $title . '</h3>
				<div class="inside">
		';
			$wrapper['foot'] = '
				</div>
			</div>
		';
		}
		else {
			if ( LTPOC_WP_GTE_27 ) {
				// handled via add_meta_box
				return '';
			}
		}
	}
	return $wrapper[ $which ];
}

function ltpoc_render_tag_controls() {
	global $ltpoc_config;
	global $post;
	$status_in_controls = ( LTPOC_WP_GTE_27 ? '
		<div class="ltpoc_status" id="ltpoc_status">
			<div id="ltpoc_tag_searching_indicator">Finding tags&hellip;</div>
			<a href="#" id="ltpoc_suggest_tags_link">Suggest Tags</a>
		</div>
	' : '' );
	$status_in_header = ( LTPOC_WP_GTE_27 ? '' : '
		<div id="ltpoc_tag_searching_indicator">Finding tags&hellip;</div>
		<a href="#" id="ltpoc_suggest_tags_link">Suggest Tags</a>
	' );
	$meta = get_post_meta( $post->ID, 'ltpoc_metadata', true );
	$tag_meta_data = get_post_meta( $post->ID, 'ltpoc_tag_data', true );
	print( '
		' . ltpoc_get_control_wrapper( 'head', 'ltpoc_tag_controls', 'Laiser Tag Plus Tags' . $status_in_header ) . '
				<div class="ltpoc_tag_notification" id="ltpoc_api_notifications"></div>
				' . $status_in_controls . '
				<textarea id="ltpoc_metadata" type="hidden" name="ltpoc_metadata">' . $meta . '</textarea>
				<textarea id="ltpoc_tag_data" type="hidden" name="ltpoc_tag_data">' . $tag_meta_data . '</textarea>
				<input id="newtag" type="hidden" value=""/>

				<div id="ltpoc_suggested_tags_wrapper">
					<div class="clear"></div>
				</div>

				<div id="ltpoc_current_tags_wrapper">
					<div id="ltpoc_add_tag_form">
						<label for="ltpoc_add_tag_field">Add your own tags:</label>
						<input type="text" id="ltpoc_add_tag_field" autocomplete="off" />
						<input type="button" class="button" id="ltpoc_add_tag_button" value="Add" />
						<div class="ltpoc_tag_notification" id="ltpoc_current_tag_notifications">&nbsp;</div>
					</div>
					<div class="clear"></div>
				</div>
				<div class="clear"></div>
		' . ltpoc_get_control_wrapper( 'foot' ) . '
	');
}

function ltpoc_render_image_controls() {
	$options = '
		<option label="Interestingness" value="interestingness" selected="selected">Interestingness</option>
		<option label="Date Posted" value="date-posted">Date Posted</option>
		<option label="Date Taken" value="date-taken">Date Taken</option>
	';
	print('
			' . ltpoc_get_control_wrapper( 'head', 'ltpoc_image_controls', 'Laiser Tag Plus Images' ) . '
				<div id="ltpoc_filmstrip_wrapper"></div>
				<div id="ltpoc_images_page_back" class="disabled"></div>
				<div id="ltpoc_images_page_fwd" class="disabled"></div>
				<div id="ltpoc_images_tools">
					<span id="ltpoc_images_page_num"></span><br />
					<a href="#" id="ltpoc_images_sort_toggle">Sorting Options</a>
				</div>
				<div id="ltpoc_images_result_tags"></div>
				<div class="clear"></div>
				<div id="ltpoc_images_sort">
					<label for="ltpoc_images_sort_select">Sort by:</label>
					<select id="ltpoc_images_sort_select">
						' . $options . '
					</select>
					<label>Sort Order:</label>
					<input id="ltpoc_sort_direction_asc" type="radio" name="ltpoc_sort_direction" value="asc"/>
					<label for="ltpoc_sort_direction_asc">Ascending</label>
					<input id="ltpoc_sort_direction_desc" type="radio" name="ltpoc_sort_direction" value="desc" checked="checked"/>
					<label for="ltpoc_sort_direction_desc">Descending</label>
				</div>
				<div class="clear"></div>
				<div id="ltpoc_image_preview"></div>
			' . ltpoc_get_control_wrapper( 'foot' ) . '
	');
}



function ltpoc_open_dbx_group() {
	print( '<div class="dbx-group" id="oc-dbx">' );
}

function ltpoc_close_dbx_group() {
	print( '</div>' );
}

if ( $ltpoc_key_entered ) {
	if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
		add_action( 'edit_form_advanced', 'ltpoc_open_dbx_group' );
	}

	if ( ! LTPOC_WP_GTE_27 ) {
		add_action( 'edit_form_advanced', 'ltpoc_render_image_controls' );
		add_action( 'edit_form_advanced', 'ltpoc_render_tag_controls' );
	}
	else {
		// use the meta_box
	}

	if ( LTPOC_WP_GTE_23 && ! LTPOC_WP_GTE_25 ) {
		add_action( 'edit_form_advanced', 'ltpoc_close_dbx_group' );
	}
}

function ltpoc_get_css( $which ) {
	switch ( $which ) {
		case 'published':
			return '
			';
		case 'admin':
			print( '
#ltpoc_preview_loading {
	position:absolute;
	background:url(' . LTPOC_HTTP_PATH . '/images/loading-black.gif) 0 50% no-repeat;
	width:16px;
	height:16px;
}
#ltpoc_image_searching {
	background:url(' . LTPOC_HTTP_PATH . '/images/loading-white.gif) 0 50% no-repeat;
	padding:8px 28px;
}
#ltpoc_tag_searching_indicator,
#ltpoc_suggest_tags_link {
	position:absolute;
	top: ' . ( LTPOC_WP_GTE_33 ? '11px' : ( LTPOC_WP_GTE_27 ? '4px' : '7px' ) ) . ';
	height:16px;
	display:none;
	text-align: right;
	font-size: 11px;
	font-weight: normal;
}
#ltpoc_tag_searching_indicator {
	background:url(' . LTPOC_HTTP_PATH . '/images/' . ( LTPOC_WP_GTE_27 ? 'loading-trans.gif' : 'loading.gif' ) . ') center right no-repeat;
	width: 200px;
	right: ' . ( LTPOC_WP_GTE_33 ? '11px' : '6px' ) . ';
	padding: 3px 25px 0 0;
	color: #909090;
	line - height:12px;
}
#ltpoc_suggest_tags_link {
	width:100px;
	right: ' . ( LTPOC_WP_GTE_33 ? '11px' : '6px' ) . ';
	top: ' . ( LTPOC_WP_GTE_33 ? '11px' : ( LTPOC_WP_GTE_27 ? '3px' : '6px' ) ) . ';
	padding: 1px 8px 1px 2px;
	line - height:15px;
	background: white url(' . LTPOC_HTTP_PATH . '/images/Calais-icon_16x16.jpg) 3px 50 % no-repeat;
	border:1px solid #bbb;
	text-decoration:none;
}
#ltpoc_suggest_tags_link a,
#ltpoc_suggest_tags_link a:visited {
	color: #21759B;
}
#ltpoc_suggest_tags_link a:hover {
	color:#F6880C;
}
#ltpoc_close_preview_button {
	position: absolute;
	background: url(' . LTPOC_HTTP_PATH . '/images/close-dark.gif) no-repeat;
	height: 16px;
	width: 16px;
	top: 7px;
	right: 5px;
	cursor: pointer;
}
#ltpoc_metadata,
#ltpoc_tag_data {
	display:none;
}
#ltpoc_close_preview_button.loading {
	background: url(' . LTPOC_HTTP_PATH . '/images/loading-black.gif) no-repeat;
}
.right_textTokenButton {
	display: block;
	float: right;
	position: relative;
	width:16px;
	height:16px;
	margin:0 6px 0 0;
	top:2px;
}
.left_textTokenButton {
	display: inline;
	position: relative;
	color: gray;
	width:10px;
	height:10px;
	padding: 0 5px;
	margin:0 6px 0 0;
	top:1px;
}
.ltpoc_tagToken {
	background: #dbf1fc url(' . LTPOC_HTTP_PATH . '/images/tag-background.gif) center center repeat-x;
}
.ltpoc_tagToken span.left-endcap {
	display: block;
	background: transparent url(' . LTPOC_HTTP_PATH . '/images/tag-left-endcap.gif) left center no-repeat;
	position: absolute;
	height: 20px;
	width: 7px;
}
.ltpoc_tagToken.userInline, .ltpoc_tagToken.userOverlay {
	background: #fff3db url(' . LTPOC_HTTP_PATH . '/images/tag-background-user.gif) center center repeat-x;
}
.right_textTokenButton.disabled {
	background-position: 0 -16px;
}
.right_textTokenButton.hover {
	background-position: 0 -32px;
}
.right_textTokenButton.kill {
	background-image: url(' . LTPOC_HTTP_PATH . '/images/delete.png);
	cursor:pointer;
}
.right_textTokenButton.add {
	background-image: url(' . LTPOC_HTTP_PATH . '/images/add.png);
	cursor:pointer;
}

.right_textTokenButton.image {
	background-image: url(' . LTPOC_HTTP_PATH . '/images/picture.png);
	cursor:pointer;
}
#ltpoc_images_page_fwd, #ltpoc_images_page_fwd.disabled, #ltpoc_images_page_back, #ltpoc_images_page_back.disabled {
	background: url(' . LTPOC_HTTP_PATH . '/images/image-nav-background.gif) 0 0 no-repeat;
	' . ( ! LTPOC_WP_GTE_33 ? 'margin: 40px 15px 0;' : '') . '
}
#ltpoc_preview_insert_sizes li.square {
	background: url(' . LTPOC_HTTP_PATH . '/images/img-size-75.png);
}
#ltpoc_preview_insert_sizes li.thumb {
	background: url(' . LTPOC_HTTP_PATH . '/images/img-size-100.png);
}
#ltpoc_preview_insert_sizes li.small {
	background: url(' . LTPOC_HTTP_PATH . '/images/img-size-200.png);
}
#ltpoc_preview_insert_sizes li.medium {
	background: url(' . LTPOC_HTTP_PATH . '/images/img-size-500.png);
}
.socialtag .token-text {
	font-weight:bold;
}

			');
			return '
			';
		case 'rte':
			return '
			';
	}

}

function ltpoc_menu_items() {
	if ( current_user_can( 'manage_options' ) ) {
		add_options_page(
			'Laiser Tag Plus Options'
			, 'Laiser Tag Plus'
			, 'manage_options'
			, basename( __FILE__ )
			, 'ltpoc_options_form'
		);
	}

}
add_action( 'admin_menu', 'ltpoc_menu_items' );

function ltpoc_options_form() {
	global $ltpoc_api_key, $ltpoc_key_entered, $ltpoc_relevance_minimum, $ltpoc_auto_fetch;
	$error = '';
	$api_msg = '';
	if ( ! $ltpoc_key_entered ) {
		$api_msg = '
			<p>Like Akismet and a few other WordPress plugins, the use of Laiser Tag Plus requires that each user obtain a key for the service.
			Logic Monitor Plus is built on top of the Calais service and getting a key is easy:</p>
			<ul>
				<li>Click <a href="http://opencalais.com/user/register">here</a> and follow the instructions for registering for an API key. Fill out the form and you’ll have your key in a few seconds.</li>
				<li>When you receive the key copy it and paste it in the box above.</li>
				<li>You’re done!</li>
			</ul>';
	}
	if ( isset( $_GET['ltpoc_api_test_failed'] ) ) {
		$error = '<p><span class="error" style="padding:3px;"><strong>Error</strong>: ' . $_GET['ltpoc_api_test_failed'] . '</span></p>';
	}
	if ( isset( $_GET['ltpoc_update_failed'] ) ) {
		$error = '<p><span class="error" style="padding:3px;"><strong>Error</strong>: Could not update API key.</span></p>';
	}
	if ( empty( $error ) && isset( $_GET['ltpoc_key_changed'] ) && $_GET['ltpoc_key_changed'] == true && ! empty( $ltpoc_api_key ) ) {
		$api_msg = '<p>Your API Key is valid. Enjoy!</p>';
	}
	else if ( ! empty( $error ) ) {
		$api_msg = '';
	}

	$searchable_checked = 'checked="checked"';
	$distribute_checked = 'checked="checked"';
	$privacy_prefs = get_option( 'ltpoc_privacy_prefs' );
	if ( $privacy_prefs ) {
		if ( $privacy_prefs['allow_search'] != 'yes' ) {
			$searchable_checked = '';
		}
		if ( $privacy_prefs['allow_distribution'] != 'yes' ) {
			$distribute_checked = '';
		}
	}

    $tag_types = ltpoc_get_tag_types();
    $tag_type_selection = '';
    foreach ( $tag_types as $tag_type => $checked) {
        $tag_type_selection .= '
        <div>
            <input id="' . sanitize_title( $tag_type ) . '" type="checkbox" name="ltpoc_tag_types[' . esc_attr( sanitize_title( $tag_type ) ) . ']" ' . checked( $checked, '1', false ) . ' value="1" />
            <label for="' . sanitize_title( $tag_type ) . '">' . esc_html( $tag_type ) .  '</label>
        </div>';
    }

	print( '
		<div class="wrap">
			<h2>Laiser Tag Plus</h2>
			<form action="" method="post">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">Calais API Key</th>
							<td>
								<input type="text" size="50" name="ltpoc_api_key" autocomplete="off" value="' . $ltpoc_api_key . '" /><br/>' . $api_msg.$error . '
								<p><a href="http://new.opencalais.com/opencalais-api/" target="_blank">Get your OpenCalais API key</p>
							</td>
						</tr>
					</tbody>
				</table>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">Your posts can be:</th>
							<td>
								<input id="ltpoc_privacy_searchable" type="checkbox" name="ltpoc_privacy_searchable" ' . $searchable_checked . ' />
								<label for="ltpoc_privacy_searchable">Searched</label><br/>
								<input id="ltpoc_privacy_distribute" type="checkbox" name="ltpoc_privacy_distribute" ' . $distribute_checked . ' />
								<label for="ltpoc_privacy_distribute">Distributed</label><br/>
								<p><a href="http://new.opencalais.com/open-calais-terms-of-service/">Only public, published posts will be indexed by Calais</a></p>
							</td>
						</tr>
					</tbody>
				</table>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">Suggest tags with:</th>
							<td>
								<input id="ltpoc_relevance_any" type="radio" name="ltpoc_relevance_minimum" value="any" ' . ( $ltpoc_relevance_minimum == 'any' ? 'checked="checked"' : '' ) . '/>
								<label for="ltpoc_relevance_any">Any relevance rating</label><br/>
								<input id="ltpoc_relevance_medium" type="radio" name="ltpoc_relevance_minimum" value="medium" ' . ( $ltpoc_relevance_minimum == 'medium' ? 'checked="checked"' : '') . '/>
								<label for="ltpoc_relevance_medium">Medium or higher relevance rating</label><br/>
								<input id="ltpoc_relevance_high" type="radio" name="ltpoc_relevance_minimum" value="high" ' . ( $ltpoc_relevance_minimum == 'high' ? 'checked="checked"' : '' ) . '/>
								<label for="ltpoc_relevance_high">Only high relevance ratings</label><br/>
								<p>' . /*(Explanatory copy goes here.)*/'</p>
							</td>
						</tr>
					</tbody>
				</table>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">' . __( 'Auto-fetch tags?', 'laisertagplus' ) . '</th>
							<td>
								<input type="checkbox" name="ltpoc_auto_fetch" ' . ( $ltpoc_auto_fetch == 'yes' ? 'checked="checked"' : '') . ' /><br/>
							</td>
						</tr>
					</tbody>
				</table>

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">' . __( 'Show the following tag types:', 'laisertagplus' ) . '</th>
							<td>'
                            . $tag_type_selection .
							'</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="hidden" name="ltpoc_action" value="update_api_key" />
					<input type="submit" name="submit" value="Update Laiser Tag Plus Options" />
				</p>
			</form>
		</div>
	');
}

function ltpoc_admin_head() {
	global $ltpoc_key_entered;
	if ( ltpoc_on_edit_page() && $ltpoc_key_entered ) {
		print( '
	<script type="text/javascript" src="' . admin_url( 'index.php?ltpoc_action=admin_js' ) . '"></script>
	<link type="text/css" href="' . admin_url( 'index.php?ltpoc_action=admin_css' ) . '" rel="stylesheet" />
	<link type="text/css" href="' . admin_url( 'index.php?ltpoc_action=admin_css' ) . '" rel="stylesheet" />
	<!--[if IE]>
	<link type="text/css" href="' . LTPOC_HTTP_PATH . '/css/ie6.css" rel="stylesheet" />
	<![endif]-->
	');
		if (LTPOC_WP_GTE_27) {
			$laisertagplus_post_types = apply_filters('ltpoc_laisertagplus_post_types', array('post'));
			foreach ($laisertagplus_post_types as $post_type) {
				add_meta_box('ltpoc_tag_controls', 'Laiser Tag Plus Tags', 'ltpoc_render_tag_controls', $post_type, 'normal', 'high');
				add_meta_box('ltpoc_image_controls', 'Laiser Tag Plus Images', 'ltpoc_render_image_controls', $post_type, 'normal', 'high');
			}
		}
	}
}
add_action( 'admin_head', 'ltpoc_admin_head' );

if ( LTPOC_WP_GTE_25 ) {
	function ltpoc_addMCE_plugin( $plugins ) {
		global $ltpoc_key_entered;
		if ( $ltpoc_key_entered ) {
			$plugins['laisertagplus'] = LTPOC_HTTP_PATH . '/js/mce/mce3/editor_plugin.js';
		}
		return $plugins;
	}

	if ( ltpoc_on_edit_page() ) {
		add_filter( 'mce_external_plugins', 'ltpoc_addMCE_plugin' );
	}
}
else {
	if ( LTPOC_WP_GTE_23 ) {
		function ltpoc_addMCE_plugin( $plugins ) {
			global $ltpoc_key_entered;
			if ( $ltpoc_key_entered ) {
				$plugins[] = 'laisertagplus';
			}
			return $plugins;
		}

		if ( ltpoc_on_edit_page() ) {
			add_filter( 'mce_plugins', 'ltpoc_addMCE_plugin' );
		}
	}
}

function ltpoc_addMCE_css( $csv ) {
}
add_filter( 'mce_css', 'ltpoc_addMCE_css' );

function ltpoc_generate_commit_id( $post ) {
	return get_permalink( $post ) . time();
}

function ltpoc_save_post( $post_id, $post ) {
	if ( LTPOC_WP_GTE_26 && $post->post_type == 'revision' ) {
		// it's at least WP2.6 and a revision, so don't add meta data, just return.
		return;
	}
	if ( $post->post_status == 'publish' ) {
		// commit the content to opencalais
		$privacy_prefs = get_option( 'ltpoc_privacy_prefs' );

		$ltpoc_id = get_post_meta( $post_id, 'ltpoc_commit_id' );
		if ( ! $ltpoc_id ) {
			$ltpoc_id = ltpoc_generate_commit_id( $post );
			add_post_meta( $post_id, 'ltpoc_commit_id', $ltpoc_id );
		}
		$params = ltpoc_api_param_xml(
			$ltpoc_id,
			'',
			( $privacy_prefs['allow_distribution'] == 'yes' ),
			( $privacy_prefs['allow_search'] == 'yes' )
		);
		$result = ltpoc_ping_ltpoc_api( $post->post_content, LTPOC_FINAL_CONTENT, $params );
	}
	if ( isset( $_POST['ltpoc_metadata'] ) ) {
		update_post_meta( $post_id, 'ltpoc_metadata', stripslashes( $_POST['ltpoc_metadata'] ) );
	}
	if ( isset( $_POST['ltpoc_tag_data'] ) ) {
		// Possibly want to add to the existing data in case the post changes and new tags are supplied?
		update_post_meta( $post_id, 'ltpoc_tag_data', stripslashes( $_POST['ltpoc_tag_data'] ) );
	}
}
add_action( 'save_post', 'ltpoc_save_post', 10, 2 );

function ltpoc_filter_content( $content ) {
	if ( ! class_exists( 'simple_html_dom_node' ) ) {
		include_once( LTPOC_FILE_PATH . 'vendor/simple_html_dom.php' );
	}
	global $post;
	$footer_markup = false;
	$preg_pattern = false;

	if ( is_single() && 'post' == $post->post_type ) {
		$types = array( 'Company', 'SocialTag' );
		$tags_on_post = ltpoc_get_tags_on_post( $post->ID );

		foreach ($types as $type) {
			$replacements = array();

			foreach ( $tags_on_post[ $type ] as $tag ) {
				$url = ltpoc_filter_get_url( $tag, $type );
				if ( $url ) {
					$link_start = '<a href="' . esc_url( $url ) . '">';
					$link_end = '<i class="oc-external-link fa fa-external-link"></i></a>';
					// Order matters here, commonname is likely to be contained within the name and be shorter
					$replacements[] = array(
						'preg_pattern' => ltpoc_filter_pattern( $tag, $type ),
						'replacement' => array(
							'link_start' => $link_start,
							'link_end' => $link_end,
						)
					);

					$footer_markup .= '<li>' . $link_start . esc_html( $tag['name'] ) . $link_end . '</li>';
				}
			}

		// Make sure that a tags are not being inserted into other a tags
			if ( ! empty( $replacements ) ) {
				$html = str_get_html( $content );
				foreach ( $html->find("text") as $element ) {
					foreach ( $replacements as $replacement_data ) {
						$preg_pattern = trim( $replacement_data['preg_pattern'], '|' );
						if ( ! ltpoc_parent_has_a_tag( $element ) ) {
							$element->innertext = preg_replace( '/\b(' . $preg_pattern . ')\b/i', '$1' . $replacement_data['replacement']['link_start'] . $replacement_data['replacement']['link_end'], $element->innertext );
						}
					}
				}
				$content = $html;
			}
		}
	}

	if ( ! empty ( $footer_markup ) ) {
		$content .= '<hr /><h4>Associated Links</h4>' . $footer_markup . '<img class="oc-trlogo" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'images/tr-logo.png' ) . '" /><hr class="oc-hr" />';
	}
	return $content;
}
add_filter( 'the_content', 'ltpoc_filter_content' );

function ltpoc_get_tags_on_post( $post_id ) {
	$tags = array(
		'SocialTag' => array(),
		'Company' => array(),
	);

	$meta = get_post_meta( $post_id, 'ltpoc_metadata', true );
	$data = json_decode( $meta );

	if ( isset( $data->tags )  ) {
		foreach ( $data->tags as $tag ) {

			if ( isset( $tag->type ) && 'current' == $tag->bucketName ) {
				if ( 'SocialTag' == $tag->type ) {
					$tags['SocialTag'][] = array(
						'name' => $tag->source->name,
					);
				}
				else if ( 'Company' == $tag->type ) {
					$tags['Company'][] = array(
						'name' => $tag->source->name,
						'fullName' => $tag->source->fullName,
						'ticker' => isset( $tag->source->ticker) ? $tag->source->ticker : false,
						'permID' => isset( $tag->source->permID ) ? $tag->source->permID : false,
					);
				}
			}
		}
	}
	return $tags;
}

function ltpoc_filter_get_url( $tag, $type ) {
	$url = false;
	if ( 'Company' == $type && ! empty( $tag['permID'] ) ) {
		$url = 'https://permid.org/1-' . $tag['permID'];
	}
	else {
		$url = 'https://en.wikipedia.org/w/index.php?title=' . $tag['name'] . '&redirect=yes';
	}

	return $url;
}

function ltpoc_filter_pattern( $tag, $type ) {
	$pattern = preg_quote( $tag['name'] ) . '|';
	if ( 'Company' == $type ) {
	  $pattern .= ! empty( $tag['ticker'] ) ? preg_quote( $tag['ticker'] ) . '|' : '';
	  // Fullname takes prescendence, match that first
	  $pattern = ( ! empty( $tag['fullName'] ) ? preg_quote( $tag['fullName'] ) . '|' : '' ) . $pattern ;
	}

	return trim( $pattern, '|' );
}

function ltpoc_parent_has_a_tag( $element ) {
	$has_a = false;
	while ( isset( $element->parent ) ) {
		$parent = $element->parent;
		if ( 'a' == $parent->tag ) {
			$has_a = true;
			break;
		}
		$element = $parent;
	}

	return $has_a;
}


// Frontend enqueue
function ltpoc_enqueue_scripts() {
	$plugin_dir_url = plugin_dir_url( __FILE__ );
	wp_enqueue_style( 'font-awesome', $plugin_dir_url . 'vendor/font-awesome/css/font-awesome.min.css' );
}
add_action( 'wp_enqueue_scripts', 'ltpoc_enqueue_scripts' );

function ltpoc_frontent_style() {
?>
<style type="text/css">
.ltpoc-external-link {
	padding-left: 5px;
}
.ltpoc-trlogo {
	float: right;
	height: auto;
	padding: 0 10px 10px 0;
	width: 113px;
}
.ltpoc-hr {
	clear: both;
}
</style>
<?php
}
add_action( 'wp_head', 'ltpoc_frontent_style' );

/**
 * Get tag types and whether or not they're activated
 *
 * @return Array array of tag types and whether or not they're active
 **/
function ltpoc_get_tag_types() {
    // By default, show all tag types
    $default_tag_types = array(
    	'Anniversary' => 1,
    	//'City' => 1,
    	//'Company' => 1,
    	//'Continent' => 1,
    	//'Country' => 1,
    	'Currency' => 1,
        //'DocCat' => 1,
    	//'Editor' => 1,
    	//'EmailAddress' => 1,
    	'EntertainmentAwardEvent' => 1,
        'EventFact' => 1,
    	'Facility' => 1,
    	//'FaxNumber' => 1,
        'Geography' => 1,
    	'Holiday' => 1,
    	'IndustryTerm' => 1,
    	//'Journalist' => 1,
        'M&A' => 1, // JS need &amp;
    	'MarketIndex' => 1,
    	'MedicalCondition' => 1,
    	'MedicalTreatment' => 1,
    	'Movie' => 1,
    	'MusicAlbum' => 1,
    	'MusicGroup' => 1,
    	'NaturalFeature' => 1,
    	'OperatingSystem' => 1,
    	'Organization' => 1,
    	//'Person' => 1,
        'Person' => 1,
    	'PharmaceuticalDrug' => 1,
    	//'PhoneNumber' => 1,
    	'PoliticalEvent' => 1,
    	//'Position' => 1,
    	//'Product' => 1,
    	'ProgrammingLanguage' => 1,
    	//'ProvinceOrState' => 1,
    	'PublishedMedium' => 1,
    	'RadioProgram' => 1,
    	'RadioStation' => 1,
    	//'Region' => 1,
    	'SportsEvent' => 1,
    	'SportsGame' => 1,
    	'SportsLeague' => 1,
        'SocialTag' => 1,
    	'Technology' => 1,
    	'TVShow' => 1,
    	'TVStation' => 1,
    	//'URL' => 1,
    );

    $saved_types = get_option( 'ltpoc_tag_types', array() );
    return array_merge( $default_tag_types, $saved_types );
}
