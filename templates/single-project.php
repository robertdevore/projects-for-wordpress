<?php
get_header();

$project_id    = get_the_ID();
$download_count = (int) get_post_meta( $project_id, '_projects_wp_download_count', true );
$github_url    = get_post_meta( $project_id, '_projects_wp_github_url', true );
$version       = 'Unknown'; // Default value if version is not found.

// Fetch the version number from the GitHub API if the URL is set.
if ( $github_url ) {
    $api_url = str_replace( 'https://github.com/', 'https://api.github.com/repos/', rtrim( $github_url, '/' ) ) . '/releases/latest';
    $response = wp_remote_get( $api_url );

    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['tag_name'] ) ) {
            $version = esc_html( $data['tag_name'] );
        }
    }
}
?>

<div class="project-single">
    <h1><?php the_title(); ?></h1>

    <div class="project-content">
        <?php the_content(); ?>
    </div>

    <div class="project-meta">
        <div class="meta-item">
            <strong><?php esc_html_e( 'Download Count:', 'projects-for-wordpress' ); ?></strong>
            <span class="count"><?php echo esc_html( $download_count ); ?></span>
        </div>
        <div class="meta-item">
            <strong><?php esc_html_e( 'Version:', 'projects-for-wordpress' ); ?></strong>
            <span class="version"><?php echo esc_html( $version ); ?></span>
        </div>
        <div class="meta-item">
            <strong><?php esc_html_e( 'GitHub Repository:', 'projects-for-wordpress' ); ?></strong>
            <?php if ( $github_url ) : ?>
                <a href="<?php echo esc_url( $github_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'View on GitHub', 'projects-for-wordpress' ); ?>
                </a>
            <?php else : ?>
                <?php esc_html_e( 'No repository linked.', 'projects-for-wordpress' ); ?>
            <?php endif; ?>
        </div>
    </div>

    <a href="<?php echo esc_url( site_url( '/download/' . $project_id ) ); ?>" class="button">
        <?php esc_html_e( 'Download Now', 'projects-for-wordpress' ); ?>
    </a>

    <?php
    // Hook to add content (e.g., social sharing buttons) below the download button.
    do_action( 'projects_after_download_button', $project_id );
    ?>
</div>

<style>
    .project-meta {
        margin: 20px 0;
        padding: 20px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .project-meta .meta-item {
        margin-bottom: 10px;
    }
    .project-meta .meta-item strong {
        display: inline-block;
        width: 150px;
    }
    .project-meta .meta-item .count,
    .project-meta .meta-item .version {
        font-weight: bold;
        color: #0073aa;
    }
    .project-meta .meta-item a {
        color: #0073aa;
        text-decoration: none;
    }
    .project-meta .meta-item a:hover {
        text-decoration: underline;
    }
</style>

<?php
get_footer();
