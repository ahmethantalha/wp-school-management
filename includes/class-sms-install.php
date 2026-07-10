<?php
defined( 'ABSPATH' ) || exit;

/**
 * Veritabanı tablolarını kurar ve rolleri kaydeder.
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

		$sql[] = "CREATE TABLE {$p}attendance (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			att_date DATE NOT NULL,
			status VARCHAR(15) NOT NULL DEFAULT 'present',
			note VARCHAR(255) NULL,
			recorded_by BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY class_student_date (class_id,student_id,att_date),
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

		$sql[] = "CREATE TABLE {$p}habit_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			habit_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			log_date DATE NOT NULL,
			value TINYINT NOT NULL DEFAULT 0,
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
}
