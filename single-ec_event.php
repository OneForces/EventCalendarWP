<?php get_header(); ?>

<main id="primary" class="site-main">
<?php echo '<div style="background:red;color:white;padding:5px;">ШАБЛОН: single-ec_event.php</div>'; ?>
<?php
if (have_posts()) :
    while (have_posts()) : the_post();

        $event_id = get_the_ID();

        // Метаполя даты
        $start = get_post_meta($event_id, 'ec_event_start', true);
        $end = get_post_meta($event_id, 'ec_event_end', true);

        // Форматированные даты
        $start_formatted = $start ? date_i18n('d.m.Y H:i', strtotime($start)) : null;
        $end_formatted = $end ? date_i18n('d.m.Y H:i', strtotime($end)) : null;

        // Таксономии
        $organizers = get_the_term_list($event_id, 'ec_organizer', '', ', ');
        $locations = get_the_term_list($event_id, 'ec_location', '', ', ');
?>

    <article id="post-<?php the_ID(); ?>" <?php post_class('ec-event'); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>

            <ul class="event-meta">
                <?php if ($start_formatted): ?>
                    <li><strong>Начало:</strong> <?php echo esc_html($start_formatted); ?></li>
                <?php endif; ?>

                <?php if ($end_formatted): ?>
                    <li><strong>Окончание:</strong> <?php echo esc_html($end_formatted); ?></li>
                <?php endif; ?>

                <?php if ($organizers): ?>
                    <li><strong>Организатор:</strong> <?php echo wp_kses_post($organizers); ?></li>
                <?php endif; ?>

                <?php if ($locations): ?>
                    <li><strong>Место проведения:</strong> <?php echo wp_kses_post($locations); ?></li>
                <?php endif; ?>
            </ul>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    </article>

<?php
    endwhile;
endif;
?>
</main>

<?php get_footer(); ?>
