/* Contact Hub — settings page. */
jQuery(function ($) {
	'use strict';

	var $list = $('#ch-channels');
	var tpl = $('#ch-row-tpl').html() || '';
	var next = Date.now();

	function syncPlaceholders($row) {
		var $opt = $row.find('.ch-type option:selected');
		$row.find('.ch-val').attr('placeholder', $opt.data('hint') || '');
		$row.find('.ch-lab').attr('placeholder', $opt.data('label') || '');
		$row.find('.ch-sublab').attr('placeholder', $opt.data('cta') || '');
	}

	$list.sortable({ handle: '.ch-handle', axis: 'y', items: '> li', tolerance: 'pointer' });

	$('#ch-add').on('click', function () {
		var $row = $($.trim(tpl.replace(/__INDEX__/g, String(next++))));
		$list.append($row);
		syncPlaceholders($row);
		$row.find('.ch-val').trigger('focus');
	});

	$list.on('change', '.ch-type', function () {
		syncPlaceholders($(this).closest('li'));
	});

	$list.on('click', '.ch-remove', function () {
		$(this).closest('li').remove();
	});

	$('.ch-color').wpColorPicker();
});
