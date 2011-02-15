<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Provides a text box with an 'Add' button next to it. This method is needed because other ways of putting
 * the add button in leave it in the wrong place, and if all of the inputs are in one form element, it doesn't
 * retrieve the values properly, so it has to be one element per input.
 *
 * @param Pieform $form
 * @param <type> $element
 */
function pieform_element_ldapquotachooser(Pieform $form, $element) {

    $smarty = smarty_core();

    $formid = $form->get_name();
    $prefix = $formid.'_';
    $smarty->assign('prefix', $prefix);

    $smarty->assign('quotaoptions', $element['options']);
    $smarty->assign('defaultvalue', $element['defaultvalue']);
    return $smarty->fetch('artefact:media:form/ldapquotachooser.tpl');

}

?>
