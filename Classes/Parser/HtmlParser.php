<?php
declare(strict_types=1);
namespace Bitmotion\SecureDownloads\Parser;

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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class HtmlParser implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var int
     *
     * @deprecated Will be removed in version 5. Use PSR-3 logger instead.
     */
    protected $logLevel = 0;

    /**
     * @var string
     */
    protected $domainPattern;

    /**
     * @var string
     */
    protected $folderPattern;

    /**
     * @var string
     */
    protected $fileExtensionPattern;

    /**
     * @var HtmlParserDelegateInterface
     */
    protected $delegate;

    /**
     * @var string
     */
    protected $tagPattern;

    public function __construct(HtmlParserDelegateInterface $delegate, array $settings)
    {
        $this->delegate = $delegate;
        foreach ($settings as $settingKey => $setting) {
            $setterMethodName = 'set' . ucfirst($settingKey);
            if (method_exists($this, $setterMethodName)) {
                $this->$setterMethodName($setting);
            }
        }
        if (substr($this->fileExtensionPattern, 0, 1) !== '\\') {
            $this->fileExtensionPattern = '\\.(' . $this->fileExtensionPattern . ')';
        }

        $this->tagPattern = '/["\'](?:' . $this->domainPattern . ')?(\/?(?:' . $this->folderPattern . ')+?.*?(?:(?i)' . $this->fileExtensionPattern . '))["\']?/i';
    }

    public function setDomainPattern(string $accessProtectedDomain): void
    {
        if (!empty($GLOBALS['TSFE']->absRefPrefix) && $GLOBALS['TSFE']->absRefPrefix !== '/') {
            $accessProtectedDomain .= '|' . $GLOBALS['TSFE']->absRefPrefix;
        }

        $this->domainPattern = $this->softQuoteExpression($accessProtectedDomain);
    }

    /**
     * Quotes special some characters for the regular expression.
     * Leave braces and brackets as is to have more flexibility in configuration.
     */
    public static function softQuoteExpression(string $string): string
    {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace(' ', '\ ', $string);
        $string = str_replace('/', '\/', $string);
        $string = str_replace('.', '\.', $string);
        $string = str_replace(':', '\:', $string);

        return $string;
    }

    public function setFileExtensionPattern(string $accessProtectedFileExtensions): void
    {
        $this->fileExtensionPattern = $accessProtectedFileExtensions;
    }

    public function setFolderPattern(string $accessProtectedFolders): void
    {
        $this->folderPattern = $this->softQuoteExpression($accessProtectedFolders);
    }

    public function setLogLevel(int $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    /**
     * Parses the HTML output and replaces the links to configured files with secured ones
     */
    public function parse(string $html): string
    {
        if ($this->logLevel >= 1) {
            $parseStartTime = $this->microtime_float();
        }

        $result = '';
        $pattern = '/((((data|ng|v)-[a-z0-9]*)|(href|src|poster))=["\']{1}(.*)["\']{1})|url\((.*)\)/siU';

        while (preg_match($pattern, $html, $match)) {
            $htmlContent = explode($match[0], $html, 2);

            if ($this->logLevel === 3) {
                $this->logger->debug(sprintf('Found matching tag: %s', $match[0]));
                DebuggerUtility::var_dump($match[0], 'Tag:');
            }

            // Parse tag
            $tag = $this->parseTag($match[0]);

            // Add tag to HTML before matching tag
            $result .= $htmlContent[0] . $tag;

            // all HTML after matching tag
            $html = $htmlContent[1];
        }

        if ($this->logLevel >= 1) {
            $parseFinishTime = $this->microtime_float();
            $executionTime = $parseFinishTime - $parseStartTime;
            $this->logger->notice(sprintf('Script runtime: %s', $executionTime));
            DebuggerUtility::var_dump($executionTime, 'Scriptlaufzeit');
        }

        return $result . $html;
    }

    protected function microtime_float(): float
    {
        list($usec, $sec) = explode(' ', microtime());

        return $usec + $sec;
    }

    /**
     * Investigate the HTML-Tag...
     */
    protected function parseTag(string $tag): string
    {
        if (preg_match($this->tagPattern, $tag, $matchedUrls)) {
            $resourceUri = $matchedUrls[1];
            $replace = $this->delegate->publishResourceUri($resourceUri);
            $tagParts = explode($matchedUrls[1], $tag, 2);
            $tag = $this->recursion(rtrim($tagParts[0], '/') . '/' . $replace, $tagParts[1]);

            // Some output for debugging
            if ($this->logLevel === 1) {
                $this->logger->notice(sprintf('New output: %s', $tag));
                DebuggerUtility::var_dump($tag, 'New output:');
            } elseif ($this->logLevel >= 2) {
                $this->logger->info(sprintf('Regular expression: %s', $this->tagPattern));
                DebuggerUtility::var_dump($this->tagPattern, 'Regular Expression:');
                $this->logger->info(sprintf('Matching URLs: %s', implode(',', $matchedUrls)));
                DebuggerUtility::var_dump($matchedUrls, 'Match:');
                $this->logger->notice(sprintf('Build tag (part 1): %s', $tagParts[0]));
                $this->logger->notice(sprintf('Build tag (replace): %s', $replace));
                $this->logger->notice(sprintf('Build tag (part 1): %s', $tagParts[0]));
                DebuggerUtility::var_dump([$tagParts[0], $replace, $tagParts[1]], 'Build Tag:');
                $this->logger->notice(sprintf('New output: %s', $tag));
                DebuggerUtility::var_dump($tag, 'New output:');
            }
        }

        return $tag;
    }

    /**
     * Search recursive in the rest of the tag (e.g. for vHWin=window.open...).
     */
    private function recursion(string $tag, string $tmp): string
    {
        if (preg_match($this->tagPattern, $tmp, $matchedUrls)) {
            $replace = $this->delegate->publishResourceUri($matchedUrls[1]);
            $tagexp = explode($matchedUrls[1], $tmp, 2);

            if ($this->logLevel >= 2) {
                $this->logger->info(sprintf('Futher matches (part 1): %s', $tagexp[0]));
                $this->logger->info(sprintf('Futher matches (replace): %s', $replace));
                $this->logger->info(sprintf('Futher matches (part 2): %s', $tagexp[1]));
                DebuggerUtility::var_dump([$tagexp[0], $replace, $tagexp[1]], 'Further Match:');
            }

            $tag .= $tagexp[0] . '/' . $replace;

            return $this->recursion($tag, $tagexp[1]);
        }

        return $tag . $tmp;
    }
}
