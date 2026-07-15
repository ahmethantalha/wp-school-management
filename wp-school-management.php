<?php
/**
 * Plugin Name:       Okul Yönetim Sistemi
 * Plugin URI:        https://github.com/ahmethantalha/wp-school-management
 * Description:       Öğrenci yurtları, okullar ve eğitim kurumları için dönem bazlı öğrenci takip sistemi: öğrenci/öğretmen/veli yönetimi, derslikler, yoklama, not ve alışkanlık takibi, raporlar.
 * Version:           1.2.0
 * Author:            Ahmet Han Talha
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-school-management
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'SMS_VERSION', '1.2.0' );
define( 'SMS_FILE', __FILE__ );
define( 'SMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMS_URL', plugin_dir_url( __FILE__ ) );

require_once SMS_DIR . 'includes/class-sms-install.php';
require_once SMS_DIR . 'includes/class-sms-roles.php';
require_once SMS_DIR . 'includes/sms-helpers.php';
require_once SMS_DIR . 'includes/class-sms-terms.php';
require_once SMS_DIR . 'includes/class-sms-students.php';
require_once SMS_DIR . 'includes/class-sms-classes.php';
require_once SMS_DIR . 'includes/class-sms-attendance-types.php';
require_once SMS_DIR . 'includes/class-sms-attendance.php';
require_once SMS_DIR . 'includes/class-sms-import.php';
require_once SMS_DIR . 'includes/class-sms-habits.php';
require_once SMS_DIR . 'includes/class-sms-grades.php';
require_once SMS_DIR . 'includes/class-sms-reports.php';

if ( is_admin() ) {
	require_once SMS_DIR . 'admin/class-sms-menu.php';
	require_once SMS_DIR . 'admin/class-sms-actions.php';
	SMS_Menu::init();
	SMS_Actions::init();
}

register_activation_hook( __FILE__, array( 'SMS_Install', 'activate' ) );

// Veritabanı sürümü eskiyse şemayı güncelle (eklenti güncellemeleri için).
add_action( 'plugins_loaded', function () {
	if ( get_option( 'sms_db_version' ) !== SMS_VERSION ) {
		SMS_Install::activate();
	}
} );

/**
 * Yönetici olmayan sistem kullanıcısı (öğretmen/veli/öğrenci) mı?
 * Bu kullanıcılar yalnızca eklenti sayfalarını görür; WP arayüzü gizlenir.
 */
function sms_is_limited_user() {
	return is_user_logged_in() && current_user_can( 'sms_access' ) && ! current_user_can( 'manage_options' );
}

// Site ön yüzünde admin çubuğunu yalnızca yönetici görür.
add_filter( 'show_admin_bar', function ( $show ) {
	return sms_is_limited_user() ? false : $show;
} );

// Sınırlı kullanıcıları eklenti sayfaları dışındaki tüm wp-admin ekranlarından yönlendir.
add_action( 'admin_init', function () {
	if ( ! sms_is_limited_user() || wp_doing_ajax() ) {
		return;
	}
	global $pagenow;
	$page    = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	$allowed = ( 'admin.php' === $pagenow && 0 === strpos( $page, 'sms-' ) )
		|| 'admin-post.php' === $pagenow;
	if ( ! $allowed ) {
		wp_safe_redirect( admin_url( 'admin.php?page=sms-dashboard' ) );
		exit;
	}
}, 1 );

// Sınırlı kullanıcılar için eklenti menüsü dışındaki tüm yönetim menülerini kaldır.
add_action( 'admin_menu', function () {
	if ( ! sms_is_limited_user() ) {
		return;
	}
	global $menu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && 'sms-dashboard' !== $item[2] ) {
			remove_menu_page( $item[2] );
		}
	}
}, 999 );

// Sınırlı kullanıcılar için wp-admin içindeki üst çubuğu ve alt bilgiyi gizle.
add_action( 'admin_head', function () {
	if ( ! sms_is_limited_user() ) {
		return;
	}
	echo '<style>#wpadminbar{display:none!important}html.wp-toolbar{padding-top:0!important}#wpfooter,#screen-meta,#screen-meta-links{display:none!important}</style>';
} );

// Giriş sonrası veli/öğrenci/öğretmeni doğrudan panele yönlendir.
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'manage_options' ) && user_can( $user, 'sms_access' ) ) {
		return admin_url( 'admin.php?page=sms-dashboard' );
	}
	return $redirect_to;
}, 10, 3 );
