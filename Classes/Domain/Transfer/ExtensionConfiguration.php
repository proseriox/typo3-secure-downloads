<?php
declare(strict_types=1);
namespace Bitmotion\SecureDownloads\Domain\Transfer;

/***
 *
 * This file is part of the "Secure Downloads" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Florian Wessels <f.wessels@bitmotion.de>, Bitmotion GmbH
 *
 ***/

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExtensionConfiguration implements SingletonInterface
{
    private $additionalMimeTypes = 'txt|text/plain,html|text/html';

    private $cachetimeadd = 3600;

    /**
     * @deprecated Will be removed in version 5. Use PSR-3 Logger instead.
     */
    private $debug = 0;

    private $domain = 'http://mydomain.com/|http://my.other.domain.org/';

    private $enableGroupCheck = false;

    private $excludeGroups = '';

    private $forcedownload = false;

    private $forcedownloadtype = 'odt|pptx?|docx?|xlsx?|zip|rar|tgz|tar|gz';

    private $groupCheckDirs = '';

    /**
     * @deprecated Will be removed with version 5
     */
    private $linkFormat = 'index.php?eID=tx_securedownloads&p=###PAGE###&u=###FEUSER###&g=###FEGROUPS###&t=###TIMEOUT###&hash=###HASH###&file=###FILE###';

    private $log = false;

    private $outputChunkSize = 1048576;

    private $outputFunction = 'readfile';

    private $securedDirs = 'typo3temp|fileadmin';

    private $securedFiletypes = 'pdf|jpe?g|gif|png|odt|pptx?|docx?|xlsx?|zip|rar|tgz|tar|gz';

    /**
     * @deprecated Will be removed with version 5
     */
    private $legacyDelivery = false;

    private $linkPrefix = 'download';

    private $tokenPrefix = 'sdl-';

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct()
    {
        $configuration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('secure_downloads');

        if ($configuration) {
            $this->setPropertiesFromConfiguration($configuration);
        }
    }

    protected function setPropertiesFromConfiguration(array $configuration): void
    {
        foreach ($configuration as $key => $value) {
            if (property_exists(__CLASS__, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function getAdditionalMimeTypes(): string
    {
        return (string)$this->additionalMimeTypes;
    }

    public function getCacheTimeAdd(): int
    {
        return (int)$this->cachetimeadd;
    }

    /**
     * @deprecated Will be removed in version 5. Use PSR-3 Logger instead.
     */
    public function getDebug(): int
    {
        trigger_error('Method getDebug() will be removed in version 5..', E_USER_DEPRECATED);

        return (int)$this->debug;
    }

    public function getDomain(): string
    {
        return (string)$this->domain;
    }

    public function isEnableGroupCheck(): bool
    {
        return (bool)$this->enableGroupCheck;
    }

    public function getExcludeGroups(): string
    {
        return (string)$this->excludeGroups;
    }

    public function isForceDownload(): bool
    {
        return (bool)$this->forcedownload;
    }

    public function getForceDownloadTypes(): string
    {
        return (string)$this->forcedownloadtype;
    }

    public function getGroupCheckDirs(): string
    {
        return (string)$this->groupCheckDirs;
    }

    /**
     * @deprecated Will be removed with version 5
     */
    public function getLinkFormat(): string
    {
        trigger_error('Method getLinkFormat() will be removed in version 5.', E_USER_DEPRECATED);

        return (string)$this->linkFormat;
    }

    public function isLog(): bool
    {
        return (bool)$this->log;
    }

    public function getOutputChunkSize(): int
    {
        $units = ['', 'K', 'M', 'G'];
        $memoryLimit = ini_get('memory_limit');

        if (!is_numeric($memoryLimit)) {
            $suffix = strtoupper(substr($memoryLimit, -1));
            $exponent = array_flip($units)[$suffix] ?? 1;
            $memoryLimit = (int)$memoryLimit * (1024 ** $exponent);
        }

        // Set max. chunk size to php memory limit - 64 kB
        $maxChunkSize = $memoryLimit - 64 * 1024;

        return (int)(($this->outputChunkSize > $maxChunkSize) ? $maxChunkSize : $this->outputChunkSize);
    }

    public function getOutputFunction(): string
    {
        return (string)$this->outputFunction;
    }

    public function getSecuredDirs(): string
    {
        return (string)$this->securedDirs;
    }

    public function getSecuredFileTypes(): string
    {
        return (string)$this->securedFiletypes;
    }

    /**
     * @deprecated Will be removed with version 5
     */
    public function isLegacyDelivery(): bool
    {
        trigger_error('Method isLegacyDelivery() will be removed in version 5.', E_USER_DEPRECATED);

        return (bool)$this->legacyDelivery;
    }

    public function getLinkPrefix(): string
    {
        return trim($this->linkPrefix, '/');
    }

    public function getTokenPrefix(): string
    {
        return trim($this->tokenPrefix, '/');
    }
}
