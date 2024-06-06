<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_approve_migration extends ActionPlugin
{
    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_SQLITE_DATABASE_UPGRADE', 'AFTER', $this, 'handle_migrations');
    }

    /**
     * Call our custom migrations when defined.
     *
     * @param Event $event
     * @param mixed $param
     */
    public function handle_migrations(Event $event, $param)
    {
        if ($event->data['sqlite']->getAdapter()->getDbname() !== 'approve') {
            return;
        }
        $to = $event->data['to'];
        if (is_callable([$this, "migration$to"])) {
            $event->preventDefault();
            $event->result = call_user_func([$this, "migration$to"], $event->data['adapter']);
        }
    }
}
