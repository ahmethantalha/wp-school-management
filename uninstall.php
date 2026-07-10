<?php
/**
 * Eklenti kaldırıldığında çalışır.
 *
 * Varsayılan olarak VERİLER KORUNUR (öğrenci kayıtları değerlidir; yanlışlıkla
 * kaldırma durumunda veri kaybı yaşanmaz). Verilerin de silinmesini istiyorsanız
 * wp-config.php dosyanıza şu satırı ekleyin:
 *
 *     define( 'SMS_REMOVE_DATA_ON_UNINSTALL', true );
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'SMS_REMOVE_DATA_ON_UNINSTALL' ) || ! SMS_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

$tables = array( 'terms', 'students', 'enrollments', 'classes', 'class_students', 'att_categories', 'att_sessions', 'attendance', 'habits', 'habit_students', 'habit_logs', 'grades' );
foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sms_' . $table ); // phpcs:ignore
}

delete_option( 'sms_settings' );
delete_option( 'sms_db_version' );

remove_role( 'sms_teacher' );
remove_role( 'sms_parent' );
remove_role( 'sms_student' );

$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'sms_access' );
	$admin->remove_cap( 'sms_teach' );
	$admin->remove_cap( 'sms_manage' );
}
