<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tek öğrenci karnesinin tam HTML belgesi. Dompdf ile sunucu tarafında gerçek bir
 * PDF dosyasına dönüştürülmek üzere üretilir (Nizamiye_Actions::handle_print_report /
 * handle_print_report_bulk). $student_id / $term_id çağıran yerde tanımlı olmalı.
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?php echo nizamiye_print_report_css(); // phpcs:ignore ?></style>
</head>
<body>
	<div class="doc">
		<?php include __DIR__ . '/_student-report-body.php'; ?>
	</div>
</body>
</html>
