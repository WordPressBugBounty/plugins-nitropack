jQuery(document).ready(function ($) {
	class nitropackSettings {
		constructor() {
			this.initial_settings = {
				ajaxShortcodes: {
					enabled: 0,
					shortcodes: [],
				},
				cacheWarmUp: {
					enabled: 0,
				},
				htmlCompression: {
					enabled: 0,
				},
				bbCachePurgeSync: {
					enabled: 0,
				},
				canEditorClearCache: {
					enabled: 0,
				},
				cartCache: {
					enabled: 0,
				},
				stockReduce: {
					enabled: 0,
				},
				optimizationLevel: {
					int: 0,
					name: "",
				},
			};
			//Settings
			this.nitropackAddEventListeners();
			this.purgeCacheClick();
			this.optimizations();
			this.optimizationModeClick();
			this.autoPurgeCache();
			this.cacheWarmUp();
			this.enableCacheWarmup();
			this.skipCacheWarmup();
			this.setHTMLCompression();
			this.beaverBuilder();
			this.editorPurgeCache();
			this.cartCache();
			this.stockRefresh();
			//shortcodes
			this.ajaxShortcodes = this.ajaxShortcodes();
			this.restoreConnection();
			this.windowNotification();
			this.clearResidualCache();
			//unsaved changes
			this.onPageLeave();
			//must be last so we get updated copy of inital settings after all other settings init
			this.unsavedChangesModal = false;
			this.modified_settings = JSON.parse(JSON.stringify(this.initial_settings));
		}
		setupCacheEventListeners(nitroSelf) {
			window.addEventListener("cache.invalidate.request", nitroSelf.clearCacheHandler("invalidate"));
			window.addEventListener("cache.purge.request", nitroSelf.clearCacheHandler("purge"));
			if ($("#np-onstate-cache-purge").length) {
				window.addEventListener("cache.purge.success", function () {
					$.post(
						ajaxurl,
						{
							action: "nitropack_cookie_path_ajax",
							nonce: np_settings.nitroNonce,
						},
						function (response) {
							var resp = JSON.parse(response);
							setTimeout(function () {
								document.cookie =
									"nitropack_apwarning=1; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=" + resp.cookie_path + ";";
								window.location.reload();
							}, 1500);
						},
					);
				});
			} else {
				window.addEventListener("cache.purge.success", () => setTimeout(() => nitroSelf.fetchOptimizations(), 1500));
			}
			window.addEventListener("cache.invalidate.success", () => setTimeout(() => nitroSelf.fetchOptimizations(), 1500));
		}
		nitropackAddEventListeners() {
			const nitroSelf = this;

			// Check if document is already complete
			if (document.readyState === "complete") {
				// Page already loaded - run immediately
				this.setupCacheEventListeners(nitroSelf);
			} else {
				// Page still loading - wait for load event
				window.addEventListener(
					"load",
					() => {
						this.setupCacheEventListeners(nitroSelf);
					},
					{ once: true },
				);
			}
		}
		/* AJAX purge/invalidate cache, used in nitropack/classes/WordPress/PurgeCache.php */
		clearCacheHandler = (clearCacheAction) => {
			return function (success, error) {
				$.ajax({
					url: ajaxurl,
					type: "GET",
					data: {
						action: "nitropack_" + clearCacheAction + "_cache",
						nonce: nitroNonce,
					},
					dataType: "json",
					beforeSend: function () {
						$("#optimizations-purge-cache").attr("disabled", true);
					},
					success: function (data) {
						if (data.type === "success") {
							NitropackUI.triggerToast("success", data.message);
							window.dispatchEvent(new Event("cache." + clearCacheAction + ".success"));
						} else {
							NitropackUI.triggerToast("error", data.message);
							window.dispatchEvent(new Event("cache." + clearCacheAction + ".error"));
						}
					},
					error: function (data) {
						NitropackUI.triggerToast("error", data.message);
						window.dispatchEvent(new Event("cache." + clearCacheAction + ".error"));
					},
					complete: function () {
						setTimeout(function () {
							$("#optimizations-purge-cache").attr("disabled", false);
						}, 3000);
					},
				});
			};
		};
		purgeCacheClick() {
			const nitroSelf = this;
			$("#modal-purge-cache .modal-action").click(function (e) {
				let purgeEvent = new Event("cache.purge.request");
				window.dispatchEvent(purgeEvent);
			});
		}
		/* Fetch optimizations data every 2 minutes in Dashboard => Optimized pages */
		fetchOptimizations() {
			$.post(
				ajaxurl,
				{
					action: "nitropack_fetch_optimizations",
					nonce: np_settings.nitroNonce,
				},
				function (resp) {
					$("[data-last-cache-purge]").text(resp.data.last_cache_purge.timeAgo);
					if (resp.data.last_cache_purge.reason) {
						$("[data-purge-reason]").text(resp.data.last_cache_purge.reason);
						$("[data-purge-reason]").attr("title", resp.data.last_cache_purge.reason);
						$("#last-cache-purge-reason").show();
					} else {
						$("#last-cache-purge-reason").hide();
					}
					if (resp.data.pending_count) {
						$("#pending-optimizations-count").text(resp.data.pending_count);
						$("#pending-optimizations-section").show();
					} else {
						$("#pending-optimizations-section").hide();
					}
					$("[data-optimized-pages-total]").text(resp.data.optimized_pages.total);
				},
			);
		}
		optimizations() {
			// Run every 120 seconds, starting after 120 seconds
			setInterval(this.fetchOptimizations, 120000);
		}
		saveOptimizationMode = (mode_int, mode_name) => {
			const nitroSelf = this;
			$.post(
				ajaxurl,
				{
					action: "nitropack_set_optimization_mode",
					nonce: np_settings.nitroNonce,
					mode_int,
					mode_name,
				},
				function (response) {
					var resp = JSON.parse(response);
					if (resp.type == "success") {
						nitroSelf.applyOptimizationCosmetics(mode_name);
						NitropackUI.triggerToast(
							"info",
							'Optimization mode changed to <strong class="capitalized">' + mode_name + "</strong>.",
						);
					} else {
						NitropackUI.triggerToast("error", resp.message);
					}
				},
			);
		};
		applyOptimizationCosmetics(mode) {
			const modes_btn = "#optimization-modes a";

			$(modes_btn).removeClass("btn-primary active").addClass("btn-link");
			$(modes_btn + '[data-mode="' + mode + '"]')
				.addClass("btn-primary active")
				.removeClass("btn-link");
			$(".active-mode").text(mode);

			$(".card-optimization-mode .tab-content").addClass("hidden");
			$('.card-optimization-mode .tab-content[data-tab="' + mode + '-tab"].hidden').removeClass("hidden");
		}
		optimizationModeClick() {
			const nitroSelf = this,
				modal_wrapper = $("#modal-optimization-mode"),
				modal_footer = modal_wrapper.find(".popup-footer"),
				action_btn = modal_footer.find(".modal-action"),
				modes_btn = "#optimization-modes a";

			$(modes_btn).click(function () {
				var mode_name = $(this).data("mode");
				action_btn.data("mode", mode_name);
			});
			action_btn.click(function () {
				var mode_name = $(this).data("mode"),
					mode_int = $(modes_btn + '[data-mode="' + mode_name + '"]').index() + 1;
				nitroSelf.saveOptimizationMode(mode_int, mode_name);
			});
			this.loadInitOptimizationMode();
		}
		loadInitOptimizationMode() {
			const mode = $("#optimization-modes a.active").data("mode"),
				mode_int = $("#optimization-modes a.active").index() + 1;
			this.initial_settings.optimizationLevel.int = mode_int;
			this.initial_settings.optimizationLevel.name = mode;
		}
		autoPurgeCache() {
			$("#auto-purge-status").on("click", function (e) {
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_auto_cache_purge_ajax",
						nonce: nitroNonce,
						autoCachePurgeStatus: $(this).is(":checked") ? 1 : 0,
					},
					function (response) {
						var resp = JSON.parse(response);
						NitropackUI.triggerToast(resp.type, resp.message);
					},
				);
			});
		}

		cacheWarmUp() {
			const setting_id = "#warmup-status",
				msg_wrapper = $("#loading-warmup-status"),
				msg_icon = msg_wrapper.find(".icon"),
				msg_text = msg_wrapper.find(".msg"),
				nitroSelf = this;

			$(setting_id).change(function () {
				if ($(this).is(":checked")) {
					estimateWarmup();
				} else {
					disableWarmup();
				}
			});
			var disableWarmup = () => {
				$.post(
					ajaxurl,
					{
						action: "nitropack_disable_warmup",
						nonce: np_settings.nitroNonce,
					},
					function (response) {
						var resp = JSON.parse(response);
						if (resp.type == "success") {
							nitroSelf.modified_settings.cacheWarmUp.enabled = 0;
							NitropackUI.triggerToast("success", np_settings.success_msg);
						} else {
							NitropackUI.triggerToast("error", np_settings.error_msg);
						}
					},
				);
			};

			var estimateWarmup = (id, retry) => {
				id = id || null;
				retry = retry || 0;
				msg_wrapper.removeClass("hidden");
				if (!id) {
					msg_text.text(np_settings.est_cachewarmup_msg);
					$.post(
						ajaxurl,
						{
							action: "nitropack_estimate_warmup",
							nonce: np_settings.nitroNonce,
						},
						function (response) {
							var resp = JSON.parse(response);
							if (resp.type == "success") {
								setTimeout(
									(function (id) {
										estimateWarmup(id);
									})(resp.res),
									1000,
								);
							} else {
								$(setting_id).prop("checked", true);
								msg_text.text(resp.message);

								msg_icon.attr("src", np_settings.nitro_plugin_url + "/view/images/info.svg");
								setTimeout(function () {
									msg_wrapper.addClass("hidden");
								}, 3000);
							}
						},
					);
				} else {
					$.post(
						ajaxurl,
						{
							action: "nitropack_estimate_warmup",
							estId: id,
							nonce: np_settings.nitroNonce,
						},
						function (response) {
							var resp = JSON.parse(response);
							if (resp.type == "success") {
								if (isNaN(resp.res) || resp.res == -1) {
									// Still calculating
									if (retry >= 10) {
										$(setting_id).prop("checked", false);
										msg_icon.attr("src", np_settings.nitro_plugin_url + "/view/images/info.svg");
										msg_text.text(resp.message);

										setTimeout(function () {
											msg_wrapper.addClass("hidden");
										}, 3000);
									} else {
										setTimeout(
											(function (id, retry) {
												estimateWarmup(id, retry);
											})(id, retry + 1),
											1000,
										);
									}
								} else {
									if (resp.res == 0) {
										$(setting_id).prop("checked", false);
										msg_icon.attr("src", np_settings.nitro_plugin_url + "/view/images/info.svg");
										msg_text.text(resp.message);
										setTimeout(function () {
											msg_wrapper.addClass("hidden");
										}, 3000);
									} else {
										enableWarmup();
									}
								}
							} else {
								msg_text.text(resp.message);
								setTimeout(function () {
									msg_wrapper.addClass("hidden");
								}, 3000);
							}
						},
					);
				}
			};
			var enableWarmup = () => {
				$.post(
					ajaxurl,
					{
						action: "nitropack_enable_warmup",
						nonce: np_settings.nitroNonce,
					},
					function (response) {
						var resp = JSON.parse(response);
						if (resp.type == "success") {
							nitroSelf.initial_settings.cacheWarmUp.enabled = 1;
							$(setting_id).prop("checked", true);
							msg_wrapper.addClass("hidden");
							NitropackUI.triggerToast("success", np_settings.success_msg);
						} else {
							setTimeout(enableWarmup, 1000);
						}
					},
				);
			};

			var loadWarmupStatus = function () {
				if ($("#warmup-status").is(":checked") == 1) {
					nitroSelf.initial_settings.cacheWarmUp.enabled = 1;
				} else {
					nitroSelf.initial_settings.cacheWarmUp.enabled = 0;
				}
			};

			loadWarmupStatus();
		}
		skipCacheWarmupAjax() {
			$.post(
				ajaxurl,
				{
					action: "nitropack_skip_cache_warmup",
					nonce: np_settings.nitroNonce,
				},
				function (response) {
					var resp = JSON.parse(response);
					if (resp.type == "success") {
						$(".cache-warmup.card").remove();
					} else {
						NitropackUI.triggerToast("error", np_settings.error_msg);
					}
				},
			);
		}
		enableCacheWarmup() {
			const nitroSelf = this;
			$("#enable-cache-warmup").on("click", function () {
				//enable CW
				$("#warmup-status").prop("checked", true).trigger("change");
				//dismiss notice forever
				nitroSelf.skipCacheWarmupAjax();
			});
		}

		skipCacheWarmup() {
			const nitroSelf = this;
			$("#skip-cache-warmup").on("click", function () {
				nitroSelf.skipCacheWarmupAjax();
			});
		}
		ajaxShortcodes() {
			//main setting
			const setting_id = "#ajax-shortcodes",
				nitroSelf = this;
			if ($(setting_id).is(":checked")) {
				nitroSelf.initial_settings.ajaxShortcodes.enabled = 1;
			}

			$(setting_id).change(function () {
				if ($(this).is(":checked")) {
					ajaxShortcodeRequest(null, 1);
				} else {
					ajaxShortcodeRequest(null, 0);
				}
			});
			//template for selected shortcodes tags
			let select2 = $("#ajax-shortcodes-dropdown").select2({
					selectOnClose: false,
					tags: true,
					multiple: true,
					width: "100%",
					placeholder: "Enter a shortcode",
					templateSelection: shortcodeTagTemplate,
				}),
				shortcodes_val = select2.val();

			/* Show the container when select2 is initialized to avoid flickering! */
			if (nitroSelf.initial_settings.ajaxShortcodes.enabled) $(".ajax-shortcodes").removeClass("hidden");

			if (shortcodes_val && shortcodes_val.length > 0) {
				nitroSelf.initial_settings.ajaxShortcodes.shortcodes = select2.val();
			} else {
				nitroSelf.initial_settings.ajaxShortcodes.shortcodes = [];
			}

			select2.on("change", (event) => {
				const selectedValues = $(event.target).val(); // Get selected values
				this.modified_settings.ajaxShortcodes.shortcodes = selectedValues;
				if (selectedValues.length === 0) {
					$(".select2-search.select2-search--inline .select2-search__field").addClass("w-full");
				} else {
					$(".select2-search.select2-search--inline .select2-search__field").removeClass("w-full");
				}
			});
			$(".select2-search.select2-search--inline .select2-search__field").addClass("w-full");
			//select2
			function shortcodeTagTemplate(item) {
				if (!item.id) {
					return item.text;
				}
				var $item = $(
					'<span class="select2-selection__choice-inner">' +
						item.text +
						'<span class="np-select2-remove"></span>' +
						"</span>",
				);
				return $item;
			}
			//remove single shortcode
			$(".ajax-shortcodes").on("click", ".np-select2-remove", function () {
				let valueToRemove = $(this).closest("li.select2-selection__choice").attr("title"),
					newVals = select2.val().filter(function (item) {
						return item !== valueToRemove;
					});
				select2.val(newVals).trigger("change");
			});
			//btn save click
			$(".ajax-shortcodes #save-shortcodes").click(function () {
				let shortcodes = $("#ajax-shortcodes-dropdown").val();
				ajaxShortcodeRequest(shortcodes, null);
			});

			/* shortcodes - array of shortcodes or null
            enabled - 1 or 0
            */
			const ajaxShortcodeRequest = function (shortcodes, enabled) {
				let data_obj = {
					action: "nitropack_set_ajax_shortcodes_ajax",
					nonce: np_settings.nitroNonce,
					shortcodes: Array.isArray(shortcodes) && shortcodes.length ? shortcodes : [JSON.stringify([])], // Ensure it's always an array
				};

				if (enabled !== null) data_obj.enabled = enabled;

				const response = $.ajax({
					url: ajaxurl,
					type: "POST",
					data: data_obj,
					dataType: "json",
					success: function (resp) {
						if (resp.type == "success") {
							if (enabled == 1) {
								$(".ajax-shortcodes").removeClass("hidden");
								nitroSelf.modified_settings.ajaxShortcodes.enabled = 1;
							}
							if (enabled == 0) {
								$(".ajax-shortcodes").addClass("hidden");
								nitroSelf.modified_settings.ajaxShortcodes.enabled = 0;
							}
							// Ensure we're setting an array in settings
							if (Array.isArray(shortcodes) && shortcodes.length) {
								nitroSelf.initial_settings.ajaxShortcodes.shortcodes = shortcodes;
							} else {
								nitroSelf.initial_settings.ajaxShortcodes.shortcodes = [];
							}
							NitropackUI.triggerToast("success", np_settings.success_msg);
						} else {
							NitropackUI.triggerToast("error", np_settings.error_msg);
						}
					},
				});
				return response;
			};
			return {
				ajaxShortcodeRequest: ajaxShortcodeRequest,
			};
		}
		// Function to omit 'enabled' property
		omitEnabledProperty(obj) {
			return Object.keys(obj).reduce((acc, key) => {
				if (typeof obj[key] === "object" && obj[key] !== null) {
					acc[key] = this.omitEnabledProperty(obj[key]);
				} else if (key !== "enabled") {
					acc[key] = obj[key];
				}
				return acc;
			}, {});
		}

		// Function to check for unsaved changes, ignoring 'enabled' property
		hasUnsavedChanges() {
			const initialWithoutEnabled = this.omitEnabledProperty(this.initial_settings);
			const modifiedWithoutEnabled = this.omitEnabledProperty(this.modified_settings);
			return JSON.stringify(initialWithoutEnabled) !== JSON.stringify(modifiedWithoutEnabled);
		}

		// Function to handle page leave
		onPageLeave() {
			const nitroSelf = this;
			window.onbeforeunload = function (event) {
				if (
					nitroSelf.hasUnsavedChanges() &&
					!nitroSelf.unsavedChangesModal &&
					nitroSelf.modified_settings.ajaxShortcodes.enabled === 1
				) {
					event.preventDefault(); // show prompt
				}
			};
			//a links - display modal
			$(document).on("click", 'a[href]:not([target="_blank"])', function (event) {
				if (nitroSelf.hasUnsavedChanges() && nitroSelf.modified_settings.ajaxShortcodes.enabled === 1) {
					event.preventDefault();
					const leaveUrl = this.href;
					nitroSelf.showUnsavedChangesModal(() => {
						``;
						window.location.href = leaveUrl;
					});
				}
			});
		}
		// Show unsaved changes modal
		showUnsavedChangesModal(onConfirm) {
			const nitroSelf = this;
			//vanilla js
			const modalID = "modal-unsavedChanges",
				$modal_target = document.getElementById(modalID),
				modal_options = {
					backdrop: "static",
					backdropClasses: "nitro-backdrop",
					closable: true,
					onHide: () => {
						this.unsavedChangesModal = false;
					},
					onShow: () => {
						this.unsavedChangesModal = true;
					},
				},
				instanceOptions = {
					id: modalID,
				},
				modal = new Modal($modal_target, modal_options, instanceOptions);
			//jquery
			const modal_wrapper = $("#" + modalID),
				x_button = modal_wrapper.find(".close-modal"),
				modal_footer = modal_wrapper.find(".popup-footer"),
				secondary_btn = modal_footer.find(".popup-close"),
				action_btn = modal_footer.find(".btn-primary");
			modal.show();

			//no action
			$(x_button).one("click", function () {
				modal.hide();
			});
			//redirect without saving
			$(secondary_btn).one("click", function () {
				onConfirm();
				modal.hide();
			});
			//save and redirect
			$(action_btn).one("click", function () {
				const ajaxRequest = nitroSelf.ajaxShortcodes.ajaxShortcodeRequest(
					nitroSelf.modified_settings.ajaxShortcodes.shortcodes,
					null,
				);
				ajaxRequest.done(function (response) {
					if (response.type === "success") onConfirm();
				});
				ajaxRequest.fail(function () {
					console.error("AJAX request failed.");
					NitropackUI.triggerToast("error", "Error saving shortcodes.");
					onConfirm();
				});
				modal.hide();
			});
		}
		removeElement(array, value) {
			const index = array.indexOf(value);
			if (index !== -1) {
				array.splice(index, 1);
			}
		}
		autoDetectCompression() {
			let msg_container = $("#compression-widget .msg-container"),
				msg_box = msg_container.find(".msg"),
				compression_setting = $("#compression-status"),
				compression_btn = $("#compression-test-btn");
			//add spinner here
			msg_box.html(
				'<img src="' +
					np_settings.nitro_plugin_url +
					'/view/images/loading.svg" alt="loading" class="icon"> ' +
					np_settings.testing_compression,
			);
			compression_btn.addClass("hidden");
			msg_container.removeClass("hidden");
			$.post(
				ajaxurl,
				{
					action: "nitropack_test_compression_ajax",
					nonce: nitroNonce,
				},
				function (response) {
					var resp = JSON.parse(response);

					if (resp.type == "success") {
						if (resp.hasCompression) {
							// compression already enabled
							compression_setting.attr("checked", false);
							compression_setting.attr("disabled", true);
							msg_box.text(np_settings.compression_already_enabled);
						} else {
							compression_setting.attr("checked", true);
							compression_setting.attr("disabled", false);
							msg_box.text(np_settings.compression_not_detected);
							NitropackUI.triggerToast(resp.type, resp.message);
						}
					} else {
						msg_box.text(np_settings.compression_not_determined);
					}
					setTimeout(function () {
						msg_container.addClass("hidden");
						compression_btn.removeClass("hidden");
					}, 5000);
				},
			);
		}
		setHTMLCompression() {
			const nitroSelf = this;
			let enabled = $("#compression-status").is(":checked") ? 1 : 0;
			nitroSelf.initial_settings.htmlCompression.enabled = enabled;
			//on load check status
			$(window).on("load", function () {
				nitroSelf.autoDetectCompression();
			});
			$(document).on("click", "#compression-test-btn", (e) => {
				e.preventDefault();
				nitroSelf.autoDetectCompression();
			});

			//toggle on/off setting
			$("#compression-status").on("click", function (e) {
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_compression_ajax",
						nonce: nitroNonce,
						data: {
							compressionStatus: $(this).is(":checked") ? 1 : 0,
						},
					},
					function (response) {
						var resp = JSON.parse(response);
						NitropackUI.triggerToast(resp.type, resp.message);
					},
				);
			});
		}
		beaverBuilder() {
			let enabled = $("#bb-purge-status").is(":checked") ? 1 : 0;
			this.initial_settings.bbCachePurgeSync.enabled = enabled;
			$("#bb-purge-status").on("click", function (e) {
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_bb_cache_purge_sync_ajax",
						nonce: nitroNonce,
						nonce: nitroNonce,
						bbCachePurgeSyncStatus: $(this).is(":checked") ? 1 : 0,
					},
					function (response) {
						var resp = JSON.parse(response);
						NitropackUI.triggerToast(resp.type, resp.message);
					},
				);
			});
		}
		editorPurgeCache() {
			let enabled = $("#can-editor-clear-cache").is(":checked") ? 1 : 0;
			this.initial_settings.canEditorClearCache.enabled = enabled;
			$("#can-editor-clear-cache").on("click", function (e) {
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_can_editor_clear_cache",
						nonce: nitroNonce,
						data: {
							canEditorClearCache: $(this).is(":checked") ? 1 : 0,
						},
					},
					function (response) {
						var resp = JSON.parse(response);
						NitropackUI.triggerToast(resp.type, resp.message);
					},
				);
			});
		}
		cartCache() {
			let enabled = $("#cart-cache-status").is(":checked") ? 1 : 0;
			this.initial_settings.cartCache.enabled = enabled;
			$("#cart-cache-status").on("click", function (e) {
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_cart_cache_ajax",
						nonce: nitroNonce,
						cartCacheStatus: $(this).is(":checked") ? 1 : 0,
					},
					function (response) {
						var resp = JSON.parse(response);
						NitropackUI.triggerToast(resp.type, resp.message);
					},
				);
			});
		}
		stockRefresh() {
			let enabled = $("#woo-stock-reduce-status").is(":checked") ? 1 : 0;
			this.initial_settings.stockReduce.enabled = enabled;

			$("#woo-stock-reduce-status").on("click", function (e) {
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_stock_reduce_status",
						nonce: nitroNonce,
						data: {
							stockReduceStatus: $(this).is(":checked") ? 1 : 0,
						},
					},
					function (response) {
						var resp = JSON.parse(response);
						NitropackUI.triggerToast(resp.type, resp.message);
					},
				);
			});
		}

		restoreConnection() {
			const loading_icon =
					'<img src="' + np_settings.nitro_plugin_url + '/view/images/loading.svg" width="14" class="icon loading"/>',
				success_icon =
					'<img src="' + np_settings.nitro_plugin_url + '/view/images/check.svg" width="16" class="icon success"/>';

			$("#nitro-restore-connection-btn").on("click", function () {
				$.ajax({
					url: ajaxurl,
					type: "GET",
					data: {
						action: "nitropack_reconfigure_webhooks",
						nonce: nitroNonce,
					},
					dataType: "json",
					beforeSend: function () {
						$("#nitro-restore-connection-btn").attr("disabled", true).html(loading_icon);
					},
					success: function (data) {
						if (!data.status || data.status != "success") {
							if (data.message) {
								alert(data.message);
							} else {
								alert(
									"We were unable to restore the connection. Please contact our support team to get this resolved.",
								);
							}
						} else {
							$("#nitro-restore-connection-btn").attr("disabled", true).html(success_icon);
							NitropackUI.triggerToast("success", data.message);
						}
					},
					complete: function () {
						location.reload();
					},
				});
			});
		}
		/* Was used in dashboard.php and oneclick.php */
		loadDismissibleNotices() {
			var $ = jQuery;

			$(".nitro-notification.is-dismissible").each(function () {
				var b = $(this),
					c = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>');
				(c.on("click.wp-dismiss-notice", function ($) {
					($.preventDefault(),
						b.fadeTo(100, 0, function () {
							b.slideUp(100, function () {
								b.remove();
							});
						}));
				}),
					b.append(c));
			});
		}

		windowNotification() {
			const nitroSelf = this;
			window.Notification = ((_) => {
				var timeout;
				var display = (msg, type) => {
					clearTimeout(timeout);
					$(".nitro-notification").remove();
					//tbd
					$('[name="form"]').prepend(
						'<div class="nitro-notification notification-' + type + '" is-dismissible"><p>' + msg + "</p></div>",
					);

					timeout = setTimeout((_) => {
						$(".nitro-notification").remove();
					}, 10000);

					nitroSelf.loadDismissibleNotices();
				};

				return {
					success: (msg) => {
						display(msg, "success");
					},
					error: (msg) => {
						display(msg, "error");
					},
					info: (msg) => {
						display(msg, "info");
					},
					warning: (msg) => {
						display(msg, "warning");
					},
				};
			})();
		}
		clearResidualCache() {
			let isClearing = false;
			$(document).on("click", ".btn[nitropack-rc-data]", function (e) {
				e.preventDefault();
				if (isClearing) return;
				let currentButton = $(this);
				$.ajax({
					url: ajaxurl,
					type: "POST",
					dataType: "text",
					data: {
						action: "nitropack_clear_residual_cache",
						gde: currentButton.attr("nitropack-rc-data"),
						nonce: nitroNonce,
					},
					beforeSend: function () {
						isClearing = true;
					},
					success: function (resp) {
						NitropackUI.triggerToast("success", np_settings.success_msg);
					},
					error: function (resp) {
						NitropackUI.triggerToast("error", np_settings.success_msg);
					},
					complete: function () {
						isClearing = false;
						setTimeout(function () {
							location.reload();
						}, 3000);
					},
				});
			});
		}
	}
	const NitroPackSettings = new nitropackSettings();
	window.NitroPackSettings = NitroPackSettings;
});
