<?php
defined( 'ABSPATH' ) || exit;

/**
 * Toplu liste raporunun AFİŞ gövdesi — velilere gönderilen, çocukların da
 * okuyabildiği iri puntolu çıktı.
 *
 * _roster-report-body.php ile aynı $nizamiye_sheet sözleşmesini tüketir; ikisi
 * de hem ekrandaki önizlemeden hem dompdf belgesinden include edilir, böylece
 * PDF ile önizlemeden üretilen PNG/JPG birbirinden ayrışamaz.
 *
 * Afişin klasik gövdeden fazladan kullandığı alanlar:
 *   heading       — künyenin iri başlığı (yoklama türünün / alışkanlığın adı)
 *   meta_pills[]  — ['l'=>etiket, 'v'=>değer] künye hapları
 *   summary_line[]— ['v'=>sayı, 's'=>açıklama] alt şerit
 *   motto         — en alttaki sarı şerit (boşsa basılmaz)
 *   rows[]['group'] + ['count'] — şube ayırıcı satırı
 *
 * Hücre sözleşmesinin afişe özgü ekleri:
 *   'chip'  => renk sınıfı  — metni renkli etiket olarak basar
 *   'check' => yes|no|off   — ✓ / ✗ / ○ işareti
 *   'field' => true         — çerçeveli açıklama kutusu (boş da olabilir)
 *   'unit'  => 's.'         — sayı rozetinin yanındaki küçük birim
 *
 * Ham HTML kabul edilmez; her şey esc_html() ile basılır.
 */

if ( empty( $nizamiye_sheet ) || ! is_array( $nizamiye_sheet ) ) {
	return;
}

