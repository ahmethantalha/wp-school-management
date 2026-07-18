<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tek bir öğrencinin karne içeriği (yalnızca gövde — <html>/<head>/<body> sarmalayıcısı yok).
 * student-report-print.php bu parçayı sarmalar; toplu indirmede (SMS_Actions::handle_print_report_bulk)
 * her öğrenci için ayrı ayrı PDF üretilirken aynı şablon döngü içinde tekrar kullanılır.
 * Beklenen: $student_id, $term_id tanımlı.
 */

$report  = SMS_Reports::student_report( $student_id, $term_id );
$student = $report['student'];
if ( ! $student ) {
	return;
}

$settings = sms_get_settings();
$term     = SMS_Terms::get( $term_id );
$att      = $report['att_all'];

$habit_rates = array_filter( array_map( function ( $h ) {
	return $h->log_count > 0 ? (int) $h->rate : null;
}, $report['habits'] ), function ( $v ) { return null !== $v; } );
$habit_avg = $habit_rates ? (int) round( array_sum( $habit_rates ) / count( $habit_rates ) ) : null;

$grade_rates = array_map( function ( $g ) { return (int) $g->avg_rate; }, $report['grade_avgs'] );
$grade_avg   = $grade_rates ? (int) round( array_sum( $grade_rates ) / count( $grade_rates ) ) : null;

$parent = $student->parent_user_id ? get_userdata( (int) $student->parent_user_id ) : null;

$rate_color = function ( $v ) {
	if ( null === $v ) {
		return '#94a3b8';
	}
	if ( $v >= 75 ) {
		return '#16a34a';
	}
	if ( $v >= 50 ) {
		return '#d97706';
	}
	return '#dc2626';
};
?>
<table class="head-table"><tr>
	<td class="head">
		<h1>ÖĞRENCİ KARNESİ</h1>
		<div class="school"><?php echo esc_html( $settings['school_name'] ); ?></div>
	</td>
	<td class="head-meta">
		<?php echo esc_html( $term ? $term->name : '' ); ?><br>
		<?php echo esc_html( date_i18n( 'j F Y', current_time( 'timestamp' ) ) ); ?>
	</td>
</tr></table>

<table class="identity"><tr>
	<td class="avatar-cell">
		<div class="avatar"><?php
			$parts = preg_split( '/\s+/', trim( sms_student_name( $student ) ) );
			echo esc_html( mb_strtoupper( mb_substr( $parts[0] ?? '', 0, 1 ) . mb_substr( $parts[ count( $parts ) - 1 ] ?? '', 0, 1 ) ) );
		?></div>
	</td>
	<td>
		<p class="name"><?php echo esc_html( sms_student_name( $student ) ); ?></p>
		<div class="sub">
			<?php echo $report['enrollment'] ? esc_html( sms_grade_label( $report['enrollment']->grade_level ) ) . ' • ' : ''; ?>
			<?php echo $student->school ? esc_html( $student->school ) . ' • ' : ''; ?>
			<?php echo $student->birth_date ? 'Doğum: ' . esc_html( sms_format_date( $student->birth_date ) ) . ' • ' : ''; ?>
			<?php echo $parent ? 'Veli: ' . esc_html( $parent->display_name ) : ''; ?>
		</div>
	</td>
</tr></table>

<table class="tiles"><tr>
	<td><div class="tile"><span class="v" style="color:<?php echo esc_attr( $rate_color( $att['rate'] ) ); ?>"><?php echo null !== $att['rate'] ? (int) $att['rate'] . '%' : '—'; ?></span><span class="l">Devam</span></div></td>
	<td><div class="tile"><span class="v" style="color:<?php echo esc_attr( $rate_color( $habit_avg ) ); ?>"><?php echo null !== $habit_avg ? $habit_avg . '%' : '—'; ?></span><span class="l">Alışkanlık</span></div></td>
	<td><div class="tile"><span class="v" style="color:<?php echo esc_attr( $rate_color( $grade_avg ) ); ?>"><?php echo null !== $grade_avg ? $grade_avg . '%' : '—'; ?></span><span class="l">Not Ortalaması</span></div></td>
	<td><div class="tile"><span class="v"><?php echo (int) $att['total']; ?></span><span class="l">Toplam Yoklama</span></div></td>
