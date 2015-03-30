<?php
namespace Omeka\Media;

use finfo;
use Zend\Math\Rand;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class StorableFile implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    /**
     * The storage prefix for original files.
     */
    const ORIGINAL_STORAGE_PREFIX = 'original';

    /**
     * @var string Path to the temporary file
     */
    protected $tempPath;

    /**
     * @var string Base name of the stored file (without extension)
     */
    protected $storageBaseName;

    /**
     * @var string Name of the stored file (with extension)
     */
    protected $storageName;

    /**
     * @var string Internet media type of the file
     */
    protected $mediaType;

    /**
     * @var string Filename extension of the original file
     */
    protected $extension;

    /**
     * Store this file.
     *
     * @param string $originalName The original name of the file
     */
    public function storeOriginal($originalName)
    {
        $extension = $this->getExtension($originalName);
        $storagePath = sprintf('%s/%s', self::ORIGINAL_STORAGE_PREFIX,
            $this->getStorageName($extension));
        $fileStore = $this->getServiceLocator()->get('Omeka\FileStore');
        $fileStore->put($this->getTempPath(), $storagePath);
    }

    /**
     * Create and store thumbnails of this file.
     *
     * @return bool Whether thumbnails were created and stored
     */
    public function storeThumbnails()
    {
        $manager = $this->getServiceLocator()->get('Omeka\ThumbnailManager');
        return $manager->create($this->getTempPath(), $this->getStorageBaseName());
    }

    /**
     * Get the path to the temporary file.
     *
     * @return string
     */
    public function getTempPath()
    {
        if (isset($this->tempPath)) {
            return $this->tempPath;
        }
        $this->setTempPath();
        return $this->tempPath;
    }

    /**
     * Set the path to the temporary file.
     *
     * @param null|string $tempDir
     */
    public function setTempPath($tempDir = null)
    {
        if (!isset($tempDir)) {
            $tempDir = $this->getServiceLocator()->get('Config')['temp_dir'];
        }
        $this->tempPath = tempnam($tempDir, 'omeka');
    }

    /**
     * Delete this temporary file.
     *
     * Always delete a temporary file after all work has been done. Otherwise
     * the file will remain in the temporary directory.
     *
     * @return bool Whether the file was deleted or never created
     */
    public function delete()
    {
        if (isset($this->tempPath)) {
            return unlink($this->tempPath);
        }
        return true;
    }

    /**
     * Get the base name of the persistently stored file.
     *
     * @return string
     */
    public function getStorageBaseName()
    {
        if (isset($this->storageBaseName)) {
            return $this->storageBaseName;
        }
        $this->storageBaseName = bin2hex(Rand::getBytes(20));
        return $this->storageBaseName;
    }

    /**
     * Get the name of the persistently stored file.
     *
     * @param string $extension The filename extension to append
     * @return string
     */
    public function getStorageName($extension = null)
    {
        if (isset($this->storageName)) {
            return $this->storageName;
        }
        $this->storageName = sprintf('%s%s', $this->getStorageBaseName(),
            $extension ? ".$extension" : null);
        return $this->storageName;
    }

    /**
     * Get the Internet media type of the file.
     *
     * @uses finfo
     * @param string $filename The path to a file
     * @return string
     */
    public function getMediaType()
    {
        if (isset($this->mediaType)) {
            return $this->mediaType;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mediaType = $finfo->file($this->getTempPath());
        $this->mediaType = $mediaType;
        return $mediaType;
    }

    /**
     * Get the filename extension for the original file.
     *
     * Checks the extension against a map of Internet media types. Returns a
     * "best guess" extension if the media type is known but the original
     * extension is unrecognized or nonexistent. Returns the original extension
     * if it is unrecoginized, maps to a known media type, or maps to the
     * catch-all media type, "application/octet-stream".
     *
     * @param string $originalFile The original file name
     * @return string
     */
    public function getExtension($originalFile)
    {
        if (isset($this->extension)) {
            return $this->extension;
        }
        $map = $this->getServiceLocator()->get('Omeka\MediaTypeExtensionMap');
        $mediaType = $this->getMediaType();
        $extension = substr(strrchr($originalFile, '.'), 1);
        if (isset($map[$mediaType][0])
            && !in_array($mediaType, array('application/octet-stream'))
        ) {
            if ($extension) {
                if (!in_array($extension, $map[$mediaType])) {
                    // Unrecognized extension.
                    $extension = $map[$mediaType][0];
                }
            } else {
                // No extension.
                $extension = $map[$mediaType][0];
            }
        }
        $this->extension = $extension;
        return $extension;
    }
}
