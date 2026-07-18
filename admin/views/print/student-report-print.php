<?php
defined( 'ABSPATH' ) || exit;

/**
 * Öğrenci karnesinin tek sayfalık, bağımsız (WP admin çerçevesi olmadan)
 * yazdırılabilir görünümü. $student_id / $term_id çağıran işleyicide
 * (SMS_Actions::handle_print_report) doğrulanmış olarak gelir.
 */

$report  = SMS_Reports::student_report( $student_id, $term_id );
$student = $report['student'];
if ( ! $student ) {
	wp_die( 'Öğrenci bulunamadı.' );
}

$settings = sms_get_settings();
$term     = SMS_Terms::get( $term_id );
$statuses = sms_attendance_statuses();
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
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>Karne — <?php echo esc_html( sms_student_name( $student ) ); ?></title>
<style>
	* { box-sizing: border-box; }
	@page { size: A4; margin: 12mm; }
	body {
		font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
		font-size: 11px;
		color: #1e293b;
		margin: 0;
		padding: 16px;
		max-width: 800px;
		margin: 0 auto;
	}
	.print-toolbar { text-align: right; margin-bottom: 10px; }
	.print-btn {
		display: inline-flex; align-items: center; gap: 6px;
		background: #4f46e5; color: #fff; border: none; border-radius: 8px;
		padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer;
	}
	.head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 10px; }
	.head h1 { font-size: 17px; margin: 0 0 2px; color: #4f46e5; }
	.head .school { font-size: 12px; font-weight: 600; }
	.head .meta { text-align: right; font-size: 10px; color: #64748b; }

	.identity { display: flex; align-items: center; gap: 12px; background: #f8fafc; border-radius: 10px; padding: 10px 14px; margin-bottom: 10px; }
	.identity .avatar { width: 40px; height: 40px; border-radius: 50%; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; flex-shrink: 0; }
	.identity .name { font-size: 15px; font-weight: 700; margin: 0 0 2px; }
	.identity .sub { color: #64748b; font-size: 10.5px; }

	.tiles { display: flex; gap: 8px; margin-bottom: 10px; }
	.tile { flex: 1; background: #f8fafc; border-radius: 8px; padding: 8px 6px; text-align: center; }
	.tile .v { display: block; font-size: 16px; font-weight: 800; }
	.tile .l { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }

	h2.sec { font-size: 12px; margin: 12px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e2e8f0; color: #334155; }
	table { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 4px; }
	th, td { text-align: left; padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
	th { color: #64748b; font-weight: 600; font-size: 9.5px; text-transform: uppercase; }
	.center { text-align: center; }
	.att-row { display: flex; gap: 10px; }
	.att-box { flex: 1; text-align: center; background: #f8fafc; border-radius: 6px; padding: 6px; }
	.att-box b { display: block; font-size: 14px; }

	.cat-line { margin-bottom: 4px; font-size: 10.5px; }
	.cat-line b { display: inline-block; min-width: 80px; }
	.chip { display: inline-block; background: #f1f5f9; border-radius: 999px; padding: 1px 7px; margin-right: 3px; font-size: 9.5px; }

	.foot { margin-top: 14px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }

	@media print {
		.print-toolbar { display: none; }
		body { padding: 0; }
	}
</style>
</head>
<body>
	<div class="print-toolbar">
		<button class="print-btn" onclick="window.print()">🖨️ Yazdır / PDF Olarak Kaydet</button>
	</div>

	<div class="head">
		<div>
			<h1>ÖĞRENCİ KARNESİ</h1>
			<div class="school"><?php echo esc_html( $settings['school_name'] ); ?></div>
		</div>
		<div class="meta">
			<?php echo esc_html( $term ? $term->name : '' ); ?><br>
			<?php echo esc_html( date_i18n( 'j F Y', current_time( 'timestamp' ) ) ); ?>
		</div>
	</div>

	<div class="identity">
		<div class="avatar"><?php
			$parts = preg_split( '/\s+/', trim( sms_student_name( $student ) ) );
			echo esc_html( mb_strtoupper( mb_substr( $parts[0] ?? '', 0, 1 ) . mb_substr( $parts[ count( $parts ) - 1 ] ?? '', 0, 1 ) ) );
		?></div>
		<div>
			<p class="name"><?php echo esc_html( sms_student_name( $student ) ); ?></p>
			<div class="sub">
				<?php echo $report['enrollment'] ? esc_html( sms_grade_label( $report['enrollment']->grade_level ) ) . ' • ' : ''; ?>
				<?php echo $student->school ? esc_html( $student->school ) . ' • ' : ''; ?>
				<?php echo $student->birth_date ? 'Doğum: ' . esc_html( sms_format_date( $student->birth_date ) ) . ' • ' : ''; ?>
				<?php echo $parent ? 'Veli: ' . esc_html( $parent->display_name ) : ''; ?>
			</div>
		</div>
	</div>

	<div class="tiles">
		<div class="tile"><span class="v" style="color:<?php echo esc_attr( $rate_color( $att['rate'] ) ); ?>"><?php echo null !== $att['rate'] ? (int) $att['rate'] . '%' : '—'; ?></span><span class="l">Devam</span></div>
		<div class="tile"><span class="v" style="color:<?php echo esc_attr( $rate_color( $habit_avg ) ); ?>"><?php echo null !== $habit_avg ? $habit_avg . '%' : '—'; ?></span><span class="l">Alışkanlık</span></div>
		<div class="tile"><span class="v" style="color:<?php echo esc_attr( $rate_color( $grade_avg ) ); ?>"><?php echo null !== $grade_avg ? $grade_avg . '%' : '—'; ?></span><span class="l">Not Ortalaması</span></div>
		<div class="tile"><span class="v"><?php echo (int) $att['total']; ?></span><span class="l">Toplam Yoklama</span></div>
	</div>

	<h2 class="sec">Yoklama Özeti (Ders)</h2>
	<div class="att-row">
		<?php foreach ( $statuses as $key => $label ) : ?>
			<div class="att-box"><b><?php echo (int) $att[ $key ]; ?></b><?php echo esc_html( $label ); ?></div>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $report['att_cats'] ) ) : ?>
		<h2 class="sec">Genel Yoklama (Namaz, Temizlik vb.)</h2>
		<?php foreach ( $report['att_cats'] as $cat ) : ?>
			<div class="cat-line">
				<b><?php echo esc_html( $cat['category'] ); ?>:</b>
				<?php foreach ( $cat['sessions'] as $s ) : ?>
					<span class="chip"><?php echo esc_html( $s['name'] ); ?> <?php echo null !== $s['rate'] ? (int) $s['rate'] . '%' : '—'; ?></span>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( $report['habits'] ) : ?>
		<h2 class="sec">Alışkanlıklar</h2>
		<table>
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
		<table>
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
</body>
</html>
