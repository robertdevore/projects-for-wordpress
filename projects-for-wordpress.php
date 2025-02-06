<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://www.robertdevore.com
 * @since             1.0.0
 * @package           Projects_For_WordPress
 *
 * @wordpress-plugin
 *
 * Plugin Name: Projects for WordPress®
 * Description: Create a showcase directory for projects (plugins, themes, patterns) with custom post types, taxonomies, and download functionality.
 * Plugin URI:  https://github.com/robertdevore/projects-for-wordpress/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://www.robertdevore.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: projects-wp
 * Domain Path: /languages
 * Update URI:  https://github.com/robertdevore/projects-for-wordpress/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Current plugin version.
define( 'PROJECTS_FOR_WORDPRESS_VERSION', time() );

// Add the required files.
require 'admin/admin-settings.php';
require 'includes/helper-functions.php';

require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/robertdevore/projects-for-wordpress/',
	__FILE__,
	'projects-for-wordpress'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

/**
 * Flush rewrite rules on activation.
 */
function projects_wp_flush_rewrite_rules() {
    projects_wp_register_cpt_and_taxonomy();
    projects_wp_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'projects_wp_flush_rewrite_rules' );

/**
 * Register Custom Post Type and Taxonomy
 */
function projects_wp_register_cpt_and_taxonomy() {
    register_post_type( 'projects', [
        'labels'        => [
            'name'          => __( 'Projects', 'projects-wp' ),
            'singular_name' => __( 'Project', 'projects-wp' ),
            'add_new_item'  => __( 'Add New Project', 'projects-wp' ),
        ],
        'public'        => true,
        'has_archive'   => true,
        'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
        'rewrite'       => [ 'slug' => 'projects' ],
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-format-gallery'
    ] );

    register_taxonomy( 'project-type', 'projects', [
        'labels'            => [
            'name'          => __( 'Project Types', 'projects-wp' ),
            'singular_name' => __( 'Project Type', 'projects-wp' ),
        ],
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => [
            'slug'         => 'project-type',
            'with_front'   => false,
        ],
    ] );

    if ( ! term_exists( 'plugin', 'project_type' ) ) {
        wp_insert_term( __( 'Plugin', 'projects-wp' ), 'project_type' );
    }
    if ( ! term_exists( 'theme', 'project_type' ) ) {
        wp_insert_term( __( 'Theme', 'projects-wp' ), 'project_type' );
    }
    if ( ! term_exists( 'pattern', 'project_type' ) ) {
        wp_insert_term( __( 'Pattern', 'projects-wp' ), 'project_type' );
    }
}
add_action( 'init', 'projects_wp_register_cpt_and_taxonomy' );

/**
 * Add rewrite rules for download endpoint.
 */
function projects_wp_add_rewrite_rules() {
    add_rewrite_rule(
        '^download/([0-9]+)/?$',
        'index.php?project_download_id=$matches[1]',
        'top'
    );
}
add_action( 'init', 'projects_wp_add_rewrite_rules' );

function projects_wp_query_vars( $vars ) {
    $vars[] = 'project_download_id';
    return $vars;
}
add_filter( 'query_vars', 'projects_wp_query_vars' );

/**
 * Add GitHub Repository URL meta box.
 */
function projects_wp_add_meta_boxes() {
    add_meta_box(
        'projects_wp_github_url',
        __( 'GitHub Repository URL', 'projects-wp' ),
        'projects_wp_render_meta_box',
        'projects',
        'side'
    );
}
add_action( 'add_meta_boxes', 'projects_wp_add_meta_boxes' );

function projects_wp_render_meta_box( $post ) {
    wp_nonce_field( 'projects_wp_save_meta_box', 'projects_wp_meta_box_nonce' );
    $github_url = get_post_meta( $post->ID, '_projects_wp_github_url', true );
    echo '<label for="projects_wp_github_url">' . __( 'GitHub Repository URL:', 'projects-wp' ) . '</label>';
    echo '<input type="url" id="projects_wp_github_url" name="projects_wp_github_url" value="' . esc_attr( $github_url ) . '" style="width: 100%;" />';
}

function projects_wp_save_meta_box( $post_id ) {
    if ( ! isset( $_POST['projects_wp_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['projects_wp_meta_box_nonce'], 'projects_wp_save_meta_box' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( isset( $_POST['projects_wp_github_url'] ) ) {
        update_post_meta( $post_id, '_projects_wp_github_url', esc_url_raw( $_POST['projects_wp_github_url'] ) );
    }
}
add_action( 'save_post', 'projects_wp_save_meta_box' );

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
    return $data['zipball_url'] ?? false;
}

function projects_wp_get_github_data( $github_url ) {
    if ( empty( $github_url ) ) {
        error_log( 'GitHub URL is empty.' );
        return null;
    }

    $api_url = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) );
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
 * Handle Download Redirect and Increment Download Count
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_handle_download_redirect() {
    $project_id = get_query_var( 'project_download_id' );

    if ( $project_id ) {
        $github_url   = get_post_meta( $project_id, '_projects_wp_github_url', true );
        $download_url = projects_wp_get_github_release_url( $github_url );

        if ( $download_url ) {
            // Increment download count
            $download_count = (int) get_post_meta( $project_id, '_projects_wp_download_count', true );
            $download_count++;
            update_post_meta( $project_id, '_projects_wp_download_count', $download_count );

            // Redirect to the GitHub ZIP file
            wp_redirect( esc_url_raw( $download_url ) );
            exit;
        } else {
            wp_die(
                __( 'Invalid download URL. Please check the GitHub repository.', 'projects-wp' ),
                __( 'Download Error', 'projects-wp' ),
                [ 'response' => 404 ]
            );
        }
    }
}
add_action( 'template_redirect', 'projects_wp_handle_download_redirect' );

