<?php
/**
 * Renderització del calendari, el formulari tipus Doodle i el resum en text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QS_Render {

	/* ---------- Utilitats de dates i hores ---------- */

	private static function hm( $minutes ) {
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	public static function slots( $s ) {
		$out   = array();
		$step  = max( 15, (int) $s['slot_minutes'] );
		$from  = ( (int) $s['start_hour'] ) * 60;
		$to    = ( (int) $s['end_hour'] ) * 60;
		$guard = 0;
		for ( $t = $from; $t < $to && $guard < 200; $t += $step, $guard++ ) {
			$out[] = array(
				'start' => self::hm( $t ),
				'end'   => self::hm( $t + $step ),
			);
		}
		if ( empty( $out ) ) {
			$out[] = array( 'start' => self::hm( $from ), 'end' => self::hm( $from + $step ) );
		}
		return $out;
	}

	public static function weeks( $month ) {
		$weeks = array();
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			return $weeks;
		}
		$first = strtotime( $month . '-01 00:00:00' );
		$last  = strtotime( $month . '-' . gmdate( 't', $first ) . ' 00:00:00' );

		$cur = $first;
		if ( 1 !== (int) gmdate( 'N', $cur ) ) {
			$cur = strtotime( 'last monday', $cur );
		}

		$guard = 0;
		while ( $cur <= $last && $guard < 10 ) {
			$week_end = strtotime( '+6 days', $cur );
			if ( $week_end >= $first && $cur <= $last ) {
				$days = array();
				for ( $i = 0; $i < 7; $i++ ) {
					$days[] = gmdate( 'Y-m-d', strtotime( '+' . $i . ' days', $cur ) );
				}
				$weeks[] = $days;
			}
			$cur = strtotime( '+7 days', $cur );
			$guard++;
		}
		return $weeks;
	}

	private static $months_ca = array(
		1  => 'gener', 2 => 'febrer', 3 => 'març', 4 => 'abril',
		5  => 'maig', 6 => 'juny', 7 => 'juliol', 8 => 'agost',
		9  => 'setembre', 10 => 'octubre', 11 => 'novembre', 12 => 'desembre',
	);
	private static $days_ca    = array( 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte', 'diumenge' );
	private static $days_ca_ab = array( 'dl', 'dt', 'dc', 'dj', 'dv', 'ds', 'dg' );

	private static function dparts( $date ) {
		$ts = strtotime( $date . ' 00:00:00' );
		return array(
			'ts' => $ts,
			'w'  => (int) gmdate( 'N', $ts ),
			'd'  => (int) gmdate( 'j', $ts ),
			'm'  => (int) gmdate( 'n', $ts ),
			'y'  => (int) gmdate( 'Y', $ts ),
		);
	}

	public static function month_label( $month ) {
		$p    = explode( '-', $month );
		$name = isset( self::$months_ca[ (int) $p[1] ] ) ? self::$months_ca[ (int) $p[1] ] : $month;
		return $name . ' ' . $p[0];
	}

	public static function date_label( $date, $short = false ) {
		$p    = self::dparts( $date );
		$day  = self::$days_ca[ $p['w'] - 1 ];
		$mon  = self::$months_ca[ $p['m'] ];
		if ( $short ) {
			$mon  = substr( $mon, 0, 3 );
			$day  = self::$days_ca_ab[ $p['w'] - 1 ];
			return $day . ' ' . $p['d'] . ' ' . $mon;
		}
		$year = $short ? '' : ' de ' . $p['y'];
		return $day . ' ' . $p['d'] . ' de ' . $mon . $year;
	}

	public static function day_short( $date ) {
		$p = self::dparts( $date );
		return self::$days_ca_ab[ $p['w'] - 1 ];
	}

	/* ---------- Càlcul de coincidències ---------- */

	public static function stats( $s, $musicians ) {
		$slots  = self::slots( $s );
		$labels = array();
		foreach ( $slots as $sl ) {
			$labels[] = $sl['start'];
		}

		$dates = array();
		foreach ( $musicians as $mu ) {
			if ( empty( $mu['avail'] ) || ! is_array( $mu['avail'] ) ) {
				continue;
			}
			foreach ( $mu['avail'] as $date => $slots_av ) {
				if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! is_array( $slots_av ) ) {
					continue;
				}
				if ( ! isset( $dates[ $date ] ) ) {
					$dates[ $date ] = array( 'yes' => array(), 'maybe' => array() );
				}
				foreach ( $slots_av as $slot => $st ) {
					if ( ! is_string( $slot ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $slot ) ) {
						continue;
					}
					$st = (int) $st;
					if ( 2 === $st ) {
						$dates[ $date ]['yes'][ $slot ] = ( isset( $dates[ $date ]['yes'][ $slot ] ) ? $dates[ $date ]['yes'][ $slot ] : 0 ) + 1;
					} elseif ( 1 === $st ) {
						$dates[ $date ]['maybe'][ $slot ] = ( isset( $dates[ $date ]['maybe'][ $slot ] ) ? $dates[ $date ]['maybe'][ $slot ] : 0 ) + 1;
					}
				}
			}
		}

		$N = count( $musicians );

		foreach ( $dates as $date => $d ) {
			$max = 0;
			foreach ( $labels as $lb ) {
				$yes = isset( $d['yes'][ $lb ] ) ? $d['yes'][ $lb ] : 0;
				if ( $yes > $max ) {
					$max = $yes;
				}
			}
			$dates[ $date ]['max']  = $max;
			$dates[ $date ]['best'] = self::best_run( $slots, $d['yes'], $max );
		}

		return array(
			'N'     => $N,
			'slots' => $slots,
			'dates' => $dates,
		);
	}

	private static function best_run( $slots, $yes, $need ) {
		if ( $need <= 0 ) {
			return null;
		}
		$best   = null;
		$run    = null;
		foreach ( $slots as $sl ) {
			$ok = ( isset( $yes[ $sl['start'] ] ) && $yes[ $sl['start'] ] >= $need );
			if ( $ok ) {
				if ( null === $run ) {
					$run = array( 'start' => $sl['start'], 'end' => $sl['end'] );
				} else {
					$run['end'] = $sl['end'];
				}
			} else {
				if ( null !== $run && ( null === $best || self::len( $run ) > self::len( $best ) ) ) {
					$best = $run;
				}
				$run = null;
			}
		}
		if ( null !== $run ) {
			if ( null === $best || self::len( $run ) >= self::len( $best ) ) {
				$best = $run;
			}
		}
		return $best;
	}

	private static function len( $run ) {
		return ( strtotime( $run['end'] . ':00' ) - strtotime( $run['start'] . ':00' ) );
	}

	private static function hm_hi( $time ) {
		$p = explode( ':', $time );
		return sprintf( '%d', (int) $p[0] );
	}

	/* ---------- Fitxes de color del calendari ---------- */

	private static function heat_class( $max, $N ) {
		if ( $max <= 0 || $N <= 0 ) {
			return 'qs-h0';
		}
		$bucket = (int) ceil( ( $max / $N ) * 4 );
		$bucket = min( 4, max( 1, $bucket ) );
		return 'qs-h' . $bucket;
	}

	/* ---------- Peces de la interfície ---------- */

	public static function render_app( $mid = '', $month = '', $week = 0, $day = null ) {
		$s         = QS_Store::get_settings();
		$musicians = QS_Store::get_musicians();
		$months    = $s['months'];

		if ( '' === $month || ! in_array( $month, $months, true ) ) {
			$month = ! empty( $months ) ? $months[0] : gmdate( 'Y-m' );
		}

		$w = 0;
		foreach ( $months as $i => $m ) {
			if ( $m === $month ) {
				$w = $i;
				break;
			}
		}

		ob_start();
		echo '<div id="qs-app" class="qs-app" data-month="' . esc_attr( $month ) . '" data-week="' . (int) $week . '" data-mid="' . esc_attr( $mid ) . '" data-day="' . esc_attr( (string) $day ) . '">';

		echo '<header class="qs-header">';
		echo '<h2 class="qs-title">' . esc_html( $s['title'] ) . '</h2>';
		echo '<p class="qs-subtitle">' . esc_html( $s['subtitle'] ) . ' — ' . esc_html( $s['venue'] ) . '</p>';
		echo '<div class="qs-concert-banner">';
		echo '<span class="qs-concert-ico" aria-hidden="true">🎻</span>';
		echo '<div>';
		echo '<strong>CONCERT — dia clau i inamovible</strong><br>';
		echo esc_html( self::date_label( $s['concert_date'] ) . ' · ' . $s['concert_time'] . ' h' );
		echo '</div>';
		echo '</div>';
		echo '</header>';

		echo self::month_tabs( $s, $month );
		echo self::calendar( $s, $musicians, $month, $day );
		if ( '' !== (string) $day ) {
			echo self::detail( $s, $musicians, $day );
		}
		echo self::summary( $s, $musicians );
		echo self::form( $s, $musicians, $mid, $month, $week );

		echo '</div>';
		return ob_get_clean();
	}

	public static function month_tabs( $s, $current ) {
		$html = '<nav class="qs-tabs" role="tablist">';
		foreach ( $s['months'] as $m ) {
			$sel  = ( $m === $current );
			$html .= '<button type="button" class="qs-tab' . ( $sel ? ' is-active' : '' ) . '" data-month="' . esc_attr( $m ) . '">' . esc_html( self::month_label( $m ) ) . '</button>';
		}
		$html .= '</nav>';
		return $html;
	}

	public static function calendar( $s, $musicians, $month, $day ) {
		$stats = self::stats( $s, $musicians );
		$N     = $stats['N'];
		$dates = $stats['dates'];

		$html = '<section class="qs-card">';
		$html .= '<h3>Calendari de coincidències</h3>';
		$html .= '<p class="qs-hint">Feu clic a un dia per veure el detall hora per hora. Com més verd, més músics coincideixen.</p>';
		$html .= '<table class="qs-cal" aria-label="Calendari de ' . esc_attr( self::month_label( $month ) ) . '">';
		$html .= '<thead><tr>';
		foreach ( self::$days_ca_ab as $d ) {
			$html .= '<th>' . esc_html( $d ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( self::weeks( $month ) as $days ) {
			$html .= '<tr>';
			foreach ( $days as $date ) {
				$in_month   = ( 0 === strpos( $date, $month . '-' ) );
				$is_concert = ( $date === $s['concert_date'] );
				$classes    = array( 'qs-day' );

				if ( ! $in_month ) {
					$classes[] = 'qs-out';
				}
				if ( $is_concert ) {
					$classes[] = 'qs-concert';
				}
				if ( $in_month && isset( $dates[ $date ] ) ) {
					$classes[] = self::heat_class( $dates[ $date ]['max'], $N );
				} else {
					$classes[] = 'qs-h0';
				}
				if ( $in_month && (string) $day === $date ) {
					$classes[] = 'qs-sel';
				}

				$badge = '';
				if ( $is_concert ) {
					$badge = '<span class="qs-badge qs-badge-concert">🎻 ' . esc_html( self::hm_hi( $s['concert_time'] ) ) . 'h</span>';
				} elseif ( $in_month && isset( $dates[ $date ] ) && $dates[ $date ]['max'] > 0 ) {
					$badge = '<span class="qs-badge">' . (int) $dates[ $date ]['max'] . '/' . (int) $N . '</span>';
				}

				$attrs = '';
				if ( $in_month && ! $is_concert ) {
					$attrs = ' role="button" tabindex="0" data-date="' . esc_attr( $date ) . '"';
				}

				$html .= '<td class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $attrs . '>';
				$html .= '<span class="qs-dnum">' . (int) self::dparts( $date )['d'] . '</span>';
				if ( $is_concert ) {
					$html .= '<span class="qs-concert-tag">CONCERT ' . esc_html( $s['concert_time'] ) . 'h</span>';
				}
				$html .= $badge;
				$html .= '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		$html .= '<div class="qs-legend"><span>Llegenda:</span> ';
		foreach ( array( 'qs-h0' => 'sense dades', 'qs-h1' => '1 músic', 'qs-h4' => 'tots (' . (int) $N . ')' ) as $cls => $label ) {
			$html .= '<span class="qs-legend-item"><i class="' . esc_attr( $cls ) . '"></i>' . esc_html( $label ) . '</span>';
		}
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	public static function detail( $s, $musicians, $day ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
			return '';
		}
		$stats = self::stats( $s, $musicians );
		$N     = $stats['N'];

		$d    = isset( $stats['dates'][ $day ] ) ? $stats['dates'][ $day ] : array( 'yes' => array(), 'maybe' => array() );
		$html = '<section class="qs-card qs-detail">';
		$html .= '<h3>Detall de ' . esc_html( self::date_label( $day ) ) . '</h3>';

		if ( $day === $s['concert_date'] ) {
			$html .= '<p class="qs-detail-concert">🎻 Aquest dia hi ha el CONCERT a les ' . esc_html( $s['concert_time'] ) . ' h. No es pot triar per assajar.</p>';
			$html .= '</section>';
			return $html;
		}

		$html .= '<div class="qs-bars">';
		foreach ( $stats['slots'] as $sl ) {
			$yes   = isset( $d['yes'][ $sl['start'] ] ) ? $d['yes'][ $sl['start'] ] : 0;
			$maybe = isset( $d['maybe'][ $sl['start'] ] ) ? $d['maybe'][ $sl['start'] ] : 0;
			$pct   = $N > 0 ? round( ( $yes / $N ) * 100 ) : 0;
			$html .= '<div class="qs-bar-row">';
			$html .= '<span class="qs-bar-time">' . esc_html( $sl['start'] ) . '–' . esc_html( $sl['end'] ) . '</span>';
			$html .= '<span class="qs-bar-track"><span class="qs-bar-fill" style="width:' . esc_attr( $pct ) . '%"></span></span>';
			$html .= '<span class="qs-bar-num">' . (int) $yes . ' sí · ' . (int) $maybe . ' potser</span>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	public static function summary( $s, $musicians ) {
		$stats = self::stats( $s, $musicians );
		$N     = $stats['N'];
		$dates = $stats['dates'];

		$html = '<section class="qs-card qs-summary">';
		$html .= '<h3>Resum de coincidències</h3>';

		if ( 0 === $N ) {
			$html .= '<p class="qs-empty">Encara no hi ha cap músic. Omple el formulari de sota per afegir el primer.</p>';
			$html .= '</section>';
			return $html;
		}

		$all_runs = array();
		foreach ( $dates as $date => $d ) {
			if ( $date === $s['concert_date'] ) {
				continue;
			}
			if ( isset( $d['best'] ) && null !== $d['best'] && $d['max'] === $N ) {
				$all_runs[] = '<strong>' . esc_html( self::date_label( $date ) ) . '</strong> · ' . esc_html( $d['best']['start'] ) . '–' . esc_html( $d['best']['end'] );
			}
		}

		$html .= '<h4>🎯 Dies on coincideix tot el quartet (' . (int) $N . ' de ' . (int) $N . ')</h4>';
		if ( empty( $all_runs ) ) {
			$html .= '<p class="qs-empty">Encara no hi ha cap dia en què coincideixin tots. Fixeu-vos en els «millors dies» i afegiu-hi disponibilitat.</p>';
		} else {
			$html .= '<ul class="qs-list">';
			$shown = array_slice( $all_runs, 0, 10 );
			foreach ( $shown as $line ) {
				$html .= '<li>' . $line . '</li>';
			}
			if ( count( $all_runs ) > 10 ) {
				$html .= '<li>… i ' . ( count( $all_runs ) - 10 ) . ' dies més.</li>';
			}
			$html .= '</ul>';
		}

		$ranked = array();
		foreach ( $dates as $date => $d ) {
			if ( $date === $s['concert_date'] || $d['max'] <= 0 ) {
				continue;
			}
			$ranked[] = $date;
		}
		usort( $ranked, function ( $a, $b ) use ( $dates ) {
			if ( $dates[ $b ]['max'] === $dates[ $a ]['max'] ) {
				return strcmp( $a, $b );
			}
			return ( $dates[ $b ]['max'] < $dates[ $a ]['max'] ) ? -1 : 1;
		} );

		$html .= '<h4>📊 Millors dies (de més a menys coincidència)</h4>';
		if ( empty( $ranked ) ) {
			$html .= '<p class="qs-empty">Encara no hi ha cap disponibilitat enregistrada.</p>';
		} else {
			$html .= '<ul class="qs-list">';
			foreach ( array_slice( $ranked, 0, 5 ) as $date ) {
				$d     = $dates[ $date ];
				$best  = ( isset( $d['best'] ) && null !== $d['best'] ) ? ' · millor franja <strong>' . esc_html( $d['best']['start'] ) . '–' . esc_html( $d['best']['end'] ) . '</strong>' : '';
				$html .= '<li><strong>' . esc_html( self::date_label( $date ) ) . '</strong> · ' . (int) $d['max'] . ' de ' . (int) $N . ' músics' . $best . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '</section>';
		return $html;
	}

	public static function form( $s, $musicians, $mid, $month, $week ) {
		$weeks  = self::weeks( $month );
		$weeks  = array_values( array_filter( $weeks, function ( $days ) use ( $month ) {
			foreach ( $days as $d ) {
				if ( 0 === strpos( $d, $month . '-' ) ) {
					return true;
				}
			}
			return false;
		} ) );
		$week    = max( 0, min( count( $weeks ) - 1, (int) $week ) );
		$days    = isset( $weeks[ $week ] ) ? $weeks[ $week ] : array();
		$slots   = self::slots( $s );
		$sel     = null;
		foreach ( $musicians as $mu ) {
			if ( $mu['id'] === $mid ) {
				$sel = $mu;
				break;
			}
		}
		$av = ( $sel && isset( $sel['avail'] ) && is_array( $sel['avail'] ) ) ? $sel['avail'] : array();

		$html = '<section class="qs-card qs-form">';
		$html .= '<h3>Quadra la teva agenda</h3>';
		$html .= '<p class="qs-hint">Tria el teu nom (o afegeix-te) i per a cada franja horària fes clic per canviar: <span class="qs-chip qs-f2">Sí</span> <span class="qs-chip qs-f1">Potser</span> <span class="qs-chip qs-f0">No</span> <span class="qs-chip qs-fx">—</span></p>';

		$html .= '<div class="qs-picker">';
		$html .= '<label for="qs-mid">Qui ets?</label> ';
		$html .= '<select id="qs-mid">';
		$html .= '<option value=""' . ( null === $sel ? ' selected' : '' ) . '>— Afegeix-m’ —</option>';
		foreach ( $musicians as $mu ) {
			$html .= '<option value="' . esc_attr( $mu['id'] ) . '"' . ( $sel && $sel['id'] === $mu['id'] ? ' selected' : '' ) . '>' . esc_html( $mu['name'] . ( ! empty( $mu['instrument'] ) ? ' (' . $mu['instrument'] . ')' : '' ) ) . '</option>';
		}
		$html .= '</select>';
		$html .= '</div>';

		$html .= '<div class="qs-new' . ( null === $sel ? '' : ' qs-hidden' ) . '">';
		$html .= '<label for="qs-name">El teu nom i cognom</label><br>';
		$html .= '<input type="text" id="qs-name" maxlength="60">';
		$html .= '<label for="qs-instrument">Instrument</label><br>';
		$html .= '<input type="text" id="qs-instrument" maxlength="40">';
		$html .= '</div>';

		if ( ! empty( $days ) ) {
			$html .= '<div class="qs-weeknav">';
			$prev = '<button type="button" class="qs-nav" data-dir="-1"' . ( 0 === $week ? ' disabled' : '' ) . ' aria-label="Setmana anterior">‹</button>';
			$next = '<button type="button" class="qs-nav" data-dir="1"' . ( $week >= count( $weeks ) - 1 ? ' disabled' : '' ) . ' aria-label="Setmana següent">›</button>';
			$html .= $prev . '<span class="qs-weeklabel">Setmana ' . ( $week + 1 ) . ' de ' . count( $weeks ) . '</span>' . $next;
			$html .= '</div>';

			$html .= '<div class="qs-tablewrap"><table class="qs-grid"><thead><tr><th>' . esc_html( self::month_label( $month ) ) . '</th>';
			foreach ( $days as $date ) {
				$in  = ( 0 === strpos( $date, $month . '-' ) );
				$cls = $in ? 'qs-th' : 'qs-th qs-out';
				$html .= '<th class="' . esc_attr( $cls ) . '">' . esc_html( self::day_short( $date ) ) . ' ' . (int) self::dparts( $date )['d'] . '</th>';
			}
			$html .= '</tr></thead><tbody>';

			foreach ( $slots as $sl ) {
				$html .= '<tr><th class="qs-slottime">' . esc_html( $sl['start'] ) . '</th>';
				foreach ( $days as $date ) {
					$in         = ( 0 === strpos( $date, $month . '-' ) );
					$is_concert = ( $date === $s['concert_date'] );
					if ( ! $in ) {
						$html .= '<td class="qs-out"></td>';
						continue;
					}
					if ( $is_concert ) {
						$html .= '<td class="qs-cellconcert" title="Concert — ' . esc_attr( $s['concert_time'] ) . ' h">🎻</td>';
						continue;
					}
					$st = '';
					if ( isset( $av[ $date ][ $sl['start'] ] ) ) {
						$st = (string) (int) $av[ $date ][ $sl['start'] ];
					}
					$cls = 'qs-cell' . ( '' !== $st ? ' qs-f' . (int) $st : '' );
					$html .= '<td><button type="button" class="' . esc_attr( $cls ) . '" data-date="' . esc_attr( $date ) . '" data-slot="' . esc_attr( $sl['start'] ) . '" data-st="' . esc_attr( $st ) . '" aria-label="' . esc_attr( self::date_label( $date ) . ' ' . $sl['start'] ) . '"></button></td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';

			$html .= '<div class="qs-savebar">';
			$html .= '<button type="button" id="qs-save" class="qs-btn">Desar la meva disponibilitat</button>';
			$html .= '<span id="qs-status" class="qs-status" role="status"></span>';
			$html .= '</div>';
		}

		$html .= '</section>';
		return $html;
	}
}
