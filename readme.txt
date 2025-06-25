=== Duplicate & Translate ===
Contributors: judicaelpoumay
Donate link: https://buymeacoffee.com/jpoumay
Tags: duplicate, translate, openai, gutenberg, translation
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2
License URI: https://github.com/JudicaelPoumay/Duplicate-And-Translate/blob/main/LICENSE

Easily duplicate any post, then automatically translate it into your desired language with AI.

== Description ==

Duplicate & Translate is a WordPress plugin that allows you to easily duplicate a post and translate it into another language while keeping layout and formatting intact using an AI provider like OpenAI.

**Features**

*   **One-click duplication and translation:** Quickly create a translated version of any post.
*   **Compatible with most LLM providers:** OpenAI, Google, Anthropic, DeepSeek
*   **Gutenberg Block Editor Support:** Translates content from various core Gutenberg blocks while keeping layout intact, including headings, paragraphs, lists, quotes, buttons, and images (alt text).
*   **AI Integration:** Utilizes the power of language models for accurate translations.
*   **Configurable Target Language:** Choose from a list of predefined languages to translate your content into.
*   **Secure API Key Storage:** Your API key is stored securely in the WordPress database.
*   **User-friendly Progress Page:** A real-time progress page keeps you informed about the translation process.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/duplicate-and-translate` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to **Settings > Duplicate & Translate**.
4.  Enter your OpenAI API key (or other supported provider).
5.  Select your desired target language.
6.  Click **Save Settings**.

== Usage ==

1.  Go to **Posts > All Posts**.
2.  Hover over the post you want to translate.
3.  Click the **Duplicate & Translate** link.
4.  A new tab will open, showing the translation progress.
5.  Once the translation is complete, you will be given a link to edit the new translated post, which will be saved as a draft.

== Frequently Asked Questions ==

= What do I need to use this plugin? =

You need an API key from a supported AI provider like OpenAI, Google, Anthropic, or DeepSeek. You can configure this in the plugin's settings page.

= What content gets translated? =

The plugin is designed to work with the Gutenberg Block Editor and translates (while keeping the layout intact) the content from core blocks like headings, paragraphs, lists, quotes, buttons, and even the alt text of images.

= Can I choose the translation language? =

Yes, you can select a target language in the plugin's settings and the translation page.

== Screenshots ==

1. The settings page where you configure your API key and target language.
2. The Duplicate & Translate button.
3. The translation page configuration.
4. The translation page progress bar showing the translation in real-time.
5. The translation page with a finalized translation and a button that leads to the translated post.

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
* Initial release.

== Author ==

This plugin is developed by Judicael Poumay.

*   [LinkedIn](https://www.linkedin.com/in/judicael-poumay/)
*   [Website](https://thethoughtprocess.xyz/)
*   [Email](mailto:pro.judicael.poumay@gmail.com) 

== Third-Party Services ==

This plugin utilizes third-party AI services to perform translations. When you initiate a translation, the content of your post (text from blocks, title, and alt tags) is sent to the AI provider you have configured in the settings.

*   **Providers:** OpenAI, Google (Gemini), Anthropic (Claude), DeepSeek.
*   **Data Sent:** The text content of the post being translated and the target language. No personal user data is sent.
*   **Terms and Privacy:** You must have an account with the selected provider and provide your own API key. Use of these services is subject to their respective terms and privacy policies.
    *   [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy)
    *   [Google AI Privacy Policy](https://policies.google.com/privacy)
    *   [Anthropic Privacy Policy](https://www.anthropic.com/privacy)
    *   [DeepSeek Privacy Policy](https://www.deepseek.com/en/privacy) 