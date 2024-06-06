<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Form\TagOpenElement;
use dokuwiki\Form\CheckableElement;

class action_plugin_approve_revisions extends ActionPlugin {

    function register(EventHandler $controller) {
		$controller->register_hook('FORM_REVISIONS_OUTPUT', 'BEFORE', $this, 'handle_revisions', array());
	}

	function handle_revisions(Event $event, $param) {
		global $INFO;

        /** @var helper_plugin_approve_data $db */
        $db = $this->loadHelper('approve_data');
        /** @var helper_plugin_approve_acl $acl */
        $acl = $this->loadHelper('approve_acl');

        if (!$acl->useApproveHere($INFO['id'])) return;

        $approve_revisions = $db->getPageRevisions($INFO['id']);

        $last_approved_rev = null;
        if (count($approve_revisions) > 1) {
            $last_approved_rev = max(array_column(array_filter($approve_revisions, function ($v) {
                return $v['approved'] != null;
            }), 'rev'));
        }

        $approve_revisions = array_combine(array_column($approve_revisions, 'rev'), $approve_revisions);


		$parent_div_position = -1;
		for ($i = 0; $i < $event->data->elementCount(); $i++) {
            $element = $event->data->getElementAt($i);
            if ($element instanceof TagOpenElement && $element->val() == 'div'
                && $element->attr('class') == 'li') {
                $parent_div_position = $i;
            } elseif ($parent_div_position > 0 && $element instanceof CheckableElement &&
                $element->attr('name') == 'rev2[]') {
                $revision = $element->attr('value');
                if ($revision == 'current') {
                    $revision = $INFO['meta']['date']['modified'];
                }
                if (!isset($approve_revisions[$revision])) {
                    $class =  'plugin__approve_draft';
                } elseif ($approve_revisions[$revision]['approved'] && $revision == $last_approved_rev) {
                    $class =  'plugin__approve_approved';
                } elseif ($approve_revisions[$revision]['approved']) {
                    $class =  'plugin__approve_old_approved';
                } elseif ($this->getConf('ready_for_approval') && $approve_revisions[$revision]['ready_for_approval']) {
                    $class =  'plugin__approve_ready';
                } else {
                    $class =  'plugin__approve_draft';
                }

                $parent_div = $event->data->getElementAt($parent_div_position);
                $parent_div->addClass($class);
                $parent_div_position = -1;
            }
		}
	}

}
