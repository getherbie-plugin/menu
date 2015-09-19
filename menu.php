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
        $options = array_merge([
            'maxdepth' => -1,
            'route' => '',
            'showhidden' => false
        ], (array)$options);

        // accept valid flags only
        $options['showhidden'] = filter_var($options['showhidden'], FILTER_VALIDATE_BOOLEAN);

        $branch = DI::get('Menu\Page\Node')->findByRoute($options['route']);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);
        $filterIterator = new Herbie\Menu\Page\Iterator\FilterIterator($treeIterator);
        $filterIterator->setEnabled(!$options['showhidden']);

        $asciiTree = new Herbie\Menu\Page\Renderer\AsciiTree($filterIterator);
        $asciiTree->setMaxDepth($options['maxdepth']);
        return $asciiTree->render();
    }

    /**
     * @param array $options
     * @return string
     */
    public static function functionBreadcrumb($options)
    {
        $options = array_merge([
            'delim' => '',
            'homelabel' => 'Home',
            'homeurl' => null,
            'reverse' => false
        ], (array)$options);

        // accept valid flags only
        $options['reverse'] = filter_var($options['reverse'], FILTER_VALIDATE_BOOLEAN);

        $links = [];

        if (isset($options['homeurl'])) {
            $links[] = static::createLink($options['homeurl'], $options['homelabel']);
        }

        foreach (DI::get('Menu\Page\RootPath') as $item) {
            $links[] = static::createLink($item->route, $item->getMenuTitle());
        }

        if ($options['reverse']) {
            $links = array_reverse($links);
        }

        $html = '<ul class="breadcrumb">';
        foreach ($links as $i => $link) {
            if ($i > 0 && !empty($options['delim'])) {
                $html .= '<li class="delim">' . $options['delim'] . '</li>';
            }
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
        $options = array_merge([
            'class' => 'menu',
            'maxdepth' => -1,
            'route' => '',
            'showhidden' => false
        ], (array)$options);

        // accept valid flags only
        $options['showhidden'] = filter_var($options['showhidden'], FILTER_VALIDATE_BOOLEAN);

        $branch = DI::get('Menu\Page\Node')->findByRoute($options['route']);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);

        // using FilterCallback for better filtering of nested items
        $routeLine = DI::get('Request')->getRouteLine();
        $callback = [new Herbie\Menu\Page\Iterator\FilterCallback($routeLine, $options['showhidden']), 'call'];
        $filterIterator = new \RecursiveCallbackFilterIterator($treeIterator, $callback);

        $htmlTree = new Herbie\Menu\Page\Renderer\HtmlTree($filterIterator);
        $htmlTree->setMaxDepth($options['maxdepth']);
        $htmlTree->setClass($options['class']);
        $htmlTree->itemCallback = function ($node) {
            $menuItem = $node->getMenuItem();
            $href = DI::get('Url\UrlGenerator')->generate($menuItem->route);
            return sprintf('<a href="%s">%s</a>', $href, $menuItem->getMenuTitle());
        };
        return $htmlTree->render(DI::get('Request')->getRoute());
    }

    /**
     * @param array $options
     * @return string
     */
    public static function functionSitemap($options)
    {
        $options = array_merge([
            'class' => 'sitemap',
            'maxdepth' => -1,
            'route' => '',
            'showhidden' => false
        ], (array)$options);

        // accept valid flags only
        $options['showhidden'] = filter_var($options['showhidden'], FILTER_VALIDATE_BOOLEAN);

        $branch = DI::get('Menu\Page\Node')->findByRoute($options['route']);
        $treeIterator = new Herbie\Menu\Page\Iterator\TreeIterator($branch);
        $filterIterator = new Herbie\Menu\Page\Iterator\FilterIterator($treeIterator);
        $filterIterator->setEnabled(!$options['showhidden']);

        $htmlTree = new Herbie\Menu\Page\Renderer\HtmlTree($filterIterator);
        $htmlTree->setMaxDepth($options['maxdepth']);
        $htmlTree->setClass($options['class']);
        $htmlTree->itemCallback = function ($node) {
            $menuItem = $node->getMenuItem();
            $href = DI::get('Url\UrlGenerator')->generate($menuItem->route);
            return sprintf('<a href="%s">%s</a>', $href, $menuItem->getMenuTitle());
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
