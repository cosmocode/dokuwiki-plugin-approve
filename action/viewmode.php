<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\approve\meta\ViewModeEdit;
use dokuwiki\plugin\approve\meta\ViewModeSiteTools;

class action_plugin_approve_viewmode extends ActionPlugin
{
    /** @inheritdoc */
    function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleAct');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addSiteTools');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addPageTools');
    }

    public function handleAct(Event $event)
    {
        if (!$this->getConf('viewmode')) return;
        if ($event->data != 'viewmodesitetools' && $event->data != 'viewmodeedit') return;
        $viewmode = get_doku_pref('approve_viewmode', false);
        set_doku_pref('approve_viewmode', !$viewmode);  // toggle status
        $event->data = 'redirect';
    }

    /**
     * Add Link for mode change to the site tools
     *
     * @param Event $event
     * @return bool
     */
    public function addSiteTools(Event $event)
    {
        global $INPUT;
        if (!$this->getConf('viewmode')) return false;
        if (!$INPUT->server->str('REMOTE_USER')) return false;
        if ($event->data['view'] != 'user') return false;

        array_splice($event->data['items'], 1, 0, [new ViewModeSiteTools()]);

        return true;
    }

    public function addPageTools(Event $event)
    {
        global $INPUT;
        if (!$this->getConf('viewmode')) return false;
        if (!$INPUT->server->str('REMOTE_USER')) return false;
        if ($event->data['view'] != 'page') return false;

        $viewmode = get_doku_pref('approve_viewmode', false);
        if ($viewmode) {
            array_splice($event->data['items'], 0, 1, [new ViewModeEdit()]);
        }
        return true;
    }

}
