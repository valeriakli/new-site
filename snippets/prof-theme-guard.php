<?php
/**
 * Название:      prof-theme-guard (на сайте — «Сторож настроек темы»)
 * Что делает:    Охраняет настройки, которые 16–23.11.2026 были затёрты неизвестным кодом:
 *                theme_mods_<активная тема> (шапка, футер, цвета, шрифты, привязка меню), widget_block
 *                (содержимое виджетов) и sidebars_widgets (какие виджеты в каких областях).
 *                1) Ловит момент: при записи / удалении этих опций, если значение резко уменьшилось
 *                   (меньше половины от прежнего или от последней здоровой копии), пишет инцидент:
 *                   время, тип запроса (cron / ajax / rest / admin), адрес без параметров, пользователь (ID),
 *                   хук и стек вызовов с плагинами-подозреваемыми. Также пишет смену темы (stylesheet/template).
 *                   Инциденты — в опции prof_tg_incidents (последние 20) и в журнале WooCommerce
 *                   (источник prof-theme-guard).
 *                2) Копии: раз в сутки (WP-Cron prof_tg_daily, запасной путь — первый заход в админку
 *                   через 26 ч) сохраняет эти опции, если они «здоровые»; хранит 8 последних разных версий
 *                   (опции prof_tg_snap_*, без автозагрузки; список — prof_tg_index).
 *                3) Тревога: красная плашка в админке, если настройки темы / виджеты выглядят сброшенными или
 *                   меню шапки не привязано; жёлтая — если есть непросмотренные инциденты за 7 дней.
 *                Страница «Инструменты → Сторож темы»: состояние, копии с кнопкой «Вернуть», инциденты.
 *                При возврате текущее значение сначала сохраняется отдельной копией; после возврата
 *                сбрасываются транзиент шрифтов Blocksy и кеш шорткодов prof-shortcode-cache.
 *                Не ловит прямые SQL-запросы мимо update_option (их покажет только ежедневная проверка).
 * Где активен:   везде (нужен и на фронтенде / в cron / REST, чтобы поймать запись): хуки update_option,
 *                add_option, delete_option; init (планирование cron); admin_init, admin_menu, admin_notices.
 * Зависимости:   Blocksy (имя меню шапки menu_1); WooCommerce — только для журнала (иначе error_log).
 *                Использует prof_sc_bump_version() из prof-shortcode-cache, если есть.
 * Источник:      profcosmetica/snippets/prof-theme-guard.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Какие опции охраняем.
 */
function prof_tg_watched( $option ) {
	return 0 === strpos( $option, 'theme_mods_' )
		|| in_array( $option, array( 'widget_block', 'sidebars_widgets', 'stylesheet', 'template' ), true );
}

/**
 * Минимум «здорового» значения.
 */
function prof_tg_min( $option ) {
	if ( 0 === strpos( $option, 'theme_mods_' ) ) {
		return 20;
	}
	if ( 'widget_block' === $option ) {
		return 10;
	}
	if ( 'sidebars_widgets' === $option ) {
		return 3;
	}
	return 0;
}

/**
 * «Размер» значения: число параметров темы / виджетов / виджетов в активных областях.
 */
function prof_tg_count( $option, $value ) {
	$value = maybe_unserialize( $value );
	if ( ! is_array( $value ) ) {
		return 0;
	}
	if ( 'sidebars_widgets' === $option ) {
		$n = 0;
		foreach ( $value as $area => $ids ) {
			if ( 'wp_inactive_widgets' !== $area && is_array( $ids ) ) {
				$n += count( $ids );
			}
		}
		return $n;
	}
	if ( 0 === strpos( $option, 'widget_' ) ) {
		return count( array_filter( array_keys( $value ), 'is_int' ) );
	}
	return count( $value );
}

/**
 * Текущие значения охраняемых опций (для копии).
 */
function prof_tg_current() {
	$mods = 'theme_mods_' . get_option( 'stylesheet' );
	return array(
		$mods              => get_option( $mods, array() ),
		'widget_block'     => get_option( 'widget_block', array() ),
		'sidebars_widgets' => get_option( 'sidebars_widgets', array() ),
	);
}

