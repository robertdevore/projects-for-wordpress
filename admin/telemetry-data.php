<?php

/**
 * Schedule a daily cron job to send telemetry data.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_schedule_telemetry_cron() {
    if ( ! wp_next_scheduled( 'projects_wp_send_telemetry_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'projects_wp_send_telemetry_cron' );
    }
}
add_action( 'wp', 'projects_wp_schedule_telemetry_cron' );

/**
 * Clear the scheduled cron job on plugin deactivation.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_clear_telemetry_cron() {
    $timestamp = wp_next_scheduled( 'projects_wp_send_telemetry_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'projects_wp_send_telemetry_cron' );
    }
}
register_deactivation_hook( __FILE__, 'projects_wp_clear_telemetry_cron' );

/**
 * Send telemetry data to a third-party API.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_send_telemetry_data() {
    // Check if telemetry sharing is enabled
    $share_telemetry = get_option( 'projects_wp_share_telemetry', '0' );
    if ( '1' !== $share_telemetry ) {
        return;
    }

    // Retrieve data from the custom REST API endpoint
    $api_url = rest_url( 'projects/v1/projects' );
    $response = wp_remote_get( $api_url, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'Failed to fetch telemetry data: ' . $response->get_error_message() );
        return;
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $status_code ) {
        error_log( 'Unexpected response code while fetching telemetry data: ' . $status_code );
        return;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data['projects'] ) || ! is_array( $data['projects'] ) ) {
        error_log( 'Invalid telemetry data received.' );
        return;
    }

    // Prepare the payload for the third-party API.
    $third_party_api_url = 'http://127.0.0.1:5000/api/telemetry';
    $payload = [
        'site_url' => home_url(),
        'data'     => $data['projects'],
    ];

    // Send the data to the third-party API.
    $response = wp_remote_post( $third_party_api_url, [
        'timeout' => 15,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode( $payload ),
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'Failed to send telemetry data: ' . $response->get_error_message() );
    } else {
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( in_array( $status_code, [ 200, 201 ], true ) ) {
            error_log( 'Telemetry data sent successfully.' );
        } else {
            error_log( 'Unexpected response code while sending telemetry data: ' . $status_code );
        }
    }
}
add_action( 'projects_wp_send_telemetry_cron', 'projects_wp_send_telemetry_data' );

/**
 * Trigger telemetry data send on 'projects' post save.
 *
 * @param int $post_id The post ID.
 * @param WP_Post $post The post object.
 * @param bool $update Whether this is an update.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_send_telemetry_on_save( $post_id, $post, $update ) {
    // Bail early if not 'projects' post type.
    if ( 'projects' !== $post->post_type ) {
        return;
    }

    // Ensure telemetry data is sent.
    projects_wp_send_telemetry_data();
}
add_action( 'save_post', 'projects_wp_send_telemetry_on_save', 10, 3 );
