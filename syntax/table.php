<?php

class syntax_plugin_approve_table extends DokuWiki_Syntax_Plugin {

    protected $states = ['approved', 'draft', 'ready_for_approval'];

    function getType() {
        return 'substition';
    }

    function getSort() {
        return 20;
    }

    function PType() {
        return 'block';
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *approve table *-+\n.*?----+', $mode,'plugin_approve_table');
    }

    function handle($match, $state, $pos, Doku_Handler $handler){
        $lines = explode("\n", $match);
        array_shift($lines);
        array_pop($lines);

        $params = [
            'namespace' => '',
            'filter' => false,
            'states' => [],
            'summarize' => true,
            'approver' => null
        ];

        foreach ($lines as $line) {
            $pair = explode(':', $line, 2);
            if (count($pair) < 2) {
                continue;
            }
            $key = trim($pair[0]);
            $value = trim($pair[1]);
            if ($key == 'states') {
                $value = array_map('trim', explode(',', $value));
                //normalize
                $value = array_map('strtolower', $value);
                foreach ($value as $state) {
                    if (!in_array($state, $this->states)) {
                        msg('approve plugin: unknown state "'.$state.'" should be: ' .
                            implode(', ', $this->states), -1);
                        return false;
                    }
                }
            } elseif($key == 'filter') {
                $value = trim($value, '/');
                if (preg_match('/' . $value . '/', null) === false) {
                    msg('approve plugin: invalid filter regex', -1);
                    return false;
                }
            } elseif ($key == 'summarize') {
                $value = $value == '0' ? false : true;
            } elseif ($key == 'namespace') {
                $value = trim(cleanID($value), ':');
            }
            $params[$key] = $value;
        }
        return $params;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        $method = 'render' . ucfirst($mode);
        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $renderer, $data);
            return true;
        }
        return false;
    }

    /**
     * Render metadata
     *
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     */
    public function renderMetadata(Doku_Renderer $renderer, $params)
    {
        $plugin_name = $this->getPluginName();
        $renderer->meta['plugin'][$plugin_name] = [];

        if ($params['approver'] == '$USER$') {
            $renderer->meta['plugin'][$plugin_name]['dynamic_approver'] = true;
        }

        $renderer->meta['plugin'][$plugin_name]['approve_table'] = true;
    }

    protected function array_equal($a, $b) {
        return (
            is_array($a)
            && is_array($b)
            && count($a) == count($b)
            && array_diff($a, $b) === array_diff($b, $a)
        );
    }

    protected function getApprovablePages($params)
    {
        global $INFO;

        try {
            /** @var \helper_plugin_approve_db $db_helper */
            $db_helper = plugin_load('helper', 'approve_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return;
        }

        if ($params['approver'] == '$USER$') {
            $params['approver'] = $INFO['client'];
        }

        $approver_query = '';
        $query_args = [$params['namespace'].'*'];
        if ($params['approver']) {
            $approver_query .= " AND page.approver LIKE ?";
            $query_args[] = $params['approver'];
        }

        if ($params['filter']) {
            $approver_query .= " AND page.page REGEXP ?";
            $query_args[] = $params['filter'];
        }

        //if all 3 states are enabled nothing is filtered
        if ($params['states'] && count($params['states']) < 3) {
            if ($this->array_equal(['draft'], $params['states'])) {
                $approver_query .= " AND revision.ready_for_approval IS NULL AND revision.approved IS NULL";
            } elseif ($this->array_equal(['ready_for_approval'], $params['states'])) {
                $approver_query .= " AND revision.ready_for_approval IS NOT NULL AND revision.approved IS NULL";
            } elseif ($this->array_equal(['approved'], $params['states'])) {
                $approver_query .= " AND revision.approved IS NOT NULL";
            } elseif ($this->array_equal(['draft', 'ready_for_approval'], $params['states'])) {
                $approver_query .= " AND revision.approved IS NULL";
            } elseif ($this->array_equal(['draft', 'approved'], $params['states'])) {
                $approver_query .= " AND (revision.approved IS NOT NULL OR (revision.approved IS NULL AND revision.ready_for_approval IS NULL))";
            } elseif ($this->array_equal(['ready_for_approval', 'approved'], $params['states'])) {
                $approver_query .= " AND (revision.ready_for_approval IS NOT NULL OR revision.approved IS NOT NULL)";
            }
        }

        $q = "SELECT page.page, GROUP_CONCAT(page.approver, ', ') AS approver, revision.rev, revision.approved, revision.approved_by,
                    revision.ready_for_approval, revision.ready_for_approval_by,
                    LENGTH(page.page) - LENGTH(REPLACE(page.page, ':', '')) AS colons
                    FROM page INNER JOIN revision ON page.page = revision.page
                    WHERE page.hidden = 0 AND revision.current=1 AND page.page GLOB ?
                            $approver_query
                    GROUP BY page.page
                    ORDER BY colons, page.page";

        $res = $sqlite->query($q, $query_args);
        return $sqlite->res2arr($res);
    }

    public function renderXhtml(Doku_Renderer $renderer, $params)
    {
        global $INFO;
        global $conf;
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $pages = $this->getApprovablePages($params);

        // Output Table
        $renderer->doc .= '<table class="plugin__approve"><tr>';
        $renderer->doc .= '<th>' . $this->getLang('hdr_page') . '</th>';
        $renderer->doc .= '<th>' . $this->getLang('hdr_state') . '</th>';
        $renderer->doc .= '<th>' . $this->getLang('hdr_updated') . '</th>';
        $renderer->doc .= '<th>' . $this->getLang('hdr_approver') . '</th>';
        $renderer->doc .= '<th>' . $this->getLang('hdr_quick') . '</th>';
        $renderer->doc .= '</tr>';


        $all_approved = 0;
        $all_approved_ready = 0;
        $all = 0;

        $form = new \dokuwiki\Form\Form(['action' => wl($INFO['id'], 'approve=approve')]);

        $curNS = '';

        foreach ($pages as $page) {
            $rowMarkup = '';

            $id = $page['page'];
            $approver = $page['approver'];
            $rev = $page['rev'];
            $approved = strtotime($page['approved']);
            $approved_by = $page['approved_by'];
            $ready_for_approval = strtotime($page['ready_for_approval']);
            $ready_for_approval_by = $page['ready_for_approval_by'];

            $pageNS = getNS($id);

            if ($pageNS != '' && $pageNS != $curNS) {
                $curNS = $pageNS;

                $rowMarkup .= '<tr><td colspan="4"><a href="';
                $rowMarkup .= wl($curNS);
                $rowMarkup .= '">';
                $rowMarkup .= $curNS;
                $rowMarkup .= '</a> ';
                $rowMarkup .= '</td>';
                // bulk NS toggle
                $rowMarkup .= '<td>';
                $rowMarkup .= '<input type="checkbox" class="plugin__approve_toggle_ns" data-ns="' . $curNS . '">';
                $rowMarkup .= $this->getLang('toggle_ns') . '</a>';
                $rowMarkup .= '</td>';

                $rowMarkup .= '</tr>';
            }

            $all += 1;
            if ($approved) {
                $class = 'plugin__approve_green';
                $state = $this->getLang('approved');
                $date = $approved;
                $by = $approved_by;

                $all_approved += 1;
            } elseif ($this->getConf('ready_for_approval') && $ready_for_approval) {
                $class = 'plugin__approve_ready';
                $state = $this->getLang('marked_approve_ready');
                $date = $ready_for_approval;
                $by = $ready_for_approval_by;

                $all_approved_ready += 1;
            } else {
                $class = 'plugin__approve_red';
                $state = $this->getLang('draft');
                $date = $rev;
                $by = p_get_metadata($id, 'last_change user');
            }

            $rowMarkup .= '<tr class="'.$class.'">';

            // page column
            $rowMarkup .= '<td><a href="';
            $rowMarkup .= wl($id);
            $rowMarkup .= '">';
            if ($conf['useheading'] == '1') {
                $heading = p_get_first_heading($id);
                if ($heading != '') {
                    $rowMarkup .= $heading;
                } else {
                    $rowMarkup .= $id;
                }
            } else {
                $rowMarkup .= $id;
            }
            $rowMarkup .= '</a></td>';

            // status column
            $rowMarkup .= '<td><strong>'.$state. '</strong> ';

            $user = $auth->getUserData($by);
            if ($user) {
                $rowMarkup .= $this->getLang('by'). ' ' . $user['name'];
            }
            $rowMarkup .= '</td>';

            // current revision column
            $rowMarkup .= '<td><a href="' . wl($id) . '">' . dformat($date) . '</a></td>';

            // approver column
            $rowMarkup .= '<td>';
            if ($approver) {
                // handle multiple approvers
                $approversArray = explode(',', $approver);
                $approvers = array_map(
                    function ($ap) use($auth) {
                        $user = $auth->getUserData(trim($ap));
                     return $user ? $user['name'] : $ap;
                    }, $approversArray
                );
                $rowMarkup .= implode(', ', $approvers);
            } else {
                $approversArray = [];
                $rowMarkup .= '---';
            }
            $rowMarkup .= '</td>';

            // include all columns so far
            $form->addHTML($rowMarkup);

            // finally bulk select column
            $form->addHTML('<td>');
            if (!$approved && $this->isCurrentUserApprover($id, $approversArray)) {
                $form->addCheckbox('bulk[]')
                    ->addClass('plugin__approve_bulk_checkbox')
                    ->attr('data-ns', $curNS)
                    ->val($id);
            }
            $form->addHTML('</td>');
            $form->addHTML('</tr>');
        } // end page loop

        // submit button
        $form->addHTML('<tr><td colspan="5">');
        $form->addButton('submit', $this->getLang('submit_quick'))->attr('type', 'submit');
        $form->addHTML('</td></tr>');

        // render the form to doc
        $formRender = $form->toHTML();
        $renderer->doc .= $formRender;

        if ($params['summarize']) {
            if ($this->getConf('ready_for_approval')) {
                $renderer->doc .= '<tr><td><strong>';
                $renderer->doc .= $this->getLang('all_approved_ready');
                $renderer->doc .= '</strong></td>';

                $renderer->doc .= '<td colspan="4">';
                $percent       = 0;
                if ($all > 0) {
                    $percent = $all_approved_ready * 100 / $all;
                }
                $renderer->doc .= $all_approved_ready . ' / ' . $all . sprintf(" (%.0f%%)", $percent);
                $renderer->doc .= '</td></tr>';
            }

            $renderer->doc .= '<tr><td><strong>';
            $renderer->doc .= $this->getLang('all_approved');
            $renderer->doc .= '</strong></td>';

            $renderer->doc .= '<td colspan="4">';
            $percent       = 0;
            if ($all > 0) {
                $percent = $all_approved * 100 / $all;
            }
            $renderer->doc .= $all_approved . ' / ' . $all . sprintf(" (%.0f%%)", $percent);
            $renderer->doc .= '</td></tr>';
        }

        $renderer->doc .= '</table>';
    }

    /**
     * Returns true if the current user may approve the given page
     *
     * @param string $id
     * @param array $approver
     * @return bool
     */
    protected function isCurrentUserApprover($id, $approver)
    {
        /** @var helper_plugin_approve $helper */
        $helper = plugin_load('helper', 'approve');

        return $helper->client_can_approve($id, $approver);
    }
}
