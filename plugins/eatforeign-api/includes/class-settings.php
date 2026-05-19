<?php
/**
 * Admin Settings
 *
 * @package EatForeignAPI
 */

declare(strict_types=1);

namespace EatForeignAPI;

final class Settings {
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_settings_page' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'wp_ajax_eatforeign_bulk_generate', [ 'EatForeignAPI\ContentGenerator', 'ajax_bulk_generate' ] );
	}

	public static function add_settings_page(): void {
		add_options_page(
			'EatForeign API Settings',
			'EatForeign API',
			'manage_options',
			'eatforeign-api',
			[ self::class, 'render_settings_page' ]
		);
	}

	public static function register_settings(): void {
		register_setting( 'eatforeign_api_settings', 'eatforeign_ai_api_key' );
		register_setting( 'eatforeign_api_settings', 'eatforeign_openai_api_key' );
		register_setting(
			'eatforeign_api_settings',
			'eatforeign_openai_image_model',
			[
				'sanitize_callback' => [ self::class, 'sanitize_openai_image_model' ],
			]
		);
		register_setting( 'eatforeign_api_settings', 'eatforeign_openai_image_size' );
		register_setting( 'eatforeign_api_settings', 'eatforeign_openai_daily_limit' );
		register_setting( 'eatforeign_api_settings', 'eatforeign_google_places_api_key' );
		register_setting( 'eatforeign_api_settings', 'eatforeign_places_daily_limit' );
		register_setting( 'eatforeign_api_settings', 'eatforeign_source_urls' );
		register_setting( 'eatforeign_api_settings', 'eatforeign_dish_source_urls' );

		add_settings_section(
			'eatforeign_api_section_keys',
			'API Keys & Limits',
			null,
			'eatforeign-api'
		);

		add_settings_field(
			'eatforeign_ai_api_key',
			'Google Gemini AI API Key',
			[ self::class, 'render_ai_key_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);

		add_settings_field(
			'eatforeign_openai_api_key',
			'OpenAI API Key (Images)',
			[ self::class, 'render_openai_key_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);

		add_settings_field(
			'eatforeign_openai_image_model',
			'OpenAI Image Model',
			[ self::class, 'render_openai_model_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);

		add_settings_field(
			'eatforeign_openai_image_size',
			'OpenAI Image Size',
			[ self::class, 'render_openai_size_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);

		add_settings_field(
			'eatforeign_openai_daily_limit',
			'Max Daily OpenAI Image Generations',
			[ self::class, 'render_openai_limit_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);

		add_settings_field(
			'eatforeign_google_places_api_key',
			'Google Places API Key',
			[ self::class, 'render_places_key_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);

		add_settings_field(
			'eatforeign_places_daily_limit',
			'Max Daily Places Lookups',
			[ self::class, 'render_places_limit_field' ],
			'eatforeign-api',
			'eatforeign_api_section_keys'
		);
		
		add_settings_section(
			'eatforeign_api_section_sources',
			'Food Holiday Source URLs',
			null,
			'eatforeign-api'
		);

		add_settings_field(
			'eatforeign_source_urls',
			'Holiday Source URLs (One per line)',
			[ self::class, 'render_source_urls_field' ],
			'eatforeign-api',
			'eatforeign_api_section_sources'
		);

		add_settings_field(
			'eatforeign_dish_source_urls',
			'National Dish Source URLs (One per line)',
			[ self::class, 'render_dish_source_urls_field' ],
			'eatforeign-api',
			'eatforeign_api_section_sources'
		);
	}

	public static function render_ai_key_field(): void {
		$key = get_option( 'eatforeign_ai_api_key', '' );
		echo '<input type="password" name="eatforeign_ai_api_key" value="' . esc_attr( $key ) . '" class="regular-text" />';
	}

	public static function render_openai_key_field(): void {
		$key = get_option( 'eatforeign_openai_api_key', '' );
		echo '<input type="password" name="eatforeign_openai_api_key" value="' . esc_attr( $key ) . '" class="regular-text" />';
		echo '<p class="description">Used for manual photorealistic dish photos in wp-admin (Generate AI photo). GPT image generation is billed per image by OpenAI — ensure your account has credits and spending headroom at <a href="https://platform.openai.com/settings/organization/billing" target="_blank" rel="noopener noreferrer">OpenAI Billing</a>.</p>';
	}

	public static function sanitize_openai_image_model( mixed $value ): string {
		return OpenAIImageClient::resolve_model( is_string( $value ) ? $value : '' );
	}

	public static function render_openai_model_field(): void {
		$model = OpenAIImageClient::resolve_model( (string) get_option( 'eatforeign_openai_image_model', 'gpt-image-1.5' ) );
		echo '<select name="eatforeign_openai_image_model">';
		foreach ( [ 'gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini' ] as $option ) {
			printf(
				'<option value="%1$s" %2$s>%1$s (recommended)</option>',
				esc_attr( $option ),
				selected( $model, $option, false )
			);
		}
		echo '</select>';
		echo '<p class="description">Uses OpenAI GPT image models. Images are saved to your media library. DALL-E is no longer available on most API keys.</p>';
	}

	public static function render_openai_size_field(): void {
		$size = get_option( 'eatforeign_openai_image_size', '1024x1024' );
		echo '<select name="eatforeign_openai_image_size">';
		foreach ( [ '1024x1024', '1536x1024', '1024x1536', 'auto' ] as $option ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $option ),
				selected( $size, $option, false )
			);
		}
		echo '</select>';
		echo '<p class="description">Supported sizes for GPT image models.</p>';
	}

	public static function render_openai_limit_field(): void {
		$limit = get_option( 'eatforeign_openai_daily_limit', '10' );
		echo '<input type="number" name="eatforeign_openai_daily_limit" value="' . esc_attr( (string) $limit ) . '" class="small-text" min="0" />';
		echo '<p class="description">Manual generations per day from the dish editor (0 for no limit).</p>';
	}

	public static function render_places_key_field(): void {
		$key = get_option( 'eatforeign_google_places_api_key', '' );
		echo '<input type="password" name="eatforeign_google_places_api_key" value="' . esc_attr( $key ) . '" class="regular-text" />';
	}

	public static function render_places_limit_field(): void {
		$limit = get_option( 'eatforeign_places_daily_limit', '20' );
		echo '<input type="number" name="eatforeign_places_daily_limit" value="' . esc_attr( (string) $limit ) . '" class="small-text" min="0" />';
		echo '<p class="description">Stop making Google Places API requests after this many unique calls per day (0 for no limit). Cached results will still be served.</p>';
	}

	public static function render_source_urls_field(): void {
		$default_urls = "https://www.musthavemenus.com/feature/food-holidays.html\nhttps://en.wikipedia.org/wiki/List_of_food_days\nhttps://en.wikipedia.org/wiki/Category:Observances_about_food_and_drink\nhttps://www.baldwinpublishing.com/blog-calendar-of-national-food-days-2025/\nhttps://www.fooda.com/blog/national-food-days-you-cant-miss-in-2026\nhttps://gluttodigest.com/international-national-food-days/\nhttps://scottroberts.org/complete-listing-of-national-food-days/";
		$urls = get_option( 'eatforeign_source_urls', $default_urls );
		echo '<textarea name="eatforeign_source_urls" rows="6" class="large-text code">' . esc_textarea( $urls ) . '</textarea>';
		echo '<p class="description">Add URLs that contain food holidays. Gemini will intelligently extract the data from the raw page content.</p>';
	}

	public static function render_dish_source_urls_field(): void {
		$default_urls = "https://worldpopulationreview.com/country-rankings/countries-national-dishes";
		$urls = get_option( 'eatforeign_dish_source_urls', $default_urls );
		echo '<textarea name="eatforeign_dish_source_urls" rows="4" class="large-text code">' . esc_textarea( $urls ) . '</textarea>';
		echo '<p class="description">Add URLs that contain lists of national dishes. Gemini will extract the dishes and countries.</p>';
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>EatForeign API Settings</h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'eatforeign_api_settings' );
				do_settings_sections( 'eatforeign-api' );
				submit_button();
				?>
			</form>
			<hr/>
			<h2>Manual Triggers</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="eatforeign_api_manual_generation">
				<?php wp_nonce_field( 'eatforeign_api_manual_gen_nonce', 'eatforeign_api_nonce' ); ?>
				<p>Manually trigger the cron job to generate a new dish and celebration (Draft).</p>
				<button type="submit" class="button">Run Generation Now</button>
			</form>
		</div>

		<hr/>
		<div class="wrap">
			<h2>Scrape Sources</h2>
			<p>Extract data from the configured Source URLs and add them to the Pending Items queue.</p>
			
			<div style="display:flex;gap:15px;margin-bottom:15px;">
				<button type="button" id="start_scrape_holidays" class="button button-primary" data-type="holiday">Scrape Food Holidays</button>
				<button type="button" id="start_scrape_dishes" class="button button-secondary" data-type="dish">Scrape National Dishes</button>
			</div>
			
			<div id="scrape_progress_container" style="display:none; margin-top:20px; max-width: 600px;">
				<div style="background: #e5e7eb; border-radius: 4px; overflow: hidden; height: 20px; width: 100%;">
					<div id="scrape_progress_bar" style="background: #3b82f6; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
				</div>
				<p id="scrape_status_text" style="font-weight:bold; margin-top:10px;">Initializing Scraper...</p>
			</div>
		</div>

		<hr/>
		<div class="wrap">
			<h2>Bulk Importer (AJAX)</h2>
			<p>Rapidly generate multiple dishes and celebrations. This uses an AJAX queue to prevent server timeouts and respect API limits.</p>
			
			<table class="form-table">
				<tr>
					<th scope="row"><label for="bulk_target_country">Target Country</label></th>
					<td>
						<input type="text" id="bulk_target_country" class="regular-text" placeholder="e.g. Jamaica (Leave blank for global random)" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bulk_count">Number to Generate</label></th>
					<td>
						<input type="number" id="bulk_count" class="small-text" value="5" min="1" max="50" />
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="button" id="start_bulk_import" class="button button-primary">Start Bulk Import</button>
			</p>

			<div id="bulk_progress_container" style="display:none; margin-top:20px; max-width: 600px;">
				<div style="background: #e5e7eb; border-radius: 4px; overflow: hidden; height: 20px; width: 100%;">
					<div id="bulk_progress_bar" style="background: #10b981; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
				</div>
				<p id="bulk_status_text" style="font-weight:bold; margin-top:10px;">Initializing...</p>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const btn = document.getElementById('start_bulk_import');
			const container = document.getElementById('bulk_progress_container');
			const bar = document.getElementById('bulk_progress_bar');
			const statusText = document.getElementById('bulk_status_text');
			const countryInput = document.getElementById('bulk_target_country');
			const countInput = document.getElementById('bulk_count');
			
			let totalCount = 0;
			let currentCount = 0;
			let consecutiveFailures = 0;
			const MAX_RETRIES = 3;

			function finishBulkImport(message) {
				statusText.textContent = message;
				btn.disabled = false;
				countryInput.disabled = false;
				countInput.disabled = false;
			}

			if (btn) {
				btn.addEventListener('click', function() {
					totalCount = parseInt(countInput.value, 10);
					if (totalCount < 1) return;

					currentCount = 0;
					consecutiveFailures = 0;
					btn.disabled = true;
					countryInput.disabled = true;
					countInput.disabled = true;
					
					container.style.display = 'block';
					updateProgress();
					
					runNext();
				});
			}

			function updateProgress() {
				const pct = Math.round((currentCount / totalCount) * 100);
				bar.style.width = pct + '%';
				statusText.textContent = `Generated ${currentCount} of ${totalCount}...`;
			}

			function runNext() {
				if (currentCount >= totalCount) {
					statusText.textContent = 'Bulk import complete!';
					btn.disabled = false;
					countryInput.disabled = false;
					countInput.disabled = false;
					return;
				}

				statusText.textContent = `Generating item ${currentCount + 1} of ${totalCount}... (Waiting for AI)`;

				const formData = new URLSearchParams();
				formData.append('action', 'eatforeign_bulk_generate');
				formData.append('target_country', countryInput.value);

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: formData.toString()
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						consecutiveFailures = 0;
						currentCount++;
						updateProgress();
						setTimeout(runNext, 4000);
						return;
					}

					console.error('Bulk import error:', data);

					const err = (data.data && typeof data.data === 'object') ? data.data : {};
					const code = err.code || 'failed';

					if (code === 'rate_limited') {
						const waitSec = err.retry_after || 60;
						finishBulkImport(
							`Gemini API quota exceeded (free tier is ~20 requests/day). Stopped after ${currentCount} of ${totalCount}. Try again in about ${waitSec} seconds, or tomorrow.`
						);
						return;
					}

					consecutiveFailures++;
					if (consecutiveFailures >= MAX_RETRIES) {
						finishBulkImport(
							`Stopped after ${MAX_RETRIES} failed attempts on item ${currentCount + 1} (${err.message || 'duplicate or empty queue'}). Generated ${currentCount} of ${totalCount}.`
						);
						return;
					}

					const delay = 8000 * consecutiveFailures;
					statusText.textContent = `Item ${currentCount + 1} failed (${err.message || 'duplicate or API error'}). Retry ${consecutiveFailures}/${MAX_RETRIES} in ${delay / 1000}s...`;
					setTimeout(runNext, delay);
				})
				.catch(err => {
					console.error('Fetch Error:', err);
					consecutiveFailures++;
					if (consecutiveFailures >= MAX_RETRIES) {
						finishBulkImport(`Stopped after ${MAX_RETRIES} network errors. Generated ${currentCount} of ${totalCount}.`);
						return;
					}
					statusText.textContent = `Network error on item ${currentCount + 1}. Retry ${consecutiveFailures}/${MAX_RETRIES} in 8s...`;
					setTimeout(runNext, 8000);
				});
			}

			// Scrape Sources Script
			const scrapeHolidaysBtn = document.getElementById('start_scrape_holidays');
			const scrapeDishesBtn = document.getElementById('start_scrape_dishes');
			
			function handleScrapeClick(e) {
				const btn = e.target;
				const type = btn.dataset.type;
				
				const scrapeContainer = document.getElementById('scrape_progress_container');
				const scrapeBar = document.getElementById('scrape_progress_bar');
				const scrapeStatusText = document.getElementById('scrape_status_text');
				
				const textareaName = type === 'dish' ? 'eatforeign_dish_source_urls' : 'eatforeign_source_urls';
				const urlsTextarea = document.querySelector(`textarea[name="${textareaName}"]`);
				
				if (!urlsTextarea) return;
				
				const urls = urlsTextarea.value.split('\n').map(u => u.trim()).filter(u => u);
				if (urls.length === 0) {
					alert('Please add at least one URL to scrape.');
					return;
				}

				if (scrapeHolidaysBtn) scrapeHolidaysBtn.disabled = true;
				if (scrapeDishesBtn) scrapeDishesBtn.disabled = true;
				
				scrapeContainer.style.display = 'block';
				let currentScrapeCount = 0;
				
				function updateScrapeProgress() {
					const pct = Math.round((currentScrapeCount / urls.length) * 100);
					scrapeBar.style.width = pct + '%';
				}

				function scrapeNext() {
					if (currentScrapeCount >= urls.length) {
						scrapeStatusText.textContent = 'Scraping complete! Check the Pending Items queue.';
						if (scrapeHolidaysBtn) scrapeHolidaysBtn.disabled = false;
						if (scrapeDishesBtn) scrapeDishesBtn.disabled = false;
						return;
					}

					const urlToScrape = urls[currentScrapeCount];
					scrapeStatusText.textContent = `Scraping ${currentScrapeCount + 1} of ${urls.length}: ${urlToScrape}... (This takes a moment as Gemini reads the page)`;

					const formData = new URLSearchParams();
					formData.append('action', 'eatforeign_scrape_url');
					formData.append('url', urlToScrape);
					formData.append('type', type);

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: formData.toString()
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							console.log('Queued items:', data.data.count);
						} else {
							console.error('Scrape error:', data);
						}
						currentScrapeCount++;
						updateScrapeProgress();
						setTimeout(scrapeNext, 2000);
					})
					.catch(err => {
						console.error('Fetch Error:', err);
						currentScrapeCount++;
						updateScrapeProgress();
						setTimeout(scrapeNext, 2000);
					});
				}

				scrapeNext();
			}

			if (scrapeHolidaysBtn) scrapeHolidaysBtn.addEventListener('click', handleScrapeClick);
			if (scrapeDishesBtn) scrapeDishesBtn.addEventListener('click', handleScrapeClick);
		});
		</script>
		<?php
	}
}