/**
 * Add Download Count Column to Projects Admin Table
 */
function projects_wp_add_download_count_column( $columns ) {
    $columns['download_count'] = __( 'Download Count', 'projects-wp' );
    return $columns;
}
add_filter( 'manage_project_posts_columns', 'projects_wp_add_download_count_column' );

/**
 * Display Download Count in Admin Column
 */
function projects_wp_display_download_count_column( $column, $post_id ) {
    if ( 'download_count' === $column ) {
        $download_count = (int) get_post_meta( $post_id, '_projects_wp_download_count', true );
        echo $download_count ? esc_html( $download_count ) : __( '0', 'projects-wp' );
    }
}
add_action( 'manage_project_posts_custom_column', 'projects_wp_display_download_count_column', 10, 2 );

/**
 * Make the Download Count Column Sortable
 */
function projects_wp_sortable_columns( $columns ) {
    $columns['download_count'] = 'download_count';
    return $columns;
}
add_filter( 'manage_edit-project_sortable_columns', 'projects_wp_sortable_columns' );

/**
 * Handle Sorting for Download Count
 */
function projects_wp_sort_download_count_column( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'download_count' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_projects_wp_download_count' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}
add_action( 'pre_get_posts', 'projects_wp_sort_download_count_column' );

/**
 * Register custom REST API endpoint for projects.
 */
function projects_wp_register_custom_endpoint() {
    register_rest_route(
        'projects/v1', // Namespace and version
        '/projects', // Endpoint
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'projects_wp_get_projects_data',
            'permission_callback' => '__return_true',
        ]
    );
}
add_action( 'rest_api_init', 'projects_wp_register_custom_endpoint' );

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
 * Fetch the version number from the GitHub API.
 *
 * @param string $github_url The GitHub repository URL.
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

/**
 * Load custom templates for single and archive project views.
 */
function projects_wp_template_loader( $template ) {
    if ( is_singular( 'projects' ) ) {
        // Check for a single-projects.php template in the theme
        $theme_template = locate_template( 'single-projects.php' );
        return $theme_template ? $theme_template : plugin_dir_path( __FILE__ ) . 'templates/single-projects.php';
    }

    if ( is_post_type_archive( 'projects' ) ) {
        // Check for an archive-projects.php template in the theme
        $theme_template = locate_template( 'archive-projects.php' );
        return $theme_template ? $theme_template : plugin_dir_path( __FILE__ ) . 'templates/archive-projects.php';
    }

    if ( is_tax( 'project-type' ) ) {
        // Check for a taxonomy-project-type.php template in the theme
        $theme_template = locate_template( 'archive-project-type.php' );
        return $theme_template ? $theme_template : plugin_dir_path( __FILE__ ) . 'templates/taxonomy-project-type.php';
    }

    return $template;
}
add_filter( 'template_include', 'projects_wp_template_loader' );

/**
 * Enqueue styles for project single and archive templates.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_enqueue_project_styles() {
    if ( is_singular( 'projects' ) || is_post_type_archive( 'projects' ) ) {
        wp_enqueue_style(
            'projects-wp-styles',
            plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
            [],
            PROJECTS_FOR_WORDPRESS_VERSION
        );
    }

    if ( is_singular( 'projects' ) ) {
        wp_enqueue_script(
            'projects-wp-buttons',
            plugin_dir_url( __FILE__ ) . 'assets/js/buttons.js',
            [],
            PROJECTS_FOR_WORDPRESS_VERSION
        );
    }
}
add_action( 'wp_enqueue_scripts', 'projects_wp_enqueue_project_styles' );

/**
 * Enqueue admin styles.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_admin_styles() {
    wp_enqueue_style( 'projects-wp-admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin-styles.css' );
}
add_action( 'admin_enqueue_scripts', 'projects_wp_admin_styles' );

/**
 * Modify the number of posts per page for the 'projects' post type archive.
 *
 * Ensures only the 'projects' archive page is affected without changing global settings.
 *
 * @param WP_Query $query The WordPress query object.
 */
