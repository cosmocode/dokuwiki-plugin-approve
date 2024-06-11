<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_approve_notification extends ActionPlugin
{
    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_NOTIFICATION_REGISTER_SOURCE', 'AFTER', $this, 'addNotificationsSource');
        $controller->register_hook('PLUGIN_NOTIFICATION_GATHER', 'AFTER', $this, 'addNotifications');
        $controller->register_hook('PLUGIN_NOTIFICATION_CACHE_DEPENDENCIES', 'AFTER', $this, 'addNotificationCacheDependencies');
    }

    public function addNotificationsSource(Event $event)
    {
        $event->data[] = 'approve';
    }

    public function addNotificationCacheDependencies(Event $event)
    {
        if (!in_array('approve', $event->data['plugins'])) return;

        /** @var \helper_plugin_approve_db $db */
        $db = $this->loadHelper('approve_db');
        $event->data['dependencies'][] = $db->getDbFile();
    }

    public function addNotifications(Event $event)
    {
        if (!in_array('approve', $event->data['plugins'])) return;

        $user = $event->data['user'];

        /** @var \helper_plugin_approve_db $db */
        $db = $this->loadHelper('approve_db');

        $states = ['draft', 'ready_for_approval'];
        if ($this->getConf('ready_for_approval_notification')) {
            $states = ['ready_for_approval'];
        }

        $notifications = $db->getPages($user, $states);

        foreach ($notifications as $notification) {
            $id = $notification['id'];
            $rev = $notification['rev'];

            $link = '<a class="wikilink1" href="' . wl($id, '', true) . '">';
            if (useHeading('content')) {
                $heading = p_get_first_heading($id);
                if (!blank($heading)) {
                    $link .= $heading;
                } else {
                    $link .= noNSorNS($id);
                }
            } else {
                $link .= noNSorNS($id);
            }
            $link .= '</a>';
            $full = sprintf($this->getLang('notification full'), $link);
            $event->data['notifications'][] = [
                'plugin' => 'approve',
                'id' => $id.':'.$rev,
                'full' => $full,
                'brief' => $link,
                'timestamp' => (int)$rev
            ];
        }
    }
}
