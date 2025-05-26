<?php
get_header();

if (have_posts()) :
    while (have_posts()) : the_post(); ?>

        <article class="ec-event">
            <h1><?php the_title(); ?></h1>
            <div class="ec-event-content"><?php the_content(); ?></div>

            <?php
            // Даты мероприятия
            $start = get_post_meta(get_the_ID(), 'ec_event_start', true);
            $end = get_post_meta(get_the_ID(), 'ec_event_end', true);
            $address = get_post_meta(get_the_ID(), 'ec_event_address', true);

            // Таксономии
            $type = get_the_term_list(get_the_ID(), 'ec_event_type', '', ', ');
            $organizer = get_the_term_list(get_the_ID(), 'ec_organizer', '', ', ');
            $location = get_the_term_list(get_the_ID(), 'ec_location', '', ', ');
            ?>

            <div class="ec-event-meta">
                <?php if ($start) : ?>
                    <p><strong>Начало:</strong> <?php echo date('d.m.Y H:i', strtotime($start)); ?></p>
                <?php endif; ?>

                <?php if ($end) : ?>
                    <p><strong>Окончание:</strong> <?php echo date('d.m.Y H:i', strtotime($end)); ?></p>
                <?php endif; ?>

                <?php if ($type) : ?>
                    <p><strong>Тип:</strong> <?php echo $type; ?></p>
                <?php endif; ?>

                <?php if ($organizer) : ?>
                    <p><strong>Организатор:</strong> <?php echo $organizer; ?></p>
                <?php endif; ?>

                <?php if ($location) : ?>
                    <p><strong>Место проведения:</strong> <?php echo $location; ?></p>
                <?php endif; ?>

                <?php if ($address) : ?>
                    <p><strong>Адрес:</strong> <?php echo esc_html($address); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($address) : ?>
                <div id="yandex-map" style="width: 100%; height: 400px;"></div>
                <script>
                    window.ec_event_address = "<?php echo esc_js($address); ?>";
                </script>
            <?php endif; ?>
        </article>

    <?php endwhile;
endif;

get_footer();
