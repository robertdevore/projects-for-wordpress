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
                'archive_title'     => get_option( 'projects_wp_archives_archive_title', '0' ),
                'projects_per_page' => get_option( 'projects_wp_archives_projects_per_page', '0' ),
                'project_title'     => get_option( 'projects_wp_archives_project_title', '0' ),
                'project_excerpt'   => get_option( 'projects_wp_archives_project_excerpt', '0' ),
                'project_buttons'   => get_option( 'projects_wp_archives_project_buttons', '0' ),
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