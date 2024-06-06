<?php

use dokuwiki\Extension\Plugin;

class helper_plugin_approve_acl extends Plugin
{
    public function useApproveHere($id) {
        /** @var helper_plugin_approve_db $db */
        $db = $this->loadHelper('approve_db');
        $page_metadata = $db->getPageMetadata($id);
        if ($page_metadata === null) { // do not use approve plugin here
            return false;
        }
        return true;
    }

    public function clientCanApprove($id): bool
    {
        global $INFO;

        // user not log in
        if (!isset($INFO['userinfo'])) return false;

        /** @var helper_plugin_approve_db $db */
        $db = $this->loadHelper('approve_db');
        $page_metadata = $db->getPageMetadata($id);

        if ($page_metadata === null) {
            return false;
        } elseif ($page_metadata['approver'] == $INFO['client']) { // user is approver
            return true;
            // user is in approvers group
        } elseif (strncmp($page_metadata['approver'], "@", 1) === 0 &&
            in_array(substr($page_metadata['approver'], 1), $INFO['userinfo']['grps'])) {
            return true;
            // if the user has AUTH_DELETE permission and the approver is not defined or strict_approver is turn off
            // user can approve the page
        } elseif (auth_quickaclcheck($id) >= AUTH_DELETE &&
            ($page_metadata['approver'] === null || !$this->getConf('strict_approver'))) {
            return true;
        }
        return false;
    }

    public function clientCanMarkReadyForApproval($id): bool {
        global $INFO;

        $ready_for_approval_acl = preg_split('/\s+/', $this->getConf('ready_for_approval_acl'),
            -1, PREG_SPLIT_NO_EMPTY);

        if (count($ready_for_approval_acl) == 0) {
            return auth_quickaclcheck($id) >= AUTH_EDIT;
        }
        foreach ($ready_for_approval_acl as $user_or_group) {
            if ($user_or_group[0] == '@' && in_array(substr($user_or_group, 1), $INFO['userinfo']['grps'])) {
                return true;
            } elseif ($user_or_group == $INFO['client']) {
                return true;
            }
        }
        return false;
    }

    public function clientCanSeeDrafts($id): bool {

        // in view mode no one can see drafts
        if ($this->getConf('viewmode') && get_doku_pref('approve_viewmode', false)) return false;

        if (!$this->getConf('hide_drafts_for_viewers')) return true;

        if (auth_quickaclcheck($id) >= AUTH_EDIT) return true;
        if ($this->clientCanApprove($id)) return true;

        return false;
    }
}
