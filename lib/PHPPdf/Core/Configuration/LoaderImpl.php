<?php

/*
 * Copyright 2011 Piotr Śliwa <peter.pl7@gmail.com>
 *
 * License information is in LICENSE file
 */

namespace PHPPdf\Core\Configuration;

use PHPPdf\Core\Parser\ColorPaletteParser;
use PHPPdf\Core\UnitConverter;
use PHPPdf\Core\Parser\FontRegistryParser;
use PHPPdf\Core\Parser\ComplexAttributeFactoryParser;
use PHPPdf\Core\Parser\NodeFactoryParser;
use PHPPdf\Cache\NullCache;
use PHPPdf\Cache\Cache;

/**
 * Standard configuration loader.
 * 
 * Loads configuration from xml files.
 * 
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
class LoaderImpl implements Loader
{
    private $nodeFile = null;
    private $complexAttributeFile = null;
    private $fontFile = null;
    private $colorFile = null;
    
    private $complexAttributeFactory;
    private $nodeFactory;
    private $fontRegistry;
    private $colorPalette;
    
    private $unitConverter;
    
    private $cache;
    
    public function __construct($nodeFile = null, $complexAttributeFile = null, $fontFile = null, $colorFile = null)
    {
        if($nodeFile === null)
        {
            $nodeFile = __DIR__.'/../../Resources/config/nodes.xml';
        }
        
        if($complexAttributeFile === null)
        {
            $complexAttributeFile = __DIR__.'/../../Resources/config/complex-attributes.xml';
        }
        
        if($fontFile === null)
        {
            $fontFile = __DIR__.'/../../Resources/config/fonts.xml';
        }
        
        if($colorFile === null)
        {
            $colorFile = __DIR__.'/../../Resources/config/colors.xml';
        }
        
        $this->nodeFile = $nodeFile;        
        $this->complexAttributeFile = $complexAttributeFile;        
        $this->fontFile = $fontFile;
        $this->colorFile = $colorFile;

        $this->setCache(NullCache::getInstance());
    }
    
    public function setUnitConverter(UnitConverter $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }

    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

	public function createComplexAttributeFactory()
    {
        if($this->complexAttributeFactory === null)
        {
            $this->complexAttributeFactory = $this->loadComplexAttributes();
        }        

        return $this->complexAttributeFactory;
    }

	public function createFontRegistry()
    {
        if($this->fontRegistry === null)
        {
            $this->fontRegistry = $this->loadFonts();
        }        

        return $this->fontRegistry;
        
    }

	public function createNodeFactory()
    {
        if($this->nodeFactory === null)
        {
            $this->nodeFactory = $this->loadNodes();
        }        

        return $this->nodeFactory;
    }

    protected function loadNodes()
    {
        $file = $this->nodeFile;

        $unitConverter = $this->unitConverter;
        $doLoadNodes = function($content) use($unitConverter)
        {
            $nodeFactoryParser = new NodeFactoryParser();
            if($unitConverter)
            {
                $nodeFactoryParser->setUnitConverter($unitConverter);
            }

            $nodeFactory = $nodeFactoryParser->parse($content);

            return $nodeFactory;
        };

        /* @var $nodeFactory PHPpdf\Node\Factory */
        $nodeFactory = $this->getFromCacheOrCallClosure($file, $doLoadNodes);

        //TODO: DI
        if($nodeFactory->hasPrototype('page') && $nodeFactory->hasPrototype('dynamic-page'))
        {
            $page = $nodeFactory->create('page');
            $nodeFactory->getPrototype('dynamic-page')->setPrototypePage($page);
        }
        
        return $nodeFactory;
    }

    protected function getFromCacheOrCallClosure($file, \Closure $closure)
    {
        $id = $this->getCacheId($file);

        if($this->cache->test($id))
        {
            $result = $this->cache->load($id);
        }
        else
        {
            $content = $this->loadFile($file);
            $result = $closure($content);
            $this->cache->save($result, $id);
        }

        return $result;
    }

    private function getCacheId($file)
    {
        return str_replace('-', '_', (string) crc32($file));
    }

    private function loadFile($file)
    {
        return $file;
    }

    private function loadComplexAttributes()
    {
        $file = $this->complexAttributeFile;

        $doLoadComplexAttributes = function($content)
        {
            $complexAttributeFactoryParser = new ComplexAttributeFactoryParser();
            $complexAttributeFactory = $complexAttributeFactoryParser->parse($content);

            return $complexAttributeFactory;
        };

        return $this->getFromCacheOrCallClosure($file, $doLoadComplexAttributes);
    }

    protected function loadFonts()
    {
        $file = $this->fontFile;

        $doLoadFonts = function($content)
        {
            $fontRegistryParser = new FontRegistryParser();
            $fontRegistry = $fontRegistryParser->parse($content);

            return $fontRegistry;
        };

        return $this->getFromCacheOrCallClosure($file, $doLoadFonts);
    }
    
    public function createColorPalette()
    {
        if($this->colorPalette === null)
        {
            $this->colorPalette = $this->loadColorPalette();
        }        

        return $this->colorPalette;
    }
    
    private function loadColorPalette()
    {
        $file = $this->colorFile;

        $doLoadColorPalette = function($content)
        {
            $colorPaletteParser = new ColorPaletteParser();
            
            return $colorPaletteParser->parse($content);
        };

        return $this->getFromCacheOrCallClosure($file, $doLoadColorPalette);
    }    
}