<?php
// Check if the theme is an FSE (block theme)
$is_fse_theme = wp_theme_has_theme_json() && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( $is_fse_theme ) {
    wp_head();
    echo do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
} else {
    get_header();
}

$settings = projects_wp_settings();

?>
<div class="project-archive">
    <?php if ( $settings['archives']['archive_title'] ) : ?>
        <h1><?php esc_html_e( 'Projects', 'projects-wp' ); ?></h1>
    <?php endif; ?>
    <?php if ( have_posts() ) : ?>
        <div class="projects-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <div class="project-item">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail( 'full' ); ?>
                    </a>
                    <?php if ( $settings['archives']['project_title'] ) : ?>
                        <h2 class="project-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                    <?php endif; ?>
                    <?php if ( $settings['archives']['project_excerpt'] ) : ?>
                        <?php the_excerpt(); ?>
                    <?php endif; ?>
                    <?php if ( $settings['archives']['project_buttons'] ) : ?>
                        <div class="project-buttons">
                            <a href="<?php echo esc_url( site_url( '/download/' . get_the_ID() ) ); ?>" class="button project-download-button">
                                <?php esc_html_e( 'Download', 'projects-wp' ); ?>
                            </a>
                            <a href="<?php the_permalink(); ?>" class="button project-view-button">
                                <?php esc_html_e( 'View Project', 'projects-wp' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Numbered Pagination -->
        <div class="pagination">
            <?php
            echo paginate_links( array(
                'total'        => $wp_query->max_num_pages,
                'current'      => max( 1, get_query_var( 'paged' ) ),
                'prev_text'    => __( '&laquo; Previous', 'projects-wp' ),
                'next_text'    => __( 'Next &raquo;', 'projects-wp' ),
                'type'         => 'list', // Outputs a <ul> list for better styling
            ) );
            ?>
        </div>

    <?php else : ?>
        <p><?php esc_html_e( 'No projects found.', 'projects-wp' ); ?></p>
    <?php endif; ?>
</div>

<?php
if ( $is_fse_theme ) {
    wp_footer();
    echo do_blocks( '<!-- wp:template-part {"slug":"footer"} /-->' );
} else {
    get_footer();
}
