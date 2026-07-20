<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yönetim menüsü, sayfa yönlendirme ve varlık (CSS/JS) yükleme.
 */
class Nizamiye_Menu {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_menu_page(
			'Okul Yönetimi', 'Okul Yönetimi', 'nizamiye_access', 'sms-dashboard',
			array( __CLASS__, 'render_dashboard' ), 'dashicons-welcome-learn-more', 56
		);

		add_submenu_page( 'sms-dashboard', 'Anasayfa', 'Anasayfa', 'nizamiye_access', 'sms-dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'sms-dashboard', 'Dönemler', 'Dönemler', 'nizamiye_manage', 'sms-terms', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Öğrenciler', 'Öğrenciler', 'nizamiye_teach', 'sms-students', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Öğretmenler', 'Öğretmenler', 'nizamiye_manage', 'sms-teachers', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Veliler', 'Veliler', 'nizamiye_manage', 'sms-parents', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Derslikler', 'Derslikler', 'nizamiye_teach', 'sms-classes', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Yoklama', 'Yoklama', 'nizamiye_teach', 'sms-attendance', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Alışkanlıklar', 'Alışkanlıklar', 'nizamiye_teach', 'sms-habits', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Notlar', 'Notlar', 'nizamiye_teach', 'sms-grades', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Raporlar', 'Raporlar', 'nizamiye_access', 'sms-reports', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Karneler', 'Karneler', 'nizamiye_teach', 'sms-cards', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'İçe Aktar', 'İçe Aktar', 'nizamiye_manage', 'sms-import', array( __CLASS__, 'render' ) );
		add_submenu_page( 'sms-dashboard', 'Yoklama Türleri', 'Yoklama Türleri', 'nizamiye_manage', 'sms-att-types', array( __CLASS__, 'render' ) );

		// Veli menüsü: yalnızca veli hesabı (yönetici/öğretmen değilse) için görünür.
		if ( ! current_user_can( 'nizamiye_teach' ) && current_user_can( 'nizamiye_access' ) && Nizamiye_Students::children_of( get_current_user_id() ) ) {
			add_submenu_page( 'sms-dashboard', 'Öğrencilerim', 'Öğrencilerim', 'nizamiye_access', 'sms-my-children', array( __CLASS__, 'render' ) );
		}

		add_submenu_page( 'sms-dashboard', 'Ayarlar', 'Ayarlar', 'nizamiye_manage', 'sms-settings', array( __CLASS__, 'render' ) );
	}

	/** Anasayfa: rolüne göre yönlendirir. */
	public static function render_dashboard() {
		if ( current_user_can( 'nizamiye_teach' ) ) {
			self::load_view( 'dashboard' );
			return;
		}
		// Veli → çocukları, öğrenci → kendi karnesi.
		$uid      = get_current_user_id();
		$children = Nizamiye_Students::children_of( $uid );
		if ( $children ) {
			self::load_view( 'my-children' );
			return;
		}
		$me = Nizamiye_Students::by_user( $uid );
		if ( $me ) {
			$_GET['student'] = (int) $me->id;
			self::load_view( 'student-report' );
			return;
		}
		echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><span class="dashicons dashicons-welcome-learn-more"></span><h2>Hesabınız henüz bir öğrenciye bağlanmamış</h2><p>Lütfen kurum yöneticinizle iletişime geçin.</p></div></div>';
	}

	/** Sayfa parametresine göre görünüm dosyasını yükler. */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt sayfa yönlendirme (GET), durum değişikliği yok.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '';

		switch ( $page ) {
			case 'sms-terms':
				self::load_view( 'terms' );
				break;
			case 'sms-students':
				self::load_view( 'edit' === $view ? 'student-edit' : 'students' );
				break;
			case 'sms-teachers':
				self::load_view( 'teachers' );
				break;
			case 'sms-parents':
				self::load_view( 'parents' );
				break;
			case 'sms-classes':
				self::load_view( 'edit' === $view ? 'class-edit' : 'classes' );
				break;
			case 'sms-attendance':
				self::load_view( 'attendance' );
				break;
			case 'sms-habits':
				if ( 'edit' === $view ) {
					self::load_view( 'habit-edit' );
				} elseif ( 'track' === $view ) {
					self::load_view( 'habit-track' );
				} else {
					self::load_view( 'habits' );
				}
				break;
			case 'sms-grades':
				self::load_view( 'grades' );
				break;
			case 'sms-reports':
				self::load_view( isset( $_GET['student'] ) ? 'student-report' : 'reports' );
				break;
			case 'sms-cards':
				self::load_view( 'cards' );
				break;
			case 'sms-import':
				self::load_view( 'import' );
				break;
			case 'sms-att-types':
				self::load_view( 'attendance-types' );
				break;
			case 'sms-my-children':
				self::load_view( 'my-children' );
				break;
			case 'sms-settings':
				self::load_view( 'settings' );
				break;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function load_view( $view ) {
		$file = NIZAMIYE_DIR . 'admin/views/' . $view . '.php';
		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	public static function enqueue_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- salt sayfa yönlendirme (GET), durum değişikliği yok.
		if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_key( $_GET['page'] ), 'sms-' ) ) {
			return;
		}
		wp_enqueue_style( 'sms-admin', NIZAMIYE_URL . 'assets/css/admin.css', array(), NIZAMIYE_VERSION );
		wp_enqueue_script( 'sms-charts', NIZAMIYE_URL . 'assets/js/sms-charts.js', array(), NIZAMIYE_VERSION, true );
		wp_enqueue_script( 'sms-admin', NIZAMIYE_URL . 'assets/js/admin.js', array( 'sms-charts' ), NIZAMIYE_VERSION, true );
	}
}
