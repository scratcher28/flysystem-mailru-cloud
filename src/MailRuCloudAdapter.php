<?php

namespace Freecod\FlysystemMailRuCloud;

use Friday14\Mailru\Cloud;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Util\MimeType;
use SplFileObject;

class MailRuCloudAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;
    
    protected $client;
    
    public function __construct(Cloud $client)
    {
        $this->client = $client;
    }
    
    protected function makePath($path, Config $config)
    {
        $path = explode(DIRECTORY_SEPARATOR, $path);
        array_pop($path);
        
        $current = '';
        
        foreach ($path as $directory) {
            
            $current = $current ? ($current . DIRECTORY_SEPARATOR . $directory) : $directory;
            
            if ( !$this->has($current)) {
                $this->createDir($current, $config);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        $stream = tmpfile();
        fputs($stream, $contents);
        
        return $this->writeStream($path, $stream, $config);
    }
    
    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        $this->makePath($path, $config);
        
        $tmpFilePath = stream_get_meta_data($resource);
        $file = new SplFileObject($tmpFilePath['uri']);
        
        return $this->client->upload($file, $path);
    }
    
    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        $stream = tmpfile();
        fputs($stream, $contents);
        
        return $this->updateStream($path, $stream, $config);
    }
    
    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        $this->client->delete($path);
        
        $tmpFilePath = stream_get_meta_data($resource);
        $file = new SplFileObject($tmpFilePath['uri']);
        
        return $this->client->upload($file, $path);
    }
    
    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        return $this->client->rename($path, $newpath);
    }
    
    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        return $this->client->copy($path, $newpath);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        return $this->client->delete($path);
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        return $this->client->delete($dirname);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        return $this->client->createFolder($dirname);
    }
    
    /**
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        try {
            $this->getMetadata($path);
            return true;
            
        } catch (ClientException $ex) {
            if ($ex->getCode() != 404) {
                throw $ex;
            }
        } catch (FileNotFoundException $ex) {}
        
        return false;
    }
    
    /**
     * @param string $path
     * @return array|bool|false
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
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
    
    /**
     * @param string $path
     * @return array|bool|false
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
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
    
    /**
     * {@inheritdoc}
     */
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
    
    /**
     * @param string $path
     * @return array|false
     * @throws FileNotFoundException
     */
    public function getMetadata($path)
    {
        $path = explode(DIRECTORY_SEPARATOR, $path);
        $file = array_pop($path);
        $directory = implode(DIRECTORY_SEPARATOR, $path);
        
        $meta = null;
        
        foreach ($this->client->files($directory)->body->list as $fileMeta) {
            if ($fileMeta->name == $file) {
                $meta = $fileMeta;
                break;
            }
        }
        
        if ( !$meta) {
            throw new FileNotFoundException($directory);
        }
        
        return (array)$meta;
    }
    
    /**
     * @param string $path
     * @return array|false|mixed|null
     * @throws FileNotFoundException
     */
    public function getSize($path)
    {
        return $this->getMetadata($path)['size'] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return ['mimetype' => MimeType::detectByFilename($path)];
    }
    
    /**
     * @param string $path
     * @return array|false|mixed|null
     * @throws FileNotFoundException
     */
    public function getTimestamp($path)
    {
        return ['timestamp' => $this->getMetadata($path)['mtime'] ?? null];
    }
}