<?php

namespace Freecod\FlysystemMailRuCloud;

use Friday14\Mailru\Cloud;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;

class MailRuCloudAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;
    
    protected $client;
    
    public function __construct(Cloud $client)
    {
        $this->client = $client;
    }
    
    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        return $this->client->createFile($path, $contents);
    }
    
    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->client->createFile($path, $resource);
    }
    
    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        $this->client->delete($path);
        
        return $this->client->createFile($path, $contents);
    }
    
    public function updateStream($path, $resource, Config $config)
    {
        $this->client->delete($path);
    
        return $this->client->createFile($path, $resource);
    }
    
    public function rename($path, $newpath)
    {
        return $this->client->rename($path, $newpath);
    }
    
    public function copy($path, $newpath)
    {
        return $this->client->copy($path, $newpath);
    }
    
    public function delete($path)
    {
        return $this->client->delete($path);
    }
    
    public function deleteDir($dirname)
    {
        return $this->client->delete($dirname);
    }
    
    public function createDir($dirname, Config $config)
    {
        return $this->client->createFolder($dirname);
    }
    
    public function has($path)
    {
        try {
            $this->rename($path, $path);
            
        } catch (\Exception $ex) {
            return false;
        }
        
        return true;
    }
    
    public function read($path)
    {
        if (! $object = $this->readStream($path)) {
            return false;
        }
    
        $object['contents'] = stream_get_contents($object['stream']);
        fclose($object['stream']);
        unset($object['stream']);
    
        return $object;
    }
    
    public function readStream($path)
    {
        $stream = fopen('php://temp', 'w+b');
        
        $result = $this->client->download($path, $stream);
        rewind($stream);
    
        if ( ! $result) {
            fclose($stream);
        
            return false;
        }
    
        return ['type' => 'file', 'path' => $path, 'stream' => $stream];
    }
    
    public function listContents($directory = '', $recursive = false)
    {
        $dirs = $this->client->files($directory);
        
        return $dirs;
    }
    
    public function getMetadata($path)
    {
        // TODO: Implement getMetadata() method.
    }
    
    public function getSize($path)
    {
        // TODO: Implement getSize() method.
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return ['mimetype' => MimeType::detectByFilename($path)];
    }
    
    public function getTimestamp($path)
    {
        // TODO: Implement getTimestamp() method.
    }
}