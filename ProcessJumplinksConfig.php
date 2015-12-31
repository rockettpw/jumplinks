<?php

/**
 * ProcessJumplinks - a ProcessWire Module by Mike Rockett
 * Manage permanent and temporary redirects. Uses named wildcards and mapping collections.
 *
 * Compatible with ProcessWire 2.6.1+
 *
 * Copyright (c) 2015, Mike Rockett. All Rights Reserved.
 * Licence: MIT License - http://mit-license.org/
 *
 * @see https://github.com/rockettpw/jumplinks [Documentation]
 * @see https://mods.pw/92 [Modules Directory Page]
 * @see https://processwire.com/talk/topic/8697-jumplinks/ [Support/Discussion Thread]
 * @see https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=L8F6FFYK6ENBQ [PayPal Donation]
 */

class ProcessJumplinksConfig extends ModuleConfig
{
    /**
     * Documentation link
     * @const string
     */
    const DOCS_HREF = 'https://github.com/rockettpw/jumplinks';
    const SUPPORT_HREF = 'https://processwire.com/talk/topic/8697-jumplinks/';

    /**
     * Given a fieldtype, create, populate, and return an Inputfield
     * @param  string $fieldNameId
     * @param  array  $meta
     * @return Inputfield
     */
    protected function buildInputField($fieldNameId, $meta)
    {
        $field = wire('modules')->get($fieldNameId);

        foreach ($meta as $metaNames => $metaInfo) {
            $metaNames = explode('+', $metaNames);
            foreach ($metaNames as $metaName) {
                $field->$metaName = $metaInfo;
            }
        }

        return $field;
    }

    /**
     * Get default condifguration, automatically passed to input fields.
     * @return array
     */
    public function getDefaults()
    {
        return array(
            '_schemaVersion' => 1, // Initial schema
            'enhancedWildcardCleaning' => false,
            'legacyDomain' => '',
            'enable404Monitor' => false,
            'disableIndexPhpMatching' => false,
            'moduleDebug' => false,
            'redirectsImported' => false,
            'statusCodes' => '200 301 302',
            'wildcardCleaning' => 'fullClean',
        );
    }

