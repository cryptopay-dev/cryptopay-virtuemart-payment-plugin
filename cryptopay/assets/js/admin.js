/**
 *
 * Cryptopay payment plugin
 *
 * @author Cryptopay
 * @version $Id: paypal.php 7217 2013-09-18 13:42:54Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004 - 2014 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 *
 * http://virtuemart.net
 */

jQuery().ready(function ($) {

    function copyToClipboard(text) {
        if (window.clipboardData && window.clipboardData.setData) {
            return window.clipboardData.setData('Text', text);
        } else if (document.queryCommandSupported && document.queryCommandSupported('copy')) {
            const textarea = document.createElement('textarea');
            textarea.textContent = text;
            textarea.style.position = 'fixed';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                return document.execCommand('copy');
            } catch (ex) {
                return prompt('Copy to clipboard: Ctrl+C, Enter', text);
            } finally {
                document.body.removeChild(textarea);
            }
        }
    }

    $('#callbackCopy').click(() => {
        const callbackUrl = $('#callbackUrl').text();
        copyToClipboard(callbackUrl);
    });
})
