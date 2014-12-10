<?php
/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
/**
 * Merges output from Twitter and FB plugins
 *
 * Usage instructions:
 *
 * 0. Install and configure Facebook and Twitter plugins.
 *
 * 1. Make sure this plugin has higher ordering than those plugins.
 *
 */

namespace ValoaApplication\Plugins;

use Libvaloa\Debug;
use Webvaloa\Cache;
use Webvaloa\Configuration;

class PluginSomeCombinePlugin extends \Webvaloa\Plugin
{

    public function onBeforeController()
    {
        $configuration = new Configuration;
        $cache = new Cache;

        // Both feeds must be set
        if ( !isset($this->view->_twitterFeed) || !isset($this->view->_facebookFeed) ) {
            Debug::__print('Feeds not found, aborting merge');

            return;
        }

        // Try loading from cache
        if ($tmp = $cache->combinedFeed) {
            $this->view->_combinedFeed = $tmp;

            // Unset old feeds
            unset($this->view->_facebookFeed);
            unset($this->view->_twitterFeed);

            Debug::__print($this->view->_combinedFeed);

            return;
        }

        Debug::__print('Combining feeds');

        // Collect twitter feeds
        foreach ($this->view->_twitterFeed as $k => $v) {
            $time = strtotime($v->created_at);
            $feeds[$time] = $v;
            $feeds[$time]->_type = 'twitter';
        }

        // Collect facebook feeds
        foreach ($this->view->_facebookFeed as $k => $v) {
            $time = strtotime($v->created);
            $feeds[$time] = $v;
            $feeds[$time]->_type = 'facebook';
        }

        // Sort feeds by timestamp
        krsort($feeds);

        $cache->combinedFeed = $feeds;
        $this->view->_combinedFeed = $feeds;

        // Unset old feeds
        unset($this->view->_facebookFeed);
        unset($this->view->_twitterFeed);

        Debug::__print($this->view->_combinedFeed);
    }

}
