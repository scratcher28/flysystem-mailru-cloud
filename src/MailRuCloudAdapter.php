<?php

namespace Freecod\FlysystemMailRuCloud;

use Friday14\Mailru\Cloud;
use GuzzleHttp\Exception\ClientException;
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
        $path = explode(DIRECTORY_SEPARATOR, $path);
        $file = array_pop($path);
        $path = implode(DIRECTORY_SEPARATOR, $path);
        
        try {
            $files = $this->listContents($path);
            $trimCount = mb_strlen($path);
            
            $files = array_map(function ($item) use ($trimCount) {
                return ltrim(mb_substr($item['path'], $trimCount), DIRECTORY_SEPARATOR);
            }, array_filter($files, function ($item) {
                return $item['type'] == 'file';
            }));
            
            return in_array($file, $files);
            
        } catch (ClientException $ex) {
            if ($ex->getCode() != 404) {
                throw $ex;
            }
        }
        
        return false;
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
        $stream = tmpfile();
        $tmpfilePath = stream_get_meta_data($stream);
        
        $result = $this->client->download(DIRECTORY_SEPARATOR . $path, $tmpfilePath['uri']);
        rewind($stream);
        
        if ( ! $result) {
            fclose($stream);
            
            return false;
        }
        
        return ['type' => 'file', 'path' => $path, 'stream' => $stream];
    }
    
    public function listContents($directory = '', $recursive = false)
    {
        $items = [];
        
        $response = $this->client->files($directory);
        
        foreach ($response->body->list as $item) {
            $element = [
                'path' => ltrim($item->home, '/'),
                'type' => $item->type == 'folder' ? 'dir' : 'file',
                'size' => $item->size,
            ];
            
            $items[] = $element;
            
            if ($item->type == 'folder' && $recursive) {
                
                $subItems = $this->listContents($item->home, $recursive);
                
                $items = array_merge($items, $subItems);
            }
        }
        
        return $items;
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