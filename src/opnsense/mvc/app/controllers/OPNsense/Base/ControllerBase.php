<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Base;

use OPNsense\Core\Config;
use Phalcon\Mvc\Controller;
use Phalcon\Translate\Adapter\Gettext;

/**
 * Class ControllerBase implements core controller for OPNsense framework
 * @package OPNsense\Base
 */
class ControllerBase extends ControllerRoot
{
    /**
     * translate a text
     * @return Gettext
     */
    public function getTranslator($cnf)
    {
        $lang = 'en_US';

        foreach ($cnf->object()->system->children() as $key => $node) {
            if ($key == 'language') {
                $lang = $node->__toString();
                break;
            }
        }

        $lang_encoding = $lang . '.UTF-8';

        $ret = new Gettext(array(
            'directory' => '/usr/local/share/locale',
            'defaultDomain' => 'OPNsense',
            'locale' => $lang_encoding,
        ));

        /* this isn't being done by Phalcon */
        putenv('LANG=' . $lang_encoding);

        return $ret;
    }

    /**
     * convert xml form definition to simple data structure to use in our Volt templates
     *
     * @param $xmlNode
     * @return array
     */
    private function parseFormNode($xmlNode)
    {
        $result = array();
        foreach ($xmlNode as $key => $node) {
            switch ($key) {
                case "tab":
                    if (!array_key_exists("tabs", $result)) {
                        $result['tabs'] = array();
                    }
                    $tab = array();
                    $tab[] = $node->attributes()->id;
                    $tab[] = $node->attributes()->description;
                    if (isset($node->subtab)) {
                        $tab["subtabs"] = $this->parseFormNode($node);
                    } else {
                        $tab[] = $this->parseFormNode($node);
                    }
                    $result['tabs'][] = $tab ;
                    break;
                case "subtab":
                    $subtab = array();
                    $subtab[] = $node->attributes()->id;
                    $subtab[] = $node->attributes()->description;
                    $subtab[] = $this->parseFormNode($node);
                    $result[] = $subtab;
                    break;
                case "field":
                    // field type, containing attributes
                    $result[] = $this->parseFormNode($node);
                    break;
                case "help":
                case "hint":
                case "label":
                    // translate text items if gettext is enabled
                    if (function_exists("gettext")) {
                        $result[$key] = gettext((string)$node);
                    } else {
                        $result[$key] = (string)$node;
                    }

                    break;
                default:
                    // default behavior, copy in value as key/value data
                    $result[$key] = (string)$node;
                    break;
            }
        }

        return $result;
    }

    /**
     * parse an xml type form
     * @param $formname
     * @return array
     * @throws \Exception
     */
    public function getForm($formname)
    {
        $class_info = new \ReflectionClass($this);
        $filename = dirname($class_info->getFileName()) . "/forms/".$formname.".xml" ;
        if (!file_exists($filename)) {
            throw new \Exception('form xml '.$filename.' missing') ;
        }
        $formXml = simplexml_load_file($filename);
        if ($formXml === false) {
            throw new \Exception('form xml '.$filename.' not valid') ;
        }

        return $this->parseFormNode($formXml);
    }

    /**
     * Default action. Set the standard layout.
     */
    public function initialize()
    {
        // set base template
        $this->view->setTemplateBefore('default');
    }

    /**
     * shared functionality for all components
     * @param $dispatcher
     * @return bool
     * @throws \Exception
     */
    public function beforeExecuteRoute($dispatcher)
    {
        // only handle input validation on first request.
        if (!$dispatcher->wasForwarded()) {
            // Authentication
            // - use authentication of legacy OPNsense.
            if (!$this->doAuth()) {
                return false;
            }

            // check for valid csrf on post requests
            if ($this->request->isPost() && !$this->security->checkToken()) {
                // post without csrf, exit.
                return false;
            }

            // REST type calls should be implemented by inheriting ApiControllerBase.
            // because we don't check for csrf on these methods, we want to make sure these aren't used.
            if ($this->request->isHead() ||
                $this->request->isPut() ||
                $this->request->isDelete() ||
                $this->request->isPatch() ||
                $this->request->isOptions()) {
                throw new \Exception('request type not supported');
            }
        }

        // include csrf for volt view rendering.
        $this->view->setVars([
            'csrf_tokenKey' => $this->security->getTokenKey(),
            'csrf_token' => $this->security->getToken()
        ]);

        // link menu system to view, append /ui in uri because of rewrite
        $menu = new Menu\MenuSystem();

        // add interfaces to "Interfaces" menu tab... kind of a hack, may need some improvement.
        $cnf = Config::getInstance();

        // set translator
        $this->view->setVar('lang', $this->getTranslator($cnf));

        $ifarr = array();
        foreach ($cnf->object()->interfaces->children() as $key => $node) {
            $ifarr[$key] = $node;
        }
        ksort($ifarr);
        $ordid = 0;
        foreach ($ifarr as $key => $node) {
            $menu->appendItem('Interfaces', $key, array(
                'url' => '/interfaces.php?if='. $key,
                'order' => ($ordid++),
                'visiblename' => $node->descr ? $node->descr : strtoupper($key)
            ));
        }
        unset($ifarr);

        $this->view->menuSystem = $menu->getItems("/ui".$this->router->getRewriteUri());

        // set theme in ui_theme template var, let template handle its defaults (if there is no theme).
        if ($cnf->object()->theme != null && !empty($cnf->object()->theme) &&
            is_dir('/usr/local/opnsense/www/themes/'.(string)$cnf->object()->theme)
        ) {
            $this->view->ui_theme = $cnf->object()->theme;
        }

        // append ACL object to view
        $this->view->acl = new \OPNsense\Core\ACL();
    }
}
