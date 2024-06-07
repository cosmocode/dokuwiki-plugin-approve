<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_approve_cache extends ActionPlugin
{
    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');
    }
    /**
     * @param Event $event
     * @param mixed $param
     */
    public function handle_parser_cache_use(Event $event, $param)
    {
        /** @var cache_renderer $cache */
        $cache = $event->data;

        if(!$cache->page) return;
        //purge only xhtml cache
        if($cache->mode != 'xhtml') return;

        //Check if it is plugins
        $approve = p_get_metadata($cache->page, 'plugin approve');
        if(!$approve) return;

        if ($approve['dynamic_approver']) {
            $cache->_nocache = true;
        } elseif ($approve['approve_table']) {
            try {
                /** @var helper_plugin_approve_db $db */
                $db = $this->loadHelper('approve_db');
                $cache->depends['files'][] = $db->getDbFile();
            } catch (Exception $e) {
                msg($e->getMessage(), -1);
                return;
            }
        }
    }
}
