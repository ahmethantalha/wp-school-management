<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_sms_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Veritabanı tablolarını kurar, varsayılan yoklama kategorilerini tohumlar ve
 * eski sürümlerden gelen yoklama tablosunu yeni şemaya taşır.
 */
class SMS_Install {

	public static function activate() {
		self::create_tables();
		SMS_Roles::add_roles();

		if ( ! get_option( 'sms_settings' ) ) {
			add_option( 'sms_settings', array(
				'school_name' => get_bloginfo( 'name' ),
				'final_grade' => 8,
				'min_grade'   => 1,
				'max_grade'   => 12,
			) );
		}

		self::seed_attendance_types();
		self::migrate_attendance();

		update_option( 'sms_db_version', SMS_VERSION );
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'sms_';

		$sql = array();

		$sql[] = "CREATE TABLE {$p}terms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY is_active (is_active)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}students (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			parent_user_id BIGINT UNSIGNED NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			birth_date DATE NULL,
			school VARCHAR(190) NULL,
			student_no VARCHAR(50) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY parent_user_id (parent_user_id),
			KEY status (status),
			KEY user_id (user_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}enrollments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id BIGINT UNSIGNED NOT NULL,
			term_id BIGINT UNSIGNED NOT NULL,
			grade_level SMALLINT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY student_term (student_id,term_id),
			KEY term_id (term_id),
			KEY grade_level (grade_level)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}classes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) NOT NULL,
			subject VARCHAR(100) NULL,
			grade_level SMALLINT NULL,
			teacher_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY term_id (term_id),
			KEY teacher_id (teacher_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}class_students (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY class_student (class_id,student_id),
			KEY student_id (student_id)
		) $charset;";

		// Yoklama kategorileri (Namaz, Temizlik, Telefon, Ders...) — yönetici genişletebilir.
		// grade_levels: JSON dizi (örn. [6,7,8]); boş/NULL = tüm sınıflar bu yoklamada görünür.
		$sql[] = "CREATE TABLE {$p}att_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			slug VARCHAR(60) NOT NULL,
			icon VARCHAR(60) NULL,
			scope VARCHAR(15) NOT NULL DEFAULT 'general',
			grade_levels TEXT NULL,
			is_system TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset;";

		// Kategori altındaki oturumlar (Namaz → Sabah, Öğle, İkindi, Akşam, Yatsı).
		$sql[] = "CREATE TABLE {$p}att_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(100) NOT NULL,
			slug VARCHAR(60) NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY category_id (category_id)
		) $charset;";

		// Yoklama kayıtları — kategori + oturum + (opsiyonel) derslik bazlı.
		$sql[] = "CREATE TABLE {$p}attendance (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			class_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			student_id BIGINT UNSIGNED NOT NULL,
			att_date DATE NOT NULL,
			status VARCHAR(15) NOT NULL DEFAULT 'present',
			note VARCHAR(255) NULL,
			recorded_by BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attu (category_id,session_id,class_id,student_id,att_date),
			KEY term_id (term_id),
			KEY student_id (student_id),
			KEY att_date (att_date)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}habits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) NOT NULL,
			description TEXT NULL,
			track_type VARCHAR(10) NOT NULL DEFAULT 'binary',
			scale_max TINYINT UNSIGNED NOT NULL DEFAULT 5,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY term_id (term_id),
			KEY created_by (created_by)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}habit_students (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			habit_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY habit_student (habit_id,student_id),
			KEY student_id (student_id)
		) $charset;";

		// value: binary/scale için 0..scale_max; 'reading' (kitap okuma) türünde o günkü sayfa sayısı.
		// note: binary/scale için serbest not; 'reading' türünde okunan kitabın adı.
		$sql[] = "CREATE TABLE {$p}habit_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			habit_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			log_date DATE NOT NULL,
			value SMALLINT NOT NULL DEFAULT 0,
			note VARCHAR(255) NULL,
			recorded_by BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY habit_student_date (habit_id,student_id,log_date),
			KEY student_id (student_id),
			KEY log_date (log_date)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}grades (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(190) NOT NULL,
			exam_type VARCHAR(50) NULL,
			score DECIMAL(6,2) NOT NULL DEFAULT 0,
			max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
			exam_date DATE NULL,
			recorded_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY student_id (student_id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Varsayılan yoklama kategorilerini ve oturumlarını ekler (slug'a göre idempotent).
	 */
	public static function seed_attendance_types() {
		$defaults = array(
			array(
				'name'     => 'Ders',
				'slug'     => 'ders',
				'icon'     => 'dashicons-welcome-learn-more',
				'scope'    => 'class',
				'sessions' => array( array( 'Ders', 'ders' ) ),
			),
			array(
				'name'     => 'Namaz',
				'slug'     => 'namaz',
				'icon'     => 'dashicons-store',
				'scope'    => 'general',
				'sessions' => array(
					array( 'Sabah', 'sabah' ),
					array( 'Öğle', 'ogle' ),
					array( 'İkindi', 'ikindi' ),
					array( 'Akşam', 'aksam' ),
					array( 'Yatsı', 'yatsi' ),
				),
			),
			array(
				'name'     => 'Temizlik',
				'slug'     => 'temizlik',
				'icon'     => 'dashicons-image-filter',
				'scope'    => 'general',
				'sessions' => array( array( 'Temizlik', 'temizlik' ) ),
			),
			array(
				'name'     => 'Telefon',
				'slug'     => 'telefon',
				'icon'     => 'dashicons-smartphone',
				'scope'    => 'general',
				'sessions' => array( array( 'Telefon', 'telefon' ) ),
			),
		);

		global $wpdb;
		$ct = $wpdb->prefix . 'sms_att_categories';
		$st = $wpdb->prefix . 'sms_att_sessions';
		$order = 0;

		foreach ( $defaults as $cat ) {
			$cat_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $ct WHERE slug = %s", $cat['slug'] ) );
			if ( ! $cat_id ) {
				$wpdb->insert( $ct, array(
					'name'       => $cat['name'],
					'slug'       => $cat['slug'],
					'icon'       => $cat['icon'],
					'scope'      => $cat['scope'],
					'is_system'  => 1,
					'sort_order' => $order,
					'is_active'  => 1,
					'created_at' => current_time( 'mysql' ),
				) );
				$cat_id = (int) $wpdb->insert_id;
			}
			$order++;

			$s_order = 0;
			foreach ( $cat['sessions'] as $sess ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM $st WHERE category_id = %d AND slug = %s", $cat_id, $sess[1]
				) );
				if ( ! $exists ) {
					$wpdb->insert( $st, array(
						'category_id' => $cat_id,
						'name'        => $sess[0],
						'slug'        => $sess[1],
						'sort_order'  => $s_order,
					) );
				}
				$s_order++;
			}
		}
	}

	/**
	 * Eski (sürüm 1.0) yoklama kayıtlarını yeni şemaya taşır:
	 * term_id ve Ders kategorisi/oturumu atar, eski benzersiz anahtarı kaldırır.
	 */
	private static function migrate_attendance() {
		global $wpdb;
		$att = $wpdb->prefix . 'sms_attendance';

		// term_id boş olan (eski) satırları derslik üzerinden doldur.
		$wpdb->query(
			"UPDATE $att a
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = a.class_id
			 SET a.term_id = c.term_id
			 WHERE a.term_id = 0 AND a.class_id > 0"
		);

		// Kategorisi olmayan eski satırları Ders kategorisine bağla.
		$ders_cat = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}sms_att_categories WHERE slug = 'ders'" );
		$ders_sess = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}sms_att_sessions WHERE category_id = %d ORDER BY id LIMIT 1", $ders_cat
		) );
		if ( $ders_cat && $ders_sess ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $att SET category_id = %d, session_id = %d WHERE category_id = 0",
				$ders_cat, $ders_sess
			) );
		}

		// Eski benzersiz anahtar (class_id,student_id,att_date) yeni kategori mantığını bozar; kaldır.
		$has_old = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM information_schema.STATISTICS
			 WHERE table_schema = DATABASE() AND table_name = '{$att}' AND index_name = 'class_student_date'"
		);
		if ( $has_old ) {
			$wpdb->query( "ALTER TABLE $att DROP INDEX class_student_date" );
		}

		// Yeni benzersiz anahtar yoksa ekle.
		$has_new = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM information_schema.STATISTICS
			 WHERE table_schema = DATABASE() AND table_name = '{$att}' AND index_name = 'attu'"
		);
		if ( ! $has_new ) {
			$wpdb->query( "ALTER TABLE $att ADD UNIQUE KEY attu (category_id,session_id,class_id,student_id,att_date)" );
		}
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
