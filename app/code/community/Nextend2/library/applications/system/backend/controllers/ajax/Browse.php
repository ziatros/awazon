<?php

class N2SystemBackendBrowseControllerAjax extends N2BackendControllerAjax
{

    public function actionIndex() {
        $this->validateToken();
        $root = N2Filesystem::fixPathSeparator(N2Filesystem::getImagesFolder());
        $path = N2Filesystem::realpath($root . '/' . ltrim(rtrim(N2Request::getVar('path', ''), '/'), '/'));
        if (strpos($path, $root) !== 0) {
            $path = $root;
        }
        $_directories = glob($path . NDS . '*', GLOB_ONLYDIR);
        (object)$directories = array();
        for ($i = 0; $i < count($_directories); $i++) {
            $directories[basename($_directories[$i])] = $this->relative($_directories[$i], $root);
        }

        $_files = glob($path . NDS . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $files  = array();
        for ($i = 0; $i < count($_files); $i++) {
            if (self::check_utf8($_files[$i])) {
                $files[basename($_files[$i])] = N2ImageHelper::dynamic(N2Filesystem::pathToAbsoluteURL($_files[$i]));
            }
        }
        $relativePath = $this->relative($path, $root);
        if (!$relativePath) {
            $relativePath = '';
        }
        $this->response->respond(array(
            'path'        => $relativePath,
            'directories' => $directories,
            'files'       => (object)$files
        ));
    }

    private static function check_utf8($str) {
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($str[$i]);
            if ($c > 128) {
                if (($c > 247)) return false; elseif ($c > 239) $bytes = 4;
                elseif ($c > 223) $bytes = 3;
                elseif ($c > 191) $bytes = 2;
                else return false;
                if (($i + $bytes) > $len) return false;
                while ($bytes > 1) {
                    $i++;
                    $b = ord($str[$i]);
                    if ($b < 128 || $b > 191) return false;
                    $bytes--;
                }
            }
        }
        return true;
    }

    public function actionUpload() {
        if (defined('N2_IMAGE_UPLOAD_DISABLE')) {
            N2Message::error(n2_('You are not allowed to upload!'));
            $this->response->error();
        }

        $this->validateToken();

        $root   = N2Filesystem::getImagesFolder();
        $folder = ltrim(rtrim(N2Request::getVar('path', ''), '/'), '/');
        $path   = N2Filesystem::realpath($root . '/' . $folder);

        if ($path === false || $path == '') {
            $folder = preg_replace("/[^A-Za-z0-9]/", '', $folder);
            if (empty($folder)) {
                N2Message::error(n2_('Folder is missing!'));
                $this->response->error();
            } else {
                N2Filesystem::createFolder($root . '/' . $folder);
                $path = N2Filesystem::realpath($root . '/' . $folder);
            }
        }

        $relativePath = $this->relative($path, $root);
        if (!$relativePath) {
            $relativePath = '';
        }
        $response = array(
            'path' => $relativePath
        );
        try {
            if (isset($_FILES) && isset($_FILES['image']) && isset($_FILES['image']['name'])) {
                $info     = pathinfo($_FILES['image']['name']);
                $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $info['filename']);
                if (strlen($fileName) == 0) {
                    $fileName = '';
                }
                $upload           = new N2BulletProof();
                $file             = $upload->uploadDir($path)
                                           ->upload($_FILES['image'], $fileName);
                $response['name'] = basename($file);
                $response['url']  = N2ImageHelper::dynamic(N2Filesystem::pathToAbsoluteURL($file));
            }
        } catch (Exception $e) {
            N2Message::error($e->getMessage());
            $this->response->error();
        }


        $this->response->respond($response);
    }

    private function relative($path, $root) {
        return substr(N2Filesystem::fixPathSeparator($path), strlen($root));
    }
}

/**
 * BULLETPROOF,
 *
 * This is a one-file solution for a quick and safe way of
 * uploading, watermarking, cropping and resizing images
 * during and after uploads with PHP with best security.
 *
 * This class is heavily commented, to be as much friendly as possible.
 * Please help out by posting out some bugs/flaws if you encounter any. Thanks!
 *
 * @category    Image uploader
 * @package     BulletProof
 * @version     1.4.0
 * @author      samayo
 * @link        https://github.com/samayo/BulletProof
 * @license     Luke 3:11 ( Free )
 */
class N2ImageUploaderException extends Exception
{

}

