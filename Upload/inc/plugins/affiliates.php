<?php
/**
 * Forum Affiliates Manager
 * Easily manage your forum's affiliates.
 *
 * Version: 1.1
 *
 * Author: Spencer
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Add our hook
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook("admin_config_menu", "affiliates_admin_nav");
	$plugins->add_hook("admin_config_permissions", "affiliates_admin_permissions");
	$plugins->add_hook("admin_config_action_handler", "affiliates_action_handler");
	$plugins->add_hook("admin_load", "affiliates_admin");
}
else
{
	global $templatelist;

	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	else
	{
		$templatelist = '';
	}

	$templatelist .= 'affiliates_list, affiliates_list_item, affiliates_list_empty';

	$plugins->add_hook("pre_output_page", "affiliates_run");
}

defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

function affiliates_info()
{
	return array(
		"name"			=> "Forum Affiliates Manager",
		"description"	=> "Easily manage your forum's affiliates.",
		"website"		=> "http://community.mybb.com/user-23387.html",
		"author"		=> "Spencer",
		"authorsite"	=> "http://community.mybb.com/user-23387.html",
		"version"		=> "1.1",
		"guid" 			=> "268b7d5d5bc2892de0f3aefcc82deb99",
		"compatibility" => "18*"
	);
}

function affiliates_activate()
{
	global $PL;
	$PL or require_once PLUGINLIBRARY;
	affiliates_deactivate();
	
	$PL->settings('affiliates', 'Forum Affiliates', 'Allows you to manage your forum\'s affiliates.', array(
			'dimensions'	=> array(
				'title'			=> 'Maximum Dimensions',
				'description'	=> 'What is the maximum affiliate image dimensions?',
				'optionscode'	=> 'text',
				'value'			=> '88x31'
			),
			'groups_ignore'	=> array(
				'title'			=> 'Disallowed Usergroups',
				'description'	=> 'Usergroups not allowed to view the affiliates (separate usergroup id\'s by a comma).',
				'optionscode'	=> 'groupselect',
				'value'			=> ''
			),
	));

	$PL->templates('affiliates', 'Forum Affiliates', array(
		'list'	=> '<br/><table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<thead> 
	<tr> 
		<td class="thead"> 
			<strong>{$lang->affiliates}</strong>
		</td> 
	</tr> 
</thead> 
<tbody> 
	<tr class="trow1"> 
		<td>
			{$list_affiliates}
		</td>
	</tr>
</tbody> 
</table>',
		'list_item'	=> '<span style="width:{$maxwidth}px;height:{$maxheight}px;float:left;margin-right:5px;margin-bottom: 2px;text-align:left;"><a href="{$mybb->settings[\'bburl\']}/index.php?action=affiliate&amp;id={$id}&amp;my_post_key={$mybb->post_code}"><img src="{$mybb->settings[\'uploadspath\']}/affiliates/{$affiliate[\'image\']}" alt="" width="auto" height="auto" title="{$affiliate[\'name\']}"></a></span>',
		'list_empty'	=> '{$lang->no_affiliates}',
	));

	change_admin_permission('config', 'affiliates', -1);
	
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('header', '#'.preg_quote('<navigation>').'#', "<navigation>\n<!--AFFILIATES-->");
}

function affiliates_deactivate()
{
	change_admin_permission('config', 'affiliates', -1);
	
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('header', '#'.preg_quote('<!--AFFILIATES-->')."(\r?)\n#", '', 0);
}

function affiliates_install()
{
	global $db;
	
	if(!$db->table_exists("affiliates"))
	{
		$db->write_query("
			CREATE TABLE ".TABLE_PREFIX."affiliates (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `active` int(11) NOT NULL,
			  `name` varchar(225) NOT NULL,
			  `link` varchar(225) NOT NULL,
			  `clicks` int(11) NOT NULL,
			  `image` varchar(225) NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM ;
		");
	}

	change_admin_permission('config', 'affiliates');
}

function affiliates_is_installed()
{
	global $db;
	
	return $db->table_exists("affiliates");
}

function affiliates_uninstall()
{
    global $PL, $db;
    $PL or require_once PLUGINLIBRARY;

	$db->drop_table("affiliates");

    $PL->settings_delete('affiliates');
    $PL->templates_delete('affiliates');
	
	change_admin_permission('config', 'affiliates', -1);
}

function affiliates_run(&$page)
{
	global $mybb;

	if(!is_member($mybb->settings['affiliates_groups_ignore']) && strpos($page, '<!--AFFILIATES-->') !== false)
	{
		if($mybb->get_input('action') == 'affiliate')
		{
			global $db;

			verify_post_check($mybb->get_input('my_post_key'));

			$query = $db->simple_select("affiliates", "id, link", "id='{$mybb->get_input('id', 1)}'");
			$affiliate = $db->fetch_array($query);

			if(empty($affiliate['id']) || empty($affiliate['link']))
			{
				error($lang->invalid_affiliate);
			}

			$db->update_query('affiliates', array('clicks' => "clicks+1"), "id='{$mybb->get_input('id', 1)}'", 1, true);
			
			header("Location: ".$affiliate['link']."");
		}

		global $templates, $lang, $theme;

		$lang->load("affiliates");

		$list_affiliates = '';

		$affiliates = $mybb->cache->read('affiliates');

		if($affiliates)
		{
			foreach($affiliates as $id => $affiliate)
			{
				$affiliate['name'] = htmlspecialchars_uni($affiliate['name']);
				$affiliate['image'] = htmlspecialchars_uni($affiliate['image']);
	
				list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
				eval("\$list_affiliates .= \"".$templates->get("affiliates_list_item")."\";");
			}
		}

		if(!$list_affiliates)
		{
			eval("\$list_affiliates = \"".$templates->get("affiliates_list_empty")."\";");
		}
		
		eval("\$affiliates = \"".$templates->get("affiliates_list")."\";");

		$page = str_replace('<!--AFFILIATES-->', $affiliates, $page);
	}
}

function affiliates_action_handler(&$action)
{
	$action['affiliates'] = array('active' => 'affiliates', 'file' => '');
}

function affiliates_admin_nav(&$sub_menu)
{
	global $mybb, $lang;

	$lang->load("affiliates", true);
		
	end($sub_menu);
	$key = (key($sub_menu))+10;

	if(!$key)
	{
		$key = '100';
	}
	
	$sub_menu[$key] = array('id' => 'affiliates', 'title' => 'Forum Affiliates', 'link' => "index.php?module=config-affiliates");
}

function affiliates_admin_permissions(&$admin_permissions)
{
  	global $db, $mybb, $lang;
		
	$lang->load("affiliates", true);
		
	$admin_permissions['affiliates'] = "Can manage forum forum affiliates?";
}

function affiliates_admin()
{
	global $mybb, $db, $page, $lang;
	
	$lang->load("affiliates", true);
	
	if($page->active_action != "affiliates")
	{
		return;
	}
	
	$page->add_breadcrumb_item($lang->affiliates);
	
	$sub_tabs['manage'] = array(
		'title' => $lang->manage_tab,
		'link' => "index.php?module=config-affiliates",
		'description' => $lang->manage_desc
	);

	$sub_tabs['add'] = array(
		'title' => $lang->add_tab,
		'link' => "index.php?module=config-affiliates&amp;action=add",
		'description' => $lang->add_desc
	);
	
	if($mybb->input['action'] == "edit")
	{
		$sub_tabs['edit'] = array(
			'title' => $lang->edit_tab,
			'link' => "index.php?module=config-affiliates",
			'description' => $lang->edit_desc
		);		
	}

	if($mybb->input['action'] == "delete")
	{
		$query = $db->simple_select("affiliates", "*", "id='".intval($mybb->input['id'])."'");
		$affiliate = $db->fetch_array($query);

		if(!$affiliate['id'])
		{
			flash_message($lang->error_invalid_partner, 'error');
			admin_redirect("index.php?module=config-affiliates");
		}
		
		if($mybb->input['no'])
		{
			admin_redirect("index.php?module=config-affiliates");
		}
		
		if($mybb->request_method == "post")
		{
			$affimg = $affiliate['image'];
			unlink(MYBB_ROOT.$mybb->settings['uploadspath'].'/affiliates/'.$affimg);
			
			$db->delete_query("affiliates", "id='{$affiliate['id']}'");

			affiliates_cache_update();

			flash_message($lang->success_affiliate_deleted, 'success');
			admin_redirect("index.php?module=config-affiliates");
		}
		else
		{
			$page->output_confirm_action("index.php?module=config-affiliates&amp;action=delete&id={$affiliate['id']}", $lang->affiliate_deletion_confirmation);
		}
		
		$page->output_footer();
	}
	
	if($mybb->input['action'] == "edit")
	{
		$page->output_header($lang->affiliates);
		$page->output_nav_tabs($sub_tabs, 'edit');
		
		$query = $db->simple_select("affiliates", "*", "id='".intval($mybb->input['id'])."'");
		$affiliate = $db->fetch_array($query);

		if(!$affiliate['id'])
		{
			flash_message($lang->error_invalid_affiliate, 'error');
			admin_redirect("index.php?module=config-affiliates");
		}
		
		if($mybb->request_method == "post")
		{
			list($width, $height) = @getimagesize($_FILES['image_upload']['tmp_name']);
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
			switch(strtolower($_FILES['image_upload']['type']))
			{
				case "image/gif":
				case "image/jpeg":
				case "image/x-jpg":
				case "image/x-jpeg":
				case "image/pjpeg":
				case "image/jpg":
				case "image/png":
				case "image/x-png":
					$img_type = 1;
				break;
				default:
					$img_type = 0;
				
			}
			if(!$mybb->input['name'])
			{
				$errors[] = $lang->error_invalid_name;
			}
			if(!preg_match("/^(https?:\/\/+[\w\-]+\.[\w\-]+)/i", $mybb->input['url']))
			{
				$errors[] = $lang->error_invalid_url;
			}
			if($_FILES['image_upload']['name'])
			{
				if(!$_FILES['image_upload'])
				{
					$errors[] = $lang->error_invalid_upload;						
				}
				if($img_type == 0)
				{
					$errors[] = $lang->error_invalid_file_type;
				}	
				if($width > $maxwidth || $height > $maxheight)
				{
					$errors[] = $lang->error_image_too_large = $lang->sprintf($lang->error_image_too_large, $maxwidth, $maxheight);
				}
			}
			if(!$errors)
			{
				if($_FILES['image_upload']['name'])
				{
					$filename = $_FILES['image_upload']['name'];
					$file_basename = substr($filename, 0, strripos($filename, '.'));
					$file_ext = substr($filename, strripos($filename, '.'));
					$filesize = $_FILES['image_upload']['size'];
					$allowed_file_types = array('.png','.jpg','.bmp','.gif');
					
					list($width, $height, $type) = @getimagesize($_FILES['image_upload']['tmp_name']);
					list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
					
					// delete old image
					$old_affimg = $affiliate['image'];
					unlink(MYBB_ROOT.$mybb->settings['uploadspath'].'/affiliates/'.$old_affimg);
					
					// upload new image
					$newfilename = random_str(12).$file_ext;
					@move_uploaded_file($_FILES['image_upload']['tmp_name'], MYBB_ROOT.$mybb->settings['uploadspath'].'/affiliates/'.$newfilename);
					
					$update = array(
						"name" => $db->escape_string($mybb->input['name']),
						"link" => $mybb->input['url'],
						"image" => $newfilename,
					);
					$db->update_query("affiliates", $update, "id={$mybb->input['id']}");

					affiliates_cache_update();

					flash_message($lang->success_affiliate_edited, 'success');
					admin_redirect("index.php?module=config-affiliates");
				}
				else
				{
					$update = array(
						"name" => $db->escape_string($mybb->input['name']),
						"link" => $mybb->input['url'],
					);
					$db->update_query("affiliates", $update, "id={$affiliate['id']}");

					affiliates_cache_update();

					flash_message($lang->success_affiliate_edited, 'success');
					admin_redirect("index.php?module=config-affiliates");
				}
			}
		}
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		
		$form = new Form("index.php?module=config-affiliates&amp;action=edit&amp;id={$affiliate['id']}", "post", "", 1);
		
		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
		
		$form_container = new FormContainer($lang->edit_affiliate_info);
		$form_container->output_row($lang->current_image, "", "<span style=\"width:{$maxwidth}px;height:{$maxheight}px;\"><img src=\".".$mybb->settings['uploadspath']."/affiliates/".htmlspecialchars_uni($affiliate['image'])."\" width=\"auto\" height=\"auto\" alt=\"#\"></span>", array('width' => 1));

		$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $affiliate['name'], $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->url." <em>*</em>", $lang->use_http, $form->generate_text_box('url', $affiliate['link'], $mybb->input['url'], array('id' => 'url')), 'url');
		$form_container->output_row($lang->upload_image." <em>*</em>", $lang->sprintf($lang->image_desc, $maxwidth, $maxheight), $form->generate_file_upload_box('image_upload', array('id' => 'image_upload')), 'image_upload');

		$form_container->end();
		$buttons[] = $form->generate_submit_button($lang->button_edit);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}
	
	if($mybb->input['action'] == "approve")
	{
		global $db;
		
		$array = array(
			"active" => 1
		);
		$db->update_query("affiliates", $array, "id={$mybb->input['id']}");

		affiliates_cache_update();

		flash_message($lang->affiliate_approved, 'success');
		admin_redirect("index.php?module=config-affiliates");
	}
	
	if($mybb->input['action'] == "unapprove")
	{
		global $db;
		
		$array = array(
			"active" => 0
		);
		$db->update_query("affiliates", $array, "id={$mybb->input['id']}");

		affiliates_cache_update();

		flash_message($lang->affiliate_unapproved, 'success');
		admin_redirect("index.php?module=config-affiliates");
	}
	
	if($mybb->input['action'] == "add")
	{
		$page->output_header($lang->affiliates);
		$page->output_nav_tabs($sub_tabs, 'add');

		if($mybb->request_method == "post")
		{
			list($width, $height) = @getimagesize($_FILES['image_upload']['tmp_name']);
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
			switch(strtolower($_FILES['image_upload']['type']))
			{
				case "image/gif":
				case "image/jpeg":
				case "image/x-jpg":
				case "image/x-jpeg":
				case "image/pjpeg":
				case "image/jpg":
				case "image/png":
				case "image/x-png":
					$img_type = 1;
				break;
				default:
					$img_type = 0;
				
			}
			if(!$_FILES['image_upload'])
			{
				$errors[] = $lang->error_invalid_upload;						
			}
			if(!$mybb->input['name'])
			{
				$errors[] = $lang->error_invalid_name;
			}
			if(!preg_match("/^(https?:\/\/+[\w\-]+\.[\w\-]+)/i", $mybb->input['url']))
			{
				$errors[] = $lang->error_invalid_url;
			}
			if($img_type == 0)
			{
				$errors[] = $lang->error_invalid_file_type;
			}	
			if($width > $maxwidth || $height > $maxheight)
			{
				$errors[] = $lang->error_image_too_large = $lang->sprintf($lang->error_image_too_large, $maxwidth, $maxheight);
			}
			elseif(!$errors)
			{
				$filename = $_FILES['image_upload']['name'];
				$file_ext = substr($filename, strripos($filename, '.'));
				$filesize = $_FILES['image_upload']['size'];
				
				$process_upload = random_str(12).$file_ext;
				@move_uploaded_file($_FILES['image_upload']['tmp_name'], MYBB_ROOT.$mybb->settings['uploadspath'].'/affiliates/'.$process_upload);

				$insert = array(
					"active" => 1,
					"name" => $db->escape_string($mybb->input['name']),
					"link" => $mybb->input['url'],
					"image" => $process_upload
				);
				$db->insert_query("affiliates", $insert);

				affiliates_cache_update();

				flash_message($lang->success_affiliate_added, 'success');
				admin_redirect("index.php?module=config-affiliates");				
			}
		}
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		
		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
		
		$form = new Form("index.php?module=config-affiliates&amp;action=add", "post", "", 1);
		
		$form_container = new FormContainer($lang->add_affiliate_info);
		$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc, $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->url." <em>*</em>", $lang->use_http, $form->generate_text_box('url', $mybb->input['url'], array('id' => 'url')), 'url');
		$form_container->output_row($lang->upload_image." <em>*</em>", $lang->sprintf($lang->image_desc, $maxwidth, $maxheight), $form->generate_file_upload_box('image_upload', array('id' => 'image_upload')), 'image_upload');

		$form_container->end();
		$buttons[] = $form->generate_submit_button($lang->button_add);
		$form->output_submit_wrapper($buttons);

		$form->end();
		
		$page->output_footer();
	}
	
	if(!$mybb->input['action'])
	{
		$page->output_header($lang->affiliates);
		$page->output_nav_tabs($sub_tabs, 'manage');
		
		$form = new Form("index.php?module=tools/pms&amp;action=delete", "post");
		
		$table = new Table;
		$table->construct_header("", array("colspan" => 1, "width" => "1%", "class" => "align_center"));
		$table->construct_header($lang->name, array("colspan" => 1));
		$table->construct_header($lang->preview, array("colspan" => 1, "width" => "13%", "class" => "align_center"));
		$table->construct_header($lang->clicks, array("colspan" => 1, "width" => "5%", "class" => "align_center"));
		$table->construct_header($lang->actions, array("colspan" => 1, "width" => "5%", "class" => "align_center"));
		
		$query = $db->simple_select("affiliates", "*", "", array("order_by" => "id"));
		
		while($affiliate = $db->fetch_array($query))
		{
			if($affiliate['active'] == 1)
			{
				$active = "<img src=\"styles/{$page->style}/images/icons/bullet_on.png\" title=\"{$lang->alt_enabled}\">";
			}
			else
			{
				$active = "<img src=\"styles/{$page->style}/images/icons/bullet_off.png\" title=\"{$lang->alt_disabled}\">";
			}
			
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['affiliates_dimensions']));
			
			$table->construct_cell($active, array("class" => "align_center"));
			$table->construct_cell("<a href=\"".$affiliate['link']."\" target=\"_blank\">".$affiliate['name']."</a>");
			$table->construct_cell("<span style=\"width:{$maxwidth}px;height:{$maxheight}px;\"><img src=\".".$mybb->settings['uploadspath']."/affiliates/".$affiliate['image']."\" width=\"auto\" height=\"auto\" alt=\"#\"></span>", array("class" => "align_center"));
			$table->construct_cell($affiliate['clicks'], array("class" => "align_center"));
			
			$popup = new PopupMenu("affiliate_{$affiliate['id']}", $lang->options);
			$popup->add_item($lang->edit_affiliate, "index.php?module=config-affiliates&amp;action=edit&amp;id={$affiliate['id']}");
			$popup->add_item($lang->delete_affiliate, "index.php?module=config-affiliates&amp;action=delete&amp;id={$affiliate['id']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->affiliate_deletion_confirmation}')");
			if($affiliate['active'] == 1)
			{
				$popup->add_item($lang->unapprove_affiliate, "index.php?module=config-affiliates&amp;action=unapprove&amp;id={$affiliate['id']}");
			}
			else
			{
				$popup->add_item($lang->approve_affiliate, "index.php?module=config-affiliates&amp;action=approve&amp;id={$affiliate['id']}");
			}
			$table->construct_cell($popup->fetch());
			$table->construct_row();
		}
		
		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->no_affiliates_found, array("colspan" => "6"));
			$table->construct_row();
			$table->output($lang->manage);
		}
		else
		{
			$table->output($lang->manage);
		}
		
		$form->end();
		
		$page->output_footer();
	}
	exit;
}

function affiliates_cache_update()
{
	global $db, $cache;

	$items = array();

	$query = $db->simple_select('affiliates', 'id, image, name', "active='1'");
	while($affiliate = $db->fetch_array($query))
	{
		$id = (int)$affiliate['id'];
		$items[$id] = $affiliate;
		unset($items[$id]['id']);
	}

	$cache->update('affiliates', $items);
}