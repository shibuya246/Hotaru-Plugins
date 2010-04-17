<?php
/**
 * name: Follow
 * description: Basic Follower/Following plugin
 * version: 0.1
 * folder: follow
 * class: Follow
 * type: Follow
 * hooks: install_plugin,admin_plugin_settings,admin_sidebar_plugin_settings, profile_navigation, theme_index_top, breadcrumbs, theme_index_main
 * author: shibuya246
 * authorurl: http://shibuya246.com
 *
 * PHP version 5
 *
 * LICENSE: Hotaru CMS is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * Hotaru CMS is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along
 * with Hotaru CMS. If not, see http://www.gnu.org/licenses/.
 * 
 * @category  Content Management System
 * @package   HotaruCMS
 * @author    shibuya246
 * @copyright Copyright (c) 2010, shibuya246
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link      http://www.hotarucms.org/
 */

class Follow
{

    /**
     * Add follow settings fields to the db.
     */
    public function install_plugin($h)
    {
        // Default settings
        $follow_settings = $h->getSerializedSettings();

	/**
	*
	* Type in your settings to be saved / retrieved from the db table
	* e.g. for a simple checked box  if (!isset($follow_settings['setting_var_to_save'])) { $follow_settings['setting_var_to_save'] = "checked"; }
	*
	*/

	$h->updateSetting('follow_settings', serialize($follow_settings));

	$this->create_table($h);
    }

    /**
     * Create db table for plugin
     */
    public function create_table($h)
    {
	# autoreader_campaign
        $exists = $h->db->table_exists("follow");
        if (!$exists) {
            $h->db->query ( "CREATE TABLE `" . DB_PREFIX . "follow` (                                
				follower_user_id int(20) NOT NULL default '0',
				following_user_id int(20) NOT NULL default '0',
                                lastactive datetime NOT NULL default '0000-00-00 00:00:00',
                                created_on datetime NOT NULL default '0000-00-00 00:00:00',
				PRIMARY KEY (follower_user_id, following_user_id)
                           ) ENGINE=" . DB_ENGINE . " DEFAULT CHARSET=" . DB_CHARSET . " COLLATE=" . DB_COLLATE . " COMMENT='Follow plugin'; "
            );
        }
	
    }


    /**
     * Profile menu link to "follow"
     */
    public function profile_navigation($h)
    {
	include_once(PLUGINS . 'follow/libs/follow_functions.php');
	$FollowFuncs = new FollowFuncs();
	
	 echo "<li><a href='" . $h->url(array('page'=>'followers', 'user'=>$h->vars['user']->name)) . "'>" . $h->lang['follow_list_followers'] . "</a> <small>(" . $FollowFuncs->getFollowCount($h, "follower") . ")</small></li>\n";
	 echo "<li><a href='" . $h->url(array('page'=>'following', 'user'=>$h->vars['user']->name)) . "'>" . $h->lang['follow_list_following'] . "</a> <small>(" . $FollowFuncs->getFollowCount($h, "following") . ")</small></li>\n";
	 
	 if ($h->vars['user']->name != $h->currentUser->name) {
	    // check if already following
	    $follow = $FollowFuncs->checkFollow($h, 'following');	    
	    if ($follow == 0)
		echo "<li><a href='" . $h->url(array('page'=>'follow', 'user'=>$h->vars['user']->name)) . "'>" . $h->lang['follow_follow_user'] . "</a></li>\n";
	    else
		echo "<li><a href='" . $h->url(array('page'=>'unfollow', 'user'=>$h->vars['user']->name)) . "'>" . $h->lang['follow_unfollow_user'] . "</a></li>\n";
	 }
    }


    /**
     * Determine page and get user details
     */
    public function theme_index_top($h)
    {
        $user = $h->cage->get->testUsername('user');
        if (!$user) { $user = $h->currentUser->name; }

	$follow_page = false;

	switch ($h->pageName)
	{ 
	    case 'followers':
		$follow_page = true;
		$h->pageTitle = $h->lang['follow_list_followers'] . "[delimiter]" . $user;
		break;
	    case 'following':
		$follow_page = true;
		$h->pageTitle = $h->lang['follow_list_following'] . "[delimiter]" . $user;
		break;
	    case 'follow':
	    case 'unfollow':
		$follow_page = true;
		$h->pageTitle = $h->lang['follow_list_following'] . "[delimiter]" . $user;
		break;
	}


	// set page types & create UserAuth and MessagingFuncs objects
        if ($follow_page) {
	    $h->pageType = 'user';  // this setting hides the posts filter bar
	    $h->subPage = 'user';
	    
	    include_once(PLUGINS . 'follow/libs/follow_functions.php');
	    $FollowFuncs = new FollowFuncs();

	    // create a user object and fill it with user info (user being viewed)
            $h->vars['user'] = new UserAuth();
            $h->vars['user']->getUserBasic($h, 0, $user);

	    switch ($h->pageName)
	    {
		case 'followers':
		    $query = $FollowFuncs->getFollowUsers($h, 'follower');
		    $h->vars['follow_list'] = $h->pagination($query, count($query), 20);
		    // how to also include the latest actvitiy for this person and a follow/unfollow button
		    break;
		case 'following':
		    $query = $FollowFuncs->getFollowUsers($h, 'following');
		    $h->vars['follow_list'] = $h->pagination($query, count($query), 20);
		    break;
		case 'follow':
		case 'unfollow':
		    $h->pageName == "follow" ? $result = $FollowFuncs->updateFollow($h, "follow", $h->vars['user']->id) : $result = $FollowFuncs->updateFollow($h, "unfollow", $h->vars['user']->id);		   
		    if ($result == "follow") {
			$h->messages[$h->lang['follow_newfollow']] = 'green';
		    } elseif ($result == "unfollow") {
			$h->messages[$h->lang['follow_unfollow']] = 'green';
		    }
		    else
			$h->messages[$h->lang['follow_unfollow']] = 'red';
		    $query = $FollowFuncs->getFollowUsers($h, 'following');
		    $h->vars['follow_list'] = $h->pagination($query, count($query), 20);
		    break;
		}
	}
    }

    /**
     * Breadcrumbs for follow pages
     */
    public function breadcrumbs($h)
    {
        $user = $h->cage->get->testUsername('user');
        if (!$user) { $user = $h->currentUser->name; }

        switch ($h->pageName)
        {
            case 'followers':
                return "<a href='" . $h->url(array('user'=>$user)) . "'>" . $user . "</a> &raquo; " . $h->lang['follow_list_followers'];
                break;
            case 'following':
		return "<a href='" . $h->url(array('user'=>$user)) . "'>" . $user . "</a> &raquo; " . $h->lang['follow_list_following'];
		break;
	    case 'follow':
	    case 'unfollow':
                return $h->lang['follow_list_following'];
                break;            
        }
    }

    /**
     * Display pages
     */
    public function theme_index_main($h)
    {
        if (isset($h->vars['user']->id) && ($h->currentUser->id != $h->vars['user']->id)) { return false; }

        switch ($h->pageName)
        {
            case 'followers':		
                $h->displayTemplate('follow_followers');
                return true;
                break;
            case 'following':
                $h->displayTemplate('follow_followers');
                return true;
                break;
            case 'follow':
	    case 'unfollow':
                $h->displayTemplate('follow_followers');
                return true;
                break;
        }
    }
  
}
?>