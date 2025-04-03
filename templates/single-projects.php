<?php
// Check if the theme is an FSE (block theme)
$is_fse_theme = wp_theme_has_theme_json() && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( $is_fse_theme ) {
    wp_head();
    echo do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
} else {
    get_header();
}

$settings       = projects_wp_settings();
$project_id     = get_the_ID();
$download_count = (int) get_post_meta( $project_id, '_projects_wp_download_count', true );
$github_url     = get_post_meta( $project_id, '_projects_wp_github_url', true );
$version        = 'Unknown';
$github_data    = projects_wp_get_github_data( $github_url );

// Extract specific GitHub data.
$owner_avatar = $github_data['owner']['avatar_url'] ?? '';
$owner_name   = $github_data['owner']['login'] ?? '';
$owner_url    = $github_data['owner']['html_url'] ?? '';
$last_updated = $github_data['updated_at'] ?? '';
$language     = $github_data['language'] ?? '';
$license      = $github_data['license']['name'] ?? 'None';

$owner = projects_wp_github_owner( $owner_name );

// Fetch the version number from the GitHub API if the URL is set.
if ( $github_url ) {
    // Retrieve the GitHub API token from the plugin settings.
    $github_token = get_option( 'projects_wp_github_api_token', '' );

    // Prepare the API URL.
    $api_url = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) ) . '/releases/latest';

    // Check if the response is cached.
    $transient_key = 'github_api_response_' . md5( $api_url );
    $response      = get_transient( $transient_key );

    if ( false === $response ) {
        // Prepare the request arguments.
        $args = [];
        if ( ! empty( $github_token ) ) {
            $args['headers'] = [
                'Authorization' => 'token ' . $github_token,
            ];
        }

        // Make the API request.
        $response = wp_remote_get( $api_url, $args );

        // Cache the response for 1 hour if the request is successful.
        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            set_transient( $transient_key, $response, HOUR_IN_SECONDS );
        }
    }

    // Handle the response.
    if ( is_wp_error( $response ) ) {
        error_log( 'GitHub API error: ' . $response->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        error_log( 'GitHub API response code: ' . $response_code );
        error_log( 'GitHub API response body: ' . $response_body );

        if ( $response_code === 200 ) {
            $data = json_decode( $response_body, true );

            // Check if tag_name exists in the response.
            if ( isset( $data['tag_name'] ) ) {
                $version = esc_html( $data['tag_name'] );
            }
        } else {
            error_log( "GitHub API error: HTTP $response_code for URL $api_url" );
        }
    }
}

// Format last updated date.
if ( $last_updated ) {
    $last_updated = date_i18n( get_option( 'date_format' ), strtotime( $last_updated ) );
}
?>

<body <?php body_class(); ?>>

