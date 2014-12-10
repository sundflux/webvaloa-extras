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

namespace ValoaApplication\Plugins;

// Libvaloa classes
use Libvaloa\Debug;

// Webvaloa classes
use Webvaloa\User;

// Standard classes

/**
 * Plugin for making subscriptions based on roles.
 *
 * Write user_id, role_id and subscription time to role_subscription table
 * and this plugin removes access rights if 'expires' timestamp expires.
 */
class LoginSubscriptionPlugin extends \Webvaloa\Plugin
{

    public function __construct()
    {
        // Leave some room for configuration options

        // Delete items from role_subscription after expire?
        $this->deleteSubscription = true;
    }

    public function onAfterController()
    {
        if (isset($_SESSION["UserID"])) {
            // Check for any role subscriptions
            $db = \Webvaloa\Webvaloa::DBConnection();

            $query = "
                SELECT role_id
                FROM role_subscription
                WHERE expires IS NOT NULL
                AND expires < NOW()
                AND user_id = ?";

            $stmt = $db->prepare($query);
            $stmt->set($_SESSION["UserID"]);

            try {
                $stmt->execute();

                foreach ($stmt as $row) {
                    if (isset($row->role_id)) {
                        $roles[] = $row->role_id;
                    }
                }

                // Found expired subscriptions
                if (isset($roles)) {
                    $user = new User($_SESSION["UserID"]);

                    // Unset them
                    foreach ($roles as $k => $v) {
                        if ($user->hasRole($v)) {
                            // Remove role
                            $user->deleteRole($v);

                            if (!$this->deleteSubscription) {
                                continue;
                            }

                            // Remove from subscription table too
                            $_query = "
                                DELETE FROM role_subscription
                                WHERE user_id = ?
                                AND role_id = ?";

                            $tmp = $db->prepare($_query);
                            $tmp->set($_SESSION["UserID"]);
                            $tmp->set((int) $v);
                            $tmp->execute();
                        }
                    }

                    Debug::__print('Removed roles:');
                    Debug::__print($roles);
                }

            } catch (Exception $e) {
                Debug::__print($e->getMessage());
            }
        }
    }

}
