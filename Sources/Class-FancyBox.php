<?php

/**
 * Class-FancyBox.php
 *
 * @package FancyBox 4 SMF
 * @link https://custom.simplemachines.org/mods/index.php?mod=3303
 * @author Bugo https://dragomano.ru/mods/fancybox-4-smf
 * @copyright 2012-2022 Bugo
 * @license https://opensource.org/licenses/gpl-3.0.html GNU GPLv3
 *
 * @version 1.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

final class FancyBox
{
	public function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes#', false, __FILE__);
		add_integration_function('integrate_attach_bbc_validate', __CLASS__ . '::attachBbcValidate#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
	}

	public function loadTheme()
	{
		global $context, $modSettings, $txt;

		loadLanguage('FancyBox/');

		if (SMF === 'BACKGROUND' || SMF === 'SSI' || in_array($context['current_action'], ['helpadmin', 'printpage']))
			return;

		loadCSSFile('https://cdn.jsdelivr.net/npm/@fancyapps/ui@4/dist/fancybox.css', ['external' => true]);

		loadJavaScriptFile('https://cdn.jsdelivr.net/npm/@fancyapps/ui@4/dist/fancybox.umd.js', ['external' => true]);

		addInlineJavaScript('
		Fancybox.bind("[data-fancybox]", {
			Toolbar: {
				display: [
					{ id: "prev", position: "center" },
					{ id: "counter", position: "center" },
					{ id: "next", position: "center" },
					"zoom",
					"slideshow",
					"fullscreen",' . (empty($modSettings['fancybox_show_download_link']) ? '' : '
					"download",') . (empty($modSettings['fancybox_thumbnails']) ? '' : '
					"thumbs",') . '
					"close",
				],
			},' . (empty($modSettings['fancybox_thumbnails']) ? '
			Thumbs: {
				autoStart: false,
			},' : '') . '
			l10n: {
				CLOSE: "' . $txt['find_close'] . '",
				NEXT: "' . $txt['fancy_button_next'] . '",
				PREV: "' . $txt['fancy_button_prev'] . '",
				MODAL: "' . $txt['fancy_text_modal'] . '",
				ERROR: "' . $txt['fancy_text_error'] . '",
				IMAGE_ERROR: "' . $txt['fancy_image_error'] . '",
				ELEMENT_NOT_FOUND: "' . $txt['fancy_element_not_found'] . '",
				AJAX_NOT_FOUND: "' . $txt['fancy_ajax_not_found'] . '",
				AJAX_FORBIDDEN: "' . $txt['fancy_ajax_forbidden'] . '",
				IFRAME_ERROR: "' . $txt['fancy_iframe_error'] . '",
				TOGGLE_ZOOM: "' . $txt['fancy_toggle_zoom'] . '",
				TOGGLE_THUMBS: "' . $txt['fancy_toggle_thumbs'] . '",
				TOGGLE_SLIDESHOW: "' . $txt['fancy_toggle_slideshow'] . '",
				TOGGLE_FULLSCREEN: "' . $txt['fancy_toggle_fullscreen'] . '",
				DOWNLOAD: "' . $txt['fancy_download'] . '"
			}
		});
		let attachments = document.querySelectorAll(".attachments_top a");
		attachments && attachments.forEach(function (item) {
			let id = item.parentNode.parentNode.parentNode.getAttribute("id");
			item.removeAttribute("onclick");
			item.setAttribute("data-fancybox", "gallery_" + id);
		});', true);

		if (!empty($modSettings['fancybox_prepare_img']) && !empty($modSettings['fancybox_save_url_img'])) {
			addInlineJavaScript('
		let linkImages = document.querySelectorAll("a.bbc_link");
		linkImages && linkImages.forEach(function (item) {
			if (! item.textContent) {
				let imgLink = item.nextElementSibling;
				imgLink.classList.add("bbc_link");
				imgLink.removeAttribute("data-fancybox");
				imgLink.setAttribute("href", item.getAttribute("href"));
				imgLink.setAttribute("target", "_blank");
				item.parentNode.removeChild(item);
			}
		});', true);
		}
	}

	public function bbcCodes(array &$codes)
	{
		global $modSettings, $user_info, $txt, $settings;

		if (! $this->shouldItWork())
			return;

		foreach ($codes as &$code) {
			if ($code['tag'] === 'img') {
				$code['validate'] = function(&$tag, &$data, $disabled, $params) use ($modSettings, $user_info, $txt, $settings) {
					$url = iri_to_url(strtr($data, array('<br>' => '')));
					$url = parse_iri($url, PHP_URL_SCHEME) === null ? '//' . ltrim($url, ':/') : get_proxied_url($url);

					if (!empty($modSettings['fancybox_show_thumb_for_img']) && empty($params['{width}']) && !empty($modSettings['attachmentThumbWidth'])) {
						$params['{width}'] = ' width="' . $modSettings['attachmentThumbWidth'] . '"';
					}

					$showGuestImage = !empty($modSettings['fancybox_traffic']) && $user_info['is_guest'];
					$alt = !empty($params['{alt}']) || $showGuestImage ? ' alt="' . ($showGuestImage ? 'traffic.gif' : $params['{alt}']) . '"' : ' alt=""';
					$title = !empty($params['{title}']) || $showGuestImage ? ' title="' . ($showGuestImage ? $txt['fancy_click'] : $params['{title}']) . '"' : '';
					$caption = ' data-caption="' . (!empty($params['{title}']) ? $params['{title}'] : (!empty($params['{alt}']) ? $params['{alt}'] : '')) . '"';

					$data = isset($disabled[$tag['tag']]) ? $url : '<a data-src="' . $url . '" data-fancybox="topic"' . $caption . '><img src="' . ($showGuestImage ? $settings['default_images_url'] . '/traffic.gif' : $url) . '"' . $alt . $title . $params['{width}'] . $params['{height}'] . ' class="bbc_img" loading="lazy"></a>';
				};
			}
		}

		unset($code);
	}

	public function attachBbcValidate(string &$returnContext, array $currentAttachment, array $tag, string $data, array $disabled, array $params)
	{
		global $smcFunc, $modSettings, $user_info, $settings, $txt;

		if (! $this->shouldItWork('attach'))
			return;

		if ($params['{display}'] === 'embed') {
			$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $currentAttachment['name']) . '"';
			$caption = ' data-caption="' . (!empty($params['{alt}']) ? $params['{alt}'] : $currentAttachment['name']) . '"';
			$title = !empty($data) ? ' title="' . $smcFunc['htmlspecialchars']($data) . '"' : '';

			if (!empty($currentAttachment['is_image'])) {
				if (empty($params['{width}']) && empty($params['{height}'])) {
					$returnContext = '<a data-fancybox="topic" data-src="' . $currentAttachment['href'] . ';image"' . $caption . '><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] . '"' : (!empty($modSettings['fancybox_show_thumb_for_attach']) ? $currentAttachment['thumbnail']['href'] : $currentAttachment['href']) . '"' . $title) . $alt . ' class="bbc_img" loading="lazy"></a>';
				} else {
					$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"': '';
					$height = !empty($params['{height}']) ? 'height="' . $params['{height}'] . '"' : '';
					$returnContext = '<a data-fancybox="topic" data-src="' . $currentAttachment['href'] . ';image"' . $caption . '><img src="' . (!empty($modSettings['fancybox_traffic']) && $user_info['is_guest'] ? $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] . '"' : $currentAttachment['href'] . ';image"' . $title . $width . $height) . $alt . ' class="bbc_img" loading="lazy"></a>';
				}
			}
		}
	}

	public function adminAreas(array &$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['fancybox'] = [$txt['fancybox_settings']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search)
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=fancybox'];
	}

	public function modifyModifications(array &$subActions)
	{
		$subActions['fancybox'] = [$this, 'settings'];
	}

	/**
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $context['settings_title'] = $txt['fancybox_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=fancybox';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['fancybox'] = ['description' => $txt['fancybox_desc']];

		$txt['fancybox_show_thumb_for_img_subtext'] = sprintf($txt['fancybox_show_thumb_for_img_subtext'], $scripturl . '?action=admin;area=manageattachments;sa=attachments#attachmentThumbWidth');

		$config_vars = [
			['check', 'fancybox_show_download_link'],
			['check', 'fancybox_thumbnails'],
			['check', 'fancybox_prepare_img'],
			['check', 'fancybox_show_thumb_for_img', 'subtext' => $txt['fancybox_show_thumb_for_img_subtext'], 'disabled' => empty($modSettings['fancybox_prepare_img'])],
			['check', 'fancybox_save_url_img', 'disabled' => empty($modSettings['fancybox_prepare_img'])],
			['check', 'fancybox_prepare_attach'],
			['check', 'fancybox_show_thumb_for_attach', 'subtext' => $txt['fancybox_show_thumb_for_attach_subtext'], 'disabled' => empty($modSettings['fancybox_prepare_attach'])],
			['check', 'fancybox_traffic', 'disabled' => empty($modSettings['fancybox_prepare_img']) && empty($modSettings['fancybox_prepare_attach']) ? 'disabled' : ''],
		];

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

	private function shouldItWork(string $tag = 'img'): bool
	{
		global $modSettings, $context;

		if (SMF === 'BACKGROUND' || SMF === 'SSI' || empty($modSettings['enableBBC']) || empty($modSettings['fancybox_prepare_' . $tag]))
			return false;

		if (!empty($modSettings['disabledBBC']) && in_array($tag, explode(',', $modSettings['disabledBBC'])))
			return false;

		if (in_array($context['current_action'], ['helpadmin', 'printpage']) || $context['current_subaction'] === 'showoperations')
			return false;

		return true;
	}
}
