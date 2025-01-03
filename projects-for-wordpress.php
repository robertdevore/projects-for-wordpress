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

/**
 * Current plugin version.
 */
define( 'PROJECTS_FOR_WORDPRESS_VERSION', '1.0.0' );

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
    register_post_type( 'project', [
        'labels'        => [
            'name'          => __( 'Projects', 'projects-wp' ),
            'singular_name' => __( 'Project', 'projects-wp' ),
            'add_new_item'  => __( 'Add New Project', 'projects-wp' ),
        ],
        'public'        => true,
        'has_archive'   => true,
        'supports'      => [ 'title', 'editor', 'thumbnail' ],
        'rewrite'       => [ 'slug' => 'project' ],
        'show_in_rest'  => true,
    ]);

    register_taxonomy( 'project_type', 'project', [
        'labels'            => [
            'name'          => __( 'Project Types', 'projects-wp' ),
            'singular_name' => __( 'Project Type', 'projects-wp' ),
        ],
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

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
        'project',
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

/**
 * Handle download redirect.
 */
/**
 * Handle Download Redirect and Increment Download Count
 */
function projects_wp_handle_download_redirect() {
    $project_id = get_query_var( 'project_download_id' );

    if ( $project_id ) {
        $github_url = get_post_meta( $project_id, '_projects_wp_github_url', true );
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
 * Add settings page for GitHub API token.
 */
function projects_wp_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=project',
        __( 'Settings', 'projects-wp' ),
        __( 'Settings', 'projects-wp' ),
        'manage_options',
        'projects-wp-settings',
        'projects_wp_render_settings_page'
    );
}
add_action( 'admin_menu', 'projects_wp_add_settings_page' );

function projects_wp_render_settings_page() {
    if ( isset( $_POST['projects_wp_save_settings'] ) && check_admin_referer( 'projects_wp_save_settings' ) ) {
        $api_token = sanitize_text_field( $_POST['projects_wp_github_api_token'] );
        update_option( 'projects_wp_github_api_token', $api_token );
        echo '<div class="updated"><p>' . __( 'Settings saved.', 'projects-wp' ) . '</p></div>';
    }
    $api_token = get_option( 'projects_wp_github_api_token', '' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Projects for WordPress Settings', 'projects-wp' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'projects_wp_save_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="projects_wp_github_api_token"><?php esc_html_e( 'GitHub API Token', 'projects-wp' ); ?></label>
                    </th>
                    <td>
                        <input type="password" id="projects_wp_github_api_token" name="projects_wp_github_api_token" value="<?php echo esc_attr( $api_token ); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'projects-wp' ) ); ?>
        </form>
    </div>
    <?php
}

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
 * Add custom fields to REST API responses for projects.
 */
function projects_wp_add_rest_fields() {
    register_rest_field( 
        'project', 
        'download_count', 
        [
            'get_callback'    => function( $post ) {
                return (int) get_post_meta( $post['id'], '_projects_wp_download_count', true );
            },
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'Download count of the project.', 'projects-wp' ),
                'type'        => 'integer',
                'context'     => [ 'view', 'edit' ],
            ],
        ]
    );

    register_rest_field( 
        'project', 
        'github_url', 
        [
            'get_callback'    => function( $post ) {
                return get_post_meta( $post['id'], '_projects_wp_github_url', true );
            },
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'GitHub repository URL of the project.', 'projects-wp' ),
                'type'        => 'string',
                'context'     => [ 'view', 'edit' ],
            ],
        ]
    );
}
add_action( 'rest_api_init', 'projects_wp_add_rest_fields' );

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
        'post_type'      => 'project',
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
    if ( is_singular( 'project' ) ) {
        // Check for a single-project.php template in the theme
        $theme_template = locate_template( 'single-project.php' );
        return $theme_template ? $theme_template : plugin_dir_path( __FILE__ ) . 'templates/single-project.php';
    }

    if ( is_post_type_archive( 'project' ) ) {
        // Check for an archive-project.php template in the theme
        $theme_template = locate_template( 'archive-project.php' );
        return $theme_template ? $theme_template : plugin_dir_path( __FILE__ ) . 'templates/archive-project.php';
    }

    return $template;
}
add_filter( 'template_include', 'projects_wp_template_loader' );

/**
 * Enqueue styles for the plugin.
 */
function projects_wp_enqueue_styles() {
    wp_enqueue_style(
        'projects-wp',
        plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
        [],
        PROJECTS_FOR_WORDPRESS_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'projects_wp_enqueue_styles' );

/**
 * Add social sharing buttons with Tabler icons.
 *
 * @param string $content The post content.
 * @return string Modified post content.
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
