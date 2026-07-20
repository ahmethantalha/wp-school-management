<?php
/**
 * Eklenti kaldırıldığında çalışır.
 *
 * Varsayılan olarak VERİLER KORUNUR (öğrenci kayıtları değerlidir; yanlışlıkla
 * kaldırma durumunda veri kaybı yaşanmaz). Verilerin de silinmesini istiyorsanız
 * wp-config.php dosyanıza şu satırı ekleyin:
 *
 *     define( 'NIZAMIYE_REMOVE_DATA_ON_UNINSTALL', true );
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'NIZAMIYE_REMOVE_DATA_ON_UNINSTALL' ) || ! NIZAMIYE_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

$tables = array( 'terms', 'students', 'enrollments', 'classes', 'class_students', 'att_categories', 'att_sessions', 'attendance', 'habits', 'habit_students', 'habit_logs', 'grades' );
foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nizamiye_' . $table ); // phpcs:ignore
}

delete_option( 'nizamiye_settings' );
delete_option( 'nizamiye_db_version' );

remove_role( 'nizamiye_teacher' );
remove_role( 'nizamiye_parent' );
remove_role( 'nizamiye_student' );

$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'nizamiye_access' );
	$admin->remove_cap( 'nizamiye_teach' );
	$admin->remove_cap( 'nizamiye_manage' );
}
