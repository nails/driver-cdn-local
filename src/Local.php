<?php

namespace Nails\Cdn\Driver;

use Nails\Factory;

class Local implements \Nails\Cdn\Interfaces\Driver
{
    protected $aErrors;
    protected $sBasePath;
    protected $sBaseUrl;

    // --------------------------------------------------------------------------

    /**
     * Constructor
     */
    public function __construct()
    {
        Factory::helper('string');

        $this->aErrors = array();
        $this->sBasePath = defined('DEPLOY_CDN_PATH') ? DEPLOY_CDN_PATH : FCPATH . 'assets/uploads';
        $this->sBasePath = addTrailingSlash($this->sBasePath);
        $this->sBaseUrl  = defined('DEPLOY_CDN_BASE_URL') ? DEPLOY_CDN_BASE_URL : 'cdn';
        $this->sBaseUrl  = addTrailingSlash($this->sBaseUrl);
    }

    /**
     * ERROR METHODS
     */

    /**
     * Adds an error to the stack
     * @param string $sError The error string
     */
    protected function setError($sError) {
        if (!empty($sError)) {
            $this->aErrors[] = $sError;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the last error to occur
     * @return string
     */
    public function lastError()
    {
        return end($this->aErrors);
    }

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     * @param  stdClass $data Data to create the object with
     * @return boolean
     */
    public function objectCreate($data)
    {
        $sBucket   = !empty($data->bucket->slug) ? $data->bucket->slug : '';
        $sFilename = !empty($data->filename) ? $data->filename : '';
        $sSource   = !empty($data->file) ? $data->file : '';

        // --------------------------------------------------------------------------

        //  Check directory exists
        if (!is_dir($this->sBasePath . $sBucket)) {

            //  Hmm, not writable, can we create it?
            if (!@mkdir($this->sBasePath . $sBucket)) {

                //  Nope, failed to create the directory - we iz gonna have problems if we continue, innit.
                $this->setError(lang('cdn_error_target_write_fail_mkdir', $this->sBasePath . $sBucket));
                return false;
            }
        }

        // --------------------------------------------------------------------------

        //  Check bucket is writable
        if (!is_writable($this->sBasePath . $sBucket)) {

            $this->setError(lang('cdn_error_target_write_fail', $this->sBasePath . $sBucket));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Move the file
        $sDest = $this->sBasePath . $sBucket . '/' . $sFilename;

        if (@move_uploaded_file($sSource, $sDest)) {

            return true;

        //  Hmm, failed to move, try copying it.
        } elseif (@copy($sSource, $sDest)) {

            return true;

        } else {

            $this->setError(lang('cdn_error_couldnotmove'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     * @param  string $sFilename The object's filename
     * @param  string $sBucket   The bucket's slug
     * @return boolean
     */
    public function objectExists($sFilename, $sBucket)
    {
        return is_file($this->sBasePath . $sBucket . '/' . $sFilename);
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permenantly deletes) an object
     * @param  string $sObject The object's filename
     * @param  string $sBucket The bucket's slug
     * @return boolean
     */
    public function objectDestroy($sObject, $sBucket)
    {
        $sObject = urldecode($sObject);
        $sBucket = urldecode($sBucket);

        if (file_exists($this->sBasePath . $sBucket . '/' . $sObject)) {

            if (@unlink($this->sBasePath . $sBucket . '/' . $sObject)) {

                //  @todo: Delete Cache items
                return true;

            } else {

                $this->setError(lang('cdn_error_delete'));
                return false;
            }

        } else {

            $this->setError(lang('cdn_error_delete_nofile'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     * @param  string $sBucket   The bucket's slug
     * @param  string $sFilename The filename
     * @return mixed             String on success, false on failure
     */
    public function objectLocalPath($sBucket, $sFilename)
    {
        $sPath = $this->sBasePath . $sBucket . '/' . $sFilename;

        if (is_file($sPath)) {

            return $sPath;

        } else {

            $this->setError('Could not find a valid local path for object ' . $sBucket . '/' . $sFilename);
            return false;
        }
    }

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     * @param  string  $sBucket The bucket's slug
     * @return boolean
     */
    public function bucketCreate($sBucket)
    {
        $sDir = $this->sBasePath . $sBucket;

        if (is_dir($sDir) && is_writable($sDir)) {

            return true;
        }

        // --------------------------------------------------------------------------

        if (@mkdir($sDir)) {

            return true;

        } else {

            if (getUserObject()->isSuperuser()) {

                $this->setError(lang('cdn_error_bucket_mkdir_su', $sDir));

            } else {

                $this->setError(lang('cdn_error_bucket_mkdir'));
            }

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     * @param  string  $sBucket The bucket's slug
     * @return boolean
     */
    public function bucketDestroy($sBucket)
    {
        if (rmdir($this->sBasePath . $sBucket)) {

            return true;

        } else {

            $this->setError(lang('cdn_error_bucket_unlink'));
            return false;
        }
    }

    /**
     * URL GENERATOR METHODS
     */

    /**
     * Generates the correct URL for serving a file
     * @param  string  $sObject        The object to serve
     * @param  string  $sBucket        The bucket to serve from
     * @param  boolean $bForceDownload Whether to force a download
     * @return string
     */
    public function urlServe($sObject, $sBucket, $bForceDownload = false)
    {
        $sUrl  = $this->sBaseUrl . 'serve/';
        $sUrl .= $sBucket . '/';
        $sUrl .= $sObject;

        if ($bForceDownload) {

            $sUrl .= '?dl=1';
        }

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the correct URL for serving a file direct from the file system
     * @param $sObject
     * @param $sBucket
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
     * @param  boolean $bForceDownload Whether or not to force download
     * @return string
     */
    public function urlServeScheme($bForceDownload = false)
    {
        $sUrl = $this->sBaseUrl . 'serve/{{bucket}}/{{filename}}{{extension}}';

        if ($bForceDownload) {

            $sUrl .= '?dl=1';
        }

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a URL for serving zipped objects
     * @param  string $sObjectIds A comma seperated list of object IDs
     * @param  string $sHash      The security hash
     * @param  string $sFilename  The filename to give the zip file
     * @return string
     */
    public function urlServeZipped($sObjectIds, $sHash, $sFilename)
    {
        $sFilename = $sFilename ? '/' . urlencode($sFilename) : '';
        return $this->urlMakeSecure($this->sBaseUrl . 'zip/' . $sObjectIds . '/' . $sHash . $sFilename);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'zipped' urls
     * @return  string
     */
    public function urlServeZippedScheme()
    {
        return $this->urlMakeSecure($this->sBaseUrl . 'zip/{{ids}}/{{hash}}/{{filename}}');
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the crop utility
     * @param   string  $sBucket The bucket which the image resides in
     * @param   string  $sObject The filename of the image we're cropping
     * @param   integer $iWidth  The width of the cropped image
     * @param   integer $iHeight The height of the cropped image
     * @return  string
     */
    public function urlCrop($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl  = $this->sBaseUrl . 'crop/';
        $sUrl .= $iWidth . '/' . $iHeight . '/';
        $sUrl .= $sBucket . '/';
        $sUrl .= $sObject;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'crop' urls
     * @return  string
     */
    public function urlCropScheme()
    {
        $sUrl = $this->sBaseUrl . 'crop/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     * @param   string  $sBucket The bucket which the image resides in
     * @param   string  $sObject The filename of the image we're 'scaling'
     * @param   integer $iWidth  The width of the scaled image
     * @param   integer $iHeight The height of the scaled image
     * @return  string
     */
    public function urlScale($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl  = $this->sBaseUrl . 'scale/';
        $sUrl .= $iWidth . '/' . $iHeight . '/';
        $sUrl .= $sBucket . '/';
        $sUrl .= $sObject;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     * @return  string
     */
    public function urlScaleScheme()
    {
        $sUrl = $this->sBaseUrl . 'scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     * @param   integer $iWidth  The width of the placeholder
     * @param   integer $iHeight The height of the placeholder
     * @param   integer $iBorder The width of the border round the placeholder
     * @return  string
     */
    public function urlPlaceholder($iWidth, $iHeight, $iBorder = 0)
    {
        $sUrl  = $this->sBaseUrl . 'placeholder/';
        $sUrl .= $iWidth . '/' . $iHeight . '/' . $iBorder;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     * @return  string
     */
    public function urlPlaceholderScheme()
    {
        $sUrl = $this->sBaseUrl . 'placeholder/{{width}}/{{height}}/{{border}}';
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for a blank avatar
     * @param  integer        $iWidth  The width fo the avatar
     * @param  integer        $iHeight The height of the avatar§
     * @param  string|integer $mSex    What gender the avatar should represent
     * @return string
     */
    public function urlBlankAvatar($iWidth, $iHeight, $mSex = '')
    {
        $sUrl  = $this->sBaseUrl . 'blank_avatar/';
        $sUrl .= $iWidth . '/' . $iHeight;
        $sUrl .= $mSex ? '/' . $mSex : '';

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     * @return  string
     */
    public function urlBlankAvatarScheme()
    {
        $sUrl = $this->sBaseUrl . 'blank_avatar/{{width}}/{{height}}/{{sex}}';
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     * @param  string  $sBucket        The bucket which the image resides in
     * @param  string  $sObject        The object to be served
     * @param  integer $iExpires       The length of time the URL should be valid for, in seconds
     * @param  boolean $bForceDownload Whether to force a download
     * @return string
     */
    public function urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload = false)
    {
        //  Hash the expiry time
        $sHash  = $sBucket . '|' . $sObject . '|' . $iExpires . '|' . time() . '|';
        $sHash .= md5(time() . $sBucket . $sObject . $iExpires . APP_PRIVATE_KEY);
        $sHash  = get_instance()->encrypt->encode($sHash, APP_PRIVATE_KEY);
        $sHash  = urlencode($sHash);

        $sUrl  = $this->sBaseUrl . 'serve?token=' . $sHash;

        if ($bForceDownload) {

            $sUrl .= '&dl=1';
        }

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     * @return  string
     */
    public function urlExpiringScheme()
    {
        $sUrl = $this->sBaseUrl . 'serve?token={{token}}';
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     * @param  string $sUrl The URL to secure
     * @return string
     */
    protected function urlMakeSecure($sUrl)
    {
        return site_url($sUrl, isPageSecure());
    }
}
