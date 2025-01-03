<?php
get_header();
?>
<div class="project-archive">
    <h1><?php esc_html_e( 'Projects Archive', 'projects-for-wordpress' ); ?></h1>
    <?php if ( have_posts() ) : ?>
        <div class="projects-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <div class="project-item">
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_post_thumbnail( 'medium' ); ?>
                    <p>
                        <strong><?php esc_html_e( 'Downloads:', 'projects-for-wordpress' ); ?></strong>
                        <?php echo (int) get_post_meta( get_the_ID(), '_projects_wp_download_count', true ); ?>
                    </p>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'No projects found.', 'projects-for-wordpress' ); ?></p>
    <?php endif; ?>
</div>
<?php
get_footer();
?>
