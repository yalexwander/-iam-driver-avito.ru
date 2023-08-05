<?php

namespace ItIsAllMail\Driver;

use ItIsAllMail\Interfaces\FetchDriverInterface;
use ItIsAllMail\DriverCommon\AbstractFetcherDriver;
use ItIsAllMail\Factory\CatalogDriverFactory;
use ItIsAllMail\HtmlToText;
use ItIsAllMail\CoreTypes\SerializationMessage;
use ItIsAllMail\Utils\CurlImpersonatedBrowser;
use ItIsAllMail\Utils\Debug;
use ItIsAllMail\Utils\URLProcessor;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDom;
use voku\helper\SimpleHtmlDomInterface;
use ItIsAllMail\CoreTypes\Source;
use ItIsAllMail\Config\FetcherSourceConfig;

class AvitoFetcherDriver extends AbstractFetcherDriver implements FetchDriverInterface
{
    protected string $driverCode = "avito.ru";
    protected \DateTimeInterface $defaultCommentDate;

    public function __construct(array $appConfig, array $opts)
    {
        parent::__construct($appConfig, $opts);

        $this->defaultCommentDate = new \DateTime();
    }

    /**
     * Return array of all posts in thread, including original article
     */
    public function getPosts(Source $source): array
    {
        $posts = [];

        $rootId = md5($source["url"]) . "@" . $this->getCode();
        $posts[] = new SerializationMessage([
            "from"    => "iam",
            "subject" => "Search " . $source["url"],
            "parent"  => null,
            "created" => $this->defaultCommentDate,
            "id"      => $rootId,
            "body"    => $source["url"],
            "thread"  => $rootId,
            "uri"     => $source["url"],
        ]);

        $sourceConfig = new FetcherSourceConfig($this->appConfig, $this, $source);

        $html = CurlImpersonatedBrowser::getAsString($source["url"], [], $sourceConfig->getOpt('curl_impersonated_bin'));

        // speed hack start
        $narrow1 = strpos($html, '<div class="items-items-');
        $narrow2 = strpos($html, '<div class="js-pages pagination-pagination-', $narrow1);
        $html = substr($html, $narrow1, $narrow2 - $narrow1);
        // speed hack end

        $dom = HtmlDomParser::str_get_html($html);

        $cssPostClass = $this->findCSSClass($html, 'iva-item-root');
        $cssSellerClass = $this->findCSSClass($html, 'iva-item-sellerInfo');
        $cssSellerNameClass = $this->findCSSClass($html, 'style-title');
        $cssPhotos = $this->findCSSClass($html, 'photo-slider-item');

        foreach ($dom->findMulti("div.${cssPostClass}") as $node) {
            $body = $node->findOne('meta[itemprop="description"]')->getAttribute('content');

            $subject = $body;

            $price = $node->findOneOrFalse('meta[itemprop="price"]');
            if ($price) {
                $subject = '[' .  sprintf("%04d", intval($price->getAttribute('content'))) . '] ' . $subject;
            }

            $from = "noname@" . $this->getCode();
            $seller = $node->findOneOrFalse("div.${cssSellerClass}")->findOneOrFalse("div.${cssSellerNameClass}");
            if ($seller) {
                $from = $seller->text() . " <seller@" . $this->getCode() . ">";
            }

            $postId = $node->getAttribute('data-item-id');

            $threadId = $postId;
            $uri = 'http://' . $this->getCode() . $node->findOne('a')->getAttribute("href");
            $body .= "\n\n[ $uri ]\n";

            $msg = new SerializationMessage([
                "from"    => $from,
                "subject" => $subject,
                "parent"  => $rootId,
                "created" => $this->defaultCommentDate,
                "id"      => $postId . "@" . $this->getCode(),
                "body"    => $body,
                "thread"  => $threadId  . "@" . $this->getCode(),
                "uri"     => $uri,
            ]);

            if (! $this->messageWithGivenIdAlreadyDownloaded($postId . "@" . $this->getCode())) {
                $this->processPostAttachements($node, $msg, $sourceConfig);
            }

            $posts[] = $msg;
        }

        return $posts;
    }

    protected function findCSSClass(string $html, string $prefix): string
    {
        $pos1 = strpos($html, $prefix . "-");

        $pos2 = strpos($html, ' ', $pos1);
        $pos3 = strpos($html, '"', $pos1);
        $pos2 = ($pos3 < $pos2) ? $pos3 : $pos2;

        return substr($html, $pos1, ($pos2 - $pos1));
    }

    protected function processPostAttachements(
        SimpleHtmlDomInterface $postNode,
        SerializationMessage $msg,
        FetcherSourceConfig $sourceConfig
    ): void {
        $image = $postNode->findOneOrFalse("img");
        if ($image) {
            $image = $image->getAttribute("src");
        } else {
            if (preg_match('/data-marker="slider-image\/image-([^\"]+)/', $postNode->html(), $found)) {
                $image = $found[1];
            }
        }

        if (! $image) {
            return;
        }

        if ($sourceConfig->getOpt('download_attachements') !== "none") {
            Debug::debug("Downloading attachement: " . $image);

            $msg->addAttachement(
                "image.jpg",
                CurlImpersonatedBrowser::getAsString($image, [], $sourceConfig->getOpt('curl_impersonated_bin')),
                TYPEIMAGE,
                'jpeg'
            );
        }
    }
}