class N2BulletProof
{

    /*
    |--------------------------------------------------------------------------
    | Image Upload Properties
    \--------------------------------------------------------------------------*/

    /**
     * Set a group of default image types to upload.
     *
     * @var array
     */
    protected $imageType = array(
        "jpg",
        "jpeg",
        "png",
        "gif"
    );

    /**
     * Set a default file size to upload. Values are in bytes. Remember: 1kb ~ 1000 bytes.
     *
     * @var array
     */
    protected $imageSize = array(
        "min" => 1,
        "max" => 20000000
    );

    /**
     * Set a default min & maximum height & width for image to upload.
     *
     * @var array
     */
    protected $imageDimension = array(
        "height" => 10000,
        "width"  => 10000
    );

    /**
     * Set a default folder to upload images, if it does not exist, it will be created.
     *
     * @var string
     */
    protected $uploadDir = "uploads";

    /**
     * To get the real image/mime type. i.e gif, jpeg, png, ....
     *
     * @var string
     */
    protected $getMimeType;

    /*
    |--------------------------------------------------------------------------
    | Image Upload Methods
    \--------------------------------------------------------------------------*/

    /**
     * Stores image types to upload
     *
     * @param array $fileTypes -  ex: ['jpg', 'doc', 'txt'].
     *
     * @return $this
     */
    public function fileTypes(array $fileTypes) {
        $this->imageType = $fileTypes;
        return $this;
    }

    /**
     * Minimum and Maximum allowed image size for upload (in bytes),
     *
     * @param array $fileSize - ex: ['min'=>500, 'max'=>1000]
     *
     * @return $this
     */
    public function limitSize(array $fileSize) {
        $this->imageSize = $fileSize;
        return $this;
    }

    /**
     * Default & maximum allowed height and width image to download.
     *
     * @param array $dimensions
     *
     * @return $this
     */
    public function limitDimension(array $dimensions) {
        $this->imageDimension = $dimensions;
        return $this;
    }

    /**
     * Get the real image's Extension/mime type
     *
     * @param $imageName
     *
     * @return mixed
     * @throws N2ImageUploaderException
     */
    protected function getMimeType($imageName) {
        if (!file_exists($imageName)) {
            throw new N2ImageUploaderException("Image " . $imageName . " does not exist");
        }

        $listOfMimeTypes = array(
            1 => "gif",
            "jpeg",
            "png",
            "swf",
            "psd",
            "bmp",
            "tiff",
            "tiff",
            "jpc",
            "jp2",
            "jpx",
            "jb2",
            "swc",
            "iff",
            "wbmp",
            "xmb",
            "ico"
        );

        if (isset($listOfMimeTypes[exif_imagetype($imageName)])) {
            return $listOfMimeTypes[exif_imagetype($imageName)];
        }
    }

    /**
     * Handy method for getting image dimensions (W & H) in pixels.
     *
     * @param $getImage - The image name
     *
     * @return array
     */
    protected function getPixels($getImage) {
        list($width, $height) = getImageSize($getImage);
        return array(
            "width"  => $width,
            "height" => $height
        );
    }

    /**
     * Rename file either from method or by generating a random one.
     *
     * @param $isNameProvided - A new name for the file.
     *
     * @return string
     */
    protected function imageRename($isNameProvided) {
        if ($isNameProvided) {
            return $isNameProvided . "." . $this->getMimeType;
        }
        return uniqid(true) . "_" . str_shuffle(implode(range("E", "Q"))) . "." . $this->getMimeType;
    }

    /**
     * Get the specified upload dir, if it does not exist, create a new one.
     *
     * @param $directoryName   - directory name where you want your files to be uploaded
     * @param $filePermissions - octal representation of file permissions in linux environment
     *
     * @return $this
     * @throws N2ImageUploaderException
     */
    public function uploadDir($directoryName, $filePermissions = 0666) {
        if (!file_exists($directoryName) && !is_dir($directoryName)) {
            $createFolder = mkdir("" . $directoryName, $filePermissions, true);
            if (!$createFolder) {
                throw new N2ImageUploaderException("Folder " . $directoryName . " could not be created");
            }
        }
        $this->uploadDir = $directoryName;
        return $this;
    }

