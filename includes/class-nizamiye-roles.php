<?php
defined( 'ABSPATH' ) || exit;

/**
 * Roller ve yetenekler.
 *
 * nizamiye_manage : tam yönetim (yönetici)
 * nizamiye_teach  : öğretmen işlemleri (kendi derslikleri/öğrencileri ile sınırlı, kayıt bazında denetlenir)
 * nizamiye_access : panele erişim (tüm sistem rolleri)
 */
class Nizamiye_Roles {

	public static function add_roles() {
		add_role( 'nizamiye_teacher', 'Öğretmen', array(
			'read'       => true,
			'nizamiye_access' => true,
			'nizamiye_teach'  => true,
		) );

		add_role( 'nizamiye_parent', 'Veli', array(
			'read'       => true,
			'nizamiye_access' => true,
		) );

		add_role( 'nizamiye_student', 'Öğrenci', array(
			'read'       => true,
			'nizamiye_access' => true,
		) );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'nizamiye_access' );
			$admin->add_cap( 'nizamiye_teach' );
			$admin->add_cap( 'nizamiye_manage' );
		}
	}
}
