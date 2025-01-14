<?php
get_header();
?>
<div class="project-archive">
    <h1>
        <?php
        // Display the taxonomy title.
        single_term_title();
        ?>
    </h1>
    <?php if ( have_posts() ) : ?>
        <div class="projects-grid">
            <?php while ( have_posts() ) : the_post(); ?>
                <div class="project-item">
                    <a href="<?php the_permalink(); ?>">
                        <?php 
                        // Display the post thumbnail.
                        if ( has_post_thumbnail() ) {
                            the_post_thumbnail( 'large' ); // Full-width image
                        } else {
                            echo '<img src="' . esc_url( plugin_dir_url( __FILE__ ) . 'assets/images/placeholder-thumbnail.jpg' ) . '" alt="' . esc_attr__( 'Project Thumbnail', 'projects-for-wordpress' ) . '">';
                        }
                        ?>
                    </a>
                    <h2 class="project-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <?php echo the_excerpt(); ?>
                    <div class="project-buttons">
                        <a href="<?php echo esc_url( site_url( '/download/' . get_the_ID() ) ); ?>" class="button project-download-button">
                            <?php esc_html_e( 'Download', 'projects-for-wordpress' ); ?>
                        </a>
                        <a href="<?php the_permalink(); ?>" class="button project-view-button">
                            <?php esc_html_e( 'View Project', 'projects-for-wordpress' ); ?>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'No projects found for this type.', 'projects-for-wordpress' ); ?></p>
    <?php endif; ?>
</div>

<?php
get_footer();
