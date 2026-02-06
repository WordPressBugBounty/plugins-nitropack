jQuery(document).ready(function ($) {
	class nitropackPreview {
		constructor() {
			this.initial_settings = {
				optimizationLevel: {
					name: "strong",
					level: null,
				},
			};
			this.modals = this.initModals();
			this.getInitialModeName();
			this.init();
			this.modeSwitchClick();
			this.previewHomeButton();
			this.goLiveButton();
			this.openIntercomBubble();
		}
		initModals() {
			const modals = {
				processing_html_modal: new Modal(document.getElementById("processing-html-modal")),
				processing_html_success_modal: new Modal(document.getElementById("processing-html-success-modal")),
				processing_html_error_modal: new Modal(document.getElementById("processing-html-error-modal")),
				go_live_modal: new Modal(document.getElementById("go-live-modal")),
			};

			return modals;
		}

		init() {
			this.startPreviewPolling(this.initial_settings.optimizationLevel.name);
		}
		getInitialModeName() {
			const mode = $(".modes-container .mode.active").data("mode");
			if (!mode) {
				return;
			}
			this.initial_settings.optimizationLevel.name = mode;
			return mode;
		}
		levels() {
			return {
				1: "standard",
				2: "medium",
				3: "strong",
				4: "ludicrous",
				5: "custom",
			};
		}
		getLevelIntByName(name) {
			const levels = this.levels();
			for (const [intLevel, levelName] of Object.entries(levels)) {
				if (levelName === name) {
					return parseInt(intLevel);
				}
			}
			return null;
		}
		/* Polling function to check if the preview homepage is cached. 
		It has maximum retry time of 60 seconds and interval of 5 seconds 
		*/
		startPreviewPolling(mode_name) {
			this.showModal("processing_html_modal");

			const maxRetryTime = 60000; // 60 seconds in milliseconds
			const retryInterval = 5000; // 5 seconds between retries
			const startTime = Date.now();
			let retryCount = 0;
			const nitroSelf = this;

			const pollPreviewStatus = () => {
				const currentTime = Date.now();
				const elapsedTime = currentTime - startTime;
				retryCount++;

				$.post(
					ajaxurl,
					{
						action: "nitropack_is_homepage_preview_cached",
						nonce: nitroNonce,
						mode_name,
					},
					function (response) {
						try {
							var resp = JSON.parse(response);
							if (resp.preview === 1) {
								nitroSelf.hideModal("processing_html_modal");
								//display status modal only once in init before we set the mode
								if (!nitroSelf.initial_settings.optimizationLevel.level) {
									nitroSelf.showModal("processing_html_success_modal");
								} else {
									NitropackUI.triggerToast(
										"success",
										'Home page optimized with <strong class="capitalized">' + mode_name + "</strong> mode."
									);
								}
								nitroSelf.initial_settings.optimizationLevel.name = mode_name;
								nitroSelf.initial_settings.optimizationLevel.level = nitroSelf.getLevelIntByName(mode_name);
							} else {
								// Check if we should continue polling
								if (elapsedTime < maxRetryTime) {
									setTimeout(pollPreviewStatus, retryInterval);
								} else {
									nitroSelf.showPreviewError(mode_name);
								}
							}
						} catch (error) {
							if (elapsedTime < maxRetryTime) {
								setTimeout(pollPreviewStatus, retryInterval);
							} else {
								nitroSelf.hideModal("processing_html_modal");
								nitroSelf.showModal("processing_html_error_modal");
							}
						}
					}
				).fail(function (xhr, status, error) {
					nitroSelf.showModal("processing_html_error_modal");
					if (Date.now() - startTime < maxRetryTime) {
						setTimeout(pollPreviewStatus, retryInterval);
					} else {
						nitroSelf.hideModal("processing_html_modal");
						nitroSelf.showModal("processing_html_error_modal");
					}
				});
			};

			pollPreviewStatus();
		}

		setOptimizationMode = (mode_int, mode_name) => {
			this.saveOptimizationMode(mode_int, mode_name)
				.then((response) => {
					var resp = JSON.parse(response);
					if (resp.type === "success") {
						this.startPreviewPolling(mode_name);
					}
				})
				.catch((error) => {
					console.error("Failed to save settings:", error);
				});
		};

		saveOptimizationMode = (mode_int, mode_name) => {
			const nitroSelf = this;
			return $.post(
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
						nitroSelf.initial_settings.optimizationLevel.int = mode_int;
						nitroSelf.initial_settings.optimizationLevel.name = mode_name;
					} else {
						NitropackUI.triggerToast("error", resp.message);
					}
				}
			);
		};

		showPreviewError = (mode) => {
			this.showModal("processing_html_error_modal");

			const nitroSelf = this;
			$("#processing-html-error-modal .btn-primary")
				.off("click")
				.on("click", function () {
					nitroSelf.hideModal("processing_html_error_modal");
					nitroSelf.startPreviewPolling(mode);
				});

			this.hideModal("processing_html_modal");
		};

		ajaxPassOnboarding() {
			$.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "nitropack_passed_onboarding",
					nonce: nitroNonce,
				},
				dataType: "json",
				success: function (resp) {
					NitropackUI.triggerToast(resp.type, resp.message);
					if (resp.status === 1 && resp.redirect) {
						window.location.href = resp.redirect;
					}
				},
				error: function (xhr) {
					NitropackUI.triggerToast("error", "Something went wrong");
				},
			});
		}

		applyOptimizationCosmetics(mode) {
			$(".mode").removeClass("active");
			$(".mode:not(.disabled) .select-mode").removeClass("selected").text(np_onboarding.select_mode).prepend("");
			let active_mode_container = $(".mode").filter(`[data-mode="${mode}"]`);
			active_mode_container.addClass("active");
			active_mode_container
				.find(".select-mode")
				.addClass("selected")
				.text(np_onboarding.active_mode)
				.prepend(
					'<svg class="icon check" xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9" fill="none"><path d="M11.4674 0.792969L4.13411 8.1263L0.800781 4.79297" stroke="#4600CC" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				);
			$(".active-mode").text(mode);
		}

		modeSwitchClick() {
			const nitroSelf = this;
			const modes_btn = ".mode .select-mode";

			/* Change optimization mode when clicking on a mode button */
			$(document).on("click", modes_btn, function () {
				const mode_name = $(this).closest(".mode").data("mode"),
					mode_int = $(this).closest(".mode").index() + 1;

				const preview_url = $(".preview-home").attr("href");
				const url = new URL(preview_url);
				url.searchParams.set("previewmode", mode_name);
				$(".preview-home").attr("href", url.toString());

				nitroSelf.setOptimizationMode(mode_int, mode_name);
			});
		}
		previewHomeButton() {
			$(".preview-home").on("click", function (e) {
				const active_mode_name = $(".mode.active").data("mode");
				const existingCookie = document.cookie.split("; ").find((row) => row.startsWith("nitropack_preview_mode="));

				let modes = [];
				if (existingCookie) {
					const existingModes = existingCookie.split("=")[1];
					modes = existingModes ? existingModes.split(",") : [];
				}

				if (!modes.includes(active_mode_name)) {
					modes.push(active_mode_name);
				}

				document.cookie = `nitropack_preview_mode=${modes.join(",")}; path=/; max-age=3600`;
			});
		}
		goLiveButton() {
			const nitroSelf = this;
			$("#go-live").on("click", function (e) {
				const active_mode_name = $(".mode.active").data("mode");
				$("#go-live-modal .popup-body .active-mode").text($(".mode.active h3").text());

				const activeModeCookie = document.cookie.split("; ").find((row) => row.startsWith("nitropack_preview_mode="));

				let is_mode_previewed = false;
				if (activeModeCookie) {
					const cookieValue = activeModeCookie.split("=")[1];
					const modes = cookieValue ? cookieValue.split(",") : [];
					is_mode_previewed = modes.includes(active_mode_name);
				}

				if (is_mode_previewed) {
					nitroSelf.ajaxPassOnboarding();
				} else {
					nitroSelf.showModal("go_live_modal");
					$("#go-live-modal .close-modal")
						.off("click")
						.on("click", function () {
							nitroSelf.hideModal("go_live_modal");
						});
				}
			});

			$("#go-live-modal .go-live").on("click", function (e) {
				nitroSelf.ajaxPassOnboarding();
			});
		}
		openIntercomBubble() {
			$("#processing-html-error-modal .contact-support").on("click", function () {
				$(".intercom-launcher").trigger("click");
			});
		}
		showModal(modalName) {
			if (this.modals[modalName]) {
				this.modals[modalName].show();
			} else {
				console.error(`Modal '${modalName}' not found`);
			}
		}
		hideModal(modalName) {
			if (this.modals[modalName]) {
				this.modals[modalName].hide();
			} else {
				console.error(`Modal '${modalName}' not found`);
			}
		}
	}

	const NitroPackPreview = new nitropackPreview();
	window.NitroPackPreview = NitroPackPreview;
});
