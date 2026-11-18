<?php
/**
 * Emmagatzematge de dades: configuració i músics amb la seva disponibilitat.
 *
 * Un músic: array( 'id' => string, 'name' => string, 'instrument' => string,
 *                  'avail' => array( 'Y-m-d' => array( 'H:i' => 0|1|2 ) ) )
 * Estats: 2 = Sí puc, 1 = Potser, 0 = No puc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QS_Store {

	const OPT_SETTINGS  = 'qs_settings';
	const OPT_MUSICIANS = 'qs_musicians';

	public static function defaults() {
		return array(
			'title'        => '«Programant Haydn: Les set últimes paraules de Crist a la creu»',
			'subtitle'     => 'Concert de Setmana Santa',
			'venue'        => 'Església Santa Magdalena (Vinaròs)',
			'months'       => array( '2026-11', '2026-12', '2027-01', '2027-02' ),
			'concert_date' => '2027-02-27',
			'concert_time' => '20:00',
			'start_hour'   => 10,
			'end_hour'     => 22,
			'slot_minutes' => 60,
		);
	}

	public static function get_settings() {
		$saved = get_option( self::OPT_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function get_musicians() {
		$musicians = get_option( self::OPT_MUSICIANS, array() );
		return is_array( $musicians ) ? $musicians : array();
	}

	private static function save_musicians( $musicians ) {
		update_option( self::OPT_MUSICIANS, array_values( $musicians ), false );
	}

	private static function merge_avail( $avail, $changes ) {
		foreach ( $changes as $date => $slots ) {
			if ( ! isset( $avail[ $date ] ) || ! is_array( $avail[ $date ] ) ) {
				$avail[ $date ] = array();
			}
			foreach ( $slots as $slot => $st ) {
				if ( null === $st ) {
					unset( $avail[ $date ][ $slot ] );
				} else {
					$avail[ $date ][ $slot ] = (int) $st;
				}
			}
			if ( empty( $avail[ $date ] ) ) {
				unset( $avail[ $date ] );
			}
		}
		return $avail;
	}

	/**
	 * Crea o actualitza un músic cercant-lo pel nom (clau amigable per al formulari
	 * públic, que no té desplegable de selecció). Fusió per nom sense distingir
	 * majúscules ni espais extrems.
	 */
	public static function save_by_name( $name, $instrument, $changes ) {
		$musicians = self::get_musicians();
		$key       = mb_strtolower( trim( $name ) );

		foreach ( $musicians as $i => $mu ) {
			if ( ! isset( $mu['name'] ) || mb_strtolower( trim( $mu['name'] ) ) !== $key ) {
				continue;
			}
			if ( '' !== $instrument ) {
				$musicians[ $i ]['instrument'] = $instrument;
			}
			$avail = isset( $mu['avail'] ) && is_array( $mu['avail'] ) ? $mu['avail'] : array();
			$musicians[ $i ]['avail'] = self::merge_avail( $avail, $changes );
			if ( ! isset( $musicians[ $i ]['id'] ) || '' === $musicians[ $i ]['id'] ) {
				$musicians[ $i ]['id'] = 'm' . substr( md5( uniqid( '', true ) ), 0, 10 );
			}
			self::save_musicians( $musicians );
			return $musicians[ $i ]['id'];
		}

		$id = 'm' . substr( md5( uniqid( '', true ) ), 0, 10 );
		$musicians[] = array(
			'id'         => $id,
			'name'       => $name,
			'instrument' => $instrument,
			'avail'      => self::merge_avail( array(), $changes ),
		);
		self::save_musicians( $musicians );
		return $id;
	}

	public static function delete_musician( $id ) {
		$musicians = self::get_musicians();
		foreach ( $musicians as $i => $mu ) {
			if ( isset( $mu['id'] ) && $mu['id'] === $id ) {
				unset( $musicians[ $i ] );
				self::save_musicians( $musicians );
				return true;
			}
		}
		return false;
	}

	public static function reset_all() {
		delete_option( self::OPT_MUSICIANS );
		add_option( self::OPT_MUSICIANS, array() );
	}
}
