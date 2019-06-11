<?php

/**
 * Upload plugin, allows upload for users with correct
 * permission fromin a wikipage to a defined namespace.
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Christian Moll <christian@chrmoll.de>
 * @author    Franz Häfner <fhaefner@informatik.tu-cottbus.de>
 * @author    Randolf Rotta <rrotta@informatik.tu-cottbus.de>
 */
if (!defined('NL'))
    define('NL', "\n");
if (!defined('DOKU_INC'))
    define('DOKU_INC', dirname(__FILE__) . '/../../');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_INC . 'inc/media.php');
require_once(DOKU_INC . 'inc/auth.php');

require_once(DOKU_INC . 'inc/infoutils.php');

class syntax_plugin_upload2 extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array(
            'author' => 'Franz Häfner',
            'email' => 'fhaefner@informatik.tu-cottbus.de',
            'date' => '2010-09-07',
            'name' => 'upload plugin',
            'desc' => 'upload plugin can add a link to the media manager in your wikipage.
            			Basic syntax: {{upload>namespace|option1|option2}}
				Use @page@ as namespage to use ID of the actual page as namespace or @current@ to use the namespace the current page is in.',
            'url' => 'http://wiki.splitbrain.org/plugin:upload2',
        );
    }

    function getType() {
        return 'substition';
    }

    function getSort() {
        return 32;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{upload>.+?\}\}', $mode, 'plugin_upload2');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;

        $match = substr($match, 9, -2);
        $matches = explode('|', $match, 2);
        $o = explode('|', $matches[1]);

        $options['overwrite'] = in_array('OVERWRITE', $o);
        $options['renameable'] = in_array('RENAMEABLE', $o);
        $options['fixed'] = in_array('FIXED', $o);

        if ($options['fixed']) {
            $pos = strrpos($matches[0], ':');
            $ns = substr($matches[0], 0, $pos);
            $file = substr($matches[0], $pos + 1);
        } else {
            $ns = $matches[0];
            $file = null;
        }

        if ($ns == '@page@') {
            $ns = $ID;
        } else if ($ns == '@current@') {
            $ns = getNS($ID);
        } else {
            resolve_pageid(getNS($ID), $ns, $exists);
        }

        return array('uploadns' => hsc($ns), 'file' => $file, 'para' => $options);
    }

    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            //check auth
            $auth = auth_quickaclcheck($data['uploadns'] . ':*');

            if ($auth >= AUTH_UPLOAD) {
                $renderer->doc .= $this->upload_plugin_uploadform($data['uploadns'], $auth, $data['para'], $data['file']);
//				$renderer->info['cache'] = false;
            }
            return true;
        } else if ($mode == 'metadata') {
            $renderer->meta['has_upload_form'] = $data['uploadns'] . ':*';
            return true;
        }
        return false;
    }

    /**
     * Print the media upload form if permissions are correct
     *
     * @author Christian Moll <christian@chrmoll.de>
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author    Franz Häfner <fhaefner@informatik.tu-cottbus.de>
     * @author    Randolf Rotta <rrotta@informatik.tu-cottbus.de>
     */
    function upload_plugin_uploadform($ns, $auth, $options, $file) {
        global $ID;
        global $lang;
	global $INFO;
        
	$id = $INFO['id'];
	    
	$html = '';

        if ($auth < AUTH_UPLOAD)
            return;

        $params = array();
        $params['id'] = 'upload_plugin';
        $params['action'] = wl($id);
        $params['method'] = 'post';
        $params['enctype'] = 'multipart/form-data';
        $params['class'] = 'upload__plugin';

        // Modification of the default dw HTML upload form
        $form = new Doku_Form($params);
        $form->startFieldset($lang['fileupload']);
        $form->addElement(formSecurityToken());
        $form->addHidden('page', hsc($id));
        $form->addHidden('ns', hsc($ns));
        $form->addHidden('file', hsc($file));
        $form->addElement(form_makeFileField('upload', $lang['txt_upload'] . ':', 'upload__file'));
        if ($options['renameable']) {
            // don't name this field here "id" because it is misinterpreted by DokuWiki if the upload form is not in media manager
            $form->addElement(form_makeTextField('new_name', '', $lang['txt_filename'] . ':', 'upload__name'));
        }

        if ($auth >= AUTH_DELETE) {
            if ($options['overwrite']) {
                //$form->addElement(form_makeCheckboxField('ow', 1, $lang['txt_overwrt'], 'dw__ow', 'check'));
                // circumvent wrong formatting in doku_form
                $form->addElement(
                        '<label class="check" for="dw__ow">' .
                        '<span>' . $lang['txt_overwrt'] . '</span>' .
                        '<input type="checkbox" id="dw__ow" name="ow" value="1"/>' .
                        '</label>'
                );
            }
        }
        $form->addElement(form_makeButton('submit', '', $lang['btn_upload']));
        $form->endFieldset();

        $html .= '<div class="upload_plugin"><p>' . NL;
        $html .= $form->getForm();
        $html .= '</p></div>' . NL;
        return $html;
    }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
