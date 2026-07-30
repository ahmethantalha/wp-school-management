<?php
defined( 'ABSPATH' ) || exit;

// Veli/öğrenci bu sayfayı açarsa kendi görünümüne yönlendirilir.
if ( ! current_user_can( 'nizamiye_teach' ) ) {
	include __DIR__ . '/my-children.php';
	return;
}

$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_teacher = nizamiye_is_teacher();
$nizamiye_term    = $nizamiye_term_id ? Nizamiye_Terms::get( $nizamiye_term_id ) : null;

/* ---------- Filtre parametreleri ---------- */
// Nonce eksik/geçersizse (ör. eski bir yer imi) filtreler yok sayılır, varsayılan görünüm yüklenir.
$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_rtype  = $nizamiye_has_nonce && isset( $_GET['rtype'] ) ? sanitize_key( $_GET['rtype'] ) : 'yoklama';
if ( ! in_array( $nizamiye_rtype, array( 'yoklama', 'aliskanlik', 'not', 'genel' ), true ) ) {
	$nizamiye_rtype = 'yoklama';
}
$nizamiye_group  = $nizamiye_has_nonce && isset( $_GET['group'] ) && 'sinif' === $_GET['group'] ? 'sinif' : 'ogrenci';
$nizamiye_grade  = $nizamiye_has_nonce && isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$nizamiye_metric = $nizamiye_has_nonce && isset( $_GET['metric'] ) ? sanitize_key( $_GET['metric'] ) : 'rate';
if ( ! in_array( $nizamiye_metric, array( 'rate', 'present', 'absent', 'late', 'excused' ), true ) ) {
	$nizamiye_metric = 'rate';
}

$nizamiye_namaz  = Nizamiye_Attendance_Types::get_category_by_slug( 'namaz' );
$nizamiye_cat_id = $nizamiye_has_nonce && isset( $_GET['cat'] ) ? (int) $_GET['cat'] : ( $nizamiye_namaz ? (int) $nizamiye_namaz->id : 0 );

