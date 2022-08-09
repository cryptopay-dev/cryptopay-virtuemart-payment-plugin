<?php
/**
 *
 * Cryptopay
 *
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004 - 2022 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');
class JFormFieldCallbackUrl extends JFormField {

    /**
     * Element name
     *
     * @access    protected
     * @var        string
     */
    var $type = 'callbackUrl';

    protected function getInput() {
        vmJsApi::addJScript( '/plugins/vmpayment/cryptopay/cryptopay/assets/js/admin.js');

        $cid = vRequest::getvar('cid', NULL, 'array');
        if (is_Array($cid)) {
            $virtuemart_paymentmethod_id = $cid[0];
        } else {
            $virtuemart_paymentmethod_id = $cid;
        }

        $callbackUrl = JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=notify&nt=ipn&tmpl=component&pm=' . $virtuemart_paymentmethod_id;
         return '<div style="display:flex;"><div id="callbackCopy" style="cursor:pointer;" class="icon-copy"></div><div id="callbackUrl">' . $callbackUrl . '</div></div>';
    }
}
