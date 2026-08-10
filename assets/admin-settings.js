(function ($) {
    'use strict';

    $(document).ready(function () {
        // Live preview for currency format changes
        var $thousandSep = $('#jankx_currency_thousand_sep');
        var $decimalSep = $('#jankx_currency_decimal_sep');
        var $decimals = $('select[name="jankx_currency_decimals"]');
        var $position = $('select[name="jankx_currency_position"]');

        function updatePreview() {
            var thousandSep = $thousandSep.val() || ',';
            var decimalSep = $decimalSep.val() || '.';
            var decimals = parseInt($decimals.val(), 10) || 0;
            var position = $position.val() || 'left';

            var num = 1234567.89;
            var formatted = num.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).replace(/,/g, thousandSep).replace(/\./g, decimalSep);

            var symbols = { USD: '$', VND: '₫', EUR: '€', GBP: '£', JPY: '¥' };
            var symbol = symbols['USD'] || '$';

            var preview;
            switch (position) {
                case 'left': preview = symbol + formatted; break;
                case 'right': preview = formatted + symbol; break;
                case 'left_space': preview = symbol + ' ' + formatted; break;
                case 'right_space': preview = formatted + ' ' + symbol; break;
                default: preview = symbol + formatted;
            }

            $('.jankx-price-preview').html(
                '<p><strong>Preview:</strong> ' + $('<span>').text(preview).html() + '</p>'
            );
        }

        $thousandSep.on('input', updatePreview);
        $decimalSep.on('input', updatePreview);
        $decimals.on('change', updatePreview);
        $position.on('change', updatePreview);

        // Confirm before switching currency default if no currencies enabled
        $('form').on('submit', function () {
            var enabled = $('input[name="jankx_enabled_currencies[]"]:checked').length;
            var defaultCurrency = $('input[name="jankx_default_currency"]:checked').val();

            if (enabled === 0) {
                alert('Vui lòng chọn ít nhất một loại tiền tệ.');
                return false;
            }

            if (defaultCurrency && $('input[name="jankx_enabled_currencies[]"][value="' + defaultCurrency + '"]:checked').length === 0) {
                alert('Tiền tệ mặc định phải được bật.');
                return false;
            }

            return true;
        });
    });
})(jQuery);
