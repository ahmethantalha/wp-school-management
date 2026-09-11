<?php
defined( 'ABSPATH' ) || exit;

/**
 * Toplu liste raporunun dompdf'e verilen bağımsız HTML belgesi.
 * $nizamiye_sheet çağıran taraftan gelir (Nizamiye_Sheet::render_html()).
 * Menüye kayıtlı bir sayfa değildir; tarayıcıda hiç render edilmez.
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
		<?php echo nizamiye_print_sheet_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit, kod içinde tanımlı CSS metni. ?>
	</style>
</head>
<body>
	<div class="sheet <?php echo esc_attr( $nizamiye_sheet['density'] ); ?>">
		<?php include __DIR__ . '/_roster-report-body.php'; ?>
	</div>
</body>
</html>