// Oturum (vakit) odağı: 0 = tüm oturumlar (matris), aksi halde tek vakte kırılım.
$nizamiye_cat_sessions   = $nizamiye_cat_id ? Nizamiye_Attendance_Types::sessions( $nizamiye_cat_id ) : array();
$nizamiye_valid_sess_ids = array_map( function ( $nizamiye_s ) { return (int) $nizamiye_s->id; }, $nizamiye_cat_sessions );
$nizamiye_sess_id        = $nizamiye_has_nonce && isset( $_GET['rsession'] ) ? (int) $_GET['rsession'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
if ( $nizamiye_sess_id && ! in_array( $nizamiye_sess_id, $nizamiye_valid_sess_ids, true ) ) {
	$nizamiye_sess_id = 0; // kategoriye ait olmayan (bayat) oturum seçimini sıfırla.
}
$nizamiye_focus_session = null;
foreach ( $nizamiye_cat_sessions as $nizamiye_s ) {
	if ( (int) $nizamiye_s->id === $nizamiye_sess_id ) {
		$nizamiye_focus_session = $nizamiye_s;
		break;
	}
}

$nizamiye_default_from = $nizamiye_term && $nizamiye_term->start_date && '0000-00-00' !== $nizamiye_term->start_date
	? $nizamiye_term->start_date
	: gmdate( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
$nizamiye_dates      = nizamiye_resolve_report_dates( $nizamiye_default_from );
$nizamiye_date_mode  = $nizamiye_dates['mode'];
$nizamiye_sel_month  = $nizamiye_dates['month'];
$nizamiye_sel_year   = $nizamiye_dates['year'];
$nizamiye_from       = $nizamiye_dates['from'];
$nizamiye_to         = $nizamiye_dates['to'];
$nizamiye_year_options = range( (int) current_time( 'Y' ) - 3, (int) current_time( 'Y' ) + 1 );
$nizamiye_month_names  = nizamiye_month_names();

// Öğretmenler yalnızca sorumlu oldukları öğrencileri analiz edebilir (kayıt düzeyi erişim).
$nizamiye_student_ids = $nizamiye_teacher ? nizamiye_teacher_student_ids() : null;
$nizamiye_grades_list = $nizamiye_term_id ? Nizamiye_Students::grades_in_term( $nizamiye_term_id ) : array();
$nizamiye_categories  = Nizamiye_Attendance_Types::categories( true );

$nizamiye_statuses      = nizamiye_attendance_statuses();
$nizamiye_metric_labels = array( 'rate' => 'Katılım Oranı' ) + $nizamiye_statuses;

/**
 * Yoklama hücresi metrik değeri (yüzde) döndürür.
 */
$nizamiye_cell_value = function ( $nizamiye_cell ) use ( $nizamiye_metric ) {
	if ( ! $nizamiye_cell || $nizamiye_cell['total'] < 1 ) {
		return null;
	}
	if ( 'rate' === $nizamiye_metric ) {
		return (int) $nizamiye_cell['rate'];
	}
	return (int) round( $nizamiye_cell[ $nizamiye_metric ] / $nizamiye_cell['total'] * 100 );
};
// Gelmedi/geç oranlarında yüksek değer kötüdür; renk skalasını ters çevir.
$nizamiye_cell_class = function ( $nizamiye_v ) use ( $nizamiye_metric ) {
	if ( null === $nizamiye_v ) {
		return '';
	}
	return in_array( $nizamiye_metric, array( 'absent', 'late' ), true ) ? nizamiye_rate_class( 100 - $nizamiye_v ) : nizamiye_rate_class( $nizamiye_v );
};

$nizamiye_tabs = array(
	'yoklama'    => array( 'Yoklama Analizi', 'dashicons-clipboard' ),
	'aliskanlik' => array( 'Alışkanlık Analizi', 'dashicons-yes-alt' ),
	'not'        => array( 'Not Analizi', 'dashicons-welcome-write-blog' ),
	'genel'      => array( 'Genel Başarı', 'dashicons-chart-bar' ),
);

// Geçerli filtrelerle CSV dışa aktarma bağlantısı (nonce'lu; kişisel veri içerir).
$nizamiye_export_url = wp_nonce_url( add_query_arg( array(
	'action'   => 'nizamiye_export_report',
	'rtype'    => $nizamiye_rtype,
	'group'    => $nizamiye_group,
	'grade'    => $nizamiye_grade,
	'cat'      => $nizamiye_cat_id,
	'rsession' => $nizamiye_sess_id,
	'metric'   => $nizamiye_metric,
	'datemode' => $nizamiye_date_mode,
	'from'     => $nizamiye_from,
	'to'       => $nizamiye_to,
	'rmonth'   => $nizamiye_sel_month,
	'ryear'    => $nizamiye_sel_year,
	'nizamiye_term' => $nizamiye_term_id,
), admin_url( 'admin-post.php' ) ), 'nizamiye_export_report' );
$nizamiye_export_btn = '<a class="sms-btn sms-btn-ghost sms-btn-sm" href="' . esc_url( $nizamiye_export_url ) . '"><span class="dashicons dashicons-download"></span> CSV İndir</a>';
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Raporlar', 'Yoklama, alışkanlık ve not verilerini öğrenci ya da sınıf bazında analiz edin. Bireysel karneler için Karneler sayfasını kullanın.' ); ?>

	<div class="sms-tabs">
		<?php foreach ( $nizamiye_tabs as $nizamiye_key => $nizamiye_t ) : ?>
			<a class="sms-tab <?php echo $nizamiye_rtype === $nizamiye_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&rtype=' . $nizamiye_key . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">
				<span class="dashicons <?php echo esc_attr( $nizamiye_t[1] ); ?>"></span> <?php echo esc_html( $nizamiye_t[0] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<?php nizamiye_view_nonce_field(); ?>
				<input type="hidden" name="page" value="nizamiye-reports">
				<input type="hidden" name="rtype" value="<?php echo esc_attr( $nizamiye_rtype ); ?>">
				<?php if ( $nizamiye_term_id ) : ?><input type="hidden" name="nizamiye_term" value="<?php echo (int) $nizamiye_term_id; ?>"><?php endif; ?>

				<label class="sms-muted">Gruplama</label>
				<select name="group">
					<option value="ogrenci" <?php selected( $nizamiye_group, 'ogrenci' ); ?>>Öğrenci bazında</option>
					<option value="sinif" <?php selected( $nizamiye_group, 'sinif' ); ?>>Sınıf bazında</option>
				</select>

				<?php if ( 'sinif' !== $nizamiye_group ) : ?>
					<select name="grade">
						<option value="0">Tüm sınıflar</option>
						<?php foreach ( $nizamiye_grades_list as $nizamiye_g ) : ?>
							<option value="<?php echo (int) $nizamiye_g; ?>" <?php selected( $nizamiye_grade, $nizamiye_g ); ?>><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<?php if ( 'yoklama' === $nizamiye_rtype ) : ?>
					<select name="cat">
						<?php foreach ( $nizamiye_categories as $nizamiye_c ) : ?>
							<option value="<?php echo (int) $nizamiye_c->id; ?>" <?php selected( $nizamiye_cat_id, (int) $nizamiye_c->id ); ?>><?php echo esc_html( $nizamiye_c->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( count( $nizamiye_cat_sessions ) > 1 ) : ?>
						<select name="rsession">
							<option value="0" <?php selected( $nizamiye_sess_id, 0 ); ?>>Tüm vakitler (matris)</option>
							<?php foreach ( $nizamiye_cat_sessions as $nizamiye_s ) : ?>
								<option value="<?php echo (int) $nizamiye_s->id; ?>" <?php selected( $nizamiye_sess_id, (int) $nizamiye_s->id ); ?>><?php echo esc_html( $nizamiye_s->name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
					<?php if ( ! $nizamiye_focus_session ) : ?>
						<select name="metric">
							<?php foreach ( $nizamiye_metric_labels as $nizamiye_mk => $nizamiye_ml ) : ?>
								<option value="<?php echo esc_attr( $nizamiye_mk ); ?>" <?php selected( $nizamiye_metric, $nizamiye_mk ); ?>><?php echo esc_html( $nizamiye_ml ); ?> %</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="hidden" name="metric" value="<?php echo esc_attr( $nizamiye_metric ); ?>">
					<?php endif; ?>

					<select name="datemode" data-sms-datemode-toggle>
						<option value="range" <?php selected( $nizamiye_date_mode, 'range' ); ?>>Tarih Aralığı</option>
						<option value="month" <?php selected( $nizamiye_date_mode, 'month' ); ?>>Ay / Yıl</option>
					</select>
					<span class="sms-daterange-fields" <?php echo 'month' === $nizamiye_date_mode ? 'style="display:none"' : ''; ?>>
						<input type="date" name="from" value="<?php echo esc_attr( $nizamiye_from ); ?>" class="sms-date-compact">
						<input type="date" name="to" value="<?php echo esc_attr( $nizamiye_to ); ?>" class="sms-date-compact">
					</span>
					<span class="sms-monthyear-fields" <?php echo 'month' !== $nizamiye_date_mode ? 'style="display:none"' : ''; ?>>
						<select name="rmonth">
							<option value="0" <?php selected( $nizamiye_sel_month, 0 ); ?>>Tüm Yıl</option>
							<?php foreach ( $nizamiye_month_names as $nizamiye_mnum => $nizamiye_mname ) : ?>
								<option value="<?php echo (int) $nizamiye_mnum; ?>" <?php selected( $nizamiye_sel_month, $nizamiye_mnum ); ?>><?php echo esc_html( $nizamiye_mname ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="ryear">
							<?php foreach ( $nizamiye_year_options as $nizamiye_y ) : ?>
								<option value="<?php echo (int) $nizamiye_y; ?>" <?php selected( $nizamiye_sel_year, $nizamiye_y ); ?>><?php echo (int) $nizamiye_y; ?></option>
							<?php endforeach; ?>
						</select>
					</span>
				<?php endif; ?>

				<button type="submit" class="sms-btn sms-btn-primary sms-btn-sm">Analiz Et</button>
			</form>
		</div>
	</div>

	<?php if ( ! $nizamiye_term_id ) : ?>
		<div class="sms-card sms-mt"><div class="sms-empty"><span class="dashicons dashicons-calendar-alt"></span><h2>Aktif dönem yok</h2></div></div>
	</div>
	<?php return; endif; ?>

	<?php
	/* ================= YOKLAMA ANALİZİ ================= */
	if ( 'yoklama' === $nizamiye_rtype ) :
		$nizamiye_category = $nizamiye_cat_id ? Nizamiye_Attendance_Types::get_category( $nizamiye_cat_id ) : null;
		$nizamiye_matrix   = $nizamiye_category ? Nizamiye_Reports::attendance_matrix( $nizamiye_term_id, $nizamiye_cat_id, $nizamiye_from, $nizamiye_to, 'sinif' === $nizamiye_group ? 0 : $nizamiye_grade, $nizamiye_student_ids ) : array( 'sessions' => array(), 'rows' => array(), 'totals' => array() );
		$nizamiye_sessions = $nizamiye_matrix['sessions'];

		// Sınıf bazında gruplama: satırları sınıf seviyesine göre topla.
		$nizamiye_empty_c = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'rate' => null );
		$nizamiye_calc_c  = function ( $nizamiye_c ) {
			if ( $nizamiye_c['total'] > 0 ) {
				$nizamiye_c['rate'] = round( ( $nizamiye_c['present'] + 0.5 * $nizamiye_c['late'] ) / $nizamiye_c['total'] * 100 );
			}
			return $nizamiye_c;
		};
		$nizamiye_grade_rows = array();
		if ( 'sinif' === $nizamiye_group ) {
			foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) {
				$nizamiye_g = (int) ( $nizamiye_row['student']->grade_level ?? 0 );
				if ( ! isset( $nizamiye_grade_rows[ $nizamiye_g ] ) ) {
					$nizamiye_grade_rows[ $nizamiye_g ] = array( 'count' => 0, 'cells' => array_fill_keys( array_map( function ( $nizamiye_s ) { return (int) $nizamiye_s->id; }, $nizamiye_sessions ), $nizamiye_empty_c ), 'overall' => $nizamiye_empty_c );
				}
				$nizamiye_grade_rows[ $nizamiye_g ]['count']++;
				foreach ( $nizamiye_sessions as $nizamiye_s ) {
					$nizamiye_sid = (int) $nizamiye_s->id;
					foreach ( array( 'present', 'absent', 'late', 'excused', 'total' ) as $nizamiye_k ) {
						$nizamiye_grade_rows[ $nizamiye_g ]['cells'][ $nizamiye_sid ][ $nizamiye_k ] += $nizamiye_row['cells'][ $nizamiye_sid ][ $nizamiye_k ];
						$nizamiye_grade_rows[ $nizamiye_g ]['overall'][ $nizamiye_k ]       += $nizamiye_row['cells'][ $nizamiye_sid ][ $nizamiye_k ];
					}
				}
			}
			ksort( $nizamiye_grade_rows );
			foreach ( $nizamiye_grade_rows as $nizamiye_g => $nizamiye_gr ) {
				foreach ( $nizamiye_gr['cells'] as $nizamiye_sid => $nizamiye_c ) {
					$nizamiye_grade_rows[ $nizamiye_g ]['cells'][ $nizamiye_sid ] = $nizamiye_calc_c( $nizamiye_c );
				}
				$nizamiye_grade_rows[ $nizamiye_g ]['overall'] = $nizamiye_calc_c( $nizamiye_gr['overall'] );
			}
		}
		?>
		<?php
		// Odak (tek vakit) tablosunda durum hücrelerini basan yardımcı.
		$nizamiye_focus_cells = function ( $nizamiye_c ) {
			$nizamiye_out = '';
			foreach ( array( 'present', 'absent', 'late', 'excused' ) as $nizamiye_k ) {
				$nizamiye_out .= '<td class="sms-center">' . ( $nizamiye_c['total'] > 0 ? (int) $nizamiye_c[ $nizamiye_k ] : '—' ) . '</td>';
			}
			$nizamiye_rate = $nizamiye_c['total'] > 0 ? (int) $nizamiye_c['rate'] : null;
			$nizamiye_out .= '<td class="sms-center"><span class="sms-score sms-score-big ' . esc_attr( nizamiye_rate_class( $nizamiye_rate ) ) . '">'
				. ( null !== $nizamiye_rate ? $nizamiye_rate . '%' : '—' ) . '</span>'
				. ( $nizamiye_c['total'] > 0 ? '<span class="sms-cell-sub">' . (int) $nizamiye_c['total'] . ' kayıt</span>' : '' ) . '</td>';
			return $nizamiye_out;
		};
		?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head">
				<h2><?php echo esc_html( ( $nizamiye_category ? $nizamiye_category->name : '' ) . ( $nizamiye_focus_session ? ' — ' . $nizamiye_focus_session->name . ' vakti' : ' — ' . $nizamiye_metric_labels[ $nizamiye_metric ] ) ); ?></h2>
				<div class="sms-head-tools"><span class="sms-muted"><?php echo esc_html( nizamiye_format_date( $nizamiye_from ) . ' – ' . nizamiye_format_date( $nizamiye_to ) ); ?></span><?php echo wp_kses_post( $nizamiye_matrix['rows'] ? $nizamiye_export_btn : '' ); ?></div>
			</div>
			<?php if ( $nizamiye_matrix['rows'] && $nizamiye_focus_session ) : ?>
				<?php // ---- ODAK: tek vakit için tam durum kırılımı ---- ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $nizamiye_group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<th class="sms-center">Geldi</th>
							<th class="sms-center">Gelmedi</th>
							<th class="sms-center">Geç</th>
							<th class="sms-center">İzinli</th>
							<th class="sms-center">Katılım</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $nizamiye_group ) : ?>
						<?php foreach ( $nizamiye_grade_rows as $nizamiye_g => $nizamiye_gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $nizamiye_gr['count']; ?> öğrenci)</span></td>
								<?php echo wp_kses_post( $nizamiye_focus_cells( $nizamiye_gr['cells'][ $nizamiye_sess_id ] ) ); ?>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) : $nizamiye_st = $nizamiye_row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_st ) ) ); ?>
									<div><a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_st->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $nizamiye_st->grade_level ) ? esc_html( nizamiye_grade_label( $nizamiye_st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php echo wp_kses_post( $nizamiye_focus_cells( $nizamiye_row['cells'][ $nizamiye_sess_id ] ) ); ?>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php echo wp_kses_post( $nizamiye_focus_cells( $nizamiye_matrix['totals'][ $nizamiye_sess_id ] ?? array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'rate' => null ) ) ); ?>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
				</div>
			<?php elseif ( $nizamiye_matrix['rows'] ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $nizamiye_group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<?php foreach ( $nizamiye_sessions as $nizamiye_s ) : ?><th class="sms-center"><?php echo esc_html( $nizamiye_s->name ); ?></th><?php endforeach; ?>
							<th class="sms-center">Genel</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $nizamiye_group ) : ?>
						<?php foreach ( $nizamiye_grade_rows as $nizamiye_g => $nizamiye_gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $nizamiye_gr['count']; ?> öğrenci)</span></td>
								<?php foreach ( $nizamiye_sessions as $nizamiye_s ) : $nizamiye_v = $nizamiye_cell_value( $nizamiye_gr['cells'][ (int) $nizamiye_s->id ] ); ?>
									<td class="sms-center"><span class="sms-score <?php echo esc_attr( $nizamiye_cell_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span>
									<?php if ( null !== $nizamiye_v ) : ?><span class="sms-cell-sub"><?php echo (int) $nizamiye_gr['cells'][ (int) $nizamiye_s->id ][ 'rate' === $nizamiye_metric ? 'present' : $nizamiye_metric ]; ?>/<?php echo (int) $nizamiye_gr['cells'][ (int) $nizamiye_s->id ]['total']; ?></span><?php endif; ?></td>
								<?php endforeach; ?>
								<?php $nizamiye_v = $nizamiye_cell_value( $nizamiye_gr['overall'] ); ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( $nizamiye_cell_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) : $nizamiye_st = $nizamiye_row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_st ) ) ); ?>
									<div><a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_st->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $nizamiye_st->grade_level ) ? esc_html( nizamiye_grade_label( $nizamiye_st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php foreach ( $nizamiye_sessions as $nizamiye_s ) : $nizamiye_cell = $nizamiye_row['cells'][ (int) $nizamiye_s->id ]; $nizamiye_v = $nizamiye_cell_value( $nizamiye_cell ); ?>
									<td class="sms-center">
										<span class="sms-score <?php echo esc_attr( $nizamiye_cell_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span>
										<?php if ( null !== $nizamiye_v ) : ?><span class="sms-cell-sub"><?php echo (int) $nizamiye_cell[ 'rate' === $nizamiye_metric ? 'present' : $nizamiye_metric ]; ?>/<?php echo (int) $nizamiye_cell['total']; ?></span><?php endif; ?>
									</td>
								<?php endforeach; ?>
								<?php $nizamiye_v = $nizamiye_cell_value( $nizamiye_row['overall'] ); ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( $nizamiye_cell_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php foreach ( $nizamiye_sessions as $nizamiye_s ) : $nizamiye_v = $nizamiye_cell_value( $nizamiye_matrix['totals'][ (int) $nizamiye_s->id ] ?? null ); ?>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( $nizamiye_cell_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
							<?php endforeach; ?>
							<?php $nizamiye_v = $nizamiye_cell_value( $nizamiye_matrix['totals']['overall'] ?? null ); ?>
							<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( $nizamiye_cell_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
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
	elseif ( 'aliskanlik' === $nizamiye_rtype ) :
		$nizamiye_matrix = Nizamiye_Reports::habit_matrix( $nizamiye_term_id, 'sinif' === $nizamiye_group ? 0 : $nizamiye_grade, $nizamiye_student_ids );
		$nizamiye_habits = $nizamiye_matrix['habits'];

		$nizamiye_grade_rows = array();
		if ( 'sinif' === $nizamiye_group && $nizamiye_matrix['rows'] ) {
			foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) {
				$nizamiye_g = (int) ( $nizamiye_row['student']->grade_level ?? 0 );
				if ( ! isset( $nizamiye_grade_rows[ $nizamiye_g ] ) ) {
					$nizamiye_grade_rows[ $nizamiye_g ] = array( 'count' => 0, 'sum' => array(), 'cnt' => array() );
				}
				$nizamiye_grade_rows[ $nizamiye_g ]['count']++;
				foreach ( $nizamiye_habits as $nizamiye_h ) {
					$nizamiye_cell = $nizamiye_row['cells'][ (int) $nizamiye_h->id ];
					if ( $nizamiye_cell ) {
						$nizamiye_grade_rows[ $nizamiye_g ]['sum'][ (int) $nizamiye_h->id ] = ( $nizamiye_grade_rows[ $nizamiye_g ]['sum'][ (int) $nizamiye_h->id ] ?? 0 ) + $nizamiye_cell['rate'];
						$nizamiye_grade_rows[ $nizamiye_g ]['cnt'][ (int) $nizamiye_h->id ] = ( $nizamiye_grade_rows[ $nizamiye_g ]['cnt'][ (int) $nizamiye_h->id ] ?? 0 ) + 1;
					}
				}
			}
			ksort( $nizamiye_grade_rows );
		}
		?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>Alışkanlık Tamamlama Oranları</h2><div class="sms-head-tools"><span class="sms-muted">Dönem geneli</span><?php echo wp_kses_post( ( $nizamiye_matrix['rows'] && $nizamiye_habits ) ? $nizamiye_export_btn : '' ); ?></div></div>
			<?php if ( $nizamiye_matrix['rows'] && $nizamiye_habits ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $nizamiye_group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<?php foreach ( $nizamiye_habits as $nizamiye_h ) : ?><th class="sms-center"><?php echo esc_html( $nizamiye_h->name ); ?></th><?php endforeach; ?>
							<th class="sms-center">Genel</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $nizamiye_group ) : ?>
						<?php foreach ( $nizamiye_grade_rows as $nizamiye_g => $nizamiye_gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $nizamiye_gr['count']; ?> öğrenci)</span></td>
								<?php
								$nizamiye_g_sum = 0;
								$nizamiye_g_cnt = 0;
								foreach ( $nizamiye_habits as $nizamiye_h ) :
									$nizamiye_hid = (int) $nizamiye_h->id;
									$nizamiye_v   = isset( $nizamiye_gr['cnt'][ $nizamiye_hid ] ) ? (int) round( $nizamiye_gr['sum'][ $nizamiye_hid ] / $nizamiye_gr['cnt'][ $nizamiye_hid ] ) : null;
									if ( null !== $nizamiye_v ) {
										$nizamiye_g_sum += $nizamiye_v;
										$nizamiye_g_cnt++;
									}
									?>
									<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
								<?php endforeach; ?>
								<?php $nizamiye_v = $nizamiye_g_cnt ? (int) round( $nizamiye_g_sum / $nizamiye_g_cnt ) : null; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) : $nizamiye_st = $nizamiye_row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_st ) ) ); ?>
									<div><a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_st->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $nizamiye_st->grade_level ) ? esc_html( nizamiye_grade_label( $nizamiye_st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php foreach ( $nizamiye_habits as $nizamiye_h ) : $nizamiye_cell = $nizamiye_row['cells'][ (int) $nizamiye_h->id ]; ?>
									<td class="sms-center">
										<?php if ( $nizamiye_cell ) : ?>
											<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_cell['rate'] ) ); ?>"><?php echo (int) $nizamiye_cell['rate']; ?>%</span>
											<span class="sms-cell-sub"><?php echo (int) $nizamiye_cell['logs']; ?> kayıt</span>
										<?php else : ?>—<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['overall'] ) ); ?>"><?php echo null !== $nizamiye_row['overall'] ? esc_html( $nizamiye_row['overall'] . '%' ) : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php foreach ( $nizamiye_habits as $nizamiye_h ) : $nizamiye_v = $nizamiye_matrix['totals'][ (int) $nizamiye_h->id ]; ?>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
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
	elseif ( 'not' === $nizamiye_rtype ) :
		$nizamiye_matrix  = Nizamiye_Reports::grade_matrix( $nizamiye_term_id, 'sinif' === $nizamiye_group ? 0 : $nizamiye_grade, $nizamiye_student_ids );
		$nizamiye_classes = $nizamiye_matrix['classes'];

		$nizamiye_grade_rows = array();
		if ( 'sinif' === $nizamiye_group && $nizamiye_matrix['rows'] ) {
			foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) {
				$nizamiye_g = (int) ( $nizamiye_row['student']->grade_level ?? 0 );
				if ( ! isset( $nizamiye_grade_rows[ $nizamiye_g ] ) ) {
					$nizamiye_grade_rows[ $nizamiye_g ] = array( 'count' => 0, 'sum' => array(), 'cnt' => array() );
				}
				$nizamiye_grade_rows[ $nizamiye_g ]['count']++;
				foreach ( $nizamiye_classes as $nizamiye_c ) {
					$nizamiye_cell = $nizamiye_row['cells'][ (int) $nizamiye_c->id ];
					if ( $nizamiye_cell ) {
						$nizamiye_grade_rows[ $nizamiye_g ]['sum'][ (int) $nizamiye_c->id ] = ( $nizamiye_grade_rows[ $nizamiye_g ]['sum'][ (int) $nizamiye_c->id ] ?? 0 ) + $nizamiye_cell['rate'];
						$nizamiye_grade_rows[ $nizamiye_g ]['cnt'][ (int) $nizamiye_c->id ] = ( $nizamiye_grade_rows[ $nizamiye_g ]['cnt'][ (int) $nizamiye_c->id ] ?? 0 ) + 1;
					}
				}
			}
			ksort( $nizamiye_grade_rows );
		}
		?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>Not Ortalamaları (%)</h2><div class="sms-head-tools"><span class="sms-muted">Derslik bazında, dönem geneli</span><?php echo wp_kses_post( ( $nizamiye_matrix['rows'] && $nizamiye_classes ) ? $nizamiye_export_btn : '' ); ?></div></div>
			<?php if ( $nizamiye_matrix['rows'] && $nizamiye_classes ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table sms-matrix">
					<thead>
						<tr>
							<th><?php echo 'sinif' === $nizamiye_group ? 'Sınıf' : 'Öğrenci'; ?></th>
							<?php foreach ( $nizamiye_classes as $nizamiye_c ) : ?><th class="sms-center"><?php echo esc_html( $nizamiye_c->name ); ?></th><?php endforeach; ?>
							<th class="sms-center">Genel</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( 'sinif' === $nizamiye_group ) : ?>
						<?php foreach ( $nizamiye_grade_rows as $nizamiye_g => $nizamiye_gr ) : ?>
							<tr>
								<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></strong> <span class="sms-muted">(<?php echo (int) $nizamiye_gr['count']; ?> öğrenci)</span></td>
								<?php
								$nizamiye_g_sum = 0;
								$nizamiye_g_cnt = 0;
								foreach ( $nizamiye_classes as $nizamiye_c ) :
									$nizamiye_cid = (int) $nizamiye_c->id;
									$nizamiye_v   = isset( $nizamiye_gr['cnt'][ $nizamiye_cid ] ) ? (int) round( $nizamiye_gr['sum'][ $nizamiye_cid ] / $nizamiye_gr['cnt'][ $nizamiye_cid ] ) : null;
									if ( null !== $nizamiye_v ) {
										$nizamiye_g_sum += $nizamiye_v;
										$nizamiye_g_cnt++;
									}
									?>
									<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
								<?php endforeach; ?>
								<?php $nizamiye_v = $nizamiye_g_cnt ? (int) round( $nizamiye_g_sum / $nizamiye_g_cnt ) : null; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $nizamiye_matrix['rows'] as $nizamiye_row ) : if ( ! $nizamiye_row['has_any'] ) { continue; } $nizamiye_st = $nizamiye_row['student']; ?>
							<tr>
								<td class="sms-name-cell">
									<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_st ) ) ); ?>
									<div><a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_st->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_st ) ); ?></strong></a>
									<span class="sms-muted"><?php echo isset( $nizamiye_st->grade_level ) ? esc_html( nizamiye_grade_label( $nizamiye_st->grade_level ) ) : ''; ?></span></div>
								</td>
								<?php foreach ( $nizamiye_classes as $nizamiye_c ) : $nizamiye_cell = $nizamiye_row['cells'][ (int) $nizamiye_c->id ]; ?>
									<td class="sms-center">
										<?php if ( $nizamiye_cell ) : ?>
											<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_cell['rate'] ) ); ?>"><?php echo (int) $nizamiye_cell['rate']; ?>%</span>
											<span class="sms-cell-sub"><?php echo (int) $nizamiye_cell['exams']; ?> sınav</span>
										<?php else : ?>—<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['overall'] ) ); ?>"><?php echo null !== $nizamiye_row['overall'] ? esc_html( $nizamiye_row['overall'] . '%' ) : '—'; ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr class="sms-total-row">
							<td><strong>Toplu (tüm liste)</strong></td>
							<?php foreach ( $nizamiye_classes as $nizamiye_c ) : $nizamiye_v = $nizamiye_matrix['totals'][ (int) $nizamiye_c->id ]; ?>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_v ) ); ?>"><?php echo null !== $nizamiye_v ? esc_html( $nizamiye_v . '%' ) : '—'; ?></span></td>
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
		if ( 'sinif' === $nizamiye_group ) :
			$nizamiye_summary = Nizamiye_Reports::grade_level_summary( $nizamiye_term_id, $nizamiye_student_ids );
			?>
			<div class="sms-card sms-mt">
				<div class="sms-card-head"><h2>Sınıf Bazında Genel Özet</h2><?php echo wp_kses_post( $nizamiye_summary ? $nizamiye_export_btn : '' ); ?></div>
				<?php if ( $nizamiye_summary ) : ?>
					<div class="sms-table-scroll">
					<table class="sms-table">
						<thead><tr><th>Sınıf</th><th>Öğrenci</th><th class="sms-center">Devam</th><th class="sms-center">Alışkanlık</th><th class="sms-center">Not Ort.</th></tr></thead>
						<tbody>
						<?php foreach ( $nizamiye_summary as $nizamiye_row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_row['grade'] ) ); ?></strong></td>
								<td><?php echo (int) $nizamiye_row['count']; ?></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['att'] ) ); ?>"><?php echo null !== $nizamiye_row['att'] ? esc_html( $nizamiye_row['att'] . '%' ) : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['habit'] ) ); ?>"><?php echo null !== $nizamiye_row['habit'] ? esc_html( $nizamiye_row['habit'] . '%' ) : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['grade_avg'] ) ); ?>"><?php echo null !== $nizamiye_row['grade_avg'] ? esc_html( $nizamiye_row['grade_avg'] . '%' ) : '—'; ?></span></td>
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
			$nizamiye_scores = Nizamiye_Reports::student_scores( $nizamiye_term_id, $nizamiye_student_ids );
			if ( $nizamiye_grade ) {
				$nizamiye_scores = array_values( array_filter( $nizamiye_scores, function ( $nizamiye_r ) use ( $nizamiye_grade ) {
					return (int) ( $nizamiye_r['student']->grade_level ?? 0 ) === $nizamiye_grade;
				} ) );
			}
			?>
			<div class="sms-card sms-mt">
				<div class="sms-card-head"><h2>Genel Başarı Sıralaması</h2><div class="sms-head-tools"><span class="sms-muted">Bileşik skor: %40 devam + %40 alışkanlık + %20 not</span><?php echo wp_kses_post( $nizamiye_scores ? $nizamiye_export_btn : '' ); ?></div></div>
				<?php if ( $nizamiye_scores ) : ?>
					<div class="sms-table-scroll">
					<table class="sms-table">
						<thead><tr><th>#</th><th>Öğrenci</th><th>Sınıf</th><th class="sms-center">Devam</th><th class="sms-center">Alışkanlık</th><th class="sms-center">Not Ort.</th><th class="sms-center">Skor</th></tr></thead>
						<tbody>
						<?php foreach ( $nizamiye_scores as $nizamiye_i => $nizamiye_row ) : $nizamiye_s = $nizamiye_row['student']; ?>
							<tr>
								<td class="sms-muted">#<?php echo (int) $nizamiye_i + 1; ?></td>
								<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_s ) ) ); ?><a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_s->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_s ) ); ?></strong></a></td>
								<td><?php echo isset( $nizamiye_s->grade_level ) ? esc_html( nizamiye_grade_label( $nizamiye_s->grade_level ) ) : '—'; ?></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['attendance'] ) ); ?>"><?php echo null !== $nizamiye_row['attendance'] ? esc_html( $nizamiye_row['attendance'] . '%' ) : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['habit'] ) ); ?>"><?php echo null !== $nizamiye_row['habit'] ? esc_html( $nizamiye_row['habit'] . '%' ) : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['grade'] ) ); ?>"><?php echo null !== $nizamiye_row['grade'] ? esc_html( $nizamiye_row['grade'] . '%' ) : '—'; ?></span></td>
								<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['score'] ) ); ?>"><?php echo (int) $nizamiye_row['score']; ?></span></td>
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
