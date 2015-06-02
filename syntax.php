<?php

/**
 * Upload plugin, allows upload for users with correct
 * permission fromin a wikipage to a defined namespace.
 * 
 * Heavily modified for specialized case of uploading files
 * for Kachna puzzlehunt game.
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Christian Moll <christian@chrmoll.de>
 * @author Franz Häfner <fhaefner@informatik.tu-cottbus.de>
 * @author Randolf Rotta <rrotta@informatik.tu-cottbus.de>
 * @author Michal Koutný <xm.koutny@gmail.com>
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

class syntax_plugin_upload extends DokuWiki_Syntax_Plugin {

    /**
     * @var helper_plugin_upload
     */
    private $helper;
    
    public function __construct() {
        $this->helper = $this->loadHelper('upload');
    }

    function getInfo() {
        return array(
            'author' => 'Franz Häfner',
            'email' => 'fhaefner@informatik.tu-cottbus.de',
            'date' => '2010-09-07',
            'name' => 'upload plugin',
            'desc' => 'upload plugin can add a link to the media manager in your wikipage.
            			Basic syntax: {{upload>namespace|option1|option2}}
				Use @page@ as namespage to use ID of the actual page as namespace or @current@ to use the namespace the current page is in.',
            'url' => 'http://wiki.splitbrain.org/plugin:upload',
        );
    }

    function getType() {
        return 'substition';
    }

    function getSort() {
        return 32;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{upload>.+?\}\}', $mode, 'plugin_upload');
        $this->Lexer->addSpecialPattern('\{\{uploadinfo>.+?\}\}', $mode, 'plugin_upload');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;

        // handle special case first
        if (substr($match, 0, 13) == '{{uploadinfo>') {
            $file = substr($match, 13, -2);
            $ns = getNS($file);
            return array('uploadns' => hsc($ns), 'file' => $file, 
                'para' => array('info' => true));
        }

        // now continue with ordinary upload
        $match = substr($match, 9, -2);
        $matches = explode('|', $match, 2);
        $o = explode('|', $matches[1]);

        $options['overwrite'] = in_array('OVERWRITE', $o);
        $options['fulloverwrite'] = in_array('FULLOVERWRITE', $o);
        $options['renameable'] = in_array('RENAMEABLE', $o);
        $options['fixed'] = in_array('FIXED', $o); 
        
        $ext_options = array('METADATA' => 'metadata');
        foreach($o as $opt) {
            foreach($ext_options as $ext_opt => $ext_key) {
                if(strpos($opt, $ext_opt) !== 0) {
                    continue;
                }
                // add one character for ':' delimiter
                $data = substr($opt, strlen($ext_opt) + 1);
                $options[$ext_key] = $this->parse_ext_option($data);
            }
        }

        if ($options['fixed']) {
            $ns = getNS($matches[0]);
            $file = noNS($matches[0]);
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

            if (isset($data['para']['info']) && $data['para']['info']) {
                if ($auth >= AUTH_READ) {
                    $renderer->doc .= $this->upload_info($data['file']);
                }      
            } else if ($auth >= AUTH_UPLOAD) {
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
        $html = '';
        
        if (!$this->getConf('show_upload')) {
            return;
        }
        
        if ($auth < AUTH_UPLOAD)
            return;

        $params = array();
        $params['id'] = 'upload_plugin';
        //$params['action'] = wl($ID);
        $params['method'] = 'post';
        $params['enctype'] = 'multipart/form-data';
        $params['class'] = 'upload__plugin';

        // Modification of the default dw HTML upload form
        $form = new Doku_Form($params);
        $form->startFieldset($lang['puzzleupload']);
        $form->addElement(formSecurityToken());
        $form->addHidden('page', hsc($ID));
        $form->addHidden('ns', hsc($ns));
        $form->addHidden('file', hsc($file));
        $form->addElement(form_makeFileField('upload', $lang['puzzlefile'], 'upload__file', 'block'));
        if ($options['renameable']) {
            // don't name this field here "id" because it is misinterpreted by DokuWiki if the upload form is not in media manager
            $form->addElement(form_makeTextField('new_name', '', $lang['txt_filename'] . ':', 'upload__name', 'block'));
        }

        if ($auth >= AUTH_DELETE) {
            if ($options['overwrite']) {
                //$form->addElement(form_makeCheckboxField('ow', 1, $lang['txt_overwrt'], 'dw__ow', 'check'));
                // circumvent wrong formatting in doku_form
                $form->addElement(
                        '<label class="check block" for="dw__ow">' .
                        '<span>' . $lang['txt_overwrt'] . '</span>' .
                        '<input type="checkbox" id="dw__ow" name="ow" value="1"/>' .
                        '</label>'
                );
            } else if ($options['fulloverwrite']) {
                $form->addHidden('ow', "1");
            }
        }

        if (isset($options['metadata'])) {
           foreach($options['metadata'] as $key => $attrs) {
               $name = action_plugin_upload::METADATA_PREFIX . hsc($key);
               switch($attrs['type']) {
                   case 'text':
                       $hkey = hsc($key);
                       $el = '<label for="dw__'.$hkey.'" class="block">' .
                         '<span>' . hsc($attrs['label']) . '</span>' .
                         '<textarea id="dw__'.$hkey.'" name="'.$name.'" rows="6" required="required"></textarea>' .
                         '</label><br/>';
                       break;
                   default:
                       $el = form_makeTextField($name, '', $attrs['label'], '', 'block', array('required' => 'required'));
                       break;
               }
               $form->addElement($el);
           }
        }

        $form->endFieldset();
        $form->addElement(form_makeButton('submit', '', $lang['btn_upload']));
        
        $html .= '<p>Zde můžete uploadovat vaši šifru ve formátu PDF (nezapomeňte též vyplnit řešení šifry).</p>';
        $html .= '<div class="upload_plugin"><p>' . NL;
        $html .= $form->getForm();
        $html .= '</p></div>' . NL;
        return $html;
    }
    
    private function parse_ext_option($string) {
        $data = array();
        foreach(split(';', $string) as $segment) {
            $segment = trim($segment);
            list($key, $value) = split('=', $segment);
            $key = trim($key);
            $value = trim($value);
            $parts = explode('.', $key);
            if (count($parts) > 1) {
                $key = $parts[0];      
                $type = $parts[1];
            } else {
                $key = $parts[0];
                $type = '.unknown';
            }

            $item = array();
            $item['label'] = $value;
            $item['type'] = $type;
            $data[$key] = $item;
        }
        return $data;
    }

    private function upload_info($media_id) {
        $metadata = $this->helper->get_metadata($media_id);
        if (!$metadata) {
            return '<p>Zatím jste šifru nenahráli.</p>';
        }

        $link = ml($media_id);
        $result = $metadata['result'];
        $solution = $metadata['solution'];
        $timestamp = date('Y-m-d H:i:s', $metadata['timestamp']);

        $html = '<p>';
        $html .= 'Vaše <a href="' . hsc($link) . '">odevzdaná šifra</a>, verze z '.$timestamp.', ';
        $html .= 'její tajenka je &bdquo;<span class="kachna-hider kachna-hidden">' . hsc($result) . '</span>&ldquo;. ';
        $html .= 'Níže je autorský postup řešení.';
        $html .= '</p>';
        $html .= '<pre class="kachna-hider kachna-hidden">';
        $html .= hsc($solution);
        $html .= '</pre>';
        
        return $html;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
