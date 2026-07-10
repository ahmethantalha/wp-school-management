<?php
defined( 'ABSPATH' ) || exit;

/**
 * Excel (.xlsx) / CSV ile toplu öğrenci, öğretmen ve veli içe aktarma.
 *
 * XLSX için harici kütüphane gerekmez; ZipArchive + SimpleXML ile okunur.
 * ZipArchive yoksa CSV kullanılması istenir.
 */
class SMS_Import {

	/** Yüklenen dosyayı satır dizisine çevirir (ilk satır başlık). */
	public static function read_file( $path, $ext ) {
		$ext = strtolower( $ext );
		if ( 'csv' === $ext || 'txt' === $ext ) {
			return self::read_csv( $path );
		}
		if ( 'xlsx' === $ext ) {
			return self::read_xlsx( $path );
		}
		return new WP_Error( 'sms_bad_ext', 'Desteklenmeyen dosya türü. Lütfen .xlsx veya .csv yükleyin.' );
	}

	private static function read_csv( $path ) {
		$rows   = array();
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'sms_read', 'Dosya okunamadı.' );
		}
		$delim = self::detect_delimiter( $path );
		while ( false !== ( $data = fgetcsv( $handle, 0, $delim ) ) ) {
			$data = array_map( function ( $v ) {
				return trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $v ) );
			}, $data );
			if ( count( array_filter( $data, 'strlen' ) ) ) {
				$rows[] = $data;
			}
		}
		fclose( $handle );
		return self::rows_to_assoc( $rows );
	}

	private static function detect_delimiter( $path ) {
		$line = '';
		$h    = fopen( $path, 'r' );
		if ( $h ) {
			$line = (string) fgets( $h );
			fclose( $h );
		}
		$semic = substr_count( $line, ';' );
		$comma = substr_count( $line, ',' );
		return $semic > $comma ? ';' : ',';
	}

	private static function read_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'sms_no_zip', 'Sunucuda ZipArchive yok; lütfen dosyayı CSV olarak kaydedip yükleyin.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'sms_zip', 'Excel dosyası açılamadı.' );
		}

		// Paylaşılan dizeler.
		$shared = array();
		$ss     = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $ss ) {
			$xml = simplexml_load_string( $ss );
			if ( $xml ) {
				foreach ( $xml->si as $si ) {
					$shared[] = self::xlsx_si_text( $si );
				}
			}
		}

		// İlk çalışma sayfası.
		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();
		if ( false === $sheet ) {
			return new WP_Error( 'sms_sheet', 'Çalışma sayfası bulunamadı.' );
		}
		$xml = simplexml_load_string( $sheet );
		if ( ! $xml ) {
			return new WP_Error( 'sms_sheet', 'Çalışma sayfası okunamadı.' );
		}

		$rows = array();
		foreach ( $xml->sheetData->row as $row ) {
			$cells = array();
			$maxCol = 0;
			foreach ( $row->c as $c ) {
				$ref  = (string) $c['r'];
				$col  = self::col_index( preg_replace( '/\d+/', '', $ref ) );
				$type = (string) $c['t'];
				$val  = '';
				if ( 's' === $type ) {
					$idx = (int) $c->v;
					$val = $shared[ $idx ] ?? '';
				} elseif ( 'inlineStr' === $type ) {
					$val = self::xlsx_si_text( $c->is );
				} else {
					$val = isset( $c->v ) ? (string) $c->v : '';
				}
				$cells[ $col ] = trim( $val );
				$maxCol        = max( $maxCol, $col );
			}
			$line = array();
			for ( $i = 0; $i <= $maxCol; $i++ ) {
				$line[] = $cells[ $i ] ?? '';
			}
			if ( count( array_filter( $line, 'strlen' ) ) ) {
				$rows[] = $line;
			}
		}
		return self::rows_to_assoc( $rows );
	}

	private static function xlsx_si_text( $si ) {
		if ( ! $si ) {
			return '';
		}
		if ( isset( $si->t ) ) {
			return (string) $si->t;
		}
		$text = '';
		foreach ( $si->r as $r ) {
			$text .= (string) $r->t;
		}
		return $text;
	}

	private static function col_index( $letters ) {
		$letters = strtoupper( $letters );
		$n       = 0;
		for ( $i = 0; $i < strlen( $letters ); $i++ ) {
			$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return $n - 1;
	}

	/** İlk satırı başlık kabul ederek anahtarlı satırlar üretir. */
	private static function rows_to_assoc( $rows ) {
		if ( count( $rows ) < 2 ) {
			return new WP_Error( 'sms_empty', 'Dosyada başlık satırı ve en az bir veri satırı bulunmalı.' );
		}
		$header = array_map( function ( $h ) {
			return self::normalize_key( $h );
		}, array_shift( $rows ) );

		$out = array();
		foreach ( $rows as $row ) {
			$assoc = array();
			foreach ( $header as $i => $key ) {
				if ( '' === $key ) {
					continue;
				}
				$assoc[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
			}
			$out[] = $assoc;
		}
		return $out;
	}

	/** Başlık adlarını Türkçe/İngilizce eş anlamlılarla iç anahtarlara çevirir. */
	private static function normalize_key( $raw ) {
		$k = mb_strtolower( trim( $raw ), 'UTF-8' );
		$k = str_replace( array( 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç', ' ', '-', '.' ), array( 'i', 's', 'g', 'u', 'o', 'c', '_', '_', '' ), $k );

		$map = array(
			'ad'                 => 'first_name',
			'adi'                => 'first_name',
			'first_name'         => 'first_name',
			'isim'               => 'first_name',
			'soyad'              => 'last_name',
			'soyadi'             => 'last_name',
			'last_name'          => 'last_name',
			'ad_soyad'           => 'full_name',
			'adsoyad'            => 'full_name',
			'isim_soyisim'       => 'full_name',
			'full_name'          => 'full_name',
			'dogum_tarihi'       => 'birth_date',
			'birth_date'         => 'birth_date',
			'okul'               => 'school',
			'school'             => 'school',
			'no'                 => 'student_no',
			'ogrenci_no'         => 'student_no',
			'numara'             => 'student_no',
			'student_no'         => 'student_no',
			'sinif'              => 'grade_level',
			'sinif_seviyesi'     => 'grade_level',
			'grade'              => 'grade_level',
			'grade_level'        => 'grade_level',
			'veli_eposta'        => 'parent_email',
			'veli_e_posta'       => 'parent_email',
			'veli_email'         => 'parent_email',
			'parent_email'       => 'parent_email',
			'veli'               => 'parent_email',
			'not'                => 'notes',
			'notlar'             => 'notes',
			'notes'              => 'notes',
			'eposta'             => 'email',
			'email'              => 'email',
			'e_posta'            => 'email',
			'kullanici_adi'      => 'username',
			'username'           => 'username',
			'sifre'              => 'password',
			'password'           => 'password',
			'sinif_ogretmeni'    => 'is_class_teacher',
			'is_class_teacher'   => 'is_class_teacher',
		);
		return $map[ $k ] ?? $k;
	}

	private static function split_name( $row ) {
		$first = $row['first_name'] ?? '';
		$last  = $row['last_name'] ?? '';
		if ( ( '' === $first || '' === $last ) && ! empty( $row['full_name'] ) ) {
			$parts = preg_split( '/\s+/', trim( $row['full_name'] ) );
			$last  = array_pop( $parts );
			$first = implode( ' ', $parts );
			if ( '' === $first ) {
				$first = $last;
				$last  = '';
			}
		}
		return array( trim( $first ), trim( $last ) );
	}

	/** Öğrencileri içe aktarır. */
	public static function import_students( array $rows, $term_id ) {
		$created = 0;
		$errors  = array();
		$parents = array();
		foreach ( get_users( array( 'role' => 'sms_parent', 'fields' => array( 'ID', 'user_email' ) ) ) as $p ) {
			$parents[ strtolower( $p->user_email ) ] = (int) $p->ID;
		}

		foreach ( $rows as $i => $row ) {
			$line = $i + 2;
			list( $first, $last ) = self::split_name( $row );
			if ( '' === $first ) {
				$errors[] = "Satır $line: ad boş, atlandı.";
				continue;
			}

			$parent_id = 0;
			if ( ! empty( $row['parent_email'] ) ) {
				$pe = strtolower( trim( $row['parent_email'] ) );
				if ( isset( $parents[ $pe ] ) ) {
					$parent_id = $parents[ $pe ];
				} else {
					$errors[] = "Satır $line: '{$row['parent_email']}' e-postalı veli bulunamadı, veli atanmadı.";
				}
			}

			$data = array(
				'first_name'     => $first,
				'last_name'      => $last,
				'birth_date'     => self::parse_date( $row['birth_date'] ?? '' ),
				'school'         => $row['school'] ?? '',
				'student_no'     => $row['student_no'] ?? '',
				'parent_user_id' => $parent_id,
				'status'         => 'active',
			);
			$grade = (int) ( $row['grade_level'] ?? 0 );
			if ( $grade <= 0 ) {
				$grade    = (int) sms_get_settings()['min_grade'];
				$errors[] = "Satır $line: sınıf belirtilmedi, {$grade}. sınıfa atandı.";
			}
			SMS_Students::save( $data, $term_id, $grade, 0 );
			$created++;
		}

		return array( 'created' => $created, 'errors' => $errors );
	}

	/** Öğretmen veya veli hesaplarını içe aktarır. */
	public static function import_users( array $rows, $role ) {
		$created = 0;
		$errors  = array();

		foreach ( $rows as $i => $row ) {
			$line = $i + 2;
			list( $first, $last ) = self::split_name( $row );
			$name  = trim( $first . ' ' . $last );
			$email = sanitize_email( $row['email'] ?? '' );
			$user  = $row['username'] ?? '';
			if ( '' === $user && $email ) {
				$user = sanitize_user( current( explode( '@', $email ) ), true );
			}
			if ( '' === $user || ! $email ) {
				$errors[] = "Satır $line: kullanıcı adı ve e-posta gerekli, atlandı.";
				continue;
			}
			if ( username_exists( $user ) || email_exists( $email ) ) {
				$errors[] = "Satır $line: '$user' / '$email' zaten kayıtlı, atlandı.";
				continue;
			}
			$pass    = ! empty( $row['password'] ) ? (string) $row['password'] : wp_generate_password( 12 );
			$user_id = wp_insert_user( array(
				'user_login'   => $user,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name ?: $user,
				'role'         => $role,
			) );
			if ( is_wp_error( $user_id ) ) {
				$errors[] = "Satır $line: " . $user_id->get_error_message();
				continue;
			}
			if ( 'sms_teacher' === $role && ! empty( $row['is_class_teacher'] ) && in_array( strtolower( (string) $row['is_class_teacher'] ), array( '1', 'evet', 'yes', 'true', 'x' ), true ) ) {
				update_user_meta( $user_id, 'sms_is_class_teacher', 1 );
			}
			$created++;
		}

		return array( 'created' => $created, 'errors' => $errors );
	}

	/** Çeşitli tarih biçimlerini Y-m-d'ye çevirir. */
	private static function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$raw = str_replace( array( '.', '/' ), '-', $raw );
		$ts  = strtotime( $raw );
		if ( false === $ts ) {
			// gg-aa-yyyy denemesi.
			if ( preg_match( '/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $m ) ) {
				return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
			}
			return '';
		}
		return gmdate( 'Y-m-d', $ts );
	}

	/** İndirilebilir CSV şablonu içeriği. */
	public static function template( $type ) {
		$sep = ';';
		switch ( $type ) {
			case 'students':
				return "ad{$sep}soyad{$sep}dogum_tarihi{$sep}okul{$sep}ogrenci_no{$sep}sinif{$sep}veli_eposta\n"
					. "Ahmet{$sep}Yılmaz{$sep}2012-05-14{$sep}Atatürk Ortaokulu{$sep}1001{$sep}6{$sep}veli@example.com\n";
			case 'teachers':
				return "ad{$sep}soyad{$sep}kullanici_adi{$sep}eposta{$sep}sinif_ogretmeni\n"
					. "Mehmet{$sep}Demir{$sep}mdemir{$sep}mdemir@example.com{$sep}1\n";
			case 'parents':
				return "ad{$sep}soyad{$sep}kullanici_adi{$sep}eposta\n"
					. "Ayşe{$sep}Yılmaz{$sep}ayilmaz{$sep}veli@example.com\n";
		}
		return '';
	}
}
