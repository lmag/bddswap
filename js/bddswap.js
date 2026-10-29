/* Copyright (C) 2026 Laurent Magnin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

document.addEventListener('DOMContentLoaded', function () {
	var selectors = document.querySelectorAll('.bddswap-select');
	selectors.forEach(function (selectEl) {
		selectEl.addEventListener('change', function () {
			if (this.form) {
				this.form.submit();
			}
		});
	});
});
