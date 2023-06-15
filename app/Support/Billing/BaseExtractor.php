<?php

namespace App\Support\Billing;

use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\StrictException;
use Psr\Http\Message\ResponseInterface;

class BaseExtractor
{
    /**
     * Parse HTML.
     *
     * @param ResponseInterface|string $html
     * @return Dom
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws StrictException
     */
    protected static function parseHtml(ResponseInterface|string $html): Dom
    {
        $htmlString = $html instanceof ResponseInterface ? $html->getBody()->getContents() : $html;
        $dom = new Dom;
        return $dom->loadStr($htmlString);
    }
}
