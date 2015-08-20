<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\menu;

use Herbie;
use Twig_SimpleFunction;

class MenuPlugin extends Herbie\Plugin
{

    public function onTwigInitialized($twig)
    {
        $options = ['is_safe' => ['html']];
        $twig->addFunction(
            new Twig_SimpleFunction('menu_ascii', [$this, 'functionAscii'], $options)
        );
        $twig->addFunction(
            new Twig_SimpleFunction('menu_breadcrumb', [$this, 'functionBreadcrumb'], $options)
        );
        $twig->addFunction(
            new Twig_SimpleFunction('menu_html', [$this, 'functionHtml'], $options)
        );
        $twig->addFunction(
            new Twig_SimpleFunction('menu_sitemap', [$this, 'functionSitemap'], $options)
        );
    }

    public function onShortcodeInitialized($shortcode)
    {
        $shortcode->add('menu_ascii', [$this, 'functionAsciiTree']);
        $shortcode->add('menu_breadcrumb', [$this, 'functionBreadcrumb']);
        $shortcode->add('menu_html', [$this, 'functionHtml']);
        $shortcode->add('menu_sitemap', [$this, 'functionSitemap']);
    }

    /**
     * @param array $options
     * @return string
     */
    public function functionAsciiTree($options)
    {
        $options = (array)$options;
        extract($options); // showHidden, route, maxDepth, class
        $showHidden = isset($showHidden) ? (bool) $showHidden : false;
        $route = isset($route) ? (string)$route : '';
        $maxDepth = isset($maxDepth) ? (int)$maxDepth : -1;
        $class = isset($class) ? (string)$class : 'sitemap';

        $branch = $this->getService('Menu\Page\Node')->findByRoute($route);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);
        $filterIterator = new Herbie\Menu\Page\Iterator\FilterIterator($treeIterator);
        $filterIterator->setEnabled(!$showHidden);

        $asciiTree = new Herbie\Menu\Page\Renderer\AsciiTree($filterIterator);
        $asciiTree->setMaxDepth($maxDepth);
        return $asciiTree->render();
    }

    /**
     * @param array $options
     * @return string
     */
    public function functionBreadcrumb($options)
    {
        // Options
        $options = (array)$options;
        extract($options);
        $delim = isset($delim) ? $delim : '';
        $homeLink = isset($homeLink) ? $homeLink : null;
        $reverse = isset($reverse) ? (bool) $reverse : false;

        $links = [];

        if (!empty($homeLink)) {
            if (is_array($homeLink)) {
                $route = reset($homeLink);
                $label = isset($homeLink[1]) ? $homeLink[1] : 'Home';
            } else {
                $route = $homeLink;
                $label = 'Home';
            }
            $links[] = $this->createLink($route, $label);
        }

        foreach ($this->getService('Menu\Page\RootPath') as $item) {
            $links[] = $this->createLink($item->route, $item->title);
        }

        if (!empty($reverse)) {
            $links = array_reverse($links);
        }

        $html = '<ul class="breadcrumb">';
        foreach ($links as $link) {
            $html .= '<li>' . $link . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @param array $options
     * @return string
     */
    public function functionHtml($options)
    {
        $options = (array)$options;
        extract($options); // showHidden, route, maxDepth, class
        $showHidden = isset($showHidden) ? (bool)$showHidden : false;
        $route = isset($route) ? (string)$route : '';
        $maxDepth = isset($maxDepth) ? (int)$maxDepth : -1;
        $class = isset($class) ? (string)$class : 'menu';

        $branch = $this->getService('Menu\Page\Node')->findByRoute($route);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);

        // using FilterCallback for better filtering of nested items
        $routeLine = $this->getService('Request')->getRouteLine();
        $callback = [new Herbie\Menu\Page\Iterator\FilterCallback($routeLine, $showHidden), 'call'];
        $filterIterator = new \RecursiveCallbackFilterIterator($treeIterator, $callback);

        $htmlTree = new Herbie\Menu\Page\Renderer\HtmlTree($filterIterator);
        $htmlTree->setMaxDepth($maxDepth);
        $htmlTree->setClass($class);
        $htmlTree->itemCallback = function ($node) {
            $menuItem = $node->getMenuItem();
            $href = $this->getService('Url\UrlGenerator')->generate($menuItem->route);
            return sprintf('<a href="%s">%s</a>', $href, $menuItem->title);
        };
        return $htmlTree->render($this->getService('Request')->getRoute());
    }

    /**
     * @param array $options
     * @return string
     */
    public function functionSitemap($options)
    {
        $options = (array)$options;
        extract($options); // showHidden, route, maxDepth, class
        $showHidden = isset($showHidden) ? (bool) $showHidden : false;
        $route = isset($route) ? (string)$route : '';
        $maxDepth = isset($maxDepth) ? (int)$maxDepth : -1;
        $class = isset($class) ? (string)$class : 'sitemap';

        $branch = $this->getService('Menu\Page\Node')->findByRoute($route);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);
        $filterIterator = new Herbie\Menu\Page\Iterator\FilterIterator($treeIterator);
        $filterIterator->setEnabled(!$showHidden);

        $htmlTree = new Herbie\Menu\Page\Renderer\HtmlTree($filterIterator);
        $htmlTree->setMaxDepth($maxDepth);
        $htmlTree->setClass($class);
        $htmlTree->itemCallback = function ($node) {
            $menuItem = $node->getMenuItem();
            $href = $this->getService('Url\UrlGenerator')->generate($menuItem->route);
            return sprintf('<a href="%s">%s</a>', $href, $menuItem->title);
        };
        return $htmlTree->render();
    }

    /**
     * @param string $route
     * @param string $label
     * @param array $htmlAttributes
     * @return string
     */
    protected function createLink($route, $label, $htmlAttributes = [])
    {
        $url = $this->getService('Url\UrlGenerator')->generate($route);
        $attributesAsString = $this->buildHtmlAttributes($htmlAttributes);
        return sprintf('<a href="%s"%s>%s</a>', $url, $attributesAsString, $label);
    }

}
