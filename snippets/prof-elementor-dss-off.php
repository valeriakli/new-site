<?php
/**
 * Название:      prof-elementor-dss-off — отключение модуля Design System Sync в Elementor на сайте
 * Что делает:    Снимает обработчик Elementor\Modules\DesignSystemSync\Module::enqueue_sync_stylesheet
 *                с хука wp_enqueue_scripts. Этот модуль относится к Atomic Editor (V4), который на сайте
 *                выключен, но всё равно на каждой некешированной сборке страницы занимал ~100 мс:
 *                читал ~70 опций размеров картинок и перезаписывал служебные опции своего файла стилей
 *                (INSERT/DELETE в wp_options). Замер prof-speed-diag v3, 2026-11-07.
 *                В админке, редакторе и предпросмотре Elementor ничего не меняется.
 *                Если когда-нибудь включите Atomic Editor — этот сниппет ОТКЛЮЧИТЬ.
 * Где активен:   только фронтенд. Основная попытка — template_redirect (PHP_INT_MAX): раньше, чем
 *                prof-speed-diag оборачивает обработчики таймерами (template_include), иначе обработчик
 *                не узнать. Запасная — wp_enqueue_scripts, приоритет 1 (у обработчика модуля 10).
 * Зависимости:   Elementor (если модуля нет — сниппет ничего не делает).
 * Источник:      profcosmetica/snippets/prof-elementor-dss-off.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function prof_elementor_dss_off() {
	// Редактор и предпросмотр Elementor не трогаем.
	if ( is_admin() || isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) {
		return;
	}
	global $wp_filter;
	if ( empty( $wp_filter['wp_enqueue_scripts'] ) ) {
		return;
	}
	foreach ( $wp_filter['wp_enqueue_scripts']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$f = $cb['function'];
			if ( is_array( $f ) && is_object( $f[0] )
				&& $f[0] instanceof \Elementor\Modules\DesignSystemSync\Module
				&& 'enqueue_sync_stylesheet' === $f[1] ) {
				remove_action( 'wp_enqueue_scripts', $f, $prio );
			}
		}
	}
}
add_action( 'template_redirect', 'prof_elementor_dss_off', PHP_INT_MAX );
add_action( 'wp_enqueue_scripts', 'prof_elementor_dss_off', 1 );