    /**
     * Render input fields on config Page.
     * @return string
     */
    public function getInputFields()
    {
        // Inject assets
        $moduleAssetPath = "{$this->config->urls->ProcessJumplinks}Assets";
        $this->config->scripts->add("{$moduleAssetPath}/ProcessJumplinks.min.js");
        $this->config->styles->add("{$moduleAssetPath}/ProcessJumplinks.css");

        // Add JS config data
        $this->config->js('pjModuleAdmin', true);
        $this->config->js('pjOldRedirectsInstalled', $this->modules->isInstalled('ProcessRedirects'));

        // Start inputfields
        $inputfields = parent::getInputfields();

        // Wildcard Cleaning Fieldset
        $fieldset = $this->buildInputField('InputfieldFieldset', array(
            'label' => $this->_('Wildcard Cleaning'),
        ));

        // Wildcard Cleaning
        $fieldset->add($this->buildInputField('InputfieldRadios', array(
            'name+id' => 'wildcardCleaning',
            'description' => $this->_("When set to 'Full Clean', each wildcard in a destination path will be automatically cleaned, or 'slugged', so that it is lower-case, and uses hyphens as word separators."),
            'notes' => sprintf($this->_("**Note:** It's recommended that you keep this set to 'Full Clean', unless you have a module installed that uses different path formats (such as TitleCase with underscores or hyphens). **[Learn more about Wildcard Cleaning](%s/Configuration#wildcard-cleaning)**"), self::DOCS_HREF),
            'options' => array(
                'fullClean' => $this->_('Full Clean (default, recommended)'),
                'semiClean' => $this->_("Clean, but don't change case"),
                'noClean' => $this->_("Don't clean at all (not recommended)"),
            ),
            'columnWidth' => 50,
            'collapsed' => Inputfield::collapsedNever,
            'skipLabel' => Inputfield::skipLabelHeader,
        )));

        // Enhanced Wildcard Cleaning
        $fieldset->add($this->buildInputField('InputfieldCheckbox', array(
            'name+id' => 'enhancedWildcardCleaning',
            'label' => $this->_('Enhanced Wildcard Cleaning'),
            'description' => $this->_('When enabled, wildcard cleaning goes a step further by means of breaking and hyphenating TitleCase wildcards, as well as those that contain abbreviations (ex: NASALaunch). Examples below.'),
            'label2' => $this->_('Use Enhanced Wildcard Cleaning'),
            'notes' => $this->_("**Examples:** 'EnvironmentStudy' would become 'environment-study' and 'NASALaunch' would become 'nasa-launch'.\n**Note:** This feature only works when Wildcard Cleaning is enabled."),
            'columnWidth' => 50,
            'collapsed' => Inputfield::collapsedNever,
            'autocheck' => true,
        )));

        $inputfields->add($fieldset);

        // Legacy Domain Fieldset
        $fieldset = $this->buildInputField('InputfieldFieldset', array(
            'label' => $this->_('Legacy Domain'),
            'description' => sprintf($this->_('Only use this if you are performing a slow migration to ProcessWire, and would still like your visitors to access old content moved to a new location, like a subdomain or folder, for example. [Learn more about how this feature works](%s/Configuration#legacy-domain).'), self::DOCS_HREF),
            'collapsed' => Inputfield::collapsedYes,
        ));

        // Legacy Domain Name
        $fieldset->add($this->buildInputField('InputfieldText', array(
            'name+id' => 'legacyDomain',
            'columnWidth' => 50,
            'description' => $this->_('Attempt any requested, unresolved Source paths on a legacy domain/URL.'),
            'notes' => $this->_("Enter a *full*, valid domain/URL. **Source Path won't be cleaned upon redirect**."),
            'placeholder' => $this->_('Examples: "http://legacy.domain.com/" or "http://domain.com/old/"'),
            'collapsed' => Inputfield::collapsedNever,
            'skipLabel' => Inputfield::skipLabelHeader,
            'spellcheck' => 'false',
        )));

        // Legacy Domain Status Codes
        $fieldset->add($this->buildInputField('InputfieldText', array(
            'name+id' => 'statusCodes',
            'columnWidth' => 50,
            'description' => $this->_('Only redirect if a request to it yields one of these HTTP status codes:'),
            'notes' => $this->_('Separate each code with a space. **[Use Default](#resetLegacyStatusCodes)**'),
            'collapsed' => Inputfield::collapsedNever,
            'skipLabel' => Inputfield::skipLabelHeader,
            'spellcheck' => 'false',
        )));

        $inputfields->add($fieldset);

        // Log Not Found Hits
        $inputfields->add($this->buildInputField('InputfieldCheckbox', array(
            'name+id' => 'enable404Monitor',
            'label' => $this->_('404 Monitor'),
            'description' => $this->_("If you'd like to monitor and log 404 hits so that you can later create jumplinks for them, check the box below."),
            'label2' => $this->_('Log 404 hits to the database'),
            'notes' => $this->_("This log will be displayed on the Jumplinks setup page in a separate tab (limited to the last 100).\n**Note:** Turning this off will not delete any existing records from the database."),
            'collapsed' => Inputfield::collapsedBlank,
            'autocheck' => true,
        )));

        // Disable index.php matching
        $inputfields->add($this->buildInputField('InputfieldCheckbox', array(
            'name+id' => 'disableIndexPhpMatching',
            'label' => $this->_('Disable index.php matching'),
            'description' => $this->_("Jumplinks supports the matching and redirecting `/index.php/oldpage` requests. When requests like these are made, Jumplinks automatically redirects them to `/index.php.pwpj/oldpage` - this is part of the 'magic sauce' that allows Jumplinks to handle these requests. However, the feature isn't very helpful when you don't need it, as those automatic redirects remain when jumplinks have not been defined for them.\n\nIf you don't need this feature, then you can safely disable it entirely by checking the box below:"),
            'label2' => $this->_("Don't match these requests"),
            'notes' => $this->_("Note that any registered jumplinks that start with `index.php/` will not be matched."),
            'collapsed' => Inputfield::collapsedBlank,
            'autocheck' => true,
        )));

        // Info & Support
        $fieldset = $this->buildInputField('InputfieldFieldset', array(
            'label' => $this->_('Info & Support'), // Hidden
            'collapsed' => Inputfield::collapsedNo,
            'skipLabel' => Inputfield::skipLabelHeader,
        ));

        // Debug Mode
        $fieldset->add($this->buildInputField('InputfieldCheckbox', array(
            'name+id' => 'moduleDebug',
            'label' => $this->_('Debug Mode'),
            'description' => $this->_("If you run into any problems with your jumplinks, you can turn on debug mode. Once turned on, you'll be shown a scan log when a 404 Page Not Found is hit. That will give you an indication of what may be going wrong. If it doesn't, and you can't figure it out, then paste your log into the support thread on the forums."),
            'label2' => $this->_('Turn debug mode on'),
            'notes' => $this->_("**Notes:** Hits won't be affected when debug mode is turned on. Also, only those that have permission to manage jumplinks will be shown the debug logs."),
            'collapsed' => Inputfield::collapsedBlank,
            'autocheck' => true,
        )));

        // Support Thread
        $links = array(
            'support' => self::SUPPORT_HREF,
            'docs' => self::DOCS_HREF,
        );
        $text = array(
            'paragraph' => $this->_("Be sure to read the documentation, as it contains all the information you need to get started with Jumplinks. If you're having problems and unable to determine the cause(s) thereof, feel free to ask for help in the official support thread."),
            'docs' => $this->_('Read the Documentation'),
            'support' => $this->_('Official Support Thread'),
        );
        $fieldset->add($this->buildInputField('InputfieldMarkup', array(
            'id' => 'docsSupport',
            'label' => $this->_('Documentation & Support'),
            'value' => <<<HTML
                <p>{$text['paragraph']}</p>
                <div id="pjInputFieldLinks">
                    <a target="_blank" href="{$links['docs']}">{$text['docs']}</a>
                    <a target="_blank" href="{$links['support']}">{$text['support']}</a>
                </div>
HTML
            ,
            'collapsed'=>Inputfield::collapsedYes,
        )));

        // Module Recommendations
        $moduleRecommendationPara = $this->_('Jumplinks complements your SEO-toolkit, which should comprise of the following modules as well:');
        $fieldset->add($this->buildInputField('InputfieldMarkup', array(
            'id' => 'moduleRecommendations',
            'label' => $this->_('Module Recommendations'),
            'value' => <<<HTML
                <p>{$moduleRecommendationPara}</p>
                <div id="pjInputFieldLinks">
                    <a target="_blank" href="http://mods.pw/5q">All In One Minify (AIOM+)</a>
                    <a target="_blank" href="http://mods.pw/2J">Page Path History (core)</a>
                    <a target="_blank" href="http://mods.pw/1V">XML Sitemap</a>
                    <a target="_blank" href="http://mods.pw/8D">Markup SEO</a>
                    <a target="_blank" href="http://mods.pw/6d">ProFields: AutoLinks</a>
                    <a target="_blank" href="http://mods.pw/58">ProFields: ProCache</a>
                </div>
HTML
            ,
            'collapsed'=>Inputfield::collapsedYes,
        )));

        // Support Development
        $text = array(
            'paragraph' => $this->_('Jumplinks is an open-source project, and is free to use. In fact, Jumplinks will always be open-source, and will always remain free to use. Forever. If you would like to support the development of Jumplinks, please make a small donation via PayPal using the button to the right.'),
            'openSource' => $this->_('Open Source Software'),
            'freeSoftware' => $this->_('Free Software'),
            'learnMore' => $this->_('Learn more about'),
        );
        $fieldset->add($this->buildInputField('InputfieldMarkup', array(
            'id' => 'supportDevelopment',
            'label' => $this->_('Support Jumplinks Development'),
            'value' => <<<HTML
                <p><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=L8F6FFYK6ENBQ"><img src="{$this->config->urls->ProcessJumplinks}Assets/DonateButton.png" alt="PayPal" style="float:right;margin-left: 22px;"></a>{$text['paragraph']}</p>
                <div id="pjInputFieldLinks">
                    <span class="prefix">{$text['learnMore']}:</span>
                    <a target="_blank" href="http://opensource.com/resources/what-open-source">{$text['openSource']}</a>
                    <a target="_blank" href="https://en.wikipedia.org/wiki/Free_software">{$text['freeSoftware']}</a>
                </div>
HTML
            ,
            'collapsed'=>Inputfield::collapsedNo,
        )));

        $inputfields->add($fieldset);

        return $inputfields;
    }
}
