<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add settings page
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=projects',
        esc_html__( 'Settings', 'projects-wp' ),
        esc_html__( 'Settings', 'projects-wp' ),
        'manage_options',
        'projects-wp-settings',
        'projects_wp_render_settings_page'
    );
}
add_action( 'admin_menu', 'projects_wp_add_settings_page' );

/**
 * Render the settings page.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_render_settings_page() {
    // Handle settings save.
    if ( isset( $_POST['projects_wp_save_settings'] ) ) {
        projects_wp_save_settings();
    }

    if ( get_transient( 'projects_wp_settings_saved' ) ) {
        settings_errors( 'projects_wp_messages' );
        delete_transient( 'projects_wp_settings_saved' );
    }

    // Retrieve saved settings.
    $api_token       = get_option( 'projects_wp_github_api_token', '' );
    $share_telemetry = get_option( 'projects_wp_share_telemetry', '0' );

    // Templates Settings.
    $templates_settings = [
        'version'           => esc_html__( 'Version', 'projects-wp' ),
        'last_updated'      => esc_html__( 'Last Updated', 'projects-wp' ),
        'license'           => esc_html__( 'License', 'projects-wp' ),
        'language'          => esc_html__( 'Language', 'projects-wp' ),
        'downloads'         => esc_html__( 'Downloads', 'projects-wp' ),
        'forks'             => esc_html__( 'Forks', 'projects-wp' ),
        'stargazers_count'  => esc_html__( 'Stars', 'projects-wp' ),
        'open_issues_count' => esc_html__( 'Issues', 'projects-wp' ),
        'github_owner'      => esc_html__( 'GitHub Owner', 'projects-wp' ),
    ];

    // Archives Settings
    $archives_settings = [
        'archive_title'     => esc_html__( 'Archive Title', 'projects-wp' ),
        'project_title'     => esc_html__( 'Project Title', 'projects-wp' ),
        'project_excerpt'   => esc_html__( 'Project Excerpt', 'projects-wp' ),
        'project_buttons'   => esc_html__( 'Project Buttons', 'projects-wp' ),
    ];
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Projects for WordPress Settings', 'projects-wp' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'projects_wp_save_settings', 'projects_wp_settings_nonce' ); ?>

            <h2><?php esc_html_e( 'General Settings', 'projects-wp' ); ?></h2>
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

            <h2><?php esc_html_e( 'Templates Settings', 'projects-wp' ); ?></h2>
            <table class="form-table">
                <?php foreach ( $templates_settings as $key => $label ) :
                    $value = get_option( "projects_wp_templates_$key", '0' ); ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( $label ); ?></th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" name="projects_wp_templates_<?php esc_attr_e( $key ); ?>" value="1" <?php checked($value, '1'); ?> />
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h2><?php esc_html_e( 'Archives Settings', 'projects-wp' ); ?></h2>
            <table class="form-table">
                <?php foreach ( $archives_settings as $key => $label ) :
                    $value = get_option( "projects_wp_archives_$key", '0' ); ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( $label ); ?></th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" name="projects_wp_archives_<?php esc_attr_e($key); ?>" value="1" <?php checked($value, '1'); ?> />
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <input type="hidden" name="projects_wp_save_settings" value="1" />
            <?php submit_button( esc_html__( 'Save Settings', 'projects-wp' ) ); ?>
        </form>
    </div>
    <?php
}

/**
 * Save the settings from the settings page.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_save_settings() {
    if ( ! isset( $_POST['projects_wp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['projects_wp_settings_nonce'], 'projects_wp_save_settings' ) ) {
        return;
    }

    update_option( 'projects_wp_github_api_token', sanitize_text_field( $_POST['projects_wp_github_api_token'] ?? '' ) );
    update_option( 'projects_wp_share_telemetry', isset( $_POST['projects_wp_share_telemetry'] ) ? '1' : '0' );

    // Templates Settings Keys.
    $templates_keys = [
        'version',
        'last_updated',
        'license',
        'language',
        'downloads',
        'forks',
        'stargazers_count',
        'open_issues_count',
        'github_owner',
    ];
    foreach ( $templates_keys as $key ) {
        update_option( "projects_wp_templates_$key", isset( $_POST["projects_wp_templates_$key"] ) ? '1' : '0' );
    }

    // Archives Settings Keys.
    $archives_keys = [
        'archive_title',
        'project_title',
        'project_excerpt',
        'project_buttons',
    ];
    foreach ( $archives_keys as $key ) {
        update_option( "projects_wp_archives_$key", isset( $_POST["projects_wp_archives_$key"] ) ? '1' : '0' );
    }

    add_settings_error( 'projects_wp_messages', 'projects_wp_settings_saved', esc_html__( 'Settings saved successfully.', 'projects-wp' ), 'updated' );

    set_transient( 'projects_wp_settings_saved', true, 30 );
}
