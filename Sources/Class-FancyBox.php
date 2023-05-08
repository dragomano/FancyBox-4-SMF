<?php

/**
 * Class-FancyBox.php
 *
 * @package FancyBox 4 SMF
 * @link https://custom.simplemachines.org/mods/index.php?mod=3303
 * @author Bugo https://dragomano.ru/mods/fancybox-4-smf
 * @copyright 2012-2023 Bugo
 * @license https://opensource.org/licenses/gpl-3.0.html GNU GPLv3
 *
 * @version 1.2.3
 */

if (!defined('SMF'))
	die('No direct access...');

final class FancyBox
{
	public function hooks()
	{
		add_integration_function('integrate_pre_css_output', __CLASS__ . '::preCssOutput#', false, __FILE__);
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes#', false, __FILE__);
		add_integration_function('integrate_attach_bbc_validate', __CLASS__ . '::attachBbcValidate#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
	}

	/**
	 * @hook integrate_pre_css_output
	 */
	public function preCssOutput()
	{
		global $modSettings;

		if ($this->shouldItWork() === false && $this->shouldItWork('attach') === false && empty($modSettings['fancybox_prepare_attachments']))
			return;

		echo "\n\t" . '<link rel="preload" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4/dist/fancybox.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
	}

	/**
	 * @hook integrate_load_theme
	 */
	public function loadTheme()
	{
		global $modSettings, $txt;

		loadLanguage('FancyBox/');

		if ($this->shouldItWork() === false && $this->shouldItWork('attach') === false && empty($modSettings['fancybox_prepare_attachments']))
			return;

		loadCSSFile('https://cdn.jsdelivr.net/npm/@fancyapps/ui@4/dist/fancybox.css', ['external' => true]);

		loadJavaScriptFile('https://cdn.jsdelivr.net/npm/@fancyapps/ui@4/dist/fancybox.umd.js', ['external' => true, 'defer' => true]);

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
		});' . (empty($modSettings['fancybox_prepare_attachments']) ? '' : '
		let attachments = document.querySelectorAll(".attachments_top a");
		attachments && attachments.forEach(function (item) {
			item.removeAttribute("onclick");
			item.setAttribute("data-fancybox", "topic");
		});'), true);

		if (!empty($modSettings['fancybox_prepare_img']) && !empty($modSettings['fancybox_save_url_img'])) {
			addInlineJavaScript('
		let linkImages = document.querySelectorAll("a.bbc_link");
		linkImages && linkImages.forEach(function (item) {
			if (! item.textContent) {
				let imgLink = item.nextElementSibling;
				if (imgLink) {
					imgLink.classList.add("bbc_link");
					imgLink.removeAttribute("data-fancybox");
					imgLink.setAttribute("href", item.getAttribute("href"));
					imgLink.setAttribute("target", "_blank");
					item.parentNode.removeChild(item);
				}
			}
		});', true);
		}
	}

	/**
	 * @hook integrate_bbc_codes
	 */
	public function bbcCodes(array &$codes)
	{
		global $modSettings, $user_info, $txt, $settings;

		if ($this->shouldItWork() === false)
			return;

		foreach ($codes as &$code) {
			if ($code['tag'] === 'img') {
				$code['validate'] = function($tag, &$data, $disabled, $params) use ($modSettings, $user_info, $txt, $settings) {
					$url = iri_to_url(strtr(trim($data), array('<br>' => '', ' ' => '%20')));
					$url = parse_iri($url, PHP_URL_SCHEME) === null ? '//' . ltrim($url, ':/') : get_proxied_url($url);

					if (!empty($modSettings['fancybox_show_thumb_for_img']) && empty($params['{width}']) && !empty($modSettings['attachmentThumbWidth'])) {
						$params['{width}'] = ' width="' . $modSettings['attachmentThumbWidth'] . '"';
					}

					$title = empty($params['{title}']) ? '' : $params['{title}'];
					$alt = empty($params['{alt}']) ? '' : $params['{alt}'];
					$src = $url;

					if ($this->showGuestImage()) {
						$title = ' title="' . $txt['fancy_click'] . '"';
						$alt = 'traffic.gif';
						$src = $settings['default_images_url'] . '/traffic.gif';
					}

					$caption = ' data-caption="' . (empty($title) ? $alt : $title) . '"';

					$link = '<a data-fancybox="topic" data-src="' . $url . '" data-thumb="' . $url . '"' . $caption . '>{img}</a>';
					$img  = '<img alt="' . $alt . '" class="bbc_img" loading="lazy" src="' . $src . '"' . $title . $params['{width}'] . $params['{height}'] . '>';

					$data = isset($disabled[$tag['tag']]) ? $url : str_replace('{img}', $img, $link);
				};

				break;
			}
		}

		unset($code);
	}