<div class="project-single-container">
    <div class="project-header">
        <div class="project-banner">
            <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'full' ); ?>
            <?php else : ?>
                <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/images/placeholder-banner.jpg' ); ?>" alt="<?php esc_attr_e( 'Project Banner', 'projects-wp' ); ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="project-main-content">
        <div class="project-description">
            <h1 class="project-title">
                <?php 
                    do_action( 'projects_wp_single_title_before' );
                    the_title(); 
                    do_action( 'projects_wp_single_title_after' );
                ?>
            </h1>
            <?php
                do_action( 'projects_wp_single_content_before' );
                the_content();
                do_action( 'projects_wp_single_content_after' );
            ?>
        </div>
        <div class="project-sidebar">
            <div class="project-buttons">
                <a href="<?php echo esc_url( site_url( '/download/' . $project_id ) ); ?>" class="button project-download-button">
                    <?php esc_html_e( 'Download Now', 'projects-wp' ); ?>
                </a>
                <?php if ( $github_url ) : ?>
                    <a href="<?php echo esc_url( $github_url ); ?>" class="button project-github-button" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'View on GitHub', 'projects-wp' ); ?>
                    </a>
                <?php else : ?>
                    <p><?php esc_html_e( 'GitHub Repository:', 'projects-wp' ); ?> <?php esc_html_e( 'No repository linked.', 'projects-wp' ); ?></p>
                <?php endif; ?>
            </div>

            <div class="project-meta">
                <?php
                    do_action( 'projects_wp_single_meta_before' );
                ?>
                <table class="project-meta-table">
                    <tbody>
                        <?php if ( $settings['templates']['version'] ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Version:', 'projects-wp' ); ?></th>
                            <td><?php esc_html_e( $version ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( $settings['templates']['last_updated'] && $last_updated ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Updated:', 'projects-wp' ); ?></th>
                                <td><?php esc_html_e( $last_updated ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $settings['templates']['license'] && $license ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'License:', 'projects-wp' ); ?></th>
                                <td><?php esc_html_e( $license ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $settings['templates']['language'] && $language ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Language:', 'projects-wp' ); ?></th>
                                <td><?php esc_html_e( $language ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $settings['templates']['downloads'] ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Downloads:', 'projects-wp' ); ?></th>
                            <td><?php esc_html_e( $download_count ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php
                        if ( ! empty( $github_data ) ) :
                            if ( isset( $github_data['stargazers_count'] ) && $settings['templates']['stargazers_count'] ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Stars:', 'projects-wp' ); ?></th>
                                    <td><?php esc_html_e( $github_data['stargazers_count'] ); ?></td>
                                </tr>
                            <?php endif;
                            if ( isset( $github_data['forks_count'] ) && $settings['templates']['forks'] ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Forks:', 'projects-wp' ); ?></th>
                                    <td><?php esc_html_e( $github_data['forks_count'] ); ?></td>
                                </tr>
                            <?php endif;
                            if ( isset( $github_data['open_issues_count'] ) && $settings['templates']['open_issues_count'] ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Issues:', 'projects-wp' ); ?></th>
                                    <td><?php esc_html_e( $github_data['open_issues_count'] ); ?></td>
                                </tr>
                            <?php endif;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $settings['templates']['github_owner'] && $owner_avatar && $owner_name ) : ?>
                <a href="<?php esc_html_e( $github_url ); ?>">
                <div class="project-owner">
                    <span>
                        <img src="<?php echo esc_url( $owner_avatar ); ?>" alt="<?php esc_attr_e( $owner_name ); ?>" class="owner-avatar" style="width: 50px; height: 50px; border-radius: 50%;" />
                    </span>
                    <span>
                        <strong>
                            <a href="<?php echo esc_url( $owner_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( $owner_name ); ?></a>
                        </strong>
                    </span>
                    <span>
                        <a class="github-button" href="https://github.com/<?php esc_html_e( $owner_name ); ?>" data-show-count="true" data-color-scheme="no-preference: light; light: light; dark: dark;" data-size="large" aria-label="Follow @<?php esc_html_e( $owner_name ); ?> on GitHub"><?php esc_attr_e( 'Follow', 'projects-wp' ) ?></a>
                    </span>
                </div>
            <?php endif; ?>
            <?php
                do_action( 'projects_wp_single_meta_after' );
            ?>
        </div>
    </div>
</div>

<?php
if ( $is_fse_theme ) {
    wp_footer();
    echo do_blocks( '<!-- wp:template-part {"slug":"footer"} /-->' );
} else {
    get_footer();
}
?>
</body>

<?php

/**
 * Enqueues theme styles specifically for single and archive pages of the "projects" post type.
 * 
 * @since  1.0.0
 * @return void
 */
function projects_wp_enqueue_theme_styles() {
    if ( is_singular( 'projects' ) || is_post_type_archive( 'projects' ) ) {
        // Enqueue the theme's main stylesheet.
        wp_enqueue_style( 'theme-style', get_stylesheet_uri() );

        // Enqueue additional theme styles if needed.
        wp_enqueue_style( 'theme-header-style', get_template_directory_uri() . '/css/header.css' );
        wp_enqueue_style( 'theme-footer-style', get_template_directory_uri() . '/css/footer.css' );
    }
}
add_action( 'wp_enqueue_scripts', 'projects_wp_enqueue_theme_styles' );
