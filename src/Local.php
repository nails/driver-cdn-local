<?php

namespace Nails\Cdn\Driver;

class Local implements \Nails\Cdn\Interfaces\Driver
{
    private $cdn;
    public $errors;
    protected $sBaseUrl;

    // --------------------------------------------------------------------------

    /**
     * Constructor
     */
    public function __construct()
    {
        //  Shortcut to CDN
        $this->cdn    =& get_instance()->cdn;
        $this->errors = array();

        // --------------------------------------------------------------------------

        //  Load langfile and dependant helper
        $oCi =& get_instance();
        $oCi->lang->load('cdn/cdn_driver_local');
        $oCi->load->helper('string');

        // --------------------------------------------------------------------------

        $this->sBaseUrl = defined('DEPLOY_CDN_BASE_URL') ? DEPLOY_CDN_BASE_URL : 'cdn';
        $this->sBaseUrl = addTrailingSlash($this->sBaseUrl);
    }

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     * @param  stdClass $data Data to create the object with
     * @return boolean
     */
    public function object_create($data)
    {
        $bucket   = ! empty($data->bucket->slug) ? $data->bucket->slug : '';
        $filename = ! empty($data->filename)     ? $data->filename     : '';
        $source   = ! empty($data->file)         ? $data->file         : '';

        // --------------------------------------------------------------------------

        //  Check directory exists
        if (!is_dir(DEPLOY_CDN_PATH . $bucket)) {

            //  Hmm, not writeable, can we create it?
            if (!@mkdir(DEPLOY_CDN_PATH . $bucket)) {

                //  Nope, failed to create the directory - we iz gonna have problems if we continue, innit.
                $this->cdn->set_error(lang('cdn_error_target_write_fail_mkdir', DEPLOY_CDN_PATH . $bucket));
                return false;
            }
        }

        // --------------------------------------------------------------------------

        //  Check bucket is writeable
        if (!is_really_writable(DEPLOY_CDN_PATH . $bucket)) {

            $this->cdn->set_error(lang('cdn_error_target_write_fail', DEPLOY_CDN_PATH . $bucket));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Move the file
        $dest = DEPLOY_CDN_PATH . $bucket . '/' . $filename;

        if (@move_uploaded_file($source, $dest)) {

            return true;

        //  Hmm, failed to move, try copying it.
        } elseif (@copy($source, $dest)) {

            return true;

        } else {

            $this->cdn->set_error(lang('cdn_error_couldnotmove'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     * @param  string $filename The object's filename
     * @param  string $bucket   The bucket's slug
     * @return boolean
     */
    public function object_exists($filename, $bucket)
    {
        return is_file(DEPLOY_CDN_PATH . $bucket . '/' . $filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permenantly deletes) an object
     * @param  string $object The object's filename
     * @param  string $bucket The bucket's slug
     * @return boolean
     */
    public function object_destroy($object, $bucket)
    {
        $file   = urldecode($object);
        $bucket = urldecode($bucket);

        if (file_exists(DEPLOY_CDN_PATH . $bucket . '/' . $file)) {

            if (@unlink(DEPLOY_CDN_PATH . $bucket . '/' . $file)) {

                //  @TODO: Delete Cache items
                return true;

            } else {

                $this->cdn->set_error(lang('cdn_error_delete'));
                return false;
            }

        } else {

            $this->cdn->set_error(lang('cdn_error_delete_nofile'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     * @param  string $bucket   The bucket's slug
     * @param  string $filename The filename
     * @return mixed            string on success, false on failure
     */
    public function object_local_path($bucket, $filename)
    {
        $path = DEPLOY_CDN_PATH . $bucket . '/' . $filename;

        if (is_file($path)) {

            return $path;

        } else {

            $this->cdn->set_error('Could not find a valid local path for object ' . $bucket . '/' . $filename);
            return false;
        }
    }

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     * @param  string $bucket The bucket's slug
     * @return  boolean
     **/
    public function bucket_create($bucket)
    {
        $dir = DEPLOY_CDN_PATH . $bucket;

        if (is_dir($dir) && is_writeable($dir)) {

            return true;
        }

        // --------------------------------------------------------------------------

        if (@mkdir($dir)) {

            return true;

        } else {

            if (getUserObject()->isSuperuser()) {

                $this->cdn->set_error(lang('cdn_error_bucket_mkdir_su', $dir));

            } else {

                $this->cdn->set_error(lang('cdn_error_bucket_mkdir'));
            }

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     * @param  string $bucket The bucket's slug
     * @return boolean
     */
    public function bucket_destroy($bucket)
    {
        if (rmdir(DEPLOY_CDN_PATH . $bucket)) {

            return true;

        } else {

            $this->cdn->set_error(lang('cdn_error_bucket_unlink'));
            return false;
        }
    }

    /**
     * URL GENERATOR METHODS
     */

    /**
     * Generates the correct URL for serving a file
     * @param  string  $object        The object to serve
     * @param  string  $bucket        The bucket to serve from
     * @param  boolean $forceDownload Whether to force a download
     * @return string
     */
    public function url_serve($object, $bucket, $forceDownload)
    {
        $out  = $this->sBaseUrl . 'serve/';
        $out .= $bucket . '/';
        $out .= $object;

        if ($forceDownload) {

            $out .= '?dl=1';
        }

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     * @param  boolean $forceDownload Whetehr or not to force download
     * @return string
     */
    public function url_serve_scheme($forceDownload)
    {
        $out = $this->sBaseUrl . 'serve/{{bucket}}/{{filename}}{{extension}}';

        if ($forceDownload) {

            $out .= '?dl=1';
        }

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a URL for serving zipped objects
     * @param  string $objectIds A comma seperated list of object IDs
     * @param  string $hash      The security hash
     * @param  string $filename  The filename ot give the zip file
     * @return string
     */
    public function url_serve_zipped($objectIds, $hash, $filename)
    {
        $filename = $filename ? '/' . urlencode($filename) : '';

        return $this->urlMakeSecure($this->sBaseUrl . 'zip/' . $objectIds . '/' . $hash . $filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'zipped' urls
     * @return  string
     **/
    public function url_serve_zipped_scheme()
    {
        return $this->urlMakeSecure($this->sBaseUrl . 'zip/{{ids}}/{{hash}}/{{filename}}');
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the thumb utility
     * @param   string  $bucket The bucket which the image resides in
     * @param   string  $object The filename of the image we're 'thumbing'
     * @param   string  $width  The width of the thumbnail
     * @param   string  $height The height of the thumbnail
     * @return  string
     **/
    public function url_thumb($object, $bucket, $width, $height)
    {
        $out  = $this->sBaseUrl . 'thumb/';
        $out .= $width . '/' . $height . '/';
        $out .= $bucket . '/';
        $out .= $object;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'thumb' urls
     * @return  string
     **/
    public function url_thumb_scheme()
    {
        $out = $this->sBaseUrl . 'thumb/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     * @param   string  $bucket The bucket which the image resides in
     * @param   string  $object The filename of the image we're 'scaling'
     * @param   string  $width  The width of the scaled image
     * @param   string  $height The height of the scaled image
     * @return  string
     **/
    public function url_scale($object, $bucket, $width, $height)
    {
        $out  = $this->sBaseUrl . 'scale/';
        $out .= $width . '/' . $height . '/';
        $out .= $bucket . '/';
        $out .= $object;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     * @return  string
     **/
    public function url_scale_scheme()
    {
        $out = $this->sBaseUrl . 'scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   int     border  The width of the border round the placeholder
     * @return  string
     **/
    public function url_placeholder($width = 100, $height = 100, $border = 0)
    {
        $out  = $this->sBaseUrl . 'placeholder/';
        $out .= $width . '/' . $height . '/' . $border;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     * @return  string
     **/
    public function url_placeholder_scheme()
    {
        $out = $this->sBaseUrl . 'placeholder/{{width}}/{{height}}/{{border}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   int     border  The width of the border round the placeholder
     * @return  string
     **/
    public function url_blank_avatar($width = 100, $height = 100, $sex = '')
    {
        $out  = $this->sBaseUrl . 'blank_avatar/';
        $out .= $width . '/' . $height;
        $out .= $sex ? '/' . $sex : '';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     * @return  string
     **/
    public function url_blank_avatar_scheme()
    {
        $out = $this->sBaseUrl . 'blank_avatar/{{width}}/{{height}}/{{sex}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     * @param  string  $bucket        The bucket which the image resides in
     * @param  string  $object        The object to be served
     * @param  string  $expires       The length of time the URL should be valid for, in seconds
     * @param  boolean $forceDownload Whether to force a download
     * @return string
     **/
    public function url_expiring($object, $bucket, $expires, $forceDownload = false)
    {
        //  Hash the expiry time
        $hash  = $bucket . '|' . $object . '|' . $expires . '|' . time() . '|';
        $hash .= md5(time() . $bucket . $object . $expires . APP_PRIVATE_KEY);
        $hash  = get_instance()->encrypt->encode($hash, APP_PRIVATE_KEY);
        $hash  = urlencode($hash);

        $out  = $this->sBaseUrl . 'serve?token=' . $hash;

        if ($forceDownload) {

            $out .= '&dl=1';
        }

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     * @param   none
     * @return  string
     **/
    public function url_expiring_scheme()
    {
        $out = $this->sBaseUrl . 'serve?token={{token}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     * @param  string $url The URL to secure
     * @return string
     */
    protected function urlMakeSecure($url)
    {
        return site_url($url, isPageSecure());
    }
}
