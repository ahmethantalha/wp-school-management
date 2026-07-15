<?php
defined( 'ABSPATH' ) || exit;

// Veli/öğrenci bu sayfayı açarsa kendi görünümüne yönlendirilir.
if ( ! current_user_can( 'sms_teach' ) ) {
	include __DIR__ . '/my-children.php';
	return;
}

$term_id = sms_current_term_id();
$teacher = sms_is_teacher();
$term    = $term_id ? SMS_Terms::get( $term_id ) : null;

/* ---------- Filtre parametreleri ---------- */
$rtype  = isset( $_GET['rtype'] ) ? sanitize_key( $_GET['rtype'] ) : 'yoklama';
if ( ! in_array( $rtype, array( 'yoklama', 'aliskanlik', 'not', 'genel' ), true ) ) {
	$rtype = 'yoklama';
}
$group  = isset( $_GET['group'] ) && 'sinif' === $_GET['group'] ? 'sinif' : 'ogrenci';
$grade  = isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$metric = isset( $_GET['metric'] ) ? sanitize_key( $_GET['metric'] ) : 'rate';
if ( ! in_array( $metric, array( 'rate', 'present', 'absent', 'late', 'excused' ), true ) ) {
	$metric = 'rate';
}

$namaz  = SMS_Attendance_Types::get_category_by_slug( 'namaz' );
$cat_id = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : ( $namaz ? (int) $namaz->id : 0 );

