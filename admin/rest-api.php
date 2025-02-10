<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Callback function for the custom projects endpoint.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response
 */
function projects_wp_get_projects_data( $request ) {
    $args = [
        'post_type'      => 'projects',
        'posts_per_page' => $request->get_param( 'per_page' ) ?? 10,
        'paged'          => $request->get_param( 'page' ) ?? 1,
    ];

    $query = new WP_Query( $args );
    $projects = [];

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();

            $project_id = get_the_ID();
            $github_url = get_post_meta( $project_id, '_projects_wp_github_url', true );
            $github_data = projects_wp_get_github_data( $github_url );

            // Fetch project-type taxonomy terms (names)
            $project_types = wp_get_post_terms( $project_id, 'project-type', [
                'fields' => 'names',
            ] );

            $projects[] = [
                'id'             => $project_id,
                'title'          => get_the_title(),
                'excerpt'        => get_the_excerpt(),
                'permalink'      => get_permalink(),
                'thumbnail'      => get_the_post_thumbnail_url( $project_id, 'large' ),
                'download_count' => (int) get_post_meta( $project_id, '_projects_wp_download_count', true ),
                'download_url'   => esc_url( site_url( '/download/' . $project_id ) ),
                'github_url'     => $github_url,
                'github_data'    => [
                    'owner'        => [
                        'avatar_url' => $github_data['owner']['avatar_url'] ?? '',
                        'name'       => $github_data['owner']['login'] ?? '',
                        'profile'    => $github_data['owner']['html_url'] ?? '',
                    ],
                    'last_updated' => ! empty( $github_data['updated_at'] )
                        ? date_i18n( get_option( 'date_format' ), strtotime( $github_data['updated_at'] ) )
                        : '',
                    'language'     => $github_data['language'] ?? '',
                    'license'      => $github_data['license']['name'] ?? 'None',
                    'stars'        => $github_data['stargazers_count'] ?? 0,
                    'forks'        => $github_data['forks_count'] ?? 0,
                    'issues'       => $github_data['open_issues_count'] ?? 0,
                ],
                'version'        => projects_wp_get_version_from_github( $github_url ),
                'project_types'  => $project_types,
                'content'        => get_the_content(),
            ];
        }
        wp_reset_postdata();
    }

    $response = [
        'projects' => $projects,
        'total'    => $query->found_posts,
        'pages'    => $query->max_num_pages,
    ];

    return rest_ensure_response( $response );
}

/**
 * Register REST API endpoint for popular projects.
 */
function projects_wp_register_popular_endpoint() {
    register_rest_route( 
        'projects/v1', 
        '/popular', 
        [
            'methods'  => 'GET',
            'callback' => 'projects_wp_get_popular_projects',
            'permission_callback' => '__return_true',
        ]
    );
}

/**
 * Callback function for popular projects endpoint.
 */
function projects_wp_get_popular_projects( $data ) {
    $args = [
        'post_type'      => 'projects',
        'posts_per_page' => $data->get_param( 'per_page' ) ?? 10,
        'meta_key'       => '_projects_wp_download_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ];

    $query = new WP_Query( $args );
    $projects = [];

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $projects[] = [
                'id'             => get_the_ID(),
                'title'          => get_the_title(),
                'download_count' => (int) get_post_meta( get_the_ID(), '_projects_wp_download_count', true ),
                'download_url'   => esc_url( site_url( '/download/' . get_the_ID() ) ),
                'github_url'     => get_post_meta( get_the_ID(), '_projects_wp_github_url', true ),
                'permalink'      => get_permalink(),
            ];
        }
    }

    wp_reset_postdata();
    return rest_ensure_response( $projects );
}
add_action( 'rest_api_init', 'projects_wp_register_popular_endpoint' );

