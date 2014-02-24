/* global define */
define(['jquery'], function ($) {
    /**
     * @export  orocrm/account/contacts-widget
     * @class   oro.ContactWidgetHandler
     */
    return {
        /**
         * @desc Fire name link click
         * @callback
         */
        boxClickHandler: function (onClickEven) {
            /**
             * @desc if target item has class contact-box-link
             * we does not click redirection link(name link)
             */
            if ($(onClickEven.target).hasClass('contact-box-link')) {
                return;
            }
            $(this).find('.contact-box-name-link').click();
        },

        /**
         * @constructs
         */
        init: function () {
            $('.contact-box').click(this.boxClickHandler);
        }
    };
});