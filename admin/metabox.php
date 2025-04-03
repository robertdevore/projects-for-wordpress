<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add GitHub Repository URL meta box.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_add_meta_boxes() {
    add_meta_box(
        'projects_wp_github_url',
        esc_html__( 'GitHub URL', 'projects-wp' ),
        'projects_wp_render_meta_box',
        'projects',
        'side'
    );
}
add_action( 'add_meta_boxes', 'projects_wp_add_meta_boxes' );

/**
 * Renders the GitHub Repository URL meta box for the Projects WP plugin.
 *
 * Outputs a label and input field for setting the GitHub repository URL, 
 * as well as a nonce for security.
 *
 * @param WP_Post $post The post object currently being edited.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_render_meta_box( $post ) {
    wp_nonce_field( 'projects_wp_save_meta_box', 'projects_wp_meta_box_nonce' );
    $github_url = get_post_meta( $post->ID, '_projects_wp_github_url', true );
    echo '<label for="projects_wp_github_url">' . __( 'GitHub Repository URL:', 'projects-wp' ) . '</label>';
    echo '<input type="url" id="projects_wp_github_url" name="projects_wp_github_url" value="' . esc_attr( $github_url ) . '" style="width: 100%;" />';
}

/**
 * Saves the GitHub repository URL meta box data for the Projects WP plugin.
 *
 * @param int $post_id The ID of the post currently being saved.
 *
 * @since  1.0.0
 * @return void
 */
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
