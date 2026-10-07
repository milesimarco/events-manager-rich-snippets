<?php
/*
Plugin Name:       Events Manager Rich Snippets
Plugin URI:        https://wordpress.org/plugins/events-manager-rich-snippets/
Description:       Extends Events Manager WordPress plugin with rich snippets preview (Google structured data).
Version:           1.2
Requires at least: 6.1
Requires PHP:      7.0
Requires Plugins:  events-manager
Author:            Marco Milesi
Author URI:        https://www.marcomilesi.com
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads an Events Manager option, using its own helper when available.
 *
 * @param string $option
 * @param mixed  $default
 * @return mixed
 */
function emrs_get_option( $option, $default = false ) {
	$value = function_exists( 'em_get_option' ) ? em_get_option( $option ) : get_option( $option );
	return ( false === $value || null === $value || '' === $value ) ? $default : $value;
}

/**
 * Converts a string to plain text, without tags and HTML entities.
 *
 * @param string $text
 * @return string
 */
function emrs_plain_text( $text ) {
	return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
}

/**
 * Builds the schema.org Event data for a single Events Manager event.
 *
 * @param EM_Event $EM_Event
 * @return array
 */
function emrs_get_event_data( $EM_Event ) {
	$start = $EM_Event->start();
	$end   = $EM_Event->end();

	// All-day events use plain dates, as recommended by Google.
	$date_format = $EM_Event->event_all_day ? 'Y-m-d' : 'c';

	$data = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'Event',
		'name'      => emrs_plain_text( $EM_Event->event_name ),
		'startDate' => $start->format( $date_format ),
		'endDate'   => $end->format( $date_format ),
		'url'       => $EM_Event->get_permalink(),
	);

	$description = $EM_Event->post_excerpt ? $EM_Event->post_excerpt : $EM_Event->post_content;
	$description = emrs_plain_text( strip_shortcodes( (string) $description ) );
	if ( $description ) {
		$data['description'] = wp_trim_words( $description, 55, '…' );
	}

	$image = $EM_Event->get_image_url();
	if ( $image ) {
		$data['image'] = $image;
	}

	$data['eventStatus'] = $EM_Event->event_active_status == 0 ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled';

	// Physical location and/or online location (e.g. a URL event location).
	$locations = array();

	if ( $EM_Event->has_location() ) {
		$EM_Location = $EM_Event->get_location();
		$address     = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $EM_Location->location_address,
				'addressLocality' => $EM_Location->location_town,
				'addressRegion'   => $EM_Location->location_state ? $EM_Location->location_state : $EM_Location->location_region,
				'postalCode'      => $EM_Location->location_postcode,
				'addressCountry'  => $EM_Location->location_country,
			)
		);
		$place = array(
			'@type'   => 'Place',
			'name'    => emrs_plain_text( $EM_Location->location_name ),
			'address' => $address,
		);
		if ( $EM_Location->location_id ) {
			$place['sameAs'] = $EM_Location->get_permalink();
		}
		$locations[] = $place;
	}

	if ( $EM_Event->has_event_location() ) {
		$Event_Location = $EM_Event->get_event_location();
		$online         = array( '@type' => 'VirtualLocation' );
		if ( $Event_Location && ! empty( $Event_Location->url ) ) {
			$online['url'] = $Event_Location->url;
		} else {
			$online['url'] = $EM_Event->get_permalink();
		}
		$locations[] = $online;
	}

	if ( count( $locations ) > 1 ) {
		$data['eventAttendanceMode'] = 'https://schema.org/MixedEventAttendanceMode';
		$data['location']            = $locations;
	} elseif ( $locations ) {
		$data['eventAttendanceMode'] = 'VirtualLocation' === $locations[0]['@type'] ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode';
		$data['location']            = $locations[0];
	}

	// Tickets, only when bookings are enabled for the event.
	if ( $EM_Event->event_rsvp && emrs_get_option( 'dbem_rsvp_enabled' ) ) {
		$offers   = array();
		$currency = emrs_get_option( 'dbem_bookings_currency', 'USD' );
		foreach ( $EM_Event->get_tickets() as $EM_Ticket ) {
			$offer = array(
				'@type'         => 'Offer',
				'name'          => emrs_plain_text( $EM_Ticket->ticket_name ),
				'price'         => number_format( (float) $EM_Ticket->get_price(), 2, '.', '' ),
				'priceCurrency' => $currency,
				'url'           => $EM_Event->get_permalink(),
				'availability'  => $EM_Ticket->get_available_spaces() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
			);
			if ( ! empty( $EM_Ticket->ticket_start ) ) {
				$offer['validFrom'] = $EM_Ticket->start()->format( 'c' );
			}
			$offers[] = $offer;
		}
		if ( $offers ) {
			$data['offers'] = $offers;
		}
	}

	$data['organizer'] = array(
		'@type' => 'Organization',
		'name'  => emrs_plain_text( get_bloginfo( 'name' ) ),
		'url'   => home_url( '/' ),
	);

	return apply_filters( 'emrs_event_data', $data, $EM_Event );
}

/**
 * Returns the JSON-LD script tag for an event.
 *
 * @param EM_Event $EM_Event
 * @return string
 */
function emrs_get_event_script( $EM_Event ) {
	if ( method_exists( $EM_Event, 'password_required' ) && $EM_Event->password_required() ) {
		return '';
	}
	$data = emrs_get_event_data( $EM_Event );
	if ( empty( $data ) ) {
		return '';
	}
	return "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
}

/**
 * Adds the upcoming events structured data to the Events Manager pages.
 *
 * @param string $content
 * @return string
 */
function emrs_content( $content ) {
	if ( ! class_exists( 'EM_Events' ) ) {
		return $content;
	}

	$args = apply_filters(
		'emrs_events_args',
		array(
			'limit'   => 25,
			'orderby' => 'event_start_date',
			'scope'   => 'future',
		)
	);

	$item = '';
	foreach ( EM_Events::get( $args ) as $EM_Event ) {
		$item .= emrs_get_event_script( $EM_Event );
	}

	return $item . $content;
}
add_filter( 'em_content', 'emrs_content' );

/**
 * Adds the structured data of the current event to single event pages.
 */
function emrs_single_event_head() {
	if ( ! defined( 'EM_POST_TYPE_EVENT' ) || ! function_exists( 'em_get_event' ) || ! is_singular( EM_POST_TYPE_EVENT ) ) {
		return;
	}
	$EM_Event = em_get_event( get_queried_object_id(), 'post_id' );
	if ( $EM_Event && $EM_Event->event_id ) {
		echo emrs_get_event_script( $EM_Event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with wp_json_encode().
	}
}
add_action( 'wp_head', 'emrs_single_event_head' );
