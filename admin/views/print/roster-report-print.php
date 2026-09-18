<?php
defined( 'ABSPATH' ) || exit;

/**
 * Toplu liste raporunun dompdf'e verilen bağımsız HTML belgesi.
 * $nizamiye_sheet çağıran taraftan gelir (Nizamiye_Sheet::render_html()).
 * Menüye kayıtlı bir sayfa değildir; tarayıcıda hiç render edilmez.
 *
 * Gövde ve CSS seçili çıktı düzenine göre belirlenir; ekrandaki önizleme de
 * aynı ikiliyi kullanır (bkz. Nizamiye_Sheet::body_template()).
 */

if ( empty( $nizamiye_sheet ) || ! is_array( $nizamiye_sheet ) ) {
	return;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="utf-8">
	<title><?php echo esc_html( $nizamiye_sheet['title'] ); ?></title>
	<style>
		@page { margin: 10mm; }
		body { margin: 0; }
		<?php
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit, kod içinde tanımlı CSS metni.
		echo 'poster' === $nizamiye_sheet['layout'] ? nizamiye_print_poster_css() : nizamiye_print_sheet_css();
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</style>
</head>
<body>
	<div class="<?php echo esc_attr( Nizamiye_Sheet::wrapper_class( $nizamiye_sheet ) ); ?>">
		<?php include Nizamiye_Sheet::body_template( $nizamiye_sheet ); ?>
	</div>
</body>
</html>
