<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Allows a list of existing quotas to be included in the config form
 * @param Pieform $form
 * @param <type> $element
 */
function pieform_element_quotaslist(Pieform $form, $element) {

    $smarty = smarty_core();

    $formid = $form->get_name();
    $prefix = $formid.'_';
    $smarty->assign('prefix', $prefix);

    $sql = "SELECT *
              FROM {artefact_media_ldap_quota}
             WHERE institution = ?
               AND quota IS NOT NULL
        ";
    $existingquotas = get_records_sql_assoc($sql, array($element['institution']));

    $smarty->assign('existingquotas', $existingquotas);
    $smarty->assign('quotaoptions', $element['quotaoptions']);
    return $smarty->fetch('artefact:media:form/quotaslist.tpl');

}

?>
