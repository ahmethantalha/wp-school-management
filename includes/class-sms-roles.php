<?php
defined( 'ABSPATH' ) || exit;

/**
 * Roller ve yetenekler.
 *
 * sms_manage : tam yönetim (yönetici)
 * sms_teach  : öğretmen işlemleri (kendi derslikleri/öğrencileri ile sınırlı, kayıt bazında denetlenir)
 * sms_access : panele erişim (tüm sistem rolleri)
 */
class SMS_Roles {

	public static function add_roles() {
		add_role( 'sms_teacher', 'Öğretmen', array(
			'read'       => true,
			'sms_access' => true,
			'sms_teach'  => true,
		) );

		add_role( 'sms_parent', 'Veli', array(
			'read'       => true,
			'sms_access' => true,
		) );

		add_role( 'sms_student', 'Öğrenci', array(
			'read'       => true,
			'sms_access' => true,
		) );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'sms_access' );
			$admin->add_cap( 'sms_teach' );
			$admin->add_cap( 'sms_manage' );
		}
	}
}
