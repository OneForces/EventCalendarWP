<?php
/**
 * single-ec_event.php
 *
 * Шаблон одиночного события ec_event.
 */

get_header();

if ( have_posts() ) :
    while ( have_posts() ) : the_post();
        $post_id = get_the_ID();
?>

<div id="ec-theme-wrapper" data-theme="<?php echo esc_attr( get_option( 'ec_theme', 'auto' ) ); ?>">
<article id="post-<?php echo $post_id; ?>" <?php post_class('ec-single-event'); ?> style="max-width: 800px; margin: 40px auto; padding: 2rem; background-color: var(--ec-highlight); color: var(--ec-text); border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">

    <!-- Хлебные крошки -->
    <div class="ec-breadcrumbs" style="margin-bottom: 1rem; font-size: 14px;">
        <a href="<?php echo esc_url(home_url()); ?>">Главная</a> &raquo;
        <a href="<?php echo esc_url(get_post_type_archive_link('ec_event')); ?>">Календарь</a> &raquo;
        <span><?php the_title(); ?></span>
    </div>

    <!-- Заголовок -->
    <h1 style="margin-bottom: 1rem;"><?php the_title(); ?></h1>

    <!-- Контент -->
    <div class="entry-content" style="margin-bottom: 1.5rem;">
        <?php the_content(); ?>
    </div>

    <div class="ec-event-meta" style="line-height: 1.8;">
        <?php
        $all_day = get_post_meta( $post_id, 'ec_event_all_day', true );
        $date_format = $all_day ? 'd.m.Y' : 'd.m.Y H:i';

        $event_start = get_post_meta( $post_id, 'ec_event_start', true );
        if ( $event_start ) {
            echo '<p><strong>Начало:</strong> ' . esc_html( date_i18n( $date_format, strtotime( $event_start ) ) ) . '</p>';
        }

        $event_end = get_post_meta( $post_id, 'ec_event_end', true );
        if ( $event_end ) {
            echo '<p><strong>Окончание:</strong> ' . esc_html( date_i18n( $date_format, strtotime( $event_end ) ) ) . '</p>';
        }


        $types = get_the_terms( $post_id, 'ec_event_type' );
        if ( ! empty( $types ) && ! is_wp_error( $types ) ) {
            $type = $types[0];
            echo '<p><strong>Тип:</strong> <a href="' . esc_url( get_term_link( $type ) ) . '">' . esc_html( $type->name ) . '</a></p>';
        }

        $organizers = get_the_terms( $post_id, 'ec_organizer' );
        if ( ! empty( $organizers ) && ! is_wp_error( $organizers ) ) {
            $org = $organizers[0];
            echo '<p><strong>Организатор:</strong> <a href="' . esc_url( get_term_link( $org ) ) . '">' . esc_html( $org->name ) . '</a></p>';

            $org_phone   = get_term_meta( $org->term_id, 'ec_organizer_phone', true );
            $org_email   = get_term_meta( $org->term_id, 'ec_organizer_email', true );
            $org_website = get_term_meta( $org->term_id, 'ec_organizer_website', true );

            if ( $org_phone ) {
                echo '<p><strong>Телефон:</strong> ' . esc_html( $org_phone ) . '</p>';
            }
            if ( $org_email ) {
                echo '<p><strong>Email:</strong> <a href="mailto:' . esc_attr( $org_email ) . '">' . esc_html( $org_email ) . '</a></p>';
            }
            if ( $org_website ) {
                echo '<p><strong>Веб-сайт:</strong> <a href="' . esc_url( $org_website ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $org_website ) . '</a></p>';
            }
        }

        $locs = get_the_terms( $post_id, 'ec_location' );
        if ( ! empty( $locs ) && ! is_wp_error( $locs ) ) {
            $loc = $locs[0];
            echo '<p><strong>Место проведения:</strong> <a href="' . esc_url( get_term_link( $loc ) ) . '">' . esc_html( $loc->name ) . '</a></p>';
        }

        $region  = get_post_meta( $post_id, 'ec_event_region', true );
        $city    = get_post_meta( $post_id, 'ec_event_city', true );
        $address = get_post_meta( $post_id, 'ec_event_address', true );

        $full_address = '';
        if ( $region )  $full_address .= $region . ', ';
        if ( $city )    $full_address .= $city . ', ';
        if ( $address ) $full_address .= $address;

        if ( $full_address ) {
            echo '<p><strong>Адрес:</strong> ' . esc_html( rtrim($full_address, ', ') ) . '</p>';
            echo '<div id="yamap-container" data-address="' . esc_attr( $full_address ) . '" style="width: 100%; height: 400px; margin-top: 20px;"></div>';
        }
        ?>

        <!-- Кнопка ICS -->
        <p>
            <a href="<?php echo esc_url( home_url( '/?ec_export_ics=1' ) ); ?>" class="ec-download-ics" style="display:inline-block;margin-top:1rem;background:var(--ec-button-bg);color:var(--ec-button-text);padding:8px 16px;border-radius:5px;text-decoration:none;">
                📥 Скачать календарь (ICS)
            </a>
        </p>
    </div>

</article>
</div>

<?php if ( $full_address ) : ?>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=<?php echo esc_attr( get_option('ec_yandex_api_key') ); ?>" type="text/javascript"></script>
    <script src="<?php echo plugin_dir_url( __FILE__ ); ?>assets/js/yamap.js"></script>
<?php endif; ?>

<?php
    endwhile;
endif;

get_footer();
