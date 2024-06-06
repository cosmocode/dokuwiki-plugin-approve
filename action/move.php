<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_approve_move extends ActionPlugin {

    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller) {
        $controller->register_hook('PLUGIN_MOVE_PAGE_RENAME', 'AFTER', $this, 'handle_move', true);
    }

    /**
     * Renames all occurrences of a page ID in the database.
     *
     * @param Event $event event object by reference
     * @param bool $ispage is this a page move operation?
     * @return void
     */
    public function handle_move(Event $event, $ispage) {
        /** @var \helper_plugin_approve_db $db_helper */
        $db = $this->loadHelper('approve_db');

        $old = $event->data['src_id'];
        $new = $event->data['dst_id'];

        // move revision history
        $db->moveRevisionHistory($old, $new);
    }

}
