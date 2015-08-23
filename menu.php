<?php

use Herbie\DI;
use Herbie\Hook;

class MenuPlugin
{

    public static function install()
    {
        Hook::attach('twigInitialized', ['MenuPlugin', 'addTwigFunctions']);
        Hook::attach('shortcodeInitialized', ['MenuPlugin', 'addShortcodes']);
    }

    public static function addTwigFunctions($twig)
    {
        $options = ['is_safe' => ['html']];
        $twig->addFunction(
            new \Twig_SimpleFunction('menu_ascii', ['MenuPlugin', 'functionAscii'], $options)
        );
        $twig->addFunction(
            new \Twig_SimpleFunction('menu_breadcrumb', ['MenuPlugin', 'functionBreadcrumb'], $options)
        );
        $twig->addFunction(
            new \Twig_SimpleFunction('menu_html', ['MenuPlugin', 'functionHtml'], $options)
        );
        $twig->addFunction(
            new \Twig_SimpleFunction('menu_sitemap', ['MenuPlugin', 'functionSitemap'], $options)
        );
    }

    public static function addShortcodes($shortcode)
    {
        $shortcode->add('menu_ascii', ['MenuPlugin', 'functionAsciiTree']);
        $shortcode->add('menu_breadcrumb', ['MenuPlugin', 'functionBreadcrumb']);
        $shortcode->add('menu_html', ['MenuPlugin', 'functionHtml']);
        $shortcode->add('menu_sitemap', ['MenuPlugin', 'functionSitemap']);
    }

    /**
     * @param array $options
     * @return string
     */
    public static function functionAsciiTree($options)
    {
        $options = (array)$options;
        extract($options); // showHidden, route, maxDepth, class
        $showHidden = isset($showHidden) ? (bool) $showHidden : false;
        $route = isset($route) ? (string)$route : '';
        $maxDepth = isset($maxDepth) ? (int)$maxDepth : -1;
        $class = isset($class) ? (string)$class : 'sitemap';

        $branch = DI::get('Menu\Page\Node')->findByRoute($route);
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
    public static function functionBreadcrumb($options)
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
            $links[] = static::createLink($route, $label);
        }

        foreach (DI::get('Menu\Page\RootPath') as $item) {
            $links[] = static::createLink($item->route, $item->title);
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
    public static function functionHtml($options)
    {
        $options = (array)$options;
        extract($options); // showHidden, route, maxDepth, class
        $showHidden = isset($showHidden) ? (bool)$showHidden : false;
        $route = isset($route) ? (string)$route : '';
        $maxDepth = isset($maxDepth) ? (int)$maxDepth : -1;
        $class = isset($class) ? (string)$class : 'menu';

        $branch = DI::get('Menu\Page\Node')->findByRoute($route);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);

        // using FilterCallback for better filtering of nested items
        $routeLine = DI::get('Request')->getRouteLine();
        $callback = [new Herbie\Menu\Page\Iterator\FilterCallback($routeLine, $showHidden), 'call'];
        $filterIterator = new \RecursiveCallbackFilterIterator($treeIterator, $callback);

        $htmlTree = new Herbie\Menu\Page\Renderer\HtmlTree($filterIterator);
        $htmlTree->setMaxDepth($maxDepth);
        $htmlTree->setClass($class);
        $htmlTree->itemCallback = function ($node) {
            $menuItem = $node->getMenuItem();
            $href = DI::get('Url\UrlGenerator')->generate($menuItem->route);
            return sprintf('<a href="%s">%s</a>', $href, $menuItem->title);
        };
        return $htmlTree->render(DI::get('Request')->getRoute());
    }

    /**
     * @param array $options
     * @return string
     */
    public static function functionSitemap($options)
    {
        $options = (array)$options;
        extract($options); // showHidden, route, maxDepth, class
        $showHidden = isset($showHidden) ? (bool) $showHidden : false;
        $route = isset($route) ? (string)$route : '';
        $maxDepth = isset($maxDepth) ? (int)$maxDepth : -1;
        $class = isset($class) ? (string)$class : 'sitemap';

        $branch = DI::get('Menu\Page\Node')->findByRoute($route);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);
        $filterIterator = new Herbie\Menu\Page\Iterator\FilterIterator($treeIterator);
        $filterIterator->setEnabled(!$showHidden);

        $htmlTree = new Herbie\Menu\Page\Renderer\HtmlTree($filterIterator);
        $htmlTree->setMaxDepth($maxDepth);
        $htmlTree->setClass($class);
        $htmlTree->itemCallback = function ($node) {
            $menuItem = $node->getMenuItem();
            $href = DI::get('Url\UrlGenerator')->generate($menuItem->route);
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
    protected static function createLink($route, $label, $htmlAttributes = [])
    {
        $url = DI::get('Url\UrlGenerator')->generate($route);
        $attributesAsString = static::buildHtmlAttributes($htmlAttributes);
        return sprintf('<a href="%s"%s>%s</a>', $url, $attributesAsString, $label);
    }

    /**
     * @param array $htmlOptions
     * @return string
     */
    protected static function buildHtmlAttributes($htmlOptions = [])
    {
        $attributes = '';
        foreach ($htmlOptions as $key => $value) {
            $attributes .= $key . '="' . $value . '" ';
        }
        return trim($attributes);
    }

}

MenuPlugin::install();
