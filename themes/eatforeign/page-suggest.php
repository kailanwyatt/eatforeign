<?php
/**
 * Suggest a dish or food holiday.
 *
 * @package EatForeignTheme
 */

declare(strict_types=1);

get_header();

$error     = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';
$submitted = isset( $_GET['submitted'] ) && sanitize_key( wp_unslash( (string) $_GET['submitted'] ) ) === '1';

$types = [
	'dish'    => __( 'Dish', 'eatforeign' ),
	'holiday' => __( 'Food holiday', 'eatforeign' ),
	'other'   => __( 'Other', 'eatforeign' ),
];
?>
<div class="ef-shell ef-stack ef-stack--suggest">
	<section class="ef-panel ef-suggest">
		<h1 class="ef-page-title"><?php esc_html_e( 'Share a suggestion', 'eatforeign' ); ?></h1>
		<p class="ef-suggest__lede ef-muted">
			<?php esc_html_e( 'Do you know a food or food holiday we should celebrate? Send us the details and we will review it.', 'eatforeign' ); ?>
		</p>

		<?php if ( $submitted ) : ?>
			<p class="ef-form-success" role="status">
				<?php esc_html_e( 'Thanks — we received your suggestion.', 'eatforeign' ); ?>
			</p>
			<p class="ef-form-footer">
				<a class="ef-button ef-button--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php esc_html_e( 'Back to Today', 'eatforeign' ); ?>
				</a>
			</p>
		<?php else : ?>
			<?php if ( $error !== '' ) : ?>
				<p class="ef-form-error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ef-form ef-suggest__form">
				<?php wp_nonce_field( 'ef_submit_suggestion' ); ?>
				<input type="hidden" name="action" value="ef_submit_suggestion" />
				<label class="ef-field">
					<span><?php esc_html_e( 'Your name', 'eatforeign' ); ?></span>
					<input type="text" name="name" autocomplete="name" value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->display_name : '' ); ?>" />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Email', 'eatforeign' ); ?></span>
					<input type="email" name="email" autocomplete="email" required value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->user_email : '' ); ?>" />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Type', 'eatforeign' ); ?></span>
					<select name="suggestion_type" required>
						<?php foreach ( $types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Title', 'eatforeign' ); ?></span>
					<input type="text" name="title" required placeholder="<?php esc_attr_e( 'e.g. National Taco Day', 'eatforeign' ); ?>" />
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Description', 'eatforeign' ); ?></span>
					<textarea name="description" rows="5" required placeholder="<?php esc_attr_e( 'What is it, where is it celebrated, and why should we add it?', 'eatforeign' ); ?>"></textarea>
				</label>
				<label class="ef-field">
					<span><?php esc_html_e( 'Source link (optional)', 'eatforeign' ); ?></span>
					<input type="url" name="source_url" placeholder="https://" />
				</label>
				<button type="submit" class="ef-button ef-button--primary">
					<?php esc_html_e( 'Send suggestion', 'eatforeign' ); ?>
				</button>
			</form>
		<?php endif; ?>
	</section>
</div>
<?php
get_footer();
