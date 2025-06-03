<?php
/**
 * Шаблон архива CPT "ec_event"
 */
get_header(); ?>
<div id="ec-theme-wrapper" data-theme="<?php echo esc_attr(get_option('ec_theme', 'auto')); ?>">

  <div class="ec-calendar-layout" style="display: flex; flex-wrap: wrap; gap: 2rem; max-width: 1200px; margin: 40px auto; padding: 0 20px;">

    <!-- Список событий -->
    <div class="ec-event-list" style="flex: 1 1 350px;">
      <!-- ✅ Хлебные крошки -->
      <div class="ec-breadcrumbs" style="margin-bottom: 15px; font-size: 14px;">
        <a href="<?php echo esc_url(home_url()); ?>">Главная</a> &raquo; Календарь мероприятий
      </div>

      <h2>Предстоящие мероприятия</h2>
      <?php echo do_shortcode('[event_list]'); ?>
    </div>

    <!-- Календарь с фильтрами -->
    <div class="ec-event-calendar" style="flex: 2 1 600px;">
      <h2>Календарь</h2>

      <!-- 🔍 Форма фильтрации + календарь через шорткод -->
      <?php echo do_shortcode('[event_calendar embed_only="false"]'); ?>

    </div>

  </div>

</div>
<?php get_footer(); ?>