$default_from = $term && $term->start_date && '0000-00-00' !== $term->start_date
	? $term->start_date
	: gmdate( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
$from = isset( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $default_from;
$to   = isset( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : current_time( 'Y-m-d' );

// Öğretmenler yalnızca sorumlu oldukları öğrencileri analiz edebilir (kayıt düzeyi erişim).
$student_ids = $teacher ? sms_teacher_student_ids() : null;
$grades_list = $term_id ? SMS_Students::grades_in_term( $term_id ) : array();
$categories  = SMS_Attendance_Types::categories( true );

$statuses      = sms_attendance_statuses();
$metric_labels = array( 'rate' => 'Katılım Oranı' ) + $statuses;

/**
 * Yoklama hücresi metrik değeri (yüzde) döndürür.
 */
$cell_value = function ( $cell ) use ( $metric ) {
	if ( ! $cell || $cell['total'] < 1 ) {
		return null;
	}
	if ( 'rate' === $metric ) {
		return (int) $cell['rate'];
	}
	return (int) round( $cell[ $metric ] / $cell['total'] * 100 );
};
// Gelmedi/geç oranlarında yüksek değer kötüdür; renk skalasını ters çevir.
$cell_class = function ( $v ) use ( $metric ) {
	if ( null === $v ) {
		return '';
	}
	return in_array( $metric, array( 'absent', 'late' ), true ) ? sms_rate_class( 100 - $v ) : sms_rate_class( $v );
};

$tabs = array(
	'yoklama'    => array( 'Yoklama Analizi', 'dashicons-clipboard' ),
	'aliskanlik' => array( 'Alışkanlık Analizi', 'dashicons-yes-alt' ),
	'not'        => array( 'Not Analizi', 'dashicons-welcome-write-blog' ),
	'genel'      => array( 'Genel Başarı', 'dashicons-chart-bar' ),
);
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Raporlar', 'Yoklama, alışkanlık ve not verilerini öğrenci ya da sınıf bazında analiz edin. Bireysel karneler için Karneler sayfasını kullanın.' ); ?>

	<div class="sms-tabs">
		<?php foreach ( $tabs as $key => $t ) : ?>
			<a class="sms-tab <?php echo $rtype === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&rtype=' . $key . '&sms_term=' . $term_id ) ); ?>">
				<span class="dashicons <?php echo esc_attr( $t[1] ); ?>"></span> <?php echo esc_html( $t[0] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<input type="hidden" name="page" value="sms-reports">
				<input type="hidden" name="rtype" value="<?php echo esc_attr( $rtype ); ?>">
				<?php if ( $term_id ) : ?><input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>"><?php endif; ?>

				<label class="sms-muted">Gruplama</label>
				<select name="group">
					<option value="ogrenci" <?php selected( $group, 'ogrenci' ); ?>>Öğrenci bazında</option>
					<option value="sinif" <?php selected( $group, 'sinif' ); ?>>Sınıf bazında</option>
				</select>

				<?php if ( 'sinif' !== $group ) : ?>
					<select name="grade">
						<option value="0">Tüm sınıflar</option>
						<?php foreach ( $grades_list as $g ) : ?>
							<option value="<?php echo (int) $g; ?>" <?php selected( $grade, $g ); ?>><?php echo esc_html( sms_grade_label( $g ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<?php if ( 'yoklama' === $rtype ) : ?>
					<select name="cat">
						<?php foreach ( $categories as $c ) : ?>
							<option value="<?php echo (int) $c->id; ?>" <?php selected( $cat_id, (int) $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="metric">
						<?php foreach ( $metric_labels as $mk => $ml ) : ?>
							<option value="<?php echo esc_attr( $mk ); ?>" <?php selected( $metric, $mk ); ?>><?php echo esc_html( $ml ); ?> %</option>
						<?php endforeach; ?>
					</select>
					<label class="sms-muted">Aralık</label>
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				<?php endif; ?>

				<button type="submit" class="sms-btn sms-btn-primary sms-btn-sm">Analiz Et</button>
			</form>
		</div>
	</div>

	<?php if ( ! $term_id ) : ?>
		<div class="sms-card sms-mt"><div class="sms-empty"><span class="dashicons dashicons-calendar-alt"></span><h2>Aktif dönem yok</h2></div></div>
	</div>
	<?php return; endif; ?>

	<?php
	/* ================= YOKLAMA ANALİZİ ================= */
	if ( 'yoklama' === $rtype ) :
		$category = $cat_id ? SMS_Attendance_Types::get_category( $cat_id ) : null;
		$matrix   = $category ? SMS_Reports::attendance_matrix( $term_id, $cat_id, $from, $to, 'sinif' === $group ? 0 : $grade, $student_ids ) : array( 'sessions' => array(), 'rows' => array(), 'totals' => array() );
		$sessions = $matrix['sessions'];

		// Sınıf bazında gruplama: satırları sınıf seviyesine göre topla.
		$empty_c = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'rate' => null );
		$calc_c  = function ( $c ) {
			if ( $c['total'] > 0 ) {
				$c['rate'] = round( ( $c['present'] + 0.5 * $c['late'] ) / $c['total'] * 100 );
			}
			return $c;
		};
		$grade_rows = array();
		if ( 'sinif' === $group ) {
			foreach ( $matrix['rows'] as $row ) {
				$g = (int) ( $row['student']->grade_level ?? 0 );
				if ( ! isset( $grade_rows[ $g ] ) ) {
					$grade_rows[ $g ] = array( 'count' => 0, 'cells' => array_fill_keys( array_map( function ( $s ) { return (int) $s->id; }, $sessions ), $empty_c ), 'overall' => $empty_c );
				}
				$grade_rows[ $g ]['count']++;
				foreach ( $sessions as $s ) {
					$sid = (int) $s->id;
					foreach ( array( 'present', 'absent', 'late', 'excused', 'total' ) as $k ) {
						$grade_rows[ $g ]['cells'][ $sid ][ $k ] += $row['cells'][ $sid ][ $k ];
						$grade_rows[ $g ]['overall'][ $k ]       += $row['cells'][ $sid ][ $k ];
					}
				}
			}
			ksort( $grade_rows );
			foreach ( $grade_rows as $g => $gr ) {
				foreach ( $gr['cells'] as $sid => $c ) {
					$grade_rows[ $g ]['cells'][ $sid ] = $calc_c( $c );
				}
				$grade_rows[ $g ]['overall'] = $calc_c( $gr['overall'] );
			}
		}
		?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head">
				<h2><?php echo esc_html( ( $category ? $category->name : '' ) . ' — ' . $metric_labels[ $metric ] ); ?></h2>
				<span class="sms-muted"><?php echo esc_html( sms_format_date( $from ) . ' – ' . sms_format_date( $to ) ); ?></span>
			</div>
			<?php if ( $matrix['rows'] ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<?php foreach ( $sessions as $s ) : ?><th class="sms-center"><?php echo esc_html( $s->name ); ?></th><?php endforeach; ?>
							<th class="sms-center">Genel</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $group ) : ?>
						<?php foreach ( $grade_rows as $g => $gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( sms_grade_label( $g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $gr['count']; ?> öğrenci)</span></td>
								<?php foreach ( $sessions as $s ) : $v = $cell_value( $gr['cells'][ (int) $s->id ] ); ?>
									<td class="sms-center"><span class="sms-score <?php echo esc_attr( $cell_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span>
									<?php if ( null !== $v ) : ?><span class="sms-cell-sub"><?php echo (int) $gr['cells'][ (int) $s->id ][ 'rate' === $metric ? 'present' : $metric ]; ?>/<?php echo (int) $gr['cells'][ (int) $s->id ]['total']; ?></span><?php endif; ?></td>
								<?php endforeach; ?>
								<?php $v = $cell_value( $gr['overall'] ); ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( $cell_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $matrix['rows'] as $row ) : $st = $row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo sms_avatar( sms_student_name( $st ) ); // phpcs:ignore ?>
									<div><a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $st->id . '&sms_term=' . $term_id ) ); ?>"><strong><?php echo esc_html( sms_student_name( $st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $st->grade_level ) ? esc_html( sms_grade_label( $st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php foreach ( $sessions as $s ) : $cell = $row['cells'][ (int) $s->id ]; $v = $cell_value( $cell ); ?>
									<td class="sms-center">
										<span class="sms-score <?php echo esc_attr( $cell_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span>
										<?php if ( null !== $v ) : ?><span class="sms-cell-sub"><?php echo (int) $cell[ 'rate' === $metric ? 'present' : $metric ]; ?>/<?php echo (int) $cell['total']; ?></span><?php endif; ?>
									</td>
								<?php endforeach; ?>
								<?php $v = $cell_value( $row['overall'] ); ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( $cell_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php foreach ( $sessions as $s ) : $v = $cell_value( $matrix['totals'][ (int) $s->id ] ?? null ); ?>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( $cell_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							<?php endforeach; ?>
							<?php $v = $cell_value( $matrix['totals']['overall'] ?? null ); ?>
							<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( $cell_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="sms-empty"><span class="dashicons dashicons-clipboard"></span><h2>Kayıt bulunamadı</h2><p>Seçilen aralıkta bu türde yoklama kaydı yok.</p></div>
			<?php endif; ?>
		</div>

	<?php
	/* ================= ALIŞKANLIK ANALİZİ ================= */
	elseif ( 'aliskanlik' === $rtype ) :
		$matrix = SMS_Reports::habit_matrix( $term_id, 'sinif' === $group ? 0 : $grade, $student_ids );
		$habits = $matrix['habits'];

		$grade_rows = array();
		if ( 'sinif' === $group && $matrix['rows'] ) {
			foreach ( $matrix['rows'] as $row ) {
				$g = (int) ( $row['student']->grade_level ?? 0 );
				if ( ! isset( $grade_rows[ $g ] ) ) {
					$grade_rows[ $g ] = array( 'count' => 0, 'sum' => array(), 'cnt' => array() );
				}
				$grade_rows[ $g ]['count']++;
				foreach ( $habits as $h ) {
					$cell = $row['cells'][ (int) $h->id ];
					if ( $cell ) {
						$grade_rows[ $g ]['sum'][ (int) $h->id ] = ( $grade_rows[ $g ]['sum'][ (int) $h->id ] ?? 0 ) + $cell['rate'];
						$grade_rows[ $g ]['cnt'][ (int) $h->id ] = ( $grade_rows[ $g ]['cnt'][ (int) $h->id ] ?? 0 ) + 1;
					}
				}
			}
			ksort( $grade_rows );
		}
		?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>Alışkanlık Tamamlama Oranları</h2><span class="sms-muted">Dönem geneli</span></div>
			<?php if ( $matrix['rows'] && $habits ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<?php foreach ( $habits as $h ) : ?><th class="sms-center"><?php echo esc_html( $h->name ); ?></th><?php endforeach; ?>
							<th class="sms-center">Genel</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $group ) : ?>
						<?php foreach ( $grade_rows as $g => $gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( sms_grade_label( $g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $gr['count']; ?> öğrenci)</span></td>
								<?php
								$g_sum = 0;
								$g_cnt = 0;
								foreach ( $habits as $h ) :
									$hid = (int) $h->id;
									$v   = isset( $gr['cnt'][ $hid ] ) ? (int) round( $gr['sum'][ $hid ] / $gr['cnt'][ $hid ] ) : null;
									if ( null !== $v ) {
										$g_sum += $v;
										$g_cnt++;
									}
									?>
									<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
								<?php endforeach; ?>
								<?php $v = $g_cnt ? (int) round( $g_sum / $g_cnt ) : null; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( sms_rate_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $matrix['rows'] as $row ) : $st = $row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo sms_avatar( sms_student_name( $st ) ); // phpcs:ignore ?>
									<div><a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $st->id . '&sms_term=' . $term_id ) ); ?>"><strong><?php echo esc_html( sms_student_name( $st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $st->grade_level ) ? esc_html( sms_grade_label( $st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php foreach ( $habits as $h ) : $cell = $row['cells'][ (int) $h->id ]; ?>
									<td class="sms-center">
										<?php if ( $cell ) : ?>
											<span class="sms-score <?php echo esc_attr( sms_rate_class( $cell['rate'] ) ); ?>"><?php echo (int) $cell['rate']; ?>%</span>
											<span class="sms-cell-sub"><?php echo (int) $cell['logs']; ?> kayıt</span>
										<?php else : ?>—<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( sms_rate_class( $row['overall'] ) ); ?>"><?php echo null !== $row['overall'] ? $row['overall'] . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php foreach ( $habits as $h ) : $v = $matrix['totals'][ (int) $h->id ]; ?>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							<?php endforeach; ?>
							<td></td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="sms-empty"><span class="dashicons dashicons-yes-alt"></span><h2>Veri yok</h2><p>Bu dönemde alışkanlık kaydı bulunmuyor.</p></div>
			<?php endif; ?>
		</div>

	<?php
	/* ================= NOT ANALİZİ ================= */
	elseif ( 'not' === $rtype ) :
		$matrix  = SMS_Reports::grade_matrix( $term_id, 'sinif' === $group ? 0 : $grade, $student_ids );
		$classes = $matrix['classes'];

		$grade_rows = array();
		if ( 'sinif' === $group && $matrix['rows'] ) {
			foreach ( $matrix['rows'] as $row ) {
				$g = (int) ( $row['student']->grade_level ?? 0 );
				if ( ! isset( $grade_rows[ $g ] ) ) {
					$grade_rows[ $g ] = array( 'count' => 0, 'sum' => array(), 'cnt' => array() );
				}
				$grade_rows[ $g ]['count']++;
				foreach ( $classes as $c ) {
					$cell = $row['cells'][ (int) $c->id ];
					if ( $cell ) {
						$grade_rows[ $g ]['sum'][ (int) $c->id ] = ( $grade_rows[ $g ]['sum'][ (int) $c->id ] ?? 0 ) + $cell['rate'];
						$grade_rows[ $g ]['cnt'][ (int) $c->id ] = ( $grade_rows[ $g ]['cnt'][ (int) $c->id ] ?? 0 ) + 1;
					}
				}
			}
			ksort( $grade_rows );
		}
		?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>Not Ortalamaları (%)</h2><span class="sms-muted">Derslik bazında, dönem geneli</span></div>
			<?php if ( $matrix['rows'] && $classes ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<?php foreach ( $classes as $c ) : ?><th class="sms-center"><?php echo esc_html( $c->name ); ?></th><?php endforeach; ?>
							<th class="sms-center">Genel</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $group ) : ?>
						<?php foreach ( $grade_rows as $g => $gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( sms_grade_label( $g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $gr['count']; ?> öğrenci)</span></td>
								<?php
								$g_sum = 0;
								$g_cnt = 0;
								foreach ( $classes as $c ) :
									$cid = (int) $c->id;
									$v   = isset( $gr['cnt'][ $cid ] ) ? (int) round( $gr['sum'][ $cid ] / $gr['cnt'][ $cid ] ) : null;
									if ( null !== $v ) {
										$g_sum += $v;
										$g_cnt++;
									}
									?>
									<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
								<?php endforeach; ?>
								<?php $v = $g_cnt ? (int) round( $g_sum / $g_cnt ) : null; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( sms_rate_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $matrix['rows'] as $row ) : if ( ! $row['has_any'] ) { continue; } $st = $row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo sms_avatar( sms_student_name( $st ) ); // phpcs:ignore ?>
									<div><a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $st->id . '&sms_term=' . $term_id ) ); ?>"><strong><?php echo esc_html( sms_student_name( $st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $st->grade_level ) ? esc_html( sms_grade_label( $st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php foreach ( $classes as $c ) : $cell = $row['cells'][ (int) $c->id ]; ?>
									<td class="sms-center">
										<?php if ( $cell ) : ?>
											<span class="sms-score <?php echo esc_attr( sms_rate_class( $cell['rate'] ) ); ?>"><?php echo (int) $cell['rate']; ?>%</span>
											<span class="sms-cell-sub"><?php echo (int) $cell['exams']; ?> sınav</span>
										<?php else : ?>—<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( sms_rate_class( $row['overall'] ) ); ?>"><?php echo null !== $row['overall'] ? $row['overall'] . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php foreach ( $classes as $c ) : $v = $matrix['totals'][ (int) $c->id ]; ?>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $v ) ); ?>"><?php echo null !== $v ? $v . '%' : '—'; ?></span></td>
							<?php endforeach; ?>
							<td></td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="sms-empty"><span class="dashicons dashicons-welcome-write-blog"></span><h2>Veri yok</h2><p>Bu dönemde not kaydı bulunmuyor.</p></div>
			<?php endif; ?>
		</div>

	<?php
	/* ================= GENEL BAŞARI ================= */
	else :
		if ( 'sinif' === $group ) :
			$summary = SMS_Reports::grade_level_summary( $term_id, $student_ids );
			?>
			<div class="sms-card sms-mt">
				<div class="sms-card-head"><h2>Sınıf Bazında Genel Özet</h2></div>
				<?php if ( $summary ) : ?>
					<div class="sms-table-scroll">
					<table class="sms-table">
						<thead><tr><th>Sınıf</th><th>Öğrenci</th><th class="sms-center">Devam</th><th class="sms-center">Alışkanlık</th><th class="sms-center">Not Ort.</th></tr></thead>
						<tbody>
						<?php foreach ( $summary as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( sms_grade_label( $row['grade'] ) ); ?></strong></td>
								<td><?php echo (int) $row['count']; ?></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['att'] ) ); ?>"><?php echo null !== $row['att'] ? $row['att'] . '%' : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['habit'] ) ); ?>"><?php echo null !== $row['habit'] ? $row['habit'] . '%' : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['grade_avg'] ) ); ?>"><?php echo null !== $row['grade_avg'] ? $row['grade_avg'] . '%' : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				<?php else : ?>
					<div class="sms-empty"><h2>Veri yok</h2></div>
				<?php endif; ?>
			</div>
		<?php else :
			$scores = SMS_Reports::student_scores( $term_id, $student_ids );
			if ( $grade ) {
				$scores = array_values( array_filter( $scores, function ( $r ) use ( $grade ) {
					return (int) ( $r['student']->grade_level ?? 0 ) === $grade;
				} ) );
			}
			?>
			<div class="sms-card sms-mt">
				<div class="sms-card-head"><h2>Genel Başarı Sıralaması</h2><span class="sms-muted">Bileşik skor: %40 devam + %40 alışkanlık + %20 not</span></div>
				<?php if ( $scores ) : ?>
					<div class="sms-table-scroll">
					<table class="sms-table">
						<thead><tr><th>#</th><th>Öğrenci</th><th>Sınıf</th><th class="sms-center">Devam</th><th class="sms-center">Alışkanlık</th><th class="sms-center">Not Ort.</th><th class="sms-center">Skor</th></tr></thead>
						<tbody>
						<?php foreach ( $scores as $i => $row ) : $s = $row['student']; ?>
							<tr>
								<td class="sms-muted">#<?php echo $i + 1; ?></td>
								<td class="sms-name-cell"><?php echo sms_avatar( sms_student_name( $s ) ); // phpcs:ignore ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $s->id . '&sms_term=' . $term_id ) ); ?>"><strong><?php echo esc_html( sms_student_name( $s ) ); ?></strong></a></td>
								<td><?php echo isset( $s->grade_level ) ? esc_html( sms_grade_label( $s->grade_level ) ) : '—'; ?></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['attendance'] ) ); ?>"><?php echo null !== $row['attendance'] ? $row['attendance'] . '%' : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['habit'] ) ); ?>"><?php echo null !== $row['habit'] ? $row['habit'] . '%' : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['grade'] ) ); ?>"><?php echo null !== $row['grade'] ? $row['grade'] . '%' : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( sms_rate_class( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				<?php else : ?>
					<div class="sms-empty"><h2>Veri yok</h2></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