</tr></table>

<?php if ( $report['att_cats'] ) : ?>
	<h2 class="sec">Yoklama Özeti (Yoklama Türüne Göre)</h2>
	<?php foreach ( $report['att_cats'] as $cat ) : ?>
		<div class="cat-line">
			<b><?php echo esc_html( $cat['category'] ); ?>:</b>
			<span style="color:<?php echo esc_attr( $rate_color( $cat['overall_rate'] ) ); ?>;font-weight:700"><?php echo null !== $cat['overall_rate'] ? (int) $cat['overall_rate'] . '%' : '—'; ?></span>
			<span style="color:#94a3b8">(<?php echo (int) $cat['overall_total']; ?> kayıt)</span>
			<?php if ( $cat['multi_session'] ) : ?>
				<?php foreach ( $cat['sessions'] as $s ) : ?>
					<span class="chip"><?php echo esc_html( $s['name'] ); ?> <?php echo null !== $s['rate'] ? (int) $s['rate'] . '%' : '—'; ?></span>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php else : ?>
	<h2 class="sec">Yoklama Özeti</h2>
	<p style="color:#94a3b8">Bu dönemde yoklama kaydı yok.</p>
<?php endif; ?>

<?php if ( $report['habits'] ) : ?>
	<h2 class="sec">Alışkanlıklar</h2>
	<table class="data">
		<thead><tr><th>Alışkanlık</th><th>Takip</th><th class="center">Tamamlama</th></tr></thead>
		<tbody>
		<?php foreach ( $report['habits'] as $h ) : ?>
			<tr>
				<td><?php echo esc_html( $h->name ); ?></td>
				<td><?php echo esc_html( sms_habit_track_type_label( $h ) ); ?></td>
				<td class="center" style="color:<?php echo esc_attr( $rate_color( $h->log_count > 0 ? (int) $h->rate : null ) ); ?>"><?php echo $h->log_count > 0 ? (int) $h->rate . '%' : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $report['reading'] ) ) : ?>
	<h2 class="sec">Kitap Okuma</h2>
	<?php foreach ( $report['reading'] as $rh ) :
		$titles = array_map( function ( $b ) { return $b['title']; }, $rh['books'] );
		$shown  = array_slice( $titles, 0, 6 );
		$more   = count( $titles ) - count( $shown );
		?>
		<div class="cat-line">
			<b><?php echo esc_html( $rh['habit_name'] ); ?>:</b>
			<?php echo (int) $rh['total_pages']; ?> sayfa, <?php echo count( $titles ); ?> kitap —
			<?php echo esc_html( implode( ', ', $shown ) ); ?><?php echo $more > 0 ? ' (+' . (int) $more . ' daha)' : ''; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $report['grade_avgs'] ) : ?>
	<h2 class="sec">Ders Ortalamaları</h2>
	<table class="data">
		<thead><tr><th>Ders</th><th class="center">Sınav</th><th class="center">Ortalama</th></tr></thead>
		<tbody>
		<?php foreach ( $report['grade_avgs'] as $g ) : ?>
			<tr>
				<td><?php echo esc_html( $g->class_name ); ?></td>
				<td class="center"><?php echo (int) $g->exam_count; ?></td>
				<td class="center" style="color:<?php echo esc_attr( $rate_color( (int) $g->avg_rate ) ); ?>"><?php echo (int) $g->avg_rate; ?>%</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<div class="foot">
	<?php echo esc_html( $settings['school_name'] ); ?> Öğrenci Takip Sistemi tarafından <?php echo esc_html( date_i18n( 'j F Y, H:i', current_time( 'timestamp' ) ) ); ?> tarihinde oluşturulmuştur.
</div>
