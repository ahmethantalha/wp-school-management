<?php
defined( 'ABSPATH' ) || exit;

/**
 * Toplu liste raporunun (alışkanlık / yoklama) gövdesi.
 *
 * $nizamiye_sheet çağıran taraftan gelir — Nizamiye_Sheet::habit_sheet() ya da
 * ::attendance_sheet() çıktısı. Bu dosyayı İKİ taraf da include eder: ekrandaki
 * önizleme (admin/views/habit-report.php, attendance-report.php) ve dompdf
 * belgesi (roster-report-print.php). Tek şablon olması, PDF ile önizlemeden
 * üretilen PNG/JPG'nin birbirinden ayrışmamasını garanti eder.
 *
 * Hücre sözleşmesi: ['text'=>string] veya ['lines'=>string[]], artı isteğe bağlı
 * 'sub' (küçük punto ikinci satır) ve 'class'. Ham HTML kabul edilmez; her şey
 * esc_html() ile basılır.
 */

if ( empty( $nizamiye_sheet ) || ! is_array( $nizamiye_sheet ) ) {
	return;
}
?>
<table class="sheet-head">
	<tr>
		<td>
			<h1><?php echo esc_html( $nizamiye_sheet['title'] ); ?></h1>
			<div class="sub"><?php echo esc_html( $nizamiye_sheet['subtitle'] ); ?></div>
		</td>
		<td class="meta">
			<div class="school"><?php echo esc_html( $nizamiye_sheet['school'] ); ?></div>
			<?php if ( ! empty( $nizamiye_sheet['term'] ) ) : ?>
				<div><?php echo esc_html( $nizamiye_sheet['term'] ); ?></div>
			<?php endif; ?>
		</td>
	</tr>
</table>

<?php if ( ! empty( $nizamiye_sheet['summary'] ) ) : ?>
	<table class="sheet-tiles">
		<tr>
			<?php foreach ( $nizamiye_sheet['summary'] as $nizamiye_tile ) : ?>
				<td style="width: <?php echo esc_attr( (string) round( 100 / count( $nizamiye_sheet['summary'] ), 2 ) ); ?>%">
					<div class="tile">
						<span class="v"><?php echo esc_html( $nizamiye_tile['v'] ); ?></span>
						<span class="l"><?php echo esc_html( $nizamiye_tile['l'] ); ?></span>
					</div>
				</td>
			<?php endforeach; ?>
		</tr>
	</table>
<?php endif; ?>

<table class="sheet-data">
	<thead>
		<tr>
			<?php foreach ( $nizamiye_sheet['columns'] as $nizamiye_col ) : ?>
				<th class="<?php echo esc_attr( $nizamiye_col['class'] ); ?>"><?php echo esc_html( $nizamiye_col['label'] ); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
		<?php if ( $nizamiye_sheet['rows'] ) : ?>
			<?php foreach ( $nizamiye_sheet['rows'] as $nizamiye_index => $nizamiye_row ) : ?>
				<?php // Şerit deseni CSS :nth-child yerine sınıfla verilir; dompdf'in nth-child desteği güvenilir değil. ?>
				<tr class="<?php echo ( $nizamiye_index % 2 ) ? 'alt' : ''; ?>">
					<?php foreach ( $nizamiye_row['cells'] as $nizamiye_cell ) : ?>
						<td class="<?php echo esc_attr( isset( $nizamiye_cell['class'] ) ? $nizamiye_cell['class'] : '' ); ?>">
							<?php if ( ! empty( $nizamiye_cell['lines'] ) ) : ?>
								<?php foreach ( $nizamiye_cell['lines'] as $nizamiye_line ) : ?>
									<div><?php echo esc_html( $nizamiye_line ); ?></div>
								<?php endforeach; ?>
							<?php else : ?>
								<?php echo esc_html( isset( $nizamiye_cell['text'] ) ? $nizamiye_cell['text'] : '' ); ?>
							<?php endif; ?>
							<?php if ( ! empty( $nizamiye_cell['sub'] ) ) : ?>
								<span class="grade"> · <?php echo esc_html( $nizamiye_cell['sub'] ); ?></span>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td class="empty" colspan="<?php echo esc_attr( (string) count( $nizamiye_sheet['columns'] ) ); ?>">
					Bu dönem ve filtre için listelenecek öğrenci bulunamadı.
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( ! empty( $nizamiye_sheet['note'] ) ) : ?>
	<div class="sheet-note"><?php echo esc_html( $nizamiye_sheet['note'] ); ?></div>
<?php endif; ?>

<div class="sheet-foot">
	<?php echo esc_html( $nizamiye_sheet['school'] ); ?> ·
	<?php echo esc_html( date_i18n( 'j F Y H:i', current_time( 'timestamp' ) ) ); ?> tarihinde oluşturuldu
</div>
