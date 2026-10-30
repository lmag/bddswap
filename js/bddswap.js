/* Copyright (C) 2026 Laurent Magnin
 * Copyright (C) 2026 Eoxia <dev@eoxia.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

document.addEventListener('DOMContentLoaded', function () {
	var topContainer = document.querySelector('.bddswap-top-container');
	var loginBlock = document.querySelector('div.login_block');
	if (topContainer && loginBlock && topContainer.parentElement !== loginBlock) {
		loginBlock.insertBefore(topContainer, loginBlock.firstChild);
	}

	var selectors = document.querySelectorAll('.bddswap-select');
	selectors.forEach(function (selectEl) {
		selectEl.addEventListener('change', function () {
			if (this.form) {
				this.form.submit();
			}
		});
	});
});
