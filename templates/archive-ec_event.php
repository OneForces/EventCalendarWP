<?php
get_header(); ?>

<div class="ec-event-archive-grid">
    <div class="ec-event-list">
        <h2>Предстоящие мероприятия</h2>
        <?php echo do_shortcode('[event_list]'); ?>
    </div>

    <div class="ec-event-calendar">
        <h2>Календарь</h2>
        <?php echo do_shortcode('[event_calendar]'); ?>
    </div>
</div>

<?php get_footer(); ?>
