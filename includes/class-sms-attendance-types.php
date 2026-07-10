<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yoklama kategorileri ve oturumları (türleri).
 *
 * scope:
 *   'class'   → derslik bazlı (Ders). Branş öğretmeni + yönetici alır.
 *   'general' → genel (Namaz, Temizlik, Telefon). Sınıf öğretmeni + yönetici alır.
 */
class SMS_Attendance_Types {

	private static function ct() {
		global $wpdb;
		return $wpdb->prefix . 'sms_att_categories';
	}

	private static function st() {
		global $wpdb;
		return $wpdb->prefix . 'sms_att_sessions';
	}

	/** Tüm kategoriler. */
	public static function categories( $only_active = true ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::ct();
		if ( $only_active ) {
			$sql .= ' WHERE is_active = 1';
		}
		$sql .= ' ORDER BY sort_order, id';
		return $wpdb->get_results( $sql );
	}

	public static function get_category( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::ct() . ' WHERE id = %d', $id ) );
	}

	public static function get_category_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::ct() . ' WHERE slug = %s', $slug ) );
	}

	/** Bir kategorinin oturumları. */
	public static function sessions( $category_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::st() . ' WHERE category_id = %d ORDER BY sort_order, id',
			$category_id
		) );
	}

	public static function get_session( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::st() . ' WHERE id = %d', $id ) );
	}

	public static function session_count( $category_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::st() . ' WHERE category_id = %d', $category_id
		) );
	}

	/** Benzersiz slug üretir. */
	private static function unique_category_slug( $name ) {
		global $wpdb;
		$base = sanitize_title( $name );
		if ( ! $base ) {
			$base = 'kategori';
		}
		$slug = $base;
		$i    = 2;
		while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::ct() . ' WHERE slug = %s', $slug ) ) ) {
			$slug = $base . '-' . $i++;
		}
		return $slug;
	}

	public static function create_category( $name, $scope, $icon = '' ) {
		global $wpdb;
		$scope = 'class' === $scope ? 'class' : 'general';
		$order = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . self::ct() );
		$wpdb->insert( self::ct(), array(
			'name'       => $name,
			'slug'       => self::unique_category_slug( $name ),
			'icon'       => $icon ?: 'dashicons-clipboard',
			'scope'      => $scope,
			'is_system'  => 0,
			'sort_order' => $order,
			'is_active'  => 1,
			'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function update_category( $id, $name, $icon ) {
		global $wpdb;
		$wpdb->update( self::ct(), array(
			'name' => $name,
			'icon' => $icon ?: 'dashicons-clipboard',
		), array( 'id' => (int) $id ) );
	}

	/** Sistem kategorileri silinemez; diğerlerinde bağlı oturum ve yoklamalar da silinir. */
	public static function delete_category( $id ) {
		global $wpdb;
		$cat = self::get_category( $id );
		if ( ! $cat ) {
			return new WP_Error( 'sms_no_cat', 'Kategori bulunamadı.' );
		}
		if ( $cat->is_system ) {
			return new WP_Error( 'sms_system_cat', 'Sistem kategorileri silinemez (oturumlarını düzenleyebilirsiniz).' );
		}
		$wpdb->delete( self::st(), array( 'category_id' => (int) $id ) );
		$wpdb->delete( $wpdb->prefix . 'sms_attendance', array( 'category_id' => (int) $id ) );
		$wpdb->delete( self::ct(), array( 'id' => (int) $id ) );
		return true;
	}

	private static function unique_session_slug( $category_id, $name ) {
		global $wpdb;
		$base = sanitize_title( $name );
		if ( ! $base ) {
			$base = 'oturum';
		}
		$slug = $base;
		$i    = 2;
		while ( $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . self::st() . ' WHERE category_id = %d AND slug = %s', $category_id, $slug
		) ) ) {
			$slug = $base . '-' . $i++;
		}
		return $slug;
	}

	public static function add_session( $category_id, $name ) {
		global $wpdb;
		$order = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . self::st() . ' WHERE category_id = %d', $category_id
		) );
		$wpdb->insert( self::st(), array(
			'category_id' => (int) $category_id,
			'name'        => $name,
			'slug'        => self::unique_session_slug( $category_id, $name ),
			'sort_order'  => $order,
		) );
		return (int) $wpdb->insert_id;
	}

	/** Oturumu siler; kategoride en az bir oturum kalmalıdır. */
	public static function delete_session( $id ) {
		global $wpdb;
		$sess = self::get_session( $id );
		if ( ! $sess ) {
			return new WP_Error( 'sms_no_sess', 'Oturum bulunamadı.' );
		}
		if ( self::session_count( $sess->category_id ) <= 1 ) {
			return new WP_Error( 'sms_last_sess', 'Kategoride en az bir oturum bulunmalıdır.' );
		}
		$wpdb->delete( $wpdb->prefix . 'sms_attendance', array( 'session_id' => (int) $id ) );
		$wpdb->delete( self::st(), array( 'id' => (int) $id ) );
		return true;
	}

	/**
	 * Geçerli kullanıcının "Yoklama Al" ekranında görebileceği kategoriler.
	 * Ders: yönetici veya bu dönemde dersliği olan öğretmen.
	 * Genel: yönetici veya sınıf öğretmeni.
	 */
	public static function accessible_categories( $term_id ) {
		$out         = array();
		$is_manager  = sms_is_manager();
		$has_classes = $is_manager || (bool) sms_teacher_class_ids( 0, $term_id );
		$is_class_t  = sms_can_take_general_attendance();

		foreach ( self::categories( true ) as $cat ) {
			if ( 'class' === $cat->scope ) {
				if ( $has_classes ) {
					$out[] = $cat;
				}
			} else {
				if ( $is_class_t ) {
					$out[] = $cat;
				}
			}
		}
		return $out;
	}
}
