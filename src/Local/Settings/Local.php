<?php

namespace Nails\Cdn\Driver\Local\Settings;

use Nails\Common\Helper\Form;
use Nails\Common\Interfaces;
use Nails\Common\Service\FormValidation;
use Nails\Components\Setting;
use Nails\Factory;

/**
 * Class Local
 *
 * @package Nails\Cdn\Driver\Local\Settings
 */
class Local implements Interfaces\Component\Settings
{
    const KEY_PATH               = 'path';
    const KEY_URL_SERVE          = 'uri_serve';
    const KEY_URL_SERVE_SECURE   = 'uri_serve_secure';
    const KEY_URL_PROCESS        = 'uri_process';
    const KEY_URL_PROCESS_SECURE = 'uri_process_secure';

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'CDN: Local Filesystem';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getPermissions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        /** @var Setting $oPath */
        $oPath = Factory::factory('ComponentSetting');
        $oPath
            ->setKey(static::KEY_PATH)
            ->setLabel('Path')
            ->setDefault('assets/uploads')
            ->setInfo('This can be the key file contents, or a path to the key file')
            ->setFieldset('Credentials')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlServe */
        $oUrlServe = Factory::factory('ComponentSetting');
        $oUrlServe
            ->setKey(static::KEY_URL_SERVE)
            ->setLabel('Serving URL')
            ->setFieldset('URLs')
            ->setDefault('cdn')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlServeSecure */
        $oUrlServeSecure = Factory::factory('ComponentSetting');
        $oUrlServeSecure
            ->setKey(static::KEY_URL_SERVE_SECURE)
            ->setLabel('Serving URL (Secure)')
            ->setFieldset('URLs')
            ->setDefault('cdn')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlProcess */
        $oUrlProcess = Factory::factory('ComponentSetting');
        $oUrlProcess
            ->setKey(static::KEY_URL_PROCESS)
            ->setLabel('Processing URL')
            ->setFieldset('URLs')
            ->setDefault('cdn')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlProcessSecure */
        $oUrlProcessSecure = Factory::factory('ComponentSetting');
        $oUrlProcessSecure
            ->setKey(static::KEY_URL_PROCESS_SECURE)
            ->setLabel('Processing URL (Secure)')
            ->setFieldset('URLs')
            ->setDefault('cdn')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        return [
            $oPath,
            $oUrlServe,
            $oUrlServeSecure,
            $oUrlProcess,
            $oUrlProcessSecure,
        ];
    }
}
