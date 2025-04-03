<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register Custom Post Type and Taxonomy
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_register_cpt_and_taxonomy() {
    register_post_type( 'projects', [
        'labels'        => [
            'name'          => esc_html__( 'Projects', 'projects-wp' ),
            'singular_name' => esc_html__( 'Project', 'projects-wp' ),
            'add_new_item'  => esc_html__( 'Add New Project', 'projects-wp' ),
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
            'name'          => esc_html__( 'Project Types', 'projects-wp' ),
            'singular_name' => esc_html__( 'Project Type', 'projects-wp' ),
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
        wp_insert_term( esc_html__( 'Plugin', 'projects-wp' ), 'project_type' );
    }
    if ( ! term_exists( 'theme', 'project_type' ) ) {
        wp_insert_term( esc_html__( 'Theme', 'projects-wp' ), 'project_type' );
    }
    if ( ! term_exists( 'pattern', 'project_type' ) ) {
        wp_insert_term( esc_html__( 'Pattern', 'projects-wp' ), 'project_type' );
    }
}
add_action( 'init', 'projects_wp_register_cpt_and_taxonomy' );