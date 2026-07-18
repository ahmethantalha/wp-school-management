<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dompdf (vendor/dompdf/dompdf) üzerinden ince bir sarmalayıcı.
 * HTML'den gerçek PDF baytları üretir; öğrenci karneleri bunu kullanır.
 */
class SMS_Pdf {

	/** Dompdf sınıfını (varsa başka bir eklentiden) çakışmaya girmeden yükler. */
	private static function ensure_loaded() {
		if ( class_exists( 'Dompdf\\Dompdf' ) ) {
			return true;
		}
		$autoload = SMS_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			return false;
		}
		require_once $autoload;
		return class_exists( 'Dompdf\\Dompdf' );
	}

	/**
	 * HTML'i A4 PDF baytlarına çevirir.
	 *
	 * @return string|WP_Error PDF içeriği (ham bayt) veya hata.
	 */
	public static function render( $html ) {
		if ( ! self::ensure_loaded() ) {
			return new WP_Error( 'sms_pdf_missing', 'PDF motoru (Dompdf) bulunamadı. Eklenti dosyalarının eksiksiz yüklendiğinden emin olun.' );
		}

		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false ); // güvenlik: uzak URL/kaynak yüklemeyi kapat.
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'defaultFont', 'DejaVu Sans' ); // Türkçe karakter desteği.
			$options->set( 'isPhpEnabled', false );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->render();
			return $dompdf->output();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'sms_pdf_render', 'PDF oluşturulamadı: ' . $e->getMessage() );
		}
	}

	/** PDF'i doğrudan indirme yanıtı olarak gönderir ve betiği sonlandırır. */
	public static function stream( $html, $filename ) {
		$pdf = self::render( $html );
		if ( is_wp_error( $pdf ) ) {
			wp_die( esc_html( $pdf->get_error_message() ) );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
