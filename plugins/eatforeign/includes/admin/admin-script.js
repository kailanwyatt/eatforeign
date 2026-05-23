jQuery(document).ready(function($) {
	var ATTRIBUTION_FIELD_IDS = {
		sourceName: '#ef_featured_image_attribution_source_name',
		author: '#ef_featured_image_attribution_author',
		license: '#ef_featured_image_attribution_license',
		licenseUrl: '#ef_featured_image_attribution_license_url',
		creditPageUrl: '#ef_featured_image_attribution_credit_page_url'
	};

	function parseAttribution(raw) {
		if (!raw) {
			return null;
		}
		if (typeof raw === 'object') {
			return raw;
		}
		try {
			return JSON.parse(raw);
		} catch (error) {
			return null;
		}
	}

	function fillFeaturedAttributionFields(attribution) {
		var record = parseAttribution(attribution);
		if (!record) {
			return;
		}

		if (record.sourceType === 'ai-generated') {
			$(ATTRIBUTION_FIELD_IDS.sourceName).val('');
			$(ATTRIBUTION_FIELD_IDS.author).val('');
			$(ATTRIBUTION_FIELD_IDS.license).val('AI generated');
		} else {
			$(ATTRIBUTION_FIELD_IDS.sourceName).val(record.sourceName || record.source_name || '');
			$(ATTRIBUTION_FIELD_IDS.author).val(record.author || '');
			$(ATTRIBUTION_FIELD_IDS.license).val(record.license || '');
		}
		$(ATTRIBUTION_FIELD_IDS.licenseUrl).val(record.licenseUrl || record.license_url || '');
		$(ATTRIBUTION_FIELD_IDS.creditPageUrl).val(record.creditPageUrl || record.credit_page_url || '');

		$('#ef-featured-attribution-fields').addClass('ef-attribution-fields--filled');
	}

	function updateFeaturedAttributionPreview(payload) {
		if (!payload) {
			return;
		}

		var caption = payload.caption || payload.creditLine || '';
		var $preview = $('#ef-featured-attribution-preview');
		if (caption) {
			$preview.text(caption).show();
			if (payload.isAiGenerated) {
				$preview.addClass('ef-image-credit--ai');
			} else {
				$preview.removeClass('ef-image-credit--ai');
			}
			$('#ef-featured-attribution-empty').hide();
		}

		var creditPageUrl = payload.attribution
			? (payload.attribution.creditPageUrl || payload.attribution.credit_page_url || '')
			: '';
		var $linkWrap = $('#ef-featured-attribution-source-wrap');
		var $link = $('#ef-featured-attribution-source-link');
		if (creditPageUrl) {
			$link.attr('href', creditPageUrl);
			$linkWrap.show();
		} else {
			$linkWrap.hide();
		}
	}

	function applyFeaturedAttribution(payload) {
		if (!payload || !payload.attribution) {
			return;
		}
		fillFeaturedAttributionFields(payload.attribution);
		updateFeaturedAttributionPreview(payload);
	}

	function bindFeaturedImagePicker() {
		if (typeof wp === 'undefined' || !wp.media || !wp.media.featuredImage) {
			return;
		}

		var featuredImage = wp.media.featuredImage;
		var frame = featuredImage.frame();
		if (!frame) {
			return;
		}

		frame.off('select.eatforeignAttribution').on('select.eatforeignAttribution', function() {
			var attachmentId = featuredImage.get('id');
			var postId = $('#post_ID').val();
			if (!attachmentId || !postId) {
				return;
			}

			$.post(ajaxurl, {
				action: 'eatforeign_get_featured_attribution',
				post_id: postId,
				attachment_id: attachmentId,
				nonce: eatforeign_admin.nonce
			}, function(response) {
				if (response.success) {
					applyFeaturedAttribution(response.data);
				}
			});
		});
	}

	bindFeaturedImagePicker();

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
					'<p class="ef-image-lightbox__credit"></p>' +
					'<p class="ef-image-lightbox__hint">Esc to close · Arrow keys to browse</p>' +
					'</div>'
				);
				$('body').append(this.$el);
			}

			this.$img = this.$el.find('.ef-image-lightbox__img');
			this.$credit = this.$el.find('.ef-image-lightbox__credit');
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

			var $card = $thumb.closest('.ef-image-sideload-card');
			var credit = $card.find('.ef-image-credit').text() || '';
			this.$credit.text(credit).toggle(!!credit);

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

	function buildImageCard(url, postId, attribution) {
		var card = $('<div></div>').addClass('ef-image-sideload-card');
		var $img = $('<img>')
			.addClass('ef-image-lightbox-thumb')
			.attr('src', url)
			.attr('data-full-url', url)
			.attr('alt', 'Click to preview')
			.attr('title', 'Click to preview');
		card.append($img);
		if (attribution) {
			var caption = attribution.caption || attribution.creditLine || '';
			if (attribution.sourceType === 'ai-generated') {
				caption = 'AI generated';
			}
			if (caption) {
				var creditClass = 'ef-image-credit description';
				if (attribution.sourceType === 'ai-generated') {
					creditClass += ' ef-image-credit--ai';
				}
				card.append($('<p></p>').addClass(creditClass).text(caption));
			}
		}
		card.append(
			$('<button type="button" class="button ef-sideload-btn">')
				.text('Set as Featured')
				.attr('data-url', url)
				.attr('data-post-id', postId)
				.attr('data-attribution', attribution ? JSON.stringify(attribution) : '')
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
		var pendingAttribution = parseAttribution($btn.attr('data-attribution'));

		if (pendingAttribution) {
			fillFeaturedAttributionFields(pendingAttribution);
			updateFeaturedAttributionPreview({
				attribution: pendingAttribution,
				caption: pendingAttribution.caption || pendingAttribution.creditLine || '',
				creditLine: pendingAttribution.creditLine || '',
				isAiGenerated: pendingAttribution.sourceType === 'ai-generated'
			});
		}

		$btn.text('Downloading...').prop('disabled', true);

		$.post(ajaxurl, {
			action: 'eatforeign_sideload_image',
			image_url: url,
			post_id: postId,
			attribution: $btn.attr('data-attribution') || '',
			nonce: eatforeign_admin.nonce
		}, function(response) {
			if (response.success) {
				$btn.text('Success!');
				$btn.css('background-color', '#46b450').css('color', '#fff');
				if (response.data && response.data.thumbnail_html) {
					$('#postimagediv .inside').html(response.data.thumbnail_html);
				}
				applyFeaturedAttribution(response.data);
			} else {
				$btn.text('Failed').prop('disabled', false);
				alert(response.data || 'Failed to download image.');
			}
		}).fail(function() {
			$btn.text('Error').prop('disabled', false);
			alert('Server error occurred.');
		});
	});

	var aiAttributionPayload = {
		sourceType: 'ai-generated',
		license: 'AI generated',
		caption: 'AI generated',
		creditLine: 'AI generated'
	};

	function generateDishImage(provider, $btn) {
		var postId = $btn.data('post-id');
		var $status = $('#ef-ai-generate-status');
		var $grid = $('#ef-ai-images-grid');
		var providerLabel = provider === 'gemini' ? 'Gemini' : 'OpenAI';

		$('.ef-generate-dish-image').prop('disabled', true);
		$status.text('Generating with ' + providerLabel + '… this may take up to a minute.');

		$.post(ajaxurl, {
			action: 'eatforeign_generate_dish_image',
			post_id: postId,
			provider: provider,
			nonce: eatforeign_admin.generate_nonce
		}, function(response) {
			$('.ef-generate-dish-image').each(function() {
				var $el = $(this);
				var enabled = $el.data('provider') === 'openai'
					? eatforeign_admin.has_openai_key
					: eatforeign_admin.has_gemini_key;
				$el.prop('disabled', !enabled);
			});
			if (response.success && response.data && response.data.imageUrl) {
				$status.text(
					'Image generated with ' + providerLabel + '. Click thumbnail to preview, or Set as Featured to use it.'
				);
				appendImageCard($grid, response.data.imageUrl, postId, aiAttributionPayload);
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
			$('.ef-generate-dish-image').each(function() {
				var $el = $(this);
				var enabled = $el.data('provider') === 'openai'
					? eatforeign_admin.has_openai_key
					: eatforeign_admin.has_gemini_key;
				$el.prop('disabled', !enabled);
			});
			$status.text('');
			alert('Server error occurred while generating the image.');
		});
	}

	$(document).on('click', '.ef-generate-dish-image', function(e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}
		generateDishImage($btn.data('provider') || 'openai', $btn);
	});
});
