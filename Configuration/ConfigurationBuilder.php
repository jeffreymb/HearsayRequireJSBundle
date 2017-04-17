<?php

/*
 * This file is part of the HearsayRequireJSBundle package.
 *
 * (c) Hearsay News Products, Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hearsay\RequireJSBundle\Configuration;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Hearsay\RequireJSBundle\Configuration\NamespaceMappingInterface;

/**
 * Helper service to build RequireJS configuration options from the Symfony
 * configuration.
 * @author Kevin Montag <kevin@hearsay.it>
 */
class ConfigurationBuilder
{
    /**
     * The base URL where assets are served, relative to the website root
     * directory
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var NamespaceMappingInterface
     */
    protected $mapping;

    /**
     * An array of options
     *
     * @var array
     */
    protected $options = array();

    /**
     * An array of paths
     *
     * @var array
     */
    protected $paths = array();

    /**
     * The shim config
     *
     * @var array
     */
    protected $shim  = array();

    /**
     * An array of dependencies
     *
     * @var array
     */
    protected $deps  = array();

    /**
     * An array of priorities
     *
     * @var array
     */
    protected $priority = array();

    /**
     * Flag to control if almond is used or not.
     *
     * @var boolean
     */
    protected $useAlmond = false;

    /**
     * The constructor method
     *
     * @param ContainerInterface        $container
     * @param NamespaceMappingInterface $mapping
     * @param string                    $baseUrl   The base URL where assets
     *                                             are served, relative to the
     *                                             website root directory
     * @param array                     $shim      The shim config
     */
    public function __construct(
        ContainerInterface $container,
        NamespaceMappingInterface $mapping,
        $baseUrl = '',
        $shim = array(),
        $deps = array(),
        $priority = array()
    ) {
        $this->container = $container;
        $this->mapping   = $mapping;
        $this->baseUrl   = ltrim($baseUrl, '/');
        $this->shim      = $shim;
        $this->deps      = $deps;
        $this->priority  = $priority;
    }

    /**
     * Adds the option
     *
     * @param string $name  The option name
     * @param mixed  $value The option value
     */
    public function addOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * Gets the RequireJS configuration options
     *
     * @return array
     */
    public function getConfiguration()
    {
        $config = array(
            'baseUrl' => $this->getScriptUrl(),
            'locale'  => $this->container->get('translator')->getLocale(),
        );

        if ($this->paths) {
            $config['paths'] = $this->paths;
        }

        if ($this->shim) {
            $config['shim'] = $this->optimizeShim($this->shim);
        }

        if ($this->deps) {
            $config['deps'] = $this->deps;
        }

        if ($this->priority) {
            $config['priority'] = $this->priority;
        }

        if ($this->container->hasParameter('kernel.debug')
            && !$this->container->getParameter('kernel.debug')
            && $this->useAlmond) {
            $config['almond'] = true;
        }

        return array_merge($config, $this->options);
    }

    /**
     * Optimizes shim array where possible
     *
     * @return array
     */
    protected function optimizeShim($shim)
    {
        $optimized = array();
        foreach ($shim AS $key => $value)
        {
            if (array_key_exists('deps', $value) && count($value['deps']) == 0) {
                unset($value['deps']);
            }
            if (count($value) > 0) {
                if (array_key_exists('deps', $value) && count($value) == 1) {
                    $optimized[$key] = $value['deps'];
                } else {
                    $optimized[$key] = $value;
                }
            }
        }

        return $optimized;
    }

    /**
     * Sets a path definition to be included in the configuration
     *
     * @param string       $path      The path name
     * @param string|array $locations The actual path locations
     */
    public function setPath($path, $locations)
    {
        if (!is_array($locations)) {
            $locations = (array) $locations;
        }

        foreach ($locations as &$location) {
            if (preg_match('~^(\/\/|http|https)~', $location)) {
                continue;
            }

            $modulePath = $this->mapping->getModulePath($location);

            if ($modulePath) {
                $modulePath = preg_replace('~\.js$~', '', $modulePath);
                $location = $this->getBaseUrl() . '/' . $modulePath;
            }
        }

        unset($location);

        if (count($locations) == 1) {
            $locations = array_shift($locations);
        }

        $this->paths[$path] = $locations;
    }

    /**
     * Sets if almond.js is used or not
     *
     * @param boolean $useAlmond The almond.js used value
     *
     * @return boolean
     */
    public function setUseAlmond($useAlmond)
    {
        $this->useAlmond = (bool) $useAlmond;
    }

    /**
     * Gets the base URL
     *
     * @return string
     */
    protected function getBaseUrl()
    {

        $baseUrl = $this->container->get('templating.helper.assets')->getUrl('');

        // Remove ?version from the end of the base URL
        if (($pos = strpos($baseUrl, '?')) !== false) {
            $baseUrl = substr($baseUrl, 0, $pos);
        }
        
        // Remove trailing slash, if there is one
        return rtrim($baseUrl, '/');
    }

    /**
     * Gets the URL to script
     *
     * @return string
     */
    protected function getScriptUrl()
    {
        return $this->getBaseUrl() . '/' . $this->baseUrl;
    }
}
