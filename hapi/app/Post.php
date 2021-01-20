<?php

namespace App;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

use Rennokki\QueryCache\Traits\QueryCacheable;

class Post extends Model
{
    // public static $names = array("Alice", "Bob", "Carol", "Dave", "Eve", "Francis", "Grace", "Hans", "Isabella", "Jason", "Kate", "Louis", "Margaret", "Nathan", "Olivia", "Paul", "Queen", "Richard", "Susan", "Thomas", "Uma", "Vivian", "Winnie", "Xander", "Yasmine", "Zach");
    // public static $modifiers = array("Angry", "Baby", "Crazy", "Diligent", "Excited", "Fat", "Greedy", "Hungry", "Interesting", "Jolly", "Kind", "Little", "Magic", "Naïve", "Old", "PKU", "Quiet", "Rich", "Superman", "THU", "Undefined", "Valuable", "Wifeless", "Xenial", "Young", "Zombie"); // X is now Xenial

    public static $names = array("Alice", "Bob", "Carol", "Dave", "Eve", "Francis", "Grace", "Hans", "Isabella", "Jason", "Kate", "Louis", "Margaret", "Nathan", "Olivia", "Paul", "Queen", "Richard", "Susan", "Thomas", "Uma", "Vivian", "Winnie", "Xander", "Yasmine", "Zach");
    public static $modifiers = array("Angry", "Baby", "Crazy", "Diligent", "Excited", "Fat", "Greedy", "Hungry", "Interesting", "Jolly", "Kind", "Little", "Magic", "Naïve", "Old", "PKU", "Quiet", "Rich", "Superman", "THU", "Undefined", "Valuable", "Wifeless", "Xenial", "Young", "Zombie"); // X is now Xenial; P is now PKU
    
    /**
     * Get comments for this post.
     */
    public function comments() { return $this->hasMany('App\Comment'); }

    /**
     * Get favorited relationships for this post.
     */
    public function favorites() { return $this->hasMany('App\Favorite'); }

    /**
     * Get the real underlying author property.
     */
    public function getUserIdAttribute() {
        try {
            $user_id = (int) Crypt::decryptString($this->user_id_enc);
        } catch(DecryptException $e) {
            Log::warning("Failed to decrypt userId attribute for Post ID # " . $this->id);
            $user_id = 0;
        }

        return $user_id;
    }

    /**
     * Not using mutators for user_id as they will be manually cryptd
     * and created when saved...
     */


    /**
     * Usermap rebuilding (used for Alice, Bob, ...)
     */
    public function rebuildUserMap($bridge = false) {
        $cDatas = $this->comments;
        $author = (int) Crypt::decryptString($this->user_id_enc);

        $userMap = [$author => "洞主"];

        $nameCount = count(self::$names);
        $modifierCount = count(self::$modifiers);

        // Go through each and build a name.
        // For nth (1, 2, ...) name, increment $i.
        $i = 0;

        foreach($cDatas as $cData) {
            $author = (int) Crypt::decryptString($cData->user_id_enc);

            if(isset($userMap[$author])) continue;

            $assignName = "Unknown";

            // If not, assign name based on index.
            // -removed-
            $index1 = intval(floor($i / count(self::$names))) - 1;
            $index2 = $i % $nameCount;
            if ($index1 < $modifierCount) {
                if($bridge) {
                    // -removed-
                } else {
                    $assignName = ($index1 < 0 ? "" : self::$modifiers[$index1] . " ") . self::$names[$index2];
                }

                $i++;
            } else {
                $assignName = "You Win " . $i;
                $i++;
            }

            $userMap[$author] = $assignName;

            // Verify if userMap fits - if not, force a reset
            // if($cData->user_nickname != $assignName) 
        }

        // After building, save userMap
        $this->user_map_enc = Crypt::encryptString(serialize($userMap));

        return $userMap;
    }

    /**
     * Usermap append use: get next name
     * This is a quick util function but you should not call this directly from
     * a loop. Use this as a one-off
     */
    public function getNextName($offset = 0, $bridge = false) {
        $i = $offset;
        $nameCount = count(self::$names);
        $modifierCount = count(self::$modifiers);

        $index1 = intval(floor($i / count(self::$names))) - 1;
        $index2 = $i % $nameCount;
        if ($index1 < $modifierCount) {
            if($bridge) {
                // -removed-
            } else {
                $assignName = ($index1 < 0 ? "" : self::$modifiers[$index1] . " ") . self::$names[$index2];
            }
        } else {
            $assignName = "You Win " . $i;
        }

        return $assignName;
    }
}