function prof_tg_healthy( $data ) {
	foreach ( $data as $opt => $val ) {
		if ( prof_tg_count( $opt, $val ) < prof_tg_min( $opt ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Стек вызовов: строки и плагины / темы, через которые прошла запись.
 */
function prof_tg_trace() {
	$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 45 );
	// Пропускаем собственные кадры сторожа — до вызова хука (do_action внутри update_option и т.п.).
	$start = 0;
	foreach ( $frames as $i => $f ) {
		if ( isset( $f['function'] ) && 'do_action' === $f['function'] ) {
			$start = $i;
			break;
		}
	}
	$root     = wp_normalize_path( ABSPATH );
	$lines    = array();
	$suspects = array();
	foreach ( array_slice( $frames, $start ) as $f ) {
		$file = isset( $f['file'] ) ? wp_normalize_path( $f['file'] ) : '';
		$fn   = ( isset( $f['class'] ) ? $f['class'] . $f['type'] : '' ) . ( isset( $f['function'] ) ? $f['function'] : '' );
		$lines[] = str_replace( $root, '', $file ) . ( isset( $f['line'] ) ? ':' . $f['line'] : '' ) . ' ' . $fn;
		if ( preg_match( '#wp-content/(plugins|mu-plugins|themes)/([^/]+)#', $file, $m ) ) {
			$suspects[ $m[1] . '/' . $m[2] ] = true;
		}
	}
	return array(
		'lines'    => array_slice( $lines, 0, 30 ),
		'suspects' => array_keys( $suspects ),
	);
}

/**
 * Записать инцидент.
 */
function prof_tg_incident( $event, $option, $detail ) {
	$trace = prof_tg_trace();
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path  = (string) wp_parse_url( $uri, PHP_URL_PATH );
	// Из параметров оставляем только action / page — без ключей и nonce.
	parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $q );
	foreach ( array( 'action', 'page' ) as $k ) {
		if ( isset( $q[ $k ] ) && is_string( $q[ $k ] ) ) {
			$path .= ' ' . $k . '=' . sanitize_key( $q[ $k ] );
		}
	}
	$ctx = array_filter(
		array(
			function_exists( 'wp_doing_cron' ) && wp_doing_cron() ? 'cron' : '',
			function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ? 'ajax' : '',
			defined( 'REST_REQUEST' ) && REST_REQUEST ? 'rest' : '',
			defined( 'WP_CLI' ) && WP_CLI ? 'wp-cli' : '',
			is_admin() ? 'admin' : '',
		)
	);

	$item = array(
		'time'     => time(),
		'event'    => $event,
		'option'   => $option,
		'detail'   => $detail,
		'request'  => ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( $_SERVER['REQUEST_METHOD'] ) . ' ' : '' ) . substr( $path, 0, 200 ),
		'context'  => $ctx ? implode( ',', $ctx ) : 'front',
		'user'     => did_action( 'init' ) ? get_current_user_id() : 0,
		'hook'     => isset( $GLOBALS['wp_current_filter'] ) ? implode( ' > ', (array) $GLOBALS['wp_current_filter'] ) : '',
		'suspects' => $trace['suspects'],
		'trace'    => $trace['lines'],
	);

	$list = get_option( 'prof_tg_incidents', array() );
	$list = is_array( $list ) ? $list : array();
	array_unshift( $list, $item );
	update_option( 'prof_tg_incidents', array_slice( $list, 0, 20 ), false );

	$msg = wp_json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( function_exists( 'wc_get_logger' ) && did_action( 'woocommerce_loaded' ) ) {
		wc_get_logger()->warning( $msg, array( 'source' => 'prof-theme-guard' ) );
	} else {
		error_log( 'prof-theme-guard: ' . $msg ); // phpcs:ignore
	}
}

/**
 * Проверка записи: резко ли уменьшилось значение.
 */
function prof_tg_check( $event, $option, $old, $new ) {
	if ( ! empty( $GLOBALS['prof_tg_restoring'] ) ) {
		return;
	}
	if ( 'stylesheet' === $option || 'template' === $option ) {
		if ( 'update' === $event && $old !== $new ) {
			prof_tg_incident( $event, $option, 'смена темы: ' . (string) $old . ' → ' . (string) $new );
		}
		return;
	}
	$base = get_option( 'prof_tg_baseline', array() );
	$ref  = max( prof_tg_count( $option, $old ), isset( $base[ $option ] ) ? (int) $base[ $option ] : 0 );
	$now  = 'delete' === $event ? 0 : prof_tg_count( $option, $new );
	if ( $ref >= prof_tg_min( $option ) && $ref > 0 && $now < $ref * 0.5 ) {
		prof_tg_incident( $event, $option, sprintf( 'было %d, стало %d', $ref, $now ) );
	}
}

add_action( 'update_option', function ( $option, $old, $value ) {
	if ( prof_tg_watched( $option ) ) {
		prof_tg_check( 'update', $option, $old, $value );
	}
}, 10, 3 );

add_action( 'add_option', function ( $option, $value ) {
	if ( prof_tg_watched( $option ) ) {
		prof_tg_check( 'add', $option, null, $value );
	}
}, 10, 2 );

add_action( 'delete_option', function ( $option ) {
	if ( prof_tg_watched( $option ) ) {
		prof_tg_check( 'delete', $option, get_option( $option ), null );
	}
} );

/**
 * Копия. $label — подпись (например, «перед возвратом»); с подписью сохраняется и «больное» значение.
 */
function prof_tg_snapshot( $label = '' ) {
	$label = is_string( $label ) ? $label : '';
	$data  = prof_tg_current();
	$hash  = md5( serialize( $data ) );
	$counts = array();
	foreach ( $data as $o => $v ) {
		$counts[ $o ] = prof_tg_count( $o, $v );
	}
	$healthy = prof_tg_healthy( $data );
	update_option( 'prof_tg_last_check', time(), false );

	if ( ! $healthy && '' === $label ) {
		// Ежедневная проверка нашла сброс — записываем один раз в сутки.
		$last = (int) get_option( 'prof_tg_last_alarm', 0 );
		if ( time() - $last > DAY_IN_SECONDS ) {
			update_option( 'prof_tg_last_alarm', time(), false );
			prof_tg_incident( 'check', 'all', 'ежедневная проверка: ' . wp_json_encode( $counts, JSON_UNESCAPED_SLASHES ) );
		}
		return false;
	}

	$index = get_option( 'prof_tg_index', array() );
	$index = is_array( $index ) ? $index : array();

	if ( $healthy ) {
		update_option( 'prof_tg_baseline', $counts, true );
	}
	if ( '' === $label && $index && isset( $index[0]['hash'] ) && $index[0]['hash'] === $hash ) {
		$index[0]['checked'] = time();
		update_option( 'prof_tg_index', $index, false );
		return true;
	}

	$key = 'prof_tg_snap_' . time() . '_' . wp_rand( 100, 999 );
	add_option( $key, $data, '', false );
	array_unshift(
		$index,
		array(
			'key'     => $key,
			'time'    => time(),
			'checked' => time(),
			'hash'    => $hash,
			'counts'  => $counts,
			'label'   => $label,
		)
	);
	while ( count( $index ) > 8 ) {
		$old = array_pop( $index );
		if ( ! empty( $old['key'] ) ) {
			delete_option( $old['key'] );
		}
	}
	update_option( 'prof_tg_index', $index, false );
	return true;
}

add_action( 'prof_tg_daily', function () {
	prof_tg_snapshot();
} );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'prof_tg_daily' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'prof_tg_daily' );
	}
} );

