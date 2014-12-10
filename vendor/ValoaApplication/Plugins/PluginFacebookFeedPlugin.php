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
 * Facebook Feed Plugin.
 *
 * Usage instructions:
 *
 * 0. Set up your app at Facebook developer site
 *
 * 1. Add your Facebook App ID and secret to configuration table with keys:
 *
 * facebook_app_id
 * facebook_app_secret
 * facebook_feed_page_id <- your page id! will fetch this wall
 *
 * 2. git clone https://github.com/facebook/facebook-php-sdk.git to your /vendor directory.
 *
 * 3. Install the plugin.
 */

namespace ValoaApplication\Plugins;

// Facebook libraries
require_once LIBVALOA_EXTENSIONSPATH . "/facebook-php-sdk/src/base_facebook.php";
require_once LIBVALOA_EXTENSIONSPATH . "/facebook-php-sdk/src/facebook.php";

use stdClass;
use Libvaloa\Debug;
use Webvaloa\Cache;
use Webvaloa\Configuration;

class PluginFacebookFeedPlugin extends \Webvaloa\Plugin
{

    public function onBeforeController()
    {
        $configuration = new Configuration;
        $cache = new Cache;

        if (!isset($configuration->facebook_feed_page_id->value)) {
            return;
        }

        if ($tmp = $cache->facebookFeed) {
            $this->view->_facebookFeed = $tmp;

            Debug::__print('Loaded from cache');
            Debug::__print($this->view->_facebookFeed);

            return;
        }

        $config = array(
            'appId' => $configuration->facebook_app_id->value,
            'secret' => $configuration->facebook_app_secret->value,
            'allowSignedRequest' => false
        );

        $pageId = $configuration->facebook_feed_page_id->value;
        $facebook = new \Facebook($config);

        // Format the facebook data a bit
        $facebookFeed = $facebook->api("/" . $pageId . "/feed");
        $facebookFeed = $facebookFeed['data'];

        foreach ($facebookFeed as $k => $v) {
            // Extract data from Fb output
            $post = new stdClass;

            // Post content
            if (isset($v['message'])) {
                $post->message = nl2br(self::formatUrls($v['message']));
            }

            // From
            $post->from = $v['from']['name'];
            $post->fromId = $v['from']['id'];

            // Link
            if (isset($v['link']) && !empty($v['link'])) {
                $post->link = $v['link'];
                $post->linkTitle = $v['name'];
            }

            // Image
            if (isset($v['picture']) && !empty($v['picture'])) {
                $post->image = $v['picture'];
            }

            // Created
            $post->created = $v['created_time'];

            // Permalink
            $parts = explode('_', $v['id']);
            $post->permalink = "http://www.facebook.com/permalink.php?id={$parts[0]}&v=wall&story_fbid=$parts[1]";

            $this->view->_facebookFeed[] = $post;
        }

        Debug::__print('Facebook feed:');
        Debug::__print($this->view->_facebookFeed);

        $cache->facebookFeed = $this->view->_facebookFeed;
    }

    private static function formatUrls($text)
    {
        $regexp = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

        if (preg_match($regexp, $text, $url)) {
            return preg_replace($regexp, "<a href=\"{$url[0]}\">{$url[0]}</a> ", $text);
        }

        return $text;
    }

}
