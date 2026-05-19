jQuery(document).ready(function($) {
	var lightbox = {
		$el: null,
		$img: null,
		$prev: null,
		$next: null,
		$sources: $(),
		index: 0,

		init: function() {
			if ($('#ef-image-lightbox').length) {
				this.$el = $('#ef-image-lightbox');
			} else {
				this.$el = $(
					'<div id="ef-image-lightbox" class="ef-image-lightbox" role="dialog" aria-modal="true" aria-label="Image preview">' +
					'<div class="ef-image-lightbox__backdrop"></div>' +
					'<button type="button" class="ef-image-lightbox__close" aria-label="Close">&times;</button>' +
					'<button type="button" class="ef-image-lightbox__prev is-hidden" aria-label="Previous image">&#8249;</button>' +
					'<button type="button" class="ef-image-lightbox__next is-hidden" aria-label="Next image">&#8250;</button>' +
					'<figure class="ef-image-lightbox__figure"><img class="ef-image-lightbox__img" src="" alt="Preview" /></figure>' +
					'<p class="ef-image-lightbox__hint">Esc to close · Arrow keys to browse</p>' +
					'</div>'
				);
				$('body').append(this.$el);
			}

			this.$img = this.$el.find('.ef-image-lightbox__img');
			this.$prev = this.$el.find('.ef-image-lightbox__prev');
			this.$next = this.$el.find('.ef-image-lightbox__next');

			var self = this;
			$(document).on('click', '.ef-image-lightbox-thumb', function(e) {
				e.preventDefault();
				var $thumb = $(this);
				var $grid = $thumb.closest('#ef-ai-images-grid, .ef-suggested-images-grid');
				if (!$grid.length) {
					$grid = $thumb.closest('.ef-image-sideload-card').parent();
				}
				self.open($thumb.data('full-url') || $thumb.attr('src'), $grid, $thumb);
			});

			this.$el.find('.ef-image-lightbox__backdrop, .ef-image-lightbox__close').on('click', function() {
				self.close();
			});

			this.$prev.on('click', function(e) {
				e.stopPropagation();
				self.step(-1);
			});

			this.$next.on('click', function(e) {
				e.stopPropagation();
				self.step(1);
			});

			$(document).on('keydown', function(e) {
				if (!self.$el.hasClass('is-open')) {
					return;
				}
				if (e.key === 'Escape') {
					self.close();
				} else if (e.key === 'ArrowLeft') {
					self.step(-1);
				} else if (e.key === 'ArrowRight') {
					self.step(1);
				}
			});
		},

		collectSources: function($grid, $currentThumb) {
			var $thumbs = $grid.find('.ef-image-lightbox-thumb');
			this.$sources = $thumbs;
			this.index = $thumbs.index($currentThumb);
			if (this.index < 0) {
				this.index = 0;
			}
		},

		open: function(url, $grid, $thumb) {
			this.collectSources($grid, $thumb);
			this.showAt(this.index);
			this.$el.addClass('is-open');
			$('body').css('overflow', 'hidden');
		},

		close: function() {
			this.$el.removeClass('is-open');
			$('body').css('overflow', '');
			this.$img.attr('src', '');
		},

		showAt: function(index) {
			if (!this.$sources.length) {
				return;
			}
			this.index = (index + this.$sources.length) % this.$sources.length;
			var $thumb = this.$sources.eq(this.index);
			var url = $thumb.data('full-url') || $thumb.attr('src');
			this.$img.attr('src', url);

			var multi = this.$sources.length > 1;
			this.$prev.toggleClass('is-hidden', !multi);
			this.$next.toggleClass('is-hidden', !multi);
		},

		step: function(delta) {
			if (this.$sources.length <= 1) {
				return;
			}
			this.showAt(this.index + delta);
		}
	};

	lightbox.init();

	function buildImageCard(url, postId) {
		var card = $('<div></div>').addClass('ef-image-sideload-card');
		var $img = $('<img>')
			.addClass('ef-image-lightbox-thumb')
			.attr('src', url)
			.attr('data-full-url', url)
			.attr('alt', 'Click to preview')
			.attr('title', 'Click to preview');
		card.append($img);
		card.append(
			$('<button type="button" class="button ef-sideload-btn">')
				.text('Set as Featured')
				.attr('data-url', url)
				.attr('data-post-id', postId)
		);
		return card;
	}

	function appendImageCard($grid, url, postId) {
		$grid.append(buildImageCard(url, postId));
	}

	$(document).on('click', '.ef-sideload-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var url = $btn.data('url');
		var postId = $btn.data('post-id');

		$btn.text('Downloading...').prop('disabled', true);

		$.post(ajaxurl, {
			action: 'eatforeign_sideload_image',
			image_url: url,
			post_id: postId,
			nonce: eatforeign_admin.nonce
		}, function(response) {
			if (response.success) {
				$btn.text('Success!');
				$btn.css('background-color', '#46b450').css('color', '#fff');
				if (response.data && response.data.thumbnail_html) {
					$('#postimagediv .inside').html(response.data.thumbnail_html);
				}
			} else {
				$btn.text('Failed').prop('disabled', false);
				alert(response.data || 'Failed to download image.');
			}
		}).fail(function() {
			$btn.text('Error').prop('disabled', false);
			alert('Server error occurred.');
		});
	});

	$('#ef-generate-ai-image').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var postId = $btn.data('post-id');
		var $status = $('#ef-ai-generate-status');
		var $grid = $('#ef-ai-images-grid');

		$btn.prop('disabled', true);
		$status.text('Generating with OpenAI… this may take up to a minute.');

		$.post(ajaxurl, {
			action: 'eatforeign_generate_dish_image',
			post_id: postId,
			nonce: eatforeign_admin.generate_nonce
		}, function(response) {
			$btn.prop('disabled', false);
			if (response.success && response.data && response.data.imageUrl) {
				$status.text('Image generated. Click thumbnail to preview, or Set as Featured to use it.');
				appendImageCard($grid, response.data.imageUrl, postId);
			} else {
				$status.text('');
				var message = 'Failed to generate image.';
				if (typeof response.data === 'string' && response.data) {
					message = response.data;
				} else if (response.data && response.data.message) {
					message = response.data.message;
				}
				alert(message);
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$status.text('');
			alert('Server error occurred while generating the image.');
		});
	});
});
