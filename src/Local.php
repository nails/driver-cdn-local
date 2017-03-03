<?php

namespace Nails\Cdn\Driver;

use Nails\Cdn\Interfaces\Driver;
use Nails\Common\Driver\Base;
use Nails\Common\Traits\ErrorHandling;

class Local extends Base implements Driver
{
    use ErrorHandling;

    // --------------------------------------------------------------------------

    /**
     * Returns the path to the local upload directory
     * @return string
     */
    protected function getPath()
    {
        return addTrailingSlash($this->getSetting('path'));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the requested URI
     *
     * @param $sUriType
     *
     * @return string
     */
    protected function getUri($sUriType)
    {
        return site_url($this->getSetting('uri_' . $sUriType));
    }

    // --------------------------------------------------------------------------

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     *
     * @param  \stdClass $data Data to create the object with
     *
     * @return boolean
     */
    public function objectCreate($data)
    {
        try {

            $sBucket   = !empty($data->bucket->slug) ? $data->bucket->slug : '';
            $sFilename = !empty($data->filename) ? $data->filename : '';
            $sSource   = !empty($data->file) ? $data->file : '';

            // --------------------------------------------------------------------------

            //  Check directory exists
            if (!is_dir($this->getPath() . $sBucket)) {
                //  Hmm, not writable, can we create it?
                if (!@mkdir($this->getPath() . $sBucket)) {
                    //  Nope, failed to create the directory - we iz gonna have problems if we continue, innit.
                    throw new \Exception(lang('cdn_error_target_write_fail_mkdir', $this->getPath() . $sBucket));
                }
            }

            // --------------------------------------------------------------------------

            //  Check bucket is writable
            if (!is_writable($this->getPath() . $sBucket)) {
                throw new \Exception(lang('cdn_error_target_write_fail', $this->getPath() . $sBucket));
            }

            //  Move the file
            $sDestination = $this->getPath() . $sBucket . '/' . $sFilename;

            if (!@move_uploaded_file($sSource, $sDestination)) {
                if (!@copy($sSource, $sDestination)) {
                    throw new \Exception(lang('cdn_error_couldnotmove'));
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->setError('LOCAL EXCEPTION: [objectCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     *
     * @param  string $sFilename The object's filename
     * @param  string $sBucket   The bucket's slug
     *
     * @return boolean
     */
    public function objectExists($sFilename, $sBucket)
    {
        return file_exists($this->getPath() . $sBucket . '/' . $sFilename);
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permanently deletes) an object
     *
     * @param  string $sObject The object's filename
     * @param  string $sBucket The bucket's slug
     *
     * @return boolean
     */
    public function objectDestroy($sObject, $sBucket)
    {
        try {

            $sObject = urldecode($sObject);
            $sBucket = urldecode($sBucket);

            if (file_exists($this->getPath() . $sBucket . '/' . $sObject)) {
                if (!@unlink($this->getPath() . $sBucket . '/' . $sObject)) {
                    throw new \Exception(lang('cdn_error_delete'));
                }
            } else {
                throw new \Exception(lang('cdn_error_delete_nofile'));
            }

            return true;

        } catch (\Exception $e) {
            $this->setError('LOCAL EXCEPTION: [objectDestroy]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     *
     * @param  string $sBucket   The bucket's slug
     * @param  string $sFilename The filename
     *
     * @return mixed             String on success, false on failure
     */
    public function objectLocalPath($sBucket, $sFilename)
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
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     *
     * @param  string $sBucket The bucket's slug
     *
     * @return boolean
     */
    public function bucketCreate($sBucket)
    {
        try {

            $sDir = $this->getPath() . $sBucket;

            if (!is_dir($sDir)) {
                if (!@mkdir($sDir)) {
                    if (getUserObject()->isSuperuser()) {
                        throw new \Exception(lang('cdn_error_bucket_mkdir_su', $sDir));
                    } else {
                        throw new \Exception(lang('cdn_error_bucket_mkdir'));
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->setError('LOCAL-SDK EXCEPTION: [bucketCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     *
     * @param  string $sBucket The bucket's slug
     *
     * @return boolean
     */
    public function bucketDestroy($sBucket)
    {
        //  @todo - consider the implications of bucket deletion; maybe prevent deletion of non-empty buckets
        dumpanddie('@todo');
        try {

            if (!rmdir($this->getPath() . $sBucket)) {
                throw new \Exception(lang('cdn_error_bucket_unlink'));
            }

            return true;

        } catch (\Exception $e) {
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
     * @param  string  $sObject        The object to serve
     * @param  string  $sBucket        The bucket to serve from
     * @param  boolean $bForceDownload Whether to force a download
     *
     * @return string
     */
    public function urlServe($sObject, $sBucket, $bForceDownload = false)
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
     * @param $sObject
     * @param $sBucket
     *
     * @return string
     */
    public function urlServeRaw($sObject, $sBucket)
    {
        $sUrl = 'assets/uploads/' . $sBucket . '/' . $sObject;
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     *
     * @param  boolean $bForceDownload Whether or not to force download
     *
     * @return string
     */
    public function urlServeScheme($bForceDownload = false)
    {
        $sUrl = $this->getUri('serve') . '/serve/{{bucket}}/{{filename}}{{extension}}';

        if ($bForceDownload) {
            $sUrl .= '?dl=1';
        }

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a URL for serving zipped objects
     *
     * @param  string $sObjectIds A comma separated list of object IDs
     * @param  string $sHash      The security hash
     * @param  string $sFilename  The filename to give the zip file
     *
     * @return string
     */
    public function urlServeZipped($sObjectIds, $sHash, $sFilename)
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
     * @return  string
     */
    public function urlServeZippedScheme()
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/zip/{{ids}}/{{hash}}/{{filename}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the crop utility
     *
     * @param   string  $sBucket The bucket which the image resides in
     * @param   string  $sObject The filename of the image we're cropping
     * @param   integer $iWidth  The width of the cropped image
     * @param   integer $iHeight The height of the cropped image
     *
     * @return  string
     */
    public function urlCrop($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl       = $this->urlCropScheme();
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'crop' urls
     * @return  string
     */
    public function urlCropScheme()
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/crop/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     *
     * @param   string  $sBucket The bucket which the image resides in
     * @param   string  $sObject The filename of the image we're 'scaling'
     * @param   integer $iWidth  The width of the scaled image
     * @param   integer $iHeight The height of the scaled image
     *
     * @return  string
     */
    public function urlScale($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl       = $this->urlScaleScheme();
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     * @return  string
     */
    public function urlScaleScheme()
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     *
     * @param   integer $iWidth  The width of the placeholder
     * @param   integer $iHeight The height of the placeholder
     * @param   integer $iBorder The width of the border round the placeholder
     *
     * @return  string
     */
    public function urlPlaceholder($iWidth, $iHeight, $iBorder = 0)
    {
        $sUrl = $this->urlPlaceholderScheme();

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{border}}', $iBorder, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     * @return  string
     */
    public function urlPlaceholderScheme()
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/placeholder/{{width}}/{{height}}/{{border}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for a blank avatar
     *
     * @param  integer        $iWidth  The width fo the avatar
     * @param  integer        $iHeight The height of the avatar§
     * @param  string|integer $mSex    What gender the avatar should represent
     *
     * @return string
     */
    public function urlBlankAvatar($iWidth, $iHeight, $mSex = '')
    {
        $sUrl = $this->urlBlankAvatarScheme();

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{sex}}', $mSex, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     * @return  string
     */
    public function urlBlankAvatarScheme()
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/blank_avatar/{{width}}/{{height}}/{{sex}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     *
     * @param  string  $sBucket        The bucket which the image resides in
     * @param  string  $sObject        The object to be served
     * @param  integer $iExpires       The length of time the URL should be valid for, in seconds
     * @param  boolean $bForceDownload Whether to force a download
     *
     * @return string
     */
    public function urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload = false)
    {
        $sUrl = $this->urlExpiringScheme();

        //  Hash the expiry time
        $sToken = $sBucket . '|' . $sObject . '|' . $iExpires . '|' . time() . '|';
        $sToken .= md5(time() . $sBucket . $sObject . $iExpires . APP_PRIVATE_KEY);
        $sToken = get_instance()->encrypt->encode($sToken, APP_PRIVATE_KEY);
        $sToken = urlencode($sToken);

        //  Sub in the values
        $sUrl = str_replace('{{token}}', $sToken, $sUrl);
        $sUrl = str_replace('{{download}}', $bForceDownload ? 1 : 0, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     * @return  string
     */
    public function urlExpiringScheme()
    {
        return $this->urlMakeSecure(
            $this->getUri('process') . '/serve?token={{token}}&dl={{download}}'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     *
     * @param  string  $sUrl          The URL to secure
     * @param  boolean $bIsProcessing Whether it's a processing type URL
     *
     * @return string
     */
    protected function urlMakeSecure($sUrl, $bIsProcessing = true)
    {
        if (isPageSecure()) {
            if ($bIsProcessing) {
                $sSearch  = $this->getUri('process');
                $sReplace = $this->getUri('process_secure');
            } else {
                $sSearch  = $this->getUri('serve');
                $sReplace = $this->getUri('serve_secure');
            }
            $sUrl = str_replace($sSearch, $sReplace, $sUrl);
        }

        return preg_match('#^https?://#', $sUrl) ? $sUrl : site_url($sUrl);
    }
}
