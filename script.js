jQuery(function() {
    "use strict";
    var $groupInput = jQuery('#plugin__approve_group_input');
    if ($groupInput.length === 0) return;

    var $form = $groupInput.closest('form');
    var $select = $form.find('select');

    //Add option to select a group
    var label = LANG['plugins']['approve']['group_option_label'];
    if (label === '') {
        label = 'Group:';
    }
    var $groupOption = jQuery('<option value="">' + label + '</option>')
        .insertAfter($select.find('option:first-child'));

    $groupInput.hide();
    $select.on('change', function() {
        var $option = $select.find(':selected');
        if ($option.is($groupOption)) {
            $groupInput.val('@');
            $groupInput.show();
        } else {
            $groupInput.val('');
            $groupInput.hide();
        }
    });

});
