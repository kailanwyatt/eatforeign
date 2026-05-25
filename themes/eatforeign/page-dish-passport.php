<?php
/**
 * Add or edit a dish passport entry (wizard).
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

use EatForeign\Support\PassportPhoto;
use EatForeign\Support\PostType;
use EatForeignTheme\Data;
use WP_Post;
use WP_User;

get_header();

$slug = sanitize_title( (string) get_query_var( 'ef_dish_slug' ) );
$dish = $slug !== '' ? get_page_by_path( $slug, OBJECT, PostType::DISH ) : null;

if ( ! $dish instanceof WP_Post ) {
	?>
	<div class="ef-shell ef-stack">
		<section class="ef-panel"><p><?php esc_html_e( 'Dish not found.', 'eatforeign' ); ?></p></section>
	</div>
	<?php
	get_footer();
	return;
}

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( '/dishes/' . $slug . '/passport' ) ), home_url( '/login' ) ) );
	exit;
}

$user          = wp_get_current_user();
$entry         = Data::user_passport_entry_for_dish( $dish->ID );
$is_update     = $entry !== null;
$photos        = is_array( $entry['photos'] ?? null ) ? $entry['photos'] : [];
$rating        = $is_update ? (float) ( $entry['rating'] ?? 0 ) : Data::user_rating_for_dish( $dish->ID );
$tried_on      = $is_update ? (string) ( $entry['triedOn'] ?? '' ) : '';
$note          = $is_update ? (string) ( $entry['note'] ?? '' ) : '';
$restaurant    = $is_update ? (string) ( $entry['restaurantName'] ?? '' ) : '';
$first_time    = $is_update ? (bool) ( $entry['firstTimeTrying'] ?? false ) : false;
$dish_url      = get_permalink( $dish );
$error         = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
$stamped       = isset( $_GET['stamped'] ) && (string) $_GET['stamped'] === '1';
$pending       = isset( $_GET['submitted'] ) && (string) $_GET['submitted'] === 'pending';
$max_photos    = PassportPhoto::MAX_PER_ENTRY;
$hero_image    = Data::post_image( $dish );
$passport_url  = $user instanceof WP_User ? home_url( '/passport/' . $user->user_nicename ) : home_url( '/passport' );
$share_text    = sprintf(
	/* translators: %s: dish title */
	__( 'I just stamped %s on my EatForeign food passport!', 'eatforeign' ),
	$dish->post_title
);
$share_tweet   = rawurlencode( $share_text . ' ' . $passport_url );
$share_fb      = rawurlencode( $passport_url );
?>
<div class="ef-shell ef-passport-wizard-wrap">
	<?php if ( $stamped ) : ?>
		<?php
		// Refresh entry after save so photos and note are current.
		$entry = Data::user_passport_entry_for_dish( $dish->ID );
		?>
		<section class="ef-passport-wizard ef-passport-wizard--success" data-passport-wizard data-share-text="<?php echo esc_attr( $share_text ); ?>">
			<div class="ef-passport-wizard__body ef-passport-wizard__body--success">
				<p class="ef-passport-wizard__eyebrow"><?php esc_html_e( 'Stamped!', 'eatforeign' ); ?></p>
				<h1 class="ef-passport-wizard__title"><?php esc_html_e( 'You’re on the map', 'eatforeign' ); ?></h1>
				<p class="ef-passport-wizard__lede">
					<?php
					if ( $pending ) {
						esc_html_e( 'Your photos are pending review — we’ll show them on the dish page once approved. Tell friends you’re exploring anyway!', 'eatforeign' );
					} else {
						esc_html_e( 'Flex a little — your table deserves an audience.', 'eatforeign' );
					}
					?>
				</p>

				<?php
				get_template_part(
					'template-parts/passport',
					'stamp-recap',
					[
						'dish'        => $dish,
						'entry'       => $entry,
						'hero_image'  => $hero_image,
					]
				);
				?>

				<div class="ef-passport-share">
					<h2 class="ef-passport-share__title"><?php esc_html_e( 'Share your passport', 'eatforeign' ); ?></h2>
					<p class="ef-passport-share__copy"><?php esc_html_e( 'Invite friends to see what you’re eating around the world.', 'eatforeign' ); ?></p>
					<div class="ef-passport-share__actions">
						<a class="ef-button ef-button--primary" href="https://twitter.com/intent/tweet?text=<?php echo esc_attr( $share_tweet ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Post on X', 'eatforeign' ); ?>
						</a>
						<a class="ef-button" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr( $share_fb ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Share on Facebook', 'eatforeign' ); ?>
						</a>
						<button type="button" class="ef-button" data-copy-share data-share-url="<?php echo esc_url( $passport_url ); ?>" data-default-label="<?php esc_attr_e( 'Copy link', 'eatforeign' ); ?>" data-copied-label="<?php esc_attr_e( 'Copied!', 'eatforeign' ); ?>">
							<?php esc_html_e( 'Copy link', 'eatforeign' ); ?>
						</button>
					</div>
				</div>

				<div class="ef-passport-wizard__footer">
					<a class="ef-button ef-button--primary" href="<?php echo esc_url( $passport_url ); ?>"><?php esc_html_e( 'View my passport', 'eatforeign' ); ?></a>
					<a class="ef-button" href="<?php echo esc_url( $dish_url ); ?>"><?php esc_html_e( 'Back to dish', 'eatforeign' ); ?></a>
				</div>
			</div>
		</section>
	<?php else : ?>
		<section
			class="ef-passport-wizard"
			data-passport-wizard
			data-max-photos="<?php echo esc_attr( (string) $max_photos ); ?>"
			data-existing-count="<?php echo esc_attr( (string) count( $photos ) ); ?>"
			data-share-text="<?php echo esc_attr( $share_text ); ?>"
		>
			<header class="ef-passport-wizard__top">
				<p class="ef-passport-wizard__dish">
					<a href="<?php echo esc_url( $dish_url ); ?>"><?php echo esc_html( $dish->post_title ); ?></a>
				</p>
				<h1 class="ef-passport-wizard__title">
					<?php echo $is_update ? esc_html__( 'Update your stamp', 'eatforeign' ) : esc_html__( 'Stamp your passport', 'eatforeign' ); ?>
				</h1>
				<p class="ef-passport-wizard__lede"><?php esc_html_e( 'Three quick beats — skip anything you like. No wrong answers, just your story.', 'eatforeign' ); ?></p>
				<?php if ( $error !== '' ) : ?>
					<p class="ef-form-error"><?php echo esc_html( $error ); ?></p>
				<?php endif; ?>
			</header>

			<div class="ef-passport-wizard__progress" aria-hidden="true">
				<div class="ef-passport-wizard__progress-track">
					<div class="ef-passport-wizard__progress-fill" data-wizard-progress></div>
				</div>
				<ol class="ef-passport-wizard__steps">
					<li class="ef-passport-wizard__step" data-wizard-step="1">
						<span class="ef-passport-wizard__step-icon" aria-hidden="true">🎫</span>
						<span class="ef-passport-wizard__step-label"><?php esc_html_e( 'The bite', 'eatforeign' ); ?></span>
					</li>
					<li class="ef-passport-wizard__step" data-wizard-step="2">
						<span class="ef-passport-wizard__step-icon" aria-hidden="true">📸</span>
						<span class="ef-passport-wizard__step-label"><?php esc_html_e( 'The snap', 'eatforeign' ); ?></span>
					</li>
					<li class="ef-passport-wizard__step" data-wizard-step="3">
						<span class="ef-passport-wizard__step-icon" aria-hidden="true">✨</span>
						<span class="ef-passport-wizard__step-label"><?php esc_html_e( 'The vibe', 'eatforeign' ); ?></span>
					</li>
				</ol>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-passport-wizard__form" data-wizard-form enctype="multipart/form-data">
				<?php wp_nonce_field( 'ef_save_passport_entry' ); ?>
				<input type="hidden" name="action" value="ef_save_passport_entry" />
				<input type="hidden" name="dish_id" value="<?php echo esc_attr( (string) $dish->ID ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $dish_url ); ?>" />
				<input type="hidden" name="rating" value="<?php echo esc_attr( $rating > 0 ? (string) $rating : '' ); ?>" data-rating-input />

				<div class="ef-passport-wizard__panel is-active" data-wizard-panel="1">
					<div class="ef-passport-wizard__panel-grid">
						<div class="ef-passport-wizard__art-wrap" aria-hidden="true">
							<?php if ( $hero_image !== '' ) : ?>
								<img class="ef-passport-wizard__dish-photo" src="<?php echo esc_url( $hero_image ); ?>" alt="" />
							<?php else : ?>
								<svg class="ef-passport-wizard__art" viewBox="0 0 200 160" aria-hidden="true">
									<ellipse cx="100" cy="120" rx="70" ry="12" fill="#f0e6dc"/>
									<circle cx="100" cy="72" r="44" fill="#ffe8d6" stroke="#e8c4a8" stroke-width="3"/>
									<path d="M72 78 Q100 108 128 78" fill="none" stroke="#c45c26" stroke-width="4" stroke-linecap="round"/>
									<text x="100" y="52" text-anchor="middle" font-size="32">🍽️</text>
								</svg>
							<?php endif; ?>
						</div>
						<div class="ef-passport-wizard__fields">
							<h2 class="ef-passport-wizard__panel-title"><?php esc_html_e( 'How was it?', 'eatforeign' ); ?></h2>
							<p class="ef-passport-wizard__hint"><?php esc_html_e( 'Tap a mood — or skip. Your stamp still counts.', 'eatforeign' ); ?></p>
							<div class="ef-passport-rating" role="group" aria-label="<?php esc_attr_e( 'Optional rating', 'eatforeign' ); ?>">
								<?php
								$faces = [
									'1' => '😕',
									'2' => '🙂',
									'3' => '😊',
									'4' => '🤩',
									'5' => '🔥',
								];
								foreach ( $faces as $value => $face ) :
									$selected = abs( $rating - (float) $value ) < 0.01;
									?>
									<button
										type="button"
										class="ef-passport-rating__btn<?php echo $selected ? ' is-selected' : ''; ?>"
										data-rating-option="<?php echo esc_attr( $value ); ?>"
										aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>"
									>
										<span aria-hidden="true"><?php echo esc_html( $face ); ?></span>
										<span class="ef-passport-rating__num"><?php echo esc_html( $value ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>

							<label class="ef-field ef-field--optional">
								<span><?php esc_html_e( 'When did you try it?', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
								<input type="date" name="tried_on" value="<?php echo esc_attr( $tried_on ); ?>" />
							</label>

							<label class="ef-field ef-field--checkbox ef-field--optional">
								<input type="checkbox" name="first_time_trying" value="1" <?php checked( $first_time ); ?> />
								<span><?php esc_html_e( 'First time — worth celebrating!', 'eatforeign' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<div class="ef-passport-wizard__panel" data-wizard-panel="2" hidden>
					<div class="ef-passport-wizard__panel-grid">
						<div class="ef-passport-wizard__art-wrap" aria-hidden="true">
							<svg class="ef-passport-wizard__art" viewBox="0 0 200 160" aria-hidden="true">
								<rect x="55" y="28" width="90" height="70" rx="10" fill="#fff" stroke="#e8c4a8" stroke-width="3"/>
								<circle cx="100" cy="62" r="18" fill="#ffe8d6"/>
								<rect x="40" y="108" width="120" height="24" rx="12" fill="#c45c26" opacity="0.2"/>
								<text x="100" y="126" text-anchor="middle" font-size="22">📷</text>
							</svg>
						</div>
						<div class="ef-passport-wizard__fields">
							<h2 class="ef-passport-wizard__panel-title"><?php esc_html_e( 'Got a photo?', 'eatforeign' ); ?></h2>
							<p class="ef-passport-wizard__hint"><?php esc_html_e( 'Plate shots, messy bites, neon signs — add a caption if you want. Skip entirely if you’re camera-shy.', 'eatforeign' ); ?></p>

							<?php if ( $photos !== [] ) : ?>
								<div class="ef-passport-form-photos">
									<?php foreach ( $photos as $photo ) : ?>
										<?php
										$url     = (string) ( $photo['url'] ?? '' );
										$caption = (string) ( $photo['caption'] ?? '' );
										if ( $url === '' ) {
											continue;
										}
										?>
										<div class="ef-passport-form-photo" data-photo-card>
											<img class="ef-passport-form-photo__preview" src="<?php echo esc_url( $url ); ?>" alt="" data-photo-preview />
											<input type="hidden" name="existing_photo_url[]" value="<?php echo esc_url( $url ); ?>" />
											<label class="ef-field ef-field--optional">
												<span><?php esc_html_e( 'Caption', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
												<input type="text" name="existing_photo_caption[]" value="<?php echo esc_attr( $caption ); ?>" placeholder="<?php esc_attr_e( 'First bite, night market…', 'eatforeign' ); ?>" />
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<div class="ef-passport-form-photos" data-photo-list>
								<div class="ef-passport-form-photo" data-photo-card>
									<div class="ef-passport-photo-drop">
										<img class="ef-passport-form-photo__preview" alt="" hidden data-photo-preview />
										<label class="ef-passport-photo-drop__label">
											<span class="ef-passport-photo-drop__icon" aria-hidden="true">＋</span>
											<span><?php esc_html_e( 'Add a photo', 'eatforeign' ); ?></span>
											<input type="file" name="passport_images[]" accept="image/*" />
										</label>
									</div>
									<label class="ef-field ef-field--optional">
										<span><?php esc_html_e( 'Caption', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
										<input type="text" name="new_photo_caption[]" placeholder="<?php esc_attr_e( 'Say something fun…', 'eatforeign' ); ?>" />
									</label>
								</div>
							</div>

							<button type="button" class="ef-button ef-button--ghost" data-add-photo>
								<?php esc_html_e( 'Add another photo', 'eatforeign' ); ?>
							</button>
						</div>
					</div>
				</div>

				<div class="ef-passport-wizard__panel" data-wizard-panel="3" hidden>
					<div class="ef-passport-wizard__panel-grid">
						<div class="ef-passport-wizard__art-wrap" aria-hidden="true">
							<svg class="ef-passport-wizard__art" viewBox="0 0 200 160" aria-hidden="true">
								<path d="M40 110 Q100 40 160 110" fill="none" stroke="#e8c4a8" stroke-width="3" stroke-dasharray="8 6"/>
								<circle cx="60" cy="90" r="8" fill="#c45c26" opacity="0.5"/>
								<circle cx="100" cy="70" r="10" fill="#c45c26"/>
								<circle cx="140" cy="95" r="7" fill="#c45c26" opacity="0.7"/>
								<text x="100" y="138" text-anchor="middle" font-size="30">💬</text>
							</svg>
						</div>
						<div class="ef-passport-wizard__fields">
							<h2 class="ef-passport-wizard__panel-title"><?php esc_html_e( 'Anything else?', 'eatforeign' ); ?></h2>
							<p class="ef-passport-wizard__hint"><?php esc_html_e( 'A hot take, a memory, where you ate — all optional. Then stamp it!', 'eatforeign' ); ?></p>

							<label class="ef-field ef-field--optional">
								<span><?php esc_html_e( 'Your take', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
								<textarea name="note" rows="4" placeholder="<?php esc_attr_e( 'Would order again at 2am…', 'eatforeign' ); ?>"><?php echo esc_textarea( $note ); ?></textarea>
							</label>

							<label class="ef-field ef-field--optional">
								<span><?php esc_html_e( 'Where?', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
								<input type="text" name="restaurant_name" value="<?php echo esc_attr( $restaurant ); ?>" placeholder="<?php esc_attr_e( 'Street cart, grandma’s kitchen…', 'eatforeign' ); ?>" />
							</label>
						</div>
					</div>
				</div>

				<footer class="ef-passport-wizard__nav">
					<button type="button" class="ef-button ef-button--ghost ef-wizard-btn--hidden" data-wizard-back hidden><?php esc_html_e( 'Back', 'eatforeign' ); ?></button>
					<button type="button" class="ef-button ef-button--primary" data-wizard-next><?php esc_html_e( 'Next', 'eatforeign' ); ?></button>
					<button type="submit" class="ef-button ef-button--primary ef-wizard-btn--hidden" data-wizard-submit hidden>
						<?php echo $is_update ? esc_html__( 'Update my stamp', 'eatforeign' ) : esc_html__( 'Stamp my passport', 'eatforeign' ); ?>
					</button>
				</footer>
			</form>

			<template data-photo-template>
				<div class="ef-passport-form-photo" data-photo-card>
					<div class="ef-passport-photo-drop">
						<img class="ef-passport-form-photo__preview" alt="" hidden data-photo-preview />
						<label class="ef-passport-photo-drop__label">
							<span class="ef-passport-photo-drop__icon" aria-hidden="true">＋</span>
							<span><?php esc_html_e( 'Add a photo', 'eatforeign' ); ?></span>
							<input type="file" name="passport_images[]" accept="image/*" />
						</label>
					</div>
					<label class="ef-field ef-field--optional">
						<span><?php esc_html_e( 'Caption', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
						<input type="text" name="new_photo_caption[]" placeholder="<?php esc_attr_e( 'Say something fun…', 'eatforeign' ); ?>" />
					</label>
				</div>
			</template>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
