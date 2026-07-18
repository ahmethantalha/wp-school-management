<?php
/**
 * Plugin Name:       Okul Yönetim Sistemi
 * Plugin URI:        https://github.com/ahmethantalha/wp-school-management
 * Description:       Öğrenci yurtları, okullar ve eğitim kurumları için dönem bazlı öğrenci takip sistemi: öğrenci/öğretmen/veli yönetimi, derslikler, yoklama, not ve alışkanlık takibi, raporlar.
 * Version:           1.3.1
 * Author:            Ahmethan T. Gültekin
 * Author URI:        https://github.com/ahmethantalha
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-school-management
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'SMS_VERSION', '1.3.1' );
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
require_once SMS_DIR . 'includes/class-sms-pdf.php';

if ( is_admin() ) {
	require_once SMS_DIR . 'admin/class-sms-menu.php';
	require_once SMS_DIR . 'admin/class-sms-actions.php';
	SMS_Menu::init();
	SMS_Actions::init();
}

register_activation_hook( __FILE__, array( 'SMS_Install', 'activate' ) );

/**
 * Veritabanı sürümü eskiyse şemayı güncelle (eklenti güncellemeleri için).
 * Yalnızca yönetim tarafında çalışır: bu kontrol ve olası ağır dbDelta/migrasyon
 * asla giriş (wp-login.php) veya site ön yüzü isteğini yavaşlatmaz.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'sms_db_version' ) !== SMS_VERSION ) {
		SMS_Install::activate();
	}
}, 0 );

/**
 * Yönetici olmayan sistem kullanıcısı (öğretmen/veli/öğrenci) mı?
 * Bu kullanıcılar yalnızca eklenti sayfalarını görür; WP arayüzü gizlenir.
 */
function sms_is_limited_user() {
	return is_user_logged_in() && current_user_can( 'sms_access' ) && ! current_user_can( 'manage_options' );
}

/**
 * Sınırlı kullanıcılar için üst çubuk yalnızca wp-admin içinde gösterilir
 * (mobilde sol menüyü açan WordPress'in kendi hamburger düğmesi bu çubuğun
 * içinde yaşar); site ön yüzünde tamamen gizlenir.
 */
add_filter( 'show_admin_bar', function ( $show ) {
	if ( ! sms_is_limited_user() ) {
		return $show;
	}
	return is_admin();
} );

/**
 * Sınırlı kullanıcılar için üst çubuktaki varsayılan WP unsurlarını (logo,
 * güncellemeler, yorumlar, arama...) kaldırıp yerine sade bir profil +
 * çıkış bloğu ekler. Sol menü açma düğmesi (menu-toggle) dokunulmadan kalır.
 */
add_action( 'admin_bar_menu', function ( $wp_admin_bar ) {
	if ( ! sms_is_limited_user() ) {
		return;
	}
	foreach ( array( 'wp-logo', 'updates', 'comments', 'new-content', 'search', 'site-name', 'my-account', 'user-info', 'edit-profile', 'logout' ) as $node_id ) {
		$wp_admin_bar->remove_node( $node_id );
	}

	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	if ( in_array( 'sms_teacher', $roles, true ) ) {
		$role_label = sms_is_class_teacher( $user->ID ) ? 'Sınıf Öğretmeni' : 'Öğretmen';
	} elseif ( in_array( 'sms_parent', $roles, true ) ) {
		$role_label = 'Veli';
	} elseif ( in_array( 'sms_student', $roles, true ) ) {
		$role_label = 'Öğrenci';
	} else {
		$role_label = '';
	}

	$avatar = get_avatar( $user->ID, 26, '', '', array( 'class' => 'sms-ab-avatar' ) );
	$html   = '<span class="sms-ab-profile">' . $avatar
		. '<span class="sms-ab-profile-text"><span class="sms-ab-name">' . esc_html( $user->display_name ) . '</span>'
		. ( $role_label ? '<span class="sms-ab-role">' . esc_html( $role_label ) . '</span>' : '' )
		. '</span><span class="sms-ab-caret dashicons dashicons-arrow-down-alt2"></span></span>';

	// Alt menüsü olan hesap düğümü: mobilde dokununca (masaüstünde üzerine gelince)
	// açılır ve içindeki "Çıkış Yap" bağlantısı görünür. WordPress'in kendi
	// hesap menüsüyle aynı mantık — mobilde de güvenilir çalışır.
	$wp_admin_bar->add_node( array(
		'id'     => 'sms-account',
		'parent' => 'top-secondary',
		'title'  => $html,
		'href'   => wp_logout_url( home_url() ), // JS kapalıysa dahi çıkış erişilebilir kalır.
		'meta'   => array( 'class' => 'sms-ab-account' ),
	) );

	$wp_admin_bar->add_node( array(
		'id'     => 'sms-account-name',
		'parent' => 'sms-account',
		'title'  => esc_html( $user->display_name ) . ( $role_label ? ' — ' . esc_html( $role_label ) : '' ),
		'href'   => false,
		'meta'   => array( 'class' => 'sms-ab-account-name' ),
	) );

	$wp_admin_bar->add_node( array(
		'id'     => 'sms-logout',
		'parent' => 'sms-account',
		'title'  => '<span class="dashicons dashicons-exit"></span> Çıkış Yap',
		'href'   => wp_logout_url( home_url() ),
	) );
}, 999 );

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

