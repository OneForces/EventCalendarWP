<?php
/**
 * calendar-shortcode.php
 * 
 * Шорткод для отображения календаря мероприятий с фильтрацией.
 */

function ec_render_calendar_shortcode() {
	ob_start();

	$theme = get_option( 'ec_theme', 'auto' );

	echo '<div class="ec-page-header">';
	echo '<div class="ec-breadcrumbs"><a href="' . esc_url(home_url()) . '">Главная</a> &raquo; <span>Календарь мероприятий</span></div>';
	echo '<h1 class="ec-page-title">Актуальные мероприятия</h1>';
	echo '</div>';

	$selected_type  = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';
	$selected_org   = isset($_GET['organizer']) ? sanitize_text_field($_GET['organizer']) : '';
	$selected_start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : '';
	$selected_end   = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : '';
	$search_query   = isset($_GET['ec_search']) ? sanitize_text_field($_GET['ec_search']) : '';

	$today = date('Y-m-d');

	$args = array(
		'post_type'      => 'ec_event',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'meta_value',
		'meta_key'       => 'ec_event_start',
		'order'          => 'ASC',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => 'ec_event_end',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			)
		),
	);

	if ( $selected_start ) {
		$args['meta_query'][] = array(
			'key'     => 'ec_event_start',
			'value'   => $selected_start,
			'compare' => '>=',
			'type'    => 'DATE',
		);
	}

	if ( $selected_end ) {
		$args['meta_query'][] = array(
			'key'     => 'ec_event_end',
			'value'   => $selected_end,
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}

	if ( $selected_type ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'ec_event_type',
			'field'    => 'slug',
			'terms'    => $selected_type,
		);
	}

	if ( $selected_org ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'ec_organizer',
			'field'    => 'slug',
			'terms'    => $selected_org,
		);
	}

	if ( $search_query ) {
		$args['s'] = $search_query;
	}

	$query = new WP_Query($args);

	$events = array();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$start = get_post_meta(get_the_ID(), 'ec_event_start', true);
			$end   = get_post_meta(get_the_ID(), 'ec_event_end', true);

			if ( $start ) {
				$events[] = array(
					'title' => get_the_title(),
					'start' => $start,
					'end'   => $end ?: $start,
					'url'   => get_permalink(),
				);
			}
		}
		wp_reset_postdata();
	}

	$types = get_terms( array('taxonomy' => 'ec_event_type', 'hide_empty' => false) );
	$orgs  = get_terms( array('taxonomy' => 'ec_organizer', 'hide_empty' => false) );

	$current_url = esc_url( remove_query_arg( array( 'event_type', 'organizer', 'start', 'end', 'ec_search' ) ) );

	wp_enqueue_script( 'fullcalendar-js' );
	wp_enqueue_style( 'fullcalendar-css' );
	?>

	<div id="ec-theme-wrapper" data-theme="<?php echo esc_attr( $theme ); ?>">
	<div id="ec-calendar-wrapper" class="ec-calendar-layout">

		<div id="ec-calendar" class="ec-calendar" data-theme="<?php echo esc_attr( $theme ); ?>"></div>

		<form id="ec-filter-form" method="get" action="<?php echo $current_url; ?>">

			<label for="event_type">Тип мероприятия</label>
			<select name="event_type" id="event_type">
				<option value="">-- Все типы --</option>
				<?php foreach ( $types as $type ) : ?>
					<option value="<?php echo esc_attr($type->slug); ?>" <?php selected($selected_type, $type->slug); ?>>
						<?php echo esc_html($type->name); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="organizer">Организатор</label>
			<select name="organizer" id="organizer">
				<option value="">-- Все организаторы --</option>
				<?php foreach ( $orgs as $org ) : ?>
					<option value="<?php echo esc_attr($org->slug); ?>" <?php selected($selected_org, $org->slug); ?>>
						<?php echo esc_html($org->name); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="start">Дата начала</label>
			<input type="date" name="start" id="start" value="<?php echo esc_attr($selected_start); ?>">

			<label for="end">Дата окончания</label>
			<input type="date" name="end" id="end" value="<?php echo esc_attr($selected_end); ?>">

			<label for="ec_search">Поиск</label>
			<input type="text" name="ec_search" id="ec_search" placeholder="Введите ключевые слова..." value="<?php echo esc_attr($search_query); ?>">

			<button type="submit">Применить</button>
		</form>

		<p><a href="<?php echo esc_url( home_url( '/?ec_export_ics=1' ) ); ?>" class="ec-download-ics">📥 Скачать календарь (ICS)</a></p>
	</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const calendarEl = document.getElementById('ec-calendar');
		const calendar = new FullCalendar.Calendar(calendarEl, {
			initialView: 'dayGridMonth',
			themeSystem: 'standard',
			locale: 'ru',
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek,listWeek'
			},
			events: <?php echo json_encode($events); ?>
		});
		calendar.render();
	});
	</script>

	<?php
	return ob_get_clean();
}
add_shortcode('event_calendar', 'ec_render_calendar_shortcode');
