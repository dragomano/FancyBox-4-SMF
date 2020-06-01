<?php

/**
 * Class-FancyBox.php
 *
 * @package FancyBox 4 SMF
 * @link https://custom.simplemachines.org/mods/index.php?mod=3303
 * @author Bugo https://dragomano.ru/mods/fancybox-4-smf
 * @copyright 2012-2020 Bugo
 * @license https://opensource.org/licenses/gpl-3.0.html GNU GPLv3
 *
 * @version 0.7.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class FancyBox
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes', false);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false);
	}

	/**
	 * Подключаем используемые скрипты и стили
	 *
	 * @return void
	 */
	public static function loadTheme()
	{
		global $context, $settings, $modSettings, $txt;

		loadLanguage('FancyBox/');

		if (in_array($context['current_action'], array('helpadmin', 'printpage')) || (defined('WIRELESS') && WIRELESS))
			return;

		$context['html_headers'] .= '
	<link rel="stylesheet" type="text/css" href="' . $settings['default_theme_url'] . '/css/jquery.fancybox.min.css" />
	<link rel="stylesheet" type="text/css" href="' . $settings['default_theme_url'] . '/css/jquery.fancybox.custom.css" />';

		$context['insert_after_template'] .= '
		<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/jquery.fancybox.min.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			jQuery(document).ready(function($) {
				$("a[id^=link_]").addClass("fancybox").removeAttr("onclick").attr("data-fancybox", "gallery");' . (!empty($modSettings['fancybox_prepare']) ? '
				$("a.bbc_link").each(function() {
					var img_link = $(this);
					if (img_link.text() == "") {
						img_link
							.next()
							.attr("class", "bbc_link")
							.removeAttr("data-fancybox")
							.attr("href", img_link.attr("href"))
							.attr("target", "_blank");
						img_link.remove();
					}
				});' : '') . '
				$("div[id*=_footer]").each(function(){
					var id = $(this).attr("id");
					$("#" + id + " a[data-fancybox=gallery]").attr("data-fancybox", "gallery_" + id);
				});
				$(".fancybox").fancybox({
					buttons: [
						"zoom",
						"slideShow",
						"fullScreen",';

		if (!empty($modSettings['fancybox_thumbnails'])) {
			$context['insert_after_template'] .= '
						"thumbs",';
		}

		$context['insert_after_template'] .= '
						"close"
					],
					image: {
						preload: ' . (!empty($modSettings['fancybox_image_preload']) ? 'true' : 'false') . '
					},
					animationEffect: ' . (!empty($modSettings['fancybox_animation_effect']) ? '"' . $modSettings['fancybox_animation_effect'] . '"' : 'false') . ',
					animationDuration: ' . (isset($modSettings['fancybox_animation_speed']) ? (int) $modSettings['fancybox_animation_speed'] : 366) . ',
					transitionEffect: ' . (!empty($modSettings['fancybox_transition_effect']) ? '"' . $modSettings['fancybox_transition_effect'] . '"' : 'false') . ',
					slideShow: {
						autoStart: ' . (!empty($modSettings['fancybox_autoplay']) ? 'true' : 'false') . ',
						speed: ' . (isset($modSettings['fancybox_slideshow_speed']) ? (int) $modSettings['fancybox_slideshow_speed'] : 4000) . '
					},';

		if (!empty($modSettings['fancybox_show_link'])) {
			$context['insert_after_template'] .= '
					caption: function(instance, item) {
						var caption = $(this).data(\'caption\') || \'\';
						if (item.type === \'image\') {
							caption = (caption.length ? caption + \'<br \/>\' : \'\') + \'<a href="\' + item.src.replace(";image", "") + \'">' . $txt['fancy_download_link'] . '<\/a>\' ;
						}
						return caption;
					},';
		}

		if (!empty($modSettings['fancybox_thumbnails'])) {
			$context['insert_after_template'] .= '
					thumbs: {
						autoStart: true,
						axis: "x"
					},';
		}

		$context['insert_after_template'] .= '
					lang: "' . (isset($txt['fancy_thumbnails']) ? $txt['lang_dictionary'] : 'en') . '",
					i18n: {
						' . (isset($txt['fancy_thumbnails']) ? $txt['lang_dictionary'] : 'en') . ': {
							CLOSE: "' . $txt['find_close'] . '",
							NEXT: "' . $txt['fancy_button_next'] . '",
							PREV: "' . $txt['fancy_button_prev']. '",
							ERROR: "' . $txt['fancy_text_error']. '",
							PLAY_START: "' . $txt['fancy_slideshow_start'] . '",
							PLAY_STOP: "' . $txt['fancy_slideshow_pause'] . '",
							FULL_SCREEN: "' . $txt['fancy_full_screen'] . '",
							THUMBS: "' . $txt['fancy_thumbnails'] . '",
							DOWNLOAD: "' . $txt['fancy_button_download'] . '",
							SHARE: "' . $txt['fancy_button_share'] . '",
							ZOOM: "' . $txt['fancy_button_zoom'] . '"
						}
					}
				});
			});
		// ]]></script>';
	}

	/**
	 * Меняем обработку ББ-кода [img]
	 *
	 * @param array $codes
	 * @return void
	 */
	public static function bbcCodes(&$codes)
	{
		global $modSettings, $user_info, $settings, $txt;

		if (SMF == 'SSI' || (defined('WIRELESS') && WIRELESS) || empty($modSettings['enableBBC']) || empty($modSettings['fancybox_prepare']))
			return;

		$disabled = array();
		if (!empty($modSettings['disabledBBC'])) {
			foreach (explode(",", $modSettings['disabledBBC']) as $tag)
				$disabled[$tag] = true;
		}

		if (isset($disabled['img']))
			return;

		foreach ($codes as &$code) {
			if ($code['tag'] == 'img') {
				if (!empty($code['parameters'])) {
					$code['content'] = '<a href="$1" class="fancybox" title="{alt}" data-fancybox="topic"><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] : '$1') . '" alt="{alt}"{width}{height} /></a>';
				} else {
					$code['content'] = '<a href="$1" class="fancybox" data-fancybox="topic"><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] : '$1') . '" alt="" class="bbc_img" /></a>';
				}
			}
		}
	}

	/**
	 * Подключаем вкладку с настройками мода в админке
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['fancybox'] = array($txt['fancybox_settings']);
	}

	/**
	 * Подключаем настройки мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['fancybox'] = array('FancyBox', 'settings');
	}

	/**
	 * Обрабатываем настройки мода
	 *
	 * @return void
	 */
	public static function settings()
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $context['settings_title'] = $txt['fancybox_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=fancybox';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['fancybox'] = array('description' => $txt['fancybox_desc']);

		$initial_settings = array(
			'fancybox_animation_effect'  => 'zoom',
			'fancybox_animation_speed'   => 366,
			'fancybox_transition_effect' => 'fade',
			'fancybox_transition_speed'  => 366,
			'fancybox_slideshow_speed'   => 4000,
			'fancybox_thumbnails'        => true
		);

		foreach ($initial_settings as $setting => $value) {
			if (!isset($modSettings[$setting]))
				updateSettings(array($setting => $value));
		}

		$config_vars = array(
			array('check', 'fancybox_image_preload'),
			array('select', 'fancybox_animation_effect', $txt['fancybox_animation_effect_set']),
			array('int', 'fancybox_animation_speed'),
			array('select', 'fancybox_transition_effect', $txt['fancybox_transition_effect_set']),
			array('int', 'fancybox_transition_speed'),
			array('check', 'fancybox_autoplay'),
			array('int', 'fancybox_slideshow_speed'),
			array('check', 'fancybox_show_link'),
			array('check', 'fancybox_thumbnails'),
			array('check', 'fancybox_prepare'),
			array('check', 'fancybox_traffic', 'disabled' => empty($modSettings['fancybox_prepare']) ? 'disabled' : '')
		);

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=modsettings;sa=fancybox');
		}

		prepareDBSettingContext($config_vars);
	}
}
