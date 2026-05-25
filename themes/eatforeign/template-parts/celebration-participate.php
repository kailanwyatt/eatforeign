<?php
/**
 * Celebration participate panel (logged in + logged out).
 *
 * @package EatForeignTheme
 *
 * @var array{
 *   celebration_id: int,
 *   featured_dishes: list<WP_Post>,
 *   completed: bool,
 * } $args
 */

declare(strict_types=1);

use EatForeignTheme\Helpers;

$celebration_id  = (int) ( $args['celebration_id'] ?? 0 );
$featured_dishes = $args['featured_dishes'] ?? [];
$completed       = (bool) ( $args['completed'] ?? false );

if ( $celebration_id <= 0 ) {
	return;
}

$redirect_to = get_permalink( $celebration_id );
if ( ! is_string( $redirect_to ) || $redirect_to === '' ) {
	$redirect_to = home_url( '/' );
}

$login_url    = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), home_url( '/login' ) );
$register_url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), home_url( '/register' ) );

$submitted = isset( $_GET['submitted'] ) && sanitize_key( wp_unslash( (string) $_GET['submitted'] ) ) === 'pending';
$error     = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
?>
<div class="ef-participate" data-celebration-participate>
	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="ef-participate__guest">
			<p class="ef-participate__lead">
				<?php esc_html_e( 'Join the table for this celebration — mark it complete, share your story, and add a photo to the community feed.', 'eatforeign' ); ?>
			</p>
			<ul class="ef-participate__benefits">
				<li><?php esc_html_e( 'Track celebrations you’ve joined', 'eatforeign' ); ?></li>
				<li><?php esc_html_e( 'Share photos, ratings, and where you ate', 'eatforeign' ); ?></li>
				<li><?php esc_html_e( 'Build your food passport over time', 'eatforeign' ); ?></li>
			</ul>
			<div class="ef-participate__cta">
				<a class="ef-button ef-button--primary ef-button--block" href="<?php echo esc_url( $login_url ); ?>">
					<?php esc_html_e( 'Log in to participate', 'eatforeign' ); ?>
				</a>
				<a class="ef-button ef-button--block" href="<?php echo esc_url( $register_url ); ?>">
					<?php esc_html_e( 'Create a free account', 'eatforeign' ); ?>
				</a>
			</div>
			<p class="ef-participate__fineprint">
				<?php esc_html_e( 'Free to join. Your posts may be reviewed before they appear in the community feed.', 'eatforeign' ); ?>
			</p>
		</div>
	<?php else : ?>
		<?php
		$user        = wp_get_current_user();
		$display     = $user->display_name !== '' ? $user->display_name : $user->user_login;
		$dish_count  = count( $featured_dishes );
		$primary_id  = $dish_count > 0 ? (int) $featured_dishes[0]->ID : 0;
		?>
		<div class="ef-participate__member">
			<div class="ef-participate__identity">
				<span class="ef-participate__avatar" aria-hidden="true"><?php echo esc_html( Helpers::initials( $display ) ); ?></span>
				<div>
					<p class="ef-participate__identity-label"><?php esc_html_e( 'Sharing as', 'eatforeign' ); ?></p>
					<p class="ef-participate__identity-name"><?php echo esc_html( $display ); ?></p>
				</div>
			</div>

			<?php if ( $error !== '' ) : ?>
				<p class="ef-form-error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<?php if ( $submitted ) : ?>
				<p class="ef-form-success" role="status">
					<?php esc_html_e( 'Thanks! Your post is pending moderation and will appear in the feed once approved.', 'eatforeign' ); ?>
				</p>
			<?php endif; ?>

			<div class="ef-participate__completion">
				<div class="ef-participate__completion-copy">
					<strong><?php esc_html_e( 'Did you celebrate?', 'eatforeign' ); ?></strong>
					<p><?php esc_html_e( 'Add this to your personal celebration history.', 'eatforeign' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-inline-form">
					<?php wp_nonce_field( 'ef_toggle_celebration' ); ?>
					<input type="hidden" name="action" value="ef_toggle_celebration" />
					<input type="hidden" name="celebration_id" value="<?php echo esc_attr( (string) $celebration_id ); ?>" />
					<button
						type="submit"
						class="ef-button<?php echo $completed ? ' ef-button--ghost' : ' ef-button--primary'; ?>"
						aria-pressed="<?php echo $completed ? 'true' : 'false'; ?>"
					>
						<?php
						echo esc_html(
							$completed
								? __( 'Celebrated ✓', 'eatforeign' )
								: __( 'I celebrated this', 'eatforeign' )
						);
						?>
					</button>
				</form>
			</div>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				class="ef-form ef-form--participate"
				enctype="multipart/form-data"
				data-participate-form
			>
				<?php wp_nonce_field( 'ef_create_celebration_post' ); ?>
				<input type="hidden" name="action" value="ef_create_celebration_post" />
				<input type="hidden" name="celebration_id" value="<?php echo esc_attr( (string) $celebration_id ); ?>" />
				<input type="hidden" name="rating" value="" data-rating-input />

				<fieldset class="ef-participate__fieldset">
					<legend class="ef-participate__legend"><?php esc_html_e( 'Share with the community', 'eatforeign' ); ?></legend>
					<p class="ef-participate__hint"><?php esc_html_e( 'Everything below is optional except your story — add as much or as little as you like.', 'eatforeign' ); ?></p>

					<label class="ef-field">
						<span><?php esc_html_e( 'Your story', 'eatforeign' ); ?></span>
						<textarea
							name="caption"
							rows="4"
							required
							placeholder="<?php esc_attr_e( 'What did you try? Who were you with? What made it memorable?', 'eatforeign' ); ?>"
						></textarea>
					</label>

					<?php if ( $dish_count > 1 ) : ?>
						<label class="ef-field ef-field--optional">
							<span><?php esc_html_e( 'Which dish?', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
							<select name="dish_id">
								<option value=""><?php esc_html_e( '— Select a dish —', 'eatforeign' ); ?></option>
								<?php foreach ( $featured_dishes as $dish ) : ?>
									<?php if ( ! $dish instanceof WP_Post ) {
										continue;
									} ?>
									<option value="<?php echo esc_attr( (string) $dish->ID ); ?>"<?php selected( $primary_id, $dish->ID ); ?>>
										<?php echo esc_html( get_the_title( $dish ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php elseif ( $primary_id > 0 ) : ?>
						<input type="hidden" name="dish_id" value="<?php echo esc_attr( (string) $primary_id ); ?>" />
					<?php endif; ?>

					<div class="ef-field ef-field--optional">
						<span><?php esc_html_e( 'How was it?', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
						<div class="ef-passport-rating" role="group" aria-label="<?php esc_attr_e( 'Rating', 'eatforeign' ); ?>">
							<?php
							$faces = [
								'1' => '😕',
								'2' => '🙂',
								'3' => '😊',
								'4' => '🤩',
								'5' => '🔥',
							];
							foreach ( $faces as $value => $face ) :
								?>
								<button
									type="button"
									class="ef-passport-rating__btn"
									data-rating-option="<?php echo esc_attr( $value ); ?>"
									aria-pressed="false"
								>
									<span aria-hidden="true"><?php echo esc_html( $face ); ?></span>
									<span class="ef-passport-rating__num"><?php echo esc_html( $value ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="ef-field ef-field--optional" data-photo-field>
						<span><?php esc_html_e( 'Photo', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
						<div class="ef-passport-photo-drop">
							<img class="ef-participate-photo__preview" alt="" hidden data-photo-preview />
							<label class="ef-passport-photo-drop__label">
								<span class="ef-passport-photo-drop__icon" aria-hidden="true">＋</span>
								<span data-photo-label><?php esc_html_e( 'Add a photo', 'eatforeign' ); ?></span>
								<input type="file" name="celebration_image" accept="image/*" data-photo-input />
							</label>
						</div>
						<p class="ef-participate__fineprint">
							<?php esc_html_e( 'Or paste an image link if you already uploaded elsewhere:', 'eatforeign' ); ?>
						</p>
						<input
							type="url"
							name="image_url"
							placeholder="https://"
							inputmode="url"
							autocomplete="off"
						/>
					</div>

					<label class="ef-field ef-field--optional">
						<span><?php esc_html_e( 'Where did you eat?', 'eatforeign' ); ?> <em><?php esc_html_e( 'optional', 'eatforeign' ); ?></em></span>
						<input
							type="text"
							name="restaurant_name"
							placeholder="<?php esc_attr_e( 'Restaurant, market stall, home kitchen…', 'eatforeign' ); ?>"
							autocomplete="organization"
						/>
					</label>

					<label class="ef-field ef-field--checkbox ef-field--optional">
						<input type="checkbox" name="first_time_trying" value="1" />
						<span><?php esc_html_e( 'First time trying this — worth celebrating!', 'eatforeign' ); ?></span>
					</label>
				</fieldset>

				<button type="submit" class="ef-button ef-button--primary ef-button--block">
					<?php esc_html_e( 'Post to community feed', 'eatforeign' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>
</div>
