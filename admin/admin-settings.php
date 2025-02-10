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
        __( 'Settings', 'projects-wp' ),
        __( 'Settings', 'projects-wp' ),
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
    // Handle settings save
    if ( isset( $_POST['projects_wp_save_settings'] ) ) {
        projects_wp_save_settings();
    }

    if ( get_transient( 'projects_wp_settings_saved' ) ) {
        settings_errors( 'projects_wp_messages' );
        delete_transient( 'projects_wp_settings_saved' );
    }

    // Retrieve saved settings
    $api_token       = get_option( 'projects_wp_github_api_token', '' );
    $share_telemetry = get_option( 'projects_wp_share_telemetry', '0' );

    // Templates Settings.
    $templates_settings = [
        'version'           => __( 'Version', 'projects-wp' ),
        'last_updated'      => __( 'Last Updated', 'projects-wp' ),
        'license'           => __( 'License', 'projects-wp' ),
        'language'          => __( 'Language', 'projects-wp' ),
        'downloads'         => __( 'Downloads', 'projects-wp' ),
        'forks'             => __( 'Forks', 'projects-wp' ),
        'stargazers_count'  => __( 'Stars', 'projects-wp' ),
        'open_issues_count' => __( 'Issues', 'projects-wp' ),
        'github_owner'      => __( 'GitHub Owner', 'projects-wp' ),
    ];

    // Archives Settings
    $archives_settings = [
        'archive_title'     => __( 'Archive Title', 'projects-wp' ),
        'project_title'     => __( 'Project Title', 'projects-wp' ),
        'project_excerpt'   => __( 'Project Excerpt', 'projects-wp' ),
        'project_buttons'   => __( 'Project Buttons', 'projects-wp' ),
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
                <tr>
                    <th scope="row"><?php esc_html_e( 'Share Telemetry Data', 'projects-wp' ); ?></th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" id="projects_wp_share_telemetry" name="projects_wp_share_telemetry" value="1" <?php checked( $share_telemetry, '1' ); ?> />
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Allow sharing telemetry data about your projects to help improve the plugin.', 'projects-wp' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Templates Settings', 'projects-wp' ); ?></h2>
            <table class="form-table">
                <?php foreach ($templates_settings as $key => $label) :
                    $value = get_option( "projects_wp_templates_$key", '0' ); ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" name="projects_wp_templates_<?php echo esc_attr($key); ?>" value="1" <?php checked($value, '1'); ?> />
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h2><?php esc_html_e( 'Archives Settings', 'projects-wp' ); ?></h2>
            <table class="form-table">
                <?php foreach ($archives_settings as $key => $label) :
                    $value = get_option( "projects_wp_archives_$key", '0' ); ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" name="projects_wp_archives_<?php echo esc_attr($key); ?>" value="1" <?php checked($value, '1'); ?> />
                                <span class="slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <input type="hidden" name="projects_wp_save_settings" value="1" />
            <?php submit_button( __( 'Save Settings', 'projects-wp' ) ); ?>
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

    // Templates Settings Keys
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

    // Archives Settings Keys
    $archives_keys = [
        'archive_title',
        'project_title',
        'project_excerpt',
        'project_buttons',
    ];
    foreach ($archives_keys as $key) {
        update_option( "projects_wp_archives_$key", isset($_POST["projects_wp_archives_$key"]) ? '1' : '0' );
    }

    add_settings_error( 'projects_wp_messages', 'projects_wp_settings_saved', __( 'Settings saved successfully.', 'projects-wp' ), 'updated' );

    set_transient( 'projects_wp_settings_saved', true, 30 );
}
