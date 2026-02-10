jQuery(document).ready(function ($) {
	class SystemReport {
		constructor() {
			//report
			this.downloadReport();
			//logger
			this.loggerToggle();
			this.setLoggerLevel();
			this.archiveLogs();
		}

		downloadReport() {
			$("#gen-report-btn").on("click", function (e) {
				e.preventDefault();
				let isReportGenerating = false;

				if (isReportGenerating) return;

				$.ajax({
					url: ajaxurl,
					type: "POST",
					dataType: "text",
					data: {
						action: "nitropack_generate_report",
						nonce: nitroNonce,
						toggled: {
							"general-info-status": $("#general-info-status:checked").length,
							"active-plugins-status": $("#active-plugins-status:checked").length,
							"conflicting-plugins-status": $("#conflicting-plugins-status:checked").length,
							"user-config-status": $("#user-config-status:checked").length,
							"dir-info-status": $("#dir-info-status:checked").length,
						},
					},
					beforeSend: function (xhr, sett) {
						if ($(".diagnostic-option:checked").length > 0) {
							$("#diagnostics-loader").show();
							isReportGenerating = true;
							return true;
						} else {
							alert(np_system_report.report_empty_options);
							return false;
						}
					},
					success: function (response, status, xhr) {
						if (response.length > 1) {
							var filename = "";
							var disposition = xhr.getResponseHeader("Content-Disposition");
							if (disposition && disposition.indexOf("attachment") !== -1) {
								var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
								var matches = filenameRegex.exec(disposition);
								if (matches != null && matches[1]) filename = matches[1].replace(/['"]/g, "");
							}

							var type = xhr.getResponseHeader("Content-Type");
							var blob = new Blob([response], {
								type: type,
							});

							if (typeof window.navigator.msSaveBlob !== "undefined") {
								// IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
								window.navigator.msSaveBlob(blob, filename);
							} else {
								var URL = window.URL || window.webkitURL;
								var downloadUrl = URL.createObjectURL(blob);

								if (filename) {
									// use HTML5 a[download] attribute to specify filename
									var a = document.createElement("a");
									// safari doesn't support this yet
									if (typeof a.download === "undefined") {
										window.location.href = downloadUrl;
									} else {
										a.href = downloadUrl;
										a.download = filename;
										document.body.appendChild(a);
										a.click();
									}
								} else {
									window.location.href = downloadUrl;
								}

								setTimeout(function () {
									URL.revokeObjectURL(downloadUrl);
								}, 100);
							}
							NitropackUI.triggerToast("success", np_system_report.report_success);
						} else {
							NitropackUI.triggerToast("error", np_system_report.report_error);
						}
					},
					error: function () {
						NitropackUI.triggerToast("error", np_system_report.report_error);
					},
					complete: function () {
						$("#diagnostics-loader").hide();
						isReportGenerating = false;
					},
				});
			});
		}
		loggerToggle() {
			const radio = $("#minimum-log-level-status"),
				widget = $("#minimum-log-level-widget"),
				fancy_radios = widget.find(".fancy-radio"),
				fancy_radios_container = widget.find(".fancy-radio-container");
			let minimum_log_level;

			radio.on("change", function () {
				const self = $(this);
				if (self.is(":checked")) {
					minimum_log_level = 3;
				} else {
					minimum_log_level = null;
				}
				$.post(
					ajaxurl,
					{
						action: "nitropack_set_log_level_ajax",
						minimum_log_level: minimum_log_level,
						nonce: np_system_report.nitroNonce,
					},
					function (response) {
						var resp = JSON.parse(response);
						if (resp.type == "success") {
							if (self.is(":checked")) {
								$(".logging").removeClass("hidden");
								fancy_radios_container.removeClass("selected");
								fancy_radios.removeClass("selected");
								widget.find('.fancy-radio-container[data-value="' + minimum_log_level + '"').addClass("selected");
								widget
									.find('.fancy-radio-container[data-value="' + minimum_log_level + '"')
									.find(".fancy-radio")
									.addClass("selected");
							} else {
								fancy_radios.removeClass("selected");
								$(".logging").addClass("hidden");
							}

							NitropackUI.triggerToast("success", np_system_report.success_msg);
						} else {
							NitropackUI.triggerToast("error", np_system_report.success_msg);
							$(this).prop("checked", false);
						}
					},
				);
			});
		}
		/* Set logger level */
		setLoggerLevel() {
			const widget = $("#minimum-log-level-widget"),
				fancy_radios_container = widget.find(".fancy-radio-container"),
				fancy_radios = widget.find(".fancy-radio");
			let initial_minimum_log_level = widget.find(".fancy-radio-container.selected").data("value");

			fancy_radios_container.click(function () {
				let fancy_radio_container = $(this),
					fancy_radio = $(this).find(".fancy-radio"),
					minimum_log_level = fancy_radio_container.data("value");
				if (minimum_log_level === initial_minimum_log_level) return;

				$.post(
					ajaxurl,
					{
						action: "nitropack_set_log_level_ajax",
						minimum_log_level: minimum_log_level,
						nonce: np_system_report.nitroNonce,
					},
					function (response) {
						var resp = JSON.parse(response);
						if (resp.type == "success") {
							//container
							fancy_radios_container.removeClass("selected");
							fancy_radio_container.addClass("selected");
							//custom radios
							fancy_radios.removeClass("selected");
							fancy_radio.addClass("selected");
							initial_minimum_log_level = minimum_log_level;
							NitropackUI.triggerToast("success", np_system_report.success_msg);
						} else {
							NitropackUI.triggerToast("error", np_system_report.success_msg);
							$(this).prop("checked", false);
						}
					},
				);
			});
		}
		/* Zips all logs and downloads them */
		archiveLogs() {
			$(".archive-logs").click(function (e) {
				e.preventDefault();
				$.post(
					ajaxurl,
					{
						action: "nitropack_archive_logs_ajax",
						nonce: np_system_report.nitroNonce,
					},
					function (response) {
						var resp = JSON.parse(response);
						if (resp.type == "success") {
							window.location.href = resp.url;
							NitropackUI.triggerToast("success", np_system_report.success_msg);
						} else {
							NitropackUI.triggerToast("error", np_system_report.success_msg);
						}
					},
				);
			});
		}
	}
	const systemReport = new SystemReport();
	//window.systemReport = systemReport;
});
