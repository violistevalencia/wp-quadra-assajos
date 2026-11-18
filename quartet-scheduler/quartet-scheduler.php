<?php
/**
 * Plugin Name:       Programant Haydn — Quadra d'assajos
 * Plugin URI:        https://example.com/programant-haydn
 * Description:       Planificador d'estil Doodle per quadrar les disponibilitats dels músics d'un quartet: formulari senzill, calendari visual de coincidències i resum en text. Inclou el dia del concert destacat com a data clau i inamovible.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quartet Vinaròs
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quartet-scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QS_VERSION', '1.0.0' );
define( 'QS_DIR', plugin_dir_path( __FILE__ ) );
define( 'QS_URL', plugin_dir_url( __FILE__ ) );

require_once QS_DIR . 'includes/class-qs-store.php';
require_once QS_DIR . 'includes/class-qs-render.php';

final class QS_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( QS_DIR . 'quartet-scheduler.php', array( __CLASS__, 'activate' ) );

		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_action( 'wp_ajax_qs_render', array( $this, 'ajax_render' ) );
		add_action( 'wp_ajax_nopriv_qs_render', array( $this, 'ajax_render' ) );
		add_action( 'wp_ajax_qs_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_nopriv_qs_save', array( $this, 'ajax_save' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
	}

	public static function activate() {
		if ( false === get_option( QS_Store::OPT_SETTINGS, false ) ) {
			add_option( QS_Store::OPT_SETTINGS, QS_Store::defaults() );
		}
		if ( false === get_option( QS_Store::OPT_MUSICIANS, false ) ) {
			add_option( QS_Store::OPT_MUSICIANS, array() );
		}
	}

	/* ---------- Frontend ---------- */

	public function register_shortcodes() {
		add_shortcode( 'quadra_assajos', array( $this, 'render_shortcode' ) );
		add_shortcode( 'quartet_scheduler', array( $this, 'render_shortcode' ) );
	}

	public function register_assets() {
		wp_register_style( 'qs-style', QS_URL . 'assets/css/qs.css', array(), QS_VERSION );
		wp_register_script( 'qs-script', QS_URL . 'assets/js/qs.js', array(), QS_VERSION, true );
	}

	public function render_shortcode() {
		wp_enqueue_style( 'qs-style' );
		wp_enqueue_script( 'qs-script' );

		wp_localize_script( 'qs-script', 'qsData', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'qs-nonce' ),
			'i18n'     => array(
				'loading'   => 'Carregant…',
				'saveError' => 'No s’ha pogut desar. Torna-ho a provar.',
				'needName'  => 'Escriu el teu nom abans de desar.',
				'needPick'  => 'Tria el teu nom a la llista o omple el formulari de nou músic.',
			),
		) );

		$settings = QS_Store::get_settings();
		$months   = $settings['months'];
		$month    = ! empty( $months ) ? $months[0] : gmdate( 'Y-m' );

		return QS_Render::render_app( '', $month, 0, null );
	}

	/* ---------- AJAX ---------- */

	private function get_request_args() {
		$settings = QS_Store::get_settings();
		$months   = $settings['months'];

		$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) || ! in_array( $month, $months, true ) ) {
			$month = ! empty( $months ) ? $months[0] : gmdate( 'Y-m' );
		}

		$week = isset( $_POST['week'] ) ? absint( $_POST['week'] ) : 0;
		$day  = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
		if ( '' !== $day && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
			$day = '';
		}

		$mid = isset( $_POST['mid'] ) ? sanitize_text_field( wp_unslash( $_POST['mid'] ) ) : '';

		return array( $month, $week, $day, $mid );
	}

	public function ajax_render() {
		check_ajax_referer( 'qs-nonce', 'nonce' );

		list( $month, $week, $day, $mid ) = $this->get_request_args();

		wp_send_json_success( array(
			'html' => QS_Render::render_app( $mid, $month, $week, $day ),
		) );
	}

	public function ajax_save() {
		check_ajax_referer( 'qs-nonce', 'nonce' );

		list( , , , $mid ) = $this->get_request_args();

		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$instrument = isset( $_POST['instrument'] ) ? sanitize_text_field( wp_unslash( $_POST['instrument'] ) ) : '';

		if ( '' === $mid && '' === trim( $name ) ) {
			wp_send_json_error( 'Escriu el teu nom abans de desar.' );
		}

		$changes = array();
		if ( isset( $_POST['changes'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['changes'] ), true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $date => $slots ) {
					if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! is_array( $slots ) ) {
						continue;
					}
					foreach ( $slots as $slot => $st ) {
						if ( ! is_string( $slot ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $slot ) ) {
							continue;
						}
						$st = is_null( $st ) ? '' : (string) (int) $st;
						if ( '0' === $st || '1' === $st || '2' === $st ) {
							$changes[ $date ][ $slot ] = (int) $st;
						} elseif ( '' === $st ) {
							$changes[ $date ][ $slot ] = null;
						}
					}
				}
			}
		}

		$new_id = QS_Store::upsert_musician( $mid, $name, $instrument, $changes );

		list( $month ) = $this->get_request_args();

		wp_send_json_success( array(
			'mid'     => $new_id,
			'message' => 'La teva disponibilitat s’ha desat correctament ✓',
			'html'    => QS_Render::render_app( $new_id, $month, 0, null ),
		) );
	}

	/* ---------- Admin ---------- */

	public function admin_menu() {
		add_menu_page(
			"Quadra d'assajos",
			'Quadra assajos',
			'manage_options',
			'quadra-assajos',
			array( $this, 'render_admin_page' ),
			'dashicons-calendar-alt',
			58
		);
	}

	public function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) || 'quadra-assajos' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_POST['qs_action'] ) && 'save_settings' === $_POST['qs_action'] ) {
			check_admin_referer( 'qs_settings' );
			$months_raw = isset( $_POST['qs_months'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_months'] ) ) : '';
			$months     = array();
			foreach ( preg_split( '/[\s,;]+/', $months_raw ) as $m ) {
				if ( preg_match( '/^\d{4}-\d{2}$/', $m ) ) {
					$months[] = $m;
				}
			}
			$months = array_values( array_unique( $months ) );
			if ( empty( $months ) ) {
				$months = QS_Store::defaults()['months'];
			}
			sort( $months );

			$settings = array(
				'title'        => isset( $_POST['qs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_title'] ) ) : '',
				'subtitle'     => isset( $_POST['qs_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_subtitle'] ) ) : '',
				'venue'        => isset( $_POST['qs_venue'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_venue'] ) ) : '',
				'months'       => $months,
				'concert_date' => isset( $_POST['qs_concert_date'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_concert_date'] ) ) : '',
				'concert_time' => isset( $_POST['qs_concert_time'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_concert_time'] ) ) : '',
				'start_hour'   => isset( $_POST['qs_start_hour'] ) ? max( 0, min( 23, absint( $_POST['qs_start_hour'] ) ) ) : 10,
				'end_hour'     => isset( $_POST['qs_end_hour'] ) ? max( 1, min( 24, absint( $_POST['qs_end_hour'] ) ) ) : 22,
				'slot_minutes' => ( isset( $_POST['qs_slot_minutes'] ) && in_array( absint( $_POST['qs_slot_minutes'] ), array( 30, 60, 90, 120 ), true ) ) ? absint( $_POST['qs_slot_minutes'] ) : 60,
			);
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $settings['concert_date'] ) ) {
				$settings['concert_date'] = QS_Store::defaults()['concert_date'];
			}
			if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $settings['concert_time'] ) ) {
				$settings['concert_time'] = QS_Store::defaults()['concert_time'];
			}
			if ( $settings['end_hour'] <= $settings['start_hour'] ) {
				$settings['end_hour'] = min( 24, $settings['start_hour'] + 1 );
			}

			update_option( QS_Store::OPT_SETTINGS, $settings, false );
			add_settings_error( 'quadra-assajos', 'qs_saved', 'Configuració desada.', 'updated' );
		}

		if ( isset( $_POST['qs_action'] ) && 'delete_musician' === $_POST['qs_action'] ) {
			check_admin_referer( 'qs_delete_musician' );
			$mid = isset( $_POST['mid'] ) ? sanitize_text_field( wp_unslash( $_POST['mid'] ) ) : '';
			if ( '' !== $mid ) {
				QS_Store::delete_musician( $mid );
				add_settings_error( 'quadra-assajos', 'qs_deleted', 'Músic eliminat.', 'updated' );
			}
		}

		if ( isset( $_POST['qs_action'] ) && 'reset_all' === $_POST['qs_action'] ) {
			check_admin_referer( 'qs_reset_all' );
			QS_Store::reset_all();
			add_settings_error( 'quadra-assajos', 'qs_reset', 'Totes les dades han estat esborrades.', 'updated' );
		}
	}

	public function render_admin_page() {
		$s         = QS_Store::get_settings();
		$musicians = QS_Store::get_musicians();
		?>
		<div class="wrap">
			<h1>Programant Haydn — Quadra d'assajos</h1>
			<?php settings_errors( 'quadra-assajos' ); ?>

			<h2 class="title">Configuració</h2>
			<form method="post">
				<input type="hidden" name="qs_action" value="save_settings">
				<?php wp_nonce_field( 'qs_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qs_title">Títol del calendari</label></th>
						<td><input type="text" id="qs_title" name="qs_title" class="regular-text" value="<?php echo esc_attr( $s['title'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs_subtitle">Sota-títol</label></th>
						<td><input type="text" id="qs_subtitle" name="qs_subtitle" class="regular-text" value="<?php echo esc_attr( $s['subtitle'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs_venue">Lloc</label></th>
						<td><input type="text" id="qs_venue" name="qs_venue" class="regular-text" value="<?php echo esc_attr( $s['venue'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs_months">Mesos (AAAA-MM, separats per comes)</label></th>
						<td><input type="text" id="qs_months" name="qs_months" class="regular-text" value="<?php echo esc_attr( implode( ', ', $s['months'] ) ); ?>">
							<p class="description">Per exemple: <code>2025-11, 2025-12, 2026-01, 2026-02</code></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs_concert_date">Data del concert (inamovible)</label></th>
						<td>
							<input type="date" id="qs_concert_date" name="qs_concert_date" value="<?php echo esc_attr( $s['concert_date'] ); ?>">
							<input type="time" name="qs_concert_time" value="<?php echo esc_attr( $s['concert_time'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qs_start_hour">Hores disponibles</label></th>
						<td>
							De les <input type="number" id="qs_start_hour" name="qs_start_hour" min="0" max="23" value="<?php echo esc_attr( $s['start_hour'] ); ?>" class="small-text"> h
							fins a les <input type="number" name="qs_end_hour" min="1" max="24" value="<?php echo esc_attr( $s['end_hour'] ); ?>" class="small-text"> h
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qs_slot_minutes">Durada de cada franja horària</label></th>
						<td>
							<select id="qs_slot_minutes" name="qs_slot_minutes">
								<?php foreach ( array( 30, 60, 90, 120 ) as $m ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $s['slot_minutes'], $m ); ?>><?php echo esc_html( $m ); ?> minuts</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Desar configuració' ); ?>
			</form>

			<hr>
			<h2 class="title">Músics (<?php echo count( $musicians ); ?>)</h2>
			<?php if ( empty( $musicians ) ) : ?>
				<p>Encara no hi ha cap músic. Els músics s'afegeixen des del formulari públic (shortcode).</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Nom</th>
							<th>Instrument</th>
							<th>Dies amb resposta</th>
							<th width="80">Accions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $musicians as $mu ) : ?>
							<tr>
								<td><?php echo esc_html( $mu['name'] ); ?></td>
								<td><?php echo esc_html( isset( $mu['instrument'] ) ? $mu['instrument'] : '' ); ?></td>
								<td><?php echo is_array( $mu['avail'] ) ? count( $mu['avail'] ) : 0; ?></td>
								<td>
									<form method="post" onsubmit="return confirm('Eliminar aquest músic i la seva disponibilitat?');">
										<input type="hidden" name="qs_action" value="delete_musician">
										<input type="hidden" name="mid" value="<?php echo esc_attr( $mu['id'] ); ?>">
										<?php wp_nonce_field( 'qs_delete_musician' ); ?>
										<button type="submit" class="button-link delete">Eliminar</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr>
			<h2 class="title">Zona de perill</h2>
			<form method="post" onsubmit="return confirm('Segur que vols esborrar TOTES les dades (músics i disponibilitats)? Aquesta acció no es pot desfer.');">
				<input type="hidden" name="qs_action" value="reset_all">
				<?php wp_nonce_field( 'qs_reset_all' ); ?>
				<?php submit_button( 'Esborrar totes les dades', 'delete', 'submit', false ); ?>
			</form>

			<hr>
			<p><strong>Ús:</strong> afegeix <code>[quadra_assajos]</code> a la pàgina on vulguis mostrar el calendari.</p>
		</div>
		<?php
	}
}

add_action( 'plugins_loaded', array( 'QS_Plugin', 'instance' ) );
