<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tek bir öğrencinin karne içeriği (yalnızca gövde — <html>/<head>/<body> sarmalayıcısı yok).
 * student-report-print.php bu parçayı sarmalar; toplu indirmede (Nizamiye_Actions::handle_print_report_bulk)
 * her öğrenci için ayrı ayrı PDF üretilirken aynı şablon döngü içinde tekrar kullanılır.
 * Beklenen: $nizamiye_student_id, $nizamiye_term_id tanımlı.
 */

$nizamiye_report  = Nizamiye_Reports::student_report( $nizamiye_student_id, $nizamiye_term_id );
$nizamiye_student = $nizamiye_report['student'];
if ( ! $nizamiye_student ) {
	return;
}

$nizamiye_settings = nizamiye_get_settings();
$nizamiye_term     = Nizamiye_Terms::get( $nizamiye_term_id );
$nizamiye_att      = $nizamiye_report['att_all'];

$nizamiye_habit_rates = array_filter( array_map( function ( $nizamiye_h ) {
	return $nizamiye_h->log_count > 0 ? (int) $nizamiye_h->rate : null;
}, $nizamiye_report['habits'] ), function ( $nizamiye_v ) { return null !== $nizamiye_v; } );
$nizamiye_habit_avg = $nizamiye_habit_rates ? (int) round( array_sum( $nizamiye_habit_rates ) / count( $nizamiye_habit_rates ) ) : null;

$nizamiye_grade_rates = array_map( function ( $nizamiye_g ) { return (int) $nizamiye_g->avg_rate; }, $nizamiye_report['grade_avgs'] );
$nizamiye_grade_avg   = $nizamiye_grade_rates ? (int) round( array_sum( $nizamiye_grade_rates ) / count( $nizamiye_grade_rates ) ) : null;

$nizamiye_parent = $nizamiye_student->parent_user_id ? get_userdata( (int) $nizamiye_student->parent_user_id ) : null;

$nizamiye_rate_color = function ( $nizamiye_v ) {
	if ( null === $nizamiye_v ) {
		return '#94a3b8';
	}
	if ( $nizamiye_v >= 75 ) {
		return '#16a34a';
	}
	if ( $nizamiye_v >= 50 ) {
		return '#d97706';
	}
	return '#dc2626';
};
?>
<table class="head-table"><tr>
	<td class="head">
		<h1>ÖĞRENCİ KARNESİ</h1>
		<div class="school"><?php echo esc_html( $nizamiye_settings['school_name'] ); ?></div>
	</td>
	<td class="head-meta">
		<?php echo esc_html( $nizamiye_term ? $nizamiye_term->name : '' ); ?><br>
		<?php echo esc_html( date_i18n( 'j F Y', current_time( 'timestamp' ) ) ); ?>
	</td>
</tr></table>

<table class="identity"><tr>
	<td class="avatar-cell">
		<div class="avatar"><?php
			$nizamiye_parts = preg_split( '/\s+/', trim( nizamiye_student_name( $nizamiye_student ) ) );
			echo esc_html( mb_strtoupper( mb_substr( $nizamiye_parts[0] ?? '', 0, 1 ) . mb_substr( $nizamiye_parts[ count( $nizamiye_parts ) - 1 ] ?? '', 0, 1 ) ) );
		?></div>
	</td>
	<td>
		<p class="name"><?php echo esc_html( nizamiye_student_name( $nizamiye_student ) ); ?></p>
		<div class="sub">
			<?php echo $nizamiye_report['enrollment'] ? esc_html( nizamiye_grade_label( $nizamiye_report['enrollment']->grade_level ) ) . ' • ' : ''; ?>
			<?php echo $nizamiye_student->school ? esc_html( $nizamiye_student->school ) . ' • ' : ''; ?>
			<?php echo $nizamiye_student->birth_date ? 'Doğum: ' . esc_html( nizamiye_format_date( $nizamiye_student->birth_date ) ) . ' • ' : ''; ?>
			<?php echo $nizamiye_parent ? 'Veli: ' . esc_html( $nizamiye_parent->display_name ) : ''; ?>
		</div>
	</td>
</tr></table>

<table class="tiles"><tr>
	<td><div class="tile"><span class="v" style="color:<?php echo esc_attr( $nizamiye_rate_color( $nizamiye_att['rate'] ) ); ?>"><?php echo null !== $nizamiye_att['rate'] ? (int) $nizamiye_att['rate'] . '%' : '—'; ?></span><span class="l">Devam</span></div></td>
	<td><div class="tile"><span class="v" style="color:<?php echo esc_attr( $nizamiye_rate_color( $nizamiye_habit_avg ) ); ?>"><?php echo null !== $nizamiye_habit_avg ? esc_html( $nizamiye_habit_avg . '%' ) : '—'; ?></span><span class="l">Alışkanlık</span></div></td>
	<td><div class="tile"><span class="v" style="color:<?php echo esc_attr( $nizamiye_rate_color( $nizamiye_grade_avg ) ); ?>"><?php echo null !== $nizamiye_grade_avg ? esc_html( $nizamiye_grade_avg . '%' ) : '—'; ?></span><span class="l">Not Ortalaması</span></div></td>
	<td><div class="tile"><span class="v"><?php echo (int) $nizamiye_att['total']; ?></span><span class="l">Toplam Yoklama</span></div></td>
