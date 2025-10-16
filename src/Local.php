<?php

namespace Nails\Cdn\Driver;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception\DriverException;
use Nails\Cdn\Interfaces\Driver;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Driver\Base;
use Nails\Common\Exception\EnvironmentException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Helper\File;
use Nails\Common\Service\Encrypt;
use Nails\Common\Traits\ErrorHandling;
use Nails\Config;
use Nails\Factory;
use Nails\Functions;
use stdClass;
use Throwable;

/**
 * Class Local
 *
 * @package Nails\Cdn\Driver
 */
class Local extends Base implements Driver
{
    use ErrorHandling;

    // --------------------------------------------------------------------------

    /**
     * Returns the path to the local upload directory
     */
    protected function getPath(): string
    {
        return addTrailingSlash($this->getSetting('path'));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the requested URI
     */
    protected function getUri(string $sUriType): string
    {
        return siteUrl($this->getSetting('uri_' . $sUriType));
    }

    // --------------------------------------------------------------------------

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     *
     * @param stdClass $oData Data to create the object with
     */
    public function objectCreate(stdClass $oData): bool
    {
        try {

            $sBucket   = !empty($oData->bucket->slug) ? $oData->bucket->slug : '';
            $sFilename = !empty($oData->filename) ? $oData->filename : '';
            $sSource   = !empty($oData->file) ? $oData->file : '';

            // --------------------------------------------------------------------------

            $sBucketPath  = $this->createBucketDir($sBucket);
            $sDestination = $sBucketPath . '/' . $sFilename;

            if (!@move_uploaded_file($sSource, $sDestination)) {
                if (!@copy($sSource, $sDestination)) {
                    throw new DriverException('Failed to move uploaded file into the bucket');
                }
            }

            return true;

        } catch (Throwable $e) {
            $this->setError('LOCAL EXCEPTION: [objectCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     *
     * @param string $sFilename The object's filename
     * @param string $sBucket   The bucket's slug
     */
    public function objectExists(string $sFilename, string $sBucket): bool
    {
        try {

            return file_exists($this->getPath() . $sBucket . '/' . $sFilename);

        } catch (Throwable $e) {
            $this->setError('LOCAL EXCEPTION: [objectExists]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Move an object
     *
     * @param string $sSourceObject The source object's filename
     * @param string $sSourceBucket The source bucket's slug
     * @param string $sTargetObject The target object's filename
     * @param string $sTargetBucket The target bucket's slug
     */
    public function objectMove(
        string $sSourceObject,
        string $sSourceBucket,
        string $sTargetObject,
        string $sTargetBucket
    ): bool {
        try {

            $sSourcePath = sprintf('%s%s/%s', $this->getPath(), $sSourceBucket, $sSourceObject);
            $sTargetPath = sprintf('%s%s/%s', $this->getPath(), $sTargetBucket, $sTargetObject);

            $this->checkSourceAndTarget($sSourcePath, $sTargetPath);
            $this->createBucketDir($sTargetBucket);

            if (!rename($sSourcePath, $sTargetPath)) {
                throw new DriverException('Failed to rename file.');
            }

            return true;

        } catch (Throwable $e) {
            $this->setError('LOCAL EXCEPTION: [objectMove]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Copy an object
     *
     * @param string $sSourceObject The source object's filename
     * @param string $sSourceBucket The source bucket's slug
     * @param string $sTargetObject The target object's filename
     * @param string $sTargetBucket The target bucket's slug
     */
    public function objectCopy(
        string $sSourceObject,
        string $sSourceBucket,
        string $sTargetObject,
        string $sTargetBucket
    ): bool {
        try {

            $sSourcePath = sprintf('%s%s/%s', $this->getPath(), $sSourceBucket, $sSourceObject);
            $sTargetPath = sprintf('%s%s/%s', $this->getPath(), $sTargetBucket, $sTargetObject);

            $this->checkSourceAndTarget($sSourcePath, $sTargetPath);
            $this->createBucketDir($sTargetBucket);

            if (!copy($sSourcePath, $sTargetPath)) {
                throw new DriverException('Failed to copy file.');
            }

            return true;

        } catch (Throwable $e) {
            $this->setError('LOCAL EXCEPTION: [objectCopy]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * @throws DriverException
     */
    protected function checkSourceAndTarget(string $sSourcePath, string $sTargetPath): void
    {
        if (!File::fileExistsCS($sSourcePath)) {
            if (isSuperuser()) {
                throw new DriverException(sprintf('Source file (%s) does not exist.', $sSourcePath));
            } else {
                throw new DriverException('Source file does not exist.');
            }
        }

        if (File::fileExistsCS($sTargetPath)) {
            if (isSuperuser()) {
                throw new DriverException(sprintf('Target file (%s) already exists.', $sTargetPath));
            } else {
                throw new DriverException('Target file already exists.');
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permanently deletes) an object
     *
     * @param string $sObject The object's filename
     * @param string $sBucket The bucket's slug
     */
    public function objectDestroy(string $sObject, string $sBucket): bool
    {
        try {

            $sObject = urldecode($sObject);
            $sBucket = urldecode($sBucket);

            if (file_exists($this->getPath() . $sBucket . '/' . $sObject)) {
                if (!@unlink($this->getPath() . $sBucket . '/' . $sObject)) {
                    throw new DriverException('File failed to delete, it may be in use');
                }
            } else {
                throw new DriverException('No file to delete');
            }

            return true;

        } catch (Throwable $e) {
            $this->setError('LOCAL EXCEPTION: [objectDestroy]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     *
     * @param string $sBucket   The bucket's slug
     * @param string $sFilename The filename
     *
     * @return bool|string String on success, false on failure
     */
    public function objectLocalPath(string $sBucket, string $sFilename): bool|string
    {
        $sPath = $this->getPath() . $sBucket . '/' . $sFilename;

        if (is_file($sPath)) {
            return $sPath;
        } else {
            $this->setError('Could not find a valid local path for object ' . $sBucket . '/' . $sFilename);
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object's meta data is set correctly or not
     *
     * @param string $sFilename        The object's filename
     * @param string $sFilenameDisplay The object's human-friendly name
     * @param string $sBucket          The bucket's slug
     * @param string $sMimeType        The object's mime type
     *
     * @return string[]
     */
    public function getObjectMetaDataErrors(
        string $sFilename,
        string $sFilenameDisplay,
        string $sBucket,
        string $sMimeType
    ): array {
        //  No metadata is set using this driver
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Attempt to fix object meta data
     *
     * @param string $sFilename        The object's filename
     * @param string $sFilenameDisplay The object's human-friendly name
     * @param string $sBucket          The bucket's slug
     * @param string $sMimeType        The object's mime type
     *
     * @return bool
     */
    public function fixObjectMetaDataErrors(
        string $sFilename,
        string $sFilenameDisplay,
        string $sBucket,
        string $sMimeType
    ): bool {
        //  No metadata is set using this driver
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     *
     * @param string $sBucket The bucket's slug
     */
    public function bucketCreate(string $sBucket): bool
    {
        try {

            $this->createBucketDir($sBucket);
            return true;

        } catch (Throwable $e) {
            $this->setError('LOCAL-SDK EXCEPTION: [bucketCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     *
     * @param string $sBucket The bucket's slug
     */
    public function bucketDestroy(string $sBucket): bool
    {
        //  @todo - consider the implications of bucket deletion; maybe prevent deletion of non-empty buckets
        try {

            if (!rmdir($this->getPath() . $sBucket)) {
                throw new DriverException('Failed to destroy bucket');
            }

            return true;

        } catch (Throwable $e) {
            $this->setError('LOCAL-SDK ERROR: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * URL GENERATOR METHODS
     */

    /**
     * Generates the correct URL for serving a file
     *
     * @param string $sObject        The object to serve
     * @param string $sBucket        The bucket to serve from
     * @param bool   $bForceDownload Whether to force a download
     */
    public function urlServe(string $sObject, string $sBucket, bool $bForceDownload = false): string
    {
        $sUrl       = $this->urlServeScheme($bForceDownload);
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the correct URL for serving a file direct from the file system
     *
     * @param string $sObject The object's filename
     * @param string $sBucket The bucket's slug
     */
    public function urlServeRaw(string $sObject, string $sBucket): string
    {
        $sUrl = 'assets/uploads/' . $sBucket . '/' . $sObject;
        return $this->urlMakeSecure($sUrl, false);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     *
     * @param bool $bForceDownload Whether to force download
     */
    public function urlServeScheme(bool $bForceDownload = false): string
    {
        $sUrl = $this->getUri('serve') . '/serve/{{bucket}}/{{filename}}{{extension}}';
        if ($bForceDownload) {
            $sUrl .= '?dl=1';
        }

        return $this->urlMakeSecure($sUrl, false);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a URL for serving zipped objects
     *
     * @param string $sObjectIds A comma-separated list of object IDs
     * @param string $sHash      The security hash
     * @param string $sFilename  The filename to give the zip file
     */
    public function urlServeZipped(string $sObjectIds, string $sHash, string $sFilename): string
    {
        $sUrl = $this->urlServeZippedScheme();

        //  Sub in the values
        $sUrl = str_replace('{{ids}}', $sObjectIds, $sUrl);
        $sUrl = str_replace('{{hash}}', $sHash, $sUrl);
        $sUrl = str_replace('{{filename}}', urlencode($sFilename), $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'zipped' urls
     *
     * @return  string
     */
    public function urlServeZippedScheme(): string
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/zip/{{ids}}/{{hash}}/{{filename}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the crop utility
     *
     * @param string $sBucket The bucket which the image resides in
     * @param string $sObject The filename of the image we're cropping
     * @param int    $iWidth  The width of the cropped image
     * @param int    $iHeight The height of the cropped image
     */
    public function urlCrop(string $sObject, string $sBucket, int $iWidth, int $iHeight): string
    {
        $sUrl       = $this->urlCropScheme();
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{width}}', (string) $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', (string) $iHeight, $sUrl);
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'crop' urls
     */
    public function urlCropScheme(): string
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/crop/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     *
     * @param string $sBucket The bucket which the image resides in
     * @param string $sObject The filename of the image we're 'scaling'
     * @param int    $iWidth  The width of the scaled image
     * @param int    $iHeight The height of the scaled image
     */
    public function urlScale(string $sObject, string $sBucket, int $iWidth, int $iHeight): string
    {
        $sUrl       = $this->urlScaleScheme();
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{width}}', (string) $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', (string) $iHeight, $sUrl);
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     */
    public function urlScaleScheme(): string
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     *
     * @param int $iWidth  The width of the placeholder
     * @param int $iHeight The height of the placeholder
     * @param int $iBorder The width of the border round the placeholder
     *
     * @throws FactoryException
     */
    public function urlPlaceholder(int $iWidth, int $iHeight, int $iBorder = 0): string
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $sCacheFile = sprintf(
            'placeholder-%sx%s-%s.png',
            $iWidth,
            $iHeight,
            $iBorder
        );

        if ($oCdn->getCdnCache()->public()->exists($sCacheFile)) {
            return $oCdn->getCdnCache()->public()->getUrl($sCacheFile);
        }

        $sUrl = $this->urlPlaceholderScheme();

        //  Sub in the values
        $sUrl = str_replace('{{width}}', (string) $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', (string) $iHeight, $sUrl);
        $sUrl = str_replace('{{border}}', (string) $iBorder, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     */
    public function urlPlaceholderScheme(): string
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/placeholder/{{width}}/{{height}}/{{border}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for a blank avatar
     *
     * @param int    $iWidth  The width fo the avatar
     * @param int    $iHeight The height of the avatar§
     * @param string $sSex    What gender the avatar should represent
     *
     * @throws FactoryException
     */
    public function urlBlankAvatar(int $iWidth, int $iHeight, string $sSex = ''): string
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        $sSex = $oCdn->blankAvatarNormaliseSex($sSex);

        $sCacheFile = sprintf(
            'blank_avatar-%sx%s-%s.png',
            $iWidth,
            $iHeight,
            $sSex
        );

        if ($oCdn->getCdnCache()->public()->exists($sCacheFile)) {
            return $oCdn->getCdnCache()->public()->getUrl($sCacheFile);
        }

        $sUrl = $this->urlBlankAvatarScheme();

        //  Sub in the values
        $sUrl = str_replace('{{width}}', (string) $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', (string) $iHeight, $sUrl);
        $sUrl = str_replace('{{sex}}', $sSex, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     */
    public function urlBlankAvatarScheme(): string
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/blank_avatar/{{width}}/{{height}}/{{sex}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     *
     * @param string $sBucket        The bucket which the image resides in
     * @param string $sObject        The object to be served
     * @param int    $iExpires       The length of time the URL should be valid for, in seconds
     * @param bool   $bForceDownload Whether to force a download
     *
     * @throws FactoryException
     * @throws EnvironmentException
     */
    public function urlExpiring(string $sObject, string $sBucket, int $iExpires, bool $bForceDownload = false): string
    {
        $sUrl = $this->urlExpiringScheme();
        /** @var Encrypt $oEncrypt */
        $oEncrypt = Factory::service('Encrypt');

        //  Hash the expiry time
        $sToken = $sBucket . '|' . $sObject . '|' . $iExpires . '|' . time() . '|';
        $sToken .= md5(time() . $sBucket . $sObject . $iExpires . Config::get('PRIVATE_KEY'));
        $sToken = $oEncrypt->encode($sToken, Config::get('PRIVATE_KEY'));
        $sToken = urlencode($sToken);

        //  Sub in the values
        $sUrl = str_replace('{{token}}', $sToken, $sUrl);
        $sUrl = str_replace('{{download}}', $bForceDownload ? '1' : '0', $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     */
    public function urlExpiringScheme(): string
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/serve?token={{token}}&dl={{download}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     *
     * @param string $sUrl          The URL to secure
     * @param bool   $bIsProcessing Whether it's a processing type URL
     */
    protected function urlMakeSecure(string $sUrl, bool $bIsProcessing = true): string
    {
        if (Functions::isPageSecure()) {
            if ($bIsProcessing) {
                $sSearch  = $this->getUri('process');
                $sReplace = $this->getUri('process_secure');
            } else {
                $sSearch  = $this->getUri('serve');
                $sReplace = $this->getUri('serve_secure');
            }
            $sUrl = str_replace($sSearch, $sReplace, $sUrl);
        }

        return preg_match('#^https?://#', $sUrl) ? $sUrl : siteUrl($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Ensures the bucket directory exists and is writable
     *
     * @throws DriverException
     */
    protected function createBucketDir(string $bucket): string
    {
        $path = $this->getPath() . $bucket;

        if (!is_dir($path)) {
            if (!@mkdir($path)) {
                if (isSuperuser()) {
                    throw new DriverException(
                        sprintf('The target directory does not exist and could not be created (%s)', $path)
                    );
                } else {
                    throw new DriverException(
                        'Failed to create bucket directory'
                    );
                }
            }
        }

        if (!is_writable($path)) {
            if (isSuperuser()) {
                throw new DriverException(
                    sprintf('The target directory is not writable (%s)', $path)
                );
            } else {
                throw new DriverException(
                    'Failed to create bucket directory'
                );
            }
        }

        return $path;
    }
}