    /**
     * For getting common error messages from FILES[] array during upload.
     *
     * @return array
     */
    protected function commonUploadErrors($key) {
        $uploadErrors = array(
            UPLOAD_ERR_OK         => "...",
            UPLOAD_ERR_INI_SIZE   => "File is larger than the specified amount set by the server",
            UPLOAD_ERR_FORM_SIZE  => "File is larger than the specified amount specified by browser",
            UPLOAD_ERR_PARTIAL    => "File could not be fully uploaded. Please try again later",
            UPLOAD_ERR_NO_FILE    => "File is not found",
            UPLOAD_ERR_NO_TMP_DIR => "Can't write to disk, due to server configuration ( No tmp dir found )",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk. Please check you file permissions",
            UPLOAD_ERR_EXTENSION  => "A PHP extension has halted this file upload process"
        );

        return $uploadErrors[$key];
    }

    /**
     * Simple file check and delete wrapper.
     *
     * @param $fileToDelete
     *
     * @return bool
     * @throws N2ImageUploaderException
     */
    public function deleteFile($fileToDelete) {
        if (file_exists($fileToDelete) && !unlink($fileToDelete)) {
            throw new N2ImageUploaderException("File may have been deleted or does not exist");
        }
        return true;
    }

    /**
     * Final image uploader method, to check for errors and upload
     *
     * @param      $fileToUpload
     * @param null $isNameProvided
     *
     * @return string
     * @throws N2ImageUploaderException
     */
    public function upload($fileToUpload, $isNameProvided = null) {

        if (!function_exists('exif_imagetype')) {
            throw new N2ImageUploaderException("Function 'exif_imagetype' Not found.");
        }

        // Check if any errors are thrown by the FILES[] array
        if ($fileToUpload["error"]) {
            throw new N2ImageUploaderException($this->commonUploadErrors($fileToUpload["error"]));
        }

        // First get the real file extension
        $this->getMimeType = $this->getMimeType($fileToUpload["tmp_name"]);

        // Check if this file type is allowed for upload
        if (!in_array($this->getMimeType, $this->imageType)) {
            throw new N2ImageUploaderException(" This is not allowed file type!
             Please only upload ( " . implode(", ", $this->imageType) . " ) file types");
        }

        //Check if size (in bytes) of the image are above or below of defined in 'limitSize()'
        if ($fileToUpload["size"] < $this->imageSize["min"] || $fileToUpload["size"] > $this->imageSize["max"]
        ) {
            throw new N2ImageUploaderException("File sizes must be between " . implode(" to ", $this->imageSize) . " bytes");
        }

        // check if image is valid pixel-wise.
        $pixel = $this->getPixels($fileToUpload["tmp_name"]);

        if ($pixel["width"] < 4 || $pixel["height"] < 4) {
            throw new N2ImageUploaderException("This file is either too small or corrupted to be an image");
        }

        if ($pixel["height"] > $this->imageDimension["height"] || $pixel["width"] > $this->imageDimension["width"]) {
            throw new N2ImageUploaderException("Image pixels/size must be below " . implode(", ", $this->imageDimension) . " pixels");
        }

        // create upload directory if it does not exist
        $this->uploadDir($this->uploadDir);

        $i           = '';
        $newFileName = $this->imageRename($isNameProvided);

        while (file_exists($this->uploadDir . "/" . $newFileName)) {
            // The file already uploaded, nothing to do here
            if (self::isFilesIdentical($this->uploadDir . "/" . $newFileName, $fileToUpload["tmp_name"])) {
                return $this->uploadDir . "/" . $newFileName;
            }
            $i++;
            $newFileName = $this->imageRename($isNameProvided . $i);
        }

        // Upload the file
        $moveUploadedFile = $this->moveUploadedFile($fileToUpload["tmp_name"], $this->uploadDir . "/" . $newFileName);

        if ($moveUploadedFile) {
            return $this->uploadDir . "/" . $newFileName;
        } else {
            throw new N2ImageUploaderException(" File could not be uploaded. Unknown error occurred. ");
        }
    }

    public function moveUploadedFile($uploaded_file, $new_file) {
        if (!is_uploaded_file($uploaded_file)) {
            return copy($uploaded_file, $new_file);
        }
        return move_uploaded_file($uploaded_file, $new_file);
    }

    private static function isFilesIdentical($fn1, $fn2) {
        if (filetype($fn1) !== filetype($fn2)) return FALSE;

        if (filesize($fn1) !== filesize($fn2)) return FALSE;

        if (sha1_file($fn1) != sha1_file($fn2)) return false;

        return true;
    }
}