$nizamiye_cols = count( $nizamiye_sheet['columns'] );
?>
<div class="pp-frame">

	<div class="pp-mast">
		<table class="pp-mast-row">
			<tr>
				<?php $nizamiye_icon = nizamiye_poster_ornament( 'attendance' === ( $nizamiye_sheet['kind'] ?? '' ) ? 'board' : 'books' ); ?>
				<?php if ( '' !== $nizamiye_icon ) : ?>
					<td class="pp-mast-icon"><?php echo $nizamiye_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kod içinde tanımlı sabit SVG; kullanıcı verisi içermez. ?></td>
				<?php endif; ?>
				<td class="pp-mast-text">
					<div class="pp-school"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_sheet['school'] ) ); ?></div>
					<div class="pp-hair"></div>
					<div class="pp-title"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_sheet['heading'] ) ); ?></div>
				</td>
			</tr>
		</table>

		<?php if ( ! empty( $nizamiye_sheet['meta_pills'] ) ) : ?>
			<table class="pp-pills">
				<tr>
					<?php foreach ( $nizamiye_sheet['meta_pills'] as $nizamiye_pill ) : ?>
						<td style="width: <?php echo esc_attr( (string) round( 100 / count( $nizamiye_sheet['meta_pills'] ), 2 ) ); ?>%">
							<div class="pp-pill">
								<?php if ( '' !== $nizamiye_pill['l'] ) : ?>
									<span class="l"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_pill['l'] ) ); ?></span>
								<?php endif; ?>
								<?php echo esc_html( nizamiye_upper_tr( $nizamiye_pill['v'] ) ); ?>
							</div>
						</td>
					<?php endforeach; ?>
				</tr>
			</table>
		<?php endif; ?>
	</div>

	<table class="pp-data">
		<thead>
			<tr>
				<?php foreach ( $nizamiye_sheet['columns'] as $nizamiye_col ) : ?>
					<th class="<?php echo esc_attr( $nizamiye_col['class'] ); ?>"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_col['label'] ) ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( $nizamiye_sheet['rows'] ) : ?>
				<?php
				// Şerit deseni yalnızca öğrenci satırlarında ilerler; ayırıcılar
				// sırayı bozmasın diye kendi sayacıyla atlanır. CSS :nth-child
				// kullanılmaz — dompdf'in desteği güvenilir değil.
				$nizamiye_stripe = 0;
				?>
				<?php foreach ( $nizamiye_sheet['rows'] as $nizamiye_row ) : ?>
					<?php if ( isset( $nizamiye_row['group'] ) ) : ?>
						<tr>
							<td class="grp" colspan="<?php echo esc_attr( (string) $nizamiye_cols ); ?>">
								<?php echo esc_html( nizamiye_upper_tr( $nizamiye_row['group'] ) ); ?>
								<?php if ( ! empty( $nizamiye_row['count'] ) ) : ?>
									<span class="n">· <?php echo esc_html( (string) $nizamiye_row['count'] ); ?> ÖĞRENCİ</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php continue; ?>
					<?php endif; ?>

					<?php // Öğrenci id'si, ekrandaki elle işaretleme için gerekir (assets/js/sheet-marks.js). ?>
					<tr class="<?php echo ( $nizamiye_stripe % 2 ) ? 'alt' : ''; ?>"
						<?php if ( ! empty( $nizamiye_row['student'] ) ) : ?>data-sms-student="<?php echo (int) $nizamiye_row['student']->id; ?>"<?php endif; ?>>
						<?php $nizamiye_stripe++; ?>
						<?php foreach ( $nizamiye_row['cells'] as $nizamiye_cell ) : ?>
							<?php
							$nizamiye_class = isset( $nizamiye_cell['class'] ) ? $nizamiye_cell['class'] : '';
							$nizamiye_text  = isset( $nizamiye_cell['text'] ) ? $nizamiye_cell['text'] : '';
							// Sınıf adları jeton olarak karşılaştırılır: strpos() ileride
							// içinde 'c' geçen her sınıfa ("check", "class"…) takılırdı.
							$nizamiye_tokens = preg_split( '/\s+/', trim( $nizamiye_class ) );
							?>
							<td class="<?php echo esc_attr( $nizamiye_class ); ?>">
								<?php if ( isset( $nizamiye_cell['check'] ) ) : ?>
									<?php if ( 'yes' === $nizamiye_cell['check'] ) : ?>
										<span class="tick-yes">✓</span>
									<?php elseif ( 'no' === $nizamiye_cell['check'] ) : ?>
										<span class="tick-no">✗</span>
									<?php else : ?>
										<span class="tick-off">○</span>
									<?php endif; ?>

								<?php elseif ( ! empty( $nizamiye_cell['field'] ) ) : ?>
									<?php // Boş kutu bilinçli: çıktı elde de doldurulabilsin. ?>
									<div class="pp-field"><?php echo '' !== $nizamiye_text ? esc_html( nizamiye_upper_tr( $nizamiye_text ) ) : '&nbsp;'; ?></div>

								<?php elseif ( isset( $nizamiye_cell['chip'] ) ) : ?>
									<span class="chip chip-<?php echo esc_attr( $nizamiye_cell['chip'] ); ?>"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_text ) ); ?></span>

								<?php elseif ( in_array( 'num', $nizamiye_tokens, true ) ) : ?>
									<span class="badge"><?php echo esc_html( $nizamiye_text ); ?></span>

								<?php elseif ( ! empty( $nizamiye_cell['lines'] ) ) : ?>
									<?php foreach ( $nizamiye_cell['lines'] as $nizamiye_line ) : ?>
										<div><?php echo esc_html( $nizamiye_line ); ?></div>
									<?php endforeach; ?>

								<?php elseif ( in_array( 'empty', $nizamiye_tokens, true ) ) : ?>
									<span class="chip chip-empty"><?php echo esc_html( '' !== $nizamiye_text ? nizamiye_upper_tr( $nizamiye_text ) : '—' ); ?></span>

								<?php elseif ( array_intersect( array( 'good', 'mid', 'low' ), $nizamiye_tokens ) ) : ?>
									<?php // Oran/durum hücreleri afişte renkli etikete döner ("%85", "✓ Yaptı"). ?>
									<?php $nizamiye_tone = current( array_intersect( array( 'good', 'mid', 'low' ), $nizamiye_tokens ) ); ?>
									<span class="chip chip-<?php echo esc_attr( $nizamiye_tone ); ?>"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_text ) ); ?></span>

								<?php elseif ( in_array( 'c', $nizamiye_tokens, true ) && is_numeric( $nizamiye_text ) ) : ?>
									<span class="pp-num"><?php echo esc_html( $nizamiye_text ); ?><?php if ( ! empty( $nizamiye_cell['unit'] ) ) : ?> <span class="u"><?php echo esc_html( $nizamiye_cell['unit'] ); ?></span><?php endif; ?></span>

								<?php elseif ( in_array( 'name', $nizamiye_tokens, true ) ) : ?>
									<?php echo esc_html( nizamiye_upper_tr( $nizamiye_text ) ); ?>

								<?php else : ?>
									<?php // Kitap adları bilerek olduğu gibi kalır — büyük harf okumayı zorlaştırırdı. ?>
									<?php echo esc_html( $nizamiye_text ); ?>
								<?php endif; ?>

								<?php if ( ! empty( $nizamiye_cell['sub'] ) ) : ?>
									<span class="grade"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_cell['sub'] ) ); ?></span>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="<?php echo esc_attr( (string) $nizamiye_cols ); ?>" style="text-align:center; padding: 22px 11px;">
						Bu dönem ve filtre için listelenecek öğrenci bulunamadı.
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $nizamiye_sheet['summary_line'] ) ) : ?>
		<div class="pp-summary">
			<?php foreach ( $nizamiye_sheet['summary_line'] as $nizamiye_j => $nizamiye_part ) : ?>
				<?php echo $nizamiye_j ? ' &nbsp;·&nbsp; ' : ''; ?>
				<?php echo esc_html( nizamiye_upper_tr( (string) $nizamiye_part['v'] ) ); ?>
				<span class="s"><?php echo esc_html( nizamiye_upper_tr( $nizamiye_part['s'] ) ); ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $nizamiye_sheet['motto'] ) ) : ?>
		<div class="pp-motto"><?php echo esc_html( $nizamiye_sheet['motto'] ); ?></div>
	<?php endif; ?>
</div>

<?php if ( ! empty( $nizamiye_sheet['note'] ) ) : ?>
	<div class="pp-note"><?php echo esc_html( $nizamiye_sheet['note'] ); ?></div>
<?php endif; ?>

<div class="pp-foot">
	<?php echo esc_html( $nizamiye_sheet['school'] ); ?> ·
	<?php echo esc_html( date_i18n( 'j F Y H:i', current_time( 'timestamp' ) ) ); ?> tarihinde oluşturuldu
</div>