function projects_wp_custom_posts_per_page( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( is_post_type_archive( 'projects' ) ) {
        $query->set( 'posts_per_page', 12 );
    }
}
add_action( 'pre_get_posts', 'projects_wp_custom_posts_per_page', 1 );

/**
 * Modify the posts per page for the 'projects' post type archive in Full Site Editing (FSE) themes.
 *
 * This function ensures that the 'projects' post type archive displays 12 posts per page,
 * even when using an FSE theme with a Query Loop block.
 *
 * @param array  $query The existing query variables for the Query Loop block.
 * @param object $block The current block object (not used but available if needed).
 * 
 * @return array Modified query variables with 'posts_per_page' set for the projects archive.
 */
function modify_projects_posts_per_page_fse( $query, $block ) {
    // Ensure we are modifying the archive query for the 'projects' post type.
    if ( isset( $query['post_type'] ) && $query['post_type'] === 'projects' ) {
        $query['posts_per_page'] = 12;
    }

    return $query;
}
add_filter( 'query_loop_block_query_vars', 'modify_projects_posts_per_page_fse', 10, 2 );

/**
 * Add social sharing buttons with Tabler icons.
 *
 * @param string $content The post content.
 * 
 * @return void
 */
function projects_wp_social_sharing_buttons( $project_id ) {
    $url   = urlencode( get_permalink( $project_id ) );
    $title = urlencode( get_the_title( $project_id ) );

    echo '<div class="project-social-sharing">';
    
    // Facebook
    echo '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $url . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-facebook" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="0" y="0" width="24" height="24" fill="none"/><path d="M7 10v-3a1 1 0 0 1 1 -1h3v-4h4v4h3a1 1 0 0 1 1 1v3h-4v10h-4v-10h-3" /></svg>';
    echo '</a>';

    // X (Twitter)
    echo '<a href="https://twitter.com/intent/tweet?text=' . $title . '&url=' . $url . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 4l10 16m0 -16l-10 16" /></svg>';
    echo '</a>';

    // LinkedIn
    echo '<a href="https://www.linkedin.com/sharing/share-offsite/?url=' . $url . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-linkedin" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="4" width="16" height="16" rx="2" /><line x1="8" y1="11" x2="8" y2="16" /><line x1="8" y1="8" x2="8" y2="8.01" /><line x1="12" y1="16" x2="12" y2="11" /><path d="M16 16v-3a2 2 0 0 0 -4 0" /></svg>';
    echo '</a>';

    // Reddit
    echo '<a href="https://www.reddit.com/submit?url=' . $url . '&title=' . $title . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-reddit" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><path d="M12 12m-6 0a6 6 0 1 0 12 0a6 6 0 1 0 -12 0" /><path d="M12 12h0" /><path d="M8.5 13c.667 .667 1.333 1 2 1s1.333 -.333 2 -1" /></svg>';
    echo '</a>';

    // WhatsApp
    echo '<a href="https://wa.me/?text=' . $title . '%20' . $url . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-whatsapp" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l1.65 -4.75a9 9 0 1 1 3.85 3.85z" /><path d="M9 10c1 2 3 3 5 4" /><path d="M9 13c1 1 2 2 4 3" /></svg>';
    echo '</a>';

    // Pinterest
    echo '<a href="https://pinterest.com/pin/create/button/?url=' . $url . '&description=' . $title . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-pinterest" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="9" /><path d="M8 17c1 -2 1.5 -4 .5 -6s-.5 -4 .5 -6" /><path d="M15 11c.667 -1 1.667 -2.333 3 -4" /></svg>';
    echo '</a>';

    // Email
    echo '<a href="mailto:?subject=' . $title . '&body=' . $url . '" target="_blank" rel="noopener noreferrer">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-mail" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="#0073aa" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7l9 6l9 -6" /><rect x="3" y="5" width="18" height="14" rx="2" /></svg>';
    echo '</a>';

    echo '</div>';
}
add_action( 'projects_after_download_button', 'projects_wp_social_sharing_buttons', 999 );

/**
 * Schedule a daily cron job to send telemetry data.
 */
function projects_wp_schedule_telemetry_cron() {
    if ( ! wp_next_scheduled( 'projects_wp_send_telemetry_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'projects_wp_send_telemetry_cron' );
    }
}
add_action( 'wp', 'projects_wp_schedule_telemetry_cron' );

/**
 * Clear the scheduled cron job on plugin deactivation.
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

    // Prepare the payload for the third-party API
    $third_party_api_url = 'http://127.0.0.1:5000/api/telemetry';
    $payload = [
        'site_url' => home_url(),
        'data'     => $data['projects'],
    ];

    // Send the data to the third-party API
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
 */
function projects_wp_send_telemetry_on_save( $post_id, $post, $update ) {
    // Bail early if not 'projects' post type
    if ( 'projects' !== $post->post_type ) {
        return;
    }

    // Ensure telemetry data is sent
    projects_wp_send_telemetry_data();
}
add_action( 'save_post', 'projects_wp_send_telemetry_on_save', 10, 3 );