</tr></table>

<?php if ( $nizamiye_report['att_cats'] ) : ?>
	<h2 class="sec">Yoklama Özeti (Yoklama Türüne Göre)</h2>
	<?php foreach ( $nizamiye_report['att_cats'] as $nizamiye_cat ) : ?>
		<div class="cat-line">
			<b><?php echo esc_html( $nizamiye_cat['category'] ); ?>:</b>
			<span style="color:<?php echo esc_attr( $nizamiye_rate_color( $nizamiye_cat['overall_rate'] ) ); ?>;font-weight:700"><?php echo null !== $nizamiye_cat['overall_rate'] ? (int) $nizamiye_cat['overall_rate'] . '%' : '—'; ?></span>
			<span style="color:#94a3b8">(<?php echo (int) $nizamiye_cat['overall_total']; ?> kayıt)</span>
			<?php if ( $nizamiye_cat['multi_session'] ) : ?>
				<?php foreach ( $nizamiye_cat['sessions'] as $nizamiye_s ) : ?>
					<span class="chip"><?php echo esc_html( $nizamiye_s['name'] ); ?> <?php echo null !== $nizamiye_s['rate'] ? (int) $nizamiye_s['rate'] . '%' : '—'; ?></span>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php else : ?>
	<h2 class="sec">Yoklama Özeti</h2>
	<p style="color:#94a3b8">Bu dönemde yoklama kaydı yok.</p>
<?php endif; ?>

<?php if ( $nizamiye_report['habits'] ) : ?>
	<h2 class="sec">Alışkanlıklar</h2>
	<table class="data">
		<thead><tr><th>Alışkanlık</th><th>Takip</th><th class="center">Tamamlama</th></tr></thead>
		<tbody>
		<?php foreach ( $nizamiye_report['habits'] as $nizamiye_h ) : ?>
			<tr>
				<td><?php echo esc_html( $nizamiye_h->name ); ?></td>
				<td><?php echo esc_html( nizamiye_habit_track_type_label( $nizamiye_h ) ); ?></td>
				<td class="center" style="color:<?php echo esc_attr( $nizamiye_rate_color( $nizamiye_h->log_count > 0 ? (int) $nizamiye_h->rate : null ) ); ?>"><?php echo $nizamiye_h->log_count > 0 ? (int) $nizamiye_h->rate . '%' : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $nizamiye_report['reading'] ) ) : ?>
	<h2 class="sec">Kitap Okuma</h2>
	<?php foreach ( $nizamiye_report['reading'] as $nizamiye_rh ) :
		$nizamiye_titles = array_map( function ( $nizamiye_b ) { return $nizamiye_b['title']; }, $nizamiye_rh['books'] );
		$nizamiye_shown  = array_slice( $nizamiye_titles, 0, 6 );
		$nizamiye_more   = count( $nizamiye_titles ) - count( $nizamiye_shown );
		?>
		<div class="cat-line">
			<b><?php echo esc_html( $nizamiye_rh['habit_name'] ); ?>:</b>
			<?php echo (int) $nizamiye_rh['total_pages']; ?> sayfa, <?php echo count( $nizamiye_titles ); ?> kitap —
			<?php echo esc_html( implode( ', ', $nizamiye_shown ) ); ?><?php echo $nizamiye_more > 0 ? ' (+' . (int) $nizamiye_more . ' daha)' : ''; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $nizamiye_report['grade_avgs'] ) : ?>
	<h2 class="sec">Ders Ortalamaları</h2>
	<table class="data">
		<thead><tr><th>Ders</th><th class="center">Sınav</th><th class="center">Ortalama</th></tr></thead>
		<tbody>
		<?php foreach ( $nizamiye_report['grade_avgs'] as $nizamiye_g ) : ?>
			<tr>
				<td><?php echo esc_html( $nizamiye_g->class_name ); ?></td>
				<td class="center"><?php echo (int) $nizamiye_g->exam_count; ?></td>
				<td class="center" style="color:<?php echo esc_attr( $nizamiye_rate_color( (int) $nizamiye_g->avg_rate ) ); ?>"><?php echo (int) $nizamiye_g->avg_rate; ?>%</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<div class="foot">
	<?php echo esc_html( $nizamiye_settings['school_name'] ); ?> Öğrenci Takip Sistemi tarafından <?php echo esc_html( date_i18n( 'j F Y, H:i', current_time( 'timestamp' ) ) ); ?> tarihinde oluşturulmuştur.
</div>
