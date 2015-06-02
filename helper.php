<?php

/**
 * DokuWiki Plugin upload (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <xm.koutny@gmail.com>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class helper_plugin_upload extends Dokuwiki_Plugin {

    public function get_metadata($media_id) {
        $filename = mediaFN($media_id);
        if (!file_exists($filename)) {
            return null;
        }

        $result = p_get_metadata($media_id, $this->getPluginName());
        $result['timestamp'] = filemtime($filename);

        return $result;
    }
}

// vim:ts=4:sw=4:et: