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

require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/robertdevore/projects-for-wordpress/',
	__FILE__,
	'projects-for-wordpress'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

// Current plugin version.
define( 'PROJECTS_FOR_WORDPRESS_VERSION', time() );

// Add the required files.
require 'admin/admin-settings.php';
require 'admin/cpt-taxonomy.php';
require 'admin/metabox.php';
require 'includes/helper-functions.php';
//require 'admin/telemetry-data.php';

/**
 * Flush rewrite rules on activation.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_flush_rewrite_rules() {
    projects_wp_register_cpt_and_taxonomy();
    projects_wp_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'projects_wp_flush_rewrite_rules' );

/**
 * Add rewrite rules for download endpoint.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_add_rewrite_rules() {
    add_rewrite_rule(
        '^download/([0-9]+)/?$',
        'index.php?project_download_id=$matches[1]',
        'top'
    );
}
add_action( 'init', 'projects_wp_add_rewrite_rules' );

/**
 * WP_Query Vars
 * 
 * @param mixed $vars
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_query_vars( $vars ) {
    $vars[] = 'project_download_id';
    return $vars;
}
add_filter( 'query_vars', 'projects_wp_query_vars' );

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
 * 
 * @since  1.0.0
 * @return mixed
 */
function projects_wp_add_download_count_column( $columns ) {
    $columns['download_count'] = __( 'Download Count', 'projects-wp' );
    return $columns;
}
add_filter( 'manage_projects_posts_columns', 'projects_wp_add_download_count_column' );

/**
 * Display Download Count in Admin Column
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_display_download_count_column( $column, $post_id ) {
    if ( 'download_count' === $column ) {
        $download_count = (int) get_post_meta( $post_id, '_projects_wp_download_count', true );
        echo $download_count ? esc_html( $download_count ) : __( '0', 'projects-wp' );
    }
}
add_action( 'manage_projects_posts_custom_column', 'projects_wp_display_download_count_column', 10, 2 );

/**
 * Make the Download Count Column Sortable
 * 
 * @since  1.0.0
 * @return mixed
 */
function projects_wp_sortable_columns( $columns ) {
    $columns['download_count'] = 'download_count';
    return $columns;
}
add_filter( 'manage_edit-projects_sortable_columns', 'projects_wp_sortable_columns' );

/**
 * Handle Sorting for Download Count
 * 
 * @since  1.0.0
 * @return void
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
 * 
 * @since  1.0.0
 * @return void
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
 * Load custom templates for single and archive project views.
 * 
 * @since  1.0.0
 * @return mixed
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
    if ( is_singular( 'projects' ) || is_post_type_archive( 'projects' ) || is_tax( 'project-type' ) ) {
        $is_fse_theme = wp_theme_has_theme_json() && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
    
        // Register stylesheet for both theme types
        wp_register_style(
            'projects-wp-styles',
            plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
            [],
            PROJECTS_FOR_WORDPRESS_VERSION
        );
    
        if ( $is_fse_theme ) {
            // Inline block style enqueue (needed for FSE themes)
            add_action( 'wp_footer', function () {
                echo '<style id="projects-wp-styles">';
                echo file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/css/style.css' );
                echo '</style>';
            } );
        } else {
            // Classic theme enqueue
            wp_enqueue_style( 'projects-wp-styles' );
        }    
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
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_custom_posts_per_page( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $projects_per_page = apply_filters( 'projects_wp_archives_projects_per_page', 12 );

    if ( is_post_type_archive( 'projects' ) ) {
        $query->set( 'posts_per_page', $projects_per_page );
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
 * 
 * @since  1.0.0
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
 * 
 * @since  1.0.0
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
