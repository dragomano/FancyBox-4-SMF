<?php

/**
 * Class-FancyBox.php
 *
 * @package FancyBox 4 SMF
 * @link https://custom.simplemachines.org/mods/index.php?mod=3303
 * @author Bugo https://dragomano.ru/mods/fancybox-4-smf
 * @copyright 2012-2021 Bugo
 * @license https://opensource.org/licenses/gpl-3.0.html GNU GPLv3
 *
 * @version 1.0
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
	public function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes#', false, __FILE__);
		add_integration_function('integrate_attach_bbc_validate', __CLASS__ . '::attachBbcValidate#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
	}

	/**
	 * Подключаем используемые скрипты и стили
	 *
	 * @return void
	 */
	public function loadTheme()
	{
		global $context, $modSettings, $txt;

		loadLanguage('FancyBox/');

		if (SMF === 'BACKGROUND' || SMF === 'SSI' || in_array($context['current_action'], array('helpadmin', 'printpage')))
			return;

		loadCSSFile('jquery.fancybox.min.css');
		loadCSSFile('jquery.fancybox.custom.css');

		loadJavaScriptFile('jquery.fancybox.min.js', array('minimize' => true), 'jquery_fancybox');
		addInlineJavaScript('
		jQuery(document).ready(function ($) {
			$("a[id^=link_]").addClass("fancybox").removeAttr("onclick").attr("data-fancybox", "gallery");
			$(".post a > img.atc_img").each(function() {
				let id = $(this).parent("a").attr("id");
				$(this).parent("a").attr("id", id + "_post");
			});' . (!empty($modSettings['fancybox_prepare_img']) && !empty($modSettings['fancybox_save_url_img']) ? '
			$("a.bbc_link").each(function() {
				let img_link = $(this);
				if (!img_link.text()) {
					img_link
						.next()
						.attr("class", "bbc_link")
						.removeAttr("data-fancybox")
						.attr("href", img_link.attr("href"))
						.attr("target", "_blank")
					img_link.remove();
				}
			});' : '') . '
			$("div[id*=_footer]").each(function() {
				let id = $(this).attr("id");
				$("#" + id + " a[data-fancybox=gallery]").attr("data-fancybox", "gallery_" + id);
			});
			$(".fancybox").fancybox({
				buttons: [
					"zoom",
					"slideShow",
					"fullScreen",' . (!empty($modSettings['fancybox_thumbnails']) ? '
					"thumbs",' : '') . '
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
				},' . (!empty($modSettings['fancybox_show_link']) ? '
				caption: function(instance, item) {
					let caption = $(this).data(\'caption\') || \'\';
					if (item.type === \'image\') {
						caption = (caption.length ? caption + \'<br>\' : \'\') + \'<a href="\' + item.src.replace(";image", "") + \'">' . $txt['fancy_download_link'] . '<\/a>\' ;
					}
					return caption;
				},' : '') . (!empty($modSettings['fancybox_thumbnails']) ? '
				thumbs: {
					autoStart: true,
					axis: "x"
				},' : '') . '
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
		});', true);
	}

	/**
	 * Меняем обработку ББ-кода [img]
	 *
	 * @param array $codes
	 * @return void
	 */
	public function bbcCodes(&$codes)
	{
		global $modSettings, $user_info, $txt, $settings;

		if ($this->isItShouldNotWork())
			return;

		foreach ($codes as &$code) {
			if ($code['tag'] === 'img') {
				if ($code['content'] === '$1') {
					$code['validate'] = function(&$tag, &$data, $disabled, $params) use ($modSettings, $user_info, $txt, $settings) {
						$url = strtr($data, array('<br>' => ''));
						$url = parse_url($url, PHP_URL_SCHEME) === null ? '//' . ltrim($url, ':/') : get_proxied_url($url);

						$showGuestImage = !empty($modSettings['fancybox_traffic']) && $user_info['is_guest'];
						$alt = !empty($params['{alt}']) || $showGuestImage ? ' alt="' . ($showGuestImage ? 'traffic.gif' : $params['{alt}']) . '"' : ' alt="' . $url . '"';
						$title = !empty($params['{title}']) || $showGuestImage ? ' title="' . ($showGuestImage ? $txt['fancy_click'] : $params['{title}']) . '"' : '';

						$data = isset($disabled[$tag['tag']]) ? $url : '<a href="' . $url . '" class="fancybox" data-fancybox="topic"><img src="' . ($showGuestImage ? $settings['default_images_url'] . '/traffic.gif' : $url) . '"' . $alt . $title . $params['{width}'] . $params['{height}'] . ' class="bbc_img" loading="lazy"></a>';
					};
				} else {
					if (!empty($code['parameters']['width']) || !empty($code['parameters']['height'])) {
						$code['content'] = '<a href="$1" class="fancybox" data-fancybox="topic"><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] . '" alt="traffic.gif"' : '$1" title="{title}" alt="{alt}"{width}{height}') . ' class="bbc_img" loading="lazy"></a>';
					} else {
						$code['content'] = '<a href="$1" class="fancybox" data-fancybox="topic"><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] : '$1') . '" alt="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? 'traffic.gif' : '$1') . '" class="bbc_img" loading="lazy"></a>';
					}
				}
			}
		}

		unset($code);
	}

	/**
	 * Меняем обработку ББ-кода [attach] для изображений
	 *
	 * @param string $returnContext
	 * @param array $currentAttachment
	 * @param array $tag
	 * @param string $data
	 * @param array $disabled
	 * @param array $params
	 * @return void
	 */
	public function attachBbcValidate(&$returnContext, $currentAttachment, $tag, $data, $disabled, $params)
	{
		global $smcFunc, $modSettings, $user_info, $settings, $txt;

		if ($this->isItShouldNotWork('attach'))
			return;

		if ($params['{display}'] === 'embed') {
			$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $currentAttachment['name']) . '"';
			$title = !empty($data) ? ' title="' . $smcFunc['htmlspecialchars']($data) . '"' : '';

			if (!empty($currentAttachment['is_image'])) {
				if (empty($params['{width}']) && empty($params['{height}'])) {
					$returnContext = '<a href="' . $currentAttachment['href'] . ';image" id="link_' . $currentAttachment['id'] . '"><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] . '"' : (!empty($modSettings['fancybox_show_thumb_for_attach']) ? $currentAttachment['thumbnail']['href'] : $currentAttachment['href']) . '"' . $title) . $alt . ' class="bbc_img" loading="lazy"></a>';
				} else {
					$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"': '';
					$height = !empty($params['{height}']) ? 'height="' . $params['{height}'] . '"' : '';
					$returnContext = '<a href="' . $currentAttachment['href'] . ';image" id="link_' . $currentAttachment['id'] . '"><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] . '"' : $currentAttachment['href'] . ';image"' . $title . $width . $height) . $alt . ' class="bbc_img" loading="lazy"></a>';
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
	public function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['fancybox'] = array($txt['fancybox_settings']);
	}

	/**
	 * Легкий доступ к настройкам мода через быстрый поиск в админке
	 *
	 * @param array $language_files
	 * @param array $include_files
	 * @param array $settings_search
	 * @return void
	 */
	public function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array(array($this, 'settings'), 'area=modsettings;sa=fancybox');
	}

	/**
	 * Подключаем настройки мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public function modifyModifications(&$subActions)
	{
		$subActions['fancybox'] = array($this, 'settings');
	}

	/**
	 * Обрабатываем настройки мода
	 *
	 * @param boolean $return_config
	 * @return array|void
	 */
	public function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $context['settings_title'] = $txt['fancybox_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=fancybox';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['fancybox'] = array('description' => $txt['fancybox_desc']);

		$add_settings = [];
		if (!isset($modSettings['fancybox_animation_effect']))
			$add_settings['fancybox_animation_effect'] = 'zoom';
		if (!isset($modSettings['fancybox_animation_speed']))
			$add_settings['fancybox_animation_speed'] = 366;
		if (!isset($modSettings['fancybox_transition_effect']))
			$add_settings['fancybox_transition_effect'] = 'fade';
		if (!isset($modSettings['fancybox_transition_speed']))
			$add_settings['fancybox_transition_speed'] = 366;
		if (!isset($modSettings['fancybox_slideshow_speed']))
			$add_settings['fancybox_slideshow_speed'] = 4000;
		if (!isset($modSettings['fancybox_thumbnails']))
			$add_settings['fancybox_thumbnails'] = true;
		if (!empty($add_settings))
			updateSettings($add_settings);

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
			array('check', 'fancybox_prepare_img'),
			array('check', 'fancybox_save_url_img', 'disabled' => empty($modSettings['fancybox_prepare_img'])),
			array('check', 'fancybox_prepare_attach'),
			array('check', 'fancybox_show_thumb_for_attach', 'subtext' => $txt['fancybox_show_thumb_for_attach_subtext'], 'disabled' => empty($modSettings['fancybox_prepare_attach'])),
			array('check', 'fancybox_traffic', 'disabled' => empty($modSettings['fancybox_prepare_img']) && empty($modSettings['fancybox_prepare_attach']) ? 'disabled' : '')
		);

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=modsettings;sa=fancybox');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * @param string $tag
	 * @return bool
	 */
	private function isItShouldNotWork($tag = 'img')
	{
		global $modSettings, $context;

		return SMF === 'BACKGROUND' || SMF === 'SSI' || empty($modSettings['enableBBC']) || empty($modSettings['fancybox_prepare_' . $tag]) || in_array($context['current_action'], array('helpadmin', 'printpage')) || $context['current_subaction'] === 'showoperations' || (!empty($modSettings['disabledBBC']) && in_array($tag, explode(',', $modSettings['disabledBBC'])));
	}
}