	/**
	 * @hook integrate_attach_bbc_validate
	 */
	public function attachBbcValidate(string &$returnContext, array $currentAttachment, array $tag, string $data, ?array $disabled, array $params)
	{
		global $smcFunc, $modSettings, $settings, $txt;

		if ($this->shouldItWork('attach') === false)
			return;

		if ($params['{display}'] === 'embed') {
			$alt = empty($params['{alt}']) ? $currentAttachment['name'] : $params['{alt}'];
			$title = empty($data) ? '' : (' title="' . $smcFunc['htmlspecialchars']($data) . '"');
			$caption = ' data-caption="' . $alt . '"';

			if (!empty($currentAttachment['is_image']) && !empty($currentAttachment['href'])) {
				$width  = empty($params['{width}'])  ? '' : ' width="' . $params['{width}'] . '"';
				$height = empty($params['{height}']) ? '' : ' height="' . $params['{height}'] . '"';

				if ($this->showGuestImage()) {
					$src = $settings['default_images_url'] . '/traffic.gif" title="' . $txt['fancy_click'] . '"';
				} elseif (empty($width) && empty($height)) {
					$src = (empty($modSettings['fancybox_show_thumb_for_attach']) ? $currentAttachment['href'] : ($currentAttachment['thumbnail']['href'] ?? $currentAttachment['href'])) . '"' . $title;
				} else {
					$src = $currentAttachment['href'] . ';image"' . $title . $width . $height;
				}

				$link = '<a data-fancybox="topic" data-thumb="' . ($currentAttachment['thumbnail']['href'] ?? $currentAttachment['href']) . '" data-src="' . $currentAttachment['href'] . ';image"' . $caption . '>%s</a>';
				$img  = '<img alt="' . $alt . '" class="bbc_img" loading="lazy" src="' . $src . '>';

				$returnContext = sprintf($link, $img);
			}
		}
	}

	/**
	 * @hook integrate_admin_areas
	 */
	public function adminAreas(array &$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['fancybox'] = [$txt['fancybox_settings']];
	}

	/**
	 * @hook integrate_admin_search
	 */
	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search)
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=fancybox'];
	}

	/**
	 * @hook integrate_modify_modifications
	 */
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

		$txt['fancybox_show_thumb_for_img_subtext'] = sprintf(
			$txt['fancybox_show_thumb_for_img_subtext'], $scripturl . '?action=admin;area=manageattachments;sa=attachments#attachmentThumbWidth'
		);

		$config_vars = [
			['check', 'fancybox_prepare_img'],
			['check', 'fancybox_show_thumb_for_img', 'subtext' => $txt['fancybox_show_thumb_for_img_subtext'], 'disabled' => empty($modSettings['fancybox_prepare_img'])],
			['check', 'fancybox_save_url_img', 'disabled' => empty($modSettings['fancybox_prepare_img'])],
			'',
			['check', 'fancybox_prepare_attach'],
			['check', 'fancybox_show_thumb_for_attach', 'subtext' => $txt['fancybox_show_thumb_for_attach_subtext'], 'disabled' => empty($modSettings['fancybox_prepare_attach'])],
			'',
			['check', 'fancybox_prepare_attachments'],
			['check', 'fancybox_show_download_link'],
			['check', 'fancybox_thumbnails'],
			['check', 'fancybox_traffic', 'disabled' => empty($modSettings['fancybox_prepare_img']) && empty($modSettings['fancybox_prepare_attach']) ? 'disabled' : ''],
		];

		if ($return_config)
			return $config_vars;

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['fancybox_desc'];

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=modsettings;sa=fancybox');
		}

		prepareDBSettingContext($config_vars);
	}

	private function showGuestImage(): bool
	{
		global $modSettings, $user_info;

		return !empty($modSettings['fancybox_traffic']) && $user_info['is_guest'];
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
