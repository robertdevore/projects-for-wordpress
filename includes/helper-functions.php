<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Retrieve all plugin settings in a single call.
 *
 * @return array The array of plugin settings.
 */
function projects_wp_settings() {
    static $settings = null;

    if ( $settings === null ) {
        $settings = [
            'github_api_token' => get_option( 'projects_wp_github_api_token', '' ),
            'share_telemetry'  => get_option( 'projects_wp_share_telemetry', '0' ),
            'templates'        => [
                'version'           => get_option( 'projects_wp_templates_version', '0' ),
                'last_updated'      => get_option( 'projects_wp_templates_last_updated', '0' ),
                'license'           => get_option( 'projects_wp_templates_license', '0' ),
                'language'          => get_option( 'projects_wp_templates_language', '0' ),
                'downloads'         => get_option( 'projects_wp_templates_downloads', '0' ),
                'forks'             => get_option( 'projects_wp_templates_forks', '0' ),
                'stargazers_count'  => get_option( 'projects_wp_templates_stargazers_count', '0' ),
                'open_issues_count' => get_option( 'projects_wp_templates_open_issues_count', '0' ),
                'github_owner'      => get_option( 'projects_wp_templates_github_owner', '0' ),
            ],
            'archives'         => [
                'archive_title'   => get_option( 'projects_wp_archives_archive_title', '0' ),
                'project_title'   => get_option( 'projects_wp_archives_project_title', '0' ),
                'project_excerpt' => get_option( 'projects_wp_archives_project_excerpt', '0' ),
                'project_buttons' => get_option( 'projects_wp_archives_project_buttons', '0' ),
            ]
        ];
    }

    return $settings;
}

/**
 * GitHub API call for owner data
 * 
 * @param mixed $owner_name
 * 
 * @return mixed
 */
function projects_wp_github_owner( $owner_name = NULL ) {
    if ( ! $owner_name ) { return; }

    $owner = 'https://api.github.com/users/' . $owner_name;

    // Set up the context with a User-Agent header (GitHub requires this).
    $options = [
        "http" => [
            "method"  => "GET",
            "header"  => "User-Agent: WORDPRESS\r\n"
        ]
    ];
    $context = stream_context_create( $options );

    // Fetch the API response
    $response = file_get_contents( $owner, false, $context );

    if ( $response === false ) {
        die( 'Error fetching data from GitHub API.' );
    }

    // Decode the JSON response to an associative array
    $owner = json_decode($response, true);

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        die( 'Error decoding JSON data: ' . json_last_error_msg() );
    }

    return $owner;
}

/**
 * Fetch GitHub latest release URL.
 * 
 * @since  1.0.0
 * @return mixed
 */
function projects_wp_get_github_release_url( $github_url ) {
    $api_token = get_option( 'projects_wp_github_api_token', '' );
    $api_url   = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) ) . '/releases/latest';

    $headers = [ 'Accept' => 'application/vnd.github.v3+json' ];
    if ( $api_token ) {
        $headers['Authorization'] = 'token ' . $api_token;
    }

    $response = wp_remote_get( $api_url, [ 'headers' => $headers ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return false;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    // Check for assets and return the URL of a custom asset if available.
    if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
        foreach ( $data['assets'] as $asset ) {
            // For example, check if the asset is a zip file and maybe even match a specific name.
            if ( isset( $asset['name'] ) && pathinfo( $asset['name'], PATHINFO_EXTENSION ) === 'zip' ) {
                return $asset['browser_download_url'];
            }
        }
    }

    // Fallback to the auto-generated zipball_url.
    return $data['zipball_url'] ?? false;
}

function projects_wp_get_github_data( $github_url ) {
    if ( empty( $github_url ) ) {
        error_log( 'GitHub URL is empty.' );
        return null;
    }

    $api_url   = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) );
    $api_token = get_option( 'projects_wp_github_api_token', '' );

    $headers = [ 'Accept' => 'application/vnd.github.v3+json' ];
    if ( ! empty( $api_token ) ) {
        $headers['Authorization'] = 'token ' . $api_token;
    } else {
        error_log( 'GitHub API token is missing. Using unauthenticated requests.' );
    }

    $cache_key = 'projects_wp_github_data_' . md5( $api_url );
    $cached_data = get_transient( $cache_key );
    if ( $cached_data ) {
        return $cached_data;
    }

    $response = wp_remote_get( $api_url, [ 'headers' => $headers ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'GitHub API error: ' . $response->get_error_message() );
        return null;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    error_log( "GitHub API response code: $response_code" );

    if ( $response_code === 403 ) {
        $rate_limit_remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
        $rate_limit_reset = wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
        error_log( 'GitHub API 403: Rate limit exceeded. Remaining: ' . $rate_limit_remaining . ' Reset at: ' . date( 'Y-m-d H:i:s', $rate_limit_reset ) );
        return null;
    } elseif ( $response_code !== 200 ) {
        error_log( 'GitHub API error: Received status ' . $response_code );
        return null;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $data ) || ! is_array( $data ) ) {
        error_log( 'GitHub API error: Invalid data received.' );
        return null;
    }

    set_transient( $cache_key, $data, HOUR_IN_SECONDS );
    return $data;
}

/**
 * Fetch the version number from the GitHub API.
 *
 * @param string $github_url The GitHub repository URL.
 * 
 * @since  1.0.0
 * @return string The version number or 'Unknown'.
 */
function projects_wp_get_version_from_github( $github_url ) {
    if ( empty( $github_url ) ) {
        return 'Unknown';
    }

    $api_url  = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) ) . '/releases/latest';
    $response = wp_remote_get( $api_url );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return 'Unknown';
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    return $data['tag_name'] ?? 'Unknown';
}