add_action( 'admin_init', function () {
	if ( current_user_can( 'manage_options' ) && time() - (int) get_option( 'prof_tg_last_check', 0 ) > 26 * HOUR_IN_SECONDS ) {
		prof_tg_snapshot();
	}
} );

function prof_tg_url() {
	return admin_url( 'tools.php?page=prof-theme-guard' );
}

/**
 * Проблемы текущего состояния (для плашки).
 */
function prof_tg_problems() {
	$problems = array();
	$data     = prof_tg_current();
	foreach ( $data as $o => $v ) {
		$n = prof_tg_count( $o, $v );
		if ( $n < prof_tg_min( $o ) ) {
			$problems[] = $o . ': ' . $n;
		}
	}
	$mods = reset( $data );
	if ( is_array( $mods ) && empty( $mods['nav_menu_locations']['menu_1'] ) ) {
		$problems[] = 'меню шапки не привязано';
	}
	return $problems;
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$problems = prof_tg_problems();
	if ( $problems ) {
		echo '<div class="notice notice-error"><p><strong>Сторож темы: настройки темы или виджеты выглядят сброшенными</strong> (' . esc_html( implode( '; ', $problems ) ) . '). Не сохраняйте ничего в «Настроить» и не обновляйте Blocksy. <a href="' . esc_url( prof_tg_url() ) . '">Вернуть копию →</a></p></div>';
	}
	$list   = get_option( 'prof_tg_incidents', array() );
	$recent = 0;
	foreach ( (array) $list as $it ) {
		if ( is_array( $it ) && empty( $it['seen'] ) && isset( $it['time'] ) && $it['time'] > time() - 7 * DAY_IN_SECONDS ) {
			$recent++;
		}
	}
	if ( $recent ) {
		echo '<div class="notice notice-warning"><p><strong>Сторож темы:</strong> подозрительных записей настроек за 7 дней: ' . (int) $recent . '. <a href="' . esc_url( prof_tg_url() ) . '">Посмотреть, кто это сделал →</a></p></div>';
	}
} );

