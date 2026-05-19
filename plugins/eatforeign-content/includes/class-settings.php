<?php
/**
 * Admin settings and bulk blog generation UI.
 *
 * @package EatForeignContent
 */

declare(strict_types=1);

namespace EatForeignContent;

final class Settings {
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_settings_page' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'wp_ajax_eatforeign_content_generate_blog', [ BlogGenerator::class, 'ajax_generate' ] );
		add_action( 'admin_post_eatforeign_content_generate_one', [ self::class, 'handle_generate_one' ] );
	}

	public static function add_settings_page(): void {
		add_options_page(
			'EatForeign Content',
			'EatForeign Content',
			'manage_options',
			'eatforeign-content',
			[ self::class, 'render_settings_page' ]
		);
	}

	public static function register_settings(): void {
		register_setting( 'eatforeign_content_settings', 'eatforeign_content_daily_limit' );
		register_setting( 'eatforeign_content_settings', 'eatforeign_content_image_daily_limit' );
		register_setting(
			'eatforeign_content_settings',
			'eatforeign_content_cron_enabled',
			[
				'sanitize_callback' => static fn ( $value ): string => $value === '1' || $value === 1 || $value === true ? '1' : '0',
			]
		);
		register_setting( 'eatforeign_content_settings', 'eatforeign_content_default_category' );
	}

	public static function handle_generate_one(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'eatforeign_content_generate_one' );

		$result = BlogGenerator::generate_draft();
		$redirect = admin_url( 'options-general.php?page=eatforeign-content' );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'ef_content_error', rawurlencode( $result->get_error_message() ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'ef_content_created', (string) $result, $redirect ) );
		exit;
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$gemini_ok  = (string) get_option( 'eatforeign_ai_api_key', '' ) !== '';
		$openai_ok  = (string) get_option( 'eatforeign_openai_api_key', '' ) !== '';
		$catalog_ok = BlogTopicResolver::catalog_available();
		$log_path   = EATFOREIGN_CONTENT_DIR . 'eatforeign-content.log';
		$log_tail   = '';

		if ( file_exists( $log_path ) ) {
			$lines = file( $log_path, FILE_IGNORE_NEW_LINES );
			if ( is_array( $lines ) ) {
				$log_tail = implode( "\n", array_slice( $lines, -15 ) );
			}
		}

		if ( isset( $_GET['ef_content_created'] ) ) {
			$post_id = (int) $_GET['ef_content_created'];
			$edit    = get_edit_post_link( $post_id, 'raw' );
			echo '<div class="notice notice-success"><p>Draft created! ';
			if ( $edit ) {
				echo '<a href="' . esc_url( $edit ) . '">Edit post</a>';
			}
			echo '</p></div>';
		}
		if ( isset( $_GET['ef_content_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( (string) $_GET['ef_content_error'] ) ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1>EatForeign Content — SEO Blog Automation</h1>
			<p>Generates WordPress blog post <strong>drafts</strong> using Gemini (article) and OpenAI (featured image). Posts are never auto-published.</p>

			<h2>API Keys</h2>
			<table class="form-table">
				<tr>
					<th>Gemini (articles)</th>
					<td>
						<?php if ( $gemini_ok ) : ?>
							<span style="color:green;">Configured</span>
						<?php else : ?>
							<span style="color:red;">Missing</span>
						<?php endif; ?>
						— <a href="<?php echo esc_url( admin_url( 'options-general.php?page=eatforeign-api' ) ); ?>">EatForeign API settings</a>
					</td>
				</tr>
				<tr>
					<th>OpenAI (images)</th>
					<td>
						<?php if ( $openai_ok ) : ?>
							<span style="color:green;">Configured</span>
						<?php else : ?>
							<span style="color:red;">Missing</span>
						<?php endif; ?>
						— <a href="<?php echo esc_url( admin_url( 'options-general.php?page=eatforeign-api' ) ); ?>">EatForeign API settings</a>
					</td>
				</tr>
				<tr>
					<th>EatForeign catalog</th>
					<td>
						<?php if ( $catalog_ok ) : ?>
							<span style="color:green;">Active</span> — topics prefer celebrations and dishes from the catalog.
						<?php else : ?>
							<span style="color:orange;">Inactive</span> — activate the EatForeign plugin for catalog-linked posts; otherwise freeform topics only.
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'eatforeign_content_settings' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="eatforeign_content_daily_limit">Max blog drafts per day</label></th>
						<td>
							<input type="number" id="eatforeign_content_daily_limit" name="eatforeign_content_daily_limit" value="<?php echo esc_attr( (string) get_option( 'eatforeign_content_daily_limit', '1' ) ); ?>" class="small-text" min="0" />
							<p class="description">Manual + cron combined (0 = unlimited).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eatforeign_content_image_daily_limit">Max blog images per day</label></th>
						<td>
							<input type="number" id="eatforeign_content_image_daily_limit" name="eatforeign_content_image_daily_limit" value="<?php echo esc_attr( (string) get_option( 'eatforeign_content_image_daily_limit', '3' ) ); ?>" class="small-text" min="0" />
							<p class="description">Separate from dish image quota in EatForeign API.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Daily cron</th>
						<td>
							<input type="hidden" name="eatforeign_content_cron_enabled" value="0" />
							<label>
								<input type="checkbox" name="eatforeign_content_cron_enabled" value="1" <?php checked( get_option( 'eatforeign_content_cron_enabled', '1' ), '1' ); ?> />
								Generate one draft per day when under the daily limit
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save settings' ); ?>
			</form>

			<hr />

			<h2>Generate now</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px;">
				<input type="hidden" name="action" value="eatforeign_content_generate_one" />
				<?php wp_nonce_field( 'eatforeign_content_generate_one' ); ?>
				<button type="submit" class="button button-primary" <?php disabled( ! $gemini_ok ); ?>>Generate 1 draft now</button>
			</form>

			<h3 style="margin-top:24px;">Bulk generate</h3>
			<p>Creates multiple drafts via AJAX. Stops on Gemini rate limits or after 3 failures per item.</p>
			<p>
				<label for="blog_bulk_count">Number of posts:</label>
				<input type="number" id="blog_bulk_count" class="small-text" value="3" min="1" max="20" />
				<button type="button" id="start_blog_bulk" class="button" <?php disabled( ! $gemini_ok ); ?>>Start bulk generation</button>
			</p>

			<div id="blog_bulk_progress_container" style="display:none; margin-top:16px; max-width:600px;">
				<div style="background:#e5e7eb;border-radius:4px;overflow:hidden;height:20px;width:100%;">
					<div id="blog_bulk_progress_bar" style="background:#10b981;height:100%;width:0%;transition:width 0.3s ease;"></div>
				</div>
				<p id="blog_bulk_status_text" style="font-weight:bold;margin-top:10px;">Initializing...</p>
			</div>

			<hr />

			<h2>Log (last 15 lines)</h2>
			<pre style="background:#f6f7f7;padding:12px;max-height:200px;overflow:auto;font-size:12px;"><?php echo esc_html( $log_tail ?: '(empty)' ); ?></pre>
			<p class="description">Full log: <code><?php echo esc_html( $log_path ); ?></code></p>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const btn = document.getElementById('start_blog_bulk');
			const container = document.getElementById('blog_bulk_progress_container');
			const bar = document.getElementById('blog_bulk_progress_bar');
			const statusText = document.getElementById('blog_bulk_status_text');
			const countInput = document.getElementById('blog_bulk_count');

			if (!btn) return;

			let totalCount = 0;
			let currentCount = 0;
			let consecutiveFailures = 0;
			const MAX_RETRIES = 3;

			function finishBulk(message) {
				statusText.textContent = message;
				btn.disabled = false;
				countInput.disabled = false;
			}

			btn.addEventListener('click', function() {
				totalCount = parseInt(countInput.value, 10);
				if (totalCount < 1) return;

				currentCount = 0;
				consecutiveFailures = 0;
				btn.disabled = true;
				countInput.disabled = true;
				container.style.display = 'block';
				updateProgress();
				runNext();
			});

			function updateProgress() {
				const pct = totalCount > 0 ? Math.round((currentCount / totalCount) * 100) : 0;
				bar.style.width = pct + '%';
				statusText.textContent = 'Created ' + currentCount + ' of ' + totalCount + ' drafts...';
			}

			function runNext() {
				if (currentCount >= totalCount) {
					finishBulk('Bulk blog generation complete!');
					return;
				}

				statusText.textContent = 'Generating draft ' + (currentCount + 1) + ' of ' + totalCount + '...';

				const formData = new URLSearchParams();
				formData.append('action', 'eatforeign_content_generate_blog');

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: formData.toString()
				})
				.then(r => r.json())
				.then(data => {
					if (data.success) {
						consecutiveFailures = 0;
						currentCount++;
						updateProgress();
						setTimeout(runNext, 5000);
						return;
					}

					const err = (data.data && typeof data.data === 'object') ? data.data : {};
					const code = err.code || 'failed';

					if (code === 'rate_limited') {
						const waitSec = err.retry_after || 60;
						finishBulk('Gemini quota exceeded. Stopped after ' + currentCount + ' of ' + totalCount + '. Try again in ~' + waitSec + 's.');
						return;
					}

					consecutiveFailures++;
					if (consecutiveFailures >= MAX_RETRIES) {
						finishBulk('Stopped after ' + MAX_RETRIES + ' failures on item ' + (currentCount + 1) + '. Created ' + currentCount + ' of ' + totalCount + '.');
						return;
					}

					const delay = 8000 * consecutiveFailures;
					statusText.textContent = 'Item ' + (currentCount + 1) + ' failed. Retry ' + consecutiveFailures + '/' + MAX_RETRIES + ' in ' + (delay/1000) + 's...';
					setTimeout(runNext, delay);
				})
				.catch(err => {
					console.error(err);
					consecutiveFailures++;
					if (consecutiveFailures >= MAX_RETRIES) {
						finishBulk('Stopped after network errors. Created ' + currentCount + ' of ' + totalCount + '.');
						return;
					}
					setTimeout(runNext, 8000);
				});
			}
		});
		</script>
		<?php
	}
}
