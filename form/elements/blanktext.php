<?php
/**
 * Provides a basic text field input, which will clear itself after submitting.
 *
 * @param Pieform  $form    The form to render the element for
 * @param array    $element The element to render
 * @return string           The HTML for the element
 */
function pieform_element_blanktext(Pieform $form, $element) {/*{{{*/
    return '<input type="text"'
        . $form->element_attributes($element)
        .  '">';
}

?>