// Sınırlı kullanıcılar için üst çubuk sadeleştirilir (mobil menü anahtarı
// korunur — bkz. wp_admin_bar_menu_toggle), alt bilgi ve WP uyarıları gizlenir.
add_action( 'admin_head', function () {
	if ( ! sms_is_limited_user() ) {
		return;
	}
	echo '<style>
		#wpfooter,#screen-meta,#screen-meta-links{display:none!important}
		.notice,.update-nag,.updated,.error:not(.sms-notice){display:none!important}
		#adminmenu .wp-submenu .wp-submenu-head{display:none}

		#wpadminbar{background:#1d2327}
		#wpadminbar #wp-admin-bar-menu-toggle .ab-icon:before{color:#fff}
		#wpadminbar #wp-admin-bar-menu-toggle:hover,
		#wpadminbar #wp-admin-bar-menu-toggle:focus{background:#4f46e5}
		#wpadminbar #wp-admin-bar-sms-account>.ab-item{display:flex;align-items:center;height:46px}
		#wpadminbar .sms-ab-profile{display:flex;align-items:center;gap:8px}
		#wpadminbar .sms-ab-avatar{border-radius:50%;box-shadow:0 0 0 2px rgba(255,255,255,.28);vertical-align:middle}
		#wpadminbar .sms-ab-profile-text{display:flex;flex-direction:column;line-height:1.15;text-align:left}
		#wpadminbar .sms-ab-name{font-size:12.5px;font-weight:600;color:#fff}
		#wpadminbar .sms-ab-role{font-size:10px;color:#b5bfc9;text-transform:uppercase;letter-spacing:.03em}
		#wpadminbar .sms-ab-caret{font-size:16px;width:16px;height:auto;color:#c3c4c7;margin-left:2px}
		#wpadminbar #wp-admin-bar-sms-account .ab-sub-wrapper{min-width:190px}
		#wpadminbar #wp-admin-bar-sms-account-name>.ab-item{color:#8c8f94!important;font-size:11px;text-transform:uppercase;letter-spacing:.03em;cursor:default;height:auto;padding-top:8px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.08)}
		#wpadminbar #wp-admin-bar-sms-logout .dashicons{font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px}
		#wpadminbar #wp-admin-bar-sms-logout>.ab-item{color:#f0b7b7!important}
		#wpadminbar #wp-admin-bar-sms-logout:hover>.ab-item,
		#wpadminbar #wp-admin-bar-sms-logout>.ab-item:focus{background:#b32d2e;color:#fff!important}
		@media (max-width:782px){
			#wpadminbar .sms-ab-name,#wpadminbar .sms-ab-role{display:none}
			#wpadminbar #wp-admin-bar-sms-account>.ab-item{padding:0 10px}
		}
	</style>';
} );

// Giriş sonrası veli/öğrenci/öğretmeni doğrudan panele yönlendir.
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'manage_options' ) && user_can( $user, 'sms_access' ) ) {
		return admin_url( 'admin.php?page=sms-dashboard' );
	}
	return $redirect_to;
}, 10, 3 );
