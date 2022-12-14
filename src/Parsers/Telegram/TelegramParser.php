<?php

namespace TelegramApiParser\Parsers\Telegram;

use DateTimeImmutable;
use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use TelegramApiParser\Parsers\ParserInterface;
use TelegramApiParser\Parsers\ResponseParseInterface;

class TelegramParser implements ParserInterface
{
    const AVAILABLE_GROUPS = [
        'Getting updates',
        'Available types',
        'Available methods',
        'Updating messages',
        'Stickers',
        'Inline mode',
        'Payments',
        'Telegram Passport',
        'Games',
    ];

    /**
     * Ссылка на документацию
     * @var string
     */
    private string $documentation_url;

    public function __construct(string $documentation_url)
    {
        $this->documentation_url = $documentation_url;
    }

    /**
     * @throws InvalidSelectorException
     */
    public function handle(): ResponseParseInterface
    {
        $response = new TelegramResponse();
        $document = new Document($this->documentation_url, true);

        foreach($this->navigationBlocks($document) as $block) {
            $context = $block['document']->first('div');

            $description = $this->findToNext($context, $context, 'h4');

            $item = new TelegramResponseBlock($block['name'], $description->text());

            /* Получаем методы блока */
            $methods = $context->find('h4');
            foreach ($methods as $method) {
                $method_block = $this->findToNext($context, $method);
                $method_description = $this->findToNext($method_block->first('div'), stopTag: 'table');

                $method = new TelegramResponseBlock(
                    $method->text(),
                    $method_description->text(),
                    $this->table($method_block->first('table'))
                );

                $item->push($method);
            }

            $response[] = $item;
        }

        return $response;
    }

    /**
     * Дата последнего обновления API
     * @return DateTimeImmutable|null
     * @throws InvalidSelectorException
     */
    public function latestDate(): ?string
    {
        $document = new Document($this->documentation_url, true);
        return $document->first('h4')?->text();
    }

    /**
     * Версия Telegram Bot API
     * @return string|null
     * @throws InvalidSelectorException
     */
    public function version(): ?string
    {
        $document = new Document($this->documentation_url, true);
        $latest_date = $document->first('h4');

        return $latest_date->nextSibling('p')?->text();
    }

    /**
     * Разделит страницу на блоки навигации
     * @param Document $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function navigationBlocks(Document $document): array
    {
        $blocks = [];

        $context = $document->first('#dev_page_content');

        foreach ($context->children() as $node) {
            $isNextBlock = $node->isElementNode() && $node->tagName() == 'h3';

            if ($isNextBlock && (!self::AVAILABLE_GROUPS || in_array($node->text(), self::AVAILABLE_GROUPS))) {
                $blocks[] = [
                    'name' => $node->text(),
                    'document' => $this->findToNext($context, $node)
                ];
            }
        }

        return $blocks;
    }

    /**
     * Парсинг данных из таблицы
     * @param Element|null $table
     * @return array
     * @throws InvalidSelectorException
     */
    private function table(Element $table = null): array
    {
        $result = [];

        if (!$table || $table->tagName() != 'table') return $result;

        $keys = [];
        foreach ($table->first('thead')->find('th') as $column) {
            $keys[] = trim(strtolower($column->text()));
        }

        foreach ($table->first('tbody')->find('tr') as $index => $row) {
            foreach ($row->find('td') as $num => $column) {
                $result[$index][$keys[$num]] = trim($column->text());
            }
        }

        return $result;
    }

    /**
     * Соберет новый документ, между $element и $stopTag или следующий за $element.tagName().
     *
     * @param Element $context
     * @param Element|null $element
     * @param string|null $stopTag
     * @return Document
     */
    private function findToNext(Element $context, Element $element = null, string $stopTag = null): Document
    {
        $block = new Element('div');

        $start = (bool) $stopTag;

        foreach ($context->children() as $node) {
            if (!$stopTag && $node->text() == $element->text()) {
                $stopTag = $node->tagName();
                $start = true;
                continue;
            }

            if ($start) {
                if ($node->isElementNode() && $node->tagName() == $stopTag) break;
                $block->appendChild($node);
            }
        }

        return new Document($block->html());
    }
}
