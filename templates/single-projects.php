<?php
get_header();

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

// Fetch the version number from the GitHub API if the URL is set.
if ( $github_url ) {
    $api_url = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) ) . '/releases/latest';
    $response = wp_remote_get( $api_url );

    if ( ! is_wp_error( $response ) ) {
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            // Check if tag_name exists in the response.
            if ( isset( $data['tag_name'] ) ) {
                $version = esc_html( $data['tag_name'] );
            }
        } else {
            // Log the error for debugging purposes.
            error_log( "GitHub API error: HTTP $response_code for URL $api_url" );
        }
    } else {
        // Log the error for debugging purposes.
        error_log( 'GitHub API error: ' . $response->get_error_message() );
    }
}

// Format last updated date
if ( $last_updated ) {
    $last_updated = date_i18n( get_option( 'date_format' ), strtotime( $last_updated ) );
}
?>

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
            <h1 class="project-title"><?php the_title(); ?></h1>
            <?php the_content(); ?>
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

            <?php if ( $owner_avatar && $owner_name ) : ?>
                <div class="project-owner">
                    <img src="<?php echo esc_url( $owner_avatar ); ?>" alt="<?php echo esc_attr( $owner_name ); ?>" class="owner-avatar" style="width: 50px; height: 50px; border-radius: 50%;" />
                    <p>
                        <strong><?php esc_html_e( 'Owner:', 'projects-wp' ); ?></strong>
                        <a href="<?php echo esc_url( $owner_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $owner_name ); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="project-meta">
                <table class="project-meta-table">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Version:', 'projects-wp' ); ?></th>
                            <td><?php echo esc_html( $version ); ?></td>
                        </tr>
                        <?php if ( $last_updated ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Updated:', 'projects-wp' ); ?></th>
                                <td><?php echo esc_html( $last_updated ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $license ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'License:', 'projects-wp' ); ?></th>
                                <td><?php echo esc_html( $license ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( $language ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Language:', 'projects-wp' ); ?></th>
                                <td><?php echo esc_html( $language ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php esc_html_e( 'Downloads:', 'projects-wp' ); ?></th>
                            <td><?php echo esc_html( $download_count ); ?></td>
                        </tr>
                        <?php
                        if ( ! empty( $github_data ) ) :
                            if ( isset( $github_data['stargazers_count'] ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Stars:', 'projects-wp' ); ?></th>
                                    <td><?php echo esc_html( $github_data['stargazers_count'] ); ?></td>
                                </tr>
                            <?php endif;
                            if ( isset( $github_data['forks_count'] ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Forks:', 'projects-wp' ); ?></th>
                                    <td><?php echo esc_html( $github_data['forks_count'] ); ?></td>
                                </tr>
                            <?php endif;
                            if ( isset( $github_data['open_issues_count'] ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Issues:', 'projects-wp' ); ?></th>
                                    <td><?php echo esc_html( $github_data['open_issues_count'] ); ?></td>
                                </tr>
                            <?php endif;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