add_action( 'admin_menu', function () {
	add_management_page( 'Сторож темы', 'Сторож темы', 'manage_options', 'prof-theme-guard', 'prof_tg_render' );
} );

/**
 * Кнопки страницы.
 */
add_action( 'admin_init', function () {
	if ( empty( $_POST['prof_tg_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'prof_tg' );
	$action = sanitize_key( wp_unslash( $_POST['prof_tg_action'] ) );
	$msg    = $action;

	if ( 'snapshot' === $action ) {
		prof_tg_snapshot( 'вручную' );
	} elseif ( 'seen' === $action || 'clear' === $action ) {
		$list = get_option( 'prof_tg_incidents', array() );
		if ( 'clear' === $action ) {
			$list = array();
		} else {
			foreach ( (array) $list as $i => $it ) {
				$list[ $i ]['seen'] = 1;
			}
		}
		update_option( 'prof_tg_incidents', $list, false );
	} elseif ( 'restore' === $action ) {
		$key  = isset( $_POST['prof_tg_key'] ) ? sanitize_key( wp_unslash( $_POST['prof_tg_key'] ) ) : '';
		$what = isset( $_POST['prof_tg_what'] ) ? sanitize_key( wp_unslash( $_POST['prof_tg_what'] ) ) : 'all';
		if ( 0 !== strpos( $key, 'prof_tg_snap_' ) ) {
			wp_die( 'Неверная копия.' );
		}
		$data = get_option( $key );
		if ( ! is_array( $data ) ) {
			wp_die( 'Копия не найдена.' );
		}
		$mods_opt = 'theme_mods_' . get_option( 'stylesheet' );
		$GLOBALS['prof_tg_restoring'] = true;
		prof_tg_snapshot( 'перед возвратом' );
		foreach ( $data as $opt => $val ) {
			$is_mods = 0 === strpos( $opt, 'theme_mods_' );
			if ( $is_mods && $opt !== $mods_opt ) {
				continue; // копия другой темы
			}
			if ( 'all' !== $what && ! ( ( 'mods' === $what && $is_mods ) || ( 'widgets' === $what && 'widget_block' === $opt ) || ( 'sidebars' === $what && 'sidebars_widgets' === $opt ) ) ) {
				continue;
			}
			if ( $is_mods && is_array( $val ) ) {
				$cur = get_option( $opt, array() );
				if ( is_array( $cur ) && ! empty( $cur['custom_css_post_id'] ) ) {
					$val['custom_css_post_id'] = $cur['custom_css_post_id'];
				}
			}
			update_option( $opt, $val, true );
		}
		$GLOBALS['prof_tg_restoring'] = false;
		delete_transient( 'blocksy_dynamic_styles_descriptor' );
		if ( function_exists( 'prof_sc_bump_version' ) ) {
			prof_sc_bump_version();
		}
		wp_cache_delete( 'alloptions', 'options' );
	} else {
		return;
	}

	wp_safe_redirect( add_query_arg( 'prof_tg_msg', $msg, prof_tg_url() ) );
	exit;
} );

function prof_tg_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msgs = array(
		'snapshot' => 'Копия сохранена.',
		'seen'     => 'Инциденты отмечены просмотренными.',
		'clear'    => 'Список инцидентов очищен.',
		'restore'  => 'Копия возвращена. Текущее значение перед этим сохранено отдельной копией «перед возвратом». Проверьте сайт в режиме инкогнито.',
	);
	echo '<div class="wrap"><h1>Сторож настроек темы</h1>';
	if ( isset( $_GET['prof_tg_msg'] ) ) {
		$m = sanitize_key( wp_unslash( $_GET['prof_tg_msg'] ) );
		if ( isset( $msgs[ $m ] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $msgs[ $m ] ) . '</p></div>';
		}
	}

	// Состояние
	$data = prof_tg_current();
	$base = get_option( 'prof_tg_baseline', array() );
	echo '<h2>Сейчас</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>Опция</th><th>Сейчас</th><th>Последняя здоровая копия</th><th></th></tr></thead><tbody>';
	foreach ( $data as $o => $v ) {
		$n  = prof_tg_count( $o, $v );
		$ok = $n >= prof_tg_min( $o );
		echo '<tr><td><code>' . esc_html( $o ) . '</code></td><td>' . (int) $n . '</td><td>' . ( isset( $base[ $o ] ) ? (int) $base[ $o ] : '—' ) . '</td><td>' . ( $ok ? '<span style="color:#008a20">в порядке</span>' : '<strong style="color:#b32d2e">сброшено?</strong>' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$problems = prof_tg_problems();
	if ( $problems ) {
		echo '<p style="color:#b32d2e"><strong>Проблемы:</strong> ' . esc_html( implode( '; ', $problems ) ) . '</p>';
	}
	$last = (int) get_option( 'prof_tg_last_check', 0 );
	$next = wp_next_scheduled( 'prof_tg_daily' );
	echo '<p>Последняя проверка: ' . ( $last ? esc_html( wp_date( 'd.m.Y H:i', $last ) ) : 'ещё не было' ) . '. Следующая по расписанию: ' . ( $next ? esc_html( wp_date( 'd.m.Y H:i', $next ) ) : '—' ) . '.</p>';

	// Копии
	$index = get_option( 'prof_tg_index', array() );
	echo '<h2>Копии</h2>';
	echo '<form method="post" style="margin-bottom:10px">';
	wp_nonce_field( 'prof_tg' );
	echo '<button class="button" name="prof_tg_action" value="snapshot">Сделать копию сейчас</button></form>';
	if ( ! $index ) {
		echo '<p>Копий пока нет — первая появится в течение суток или по кнопке выше.</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Сделана</th><th>Последняя проверка</th><th>Параметров темы / виджетов / в областях</th><th>Пометка</th><th>Вернуть</th></tr></thead><tbody>';
		foreach ( $index as $row ) {
			$c = isset( $row['counts'] ) ? array_values( (array) $row['counts'] ) : array();
			echo '<tr><td>' . esc_html( wp_date( 'd.m.Y H:i', (int) $row['time'] ) ) . '</td><td>' . esc_html( wp_date( 'd.m.Y H:i', (int) ( isset( $row['checked'] ) ? $row['checked'] : $row['time'] ) ) ) . '</td><td>' . esc_html( implode( ' / ', array_map( 'intval', $c ) ) ) . '</td><td>' . esc_html( isset( $row['label'] ) ? $row['label'] : '' ) . '</td><td>';
			echo '<form method="post" style="display:flex;gap:6px;align-items:center">';
			wp_nonce_field( 'prof_tg' );
			echo '<input type="hidden" name="prof_tg_key" value="' . esc_attr( $row['key'] ) . '">';
			echo '<select name="prof_tg_what"><option value="all">всё</option><option value="mods">только настройки темы</option><option value="widgets">только виджеты</option><option value="sidebars">только области виджетов</option></select>';
			echo '<button class="button" name="prof_tg_action" value="restore" onclick="return confirm(\'Вернуть эту копию? Текущее значение будет сохранено отдельной копией.\')">Вернуть</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	// Инциденты
	$list = get_option( 'prof_tg_incidents', array() );
	echo '<h2>Подозрительные записи</h2>';
	if ( ! $list ) {
		echo '<p>Не было.</p>';
	} else {
		echo '<form method="post" style="margin-bottom:10px">';
		wp_nonce_field( 'prof_tg' );
		echo '<button class="button" name="prof_tg_action" value="seen">Отметить просмотренными</button> <button class="button" name="prof_tg_action" value="clear" onclick="return confirm(\'Очистить список?\')">Очистить</button></form>';
		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr><th>Когда</th><th>Что</th><th>Запрос</th><th>Подозреваемые</th><th>Стек</th></tr></thead><tbody>';
		foreach ( (array) $list as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			echo '<tr' . ( empty( $it['seen'] ) ? ' style="font-weight:600"' : '' ) . '><td>' . esc_html( wp_date( 'd.m.Y H:i:s', (int) $it['time'] ) ) . '</td>';
			echo '<td>' . esc_html( $it['event'] . ' ' . $it['option'] ) . '<br>' . esc_html( $it['detail'] ) . '</td>';
			echo '<td>' . esc_html( $it['context'] ) . '<br><code>' . esc_html( $it['request'] ) . '</code><br>user ' . (int) $it['user'] . '<br><small>' . esc_html( $it['hook'] ) . '</small></td>';
			echo '<td>' . esc_html( $it['suspects'] ? implode( ', ', $it['suspects'] ) : '—' ) . '</td>';
			echo '<td><details><summary>показать</summary><pre style="white-space:pre-wrap;font-size:11px">' . esc_html( implode( "\n", (array) $it['trace'] ) ) . '</pre></details></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p style="color:#646970">Те же записи — в WooCommerce → Статус → Журналы, источник <code>prof-theme-guard</code>.</p>';
	}
	echo '</div>';
}
