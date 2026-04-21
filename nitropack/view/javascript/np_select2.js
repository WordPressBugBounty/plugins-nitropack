(function ($) {
	"use strict";

	if (typeof $.fn.npSelect === "function") {
		return;
	}

	let instanceCount = 0;

	function normalize(value) {
		if (typeof value === "undefined" || value === null) {
			return "";
		}
		return String(value);
	}

	function matchesTerm(text, value, term) {
		const needle = term.toLowerCase();
		return text.toLowerCase().includes(needle) || value.toLowerCase().includes(needle);
	}

	class NPSelect {
		constructor($select) {
			this.$select = $select;
			this.instanceId = ++instanceCount;
			this.isOpen = false;
			this.init();
		}

		init() {
			if (!this.$select.length) {
				return;
			}

			this.$select.addClass("np-select-hidden-accessible");
			this.build();
			this.bind();
			this.render();
		}

		build() {
			this.$container = $('<span class="np-select"></span>');
			this.$control = $('<span class="np-select-control" role="combobox" aria-expanded="false"></span>');
			this.$values = $('<ul class="np-select-values"></ul>');
			this.$inputItem = $('<li class="np-select-input-item"></li>');
			this.$input = $('<input class="np-select-input" type="search" autocomplete="off" spellcheck="false" />');
			this.$dropdown = $('<span class="np-select-dropdown"></span>');
			this.$options = $('<ul class="np-select-options" role="listbox"></ul>');

			this.$inputItem.append(this.$input);
			this.$values.append(this.$inputItem);
			this.$control.append(this.$values);
			this.$dropdown.append(this.$options);
			this.$container.append(this.$control, this.$dropdown);
			this.$select.after(this.$container);
			this.$dropdown.hide();
		}

		bind() {
			this.$control.on("click", (event) => {
				event.preventDefault();
				this.open();
				this.$input.trigger("focus");
			});

			this.$control.on("click", ".np-select-remove", (event) => {
				event.preventDefault();
				event.stopPropagation();

				const value = normalize($(event.currentTarget).closest("li.np-select-value").attr("data-value"));
				if (!value) {
					return;
				}

				this.unselect(value);
				this.open();
				this.$input.trigger("focus");
			});

			this.$input.on("input", () => this.renderOptions());

			this.$input.on("keydown", (event) => {
				if (event.key === "Enter" || event.key === ",") {
					event.preventDefault();

					const $first = this.$options.find(".np-select-option:not(.np-select-option--disabled)").first();
					if ($first.length) {
						this.pickOption($first);
					} else {
						this.addFromInput();
					}
					return;
				}

				if (event.key === "Escape") {
					event.preventDefault();
					this.close();
					return;
				}

				if (event.key === "Backspace" && !normalize(this.$input.val()).trim()) {
					this.removeLast();
				}
			});

			this.$options.on("mousedown", ".np-select-option", (event) => {
				event.preventDefault();
			});

			this.$options.on("click", ".np-select-option", (event) => {
				this.pickOption($(event.currentTarget));
			});

			this.$select.on("change.npSelect", () => {
				this.render();
			});

			$(document).on("mousedown.npSelect-" + this.instanceId, (event) => {
				if (!this.$container.is(event.target) && this.$container.has(event.target).length === 0) {
					this.close();
				}
			});
		}

		pickOption($option) {
			if ($option.hasClass("np-select-option--disabled")) {
				return;
			}

			const value = normalize($option.attr("data-value"));
			const label = normalize($option.text()).trim();
			this.select(value, label, $option.hasClass("np-select-option--new"));
			this.$input.val("");
			this.renderOptions();
			this.$input.trigger("focus");
		}

		render() {
			this.renderSelected();
			this.renderOptions();

			const isDisabled = this.$select.prop("disabled");
			this.$input.prop("disabled", isDisabled);
			this.$container.toggleClass("np-select--disabled", isDisabled);
		}

		renderSelected() {
			this.$values.find("li.np-select-value").remove();

			this.$select.find("option:selected").each((_, optionEl) => {
				const value = normalize(optionEl.value);
				const text = normalize($(optionEl).text()).trim();

				const $value = $('<li class="np-select-value" role="option"></li>').attr("data-value", value);
				const $inner = $('<span class="np-select-value-inner"></span>');
				$inner.append(document.createTextNode(text));
				$inner.append('<span class="np-select-remove"></span>');
				$value.append($inner);
				this.$inputItem.before($value);
			});
		}

		renderOptions() {
			const term = normalize(this.$input.val()).trim();
			const termLower = term.toLowerCase();

			const rawSelected = this.$select.val();
			const selectedValues = Array.isArray(rawSelected)
				? rawSelected.map(normalize)
				: rawSelected
					? [normalize(rawSelected)]
					: [];
			const selectedSet = new Set(selectedValues);

			let hasOptions = false;
			let hasExactMatch = false;

			this.$options.empty();

			this.$select.find("option").each((_, optionEl) => {
				const value = normalize(optionEl.value);
				const text = normalize($(optionEl).text()).trim();
				const isDisabled = $(optionEl).prop("disabled");

				if (selectedSet.has(value)) {
					return;
				}

				if (term && !matchesTerm(text, value, term)) {
					return;
				}

				if (termLower && (text.toLowerCase() === termLower || value.toLowerCase() === termLower)) {
					hasExactMatch = true;
				}

				const $option = $('<li class="np-select-option" role="option"></li>')
					.attr("data-value", value)
					.attr("aria-selected", "false")
					.text(text);

				if (isDisabled) {
					$option.addClass("np-select-option--disabled").attr("aria-disabled", "true");
				}

				this.$options.append($option);
				hasOptions = true;
			});

			if (term && !hasExactMatch) {
				const $newOption = $('<li class="np-select-option np-select-option--new" role="option"></li>')
					.attr("data-value", term)
					.attr("aria-selected", "false")
					.text(term);
				this.$options.prepend($newOption);
				hasOptions = true;
			}

			if (!hasOptions) {
				this.$options.append('<li class="np-select-option np-select-option--disabled" aria-disabled="true">No results found</li>');
			}
		}

		select(value, label, allowCreate) {
			const normalizedValue = normalize(value);
			let $option = this.$select
				.find("option")
				.filter(function () {
					return normalize(this.value) === normalizedValue;
				})
				.first();

			if (!$option.length && allowCreate) {
				$option = $("<option></option>").val(normalizedValue).text(label || normalizedValue);
				this.$select.append($option);
			}

			if (!$option.length) {
				return;
			}

			if (!this.$select.prop("multiple")) {
				this.$select.find("option").prop("selected", false);
			}

			$option.prop("selected", true);
			this.$select.trigger("change");

			if (!this.$select.prop("multiple")) {
				this.close();
			}
		}

		unselect(value) {
			const normalizedValue = normalize(value);
			this.$select
				.find("option")
				.filter(function () {
					return normalize(this.value) === normalizedValue;
				})
				.prop("selected", false);

			this.$select.trigger("change");
		}

		addFromInput() {
			const term = normalize(this.$input.val()).trim();
			if (!term) {
				return;
			}

			const termLower = term.toLowerCase();
			const $existing = this.$select
				.find("option")
				.filter(function () {
					const value = normalize(this.value).toLowerCase();
					const text = normalize($(this).text()).trim().toLowerCase();
					return value === termLower || text === termLower;
				})
				.first();

			if ($existing.length) {
				this.select(normalize($existing.val()), normalize($existing.text()).trim(), false);
				return;
			}

			this.select(term, term, true);
		}

		removeLast() {
			if (!this.$select.prop("multiple")) {
				return;
			}

			const values = this.$select.val() || [];
			if (!Array.isArray(values) || values.length === 0) {
				return;
			}

			values.pop();
			this.$select.val(values).trigger("change");
		}

		open() {
			if (this.isOpen || this.$select.prop("disabled")) {
				return;
			}

			this.isOpen = true;
			this.$container.addClass("np-select--open");
			this.$control.attr("aria-expanded", "true");
			this.$dropdown.show();
			this.renderOptions();
		}

		close() {
			if (!this.isOpen) {
				return;
			}

			this.isOpen = false;
			this.$container.removeClass("np-select--open");
			this.$control.attr("aria-expanded", "false");
			this.$dropdown.hide();
			this.$input.val("");
			this.renderOptions();
		}

		destroy() {
			$(document).off("mousedown.npSelect-" + this.instanceId);
			this.$select.off("change.npSelect");
			this.$select.removeClass("np-select-hidden-accessible");
			if (this.$container) {
				this.$container.remove();
			}
			this.$select.removeData("npSelect");
		}
	}

	$.fn.npSelect = function (method) {
		if (typeof method === "string") {
			if (method === "destroy") {
				return this.each(function () {
					const instance = $(this).data("npSelect");
					if (instance) {
						instance.destroy();
					}
				});
			}

			return this;
		}

		return this.each(function () {
			const $element = $(this);
			if (!$element.data("npSelect")) {
				$element.data("npSelect", new NPSelect($element));
			}
		});
	};
})(jQuery);
