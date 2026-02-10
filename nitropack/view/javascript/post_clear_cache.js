(function ($) {
	"use strict";

	// Constants
	const CACHE_TYPES = {
		PURGE: "purge",
		INVALIDATE: "invalidate",
	};

	const AJAX_ACTIONS = {
		purge: "nitropack_purge_single_cache",
		invalidate: "nitropack_invalidate_single_cache",
	};

	const ICONS = {
		loading: "/view/images/loading.svg",
		success: "/view/images/check.svg",
		error: "/view/images/x-mark.svg",
	};

	const SELECTORS = {
		purgeButton: ".nitropack-purge-single",
		invalidateButton: ".nitropack-invalidate-single",
		metaBox: "#nitropack_manage_cache_box",
		loadingIcon: ".icon.loading",
	};

	const UI_TIMEOUT_CLEAN = 3000;

	// Module state
	let statusHideTimeout = null;

	/**
	 * Initialize the cache management functionality
	 */
	function init() {
		// Use event delegation for better performance
		$(document).on("click", SELECTORS.purgeButton, handlePurgeClick);
		$(document).on("click", SELECTORS.invalidateButton, handleInvalidateClick);
	}

	/**
	 * Handle click on purge cache button
	 */
	function handlePurgeClick(event) {
		event.preventDefault();
		const $button = $(this);
		const postId = $button.data("post_id");
		const postUrl = $button.data("post_url");

		cleanSingleCache(postId, postUrl, CACHE_TYPES.PURGE, $button);
	}

	/**
	 * Handle click on invalidate cache button
	 */
	function handleInvalidateClick(event) {
		event.preventDefault();
		const $button = $(this);
		const postId = $button.data("post_id");
		const postUrl = $button.data("post_url");

		cleanSingleCache(postId, postUrl, CACHE_TYPES.INVALIDATE, $button);
	}

	/**
	 * Create loading icon HTML
	 * @returns {string} HTML for loading icon
	 */
	function createLoadingIcon() {
		const iconUrl = np_post_clear_cache.nitro_plugin_url + ICONS.loading;
		return `<img src="${iconUrl}" width="14" class="icon loading" style="vertical-align: middle; margin-left: .25rem;" alt="Loading..."/>`;
	}

	/**
	 * Check if we are on a single post page (metabox context)
	 * @returns {boolean}
	 */
	function isSinglePostPage() {
		return $(SELECTORS.metaBox).length > 0;
	}

	/**
	 * Show loading state in UI
	 * @param {jQuery} $button - The button element that was clicked
	 */
	function showLoadingState($button) {
		const loadingIcon = createLoadingIcon();
		$button.css("pointer-events", "none");
		$button.attr("disabled", true);

		// Show loading icon based on context
		if (!isSinglePostPage()) {
			// Listing page - show icon next to button
			$(loadingIcon).insertAfter($button);
		} else {
			$button.append(loadingIcon);
		}
	}

	/**
	 * Show success state in UI
	 * @param {jQuery} $button - The button element that was clicked
	 */
	function showSuccessState($button) {
		if (!isSinglePostPage()) {
			const successIconUrl = np_post_clear_cache.nitro_plugin_url + ICONS.success;
			$button.next(".icon").attr("src", successIconUrl);
		} else {
			$button.find(".icon").attr("src", np_post_clear_cache.nitro_plugin_url + ICONS.success);
		}
	}

	/**
	 * Show error state in UI
	 * @param {jQuery} $button - The button element that was clicked
	 */
	function showErrorState($button) {
		if (!isSinglePostPage()) {
			const errorIconUrl = np_post_clear_cache.nitro_plugin_url + ICONS.error;
			$button.next(".icon").attr("src", errorIconUrl);
		} else {
			$button.find(".icon").attr("src", np_post_clear_cache.nitro_plugin_url + ICONS.error);
		}
	}

	/**
	 * Clean up UI after operation completes
	 * @param {jQuery} $button - The button element that was clicked
	 */
	function cleanupUI($button) {
		// Clear any existing timeout
		if (statusHideTimeout) {
			clearTimeout(statusHideTimeout);
		}

		// Schedule status message hide and cleanup
		statusHideTimeout = setTimeout(() => {
			$(SELECTORS.loadingIcon).remove();
		}, UI_TIMEOUT_CLEAN);
		$button.css("pointer-events", "auto");
		// Re-enable button
		$button.attr("disabled", false);
	}

	/**
	 * Perform cache clean operation via AJAX
	 * @param {number} postId - The post ID
	 * @param {string|array} postUrl - The post URL(s)
	 * @param {string} type - Cache operation type (purge or invalidate)
	 * @param {jQuery} $button - The button element that was clicked
	 */
	function cleanSingleCache(postId, postUrl, type, $button) {
		// Validate input
		if (!postId) {
			console.error("NitroPack: Invalid post ID");
			return;
		}

		// Normalize postUrl
		postUrl = postUrl || [];

		// Get appropriate AJAX action
		const action = AJAX_ACTIONS[type];
		if (!action) {
			console.error("NitroPack: Invalid cache type:", type);
			return;
		}

		// Show loading state
		showLoadingState($button);

		// Perform AJAX request
		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: action,
				postId: postId,
				postUrl: postUrl,
				nonce: np_post_clear_cache.nitroNonce,
			},
		})
			.done(() => {
				showSuccessState($button);
			})
			.fail((jqXHR, textStatus, errorThrown) => {
				console.error("NitroPack: Cache operation failed:", textStatus, errorThrown);
				showErrorState($button);
			})
			.always(() => {
				cleanupUI($button);
			});
	}

	// Initialize when DOM is ready
	$(document).ready(init);
})(jQuery);
