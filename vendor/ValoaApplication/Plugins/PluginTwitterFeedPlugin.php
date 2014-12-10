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
 * Twitter feed Plugin.
 *
 * Usage instructions:
 *
 * 0. See this this page how to set up Twitter application and tokens etc:
 * http://stackoverflow.com/questions/12916539/simplest-php-example-for-retrieving-user-timeline-with-twitter-api-version-1-1/15314662#15314662
 *
 * 1. Add your Facebook App ID and secret to configuration table with keys:
 *
 * twitter_oauth_access_token
 * twitter_oauth_access_token_secret
 * twitter_consumer_key
 * twitter_consumer_secret
 * twitter_screen_name   <- username here whos feed you wanna fetch
 *
 * 2. Install the plugin with Extension manager
 *
 */

namespace ValoaApplication\Plugins;

use Libvaloa\Debug;
use Webvaloa\Cache;
use Webvaloa\Configuration;
use Webvaloa\Helpers\Twitter;

class PluginTwitterFeedPlugin extends \Webvaloa\Plugin
{

    public function onBeforeController()
    {
        $configuration = new Configuration;
        $cache = new Cache;

        if (!isset($configuration->twitter_oauth_access_token->value)) {
            return;
        }

        if ($tmp = $cache->twitterFeed) {
            $this->view->_twitterFeed = $tmp;

            Debug::__print('Loaded from cache');
            Debug::__print($this->view->_twitterFeed);

            return;
        }

        $settings = array(
            'oauth_access_token' => $configuration->twitter_oauth_access_token->value,
            'oauth_access_token_secret' => $configuration->twitter_oauth_access_token_secret->value,
            'consumer_key' => $configuration->twitter_consumer_key->value,
            'consumer_secret' => $configuration->twitter_consumer_secret->value
        );

        $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
        $getfield = '?screen_name=' . $configuration->twitter_screen_name->value;
        $requestMethod = 'GET';

        $twitter = new Twitter($settings);
        $response = $twitter->setGetfield($getfield)
            ->buildOauth($url, $requestMethod)
            ->performRequest();

        $this->view->_twitterFeed = json_decode($response);
        $cache->twitterFeed = $this->view->_twitterFeed;

        Debug::__print('Fetched from ' . $url);
        Debug::__print($this->view->_twitterFeed);
    }

}
